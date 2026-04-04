<?php
declare(strict_types=1);

namespace Nemesis\Support;

class Time {
    public static function now() {
        return date('Y-m-d H:i:s');
    }

    public static function diff($date1, $date2) {
        $d1 = new \DateTime($date1);
        $d2 = new \DateTime($date2);
        return $d1->diff($d2);
    }

    public static function businessDays($startDate, $endDate) {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $days = 0;
        while ($start <= $end) {
            if ($start->format('N') < 6) $days++;
            $start->modify('+1 day');
        }
        return $days;
    }

    public static function range($startDate, $endDate) {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $dates = [];
        while ($start <= $end) {
            $dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }
        return $dates;
    }

    public static function travel($modifier) {
        return (new \DateTime())->modify($modifier)->format('Y-m-d H:i:s');
    }

    // --- Added Utilities ---

    public static function isLeapYear($year = null) {
        $year = $year ?: date('Y');
        return (($year % 4 == 0) && ($year % 100 != 0)) || ($year % 400 == 0);
    }

    public static function age($date) {
        return date_diff(date_create($date), date_create('now'))->y;
    }

    public static function isPast($date) {
        return strtotime($date) < time();
    }

    public static function isFuture($date) {
        return strtotime($date) > time();
    }

    public static function isToday($date) {
        return date('Y-m-d', strtotime($date)) === date('Y-m-d');
    }

    public static function dayOfWeek($date) {
        return date('l', strtotime($date));
    }

    // --- Final Parity Additions ---

    public static function format($date, $format = 'Y-m-d') { return date($format, strtotime($date)); }
    public static function timestamp($date = null) { return $date ? strtotime($date) : time(); }
    public static function timezone($date, $from, $to) {
        $dt = new \DateTime($date, new \DateTimeZone($from));
        $dt->setTimezone(new \DateTimeZone($to));
        return $dt->format('Y-m-d H:i:s');
    }
    
    public static function listTimezones() { return \DateTimeZone::listIdentifiers(); }
    public static function sleep($seconds) { sleep($seconds); }
    public static function usleep($ms) { usleep($ms * 1000); }
    public static function measure($start) { return microtime(true) - $start; }
    
    public static function startOfDay($date) { return date('Y-m-d 00:00:00', strtotime($date)); }
    public static function endOfDay($date) { return date('Y-m-d 23:59:59', strtotime($date)); }
    public static function firstDayOfMonth($date) { return date('Y-m-01', strtotime($date)); }
    public static function lastDayOfMonth($date) { return date('Y-m-t', strtotime($date)); }
    public static function monthName($date) { return date('F', strtotime($date)); }
    public static function weekNumber($date) { return date('W', strtotime($date)); }
    public static function daysUntil($date) { return (new \DateTime($date))->diff(new \DateTime())->days; }

    // Added: 2026-04-03

    /**
     * Format a duration in minutes as a human-readable string.
     *
     *   minutesToHm(0)   → '-'
     *   minutesToHm(45)  → '45m'
     *   minutesToHm(90)  → '1 H 30 m'
     *   minutesToHm(120) → '2 H 0 m'
     */
    public static function minutesToHm(int|float $minutes): string
    {
        if ($minutes <= 0) return '-';
        $h = (int) floor($minutes / 60);
        $m = (int) ($minutes % 60);
        return $h === 0 ? "{$m}m" : "{$h} H {$m} m";
    }

    /**
     * Format seconds as H:MM:SS (e.g. 3661 → '1:01:01').
     */
    public static function secondsToHms(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
}
