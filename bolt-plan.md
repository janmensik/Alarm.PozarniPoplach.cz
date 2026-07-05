## Performance Analysis

I'm acting as Bolt, looking for ONE measurable performance improvement.

Looking at `ui/alpine.js`, there's a significant frontend performance bottleneck in the `fetchData` loop.
Currently, the application polls `apiUrl` (the `/api/dispatch` endpoint) every `DISPATCH_POLL_INTERVAL_MS` (30 seconds).

Inside `fetchData()`, we have this block:
```javascript
        // If peacetime and no ad, fetch calendar
        if (this.data.dispatch_status === 'peacetime' && !this.data.ad) {
          this.fetchCalendar();
        } else {
          this.calendarEvents = null;
        }
```

This means that if there's no alarm and no ad, the application makes an API request to `calendarUrl` (`/api/calendar`) *every 30 seconds*.

However, calendar events (`.ics` feeds) change very rarely (maybe once a day or once a week). The `.ics` feeds are parsed from remote servers. Even though we lazily instantiate the parser (from my previous memory), polling a remote calendar feed every 30 seconds is a huge waste of resources and can easily hit rate limits on the calendar provider side (e.g., Google Calendar, Apple iCloud).

### Proposed Fix

I will add a frontend cache for the calendar data in `ui/alpine.js` to avoid hitting the calendar endpoint on every dispatch poll during peacetime. I will cache the calendar data for a reasonable amount of time, like 1 hour (3600000 ms), since upcoming unit events are not real-time critical like alarms.

This will reduce the number of `/api/calendar` requests by a factor of 120 (from 120 requests/hour to 1 request/hour per connected kiosk).

### Plan

1. Modify `ui/alpine.js` to cache `calendarEvents` and only fetch if a certain time (e.g., 1 hour) has passed.
2. Ensure tests pass.
3. Complete pre-commit instructions.
4. Submit the PR with Bolt formatting.
