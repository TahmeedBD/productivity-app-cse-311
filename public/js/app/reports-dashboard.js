"use strict";
const REPORTS_DASHBOARD_MAX_RANGE_DAYS = 90;
const REPORTS_DASHBOARD_DEFAULT_RANGE_DAYS = 30;
const initialReportsDashboardRange = getRequestedReportsDashboardRange();
let reportsDashboardRangeStart = initialReportsDashboardRange.start;
let reportsDashboardRangeEnd = initialReportsDashboardRange.end;
function isValidReportsDashboardDateKey(value) {
    const parsedDate = parseDateKey(value);
    return parsedDate !== null && buildDateKey(parsedDate) === value;
}
function getDefaultReportsDashboardRange() {
    const today = buildDateKey(new Date());
    return {
        start: addDays(today, -(REPORTS_DASHBOARD_DEFAULT_RANGE_DAYS - 1)),
        end: today,
    };
}
function normalizeReportsDashboardRange(start, end) {
    if (!isValidReportsDashboardDateKey(start) ||
        !isValidReportsDashboardDateKey(end) ||
        start > end) {
        return null;
    }
    return { start, end };
}
function getRequestedReportsDashboardRange() {
    var _a, _b, _c, _d;
    const url = new URL(window.location.href);
    const requestedStart = (_b = (_a = url.searchParams.get('from')) === null || _a === void 0 ? void 0 : _a.trim()) !== null && _b !== void 0 ? _b : '';
    const requestedEnd = (_d = (_c = url.searchParams.get('to')) === null || _c === void 0 ? void 0 : _c.trim()) !== null && _d !== void 0 ? _d : '';
    const normalizedRange = normalizeReportsDashboardRange(requestedStart, requestedEnd);
    return normalizedRange !== null && normalizedRange !== void 0 ? normalizedRange : getDefaultReportsDashboardRange();
}
function updateReportsDashboardUrlRange(start, end) {
    const url = new URL(window.location.href);
    url.searchParams.delete('date');
    url.searchParams.set('from', start);
    url.searchParams.set('to', end);
    window.history.replaceState({}, '', url.toString());
}
function countReportsDashboardDaysInclusive(start, end) {
    const startDate = parseDateKey(start);
    const endDate = parseDateKey(end);
    if (!startDate || !endDate) {
        return 0;
    }
    const millisecondsPerDay = 24 * 60 * 60 * 1000;
    return (Math.floor((endDate.getTime() - startDate.getTime()) / millisecondsPerDay) +
        1);
}
function buildReportsDashboardDateRange(start, end) {
    const totalDays = countReportsDashboardDaysInclusive(start, end);
    return Array.from({ length: totalDays }, (_, index) => addDays(start, index));
}
function getPreviousReportsDashboardRange(start, end) {
    const totalDays = countReportsDashboardDaysInclusive(start, end);
    const previousEnd = addDays(start, -1);
    return {
        start: addDays(previousEnd, -(totalDays - 1)),
        end: previousEnd,
    };
}
function getReportsDashboardEntryEnd(entry) {
    if (entry.end) {
        return parseDateTime(entry.end);
    }
    const entryDate = extractDateKeyFromTimestamp(entry.start);
    if (entryDate !== buildDateKey(new Date())) {
        return null;
    }
    return new Date();
}
function getReportsDashboardEntryDurationMs(entry) {
    const start = parseDateTime(entry.start);
    const end = getReportsDashboardEntryEnd(entry);
    if (!start || !end || end.getTime() <= start.getTime()) {
        return 0;
    }
    return end.getTime() - start.getTime();
}
function summarizeReportsDashboardActivities(entries) {
    var _a, _b;
    const durationByActivity = new Map();
    for (const entry of entries) {
        const durationMs = getReportsDashboardEntryDurationMs(entry);
        if (durationMs <= 0) {
            continue;
        }
        const activityName = ((_a = entry.activity_name) !== null && _a !== void 0 ? _a : 'Untitled').trim() || 'Untitled';
        durationByActivity.set(activityName, ((_b = durationByActivity.get(activityName)) !== null && _b !== void 0 ? _b : 0) + durationMs);
    }
    return Array.from(durationByActivity.entries())
        .sort((left, right) => right[1] - left[1])
        .map(([name, durationMs], index) => ({
        name,
        durationMs,
        color: getReportActivityColor(index),
    }));
}
function setReportsDashboardMessage(message) {
    const element = document.querySelector('#reports-dashboard-message');
    if (!element) {
        return;
    }
    if (message === null) {
        element.hidden = true;
        element.textContent = '';
        return;
    }
    element.hidden = false;
    element.textContent = message;
}
function buildReportsDashboardActivityConsistencyRows(activities, entries, start, end) {
    var _a;
    const totalDays = countReportsDashboardDaysInclusive(start, end);
    const activeDatesByActivityId = new Map();
    for (const entry of entries) {
        const activityId = entry.activity_id;
        const entryDateKey = extractDateKeyFromTimestamp(entry.start);
        if (activityId === null ||
            activityId === undefined ||
            entryDateKey === null ||
            !isValidReportsDashboardDateKey(entryDateKey) ||
            entryDateKey < start ||
            entryDateKey > end) {
            continue;
        }
        const key = String(activityId);
        const activeDates = (_a = activeDatesByActivityId.get(key)) !== null && _a !== void 0 ? _a : new Set();
        activeDates.add(entryDateKey);
        activeDatesByActivityId.set(key, activeDates);
    }
    return activities
        .map((activity, index) => {
        var _a, _b;
        const activeDays = (_b = (_a = activeDatesByActivityId.get(String(activity.id))) === null || _a === void 0 ? void 0 : _a.size) !== null && _b !== void 0 ? _b : 0;
        const rate = totalDays > 0 ? activeDays / totalDays : 0;
        return {
            name: activity.name,
            activeDays,
            totalDays,
            rate,
            color: getReportActivityColor(index),
        };
    })
        .sort((left, right) => right.activeDays - left.activeDays ||
        left.name.localeCompare(right.name));
}
function calculateReportsDashboardConsistencyRate(rows) {
    if (rows.length === 0) {
        return null;
    }
    const totalOpportunities = rows.reduce((total, row) => total + row.totalDays, 0);
    const totalActiveDays = rows.reduce((total, row) => total + row.activeDays, 0);
    return totalOpportunities > 0 ? totalActiveDays / totalOpportunities : 0;
}
function formatReportsDashboardPercentage(value) {
    if (value === null) {
        return '0%';
    }
    return `${Math.round(value * 100)}%`;
}
function buildReportsDashboardRelativeTrend(current, previous) {
    if (previous <= 0) {
        return {
            icon: '→',
            text: 'No prior period',
            state: 'muted',
        };
    }
    const delta = ((current - previous) / previous) * 100;
    return {
        icon: delta > 0 ? '↑' : delta < 0 ? '↓' : '→',
        text: `${delta > 0 ? '+' : ''}${Math.round(delta)}% vs last period`,
        state: delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat',
    };
}
function buildReportsDashboardPointTrend(current, previous, emptyLabel) {
    if (current === null) {
        return {
            icon: '→',
            text: emptyLabel,
            state: 'muted',
        };
    }
    if (previous === null) {
        return {
            icon: '→',
            text: 'No prior period',
            state: 'muted',
        };
    }
    const delta = Math.round((current - previous) * 100);
    return {
        icon: delta > 0 ? '↑' : delta < 0 ? '↓' : '→',
        text: `${delta > 0 ? '+' : ''}${delta}% vs last period`,
        state: delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat',
    };
}
function renderReportsDashboardTrend(selector, trend) {
    const element = document.querySelector(selector);
    if (!element) {
        return;
    }
    element.dataset.state = trend.state;
    element.innerHTML = `<span aria-hidden="true">${escapeHtml(trend.icon)}</span><span>${escapeHtml(trend.text)}</span>`;
}
function renderReportsDashboardSummaryCards(currentSummaries, previousSummaries, currentConsistencyRate, previousConsistencyRate) {
    var _a, _b;
    const totalCurrentMs = currentSummaries.reduce((total, summary) => total + summary.durationMs, 0);
    const totalPreviousMs = previousSummaries.reduce((total, summary) => total + summary.durationMs, 0);
    const mostActive = (_a = currentSummaries[0]) !== null && _a !== void 0 ? _a : null;
    setElementText('#reports-dashboard-total-time-value', formatHoursMinutes(totalCurrentMs));
    renderReportsDashboardTrend('#reports-dashboard-total-time-trend', buildReportsDashboardRelativeTrend(totalCurrentMs, totalPreviousMs));
    setElementText('#reports-dashboard-most-active-value', (_b = mostActive === null || mostActive === void 0 ? void 0 : mostActive.name) !== null && _b !== void 0 ? _b : 'No activity');
    setElementText('#reports-dashboard-most-active-sub', mostActive
        ? `${formatHoursMinutes(mostActive.durationMs)} logged`
        : 'No time logged in this range');
    setElementText('#reports-dashboard-completion-rate-value', formatReportsDashboardPercentage(currentConsistencyRate));
    renderReportsDashboardTrend('#reports-dashboard-completion-rate-trend', buildReportsDashboardPointTrend(currentConsistencyRate, previousConsistencyRate, 'No activities configured'));
}
function renderReportsDashboardActivityBars(summaries) {
    const container = document.querySelector('#reports-dashboard-activity-bars');
    if (!container) {
        return;
    }
    const totalDurationMs = summaries.reduce((total, summary) => total + summary.durationMs, 0);
    if (summaries.length === 0 || totalDurationMs === 0) {
        container.innerHTML =
            '<div class="reports-dashboard-empty-state">No activity data in this range.</div>';
        return;
    }
    container.innerHTML = summaries
        .map((summary) => {
        const percentage = Math.max(1, Math.round((summary.durationMs / totalDurationMs) * 100));
        return `
        <div class="reports-dashboard-activity-row">
          <div class="reports-dashboard-activity-row__header">
            <span class="reports-dashboard-activity-row__label">${escapeHtml(summary.name)}</span>
            <span class="reports-dashboard-activity-row__value">${escapeHtml(formatHoursMinutes(summary.durationMs))} (${percentage}%)</span>
          </div>
          <div class="progress reports-dashboard-activity-progress">
            <div class="progress-bar reports-dashboard-activity-progress__fill" style="width: ${percentage}%; --reports-dashboard-activity-color: ${summary.color}"></div>
          </div>
        </div>
      `;
    })
        .join('');
}
function renderReportsDashboardHabits(rows) {
    const tableBody = document.querySelector('#reports-dashboard-habits-body');
    if (!tableBody) {
        return;
    }
    if (rows.length === 0) {
        tableBody.innerHTML = `
      <tr>
        <td colspan="3">
          <div class="reports-dashboard-empty-state">No activities configured yet.</div>
        </td>
      </tr>
    `;
        return;
    }
    tableBody.innerHTML = rows
        .map((row) => `
        <tr>
          <td>
            <span class="reports-dashboard-habit__name">
              <span class="reports-dashboard-habit__dot" style="--reports-dashboard-habit-color: ${row.color}"></span>
              <span>${escapeHtml(row.name)}</span>
            </span>
          </td>
          <td class="reports-dashboard-table__numeric">${escapeHtml(`${row.activeDays} / ${row.totalDays}`)}</td>
          <td class="reports-dashboard-table__numeric">${escapeHtml(formatReportsDashboardPercentage(row.rate))}</td>
        </tr>
      `)
        .join('');
}
function fetchReportsDashboardEntriesByDate(dateKeys) {
    return __awaiter(this, void 0, void 0, function* () {
        const entriesByDate = new Map();
        const batchSize = 7;
        for (let index = 0; index < dateKeys.length; index += batchSize) {
            const batch = dateKeys.slice(index, index + batchSize);
            const batchEntries = yield Promise.all(batch.map((dateKey) => __awaiter(this, void 0, void 0, function* () {
                const response = yield fetchJson(`/time-entries/today.php?date=${encodeURIComponent(dateKey)}`);
                return [
                    dateKey,
                    response.entries
                        .filter((entry) => !isSleepEntry(entry))
                        .sort(compareEntryAscending),
                ];
            })));
            for (const [dateKey, entries] of batchEntries) {
                entriesByDate.set(dateKey, entries);
            }
        }
        return entriesByDate;
    });
}
function syncReportsDashboardFilterInputs() {
    const fromInput = document.querySelector('#reports-dashboard-from-input');
    const toInput = document.querySelector('#reports-dashboard-to-input');
    const today = buildDateKey(new Date());
    if (fromInput) {
        fromInput.value = reportsDashboardRangeStart;
        fromInput.max = today;
    }
    if (toInput) {
        toInput.value = reportsDashboardRangeEnd;
        toInput.max = today;
    }
}
function setReportsDashboardLoading(isLoading) {
    const root = document.querySelector('#reports-dashboard');
    const filterButton = document.querySelector('#reports-dashboard-filter-button');
    root === null || root === void 0 ? void 0 : root.classList.toggle('is-loading', isLoading);
    setButtonLoading(filterButton, isLoading, 'Loading...');
}
function validateReportsDashboardRange(start, end) {
    const normalizedRange = normalizeReportsDashboardRange(start, end);
    const today = buildDateKey(new Date());
    if (!normalizedRange) {
        return 'Choose a valid date range.';
    }
    if (normalizedRange.end > today) {
        return 'The report range cannot extend into the future.';
    }
    if (countReportsDashboardDaysInclusive(normalizedRange.start, normalizedRange.end) > REPORTS_DASHBOARD_MAX_RANGE_DAYS) {
        return `Select ${REPORTS_DASHBOARD_MAX_RANGE_DAYS} days or fewer.`;
    }
    return null;
}
function refreshReportsDashboardPage() {
    return __awaiter(this, void 0, void 0, function* () {
        updateReportsDashboardUrlRange(reportsDashboardRangeStart, reportsDashboardRangeEnd);
        setReportsDashboardMessage(null);
        syncReportsDashboardFilterInputs();
        setReportsDashboardLoading(true);
        try {
            const currentRange = {
                start: reportsDashboardRangeStart,
                end: reportsDashboardRangeEnd,
            };
            const previousRange = getPreviousReportsDashboardRange(currentRange.start, currentRange.end);
            const currentDates = buildReportsDashboardDateRange(currentRange.start, currentRange.end);
            const previousDates = buildReportsDashboardDateRange(previousRange.start, previousRange.end);
            const uniqueDates = Array.from(new Set([...currentDates, ...previousDates]));
            const [activitiesResponse, entriesByDate] = yield Promise.all([
                fetchJson('/activities/list.php'),
                fetchReportsDashboardEntriesByDate(uniqueDates),
            ]);
            const currentEntries = currentDates.flatMap((dateKey) => { var _a; return (_a = entriesByDate.get(dateKey)) !== null && _a !== void 0 ? _a : []; });
            const previousEntries = previousDates.flatMap((dateKey) => { var _a; return (_a = entriesByDate.get(dateKey)) !== null && _a !== void 0 ? _a : []; });
            const currentSummaries = summarizeReportsDashboardActivities(currentEntries);
            const previousSummaries = summarizeReportsDashboardActivities(previousEntries);
            const currentConsistencyRows = buildReportsDashboardActivityConsistencyRows(activitiesResponse.activities, currentEntries, currentRange.start, currentRange.end);
            const previousConsistencyRows = buildReportsDashboardActivityConsistencyRows(activitiesResponse.activities, previousEntries, previousRange.start, previousRange.end);
            const currentConsistencyRate = calculateReportsDashboardConsistencyRate(currentConsistencyRows);
            const previousConsistencyRate = calculateReportsDashboardConsistencyRate(previousConsistencyRows);
            renderReportsDashboardSummaryCards(currentSummaries, previousSummaries, currentConsistencyRate, previousConsistencyRate);
            renderReportsDashboardActivityBars(currentSummaries);
            renderReportsDashboardHabits(currentConsistencyRows);
        }
        finally {
            setReportsDashboardLoading(false);
        }
    });
}
function bindReportsDashboardPage() {
    const root = document.querySelector('#reports-dashboard');
    if (!root) {
        return;
    }
    const form = document.querySelector('#reports-dashboard-filter-form');
    const fromInput = document.querySelector('#reports-dashboard-from-input');
    const toInput = document.querySelector('#reports-dashboard-to-input');
    syncReportsDashboardFilterInputs();
    form === null || form === void 0 ? void 0 : form.addEventListener('submit', (event) => __awaiter(this, void 0, void 0, function* () {
        var _a, _b;
        event.preventDefault();
        const nextStart = (_a = fromInput === null || fromInput === void 0 ? void 0 : fromInput.value.trim()) !== null && _a !== void 0 ? _a : '';
        const nextEnd = (_b = toInput === null || toInput === void 0 ? void 0 : toInput.value.trim()) !== null && _b !== void 0 ? _b : '';
        const validationMessage = validateReportsDashboardRange(nextStart, nextEnd);
        if (validationMessage) {
            setReportsDashboardMessage(validationMessage);
            return;
        }
        reportsDashboardRangeStart = nextStart;
        reportsDashboardRangeEnd = nextEnd;
        try {
            yield refreshReportsDashboardPage();
        }
        catch (error) {
            setReportsDashboardMessage(error instanceof Error ? error.message : 'Unable to load the report.');
        }
    }));
    void refreshReportsDashboardPage().catch((error) => {
        setReportsDashboardMessage(error instanceof Error ? error.message : 'Unable to load the report.');
    });
}
bindReportsDashboardPage();