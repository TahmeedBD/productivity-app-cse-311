"use strict";
function toTimeOnlyInputValue(value) {
    if (!value) {
        return '';
    }
    const trimmed = value.trim();
    return trimmed.length >= 5 ? trimmed.slice(0, 5) : '';
}
function showDashboardMessage(message, variant = 'danger') {
    const messageElement = document.querySelector('#dashboard-message');
    if (!messageElement) {
        return;
    }
    if (message === null) {
        messageElement.hidden = true;
        messageElement.className = 'alert alert-danger';
        messageElement.textContent = '';
        return;
    }
    messageElement.hidden = false;
    messageElement.className = `alert alert-${variant}`;
    messageElement.textContent = message;
}
function getDashboardEntryEnd(entry) {
    if (entry.end) {
        return parseDateTime(entry.end);
    }
    return new Date();
}
function getDashboardTrackedTime(entries) {
    return entries.reduce((total, entry) => {
        const start = parseDateTime(entry.start);
        const end = getDashboardEntryEnd(entry);
        if (!start || !end || end.getTime() <= start.getTime()) {
            return total;
        }
        return total + (end.getTime() - start.getTime());
    }, 0);
}
function renderDashboardPage(schedule, entries, activitiesResponse) {
    var _a, _b, _c, _d;
    const trackedEntries = entries.filter((entry) => !isSleepEntry(entry));
    const trackedTime = getDashboardTrackedTime(trackedEntries);
    const runningEntry = getRunningEntry(trackedEntries);
    const activeActivityCount = new Set(trackedEntries
        .map((entry) => { var _a; return ((_a = entry.activity_name) !== null && _a !== void 0 ? _a : '').trim(); })
        .filter((name) => name !== '')).size;
    setElementText('#dashboard-date-label', new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    }).format(new Date()));
    setElementText('#dashboard-today-summary', `${trackedEntries.length} ${trackedEntries.length === 1 ? 'entry' : 'entries'} logged today.`);
    setElementText('#dashboard-wake-pill', `Wake ${formatClockTime(schedule.today_daily_log.wake_time, true)}`);
    setElementText('#dashboard-sleep-pill', `Sleep ${formatClockTime(schedule.today_daily_log.sleep_time, true)}`);
    setElementText('#dashboard-tracked-time', formatHoursMinutes(trackedTime));
    setElementText('#dashboard-tracked-time-sub', trackedEntries.length > 0
        ? `${trackedEntries.length} ${trackedEntries.length === 1 ? 'entry' : 'entries'} so far today`
        : 'No entries yet');
    setElementText('#dashboard-current-focus', ((_a = runningEntry === null || runningEntry === void 0 ? void 0 : runningEntry.activity_name) === null || _a === void 0 ? void 0 : _a.trim()) ||
        ((_b = runningEntry === null || runningEntry === void 0 ? void 0 : runningEntry.activity_subtype_name) === null || _b === void 0 ? void 0 : _b.trim()) ||
        (runningEntry ? 'Active entry' : 'Idle'));
    setElementText('#dashboard-current-focus-sub', runningEntry
        ? ((_c = runningEntry.activity_subtype_name) === null || _c === void 0 ? void 0 : _c.trim()) ||
            ((_d = runningEntry.notes) === null || _d === void 0 ? void 0 : _d.trim()) ||
            'Timer is running'
        : 'Nothing running right now');
    setElementText('#dashboard-activity-overview', `${activeActivityCount}/${activitiesResponse.activities.length}`);
    setElementText('#dashboard-activity-sub', 'Active today');
    setFieldValue('#dashboard-wake-time', toTimeOnlyInputValue(schedule.future_defaults.wake_time));
    setFieldValue('#dashboard-sleep-time', toTimeOnlyInputValue(schedule.today_daily_log.sleep_time));
}
function refreshDashboardPage() {
    return __awaiter(this, void 0, void 0, function* () {
        const todayDateKey = buildDateKey(new Date());
        const [scheduleResponse, entriesResponse, activitiesResponse] = yield Promise.all([
            fetchJson('/daily-log/schedule.php'),
            fetchJson(`/time-entries/today.php?date=${encodeURIComponent(todayDateKey)}`),
            fetchJson('/activities/list.php'),
        ]);
        renderDashboardPage(scheduleResponse, entriesResponse.entries.sort(compareEntryAscending), activitiesResponse);
    });
}
function bindDashboardPage() {
    const root = document.querySelector('#dashboard-app');
    if (!root) {
        return;
    }
    const form = document.querySelector('#dashboard-schedule-form');
    const wakeField = document.querySelector('#dashboard-wake-time');
    const sleepField = document.querySelector('#dashboard-sleep-time');
    const submitButton = document.querySelector('#dashboard-schedule-submit');
    form === null || form === void 0 ? void 0 : form.addEventListener('submit', (event) => __awaiter(this, void 0, void 0, function* () {
        var _a, _b;
        event.preventDefault();
        const wakeTime = normalizeTimeForApi((_a = wakeField === null || wakeField === void 0 ? void 0 : wakeField.value) !== null && _a !== void 0 ? _a : '');
        const sleepTime = normalizeTimeForApi((_b = sleepField === null || sleepField === void 0 ? void 0 : sleepField.value) !== null && _b !== void 0 ? _b : '');
        if (!wakeTime || !sleepTime) {
            showInlineError('#dashboard-schedule-error', 'Wake and sleep times are required.');
            return;
        }
        clearInlineError('#dashboard-schedule-error');
        showDashboardMessage(null);
        setButtonLoading(submitButton, true, 'Saving...');
        try {
            yield fetchJson('/daily-log/schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    wake_time: wakeTime,
                    sleep_time: sleepTime,
                }),
            });
            yield refreshDashboardPage();
            showDashboardMessage('Schedule updated.', 'success');
        }
        catch (error) {
            showInlineError('#dashboard-schedule-error', error instanceof Error
                ? error.message
                : "Unable to update today's schedule right now.");
        }
        finally {
            setButtonLoading(submitButton, false, 'Saving...');
        }
    }));
    void refreshDashboardPage().catch((error) => {
        showDashboardMessage(error instanceof Error ? error.message : 'Unable to load the dashboard.');
    });
}
bindDashboardPage();