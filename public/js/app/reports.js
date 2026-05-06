"use strict";
function setReportsMessage(message) {
    const element = document.querySelector('#reports-message');
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
function summarizeActivities(entries, date, dailyLog) {
    var _a, _b;
    const durationByActivity = new Map();
    for (const entry of entries) {
        const start = parseDateTime(entry.start);
        const end = getReportEntryEnd(entry, date, dailyLog);
        if (!start || !end) {
            continue;
        }
        const activityName = ((_a = entry.activity_name) !== null && _a !== void 0 ? _a : 'Untitled').trim() || 'Untitled';
        durationByActivity.set(activityName, ((_b = durationByActivity.get(activityName)) !== null && _b !== void 0 ? _b : 0) +
            (end.getTime() - start.getTime()));
    }
    return Array.from(durationByActivity.entries())
        .sort((left, right) => right[1] - left[1])
        .map(([name, durationMs], index) => ({
        name,
        durationMs,
        color: getReportActivityColor(index),
    }));
}
function splitSegmentAtNow(segment, now) {
    if (now.getTime() <= segment.start.getTime() ||
        now.getTime() >= segment.end.getTime()) {
        return [segment];
    }
    return [
        Object.assign(Object.assign({}, segment), { end: new Date(now), durationMs: now.getTime() - segment.start.getTime(), isFuture: false }),
        Object.assign(Object.assign({}, segment), { start: new Date(now), durationMs: segment.end.getTime() - now.getTime(), isFuture: true }),
    ];
}
function buildTimelineSegments(date, dailyLog, entries) {
    var _a, _b;
    const wakeDate = buildDateTimeFromParts(date, dailyLog.wake_time);
    const sleepDate = buildDateTimeFromParts(date, dailyLog.sleep_time);
    if (!wakeDate || !sleepDate || sleepDate.getTime() <= wakeDate.getTime()) {
        return [];
    }
    const colorByActivity = new Map(summarizeActivities(entries, date, dailyLog).map((summary) => [
        summary.name,
        summary.color,
    ]));
    const segments = [];
    const orderedEntries = [...entries].sort(compareEntryAscending);
    const now = new Date();
    const isToday = date === buildDateKey(now);
    let cursor = wakeDate;
    for (const entry of orderedEntries) {
        const start = parseDateTime(entry.start);
        const end = getReportEntryEnd(entry, date, dailyLog);
        if (!start || !end) {
            continue;
        }
        if (start.getTime() > cursor.getTime()) {
            segments.push({
                kind: 'gap',
                start: new Date(cursor),
                end: new Date(start),
                durationMs: start.getTime() - cursor.getTime(),
                isFuture: isToday && cursor.getTime() >= now.getTime(),
                entry: null,
                color: 'var(--color-surface)',
            });
        }
        const activityName = ((_a = entry.activity_name) !== null && _a !== void 0 ? _a : 'Untitled').trim() || 'Untitled';
        segments.push({
            kind: 'entry',
            start,
            end,
            durationMs: end.getTime() - start.getTime(),
            isFuture: isToday && start.getTime() >= now.getTime(),
            entry,
            color: (_b = colorByActivity.get(activityName)) !== null && _b !== void 0 ? _b : getReportActivityColor(0),
        });
        cursor = end;
    }
    if (cursor.getTime() < sleepDate.getTime()) {
        segments.push({
            kind: 'gap',
            start: new Date(cursor),
            end: sleepDate,
            durationMs: sleepDate.getTime() - cursor.getTime(),
            isFuture: isToday && cursor.getTime() >= now.getTime(),
            entry: null,
            color: 'var(--color-surface)',
        });
    }
    if (!isToday) {
        return segments;
    }
    const resolvedSegments = [];
    for (const segment of segments) {
        resolvedSegments.push(...splitSegmentAtNow(segment, now));
    }
    return resolvedSegments;
}
function renderReportsDonut(summaries, segments) {
    const donut = document.querySelector('#reports-donut-chart');
    const totalLabel = document.querySelector('#reports-total-productive');
    const legend = document.querySelector('#reports-legend');
    if (!donut || !totalLabel || !legend) {
        return;
    }
    const totalTrackedMs = summaries.reduce((total, summary) => total + summary.durationMs, 0);
    const untrackedMs = segments
        .filter((segment) => segment.kind === 'gap')
        .reduce((total, segment) => total + segment.durationMs, 0);
    totalLabel.textContent = formatHoursMinutes(totalTrackedMs);
    if (summaries.length === 0 || totalTrackedMs === 0) {
        donut.innerHTML = `
      <circle cx="110" cy="110" r="78" fill="none" stroke="rgba(255, 234, 206, 0.08)" stroke-width="34"></circle>
      <circle cx="110" cy="110" r="55" fill="var(--color-surface)"></circle>
    `;
        legend.innerHTML = `
      <div class="reports-legend__row">
        <div class="reports-legend__left">
          <span class="reports-legend__dot" style="--legend-color: var(--color-surface-up)"></span>
          <span class="text-sm">No tracked work</span>
        </div>
        <span class="text-sm">0h 00m</span>
      </div>
    `;
        return;
    }
    let angle = -Math.PI / 2;
    const paths = summaries
        .map((summary) => {
        const sweep = (summary.durationMs / totalTrackedMs) * Math.PI * 2;
        const nextAngle = angle + sweep;
        const path = buildArcPath(110, 110, 78, angle, nextAngle);
        angle = nextAngle;
        return `<path d="${path}" fill="none" style="stroke: ${summary.color}" stroke-width="34" stroke-linecap="round"></path>`;
    })
        .join('');
    donut.innerHTML = `
    <circle cx="110" cy="110" r="78" fill="none" stroke="rgba(255, 234, 206, 0.08)" stroke-width="34"></circle>
    ${paths}
    <circle cx="110" cy="110" r="55" fill="var(--color-surface)"></circle>
    <circle cx="110" cy="110" r="94" fill="none" stroke="rgba(255, 234, 206, 0.05)" stroke-width="2"></circle>
  `;
    legend.innerHTML = [
        ...summaries.map((summary) => `
        <div class="reports-legend__row">
          <div class="reports-legend__left">
            <span class="reports-legend__dot" style="--legend-color: ${summary.color}"></span>
            <span class="text-sm">${escapeHtml(summary.name)}</span>
          </div>
          <span class="text-sm">${escapeHtml(formatHoursMinutes(summary.durationMs))}</span>
        </div>
      `),
        `
      <div class="reports-legend__row">
        <div class="reports-legend__left">
          <span class="reports-legend__dot" style="--legend-color: var(--color-surface-up)"></span>
          <span class="text-sm">Untracked</span>
        </div>
        <span class="text-sm">${escapeHtml(formatHoursMinutes(untrackedMs))}</span>
      </div>
    `,
    ].join('');
}
function renderReportsTimelineBar(date, dailyLog, segments) {
    const bar = document.querySelector('#reports-timeline-bar');
    const nowMarker = document.querySelector('#reports-timeline-now');
    const wakeLabel = document.querySelector('#reports-wake-label');
    const midpointLabel = document.querySelector('#reports-midpoint-label');
    const sleepLabel = document.querySelector('#reports-sleep-label');
    const wakeDate = buildDateTimeFromParts(date, dailyLog.wake_time);
    const sleepDate = buildDateTimeFromParts(date, dailyLog.sleep_time);
    if (!bar || !nowMarker || !wakeLabel || !midpointLabel || !sleepLabel) {
        return;
    }
    if (!wakeDate || !sleepDate || sleepDate.getTime() <= wakeDate.getTime()) {
        bar.innerHTML = '';
        nowMarker.hidden = true;
        return;
    }
    const totalWindowMs = sleepDate.getTime() - wakeDate.getTime();
    const midpointDate = new Date(wakeDate.getTime() + totalWindowMs / 2);
    wakeLabel.textContent = formatClockTime(dailyLog.wake_time);
    midpointLabel.textContent = formatDateTimeDisplay(midpointDate);
    sleepLabel.textContent = formatClockTime(dailyLog.sleep_time);
    bar.innerHTML = segments
        .map((segment) => {
        var _a, _b;
        const width = (segment.durationMs / totalWindowMs) * 100;
        const label = segment.kind === 'gap'
            ? ''
            : ((_b = (_a = segment.entry) === null || _a === void 0 ? void 0 : _a.activity_name) !== null && _b !== void 0 ? _b : 'Untitled');
        return `
        <div
          class="reports-day-track__segment ${segment.kind === 'gap' ? 'reports-day-track__segment--gap' : ''} ${segment.isFuture ? 'reports-day-track__segment--future' : ''}"
          style="width: ${width}%; ${segment.kind === 'gap' ? '' : `background: ${segment.color}`}"
          title="${escapeHtml(`${segment.kind === 'gap' ? 'Untracked' : label} · ${formatHoursMinutes(segment.durationMs)}`)}"
        >
          <span>${escapeHtml(width >= 11 ? label : '')}</span>
        </div>
      `;
    })
        .join('');
    const now = new Date();
    const isToday = date === buildDateKey(now);
    if (!isToday ||
        now.getTime() < wakeDate.getTime() ||
        now.getTime() > sleepDate.getTime()) {
        nowMarker.hidden = true;
        return;
    }
    nowMarker.hidden = false;
    nowMarker.style.left = `${((now.getTime() - wakeDate.getTime()) / totalWindowMs) * 100}%`;
}
function buildReportRowStatus(segment) {
    var _a, _b, _c, _d, _e, _f;
    if (segment.kind === 'gap') {
        const startValue = `${String(segment.start.getHours()).padStart(2, '0')}:${String(segment.start.getMinutes()).padStart(2, '0')}`;
        const endValue = `${String(segment.end.getHours()).padStart(2, '0')}:${String(segment.end.getMinutes()).padStart(2, '0')}`;
        return `<button type="button" class="reports-row__add" data-gap-start="${startValue}" data-gap-end="${endValue}">+ Add entry</button>`;
    }
    return `<button type="button" class="reports-row__edit" data-entry-id="${escapeHtml(String((_b = (_a = segment.entry) === null || _a === void 0 ? void 0 : _a.id) !== null && _b !== void 0 ? _b : ''))}">Edit</button><span class="reports-row__status ${getEntryBadgeClass((_d = (_c = segment.entry) === null || _c === void 0 ? void 0 : _c.state) !== null && _d !== void 0 ? _d : 'completed')}">${escapeHtml(formatStateLabel((_f = (_e = segment.entry) === null || _e === void 0 ? void 0 : _e.state) !== null && _f !== void 0 ? _f : 'completed'))}</span>`;
}
function updateReportsSortButton() {
    const sortButton = document.querySelector('#reports-sort-button');
    if (!sortButton) {
        return;
    }
    const isNewestFirst = reportsTimelineSortOrder === 'desc';
    sortButton.innerHTML = `<span aria-hidden="true">${isNewestFirst ? '↓' : '↑'}</span>`;
    sortButton.setAttribute('aria-label', isNewestFirst ? 'Show newest entries first' : 'Show oldest entries first');
    sortButton.title = isNewestFirst
        ? 'Show newest entries first'
        : 'Show oldest entries first';
}
function renderReportsTimelineList(segments) {
    const container = document.querySelector('#reports-timeline-list');
    const countBadge = document.querySelector('#reports-entry-count');
    if (!container || !countBadge) {
        return;
    }
    const entryCount = segments.filter((segment) => segment.kind === 'entry').length;
    const sortedSegments = reportsTimelineSortOrder === 'desc' ? [...segments].reverse() : segments;
    const visibleSegments = sortedSegments.filter((segment) => !(segment.kind === 'gap' && segment.isFuture));
    updateReportsSortButton();
    countBadge.textContent = `${entryCount} ${entryCount === 1 ? 'entry' : 'entries'}`;
    if (visibleSegments.length === 0) {
        container.innerHTML =
            '<div class="reports-empty-state">No awake-window data available for this day.</div>';
        return;
    }
    container.innerHTML = visibleSegments
        .map((segment) => {
        var _a, _b, _c, _d, _e, _f;
        const title = segment.kind === 'gap'
            ? 'Untracked'
            : ((_b = (_a = segment.entry) === null || _a === void 0 ? void 0 : _a.activity_name) !== null && _b !== void 0 ? _b : 'Untitled');
        const subtitle = segment.kind === 'gap'
            ? segment.isFuture
                ? 'Upcoming time'
                : 'No entry logged'
            : ((_d = (_c = segment.entry) === null || _c === void 0 ? void 0 : _c.activity_subtype_name) === null || _d === void 0 ? void 0 : _d.trim()) ||
                ((_f = (_e = segment.entry) === null || _e === void 0 ? void 0 : _e.notes) === null || _f === void 0 ? void 0 : _f.trim()) ||
                'Logged entry';
        return `
        <div class="reports-row ${segment.kind === 'gap' ? 'reports-row--gap' : ''} ${segment.isFuture ? 'reports-row--future' : ''}">
          <div class="reports-row__time">${escapeHtml(formatDateTimeDisplay(segment.start))} - ${escapeHtml(formatDateTimeDisplay(segment.end))}</div>
          <div class="reports-row__details">
            <span class="reports-row__marker ${segment.kind === 'gap' ? 'reports-row__marker--gap' : ''}" style="${segment.kind === 'gap' ? '' : `--row-color: ${segment.color}`} "></span>
            <div class="reports-row__content">
              <div class="reports-row__headline">
                ${segment.kind === 'gap'
            ? `<span class="reports-row__title reports-row__title--gap">${escapeHtml(title)}</span>`
            : `<span class="reports-row__title">${escapeHtml(title)}</span>`}
                <span class="reports-row__duration ${segment.kind === 'gap' ? 'reports-row__duration--gap' : ''}">${escapeHtml(formatHoursMinutes(segment.durationMs))}</span>
              </div>
              <span class="reports-row__subtitle">${escapeHtml(subtitle)}</span>
            </div>
          </div>
          <div class="reports-row__actions">
            <div class="reports-row__action-group">${buildReportRowStatus(segment)}</div>
          </div>
        </div>
      `;
    })
        .join('');
}
function openReportsEntryModal(prefill) {
    var _a, _b, _c;
    const modal = document.querySelector('#reports-entry-modal');
    const dateLabel = document.querySelector('#reports-entry-modal-date');
    const titleLabel = document.querySelector('#reports-entry-modal-title');
    const activityField = document.querySelector('#reports-entry-activity');
    const startField = document.querySelector('#reports-entry-start');
    const endField = document.querySelector('#reports-entry-end');
    const notesField = document.querySelector('#reports-entry-notes');
    const deleteButton = document.querySelector('#reports-entry-delete');
    if (!modal ||
        !dateLabel ||
        !titleLabel ||
        !activityField ||
        !startField ||
        !endField ||
        !notesField ||
        !deleteButton) {
        return;
    }
    clearInlineError('#reports-entry-error');
    dateLabel.textContent = formatCalendarLabel(reportSelectedDate);
    titleLabel.textContent =
        reportEditingEntryId === null ? 'Add Entry' : 'Edit Entry';
    setButtonText('#reports-entry-submit', reportEditingEntryId === null ? 'Save Entry' : 'Save Changes');
    deleteButton.hidden = reportEditingEntryId === null;
    endField.required = !reportEditingEntryIsRunning;
    startField.value = (_a = prefill === null || prefill === void 0 ? void 0 : prefill.start) !== null && _a !== void 0 ? _a : '';
    endField.value = (_b = prefill === null || prefill === void 0 ? void 0 : prefill.end) !== null && _b !== void 0 ? _b : '';
    notesField.value = (_c = prefill === null || prefill === void 0 ? void 0 : prefill.notes) !== null && _c !== void 0 ? _c : '';
    modal.hidden = false;
    activityField.focus();
}
function closeReportsEntryModal() {
    const modal = document.querySelector('#reports-entry-modal');
    if (modal) {
        modal.hidden = true;
    }
}
function ensureReportActivityOptions() {
    return __awaiter(this, void 0, void 0, function* () {
        const activityField = document.querySelector('#reports-entry-activity');
        if (!activityField) {
            return;
        }
        if (!reportActivitiesLoaded) {
            const response = yield fetchJson('/activities/list.php');
            availableActivities = response.activities;
            reportActivitiesLoaded = true;
        }
        populateSelectOptions(activityField, availableActivities.length > 0
            ? 'Choose activity (required)'
            : 'No activities yet', availableActivities, (activity) => String(activity.id), (activity) => activity.name);
        setSelectDisabledState(activityField, availableActivities.length === 0, availableActivities.length === 0 ? 'Create an activity first' : '');
    });
}
function refreshReportsPage() {
    return __awaiter(this, void 0, void 0, function* () {
        updateUrlDate(reportSelectedDate);
        setReportsMessage(null);
        const [dailyLogResponse, entriesResponse] = yield Promise.all([
            fetchJson(`/daily-log/today.php?date=${encodeURIComponent(reportSelectedDate)}`),
            fetchJson(`/time-entries/today.php?date=${encodeURIComponent(reportSelectedDate)}`),
        ]);
        const reportEntries = entriesResponse.entries
            .filter((entry) => !isSleepEntry(entry))
            .sort(compareEntryAscending);
        currentReportEntries = reportEntries;
        const summaries = summarizeActivities(reportEntries, reportSelectedDate, dailyLogResponse.daily_log);
        const segments = buildTimelineSegments(reportSelectedDate, dailyLogResponse.daily_log, reportEntries);
        currentReportSegments = segments;
        const dateLabel = document.querySelector('#reports-date-label');
        const dateInput = document.querySelector('#reports-date-input');
        const nextButton = document.querySelector('#reports-next-day');
        if (dateLabel) {
            dateLabel.textContent = formatCalendarLabel(reportSelectedDate);
        }
        if (dateInput) {
            dateInput.value = reportSelectedDate;
        }
        if (nextButton) {
            nextButton.disabled = reportSelectedDate === buildDateKey(new Date());
        }
        renderReportsDonut(summaries, segments);
        renderReportsTimelineBar(reportSelectedDate, dailyLogResponse.daily_log, segments);
        renderReportsTimelineList(segments);
    });
}
function bindReportsPage() {
    var _a;
    const root = document.querySelector('#reports-overview');
    if (!root) {
        return;
    }
    const previousButton = document.querySelector('#reports-prev-day');
    const nextButton = document.querySelector('#reports-next-day');
    const todayButton = document.querySelector('#reports-today-button');
    const dateInput = document.querySelector('#reports-date-input');
    const addButton = document.querySelector('#reports-add-entry-button');
    const sortButton = document.querySelector('#reports-sort-button');
    const deleteButton = document.querySelector('#reports-entry-delete');
    const modal = document.querySelector('#reports-entry-modal');
    const closeButton = document.querySelector('#reports-entry-modal-close');
    const activityField = document.querySelector('#reports-entry-activity');
    const subtypeField = document.querySelector('#reports-entry-subtype');
    const startField = document.querySelector('#reports-entry-start');
    const endField = document.querySelector('#reports-entry-end');
    const notesField = document.querySelector('#reports-entry-notes');
    const form = document.querySelector('#reports-entry-form');
    const submitButton = document.querySelector('#reports-entry-submit');
    previousButton === null || previousButton === void 0 ? void 0 : previousButton.addEventListener('click', () => {
        reportSelectedDate = addDays(reportSelectedDate, -1);
        void refreshReportsPage().catch((error) => {
            setReportsMessage(error instanceof Error
                ? error.message
                : 'Unable to load the selected day.');
        });
    });
    nextButton === null || nextButton === void 0 ? void 0 : nextButton.addEventListener('click', () => {
        if (reportSelectedDate === buildDateKey(new Date())) {
            return;
        }
        reportSelectedDate = addDays(reportSelectedDate, 1);
        void refreshReportsPage().catch((error) => {
            setReportsMessage(error instanceof Error
                ? error.message
                : 'Unable to load the selected day.');
        });
    });
    todayButton === null || todayButton === void 0 ? void 0 : todayButton.addEventListener('click', () => {
        reportSelectedDate = buildDateKey(new Date());
        void refreshReportsPage().catch((error) => {
            setReportsMessage(error instanceof Error ? error.message : 'Unable to load today.');
        });
    });
    dateInput === null || dateInput === void 0 ? void 0 : dateInput.addEventListener('change', () => {
        const nextDate = dateInput.value.trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(nextDate)) {
            return;
        }
        reportSelectedDate = nextDate;
        void refreshReportsPage().catch((error) => {
            setReportsMessage(error instanceof Error
                ? error.message
                : 'Unable to load the selected date.');
        });
    });
    addButton === null || addButton === void 0 ? void 0 : addButton.addEventListener('click', () => __awaiter(this, void 0, void 0, function* () {
        try {
            reportEditingEntryId = null;
            reportEditingEntryIsRunning = false;
            yield ensureReportActivityOptions();
            setSelectValue(activityField, null);
            yield loadSubtypeOptionsForSelect(subtypeField, null, null, 'Choose subtype (optional)', 'No subtypes yet', 'Select an activity first');
            openReportsEntryModal();
        }
        catch (error) {
            setReportsMessage(error instanceof Error ? error.message : 'Unable to load activities.');
        }
    }));
    sortButton === null || sortButton === void 0 ? void 0 : sortButton.addEventListener('click', () => {
        reportsTimelineSortOrder =
            reportsTimelineSortOrder === 'desc' ? 'asc' : 'desc';
        renderReportsTimelineList(currentReportSegments);
    });
    closeButton === null || closeButton === void 0 ? void 0 : closeButton.addEventListener('click', closeReportsEntryModal);
    modal === null || modal === void 0 ? void 0 : modal.addEventListener('click', (event) => {
        const target = event.target;
        if (target.dataset.closeModal === 'true') {
            closeReportsEntryModal();
        }
    });
    activityField === null || activityField === void 0 ? void 0 : activityField.addEventListener('change', () => {
        void loadSubtypeOptionsForSelect(subtypeField, parseSelectedId(activityField), null, 'Choose subtype (optional)', 'No subtypes yet', 'Select an activity first');
    });
    (_a = document
        .querySelector('#reports-timeline-list')) === null || _a === void 0 ? void 0 : _a.addEventListener('click', (event) => __awaiter(this, void 0, void 0, function* () {
        var _a, _b, _c, _d;
        const target = event.target;
        const editButton = target.closest('.reports-row__edit');
        const addGapButton = target.closest('.reports-row__add');
        if (editButton) {
            const entryId = Number((_a = editButton.dataset.entryId) !== null && _a !== void 0 ? _a : '');
            const entry = currentReportEntries.find((candidate) => Number(candidate.id) === entryId);
            if (!entry) {
                setReportsMessage('Unable to find that entry right now.');
                return;
            }
            try {
                reportEditingEntryId = entryId;
                reportEditingEntryIsRunning =
                    entry.state === 'running' || entry.end === null;
                yield ensureReportActivityOptions();
                setSelectValue(activityField, toNullableId(entry.activity_id));
                yield loadSubtypeOptionsForSelect(subtypeField, toNullableId(entry.activity_id), toNullableId(entry.activity_subtype_id), 'Choose subtype (optional)', 'No subtypes yet', 'Select an activity first');
                openReportsEntryModal({
                    start: toTimeInputValue(entry.start),
                    end: toTimeInputValue(entry.end),
                    notes: (_b = entry.notes) !== null && _b !== void 0 ? _b : '',
                });
            }
            catch (error) {
                setReportsMessage(error instanceof Error
                    ? error.message
                    : 'Unable to load the entry.');
            }
            return;
        }
        if (!addGapButton) {
            return;
        }
        try {
            reportEditingEntryId = null;
            reportEditingEntryIsRunning = false;
            yield ensureReportActivityOptions();
            setSelectValue(activityField, null);
            yield loadSubtypeOptionsForSelect(subtypeField, null, null, 'Choose subtype (optional)', 'No subtypes yet', 'Select an activity first');
            openReportsEntryModal({
                start: (_c = addGapButton.dataset.gapStart) !== null && _c !== void 0 ? _c : '',
                end: (_d = addGapButton.dataset.gapEnd) !== null && _d !== void 0 ? _d : '',
            });
        }
        catch (error) {
            setReportsMessage(error instanceof Error ? error.message : 'Unable to load activities.');
        }
    }));
    deleteButton === null || deleteButton === void 0 ? void 0 : deleteButton.addEventListener('click', () => __awaiter(this, void 0, void 0, function* () {
        if (reportEditingEntryId === null) {
            return;
        }
        if (!window.confirm('Delete this entry?')) {
            return;
        }
        clearInlineError('#reports-entry-error');
        setButtonLoading(deleteButton, true, 'Deleting...');
        try {
            yield fetchJson('/time-entries/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: reportEditingEntryId,
                }),
            });
            closeReportsEntryModal();
            reportEditingEntryId = null;
            reportEditingEntryIsRunning = false;
            yield refreshReportsPage();
        }
        catch (error) {
            showInlineError('#reports-entry-error', error instanceof Error
                ? error.message
                : 'Unable to delete the entry right now.');
        }
        finally {
            setButtonLoading(deleteButton, false, 'Deleting...');
        }
    }));
    form === null || form === void 0 ? void 0 : form.addEventListener('submit', (event) => __awaiter(this, void 0, void 0, function* () {
        var _a, _b, _c;
        event.preventDefault();
        const activityId = parseSelectedId(activityField);
        const subtypeId = parseSelectedId(subtypeField);
        const start = normalizeTimeForApi((_a = startField === null || startField === void 0 ? void 0 : startField.value) !== null && _a !== void 0 ? _a : '');
        const end = normalizeTimeForApi((_b = endField === null || endField === void 0 ? void 0 : endField.value) !== null && _b !== void 0 ? _b : '');
        const requiresEndTime = !reportEditingEntryIsRunning;
        if (!activityId) {
            showInlineError('#reports-entry-error', 'Activity is required.');
            return;
        }
        if (!start || (requiresEndTime && !end)) {
            showInlineError('#reports-entry-error', requiresEndTime
                ? 'Start and end times are required.'
                : 'Start time is required.');
            return;
        }
        clearInlineError('#reports-entry-error');
        setButtonLoading(submitButton, true, 'Saving...');
        try {
            yield fetchJson(reportEditingEntryId === null
                ? '/time-entries/add.php'
                : '/time-entries/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(Object.assign(Object.assign({}, (reportEditingEntryId === null
                    ? {}
                    : { id: reportEditingEntryId })), { date: reportSelectedDate, start, end: end || null, notes: (_c = notesField === null || notesField === void 0 ? void 0 : notesField.value) !== null && _c !== void 0 ? _c : '', activity_id: activityId, activity_subtype_id: subtypeId })),
            });
            closeReportsEntryModal();
            reportEditingEntryId = null;
            reportEditingEntryIsRunning = false;
            yield refreshReportsPage();
        }
        catch (error) {
            showInlineError('#reports-entry-error', error instanceof Error
                ? error.message
                : 'Unable to save the entry right now.');
        }
        finally {
            setButtonLoading(submitButton, false, 'Saving...');
        }
    }));
    void refreshReportsPage().catch((error) => {
        setReportsMessage(error instanceof Error ? error.message : 'Unable to load the report.');
    });
}
bindReportsPage();