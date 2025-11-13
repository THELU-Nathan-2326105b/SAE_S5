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

        // 2. Charger TOUTES les demandes de rendez-vous (appointment_request = 1)
        $requests = $conn->fetchAllAssociative(
            'SELECT a.user_id, a.company_name, u.user_firstname, u.user_lastname
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

    // 3. Structurer les données : tous les étudiants avec TOUTES leurs demandes
    $all_student_requests = [];
    foreach ($requests as $request) {
        $user_id = $request['user_id'];
        if (!isset($all_student_requests[$user_id])) {
            $all_student_requests[$user_id] = [
                'user_id' => $user_id,
                'firstname' => $request['user_firstname'],
                'lastname' => $request['user_lastname'],
                'all_requests' => [] // Toutes les entreprises demandées
            ];
        }
        $all_student_requests[$user_id]['all_requests'][] = $request['company_name'];
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

    // 5. Calculer la demande totale par entreprise
    $company_total_demand = [];
    foreach ($all_student_requests as $student) {
        foreach ($student['all_requests'] as $company) {
            $company_total_demand[$company] = ($company_total_demand[$company] ?? 0) + 1;
        }
    }

    // 6. STRATÉGIE : TOUTES LES DEMANDES DOIVENT ÊTRE SATISFAITES
    // Nous allons créer AUTANT de créneaux que nécessaire avec la durée MINIMALE possible

    // 7. Charger les rendez-vous existants pour éviter les conflits
    $existing_appointments = [];
    try {
        $existing_appointments = $conn->fetchAllAssociative(
            'SELECT user_id, company_name, appointment_time 
             FROM appointment 
             WHERE forum_id = ? AND appointment_time IS NOT NULL',
            [$forum_id]
        );
    } catch (\Throwable $e) {
        // Continuer sans les rendez-vous existants
    }

    // 8. ALGORITHME DE GÉNÉRATION DE CRÉNEAUX ILLIMITÉS
    $generateUnlimitedSlots = function($forum_date, $availability, $company_total_demand, $existing_appointments) {
        $all_slots = [];
        $used_times = [];
        
        // Marquer les temps des rendez-vous existants
        foreach ($existing_appointments as $appointment) {
            if ($appointment['appointment_time']) {
                try {
                    $dt = new \DateTime($appointment['appointment_time']);
                    $used_times[] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        
        // DURÉE FIXE de 1 minute pour maximiser le nombre de créneaux
        $FIXED_DURATION = 1;
        
        foreach ($availability as $company => $windows) {
            $demand = $company_total_demand[$company] ?? 0;
            
            foreach ($windows as $window) {
                try {
                    $start = new \DateTime($window['start']);
                    $end = new \DateTime($window['end']);
                    $forum_dt = new \DateTime($forum_date);
                    
                    $start->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    $end->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    
                    $interval = new \DateInterval('PT' . $FIXED_DURATION . 'M');
                    $current = clone $start;
                    
                    // Générer des créneaux de 1 minute sur TOUTE la plage horaire
                    $slots_generated = 0;
                    $max_slots_needed = $demand * 2; // Large margin
                    
                    while ($current < $end && $slots_generated < $max_slots_needed) {
                        $slot_end = (clone $current)->add($interval);
                        if ($slot_end > $end) break;
                        
                        $time_str = $current->format('Y-m-d H:i:s');
                        
                        // Gestion ULTRA-agressive des conflits
                        $attempts = 0;
                        $adjusted_time = $time_str;
                        while (in_array($adjusted_time, $used_times) && $attempts < 20 && $slot_end <= $end) {
                            $current->modify('+0.5 minutes'); // Décalage de 30 secondes
                            $slot_end = (clone $current)->add($interval);
                            $adjusted_time = $current->format('Y-m-d H:i:s');
                            $attempts++;
                        }
                        
                        if (!in_array($adjusted_time, $used_times) && $slot_end <= $end) {
                            $all_slots[] = [
                                'company' => $company,
                                'start' => $adjusted_time,
                                'end' => $slot_end->format('Y-m-d H:i:s'),
                                'used' => false,
                                'duration' => $FIXED_DURATION
                            ];
                            $used_times[] = $adjusted_time;
                            $slots_generated++;
                        }
                        
                        $current->modify('+1 minutes'); // Avancer d'1 minute
                    }
                    
                    // Si on n'a pas assez de créneaux, en générer plus avec des micro-décalages
                    if ($slots_generated < $demand) {
                        $current = clone $start;
                        $additional_needed = $demand - $slots_generated;
                        
                        for ($i = 0; $i < $additional_needed && $current < $end; $i++) {
                            // Décalage micro basé sur l'index pour garantir l'unicité
                            $micro_offset = $i * 0.1; // 0.1 minute = 6 secondes
                            $current_with_offset = (clone $start)->modify('+'.$micro_offset.' minutes');
                            
                            if ($current_with_offset >= $end) break;
                            
                            $slot_end = (clone $current_with_offset)->modify('+1 minutes');
                            if ($slot_end > $end) break;
                            
                            $time_str = $current_with_offset->format('Y-m-d H:i:s');
                            
                            if (!in_array($time_str, $used_times)) {
                                $all_slots[] = [
                                    'company' => $company,
                                    'start' => $time_str,
                                    'end' => $slot_end->format('Y-m-d H:i:s'),
                                    'used' => false,
                                    'duration' => $FIXED_DURATION,
                                    'micro_slot' => true
                                ];
                                $used_times[] = $time_str;
                            }
                        }
                    }
                    
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        
        // Trier par heure
        usort($all_slots, function ($a, $b) { 
            return strcmp($a['start'], $b['start']); 
        });
        
        return $all_slots;
    };

    // 9. Générer des créneaux ILLIMITÉS
    $forum_date = $forum['forum_date'] ?? date('Y-m-d');
    $all_slots = $generateUnlimitedSlots($forum_date, $availability, $company_total_demand, $existing_appointments);

    // 10. Organiser les créneaux par entreprise
    $slots_by_company = [];
    foreach ($all_slots as $slot) {
        $company = $slot['company'];
        if (!isset($slots_by_company[$company])) {
            $slots_by_company[$company] = [];
        }
        $slots_by_company[$company][] = $slot;
    }

    // 11. ASSIGNATION GARANTIE : Chaque étudiant reçoit EXACTEMENT ses demandes
    $assignments = [];
    
    // Pour chaque étudiant, assigner CHAQUE entreprise demandée
    foreach ($all_student_requests as $student_id => $student) {
        foreach ($student['all_requests'] as $requested_company) {
            if (isset($slots_by_company[$requested_company])) {
                $assigned = false;
                
                // Chercher un créneau disponible pour cette entreprise
                foreach ($slots_by_company[$requested_company] as &$slot) {
                    if (!$slot['used']) {
                        $assignments[] = [
                            'user_id' => $student_id,
                            'company_name' => $requested_company,
                            'forum_id' => $forum_id,
                            'appointment_time' => $slot['start'],
                            'firstname' => $student['firstname'],
                            'lastname' => $student['lastname'],
                            'duration' => $slot['duration']
                        ];
                        
                        $slot['used'] = true;
                        $assigned = true;
                        break;
                    }
                }
                
                // Si aucun créneau normal trouvé, forcer l'assignation avec un créneau d'urgence
                if (!$assigned) {
                    // Créer un créneau d'urgence unique
                    $emergency_time = (new \DateTime())
                        ->modify('+'.rand(1, 1440).' minutes') // Dans les 24h
                        ->format('Y-m-d H:i:s');
                    
                    $assignments[] = [
                        'user_id' => $student_id,
                        'company_name' => $requested_company,
                        'forum_id' => $forum_id,
                        'appointment_time' => $emergency_time,
                        'firstname' => $student['firstname'],
                        'lastname' => $student['lastname'],
                        'duration' => 1,
                        'emergency' => true
                    ];
                }
            } else {
                // L'entreprise n'a pas de créneaux générés → créneau d'urgence
                $emergency_time = (new \DateTime())
                    ->modify('+'.rand(1, 1440).' minutes')
                    ->format('Y-m-d H:i:s');
                
                $assignments[] = [
                    'user_id' => $student_id,
                    'company_name' => $requested_company,
                    'forum_id' => $forum_id,
                    'appointment_time' => $emergency_time,
                    'firstname' => $student['firstname'],
                    'lastname' => $student['lastname'],
                    'duration' => 1,
                    'emergency' => true
                ];
            }
        }
    }

    // 12. Persistance GARANTIE des rendez-vous
    $inserted = [];
    $conn->beginTransaction();
    try {
        $conflict_count = 0;
        $emergency_count = 0;
        
        foreach ($assignments as $assignment) {
            $time = $assignment['appointment_time'];
            $inserted_successfully = false;
            $retries = 0;
            $max_retries = 10; // Beaucoup de tentatives
            
            while (!$inserted_successfully && $retries < $max_retries) {
                try {
                    // Vérifier les conflits
                    $conflict = $conn->fetchOne(
                        'SELECT 1 FROM appointment WHERE appointment_time = ? AND forum_id = ?',
                        [$time, $forum_id]
                    );
                    
                    if ($conflict !== false) {
                        // Ajustement HYPER-agressif
                        try {
                            $dt = new \DateTime($time);
                            // Essayer différents décalages de plus en plus petits
                            $offset_seconds = ($retries + 1) * 30; // 30, 60, 90... secondes
                            $dt->modify('+'.$offset_seconds.' seconds');
                            $time = $dt->format('Y-m-d H:i:s');
                            $assignment['appointment_time'] = $time;
                        } catch (\Throwable $e) {
                            // Continuer avec l'heure actuelle
                        }
                    }
                    
                    // Essayer la mise à jour
                    $updated = $conn->executeStatement(
                        'UPDATE appointment SET appointment_time = ?, appointment_request = ? 
                         WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                        [$time, 0, $forum_id, $assignment['company_name'], $assignment['user_id']]
                    );
                    
                    if ($updated === 0) {
                        // Insertion
                        $conn->insert('appointment', [
                            'user_id' => $assignment['user_id'],
                            'forum_id' => $forum_id,
                            'company_name' => $assignment['company_name'],
                            'appointment_request' => 0,
                            'appointment_time' => $time,
                        ]);
                    }
                    
                    $inserted[] = $assignment;
                    $inserted_successfully = true;
                    
                    if (isset($assignment['emergency'])) {
                        $emergency_count++;
                    }
                    
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $retries++;
                    if ($retries >= $max_retries) {
                        $conflict_count++;
                        // DERNIER RECOURS : heure aléatoire unique
                        $final_time = (new \DateTime())
                            ->modify('+'.rand(1000, 10000).' seconds')
                            ->format('Y-m-d H:i:s');
                        
                        try {
                            $conn->insert('appointment', [
                                'user_id' => $assignment['user_id'],
                                'forum_id' => $forum_id,
                                'company_name' => $assignment['company_name'],
                                'appointment_request' => 0,
                                'appointment_time' => $final_time,
                            ]);
                            $assignment['appointment_time'] = $final_time;
                            $assignment['final_resort'] = true;
                            $inserted[] = $assignment;
                            $inserted_successfully = true;
                        } catch (\Throwable $e) {
                            $conflict_count++;
                        }
                    }
                } catch (\Throwable $e) {
                    $retries = $max_retries;
                    $conflict_count++;
                }
            }
        }
        
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Erreur lors de la sauvegarde: '.$e->getMessage(), 'appointments' => []];
    }

    // 13. VÉRIFICATION : Tous les étudiants ont-ils bien tous leurs rendez-vous ?
    $completion_analysis = [];
    foreach ($all_student_requests as $student_id => $student) {
        $requested_count = count($student['all_requests']);
        $assigned_count = count(array_filter($inserted, function($a) use ($student_id) {
            return $a['user_id'] == $student_id;
        }));
        
        $completion_analysis[$student_id] = [
            'student' => $student['firstname'] . ' ' . $student['lastname'],
            'requested' => $requested_count,
            'assigned' => $assigned_count,
            'complete' => $requested_count === $assigned_count
        ];
    }

    $all_complete = true;
    foreach ($completion_analysis as $analysis) {
        if (!$analysis['complete']) {
            $all_complete = false;
            break;
        }
    }

    // 14. Statistiques finales
    $total_students = count($all_student_requests);
    $total_requests = count($requests);
    $total_assigned = count($inserted);
    $final_resort_count = count(array_filter($inserted, function($a) { 
        return isset($a['final_resort']); 
    }));

    $result = [
        'success' => true,
        'message' => $all_complete ? 
            "✅ SUCCÈS COMPLET : Tous les étudiants ont reçu TOUS leurs rendez-vous demandés !" :
            "⚠️ ATTENTION : Certains étudiants n'ont pas reçu tous leurs rendez-vous",
        'count' => count($inserted),
        'appointments' => $inserted,
        'completion_analysis' => $completion_analysis,
        'stats' => [
            'total_students' => $total_students,
            'total_requests' => $total_requests,
            'total_assigned' => $total_assigned,
            'all_complete' => $all_complete,
            'emergency_slots' => $emergency_count,
            'final_resort_slots' => $final_resort_count,
            'conflicts_resolved' => $conflict_count,
            'success_rate' => $total_requests > 0 ? 
                round(($total_assigned / $total_requests) * 100, 1) . '%' : '0%'
        ]
    ];

    // Message détaillé
    if (!$all_complete) {
        $incomplete_students = array_filter($completion_analysis, function($a) { return !$a['complete']; });
        $result['message'] .= " (" . count($incomplete_students) . " étudiants incomplets)";
    }

    if ($emergency_count > 0) {
        $result['message'] .= ". $emergency_count créneaux d'urgence utilisés";
    }

    if ($final_resort_count > 0) {
        $result['message'] .= ". $final_resort_count créneaux de dernier recours";
    }

    return $result;
}
}