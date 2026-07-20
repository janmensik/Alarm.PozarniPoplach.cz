<?php

namespace PozarniPoplach;

use ICal\ICal;

/**
 * Class Calendar
 *
 * Handles parsing and retrieving upcoming events from an iCalendar (.ics) feed.
 */
class Calendar
{
    /**
     * @var string The URL to the .ics calendar feed.
     */
    public string $calendar_url = '';

    /**
     * @var ICal|null The ICal parser instance.
     */
    private ?ICal $ical = null;

    # ...................................................................
    /**
     * Calendar constructor.
     *
     * @param string|null $calendar_url Optional URL of the iCalendar feed to parse.
     */
    public function __construct(?string $calendar_url = null)
    {
        if (!$calendar_url) {
            return;
        }

        $this->calendar_url = $calendar_url;
    }

    # ...................................................................
    /**
     * Fetches, sorts, and formats upcoming calendar events.
     *
     * @param string|null $sort The sorting order for the events (default: 'SORT_ASC').
     * @param int|null $limit The maximum number of events to retrieve (default: 10).
     * @param string|null $max_ahead The maximum date range in the future to look for events (default: '+1 year').
     * @return array Returns an array of formatted events containing title, start, end, location, link, description, and status.
     */
    public function getCalendar($sort = SORT_ASC, ?int $limit = 10, ?string $max_ahead = '+1 year'): array
    {

        if (empty($this->calendar_url)) {
            return [];
        }

        // Map string sort flags to constants if needed
        switch ($sort) {
            case 'SORT_DESC':
                $sort = SORT_DESC;
                break;
            case 'SORT_ASC':
            default:
                $sort = SORT_ASC; // Default to ascending if invalid value provided
                break;
        }

        // Validate URL scheme to prevent SSRF and LFI vulnerabilities, or allow raw iCalendar content
        $scheme = parse_url($this->calendar_url, PHP_URL_SCHEME);
        $is_valid_scheme = in_array(strtolower((string)$scheme), ['http', 'https'], true);
        $is_raw_content = str_starts_with(trim($this->calendar_url), 'BEGIN:VCALENDAR');

        if (!$is_valid_scheme && !$is_raw_content) {
            // Log security warning here if logger available
            return [];
        }

        // Delay instantiation of ICal to prevent synchronous network requests in the constructor
        if ($this->ical === null) {
            $this->ical = new ICal($this->calendar_url, [
                'defaultTimeZone' => date_default_timezone_get(),
            ]);
        }

        // Fetch events from today to 1 year in the future
        $events = $this->ical->eventsFromRange(date('Y-m-d'), date('Y-m-d', strtotime($max_ahead)));
        $output = [];

        if ($events) {
            // Sort chronologically
            $events = $this->ical->sortEventsWithOrder($events, $sort);

            // Limit to the next 10 upcoming events
            $events = array_slice($events, 0, (int) $limit);

            foreach ($events as $event) {
                $output[] = $this->formatEvent($event);
            }
        }

        return $output;
    }

    # ...................................................................
    /**
     * Formats a single calendar event.
     *
     * @param \ICal\Event $event The raw event object from ICal.
     * @return array The formatted event array.
     */
    private function formatEvent(\ICal\Event $event): array
    {
        return [
            'title'       => $event->summary,
            // Convert dtstart to ISO 8601 so JS parses it flawlessly
            'start'       => date('c', strtotime($event->dtstart)),
            'end'         => date('c', strtotime($event->dtend)),
            'location'    => $event->location ?? '',
            'link'        => '', // .ics feeds rarely provide a direct HTML link back to Google
            'description' => $event->description ?? '',
            'status'      => $event->status ?? ''
        ];
    }
}
