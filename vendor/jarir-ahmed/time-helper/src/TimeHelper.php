<?php

namespace JarirAhmed\TimeHelper;

class TimeHelper
{
    /**
     * Get the current time in a specific format.
     *
     * @param string $format The format for the time.
     * @return string The current time.
     */
    public function getCurrentTime(string $format = 'H:i:s'): string
    {
        return date($format);
    }

    /**
     * Get the current date in a specific format.
     *
     * @param string $format The format for the date.
     * @return string The current date.
     */
    public function getCurrentDate(string $format = 'Y-m-d'): string
    {
        return date($format);
    }

    /**
     * Get the current date and time in a specific format.
     *
     * @param string $format The format for the date and time.
     * @return string The current date and time.
     */
    public function getCurrentDateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }

    /**
     * Get the current time in a specific UTC timezone.
     *
     * @param string $timezone The timezone identifier (e.g., 'UTC', 'America/New_York').
     * @param string $format The format for the time.
     * @return string The current time in the specified timezone.
     */
    public function getCurrentTimeInTimezone(string $timezone, string $format = 'H:i:s'): string
    {
        $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
        return $dateTime->format($format);
    }

    /**
     * Get the current date in a specific UTC timezone.
     *
     * @param string $timezone The timezone identifier.
     * @param string $format The format for the date.
     * @return string The current date in the specified timezone.
     */
    public function getCurrentDateInTimezone(string $timezone, string $format = 'Y-m-d'): string
    {
        $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
        return $dateTime->format($format);
    }

    /**
     * Get the current date and time in a specific UTC timezone.
     *
     * @param string $timezone The timezone identifier.
     * @param string $format The format for the date and time.
     * @return string The current date and time in the specified timezone.
     */
    public function getCurrentDateTimeInTimezone(string $timezone, string $format = 'Y-m-d H:i:s'): string
    {
        $dateTime = new \DateTime('now', new \DateTimeZone($timezone));
        return $dateTime->format($format);
    }

    /**
     * Calculate the difference between two dates.
     *
     * @param string $date1 The first date.
     * @param string $date2 The second date.
     * @param string $format The format for the output.
     * @return string The difference between the two dates.
     */
    public function getDateDifference(string $date1, string $date2, string $format = '%y years, %m months, %d days'): string
    {
        $dt1 = new \DateTime($date1);
        $dt2 = new \DateTime($date2);
        $interval = $dt1->diff($dt2);

        return $interval->format($format);
    }

    /**
     * Convert a date/time from one timezone to another.
     *
     * @param string $dateTime The date/time to convert.
     * @param string $fromTimezone The timezone of the original date/time.
     * @param string $toTimezone The target timezone.
     * @return string The converted date/time.
     */
    public function convertTimezone(string $dateTime, string $fromTimezone, string $toTimezone): string
    {
        $date = new \DateTime($dateTime, new \DateTimeZone($fromTimezone));
        $date->setTimezone(new \DateTimeZone($toTimezone));

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Add or subtract time to/from the current date/time.
     *
     * @param int $seconds Number of seconds to add (negative for subtracting).
     * @param int $minutes Number of minutes to add (negative for subtracting).
     * @param int $hours Number of hours to add (negative for subtracting).
     * @param int $days Number of days to add (negative for subtracting).
     * @param int $months Number of months to add (negative for subtracting).
     * @param int $years Number of years to add (negative for subtracting).
     * @return string The new date/time.
     */
    public function timeTravel(
        int $seconds = 0,
        int $minutes = 0,
        int $hours = 0,
        int $days = 0,
        int $months = 0,
        int $years = 0
    ): string {
        $dateTime = new \DateTime();
        $dateTime->modify(sprintf(
            '%+d seconds %+d minutes %+d hours %+d days %+d months %+d years',
            $seconds, $minutes, $hours, $days, $months, $years
        ));
        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Check if a year is a leap year.
     *
     * @param int $year The year to check.
     * @return bool True if it's a leap year, false otherwise.
     */
    public function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * Get the list of all timezones.
     *
     * @return array An array of timezone identifiers.
     */
    public function getTimezones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    /**
     * Generate a random string based on the current time.
     *
     * @param int $length The length of the generated string (default: 60).
     * @return string The generated random string.
     */
    public function generateRandomString(int $length = 60): string
    {
        $time = microtime(true);
        $randomString = md5($time . uniqid('', true));

        return substr($randomString, 0, $length);
    }

        /**
     * Measure the execution time of a PHP script.
     *
     * @param float $startTime The start time of the script execution.
     * @return float The total execution time in seconds.
     */
    public function measureExecutionTime(float $startTime): float
    {
        $endTime = microtime(true); // Get the end time
        return $endTime - $startTime; // Calculate execution time
    }

        /**
     * Sleep for a specified number of seconds.
     *
     * @param int $seconds The number of seconds to sleep.
     */
    public function sleepFor(int $seconds): void
    {
        sleep($seconds); // Pause execution for the specified time
    }

    /**
     * Sleep for a specified number of milliseconds.
     *
     * @param int $milliseconds The number of milliseconds to sleep.
     */
    public function sleepForMilliseconds(int $milliseconds): void
    {
        usleep($milliseconds * 1000); // Pause execution for the specified time
    }

        public function calculateAge(string $dateOfBirth): int
    {
        $dob = new \DateTime($dateOfBirth);
        $today = new \DateTime();
        return $today->diff($dob)->y;
    }

        public function isDateInPast(string $date): bool
    {
        return new \DateTime($date) < new \DateTime();
    }

        public function getDayOfWeek(string $date): string
    {
        return (new \DateTime($date))->format('l'); // Returns full name of the day
    }
    
    /**
     * Get the Unix timestamp of the current date/time or a specific date/time.
     *
     * @param string|null $dateTime The date/time to convert (default: null for current time).
     * @return int The Unix timestamp.
     */
    public function getTimestamp(string $dateTime = null): int
    {
        if ($dateTime === null) {
            return time(); // Current timestamp
        }
        return (new \DateTime($dateTime))->getTimestamp();
    }

    /**
     * Check if a given date is today.
     *
     * @param string $date The date to check.
     * @return bool True if the date is today, false otherwise.
     */
    public function isToday(string $date): bool
    {
        return (new \DateTime($date))->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
    }

     /**
     * Get the start and end times of a given day.
     *
     * @param string $date The date for which to get the start and end times.
     * @return array An associative array with 'start' and 'end' keys.
     */
    public function getStartAndEndOfDay(string $date): array
    {
        $start = (new \DateTime($date))->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $end = (new \DateTime($date))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        return ['start' => $start, 'end' => $end];
    }


    /**
     * Format a date according to the specified locale.
     *
     * @param string $date The date to format.
     * @param string $locale The locale to use (e.g., 'en_US').
     * @param string $format The format string.
     * @return string The formatted date.
     */
    public function formatDateAccordingToLocale(string $date, string $locale, string $format): string
    {
        $dateFormatter = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        return $dateFormatter->format(new \DateTime($date));
    }

    /**
     * Get the week number of the year for a given date.
     *
     * @param string $date The date to check.
     * @return int The week number of the year.
     */
    public function getWeekNumber(string $date): int
    {
        return (new \DateTime($date))->format('W');
    }

    /**
     * Get the first and last days of the month for a given date.
     *
     * @param string $date The date to check.
     * @return array An associative array with 'first' and 'last' keys.
     */
    public function getFirstAndLastDayOfMonth(string $date): array
    {
        $first = (new \DateTime($date))->modify('first day of this month')->format('Y-m-d');
        $last = (new \DateTime($date))->modify('last day of this month')->format('Y-m-d');
        return ['first' => $first, 'last' => $last];
    }

    /**
     * Calculate the number of days until a future date.
     *
     * @param string $futureDate The future date.
     * @return int The number of days until the future date.
     */
    public function daysUntil(string $futureDate): int
    {
        $today = new \DateTime();
        $future = new \DateTime($futureDate);
        return $today->diff($future)->days;
    }

    /**
     * Get the name of the month for a given date.
     *
     * @param string $date The date to check.
     * @return string The name of the month.
     */
    public function getMonthName(string $date): string
    {
        return (new \DateTime($date))->format('F'); // Full month name
    }

        /**
     * Get all dates between two dates.
     *
     * @param string $startDate The start date.
     * @param string $endDate The end date.
     * @return array An array of dates.
     */
    public function getAllDatesInRange(string $startDate, string $endDate): array
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $dates = [];

        while ($start <= $end) {
            $dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }

        return $dates;
    }

        /**
     * Get the number of business days between two dates.
     *
     * @param string $startDate The start date.
     * @param string $endDate The end date.
     * @return int The number of business days.
     */
    public function getBusinessDaysBetween(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $businessDays = 0;

        while ($start <= $end) {
            if ($start->format('N') < 6) { // 1 (Monday) to 5 (Friday)
                $businessDays++;
            }
            $start->modify('+1 day');
        }

        return $businessDays;
    }
    /**
     * Get an approximation of CLOCKS_PER_SEC.
     *
     * This function returns a value representing the number of microseconds in one second.
     * In other words, it returns 1,000,000 microseconds per second.
     *
     * @return int Number of microseconds per second (1,000,000).
     */
    public function getClocksPerSec(): int
    {
        return 1000000; // Microseconds per second
    }
    public function generateRandomNumber(int $length = 10): string
{
    $randomNumber = '';
    for ($i = 0; $i < $length; $i++) {
        $randomNumber .= mt_rand(0, 9);
    }

    return $randomNumber;
}

public function roll_dice(): int
{
    return rand(1, 6);
}
    
public function generateRandomNumberInRange(int $min, int $max): int
{
    return rand($min, $max);
}


}
