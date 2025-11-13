<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;
use App\Service\PlanningAlgo;

final class PlanningAlgoController extends AbstractController
{
    #[Route('/planning-algo', name: 'planning-algo')]
    public function view(Connection $conn): Response
{
    $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

    return $this->render('planning/planningalgo.html.twig', [
        'forums' => $forums,
    ]);
}
    public function index(): Response
    {
        // Generate a per-request CSP nonce and set a permissive script-src for same-origin
        $nonce = base64_encode(random_bytes(18));
        $response = $this->render('planning/planningalgo.html.twig', ['csp_nonce' => $nonce]);
        // Allow scripts from same origin and inline scripts with the nonce
        $response->headers->set('Content-Security-Policy', "script-src 'self' 'nonce-".$nonce."';");
        return $response;
    }

#[Route('/planning/run', name: 'planning_algo_run')]
public function run(Request $request, Connection $conn): Response
{
    $forum_id = $request->query->getInt('forum_id');

    // Call the new planning generator which reads data and writes appointments
    $result = PlanningAlgo::generatePlanning($conn, $forum_id);

    $forums = $conn->fetchAllAssociative('SELECT * FROM forum ORDER BY forum_id');

    // Pass result to the template: message, count and appointments list
    return $this->render('planning/planningalgo.html.twig', [
        'forums' => $forums,
        'planning_result' => $result,
        'appointments' => $result['appointments'] ?? [],
        'appointments_count' => $result['count'] ?? 0,
    ]);
}



    public static function overlapsTime($start1, $end1, $start2, $end2) {
        return $start1 < $end2 && $start2 < $end1;
    }

    public static function addBusy(&$mapBusy, $key, $start, $end) {
        if (!isset($mapBusy[$key])) {
            $mapBusy[$key] = [];
        }

        // Insert the new interval into the list while keeping start order
        $intervals = $mapBusy[$key];
        $inserted = false;
        for ($i = 0; $i < count($intervals); $i++) {
            if ($start < $intervals[$i][0]) {
                array_splice($intervals, $i, 0, [[$start, $end]]);
                $inserted = true;
                break;
            }
        }
        if (!$inserted) {
            $intervals[] = [$start, $end];
        }

        // Merge overlapping intervals
        $merged = [];
        foreach ($intervals as $interval) {
            if (empty($merged) || $merged[count($merged) - 1][1] < $interval[0]) {
                $merged[] = $interval;
            } else {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $interval[1]);
            }
        }

        $mapBusy[$key] = $merged;
    }

    public static function next_free_within(array $window, float $duration, array $busys): ?array {
        // $window are hours (e.g. [9.0,12.5]), $duration is in hours (e.g. 0.25)
        [$fs, $fe] = $window;
        $t = $fs;

        // ensure busys sorted by start
        usort($busys, function($a, $b) { return $a[0] <=> $b[0]; });
        $n = count($busys);

        while ($t + $duration <= $fe + 1e-12) {
            $candidate_start = $t;
            $candidate_end = $t + $duration;
            $conflict = false;
            $overlap_ends = [];

            for ($j = 0; $j < $n; $j++) {
                $bstart = $busys[$j][0];
                $bend = $busys[$j][1];

                // skip busys that end before or exactly at candidate start
                if ($bend <= $candidate_start + 1e-12) continue;
                // if busy starts at or after candidate end we can stop searching (busys sorted)
                if ($bstart >= $candidate_end - 1e-12) break;

                // overlapping condition
                if (self::overlapsTime($candidate_start, $candidate_end, $bstart, $bend)) {
                    $conflict = true;
                    $overlap_ends[] = $bend;
                }
            }

            if (!$conflict) {
                return [$candidate_start, $candidate_end];
            }

            if (empty($overlap_ends)) {
                // shouldn't happen, but safe guard
                return null;
            }

            // advance t to the maximum end of overlapping intervals (so we skip all conflicts)
            $t = max($overlap_ends);
        }

        return null;
    }

    public static function split_half_days(array $window): array {
        [$fs, $fe] = $window;
        $LUNCH_START = 12.5; // 12:30 in decimal hours
        $LUNCH_END = 13.5;   // 13:30 in decimal hours

        // AM: from fs to min(fe, 12:30)
        $am_end = min($fe, $LUNCH_START);
        $am = ($fs < $am_end) ? [$fs, $am_end] : null;

        // PM: from max(fs, 13:30) to fe
        $pm_start = max($fs, $LUNCH_END);
        $pm = ($pm_start < $fe) ? [$pm_start, $fe] : null;

        return [$am, $pm];
    }

    public static function minutes(array $window): int {
        [$fs, $fe] = $window;
        $delta = $fe - $fs;
        $min = max(0, (int)floor($delta * 60));
        return $min;
    }

    public static function compute_duration(int $dispoTotale, int $demande): int {
        if ($demande <= 0) {
            return 15;
        }
    $est = (int)floor($dispoTotale / $demande);
    // clamp between 10 and 15
    return max(10, min(15, $est));
    }

    public static function insert_company_breaks(array $slots, array $window, int $max = 2, int $pause = 10): array {
        $pause_h = $pause / 60.0;

        // Normalize input slots into associative slots: ['start'=>..., 'end'=>..., 'meta'=>original]
        $normalized = [];
        foreach ($slots as $s) {
            if (is_array($s) && array_key_exists('start', $s) && array_key_exists('end', $s)) {
                $normalized[] = ['start' => (float)$s['start'], 'end' => (float)$s['end'], 'meta' => $s];
            } elseif (is_array($s) && isset($s[0]) && isset($s[1])) {
                $normalized[] = ['start' => (float)$s[0], 'end' => (float)$s[1], 'meta' => $s];
            }
            // else ignore malformed slot
        }

        // Sort normalized slots by start
        usort($normalized, function($a, $b) { return $a['start'] <=> $b['start']; });

        // Build 'all' with window boundaries
        $all = [];
        $all[] = ['start' => $window[0], 'end' => $window[0], 'meta' => null];
        foreach ($normalized as $n) $all[] = $n;
        $all[] = ['start' => $window[1], 'end' => $window[1], 'meta' => null];

        $breaks_added = 0;
        while ($breaks_added < $max) {
            // compute gaps (gap_start = previous.end, gap_end = next.start)
            $gaps = [];
            for ($i = 0; $i < count($all) - 1; $i++) {
                $gap_start = $all[$i]['end'];
                $gap_end = $all[$i+1]['start'];
                $gap_len = $gap_end - $gap_start;
                if ($gap_len >= $pause_h + 1e-9) {
                    $gaps[] = ['start' => $gap_start, 'end' => $gap_end, 'len' => $gap_len, 'index_after' => $i+1];
                }
            }

            if (empty($gaps)) break;

            // choose the largest gap
            usort($gaps, function($a, $b) { return $b['len'] <=> $a['len']; });
            $gap = $gaps[0];

            // place break centered in the gap
            $bStart = $gap['start'] + ($gap['len'] - $pause_h) / 2.0;
            $bEnd = $bStart + $pause_h;

            // prepare break slot
            $break_slot = ['start' => $bStart, 'end' => $bEnd, 'meta' => ['break' => true]];

            // insert into normalized slots and all
            $normalized[] = $break_slot;

            // rebuild all from normalized + boundaries and sort
            usort($normalized, function($a, $b) { return $a['start'] <=> $b['start']; });
            $all = [];
            $all[] = ['start' => $window[0], 'end' => $window[0], 'meta' => null];
            foreach ($normalized as $n) $all[] = $n;
            $all[] = ['start' => $window[1], 'end' => $window[1], 'meta' => null];

            $breaks_added++;
        }

        // Build result: return original appointment entries (their original meta) and breaks as associative arrays
        $result = [];
        foreach ($normalized as $n) {
            if (isset($n['meta']) && is_array($n['meta']) && isset($n['meta']['student_id'])) {
                // keep original appointment entry (but ensure start/end are set)
                $entry = $n['meta'];
                $entry['start'] = $n['start'];
                $entry['end'] = $n['end'];
                $result[] = $entry;
            } elseif (isset($n['meta']) && is_array($n['meta']) && isset($n['meta']['break'])) {
                $result[] = ['start' => $n['start'], 'end' => $n['end'], 'break' => true];
            } else {
                // if original meta missing (came from numeric input), return associative
                $result[] = ['start' => $n['start'], 'end' => $n['end']];
            }
        }

        // sort final result by start
        usort($result, function($a, $b) { return ($a['start'] ?? $a[0]) <=> ($b['start'] ?? $b[0]); });

        return $result;
    }

    public static function charger_donnees(int $forum_id, Connection $conn): array
    {
        // 1. Load the forum 
        $forum = $conn->fetchAssociative('SELECT * FROM forum WHERE forum_id = ?', [$forum_id]);

        // Helper: convert timestamp string to decimal hour (e.g. 9:30 -> 9.5)
        $toHour = function ($ts) {
            if ($ts === null) return null;
            $dt = new \DateTime($ts);
            $h = (int)$dt->format('H');
            $m = (int)$dt->format('i');
            $s = (int)$dt->format('s');
            return $h + ($m / 60.0) + ($s / 3600.0);
        };

        // 2. Load companies present at this forum (use company_name as key)
        $companies = $conn->fetchAllAssociative(
            'SELECT DISTINCT c.company_name, c.company_description
             FROM company c
             INNER JOIN is_present ip ON ip.company_name = c.company_name
             WHERE ip.forum_id = ?', [$forum_id]
        );

        // Normalize companies so planner can still use 'id' field (use company_name as id)
        foreach ($companies as &$c) {
            $c['id'] = $c['company_name'];
        }
        unset($c);

        // 3. Load company availabilities (windows) from is_present
        $windows = $conn->fetchAllAssociative(
            'SELECT ip.company_name, ip.start_time, ip.end_time
             FROM is_present ip
             WHERE ip.forum_id = ?', [$forum_id]
        );

        // 4. Load students' appointment requests (appointment rows where appointment_request = true)
        $reqs = $conn->fetchAllAssociative(
            'SELECT a.user_id, a.company_name, a.appointment_request, u.user_firstname, u.user_lastname, u.user_level, u.user_role
             FROM appointment a
             INNER JOIN users u ON u.user_id = a.user_id
             WHERE a.forum_id = ? AND a.appointment_request = ?', [$forum_id, 1]
        );

        // 5. Split windows into AM/PM and build availability_by_company keyed by company_name
        $availability_by_company = [];
        foreach ($windows as $w) {
            $start_h = $toHour($w['start_time']);
            $end_h = $toHour($w['end_time']);
            if ($start_h === null || $end_h === null) continue;
            $win = [(float)$start_h, (float)$end_h];
            [$am, $pm] = self::split_half_days($win);
            $cname = $w['company_name'];
            if (!isset($availability_by_company[$cname])) $availability_by_company[$cname] = [];
            if ($am) $availability_by_company[$cname][] = $am;
            if ($pm) $availability_by_company[$cname][] = $pm;
        }

        // 6. Partition requests: company_name -> type -> level
        $partitioned_reqs = [];
        foreach ($reqs as $r) {
            $cname = $r['company_name'];
            $rawType = $r['user_role'] ?? 'internship';
            // map english 'internship' to planner's expected 'stage'
            $type = ($rawType === 'internship') ? 'stage' : $rawType;
            $level = $r['user_level'] ?? 'B1';
            if (!isset($partitioned_reqs[$cname])) $partitioned_reqs[$cname] = [];
            if (!isset($partitioned_reqs[$cname][$type])) $partitioned_reqs[$cname][$type] = [];
            if (!isset($partitioned_reqs[$cname][$type][$level])) $partitioned_reqs[$cname][$type][$level] = [];
            // normalize to structure expected by planner
            $partitioned_reqs[$cname][$type][$level][] = [
                'student_id' => $r['user_id'],
                'firstname' => $r['user_firstname'],
                'lastname' => $r['user_lastname'],
            ];
        }

        return [
            'forum' => $forum,
            'companies' => $companies,
            'availability' => $availability_by_company,
            'reqs' => $partitioned_reqs,
        ];
    }

    public static function calculer_durees(array $companies, array $availability, array $reqs): array
    {
        $duration_type_by_company = [];
        foreach ($companies as $company) {
            $cid = $company['id'];
            $duration_type_by_company[$cid] = [];
            // For each type (stage, apprenticeship, etc.)
            if (!isset($reqs[$cid])) continue;
            foreach ($reqs[$cid] as $type => $levels) {
                // Total available minutes (AM+PM)
                $total_minutes = 0;
                if (isset($availability[$cid])) {
                    foreach ($availability[$cid] as $window) {
                        $total_minutes += self::minutes($window);
                    }
                }
                // Number of requests for this type
                $demande = 0;
                foreach ($levels as $level => $lst) {
                    $demande += count($lst);
                }
                // Compute the optimal duration
                $dur = self::compute_duration($total_minutes, $demande);
                $duration_type_by_company[$cid][$type] = $dur;
            }
        }
        return $duration_type_by_company;
    }

    public static function placer_rendezvous(array $companies, array $availability, array $reqs, array $durations): array
    {
        $planning = [];
        $busy_company = [];
        $busy_student = [];
        $waitlist = [];

        foreach ($companies as $company) {
            $cid = $company['id'];
            if (!isset($availability[$cid])) continue;
            $planning[$cid] = [];
            $busy_company[$cid] = [];
            foreach ($availability[$cid] as $window) {
                // AM/PM windows
                [$am, $pm] = self::split_half_days($window);
                foreach ([$am, $pm] as $halfday) {
                    if (!$halfday) continue;
                    $slots = [];
                    foreach (['stage', 'alternance'] as $type) {
                        if (!isset($reqs[$cid][$type])) continue;
                        $levels = array_keys($reqs[$cid][$type]);
                        sort($levels, SORT_NUMERIC);
                        foreach ($levels as $level) {
                            foreach ($reqs[$cid][$type][$level] as $student) {
                                $sid = $student['student_id'];
                                $dur = $durations[$cid][$type] ?? 15;
                                // Find a free slot for both the company AND the student
                                $company_busys = $busy_company[$cid] ?? [];
                                $student_busys = $busy_student[$sid] ?? [];
                                $all_busys = array_merge($company_busys, $student_busys);
                                usort($all_busys, function($a, $b) { return $a[0] <=> $b[0]; });
                                $slot = self::next_free_within($halfday, $dur / 60.0, $all_busys);
                                if ($slot) {
                                    $slots[] = [
                                        'student_id' => $sid,
                                        'student_name' => $student['firstname'] . ' ' . $student['lastname'],
                                        'type' => $type,
                                        'level' => $level,
                                        'start' => $slot[0],
                                        'end' => $slot[1],
                                    ];
                                    self::addBusy($busy_company, $cid, $slot[0], $slot[1]);
                                    self::addBusy($busy_student, $sid, $slot[0], $slot[1]);
                                } else {
                                    $waitlist[] = [
                                        'company_id' => $cid,
                                        'student_id' => $sid,
                                        'type' => $type,
                                        'level' => $level,
                                    ];
                                }
                            }
                        }
                    }
                    // Insert up to 2 breaks of 10 minutes in the half-day
                    $slots_with_breaks = self::insert_company_breaks($slots, $halfday, 2, 10);
                    $planning[$cid][] = [
                        'window' => $halfday,
                        'slots' => $slots_with_breaks,
                    ];
                }
            }
        }
        return [
            'planning' => $planning,
            'busy_company' => $busy_company,
            'busy_student' => $busy_student,
            'waitlist' => $waitlist,
        ];
    }

    public static function compacter_attente_etudiants(array $planning, array &$busy_company, array &$busy_student, array $availability): array
    {
        // 1. Group all slots by student
        $slots_by_student = [];
        foreach ($planning as $cid => $company_days) {
            foreach ($company_days as $day) {
                foreach ($day['slots'] as $slot) {
                    if (!isset($slot['student_id'])) continue;
                    $sid = $slot['student_id'];
                    $slots_by_student[$sid][] = [
                        'company_id' => $cid,
                        'window' => $day['window'],
                        'slot' => $slot,
                    ];
                }
            }
        }

        // 2. For each student, try to compact their appointments
        foreach ($slots_by_student as $sid => &$student_slots) {
            // Sort by start time
            usort($student_slots, function($a, $b) {
                return $a['slot']['start'] <=> $b['slot']['start'];
            });

            // Two passes to improve compaction
            for ($pass = 0; $pass < 2; $pass++) {
                for ($i = 0; $i < count($student_slots) - 1; $i++) {
                    $s1 = &$student_slots[$i];
                    $s2 = &$student_slots[$i+1];
                    $gap = $s2['slot']['start'] - $s1['slot']['end'];
                    $objectif = 0.17; // 10 minutes in decimal hours

                    if ($gap <= $objectif + 1e-6) continue;

                    // Try to advance s2 (pull-forward)
                    $new_start = $s1['slot']['end'];
                    $new_end = $new_start + ($s2['slot']['end'] - $s2['slot']['start']);

                    // Check if the advanced slot is within the company's window and conflict-free
                    $company_window = null;
                    foreach ($availability[$s2['company_id']] as $w) {
                        if ($new_start >= $w[0] && $new_end <= $w[1]) {
                            $company_window = $w;
                            break;
                        }
                    }
                    $conflict = false;
                    if ($company_window) {
                        // Check conflicts for company and student
                        foreach (($busy_company[$s2['company_id']] ?? []) as $b) {
                            if (self::overlapsTime($new_start, $new_end, $b[0], $b[1]) && !($b[0] == $s2['slot']['start'] && $b[1] == $s2['slot']['end'])) {
                                $conflict = true;
                                break;
                            }
                        }
                        foreach (($busy_student[$sid] ?? []) as $b) {
                            if (self::overlapsTime($new_start, $new_end, $b[0], $b[1]) && !($b[0] == $s2['slot']['start'] && $b[1] == $s2['slot']['end'])) {
                                $conflict = true;
                                break;
                            }
                        }
                    } else {
                        $conflict = true;
                    }

                    if (!$conflict) {
                        // Apply the shift
                        // Update the slot in the planning
                        $old_start = $s2['slot']['start'];
                        $old_end = $s2['slot']['end'];
                        $s2['slot']['start'] = $new_start;
                        $s2['slot']['end'] = $new_end;

                        // Update busy arrays
                        foreach ($busy_company[$s2['company_id']] as &$b) {
                            if ($b[0] == $old_start && $b[1] == $old_end) {
                                $b = [$new_start, $new_end];
                                break;
                            }
                        }
                        foreach ($busy_student[$sid] as &$b) {
                            if ($b[0] == $old_start && $b[1] == $old_end) {
                                $b = [$new_start, $new_end];
                                break;
                            }
                        }
                        continue;
                    }

                    // Otherwise, try to push back s1 (push-back)
                    $new_end = $s2['slot']['start'];
                    $new_start = $new_end - ($s1['slot']['end'] - $s1['slot']['start']);
                    $company_window = null;
                    foreach ($availability[$s1['company_id']] as $w) {
                        if ($new_start >= $w[0] && $new_end <= $w[1]) {
                            $company_window = $w;
                            break;
                        }
                    }
                    $conflict = false;
                    if ($company_window) {
                        foreach (($busy_company[$s1['company_id']] ?? []) as $b) {
                            if (self::overlapsTime($new_start, $new_end, $b[0], $b[1]) && !($b[0] == $s1['slot']['start'] && $b[1] == $s1['slot']['end'])) {
                                $conflict = true;
                                break;
                            }
                        }
                        foreach (($busy_student[$sid] ?? []) as $b) {
                            if (self::overlapsTime($new_start, $new_end, $b[0], $b[1]) && !($b[0] == $s1['slot']['start'] && $b[1] == $s1['slot']['end'])) {
                                $conflict = true;
                                break;
                            }
                        }
                    } else {
                        $conflict = true;
                    }

                    if (!$conflict) {
                        // Apply the shift
                        $old_start = $s1['slot']['start'];
                        $old_end = $s1['slot']['end'];
                        $s1['slot']['start'] = $new_start;
                        $s1['slot']['end'] = $new_end;
                        foreach ($busy_company[$s1['company_id']] as &$b) {
                            if ($b[0] == $old_start && $b[1] == $old_end) {
                                $b = [$new_start, $new_end];
                                break;
                            }
                        }
                        foreach ($busy_student[$sid] as &$b) {
                            if ($b[0] == $old_start && $b[1] == $old_end) {
                                $b = [$new_start, $new_end];
                                break;
                            }
                        }
                    }
                    // Option: could attempt a small swap within the same company/type here
                }
            }
        }
        unset($student_slots);

        // 3. Reinject modified slots into the planning
        foreach ($planning as $cid => &$company_days) {
            foreach ($company_days as &$day) {
                foreach ($day['slots'] as &$slot) {
                    if (!isset($slot['student_id'])) continue;
                    $sid = $slot['student_id'];
                    foreach ($slots_by_student[$sid] as $stud_slot) {
                        if (
                            $stud_slot['company_id'] == $cid &&
                            abs($stud_slot['slot']['start'] - $slot['start']) < 1e-6 &&
                            abs($stud_slot['slot']['end'] - $slot['end']) < 1e-6
                        ) {
                            // Slot already up-to-date
                            break;
                        }
                        if (
                            $stud_slot['company_id'] == $cid &&
                            abs($stud_slot['slot']['start'] - $slot['start']) > 1e-6 &&
                            abs($stud_slot['slot']['end'] - $slot['end']) > 1e-6 &&
                            abs($stud_slot['slot']['start'] - $slot['start']) < 0.5 // tolerance
                        ) {
                            // Update the slot
                            $slot['start'] = $stud_slot['slot']['start'];
                            $slot['end'] = $stud_slot['slot']['end'];
                            break;
                        }
                    }
                }
                unset($slot);
            }
            unset($day);
        }
        unset($company_days);

        return $planning;
    }

    public static function ecrire_en_base(int $forum_id, array $planning, array $waitlist, Connection $conn): void
    {
        $conn->beginTransaction();
        try {
            // Note: the project's DB schema (dump) does not include a company_break table.
            // We'll write planned appointment_time into the existing appointment table
            // using the primary key (user_id, forum_id, company_name).

            // Helper: convert forum date + decimal hour -> timestamp string
            $toTimestamp = function ($forumDate, $hourFloat) {
                // $forumDate expected as 'YYYY-mm-dd' or datetime string
                $date = new \DateTime($forumDate);
                $h = (int)floor($hourFloat);
                $m = (int)floor(($hourFloat - $h) * 60 + 0.5);
                $date->setTime($h, $m, 0);
                return $date->format('Y-m-d H:i:s');
            };

            // Get forum date for timestamp composition
            $forumRow = $conn->fetchAssociative('SELECT forum_date FROM forum WHERE forum_id = ?', [$forum_id]);
            $forumDate = $forumRow['forum_date'] ?? date('Y-m-d');

            // 1. For each company (keyed by company_name) and each slot
            foreach ($planning as $company_name => $company_days) {
                foreach ($company_days as $day) {
                    foreach ($day['slots'] as $slot) {
                        if (isset($slot['student_id'])) {
                            $student_id = $slot['student_id'];
                            $start = $slot['start'];

                            // Compose timestamp from forum date + start hour
                            $ts = $toTimestamp($forumDate, $start);

                            // UPDATE existing appointment if present (by PK user_id, forum_id, company_name)
                            $affected = $conn->executeStatement(
                                'UPDATE appointment SET appointment_time = ?, appointment_request = ? WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                                [$ts, 0, $forum_id, $company_name, $student_id]
                            );
                            if ($affected === 0) {
                                // INSERT new appointment row if none exists
                                $conn->insert('appointment', [
                                    'user_id' => $student_id,
                                    'forum_id' => $forum_id,
                                    'company_name' => $company_name,
                                    'appointment_request' => 0,
                                    'appointment_time' => $ts,
                                ]);
                            }
                        } else {
                            // It's a break — the dump lacks company_break table, so skip DB write.
                            // Optionally log or collect breaks elsewhere if needed.
                        }
                    }
                }
            }

            // 2. Mark unsatisfied requests as waitlist
            // The DB schema does not have a 'status' column; update appointment_request to true as a marker
            foreach ($waitlist as $w) {
                $companyKey = $w['company_id'] ?? ($w['company_name'] ?? null);
                if ($companyKey === null) continue;
                $conn->executeStatement(
                    'UPDATE appointment SET appointment_request = ? WHERE forum_id = ? AND company_name = ? AND user_id = ?',
                    [1, $forum_id, $companyKey, $w['student_id']]
                );
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public static function planifier_fames(int $forum_id, Connection $conn): string
    {
        // Charger les données nécessaires
        $data = self::charger_donnees($forum_id, $conn);

        // Calculer les durées optimales par entreprise/type
        $durations = self::calculer_durees($data['companies'], $data['availability'], $data['reqs']);

        // Placer les rendez-vous selon les disponibilités et durées
        $result = self::placer_rendezvous($data['companies'], $data['availability'], $data['reqs'], $durations);

        // Compacter les rendez-vous étudiants pour minimiser les temps morts
        $planning_compact = self::compacter_attente_etudiants(
            $result['planning'],
            $result['busy_company'],
            $result['busy_student'],
            $data['availability']
        );

        // Écrire le planning et la liste d'attente en base
        self::ecrire_en_base($forum_id, $planning_compact, $result['waitlist'], $conn);

        return "OK";
    }

    /**
     * Check that expected tables/columns exist in the connected database.
     * Returns an array of human-friendly missing items (empty if OK).
     */
    public static function check_schema(Connection $conn): array
    {
        $required = [
            'forum' => ['forum_id'],
            'company' => ['company_name'],
            'is_present' => ['forum_id', 'company_name', 'start_time', 'end_time'],
            'appointment' => ['user_id', 'forum_id', 'company_name', 'appointment_request', 'appointment_time'],
            'users' => ['user_id', 'user_firstname', 'user_lastname', 'user_level', 'user_role'],
        ];

        $missing = [];
        foreach ($required as $table => $cols) {
            try {
                $rows = $conn->fetchAllAssociative(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                    ['public', $table]
                );
            } catch (\Throwable $e) {
                // If the information_schema query fails, report the table as missing
                $missing[] = "table {$table} (could not query information_schema): {$e->getMessage()}";
                continue;
            }
            if (empty($rows)) {
                $missing[] = "missing table: {$table}";
                continue;
            }
            $have = array_map(function ($r) { return $r['column_name']; }, $rows);
            foreach ($cols as $c) {
                if (!in_array($c, $have, true)) {
                    $missing[] = "table {$table} missing column: {$c}";
                }
            }
        }

        return $missing;
    }
}

