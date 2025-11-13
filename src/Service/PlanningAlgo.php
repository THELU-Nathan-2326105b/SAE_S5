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

        // Companies present at this forum with their windows
        $windows = $conn->fetchAllAssociative(
            'SELECT ip.company_name, ip.start_time, ip.end_time
             FROM is_present ip
             WHERE ip.forum_id = ?
             ORDER BY ip.company_name, ip.start_time',
            [$forum_id]
        );

        // Students list
        $students = $conn->fetchAllAssociative(
            "SELECT user_id, user_firstname, user_lastname FROM users WHERE user_role IN ('internship','alternance') ORDER BY user_lastname, user_firstname"
        );

        if (empty($students)) {
            $students = $conn->fetchAllAssociative(
                'SELECT DISTINCT u.user_id, u.user_firstname, u.user_lastname
                 FROM appointment a
                 INNER JOIN users u ON u.user_id = a.user_id
                 WHERE a.forum_id = ? AND a.appointment_request = ?
                 ORDER BY u.user_lastname, u.user_firstname',
                [$forum_id, 1]
            );
        }

    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'DB error: '.$e->getMessage(), 'appointments' => []];
    }

    $windows_count = count($windows);
    $students_count = count($students);
    if ($windows_count === 0 || $students_count === 0) {
        $parts = [];
        if ($windows_count === 0) $parts[] = 'no companies (is_present rows) for this forum';
        if ($students_count === 0) $parts[] = 'no students found';
        return [
            'success' => false,
            'message' => 'No scheduling possible: ' . implode(' and ', $parts) . '.',
            'windows_count' => $windows_count,
            'students_count' => $students_count,
            'appointments' => [],
        ];
    }

    // Build availability windows per company
    $availability = [];
    foreach ($windows as $w) {
        $cname = $w['company_name'];
        if (!isset($availability[$cname])) $availability[$cname] = [];
        $availability[$cname][] = ['start' => $w['start_time'], 'end' => $w['end_time']];
    }

    // Helper: create slots for a company
    $createSlots = function (string $forum_date, string $start_ts, string $end_ts, int $duration_minutes = 15) {
        $slots = [];
        try {
            $s = new \DateTime($start_ts);
            $e = new \DateTime($end_ts);
        } catch (\Throwable $ex) {
            return [];
        }

        try {
            $f = new \DateTime($forum_date);
            $s->setDate((int)$f->format('Y'), (int)$f->format('m'), (int)$f->format('d'));
            $e->setDate((int)$f->format('Y'), (int)$f->format('m'), (int)$f->format('d'));
        } catch (\Throwable $ignore) {
        }

        $interval = new \DateInterval('PT'.(int)$duration_minutes.'M');
        while ($s < $e) {
            $slotEnd = (clone $s)->add($interval);
            if ($slotEnd > $e) break;
            $slots[] = ['start' => $s->format('Y-m-d H:i:s'), 'end' => $slotEnd->format('Y-m-d H:i:s')];
            $s->add($interval);
        }
        return $slots;
    };

    $slots_by_company = [];
    $forum_date = $forum['forum_date'] ?? date('Y-m-d');
    
    foreach ($availability as $cname => $wins) {
        $slots_by_company[$cname] = [];
        foreach ($wins as $win) {
            $slots = $createSlots($forum_date, $win['start'], $win['end'], 15);
            foreach ($slots as $s) {
                $slots_by_company[$cname][] = [
                    'start' => $s['start'], 
                    'end' => $s['end'], 
                    'used' => false
                ];
            }
        }
        // Sort company slots by start time
        usort($slots_by_company[$cname], function ($a, $b) { 
            return strcmp($a['start'], $b['start']); 
        });
    }

    // Assign students to companies (round-robin or other distribution)
    $assignments = [];
    $company_names = array_keys($slots_by_company);
    $company_count = count($company_names);
    $company_index = 0;
    
    // Track student assignments to avoid double-booking students
    $student_assigned = [];
    
    foreach ($students as $student) {
        $student_id = $student['user_id'];
        
        if (isset($student_assigned[$student_id])) {
            continue; // Student already assigned
        }
        
        // Try to assign student to next company in round-robin
        $assigned = false;
        $attempts = 0;
        
        while ($attempts < $company_count && !$assigned) {
            $cname = $company_names[$company_index];
            $company_index = ($company_index + 1) % $company_count;
            $attempts++;
            
            // Find first available slot in this company
            foreach ($slots_by_company[$cname] as &$slot) {
                if (!$slot['used']) {
                    // Assign this slot to student
                    $assignments[] = [
                        'user_id' => $student_id,
                        'company_name' => $cname,
                        'forum_id' => $forum_id,
                        'appointment_time' => $slot['start'],
                    ];
                    $slot['used'] = true;
                    $student_assigned[$student_id] = true;
                    $assigned = true;
                    break;
                }
            }
        }
    }

    // Persist assignments
    $inserted = [];
    $conn->beginTransaction();
    try {
        // Delete existing scheduled appointments for this forum
        $conn->executeStatement(
            'DELETE FROM appointment WHERE forum_id = ? AND appointment_time IS NOT NULL',
            [$forum_id]
        );

        $skipped_pk_exists = 0;
        
        foreach ($assignments as $a) {
            $ts = $a['appointment_time'];

            // NO MORE GLOBAL TIME CONFLICT CHECK - each company has its own room/timeline

            // Try to update existing row
            $affected = $conn->executeStatement(
                'UPDATE appointment SET appointment_time = ?, appointment_request = ? WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                [$ts, 0, $a['forum_id'], $a['company_name'], $a['user_id']]
            );
            
            if ($affected > 0) {
                $inserted[] = $a;
                continue;
            }

            // Check if primary key already exists
            $pkExists = $conn->fetchOne(
                'SELECT 1 FROM appointment WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                [$a['forum_id'], $a['company_name'], $a['user_id']]
            );
            
            if ($pkExists !== false) {
                $skipped_pk_exists++;
                continue;
            }

            // Insert new appointment
            $row = [
                'user_id' => $a['user_id'],
                'forum_id' => $a['forum_id'],
                'company_name' => $a['company_name'],
                'appointment_request' => 0,
                'appointment_time' => $a['appointment_time'],
            ];

            $conn->insert('appointment', $row);
            $inserted[] = $row;
        }
        
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Insert failed: '.$e->getMessage(), 'appointments' => []];
    }

    $result = [
        'success' => true, 
        'message' => 'Planning generated successfully', 
        'count' => count($inserted), 
        'appointments' => $inserted,
        'companies_used' => count(array_unique(array_column($inserted, 'company_name'))),
        'students_scheduled' => count(array_unique(array_column($inserted, 'user_id')))
    ];
    
    if ($skipped_pk_exists > 0) {
        $result['skipped_pk_exists'] = $skipped_pk_exists;
        $result['message'] .= ' (' . $skipped_pk_exists . ' skipped due to existing appointment records)';
    }

    return $result;
}
}