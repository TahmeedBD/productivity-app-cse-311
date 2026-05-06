# Day Overview Manual E2E Test Plan

This checklist is based on the current git diff for the Overview / Day Overview feature and its supporting backend changes.

## Scope Covered

- New `Overview` / `Day Overview` page.
- Date-scoped loading for daily log and time entries.
- Add, edit, and delete time entries from the Day Overview page.
- Visual rendering of donut summary, timeline bar, gap rows, and empty states.
- Asset versioning and stylesheet import changes that affect Chrome delivery.
- Seed script added to generate realistic report data.

## Recommended Test Setup

1. Start the app in Docker with a fresh build so assets match the working tree.

```bash
docker compose up -d --build
```

2. Seed a dedicated manual-test user with realistic data.

```bash
docker compose exec -T -w /var/www web php scripts/seed_dummy_data.php qa.reports@example.com
```

3. Sign in as that user in Chrome.

- If dev auto-login is still enabled, opening a protected page is enough.
- If normal login is required, use `qa.reports@example.com` and password `DummySeed#ChangeMe1`.

4. Open Chrome DevTools before testing.

- Use the `Console` tab to watch for JS errors.
- Use the `Network` tab with `Preserve log` enabled for endpoint and asset checks.

## Core Smoke Tests

- [ ] Open `/reports.php` directly. Expected: page loads without redirect loops or PHP warnings.
- [ ] Open the page from the main nav. Expected: the `Overview` nav item is highlighted as active.
- [ ] Confirm the page header shows `Day Overview` and the toolbar renders previous day, next day, date picker, `Today`, and `Add Entry` controls.
- [ ] Confirm there are no uncaught errors in Chrome DevTools Console on first load.
- [ ] Refresh the page normally. Expected: the page stays functional and the selected date remains correct.
- [ ] Hard refresh the page with Chrome. Expected: the latest JS and CSS still load correctly and the page still works.

## Asset Delivery Checks

- [ ] In Chrome Network, reload `/reports.php`. Expected: `/css/style.css?v=...` is requested with a version string.
- [ ] In Chrome Network, reload `/reports.php`. Expected: `/js/main.js?v=...` is requested with a version string.
- [ ] In Chrome Network, confirm `style.css` imports `tokens.css`, `base.css`, and `components.css` successfully with no missing-file errors.
- [ ] Confirm there is no stale-style behavior after refresh when switching between pages and returning to `Overview`.

## Date Routing And Navigation

- [ ] Land on `/reports.php` with no query string. Expected: the selected day defaults to today.
- [ ] Click the previous-day button once. Expected: the page reloads data for yesterday and the URL becomes `/reports.php?date=YYYY-MM-DD`.
- [ ] Click the next-day button after moving to a past day. Expected: the page advances one day at a time.
- [ ] Return to today using the `Today` button. Expected: the label returns to today and the next-day button becomes disabled.
- [ ] Confirm the next-day button is disabled when the selected day is today.
- [ ] Pick a specific past date using the date picker. Expected: the selected day changes immediately and the URL query string updates to that exact date.
- [ ] Refresh after choosing a past date. Expected: the same date stays selected after reload.
- [ ] Open a copied deep link such as `/reports.php?date=2026-05-04`. Expected: the page opens on that exact day.
- [ ] Open `/reports.php?date=bad-value`. Expected: the page falls back to today instead of crashing.
- [ ] Change dates a few times, then press the browser Back button once. Expected: Chrome goes back to the previous page, not back through every date change, because date changes replace history.

## Read-Only Rendering Checks

- [ ] On a seeded day with data, confirm the donut chart renders arcs instead of an empty ring.
- [ ] Confirm the center total shows the total tracked time for that day.
- [ ] Confirm the legend lists activities in descending duration order and includes an `Untracked` row.
- [ ] Confirm legend colors match the donut segment colors.
- [ ] Confirm the timeline bar shows a continuous awake-window strip from wake time to sleep time.
- [ ] Confirm tracked entries use colored segments and untracked gaps use the striped gap styling.
- [ ] Confirm the wake, midpoint, and sleep labels render and look plausible for the selected day.
- [ ] On today, confirm the `now` marker appears only if the current local time is between wake time and sleep time.
- [ ] On any past day, confirm the `now` marker is hidden.
- [ ] Confirm the timeline list is in chronological order from the beginning of the day to the end of the day.
- [ ] Confirm the entry count badge matches the number of non-gap rows that represent real entries.
- [ ] If the seeded data includes visible gaps, confirm each gap row shows `Untracked`, its duration, and a `+ Add entry` action.
- [ ] If any entry has notes but no subtype, confirm the row subtitle falls back to notes.
- [ ] If any entry has a subtype, confirm the row subtitle shows the subtype name.
- [ ] If you have or create any `Sleep` activity entry on the selected day, confirm it does not appear in the donut, timeline bar, or timeline list.

## Empty And Boundary Day Checks

- [ ] Pick a date outside the seeded 30-day range, for example a much older date. Expected: the page loads without errors, shows `0 entries`, and renders an empty-state timeline instead of failing.
- [ ] On that empty day, confirm the donut center shows `0h 00m` and the legend shows `No tracked work` plus `0h 00m`.
- [ ] On that empty day, confirm the timeline list shows `No awake-window data available for this day.`
- [ ] Pick a future date using the date picker. Expected: the page should remain stable, the URL should update, and no frontend crash should occur.

## Add Entry From Toolbar

- [ ] On a past day such as `2026-05-04`, click `Add Entry`. Expected: the modal opens with title `Add Entry`, the selected-day label, and the delete button hidden.
- [ ] In the modal, do not choose an activity and try to submit. Expected: inline error `Activity is required.`
- [ ] Choose an activity but leave start or end empty and try to submit. Expected: inline error `Start and end times are required.`
- [ ] Choose an activity and confirm the subtype dropdown reloads based on that activity.
- [ ] If the chosen activity has subtypes, confirm subtype options appear.
- [ ] If the chosen activity has no subtypes, confirm the subtype control stays usable and does not break submission.
- [ ] Save a valid entry on a past day. Expected: the modal closes, the new row appears on that same selected day, the count badge increments, and donut/timeline totals update.
- [ ] After adding a past-day entry, switch back to today. Expected: the new entry does not appear on today unless it was created for today.
- [ ] Re-open the same past date. Expected: the new entry is still present after reload.

## Add Entry From Gap Rows

- [ ] On a seeded day with a visible gap row, click `+ Add entry`. Expected: the modal opens with start and end prefilled to the gap boundaries.
- [ ] Save a valid entry using those prefilled times. Expected: the gap shrinks or disappears and the new entry appears in the correct chronological position.
- [ ] Confirm the donut totals and `Untracked` legend value update after filling part of a gap.

## Edit Existing Entry

- [ ] Click `Edit` on an existing entry. Expected: the modal opens with title `Edit Entry`, the delete button visible, and the current activity, subtype, start, end, and notes prefilled.
- [ ] Change only the notes and save. Expected: the row subtitle updates if notes are the fallback text and no duplicate entry is created.
- [ ] Change the activity and subtype and save. Expected: the row updates and the donut legend/totals move time into the new activity bucket.
- [ ] Change the start and end times and save. Expected: the timeline bar and list reorder or resize as needed.
- [ ] After editing a past-day entry, refresh the page. Expected: the edited values persist.

## Delete Existing Entry

- [ ] Open an existing entry in edit mode and click `Delete`. Expected: Chrome shows a confirmation dialog.
- [ ] Cancel the confirmation dialog. Expected: nothing is deleted.
- [ ] Re-open the same entry and confirm it still exists after canceling.
- [ ] Confirm delete again and accept the dialog. Expected: the modal closes, the row disappears, the count badge decrements, and donut/timeline totals update.
- [ ] Refresh the page after deletion. Expected: the deleted entry stays gone.

## Error Handling And Backend Contract Checks

- [ ] In Chrome, open `/daily-log/today.php?date=2026-05-04` while authenticated. Expected: HTTP 200 JSON with `ok: true` and `daily_log.date` equal to `2026-05-04`.
- [ ] In Chrome, open `/time-entries/today.php?date=2026-05-04` while authenticated. Expected: HTTP 200 JSON with the selected day’s entries.
- [ ] In Chrome, open `/daily-log/today.php?date=04-05-2026`. Expected: HTTP 422 JSON with `Invalid date format. Expected YYYY-MM-DD.`
- [ ] In Chrome, open `/time-entries/today.php?date=04-05-2026`. Expected: HTTP 422 JSON with `Invalid date format. Expected YYYY-MM-DD.`
- [ ] Create a valid entry from the Day Overview page, then in Chrome Network use `Copy as fetch` on that request, change only the `date` field to an invalid format such as `05/04/2026`, and replay it from the Console. Expected: the response is HTTP 422 with `date must be in YYYY-MM-DD format.`

## No-Activity Account Check

- [ ] Test the page with a separate account that has no activities configured. Expected: opening `Add Entry` should not crash the page.
- [ ] On that no-activity account, open `Add Entry`. Expected: the activity select is disabled with a clear empty-state message and saving is not possible until activities exist.

## Time Log Local-Time Regression Checks

- [ ] Open `/time_logger.php` in Chrome and confirm the page loads the browser-local current day instead of drifting to a different day because of server timezone.
- [ ] On the Time Log page, click `Start` with no running entry. Expected: the new running entry is created at the browser's current local time without an awake-window validation error caused only by server/client timezone mismatch.
- [ ] With a running entry open, use `Save`, `Stop`, and `Stop and Start`. Expected: each action stays on the same browser-local day as the currently displayed running entry and does not silently jump to a different day.
- [ ] In Chrome DevTools `More tools -> Sensors`, override the timezone to a different region, reload `/time_logger.php`, and repeat `Start` and `Stop`. Expected: load and write behavior remains internally consistent for that browser-local timezone.

## Responsive Chrome Checks

- [ ] In Chrome desktop width, confirm the layout renders as two columns with the donut summary on the left and timeline content on the right.
- [ ] In Chrome responsive mode around tablet width, confirm the layout collapses cleanly to one column with no overlapping controls.
- [ ] In Chrome responsive mode around mobile width, confirm the toolbar stacks vertically, the date nav remains usable, and the timeline rows do not overflow horizontally in a broken way.
- [ ] On mobile width, open the add/edit modal and confirm all fields and buttons are reachable without clipped content.

## Final Ship Checklist

- [ ] No JS console errors across smoke, add, edit, delete, date navigation, and responsive runs.
- [ ] No failed CSS or JS asset requests in Chrome Network.
- [ ] No incorrect date leakage where a past-day add or edit appears on today.
- [ ] No stale data after refresh on any tested date.
- [ ] No broken empty states, gap states, or modal states.
- [ ] All direct date-format validation checks return the expected 422 responses.

If every item above passes in Chrome, the Day Overview feature and its date-scoped backend support are in good manual-ship shape for this diff.
