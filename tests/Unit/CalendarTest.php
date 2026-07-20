<?php

namespace Tests\Unit;

use PozarniPoplach\Calendar;

require_once __DIR__ . '/../../include/class.Calendar.php';

beforeEach(function () {
    // Create dates relative to today so they fall within the default 'max_ahead' (+1 year)
    // Use +2 and +3 days to avoid ambiguity with current time and +1 day/36 hours
    $date1 = date('Ymd', strtotime('+2 days'));
    $date2 = date('Ymd', strtotime('+3 days'));

    $this->icsContent = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//hacksw/handcal//NONSGML v1.0//EN
BEGIN:VEVENT
UID:event1
DTSTART:{$date1}T100000Z
DTEND:{$date1}T110000Z
SUMMARY:Event 1
LOCATION:Location 1
DESCRIPTION:Description 1
END:VEVENT
BEGIN:VEVENT
UID:event2
DTSTART:{$date2}T120000Z
DTEND:{$date2}T130000Z
SUMMARY:Event 2
LOCATION:Location 2
DESCRIPTION:Description 2
END:VEVENT
END:VCALENDAR";

    $this->calendar = new Calendar($this->icsContent);
});

afterEach(function () {
});

test('it can be instantiated', function () {
    expect($this->calendar)->toBeInstanceOf(Calendar::class);
    expect($this->calendar->calendar_url)->toBe($this->icsContent);
});

test('it handles null URL in constructor', function () {
    $calendar = new Calendar(null);
    expect($calendar->calendar_url)->toBe('');
});

test('it fetches and formats events correctly', function () {
    $events = $this->calendar->getCalendar();

    expect($events)->toBeArray()
        ->and($events)->toHaveCount(2);

    expect($events[0])->toHaveKeys(['title', 'start', 'end', 'location', 'link', 'description', 'status']);
    expect($events[0]['title'])->toBe('Event 1');
    expect($events[0]['location'])->toBe('Location 1');
    expect($events[0]['description'])->toBe('Description 1');

    // Check ISO 8601 date format
    expect($events[0]['start'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

test('it respects the limit parameter', function () {
    $events = $this->calendar->getCalendar('SORT_ASC', 1);
    expect($events)->toHaveCount(1);
    expect($events[0]['title'])->toBe('Event 1');
});

test('it respects the sort parameter', function () {
    // Default is ASC, let's test DESC
    $events = $this->calendar->getCalendar('SORT_DESC');
    expect($events)->toHaveCount(2);
    expect($events[0]['title'])->toBe('Event 2');
});

test('it respects the max_ahead parameter', function () {
    // If we look only 1 day ahead, we should find nothing (since events are +2 and +3 days)
    $events = $this->calendar->getCalendar('SORT_ASC', 10, '+1 day');
    expect($events)->toBeEmpty();

    // If we look 3 days ahead, we should find the first event (+2 days)
    // rangeEnd becomes D+3 00:00:00. Event 1 (D+2 10:00:00) is < D+3 00:00:00.
    $events = $this->calendar->getCalendar('SORT_ASC', 10, '+3 days');
    expect($events)->toHaveCount(1);
    expect($events[0]['title'])->toBe('Event 1');
});

test('it returns empty array when no events are in range', function () {
    // Create events in the past
    $datePast = date('Ymd', strtotime('-10 days'));
    $icsContent = "BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:past_event
DTSTART:{$datePast}T100000Z
DTEND:{$datePast}T110000Z
SUMMARY:Past Event
END:VEVENT
END:VCALENDAR";
    // Re-instantiate to reload the file (ics-parser loads on construct)
    $this->calendar = new Calendar($icsContent);

    $events = $this->calendar->getCalendar();
    expect($events)->toBeEmpty();
});

test('it handles missing optional fields', function () {
    $date = date('Ymd', strtotime('+2 days'));
    $icsContent = "BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:minimal
DTSTART:{$date}T100000Z
DTEND:{$date}T110000Z
SUMMARY:Minimal Event
END:VEVENT
END:VCALENDAR";
    $this->calendar = new Calendar($icsContent);

    $events = $this->calendar->getCalendar();
    expect($events[0]['location'])->toBe('')
        ->and($events[0]['description'])->toBe('')
        ->and($events[0]['status'])->toBe('');
});
