<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\ContractKeyDate;
use App\Models\Contract;

class CalendarService
{
    /**
     * Generate an ICS calendar file content for a reminder.
     * Returns raw ICS string suitable for attaching to an email.
     */
    public function generateIcs(Reminder $reminder, Contract $contract, ContractKeyDate $keyDate): string
    {
        $uid     = 'ccrs-reminder-' . $reminder->id . '@digittal.io';
        $now     = gmdate('Ymd\THis\Z');
        $dtstart = gmdate('Ymd', strtotime($keyDate->date_value));
        $summary = 'CCRS Reminder: ' . $contract->title;
        $description = sprintf(
            'Contract: %s\nKey Date: %s (%s)\nReminder: %d days notice',
            $contract->title,
            $keyDate->date_type,
            $keyDate->date_value,
            $reminder->lead_days,
        );

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Digittal Group//CCRS//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART;VALUE=DATE:{$dtstart}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
    }
}
