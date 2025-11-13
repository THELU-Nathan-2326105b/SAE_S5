<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlanningAlgo
{

    public static function run(Connection $conn, $forum_id): array
    {
        // If forum_id is null-ish, try to pick the first forum available
    $windows = [];
    $students = [];

    try {
            if ($forum_id === null || $forum_id === '') {
                $first = $conn->fetchAssociative('SELECT forum_id FROM forum ORDER BY forum_id LIMIT 1');
                $forum_id = $first['forum_id'] ?? null;
            } else {
                $forum_id = is_numeric($forum_id) ? (int)$forum_id : $forum_id;
            }

            // Prefer students who requested appointments for this forum; fallback to all students
            $students = [];
            if ($forum_id !== null) {
                $students = $conn->fetchAllAssociative(
                    "SELECT DISTINCT u.user_id, u.user_firstname, u.user_lastname, u.user_level\n                     FROM users u\n                     INNER JOIN appointment a ON a.user_id = u.user_id\n                     WHERE a.forum_id = ? AND a.appointment_request = ?\n                     ORDER BY u.user_lastname, u.user_firstname",
                    [$forum_id, 1]
                );
            }

            if (empty($students)) {
                $students = $conn->fetchAllAssociative(
                    "SELECT user_id, user_firstname, user_lastname, user_level\n                     FROM users\n                     WHERE user_role IN ('internship','alternance')\n                     ORDER BY user_lastname, user_firstname"
                );
            }

            // Companies present at this forum
            $companies = [];
            if ($forum_id !== null) {
                $companies = $conn->fetchAllAssociative(
                    "SELECT ip.company_name, ip.start_time, ip.end_time\n                     FROM is_present ip\n                     WHERE ip.forum_id = ?\n                     ORDER BY ip.start_time",
                    [$forum_id]
                );
            }
        } catch (\Throwable $e) {
            // If any DB error, return empty planning — keep behavior safe
            return [];
        }

        $planning = [];

        // If no companies or no students, return empty (nothing to schedule)
        if (empty($companies) || empty($students)) {
            return $planning;
        }

        // Simple, safe mapping: assign students to companies round-robin and use company start_time
        $company_count = count($companies);
        foreach ($students as $i => $s) {
            $c = $companies[$i % $company_count];

            $appt_time = $c['start_time'] ?? null;
            if ($appt_time) {
                try {
                    $dt = new \DateTime($appt_time);
                    $appt_time = $dt->format('H:i');
                } catch (\Throwable $e) {
                    // leave raw if parsing fails
                }
            }

            // Return keys matching the Twig template expectations (`user_firstname`, `user_lastname`)
            $planning[] = [
                'user_id' => $s['user_id'],
                'user_firstname' => $s['user_firstname'],
                'user_lastname' => $s['user_lastname'],
                'company_name' => $c['company_name'],
                'appointment_time' => $appt_time,
            ];
        }

        return $planning;
    }

public static function generatePlanning(Connection $conn, int $forum_id): array
{
    try {
        $forum = $conn->fetchAssociative('SELECT * FROM forum WHERE forum_id = ?', [$forum_id]);
        if (empty($forum)) {
            return ['success' => false, 'message' => 'Forum not found', 'appointments' => []];
        }

        // 1. Charger les entreprises présentes avec leurs disponibilités
        $windows = $conn->fetchAllAssociative(
            'SELECT ip.company_name, ip.start_time, ip.end_time
             FROM is_present ip
             WHERE ip.forum_id = ?
             ORDER BY ip.company_name, ip.start_time',
            [$forum_id]
        );

        // 2. Charger les DEMANDES de rendez-vous (appointment_request = 1)
        $requests = $conn->fetchAllAssociative(
            'SELECT a.user_id, a.company_name, u.user_firstname, u.user_lastname, u.user_level, u.user_role
             FROM appointment a
             INNER JOIN users u ON u.user_id = a.user_id
             WHERE a.forum_id = ? AND a.appointment_request = ?
             ORDER BY a.user_id, a.company_name',
            [$forum_id, 1]
        );

        if (empty($requests)) {
            return [
                'success' => false, 
                'message' => 'Aucune demande de rendez-vous trouvée pour ce forum',
                'appointments' => []
            ];
        }

    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'DB error: '.$e->getMessage(), 'appointments' => []];
    }

    // 3. Structurer les données : étudiants avec leurs entreprises demandées
    $students_requests = [];
    foreach ($requests as $request) {
        $user_id = $request['user_id'];
        if (!isset($students_requests[$user_id])) {
            $students_requests[$user_id] = [
                'user_id' => $user_id,
                'firstname' => $request['user_firstname'],
                'lastname' => $request['user_lastname'],
                'level' => $request['user_level'],
                'role' => $request['user_role'],
                'requested_companies' => [],
                'priority' => 0
            ];
        }
        $students_requests[$user_id]['requested_companies'][] = $request['company_name'];
    }

    // 4. Structurer les disponibilités par entreprise
    $availability = [];
    foreach ($windows as $w) {
        $cname = $w['company_name'];
        if (!isset($availability[$cname])) {
            $availability[$cname] = [];
        }
        $availability[$cname][] = ['start' => $w['start_time'], 'end' => $w['end_time']];
    }

    // 5. Analyser la demande par entreprise
    $company_demand = [];
    foreach ($students_requests as $student) {
        foreach ($student['requested_companies'] as $company) {
            if (!isset($company_demand[$company])) {
                $company_demand[$company] = 0;
            }
            $company_demand[$company]++;
        }
    }

    // 6. Calculer la capacité par entreprise
    $company_capacity = [];
    foreach ($availability as $cname => $windows) {
        $total_minutes = 0;
        foreach ($windows as $window) {
            try {
                $start = new \DateTime($window['start']);
                $end = new \DateTime($window['end']);
                $diff = $end->getTimestamp() - $start->getTimestamp();
                $total_minutes += floor($diff / 60);
            } catch (\Throwable $e) {
                continue;
            }
        }
        $company_capacity[$cname] = $total_minutes;
    }

    // 7. Identifier les entreprises surbookées
    $overbooked_companies = [];
    foreach ($company_demand as $company => $demand) {
        if (isset($company_capacity[$company])) {
            $max_slots = floor($company_capacity[$company] / 15);
            if ($demand > $max_slots) {
                $overbooked_companies[$company] = [
                    'demand' => $demand,
                    'capacity' => $max_slots,
                    'overflow' => $demand - $max_slots
                ];
            }
        }
    }

    // 8. Attribuer un score de priorité aux étudiants
    foreach ($students_requests as &$student) {
        $student['priority'] = 0;
        
        foreach ($student['requested_companies'] as $company) {
            if (isset($overbooked_companies[$company])) {
                $student['priority'] -= $overbooked_companies[$company]['overflow'];
            }
        }
        
        $level_bonus = [
            'B1' => 0, 'B2' => 1, 'B3' => 2, 
            'M1' => 3, 'M2' => 4
        ];
        if (isset($level_bonus[$student['level']])) {
            $student['priority'] += $level_bonus[$student['level']];
        }
    }
    unset($student);

    // 9. Trier les étudiants par priorité
    uasort($students_requests, function($a, $b) {
        return $b['priority'] - $a['priority'];
    });

    // 10. Helper pour créer des créneaux
    $createOptimizedSlots = function (string $forum_date, array $availability, array $company_demand) {
        $all_slots = [];
        $used_times = [];
        
        foreach ($availability as $cname => $wins) {
            $demand = $company_demand[$cname] ?? 0;
            
            foreach ($wins as $win) {
                try {
                    $start = new \DateTime($win['start']);
                    $end = new \DateTime($win['end']);
                    $forum_dt = new \DateTime($forum_date);
                    
                    $start->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    $end->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    
                    $slot_duration = 15;
                    if ($demand > 10) {
                        $slot_duration = 10;
                    }
                    
                    $interval = new \DateInterval('PT' . $slot_duration . 'M');
                    $current = clone $start;
                    
                    while ($current < $end) {
                        $slot_end = (clone $current)->add($interval);
                        if ($slot_end > $end) break;
                        
                        $time_str = $current->format('Y-m-d H:i:s');
                        
                        $attempts = 0;
                        $base_time = $time_str;
                        while (in_array($time_str, $used_times) && $attempts < 8 && $slot_end <= $end) {
                            $current->modify('+2 minutes');
                            $slot_end = (clone $current)->add($interval);
                            $time_str = $current->format('Y-m-d H:i:s');
                            $attempts++;
                        }
                        
                        if (!in_array($time_str, $used_times) && $slot_end <= $end) {
                            $all_slots[] = [
                                'company' => $cname,
                                'start' => $time_str,
                                'end' => $slot_end->format('Y-m-d H:i:s'),
                                'used' => false,
                                'duration' => $slot_duration
                            ];
                            $used_times[] = $time_str;
                        }
                        
                        $current->add($interval);
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        
        usort($all_slots, function ($a, $b) { 
            return strcmp($a['start'], $b['start']); 
        });
        
        return $all_slots;
    };

    // 11. Générer les créneaux
    $forum_date = $forum['forum_date'] ?? date('Y-m-d');
    $all_slots = $createOptimizedSlots($forum_date, $availability, $company_demand);

    if (empty($all_slots)) {
        return ['success' => false, 'message' => 'No available slots generated', 'appointments' => []];
    }

    // 12. Organiser les créneaux par entreprise
    $slots_by_company = [];
    foreach ($all_slots as $slot) {
        $company = $slot['company'];
        if (!isset($slots_by_company[$company])) {
            $slots_by_company[$company] = [];
        }
        $slots_by_company[$company][] = $slot;
    }

    // 13. Assigner les rendez-vous
    $assignments = [];
    $student_assigned_count = [];
    $max_appointments_per_student = 3;
    $unassigned_students = [];

    // Premier passage
    foreach ($students_requests as $student_id => $student_data) {
        $student_assigned_count[$student_id] = 0;
        $assigned_companies = [];
        
        foreach ($student_data['requested_companies'] as $requested_company) {
            if ($student_assigned_count[$student_id] >= $max_appointments_per_student) {
                break;
            }
            
            if (!isset($slots_by_company[$requested_company]) || in_array($requested_company, $assigned_companies)) {
                continue;
            }
            
            foreach ($slots_by_company[$requested_company] as &$slot) {
                if (!$slot['used']) {
                    $assignments[] = [
                        'user_id' => $student_id,
                        'company_name' => $requested_company,
                        'forum_id' => $forum_id,
                        'appointment_time' => $slot['start'],
                        'firstname' => $student_data['firstname'],
                        'lastname' => $student_data['lastname']
                    ];
                    
                    $slot['used'] = true;
                    $student_assigned_count[$student_id]++;
                    $assigned_companies[] = $requested_company;
                    break;
                }
            }
        }
        
        if ($student_assigned_count[$student_id] < count($student_data['requested_companies'])) {
            $unassigned_students[$student_id] = $student_assigned_count[$student_id];
        }
    }

    // 14. Deuxième passage : alternatives
    $alternative_assignments = [];
    foreach ($unassigned_students as $student_id => $assigned_count) {
        if ($assigned_count >= $max_appointments_per_student) continue;
        
        $student_data = $students_requests[$student_id];
        $missing_slots = $max_appointments_per_student - $assigned_count;
        
        foreach ($slots_by_company as $company => $slots) {
            if ($missing_slots <= 0) break;
            
            if (in_array($company, $student_data['requested_companies'])) continue;
            
            foreach ($slots as &$slot) {
                if (!$slot['used'] && $missing_slots > 0) {
                    $alternative_assignments[] = [
                        'user_id' => $student_id,
                        'company_name' => $company,
                        'forum_id' => $forum_id,
                        'appointment_time' => $slot['start'],
                        'firstname' => $student_data['firstname'],
                        'lastname' => $student_data['lastname'],
                        'alternative' => true
                    ];
                    
                    $slot['used'] = true;
                    $missing_slots--;
                    $student_assigned_count[$student_id]++;
                    break;
                }
            }
        }
    }

    $all_assignments = array_merge($assignments, $alternative_assignments);

    // 15. Persister les assignations
    $inserted = [];
    $conn->beginTransaction();
    try {
        $deleted_count = $conn->executeStatement(
            'DELETE FROM appointment WHERE forum_id = ? AND appointment_time IS NOT NULL',
            [$forum_id]
        );

        $skipped_conflicts = 0;
        
        foreach ($all_assignments as $a) {
            $ts = $a['appointment_time'];
            $max_retries = 3;
            $retry_count = 0;
            $inserted_successfully = false;
            
            while ($retry_count < $max_retries && !$inserted_successfully) {
                try {
                    $time_exists = $conn->fetchOne(
                        'SELECT 1 FROM appointment WHERE appointment_time = ?',
                        [$ts]
                    );
                    
                    if ($time_exists !== false) {
                        try {
                            $dt = new \DateTime($ts);
                            $dt->modify('+'.($retry_count + 1).' minutes');
                            $new_ts = $dt->format('Y-m-d H:i:s');
                            
                            $new_time_exists = $conn->fetchOne(
                                'SELECT 1 FROM appointment WHERE appointment_time = ?',
                                [$new_ts]
                            );
                            
                            if ($new_time_exists === false) {
                                $ts = $new_ts;
                                $a['appointment_time'] = $ts;
                            }
                        } catch (\Throwable $e) {
                            break;
                        }
                    }
                    
                    $affected = $conn->executeStatement(
                        'UPDATE appointment SET appointment_time = ?, appointment_request = ? WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                        [$ts, 0, $a['forum_id'], $a['company_name'], $a['user_id']]
                    );
                    
                    if ($affected > 0) {
                        $inserted[] = $a;
                        $inserted_successfully = true;
                        break;
                    }
                    
                    $conn->insert('appointment', [
                        'user_id' => $a['user_id'],
                        'forum_id' => $a['forum_id'],
                        'company_name' => $a['company_name'],
                        'appointment_request' => 0,
                        'appointment_time' => $ts,
                    ]);
                    
                    $inserted[] = $a;
                    $inserted_successfully = true;
                    
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $retry_count++;
                    if ($retry_count >= $max_retries) {
                        $skipped_conflicts++;
                    }
                } catch (\Throwable $e) {
                    $retry_count = $max_retries;
                    $skipped_conflicts++;
                }
            }
        }
        
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Insert failed: '.$e->getMessage(), 'appointments' => []];
    }

    // 16. Statistiques et retour FINAL
    $students_with_appointments = count(array_unique(array_column($inserted, 'user_id')));
    $companies_used = count(array_unique(array_column($inserted, 'company_name')));
    $alternative_count = count($alternative_assignments);
    
    $result = [
        'success' => true, 
        'message' => "Planning généré avec succès", 
        'count' => count($inserted), 
        'appointments' => $inserted,
        'stats' => [
            'total_students' => count($students_requests),
            'students_with_appointments' => $students_with_appointments,
            'companies_used' => $companies_used,
            'total_requests' => count($requests),
            'assigned_requests' => count($inserted) - $alternative_count,
            'alternative_assignments' => $alternative_count,
            'overbooked_companies' => $overbooked_companies,
            'skipped_conflicts' => $skipped_conflicts
        ]
    ];
    
    if ($skipped_conflicts > 0) {
        $result['message'] .= " ($skipped_conflicts conflits de temps)";
    }

    return $result;
}   
}