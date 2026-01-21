<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlanningAlgo
{
    private const DURATION_MIN = 5;

    public static function resetAppointments(Connection $conn, int $forum_id): void
    {
        $conn->executeStatement(
            'UPDATE appointment
                SET appointment_time = NULL,
                appointment_request = true,
                duration = 0
                WHERE forum_id = ?',
            [$forum_id]
        );
    }

    // =====================================
    // 1️⃣ Génération des créneaux
    // =====================================
    private static function generateSlots(array $availability, array $companySlotDuration, array $existing_appointments = []): array
    {
        $all_slots = [];
        $used_times_by_company = [];

        // Collecter les créneaux déjà utilisés par entreprise
        foreach ($existing_appointments as $appointment) {
            if (!empty($appointment['appointment_time'])) {
                $used_times_by_company[$appointment['company_name']][] = $appointment['appointment_time'];
            }
        }

        foreach ($availability as $company => $windows) {
            if (!isset($companySlotDuration[$company])) continue;

            $duration = min($companySlotDuration[$company], 20);
            $used_times = array_flip($used_times_by_company[$company] ?? []);

            foreach ($windows as $window) {
                $start = new \DateTimeImmutable($window['start']);
                $end = new \DateTimeImmutable($window['end']);
                $current = $start;
                $workedMinutes = 0;

                // Pré-calcul des pauses pour ce jour
                $pauseStart = $current->setTime(12, 0);
                $pauseEnd = $current->setTime(13, 30);

                while ($current < $end) {
                    // Pause après 2h de travail
                    if ($workedMinutes >= 120) {
                        $current = $current->modify('+15 minutes');
                        $workedMinutes = 0;
                        continue;
                    }

                    $slot_end = $current->modify("+{$duration} minutes");

                    // Pause méridienne
                    if ($current < $pauseEnd && $slot_end > $pauseStart) {
                        $current = $pauseEnd;
                        $workedMinutes = 0;
                        continue;
                    }

                    if ($slot_end > $end) $slot_end = $end;

                    $slotMinutes = ($slot_end->getTimestamp() - $current->getTimestamp()) / 60;
                    if ($slotMinutes >= 1) {
                        $time_str = $current->format('Y-m-d H:i:s');
                        if (!isset($used_times[$time_str])) {
                            $all_slots[] = [
                                'company' => $company,
                                'start' => $time_str,
                                'end' => $slot_end->format('Y-m-d H:i:s'),
                                'used' => false,
                                'duration' => $slotMinutes
                            ];
                            $used_times[$time_str] = true;
                        }
                    }

                    $current = $slot_end;
                    $workedMinutes += $slotMinutes;
                }
            }
        }

        usort($all_slots, fn($a, $b) => strcmp($a['start'], $b['start']));

        return $all_slots;
    }


    // =====================================
    // 2️⃣ Fonction principale
    // =====================================
    public static function generatePlanning(Connection $conn, int $forum_id): array
    {
        $data = self::fetchForumData($conn, $forum_id);
        if (!$data) return ['success'=>false, 'message'=>'Forum ou demandes introuvables', 'appointments'=>[]];

        $filtered = self::filterRequests($data['requests'], $data['windows']);
        $filtered_requests = $filtered['filtered'];
        $rejected_requests = $filtered['rejected'];

        if (empty($filtered_requests)) {
            return ['success'=>false,'message'=>'Aucune demande valide','appointments'=>[],'rejected_requests'=>$rejected_requests];
        }

        $org = self::organizeRequestsByStudent($filtered_requests);
        $all_student_requests = $org['students'];
        $company_demand = $org['company_demand'];

        $availability = [];
        foreach ($data['windows'] as $w) {
            $cname = $w['company_name'];
            $availability[$cname][] = ['start'=>$w['start_time'], 'end'=>$w['end_time']];
        }

        $slots_data = self::computeCompanySlots($availability, $company_demand);
        $slots_by_company = $slots_data['slots'];
        $blockedCompanies = $slots_data['blocked'];

        $assign_data = self::assignAppointments($all_student_requests, $slots_by_company, $blockedCompanies, $forum_id);
        $assignments = $assign_data['assignments'];
        $unassigned_requests = $assign_data['unassigned_requests'];

        $inserted = self::saveAppointments($conn, $assignments, $forum_id);

        $assigned_count = count($assignments);
        $total_valid_wishes = count($filtered_requests);
        $success_rate = $total_valid_wishes > 0
            ? round(($assigned_count / $total_valid_wishes) * 100, 1)
            : 0;


        usort($inserted, function($a, $b) {
            $cmp = strcmp($a['company_name'], $b['company_name']);
            if ($cmp !== 0) return $cmp;

            if ($a['appointment_time'] === null) return 1;
            if ($b['appointment_time'] === null) return -1;

            return strcmp($a['appointment_time'], $b['appointment_time']);
        });


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

    // =====================================
    // 3️⃣ Fonctions secondaires
    // =====================================

    private static function fetchForumData(Connection $conn, int $forum_id): ?array
    {
        try {
            $forum = $conn->fetchAssociative('SELECT * FROM forum WHERE forum_id = ?', [$forum_id]);
            if (empty($forum)) return null;

            $windows = $conn->fetchAllAssociative(
                'SELECT ip.company_name, ip.start_time, ip.end_time, ip.search_type, ip.search_level
                 FROM is_present ip
                 WHERE ip.forum_id = ? ORDER BY ip.company_name, ip.start_time',
                [$forum_id]
            );

            $requests = $conn->fetchAllAssociative(
                'SELECT a.user_id, a.company_name, u.user_firstname, u.user_lastname, u.user_role, u.user_level
                 FROM appointment a
                 INNER JOIN users u ON u.user_id = a.user_id
                 WHERE a.forum_id = ? AND a.appointment_request = ? ORDER BY a.user_id, a.company_name',
                [$forum_id, 1]
            );

            return ['forum'=>$forum, 'windows'=>$windows, 'requests'=>$requests];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function filterRequests(array $requests, array $windows): array
    {
        $company_search_types = [];
        $company_search_levels = [];
        foreach ($windows as $w) {
            $company_search_types[$w['company_name']] = $w['search_type'];
            $company_search_levels[$w['company_name']] = $w['search_level'];
        }

        $filtered_requests = [];
        $rejected_requests = [];
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
                if (!$is_role_match) $reason[] = "Rôle ($user_role) ne correspond pas ($search_type)";
                if (!$is_level_match) $reason[] = "Niveau ($user_level) ne correspond pas ($search_level)";
                $rejected_requests[] = array_merge($request, ['reason'=>implode(' | ', $reason)]);
            }
        }
        return ['filtered'=>$filtered_requests, 'rejected'=>$rejected_requests];
    }

    private static function organizeRequestsByStudent(array $filtered_requests): array
    {
        $all_student_requests = [];
        $company_demand = [];
        foreach ($filtered_requests as $request) {
            $user_id = $request['user_id'];
            $company = $request['company_name'];
            if (!isset($all_student_requests[$user_id])) {
                $all_student_requests[$user_id] = [
                    'user_id'=>$user_id,
                    'firstname'=>$request['user_firstname'],
                    'lastname'=>$request['user_lastname'],
                    'role'=>$request['user_role'],
                    'level'=>$request['user_level'],
                    'requests'=>[]
                ];
            }
            $all_student_requests[$user_id]['requests'][] = $company;
            $company_demand[$company] = ($company_demand[$company] ?? 0) + 1;
        }
        return ['students'=>$all_student_requests, 'company_demand'=>$company_demand];
    }

    private static function computeCompanySlots(array $availability, array $company_demand): array
    {
        $slots_by_company = [];
        $companySlotDuration = [];
        $blockedCompanies = [];

        foreach ($availability as $companyName => $windows) {
            $demandCount = $company_demand[$companyName] ?? 0;
            if ($demandCount <= 0) continue;

            $baseSlots = self::generateSlots([$companyName=>$windows], [$companyName=>self::DURATION_MIN], []);
            $totalAvailableMinutes = count($baseSlots) * self::DURATION_MIN;

            if ($totalAvailableMinutes < $demandCount * self::DURATION_MIN) {
                $blockedCompanies[$companyName] = [
                    'reason'=>'Temps réel insuffisant',
                    'capacity'=>intdiv($totalAvailableMinutes,self::DURATION_MIN),
                    'demand'=>$demandCount
                ];
                continue;
            }

            $slotDuration = max(self::DURATION_MIN, min(20, intdiv($totalAvailableMinutes,$demandCount)));
            $slotDuration = intdiv($slotDuration,5)*5;

            $finalSlots = self::generateSlots([$companyName=>$windows], [$companyName=>$slotDuration], []);

            if (count($finalSlots) < $demandCount) {
                $adjustedDuration = max(self::DURATION_MIN, intdiv(count($finalSlots)*$slotDuration,$demandCount));
                $adjustedDuration = min(20, intdiv($adjustedDuration,5)*5);
                $finalSlots = self::generateSlots([$companyName=>$windows], [$companyName=>$adjustedDuration], []);
                $slotDuration = $adjustedDuration;

                if (count($finalSlots) < $demandCount) {
                    $blockedCompanies[$companyName] = [
                        'reason'=>'Impossible après ajustement',
                        'capacity'=>count($finalSlots),
                        'demand'=>$demandCount
                    ];
                    continue;
                }
            }

            $slots_by_company[$companyName] = $finalSlots;
            $companySlotDuration[$companyName] = $slotDuration;
        }

        return ['slots'=>$slots_by_company, 'blocked'=>$blockedCompanies];
    }

    private static function assignAppointments(array $all_student_requests, array $slots_by_company, array $blockedCompanies, int $forum_id): array
    {
        $assignments = [];
        $unassigned_requests = [];

        // Organiser les demandes par entreprise
        $requests_by_company = [];
        foreach ($all_student_requests as $student_id => $student) {
            foreach ($student['requests'] as $company) {
                $requests_by_company[$company][] = [
                    'student_id' => $student_id,
                    'student' => $student
                ];
            }
        }

        foreach ($requests_by_company as $company => $requests) {

            usort($requests, function ($a, $b) {
                $rolePriority = [
                    'alternance' => 1,
                    'internship' => 2,
                ];
                $levelPriority = [
                    'M2' => 1,
                    'M1' => 2,
                    'B3' => 3,
                    'B2' => 4,
                    'B1' => 5,
                ];
                $roleA  = $a['student']['role']  ?? '';
                $roleB  = $b['student']['role']  ?? '';
                $levelA = $a['student']['level'] ?? '';
                $levelB = $b['student']['level'] ?? '';
                $cmpRole = ($rolePriority[$roleA] ?? 99) <=> ($rolePriority[$roleB] ?? 99);
                if ($cmpRole !== 0) {
                    return $cmpRole;
                }
                return ($levelPriority[$levelA] ?? 99) <=> ($levelPriority[$levelB] ?? 99);
            });


            if (isset($blockedCompanies[$company])) {
                foreach ($requests as $req) {
                    $student = $req['student'];
                    $unassigned_requests[] = [
                        'user_id' => $req['student_id'],
                        'company_name' => $company,
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'role' => $student['role'],
                        'reason' => $blockedCompanies[$company]['reason']
                    ];
                }
                continue;
            }

            if (!isset($slots_by_company[$company])) continue;

            foreach ($requests as $req) {
                $assigned = false;
                $student = $req['student'];

                foreach ($slots_by_company[$company] as &$slot) {
                    if ($slot['used']) continue;

                    // Vérifier si l'étudiant a déjà un RDV chez cette entreprise
                    $alreadyAssigned = array_filter($assignments, fn($a) =>
                        $a['user_id'] === $req['student_id'] && $a['company_name'] === $company
                    );
                    if (!empty($alreadyAssigned)) break;

                    // Assignation du slot
                    $assignments[] = [
                        'user_id' => $req['student_id'],
                        'company_name' => $company,
                        'forum_id' => $forum_id,
                        'appointment_time' => $slot['start'],
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'duration' => $slot['duration'],
                        'role' => $student['role'],
                        'level' => $student['level']
                    ];
                    $slot['used'] = true;
                    $assigned = true;
                    break;
                }

                if (!$assigned) {
                    $unassigned_requests[] = [
                        'user_id' => $req['student_id'],
                        'company_name' => $company,
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'role' => $student['role'],
                        'level' => $student['level'],
                        'reason' => 'Plus de créneaux disponibles'
                    ];
                }
            }
        }

        return [
            'assignments' => $assignments,
            'unassigned_requests' => $unassigned_requests
        ];
    }



    private static function saveAppointments(Connection $conn, array $assignments, int $forum_id): array
    {
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

                        $updated = $conn->executeStatement(
                            'UPDATE appointment
                            SET appointment_time = ?, appointment_request = ?, duration = ?
                            WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                            [
                                $time,
                                0,
                                $assignment['duration'],
                                $forum_id,
                                $assignment['company_name'],
                                $assignment['user_id']
                            ]
                        );


                        if ($updated === 0) {
                            $conn->insert('appointment', [
                                'user_id' => $assignment['user_id'],
                                'forum_id' => $forum_id,
                                'company_name' => $assignment['company_name'],
                                'appointment_request' => 0,
                                'appointment_time' => $time,
                                'duration' => $assignment['duration']
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
            return [];
        }

        return $inserted;
    }

    // =====================================
    // 4️⃣ Match rôle / niveau
    // =====================================
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
