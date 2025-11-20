<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlanningAlgo
{
    // Constantes pour les seuils de saturation
    private const SATURATION_CRITICAL = 100;  // Au-delà de 100% même avec 5min
    private const SATURATION_HIGH = 90;       // Au-delà de 90% avec 10min
    private const SATURATION_MODERATE = 80;   // Au-delà de 80% avec 15min
    
    // Constantes pour les durées de rendez-vous (en minutes)
    private const DURATION_MIN = 5;
    private const DURATION_SHORT = 10;
    private const DURATION_MEDIUM = 15;
    private const DURATION_COMFORTABLE = 20;
    private const DURATION_LONG = 25;
    
    // Seuils de ratio de saturation pour ajustement des durées
    private const RATIO_CRITICAL = 1.0;      // Saturation critique
    private const RATIO_HIGH = 0.8;          // Saturation élevée
    private const RATIO_MODERATE = 0.6;      // Saturation modérée
    private const RATIO_GOOD = 0.4;          // Bonne capacité
    
    /**
     * Réinitialise les rendez-vous pour un forum donné
     */
    public static function resetAppointments(Connection $conn, int $forum_id): void
    {
        $conn->executeStatement(
            'UPDATE appointment 
             SET appointment_time = NULL, appointment_request = true 
             WHERE forum_id = ?',
            [$forum_id]
        );
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

        // 2. Charger TOUTES les demandes de rendez-vous
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

    // 3. Structurer les données
    $all_student_requests = [];
    $company_demand = [];
    
    foreach ($requests as $request) {
        $user_id = $request['user_id'];
        $company = $request['company_name'];
        
        if (!isset($all_student_requests[$user_id])) {
            $all_student_requests[$user_id] = [
                'user_id' => $user_id,
                'firstname' => $request['user_firstname'],
                'lastname' => $request['user_lastname'],
                'requests' => []
            ];
        }
        $all_student_requests[$user_id]['requests'][] = $company;
        $company_demand[$company] = ($company_demand[$company] ?? 0) + 1;
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

    // 5. ANALYSE GÉNÉRIQUE DE CAPACITÉ
    $capacity_analysis = [];
    $problem_companies = [];
    
    // D'abord, identifier les entreprises demandées SANS disponibilité
    $companies_without_availability = [];
    foreach ($company_demand as $company => $demand) {
        if (!isset($availability[$company])) {
            $companies_without_availability[] = $company;
            $problem_companies[$company] = [
                'company' => $company,
                'demand' => $demand,
                'capacity_minutes' => 0,
                'optimal_duration_raw' => 0,
                'max_possible_5min' => 0,
                'max_possible_10min' => 0,
                'issue_reasons' => ["⚠️ AUCUNE DISPONIBILITÉ : L'entreprise n'est pas présente au forum (pas d'entrée dans is_present)"],
                'severity' => 'CRITIQUE',
                'saturations' => [
                    '5min' => 0,
                    '10min' => 0,
                    '15min' => 0
                ]
            ];
        }
    }
    
    // Ensuite, analyser les entreprises qui ONT des disponibilités
    foreach ($availability as $company => $windows) {
        $total_capacity_minutes = self::calculateTotalCapacity($windows);
        $demand = $company_demand[$company] ?? 0;
        
        // Calculs de capacité génériques
        $capacity_analysis[$company] = self::analyzeCompanyCapacity(
            $total_capacity_minutes,
            $demand
        );
        
        // DÉTECTION GÉNÉRIQUE des problèmes de capacité
        $issue_detection = self::detectCapacityIssues($capacity_analysis[$company], $company, $total_capacity_minutes);
        
        if ($issue_detection['has_issue']) {
            $problem_companies[$company] = $issue_detection['details'];
        }
    }

    // 6. CALCUL GÉNÉRIQUE des durées optimales
    $optimal_durations = [];
    foreach ($availability as $company => $windows) {
        $total_capacity = self::calculateTotalCapacity($windows);
        $demand = $company_demand[$company] ?? 0;
        
        $optimal_durations[$company] = self::calculateOptimalDuration($total_capacity, $demand);
    }

    // 7. GÉNÉRATION GÉNÉRIQUE des créneaux
    $generateSlots = function($forum_date, $availability, $optimal_durations, $existing_appointments = []) {
        $all_slots = [];
        $used_times_by_company = [];
        
        // Indexer les créneaux déjà utilisés PAR ENTREPRISE (car chaque entreprise a son propre planning)
        foreach ($existing_appointments as $appointment) {
            if ($appointment['appointment_time']) {
                try {
                    $dt = new \DateTime($appointment['appointment_time']);
                    $company = $appointment['company_name'];
                    if (!isset($used_times_by_company[$company])) {
                        $used_times_by_company[$company] = [];
                    }
                    $used_times_by_company[$company][] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        
        foreach ($availability as $company => $windows) {
            $duration = $optimal_durations[$company] ?? 15;
            $used_times = $used_times_by_company[$company] ?? [];
            
            foreach ($windows as $window) {
                try {
                    $start = new \DateTime($window['start']);
                    $end = new \DateTime($window['end']);
                    $forum_dt = new \DateTime($forum_date);
                    
                    $start->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    $end->setDate((int)$forum_dt->format('Y'), (int)$forum_dt->format('m'), (int)$forum_dt->format('d'));
                    
                    $interval = new \DateInterval('PT' . $duration . 'M');
                    $current = clone $start;
                    
                    // Logique générique pour toutes les entreprises
                    while ($current < $end) {
                        $slot_end = (clone $current)->add($interval);
                        if ($slot_end > $end) break;
                        
                        $time_str = $current->format('Y-m-d H:i:s');
                        
                        $attempts = 0;
                        $adjusted_time = $time_str;
                        while (in_array($adjusted_time, $used_times) && $attempts < 3 && $slot_end <= $end) {
                            $current->modify('+5 minutes');
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
                                'duration' => $duration
                            ];
                            // Ajouter au tracking des créneaux utilisés pour CETTE entreprise
                            if (!isset($used_times_by_company[$company])) {
                                $used_times_by_company[$company] = [];
                            }
                            $used_times_by_company[$company][] = $adjusted_time;
                            $used_times[] = $adjusted_time;
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

    // 8. Charger les rendez-vous existants
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

    // 9. Génération des créneaux
    $forum_date = $forum['forum_date'] ?? date('Y-m-d');
    $all_slots = $generateSlots($forum_date, $availability, $optimal_durations, $existing_appointments);

    // 10. Organiser les créneaux par entreprise
    $slots_by_company = [];
    foreach ($all_slots as $slot) {
        $company = $slot['company'];
        if (!isset($slots_by_company[$company])) {
            $slots_by_company[$company] = [];
        }
        $slots_by_company[$company][] = $slot;
    }

    // 11. ASSIGNATION ÉQUITABLE (pas de priorité à des entreprises spécifiques)
    $assignments = [];
    $unassigned_requests = [];
    
    // Parcourir tous les étudiants et leurs demandes
    foreach ($all_student_requests as $student_id => $student) {
        foreach ($student['requests'] as $requested_company) {
            $assigned = false;
            
            if (isset($slots_by_company[$requested_company])) {
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
            }
            
            if (!$assigned) {
                $unassigned_requests[] = [
                    'user_id' => $student_id,
                    'company_name' => $requested_company,
                    'firstname' => $student['firstname'],
                    'lastname' => $student['lastname']
                ];
            }
        }
    }

    // 12. Persistance
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
                        try {
                            $dt = new \DateTime($time);
                            $dt->modify('+'.($retries + 1).' minutes');
                            $time = $dt->format('Y-m-d H:i:s');
                            $assignment['appointment_time'] = $time;
                        } catch (\Throwable $e) {
                            break;
                        }
                    }
                    
                    $updated = $conn->executeStatement(
                        'UPDATE appointment SET appointment_time = ?, appointment_request = ? 
                         WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                        [$time, 0, $forum_id, $assignment['company_name'], $assignment['user_id']]
                    );
                    
                    if ($updated === 0) {
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
        return ['success' => false, 'message' => 'Erreur lors de la sauvegarde: '.$e->getMessage(), 'appointments' => []];
    }

    // 13. ANALYSE FINALE GÉNÉRIQUE
    $total_requests = count($requests);
    $assigned_count = count($inserted);
    $unassigned_count = count($unassigned_requests);
    $success_rate = $total_requests > 0 ? round(($assigned_count / $total_requests) * 100, 1) : 0;

    // Catégoriser les entreprises à problèmes de façon générique
    $critical_companies = [];
    $high_saturation_companies = [];
    $moderate_saturation_companies = [];
    
    foreach ($problem_companies as $company => $details) {
        switch ($details['severity']) {
            case 'CRITIQUE':
                $critical_companies[] = $company;
                break;
            case 'ÉLEVÉE':
                $high_saturation_companies[] = $company;
                break;
            case 'MODÉRÉE':
                $moderate_saturation_companies[] = $company;
                break;
        }
    }

    // Construire le message d'alerte générique
    $alert_messages = [];
    
    if (!empty($critical_companies)) {
        $alert_messages[] = "Capacité critique : " . implode(', ', $critical_companies);
    }
    if (!empty($high_saturation_companies)) {
        $alert_messages[] = "Saturation élevée : " . implode(', ', $high_saturation_companies);
    }
    if (!empty($moderate_saturation_companies)) {
        $alert_messages[] = "Saturation modérée : " . implode(', ', $moderate_saturation_companies);
    }
    
    $alert_message = implode(" | ", $alert_messages);

    // Déterminer le statut global
    $global_status = 'SUCCESS';
    if (!empty($critical_companies) && $success_rate < 95) {
        $global_status = 'CRITICAL';
    } elseif ((!empty($high_saturation_companies) || !empty($moderate_saturation_companies)) && $success_rate < 100) {
        $global_status = 'WARNING';
    }

    // 14. RAPPORT FINAL GÉNÉRIQUE
    $result = [
        'success' => $global_status !== 'CRITICAL',
        'status' => $global_status,
        'message' => $global_status === 'SUCCESS' ? 
            "✅ Succès : {$success_rate}% des rendez-vous assignés" :
            "{$alert_message} | Taux de réussite : {$success_rate}%",
        'count' => count($inserted),
        'appointments' => $inserted,
        
        'capacity_analysis' => [
            'has_issues' => !empty($problem_companies),
            'critical_companies' => $critical_companies,
            'high_saturation_companies' => $high_saturation_companies,
            'moderate_saturation_companies' => $moderate_saturation_companies,
            'total_companies_analyzed' => count($availability),
            'companies_with_issues' => count($problem_companies)
        ],
        
        'unassigned_requests' => $unassigned_requests,
        
        'stats' => [
            'total_requests' => $total_requests,
            'assigned_requests' => $assigned_count,
            'unassigned_requests' => $unassigned_count,
            'success_rate' => $success_rate . '%',
            'duration_strategy' => [
                'min' => min($optimal_durations),
                'max' => max($optimal_durations),
                'average' => round(array_sum($optimal_durations) / count($optimal_durations), 1)
            ]
        ]
    ];
    return $result;

    }

    /**
     * Calcule la capacité totale en minutes pour une liste de créneaux
     */
    private static function calculateTotalCapacity(array $windows): float
    {
        $total_minutes = 0;
        foreach ($windows as $window) {
            try {
                $start = new \DateTime($window['start']);
                $end = new \DateTime($window['end']);
                $total_minutes += ($end->getTimestamp() - $start->getTimestamp()) / 60;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $total_minutes;
    }

    /**
     * Analyse la capacité d'une entreprise et calcule les saturations
     */
    private static function analyzeCompanyCapacity(float $capacity_minutes, int $demand): array
    {
        $max_5min = max(1, floor($capacity_minutes / self::DURATION_MIN));
        $max_10min = max(1, floor($capacity_minutes / self::DURATION_SHORT));
        $max_15min = max(1, floor($capacity_minutes / self::DURATION_MEDIUM));
        
        return [
            'capacity_minutes' => round($capacity_minutes, 1),
            'demand' => $demand,
            'max_5min_slots' => $max_5min,
            'max_10min_slots' => $max_10min,
            'max_15min_slots' => $max_15min,
            'saturation_5min' => $demand > 0 ? round(($demand / $max_5min) * 100, 1) : 0,
            'saturation_10min' => $demand > 0 ? round(($demand / $max_10min) * 100, 1) : 0,
            'saturation_15min' => $demand > 0 ? round(($demand / $max_15min) * 100, 1) : 0
        ];
    }

    /**
     * Détecte les problèmes de capacité et détermine leur sévérité
     */
    private static function detectCapacityIssues(array $capacity_data, string $company, float $total_capacity): array
    {
        $has_issue = false;
        $issue_reasons = [];
        $severity = 'FAIBLE';
        
        $demand = $capacity_data['demand'];
        $sat_5min = $capacity_data['saturation_5min'];
        $sat_10min = $capacity_data['saturation_10min'];
        $sat_15min = $capacity_data['saturation_15min'];
        
        // Calculer la durée optimale théorique
        $optimal_duration_raw = $demand > 0 ? $total_capacity / $demand : 0;
        
        // ALERTE CRITIQUE : Si la durée optimale est < 5 min
        if ($optimal_duration_raw < self::DURATION_MIN && $demand > 0) {
            $has_issue = true;
            $severity = 'CRITIQUE';
            $issue_reasons[] = "⚠️ ALERTE : Durée optimale calculée = " . round($optimal_duration_raw, 1) . " min (< 5 min minimum)";
            $issue_reasons[] = "Capacité insuffisante : {$total_capacity} min pour {$demand} demandes";
        }
        // Vérification par ordre de sévérité décroissante
        elseif ($sat_5min >= self::SATURATION_CRITICAL) {
            $has_issue = true;
            $severity = 'CRITIQUE';
            if ($demand > $capacity_data['max_5min_slots']) {
                $issue_reasons[] = "Demande ({$demand}) > capacité max avec 5min ({$capacity_data['max_5min_slots']})";
            } else {
                $issue_reasons[] = "Saturation critique : {$sat_5min}% avec 5min";
            }
        } elseif ($sat_10min > self::SATURATION_HIGH) {
            $has_issue = true;
            $severity = 'ÉLEVÉE';
            $issue_reasons[] = "Saturation élevée : {$sat_10min}% avec 10min";
        } elseif ($sat_15min > self::SATURATION_MODERATE) {
            $has_issue = true;
            $severity = 'MODÉRÉE';
            $issue_reasons[] = "Saturation modérée : {$sat_15min}% avec 15min";
        }
        
        return [
            'has_issue' => $has_issue,
            'details' => $has_issue ? [
                'company' => $company,
                'demand' => $demand,
                'capacity_minutes' => round($total_capacity, 1),
                'optimal_duration_raw' => round($optimal_duration_raw, 1),
                'max_possible_5min' => $capacity_data['max_5min_slots'],
                'max_possible_10min' => $capacity_data['max_10min_slots'],
                'issue_reasons' => $issue_reasons,
                'severity' => $severity,
                'saturations' => [
                    '5min' => $sat_5min,
                    '10min' => $sat_10min,
                    '15min' => $sat_15min
                ]
            ] : []
        ];
    }

    /**
     * Calcule la durée optimale de rendez-vous selon la capacité et la demande
     * Méthode : Division directe du temps disponible par le nombre de demandes
     */
    private static function calculateOptimalDuration(float $capacity_minutes, int $demand): int
    {
        if ($demand === 0) {
            return self::DURATION_MEDIUM; // Durée par défaut
        }
        
        // Calcul direct : temps disponible / nombre de demandes
        $optimal_duration_raw = $capacity_minutes / $demand;
        
        // Arrondir à la durée standard la plus proche (5, 10, 15, 20, 25)
        if ($optimal_duration_raw >= 22.5) {
            $duration = self::DURATION_LONG;          // 25 min
        } elseif ($optimal_duration_raw >= 17.5) {
            $duration = self::DURATION_COMFORTABLE;   // 20 min
        } elseif ($optimal_duration_raw >= 12.5) {
            $duration = self::DURATION_MEDIUM;        // 15 min
        } elseif ($optimal_duration_raw >= 7.5) {
            $duration = self::DURATION_SHORT;         // 10 min
        } else {
            $duration = self::DURATION_MIN;           // 5 min
        }
        
        // Validation : vérifier qu'on peut satisfaire toutes les demandes
        $max_appointments_possible = floor($capacity_minutes / $duration);
        
        // Si la durée choisie ne suffit pas, réduire progressivement
        while ($max_appointments_possible < $demand && $duration > self::DURATION_MIN) {
            if ($duration === self::DURATION_LONG) {
                $duration = self::DURATION_COMFORTABLE;
            } elseif ($duration === self::DURATION_COMFORTABLE) {
                $duration = self::DURATION_MEDIUM;
            } elseif ($duration === self::DURATION_MEDIUM) {
                $duration = self::DURATION_SHORT;
            } elseif ($duration === self::DURATION_SHORT) {
                $duration = self::DURATION_MIN;
            }
            $max_appointments_possible = floor($capacity_minutes / $duration);
        }
        
        return $duration;
    }

}
