<?php

declare(strict_types=1);

namespace Hillmeet\Services;

/**
 * Builds a single VEVENT in iCalendar format (RFC 5545) for a meeting time.
 * All times in UTC; clients display in local timezone.
 */
final class IcsGenerator
{
    /**
     * @param string $summary Event title (e.g. poll title)
     * @param string $startUtc Start datetime in UTC, format Y-m-d H:i:s
     * @param string $endUtc End datetime in UTC, format Y-m-d H:i:s
     * @param string $organizerEmail Organizer mailto
     * @param string|null $uid Optional UID; if null a unique one is generated
     */
    public static function singleEvent(
        string $summary,
        string $startUtc,
        string $endUtc,
        string $organizerEmail,
        ?string $uid = null
    ): string {
        $dtStart = self::formatUtcIcs($startUtc);
        $dtEnd = self::formatUtcIcs($endUtc);
        $uid = $uid ?? 'hillmeet-' . bin2hex(random_bytes(8)) . '@' . (parse_url(\Hillmeet\Support\config('app.url', 'https://hillmeet.local'), PHP_URL_HOST) ?: 'hillmeet');
        $now = gmdate('Ymd\THis\Z');
        $summaryEsc = self::escapeIcs($summary);
        $organizerEsc = self::escapeIcs('mailto:' . $organizerEmail);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Hillmeet//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $now,
            'DTSTART:' . $dtStart,
            'DTEND:' . $dtEnd,
            'SUMMARY:' . $summaryEsc,
            'ORGANIZER:' . $organizerEsc,
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines);
    }

    private static function formatUtcIcs(string $mysqlUtc): string
    {
        $ts = strtotime($mysqlUtc);
        return gmdate('Ymd\THis\Z', $ts);
    }

    private static function escapeIcs(string $s): string
    {
        $s = str_replace(["\r\n", "\n", "\r", '\\', ';', ','], ['\n', '\n', '\n', '\\\\', '\\;', '\\,'], $s);
        return $s;
    }
}
