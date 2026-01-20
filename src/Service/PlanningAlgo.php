<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlanningAlgo
{
    private const SATURATION_CRITICAL = 100;
    private const SATURATION_HIGH = 90;
    private const SATURATION_MODERATE = 80;
    private const DURATION_MIN = 5;

    public static function resetAppointments(Connection $conn, int $forum_id): void
    {
        $conn->executeStatement(
            'UPDATE appointment
             SET appointment_time = NULL, appointment_request = true
             WHERE forum_id = ?',
            [$forum_id]
        );
    }

    private static function generateSlots(
        array $availability,
        array $companySlotDuration,
        array $existing_appointments = []
    ): array {
        $all_slots = [];
        $used_times_by_company = [];

        // Marquer les créneaux déjà utilisés
        foreach ($existing_appointments as $appointment) {
            if (!empty($appointment['appointment_time'])) {
                $dt = new \DateTime($appointment['appointment_time']);
                $used_times_by_company[$appointment['company_name']][] = $dt->format('Y-m-d H:i:s');
            }
        }

        foreach ($availability as $company => $windows) {
            if (!isset($companySlotDuration[$company])) continue;

            $duration = $companySlotDuration[$company];
            $used_times = $used_times_by_company[$company] ?? [];

            foreach ($windows as $window) {
                $start = new \DateTime($window['start']);
                $end   = new \DateTime($window['end']);

                $current = clone $start;
                $workedMinutes = 0;

                while ($current < $end) {
                    $slot_end = (clone $current)->modify("+{$duration} minutes");

                    // Pause déjeuner 12:00 → 13:30
                    $pauseStart = new \DateTime($current->format('Y-m-d') . ' 12:00');
                    $pauseEnd   = new \DateTime($current->format('Y-m-d') . ' 13:30');
                    if ($slot_end > $pauseStart && $current < $pauseEnd) {
                        $current = clone $pauseEnd;
                        $workedMinutes = 0;
                        continue;
                    }

                    // Pause 15 min toutes les 2h
                    if ($workedMinutes >= 120) {
                        $current->modify('+15 minutes');
                        $workedMinutes = 0;
                        continue;
                    }

                    // Ajouter le créneau si valide et pas déjà utilisé
                    if ($slot_end <= $end) {
                        $time_str = $current->format('Y-m-d H:i:s');
                        if (!in_array($time_str, $used_times, true)) {
                            $all_slots[] = [
                                'company'  => $company,
                                'start'    => $time_str,
                                'end'      => $slot_end->format('Y-m-d H:i:s'),
                                'used'     => false,
                                'duration' => $duration
                            ];
                            $used_times[] = $time_str;
                        }
                    }

                    $current->modify("+{$duration} minutes");
                    $workedMinutes += $duration;
                }
            }
        }

        // Trier par date de début
        usort($all_slots, fn($a, $b) => strcmp($a['start'], $b['start']));

        return $all_slots;
    }

    public static function generatePlanning(Connection $conn, int $forum_id): array
    {
        try {
            $forum = $conn->fetchAssociative('SELECT * FROM forum WHERE forum_id = ?', [$forum_id]);
            if (empty($forum)) {
                return ['success' => false, 'message' => 'Forum not found', 'appointments' => []];
            }

            $windows = $conn->fetchAllAssociative(
                'SELECT ip.company_name, ip.start_time, ip.end_time, ip.search_type, ip.search_level
                 FROM is_present ip
                 WHERE ip.forum_id = ?
                 ORDER BY ip.company_name, ip.start_time',
                [$forum_id]
            );

            $requests = $conn->fetchAllAssociative(
                'SELECT a.user_id, a.company_name, u.user_firstname, u.user_lastname, u.user_role, u.user_level
                 FROM appointment a
                 INNER JOIN users u ON u.user_id = a.user_id
                 WHERE a.forum_id = ? AND a.appointment_request = ?
                 ORDER BY a.user_id, a.company_name',
                [$forum_id, 1]
            );

            if (empty($requests)) {
                return ['success' => false, 'message' => 'Aucune demande de rendez-vous trouvée', 'appointments' => []];
            }

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'DB error: '.$e->getMessage(), 'appointments' => []];
        }

        $all_student_requests = [];
        $company_demand = [];
        $company_search_types = [];
        $company_search_levels = [];

        foreach ($windows as $w) {
            $company_search_types[$w['company_name']] = $w['search_type'];
            $company_search_levels[$w['company_name']] = $w['search_level'];
        }

        $filtered_requests = [];
        $rejected_requests = [];

        // Filtrer les demandes selon rôle et niveau
        foreach ($requests as $request) {
            $user_role = $request['user_role'];
            $user_level = $request['user_level'];
            $company = $request['company_name'];
            $search_type = $company_search_types[$company] ?? null;
            $search_level = $company_search_levels[$company] ?? null;

            $is_role_match = self::matchesSearchType($user_role, $search_type);
            $is_level_match = self::matchesSearchLevel($user_level, $search_level);

            if ($is_role_match && $is_level_match) {
                $filtered_requests[] = $request;
            } else {
                $reason = [];
                if (!$is_role_match) $reason[] = "Rôle étudiant ($user_role) ne correspond pas au critère ($search_type)";
                if (!$is_level_match) $reason[] = "Niveau étudiant ($user_level) ne correspond pas au niveau recherché ($search_level)";
                $rejected_requests[] = [
                    'user_id' => $request['user_id'],
                    'company_name' => $company,
                    'user_role' => $user_role,
                    'user_level' => $user_level,
                    'company_search_type' => $search_type,
                    'company_search_level' => $search_level,
                    'reason' => implode(' | ', $reason)
                ];
            }
        }

        // Organiser les demandes par étudiant
        foreach ($filtered_requests as $request) {
            $user_id = $request['user_id'];
            $company = $request['company_name'];
            if (!isset($all_student_requests[$user_id])) {
                $all_student_requests[$user_id] = [
                    'user_id' => $user_id,
                    'firstname' => $request['user_firstname'],
                    'lastname' => $request['user_lastname'],
                    'role' => $request['user_role'],
                    'level' => $request['user_level'],
                    'requests' => []
                ];
            }
            $all_student_requests[$user_id]['requests'][] = $company;
            $company_demand[$company] = ($company_demand[$company] ?? 0) + 1;
        }

        // Construire disponibilité par entreprise
        $availability = [];
        foreach ($windows as $w) {
            $cname = $w['company_name'];
            if (!isset($availability[$cname])) $availability[$cname] = [];
            $availability[$cname][] = ['start' => $w['start_time'], 'end' => $w['end_time']];
        }

        // ========================
        // Calcul des durées réelles et création des créneaux
        // ========================
        $companySlotDuration = [];
        $blockedCompanies = [];

        foreach ($availability as $companyName => $windows) {
            $demandCount = $company_demand[$companyName] ?? 0;
            if ($demandCount <= 0) continue;

            // 1️⃣ Générer les créneaux min 5min pour connaître la capacité réelle
            $baseSlots = self::generateSlots([$companyName => $windows], [$companyName => 5], []);
            $realMinutesAvailable = count($baseSlots) * 5;

            // 2️⃣ Bloquer si impossible même en 5 min
            if ($realMinutesAvailable < $demandCount * 5) {
                $blockedCompanies[$companyName] = [
                    'reason'   => 'Temps réel insuffisant (pauses incluses)',
                    'capacity' => intdiv($realMinutesAvailable, 5),
                    'demand'   => $demandCount
                ];
                continue;
            }

            // 3️⃣ Calcul de la durée exacte
            $slotDuration = intdiv($realMinutesAvailable, $demandCount);

            // Optionnel : arrondi multiple de 5 min
            $slotDuration = intdiv($slotDuration, 5) * 5;

            // 4️⃣ Re-générer les créneaux avec cette durée
            $finalSlots = self::generateSlots([$companyName => $windows], [$companyName => $slotDuration], []);
            if (count($finalSlots) < $demandCount) {
                $blockedCompanies[$companyName] = [
                    'reason'   => 'Impossible après ajustement de durée',
                    'capacity' => count($finalSlots),
                    'demand'   => $demandCount
                ];
                continue;
            }

            $companySlotDuration[$companyName] = $slotDuration;
        }


        // ========================
        // Assignation des RDV
        // ========================
        $assignments = [];
        $unassigned_requests = [];

        $requests_by_company_and_role = [];
        foreach ($all_student_requests as $student_id => $student) {
            foreach ($student['requests'] as $requested_company) {
                if (!isset($requests_by_company_and_role[$requested_company])) {
                    $requests_by_company_and_role[$requested_company] = ['internship'=>[],'alternance'=>[]];
                }
                $role = $student['role'];
                if ($role === 'internship' || $role === 'alternance') {
                    $requests_by_company_and_role[$requested_company][$role][] = ['student_id'=>$student_id,'student'=>$student];
                }
            }
        }

        foreach ($requests_by_company_and_role as $company => $roles_data) {
            if (isset($blockedCompanies[$company])) {
                foreach (array_merge($roles_data['internship'], $roles_data['alternance']) as $request_data) {
                    $student = $request_data['student'];
                    $unassigned_requests[] = [
                        'user_id' => $request_data['student_id'],
                        'company_name' => $company,
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'role' => $student['role'],
                        'reason' => $blockedCompanies[$company]['reason']
                    ];
                }
                continue;
            }

            foreach (['internship','alternance'] as $roleType) {
                foreach ($roles_data[$roleType] as $request_data) {
                    $student_id = $request_data['student_id'];
                    $student = $request_data['student'];
                    $assigned = false;

                    if (isset($slots_by_company[$company])) {
                        foreach ($slots_by_company[$company] as &$slot) {
                            if (!$slot['used']) {
                                $assignments[] = [
                                    'user_id' => $student_id,
                                    'company_name' => $company,
                                    'forum_id' => $forum_id,
                                    'appointment_time' => $slot['start'],
                                    'firstname' => $student['firstname'],
                                    'lastname' => $student['lastname'],
                                    'duration' => $slot['duration'],
                                    'role' => $student['role']
                                ];
                                $slot['used'] = true;
                                $assigned = true;
                                break;
                            }
                        }
                    }

                    if (!$assigned) {
                        $unassigned_requests[] = [
                            'user_id' => $student_id,
                            'company_name' => $company,
                            'firstname' => $student['firstname'],
                            'lastname' => $student['lastname'],
                            'role' => $student['role'],
                            'reason' => 'Plus de créneaux disponibles'
                        ];
                    }
                }
            }
        }

        // ========================
        // Sauvegarde en base
        // ========================
        $inserted = [];
        $conn->beginTransaction();
        try {
            foreach ($assignments as $assignment) {
                $time = $assignment['appointment_time'];
                $inserted_successfully = false;
                $retries = 0;

                while (!$inserted_successfully && $retries < 3) {
                    try {
                        $conflict = $conn->fetchOne(
                            'SELECT 1 FROM appointment WHERE appointment_time = ? AND forum_id = ?',
                            [$time, $forum_id]
                        );

                        if ($conflict !== false) {
                            $dt = new \DateTime($time);
                            $dt->modify('+'.($retries+1).' minutes');
                            $time = $dt->format('Y-m-d H:i:s');
                            $assignment['appointment_time'] = $time;
                        }

                        $updated = $conn->executeStatement(
                            'UPDATE appointment SET appointment_time = ?, appointment_request = ?
                             WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                            [$time, 0, $forum_id, $assignment['company_name'], $assignment['user_id']]
                        );

                        if ($updated === 0) {
                            $conn->insert('appointment', [
                                'user_id'=>$assignment['user_id'],
                                'forum_id'=>$forum_id,
                                'company_name'=>$assignment['company_name'],
                                'appointment_request'=>0,
                                'appointment_time'=>$time
                            ]);
                        }

                        $inserted[] = $assignment;
                        $inserted_successfully = true;
                    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                        $retries++;
                    } catch (\Throwable $e) {
                        $retries = 3;
                    }
                }
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            return ['success'=>false,'message'=>'Erreur lors de la sauvegarde: '.$e->getMessage(),'appointments'=>[]];
        }

        $total_requests = count($requests);
        $assigned_count = count($inserted);
        $success_rate = $total_requests > 0 ? round(($assigned_count / $total_requests) * 100,1) : 0;

        return [
            'success' => true,
            'status' => 'SUCCESS',
            'message' => "Taux de réussite : {$success_rate}%",
            'count' => $assigned_count,
            'appointments' => $inserted,
            'unassigned_requests' => $unassigned_requests,
            'rejected_requests' => $rejected_requests,
            'blocked_companies' => $blockedCompanies
        ];
    }

    private static function matchesSearchType(string $user_role, ?string $search_type): bool
    {
        if ($search_type === null) return false;
        if ($search_type === 'internship;alternance') return in_array($user_role,['internship','alternance']);
        return $user_role === $search_type;
    }

    private static function matchesSearchLevel(?string $user_level, ?string $search_level): bool
    {
        if ($search_level === null || $user_level === null) return false;
        return in_array($user_level, explode(';', $search_level));
    }
}
