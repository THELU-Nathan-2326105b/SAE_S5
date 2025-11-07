<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlanningAlgo
{
    /**
     * Minimal placeholder algorithm that returns an array of planned meetings.
     * Keep it small and safe — you can replace with the full algorithm later.
     *
     * @param Connection $conn
     * @param int|string $forum_id
     * @return array
     */
    public static function run(Connection $conn, $forum_id): array
    {
        // If forum_id is null-ish, try to pick the first forum available
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
                    [$forum_id, true]
                );
            }

            if (empty($students)) {
                $students = $conn->fetchAllAssociative(
                    "SELECT user_id, user_firstname, user_lastname, user_level\n                     FROM users\n                     WHERE user_role = 'student'\n                     ORDER BY user_lastname, user_firstname"
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

    
}
