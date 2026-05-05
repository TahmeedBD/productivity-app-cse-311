"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
let runningEntryTimerId = null;
function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
function parseDateTime(value) {
    if (!value) {
        return null;
    }
    const [datePart, timePart] = value.split(' ');
    if (!datePart || !timePart) {
        return null;
    }
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes, seconds] = timePart.split(':').map(Number);
    if ([year, month, day, hours, minutes, seconds].some(Number.isNaN)) {
        return null;
    }
    return new Date(year, month - 1, day, hours, minutes, seconds);
}
function parseTimeOnly(value) {
    if (!value) {
        return null;
    }
    const [hours, minutes, seconds] = value.split(':').map(Number);
    if ([hours, minutes, seconds].some(Number.isNaN)) {
        return null;
    }
    return new Date(2000, 0, 1, hours, minutes, seconds);
}
function formatClockTime(value, lowercaseMeridiem = false) {
    const date = (value === null || value === void 0 ? void 0 : value.includes(' '))
        ? parseDateTime(value)
        : parseTimeOnly(value !== null && value !== void 0 ? value : null);
    if (!date) {
        return '--:--';
    }
    const rawHours = date.getHours();
    const displayHours = rawHours % 12 === 0 ? 12 : rawHours % 12;
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const meridiem = rawHours >= 12
        ? lowercaseMeridiem
            ? 'pm'
            : 'PM'
        : lowercaseMeridiem
            ? 'am'
            : 'AM';
    return `${displayHours}:${minutes} ${meridiem}`;
}
function formatStateLabel(state) {
    if (state === 'completed') {
        return 'Complete';
    }
    if (state === 'running') {
        return 'Running';
    }
    return state.charAt(0).toUpperCase() + state.slice(1);
}
function formatDayLabel(value) {
    const [year, month, day] = value.split('-').map(Number);
    if ([year, month, day].some(Number.isNaN)) {
        return 'Today';
    }
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(year, month - 1, day));
}
function formatDuration(milliseconds) {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
}
function formatEntryDuration(entry) {
    const startDate = parseDateTime(entry.start);
    const endDate = entry.end ? parseDateTime(entry.end) : new Date();
    if (!startDate || !endDate) {
        return '--:--:--';
    }
    return formatDuration(endDate.getTime() - startDate.getTime());
}
function getRunningEntry(entries) {
    for (let index = entries.length - 1; index >= 0; index -= 1) {
        const entry = entries[index];
        if (entry.end === null || entry.state === 'running') {
            return entry;
        }
    }
    return null;
}
function setElementText(selector, text) {
    const element = document.querySelector(selector);
    if (element) {
        element.textContent = text;
    }
}
function setFieldValue(selector, value) {
    const field = document.querySelector(selector);
    if (field) {
        field.value = value;
    }
}
function setButtonText(selector, text) {
    const button = document.querySelector(selector);
    if (!button) {
        return;
    }
    button.textContent = text;
    button.dataset.defaultLabel = text;
}
function setSelectDisabledState(select, isDisabled, title = '') {
    if (!select) {
        return;
    }
    select.disabled = isDisabled;
    select.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
    if (title) {
        select.title = title;
        return;
    }
    select.removeAttribute('title');
}
function populateSelectOptions(select, placeholder, items, getValue, getLabel) {
    if (!select) {
        return;
    }
    select.innerHTML = '';
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.append(placeholderOption);
    for (const item of items) {
        const option = document.createElement('option');
        option.value = getValue(item);
        option.textContent = getLabel(item);
        select.append(option);
    }
    updatePlaceholderSelectionState(select);
}
function parseSelectedId(select) {
    if (!select || !select.value) {
        return null;
    }
    const parsed = Number(select.value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}
function getSelectedOptionLabel(select) {
    var _a, _b;
    if (!select || !select.value) {
        return null;
    }
    return ((_b = (_a = select.selectedOptions[0]) === null || _a === void 0 ? void 0 : _a.textContent) === null || _b === void 0 ? void 0 : _b.trim()) || null;
}
function normalizeTimeForApi(value) {
    const trimmed = value.trim();
    if (!trimmed) {
        return '';
    }
    const parts = trimmed.split(':');
    if (parts.length === 2) {
        return `${parts[0]}:${parts[1]}:00`;
    }
    return trimmed;
}
function buildTimeKey(date) {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
}
function extractDateKeyFromTimestamp(value) {
    var _a, _b;
    const datePart = (_b = (_a = value === null || value === void 0 ? void 0 : value.split(' ')[0]) === null || _a === void 0 ? void 0 : _a.trim()) !== null && _b !== void 0 ? _b : '';
    return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : null;
}
function getClientTodayDateKey() {
    return buildDateKey(new Date());
}
function buildTimeLogRequestDate() {
    var _a, _b, _c;
    return ((_c = (_b = extractDateKeyFromTimestamp((_a = currentRunningEntry === null || currentRunningEntry === void 0 ? void 0 : currentRunningEntry.start) !== null && _a !== void 0 ? _a : null)) !== null && _b !== void 0 ? _b : timeLogActiveDate) !== null && _c !== void 0 ? _c : getClientTodayDateKey());
}
function updatePlaceholderSelectionState(select) {
    if (!select) {
        return;
    }
    select.classList.toggle('is-placeholder-selected', select.value === '');
}
function renderCurrentEntryState(label, isRunning) {
    const stateElement = document.querySelector('#current-entry-state');
    if (!stateElement) {
        return;
    }
    stateElement.className = isRunning
        ? 'time-log-running-state'
        : 'time-log-running-state is-idle';
    stateElement.innerHTML = `
		<span class="time-log-running-dot"></span>
		<span class="time-log-running-label">${escapeHtml(label)}</span>
	`;
}
function showTimeLogMessage(message, variant) {
    const messageElement = document.querySelector('#time-log-message');
    if (!messageElement) {
        return;
    }
    messageElement.hidden = false;
    messageElement.className = `alert alert-${variant} time-log-message`;
    messageElement.textContent = message;
}
function clearTimeLogMessage() {
    const messageElement = document.querySelector('#time-log-message');
    if (!messageElement) {
        return;
    }
    messageElement.hidden = true;
    messageElement.className = 'time-log-message';
    messageElement.textContent = '';
}
function showInlineError(selector, message) {
    const element = document.querySelector(selector);
    if (!element) {
        return;
    }
    element.hidden = false;
    element.textContent = message;
}
function clearInlineError(selector) {
    const element = document.querySelector(selector);
    if (!element) {
        return;
    }
    element.hidden = true;
    element.textContent = '';
}
function setButtonLoading(button, isLoading, pendingLabel) {
    var _a, _b;
    if (!button) {
        return;
    }
    const defaultLabel = (_b = (_a = button.dataset.defaultLabel) !== null && _a !== void 0 ? _a : button.textContent) !== null && _b !== void 0 ? _b : '';
    button.dataset.defaultLabel = defaultLabel;
    button.disabled = isLoading;
    button.textContent = isLoading ? pendingLabel : defaultLabel;
}
function fetchJson(url, init) {
    return __awaiter(this, void 0, void 0, function* () {
        const headers = new Headers(init === null || init === void 0 ? void 0 : init.headers);
        headers.set('Accept', 'application/json');
        const response = yield fetch(url, Object.assign(Object.assign({}, init), { credentials: 'same-origin', headers }));
        const text = yield response.text();
        const payload = text
            ? JSON.parse(text)
            : {};
        if (!response.ok) {
            throw new Error(payload.error || 'Request failed.');
        }
        return payload;
    });
}
function getEntryBadgeClass(state) {
    if (state === 'completed') {
        return 'badge badge-success';
    }
    if (state === 'running') {
        return 'badge badge-accent';
    }
    return 'badge badge-neutral';
}
let availableActivities = [];
let currentEntries = [];
let currentRunningEntry = null;
let currentEntrySnapshot = null;
let isNotesEditMode = false;
let timeLogActiveDate = getClientTodayDateKey();
function toNullableId(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const parsed = Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}
function buildEntrySnapshot(entry) {
    var _a;
    return {
        activityId: toNullableId(entry === null || entry === void 0 ? void 0 : entry.activity_id),
        activitySubtypeId: toNullableId(entry === null || entry === void 0 ? void 0 : entry.activity_subtype_id),
        notes: (_a = entry === null || entry === void 0 ? void 0 : entry.notes) !== null && _a !== void 0 ? _a : '',
    };
}
function getCurrentEntryFormSnapshot() {
    var _a;
    const activityField = document.querySelector('#time-entry-activity');
    const subtypeField = document.querySelector('#time-entry-subtype');
    const notesField = document.querySelector('#time-entry-notes');
    return {
        activityId: parseSelectedId(activityField),
        activitySubtypeId: parseSelectedId(subtypeField),
        notes: (_a = notesField === null || notesField === void 0 ? void 0 : notesField.value) !== null && _a !== void 0 ? _a : '',
    };
}
function areSnapshotsEqual(left, right) {
    if (!left || !right) {
        return left === right;
    }
    return (left.activityId === right.activityId &&
        left.activitySubtypeId === right.activitySubtypeId &&
        left.notes === right.notes);
}
function setSelectValue(select, value) {
    if (!select) {
        return;
    }
    select.value = value === null ? '' : String(value);
    updatePlaceholderSelectionState(select);
}
function loadSubtypeOptionsForSelect(select, activityId, selectedSubtypeId, idleLabel, emptyLabel, disabledTitle) {
    return __awaiter(this, void 0, void 0, function* () {
        if (!select) {
            return;
        }
        if (!activityId) {
            populateSelectOptions(select, idleLabel, [], () => '', () => '');
            setSelectDisabledState(select, true, disabledTitle);
            return;
        }
        const response = yield fetchJson(`/activity-subtypes/list.php?activity_id=${encodeURIComponent(String(activityId))}`);
        const hasSubtypes = response.subtypes.length > 0;
        populateSelectOptions(select, hasSubtypes ? 'Choose subtype (optional)' : emptyLabel, response.subtypes, (subtype) => String(subtype.id), (subtype) => subtype.name);
        if (selectedSubtypeId !== null) {
            select.value = String(selectedSubtypeId);
            updatePlaceholderSelectionState(select);
        }
        setSelectDisabledState(select, !hasSubtypes, hasSubtypes ? '' : disabledTitle);
    });
}
function syncCurrentEntryControls() {
    var _a, _b;
    const activityField = document.querySelector('#time-entry-activity');
    const subtypeField = document.querySelector('#time-entry-subtype');
    const notesField = document.querySelector('#time-entry-notes');
    const saveButton = document.querySelector('#time-entry-save-button');
    const stopButton = document.querySelector('#time-entry-stop-button');
    const startButton = document.querySelector('#time-entry-start-button');
    const resetButton = document.querySelector('#time-entry-reset-button');
    const editNotesButton = document.querySelector('#time-entry-notes-edit-button');
    const hasRunningEntry = currentRunningEntry !== null;
    const hasActivities = availableActivities.length > 0;
    const selectedActivityId = parseSelectedId(activityField);
    const hasSelectedActivity = ((_a = activityField === null || activityField === void 0 ? void 0 : activityField.value) !== null && _a !== void 0 ? _a : '') !== '';
    const subtypeHasChoices = ((_b = subtypeField === null || subtypeField === void 0 ? void 0 : subtypeField.options.length) !== null && _b !== void 0 ? _b : 0) > 1;
    const hasRequiredActivity = !hasRunningEntry || hasSelectedActivity;
    const isDirty = hasRunningEntry &&
        !areSnapshotsEqual(getCurrentEntryFormSnapshot(), currentEntrySnapshot);
    setSelectDisabledState(activityField, !hasRunningEntry || !hasActivities, hasRunningEntry
        ? hasActivities
            ? ''
            : 'Create an activity first'
        : 'Start an entry first');
    setSelectDisabledState(subtypeField, !hasRunningEntry || selectedActivityId === null || !subtypeHasChoices, !hasRunningEntry
        ? 'Start an entry first'
        : selectedActivityId === null
            ? 'Select an activity first'
            : 'No subtypes available for this activity');
    if (notesField) {
        notesField.disabled = !hasRunningEntry;
        notesField.readOnly = !hasRunningEntry || !isNotesEditMode;
    }
    if (saveButton) {
        saveButton.disabled = !hasRunningEntry || !isDirty || !hasRequiredActivity;
    }
    if (stopButton) {
        stopButton.disabled = !hasRunningEntry || !hasRequiredActivity;
    }
    if (startButton) {
        startButton.textContent = hasRunningEntry ? 'Stop and Start' : 'Start';
        startButton.dataset.defaultLabel = startButton.textContent;
        startButton.disabled = hasRunningEntry && !hasRequiredActivity;
    }
    if (resetButton) {
        resetButton.disabled = !isDirty;
    }
    if (editNotesButton) {
        editNotesButton.disabled = !hasRunningEntry;
    }
    renderCurrentEntrySummary();
}
function renderCurrentEntrySummary() {
    var _a, _b, _c, _d, _e, _f;
    const cardElement = document.querySelector('#current-entry-card');
    const activityElement = document.querySelector('#current-entry-activity');
    const subtypeElement = document.querySelector('#current-entry-subtype');
    const activityField = document.querySelector('#time-entry-activity');
    const subtypeField = document.querySelector('#time-entry-subtype');
    if (!cardElement || !activityElement || !subtypeElement) {
        return;
    }
    if (!currentRunningEntry) {
        cardElement.hidden = true;
        subtypeElement.hidden = true;
        return;
    }
    const activityLabel = (_c = (_a = getSelectedOptionLabel(activityField)) !== null && _a !== void 0 ? _a : (_b = currentRunningEntry.activity_name) === null || _b === void 0 ? void 0 : _b.trim()) !== null && _c !== void 0 ? _c : '';
    const subtypeLabel = (_f = (_d = getSelectedOptionLabel(subtypeField)) !== null && _d !== void 0 ? _d : (_e = currentRunningEntry.activity_subtype_name) === null || _e === void 0 ? void 0 : _e.trim()) !== null && _f !== void 0 ? _f : '';
    if (!activityLabel) {
        cardElement.hidden = true;
        subtypeElement.hidden = true;
        return;
    }
    cardElement.hidden = false;
    activityElement.textContent = activityLabel;
    subtypeElement.hidden = !subtypeLabel;
    subtypeElement.textContent = subtypeLabel;
}
function renderCurrentEntry(entries) {
    return __awaiter(this, void 0, void 0, function* () {
        const runningEntry = getRunningEntry(entries);
        const activityField = document.querySelector('#time-entry-activity');
        const subtypeField = document.querySelector('#time-entry-subtype');
        const notesField = document.querySelector('#time-entry-notes');
        currentEntries = entries;
        currentRunningEntry = runningEntry;
        currentEntrySnapshot = buildEntrySnapshot(runningEntry);
        isNotesEditMode = false;
        stopRunningEntryTimer();
        if (!runningEntry) {
            setElementText('#current-entry-duration', '00:00:00');
            setSelectValue(activityField, null);
            yield loadSubtypeOptionsForSelect(subtypeField, null, null, 'Select activity first', 'No subtypes yet', 'Start an entry first');
            if (notesField) {
                notesField.value = '';
            }
            renderCurrentEntryState('Idle', false);
            syncCurrentEntryControls();
            return;
        }
        const renderDuration = () => {
            setElementText('#current-entry-duration', formatEntryDuration(runningEntry));
            renderEntries(currentEntries);
        };
        setSelectValue(activityField, currentEntrySnapshot.activityId);
        yield loadSubtypeOptionsForSelect(subtypeField, currentEntrySnapshot.activityId, currentEntrySnapshot.activitySubtypeId, 'Select activity first', 'No subtypes yet', 'No subtypes available for this activity');
        if (notesField) {
            notesField.value = currentEntrySnapshot.notes;
        }
        renderDuration();
        runningEntryTimerId = window.setInterval(renderDuration, 1000);
        renderCurrentEntryState('Running', true);
        syncCurrentEntryControls();
    });
}
function renderEntries(entries) {
    const body = document.querySelector('#time-entries-body');
    if (!body) {
        return;
    }
    if (entries.length === 0) {
        body.innerHTML = `
			<tr>
        <td colspan="6" class="time-log-empty">No entries yet for today.</td>
			</tr>
		`;
        return;
    }
    body.innerHTML = entries
        .map((entry) => {
        var _a;
        const isRunning = entry.end === null || entry.state === 'running';
        const timeRange = `${escapeHtml(formatClockTime(entry.start))} - ${escapeHtml(entry.end ? formatClockTime(entry.end) : '--')}`;
        const activityLabel = escapeHtml(entry.activity_name || '—');
        const subtypeLabel = escapeHtml(entry.activity_subtype_name || '—');
        return `
				<tr class="time-log-entry-row ${isRunning ? 'is-running' : ''}">
          <td>${timeRange}</td>
          <td>${activityLabel}</td>
          <td>${subtypeLabel}</td>
					<td class="time-log-duration-cell ${isRunning ? 'is-running' : ''}">${escapeHtml(formatEntryDuration(entry))}</td>
					<td><span class="time-log-entry-badge ${getEntryBadgeClass(entry.state)}">${escapeHtml(formatStateLabel(entry.state))}</span></td>
					<td class="time-log-notes-cell">${escapeHtml(((_a = entry.notes) === null || _a === void 0 ? void 0 : _a.trim()) || 'No notes')}</td>
				</tr>
			`;
    })
        .join('');
}
function stopRunningEntryTimer() {
    if (runningEntryTimerId !== null) {
        window.clearInterval(runningEntryTimerId);
        runningEntryTimerId = null;
    }
}
function renderDailyLog(dailyLog) {
    setElementText('#time-log-day-pill', formatDayLabel(dailyLog.date));
    setElementText('#time-log-schedule', `${formatClockTime(dailyLog.wake_time, true)} - ${formatClockTime(dailyLog.sleep_time, true)}`);
}
function loadActivityOptions() {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetchJson('/activities/list.php');
        const startActivitySelect = document.querySelector('#time-entry-activity');
        const hasActivities = response.activities.length > 0;
        availableActivities = response.activities;
        populateSelectOptions(startActivitySelect, hasActivities ? 'Choose activity (required)' : 'No activities yet', response.activities, (activity) => String(activity.id), (activity) => activity.name);
        setSelectDisabledState(startActivitySelect, !hasActivities, hasActivities ? '' : 'Create an activity first');
    });
}
function refreshTimeLogPage() {
    return __awaiter(this, void 0, void 0, function* () {
        const clientDate = getClientTodayDateKey();
        const [dailyLogResponse, entriesResponse] = yield Promise.all([
            fetchJson(`/daily-log/today.php?date=${encodeURIComponent(clientDate)}`),
            fetchJson(`/time-entries/today.php?date=${encodeURIComponent(clientDate)}`),
        ]);
        timeLogActiveDate = dailyLogResponse.daily_log.date;
        renderDailyLog(dailyLogResponse.daily_log);
        yield renderCurrentEntry(entriesResponse.entries);
        renderEntries(entriesResponse.entries);
    });
}
function resetCurrentEntryFormToSnapshot() {
    return __awaiter(this, void 0, void 0, function* () {
        const activityField = document.querySelector('#time-entry-activity');
        const subtypeField = document.querySelector('#time-entry-subtype');
        const notesField = document.querySelector('#time-entry-notes');
        if (!currentEntrySnapshot) {
            return;
        }
        setSelectValue(activityField, currentEntrySnapshot.activityId);
        yield loadSubtypeOptionsForSelect(subtypeField, currentEntrySnapshot.activityId, currentEntrySnapshot.activitySubtypeId, 'Select activity first', 'No subtypes yet', 'No subtypes available for this activity');
        if (notesField) {
            notesField.value = currentEntrySnapshot.notes;
        }
        isNotesEditMode = false;
        syncCurrentEntryControls();
    });
}
function buildCurrentEntryPayload() {
    const snapshot = getCurrentEntryFormSnapshot();
    const payload = {
        notes: snapshot.notes,
    };
    if (snapshot.activityId !== null) {
        payload.activity_id = snapshot.activityId;
    }
    if (snapshot.activitySubtypeId !== null) {
        payload.activity_subtype_id = snapshot.activitySubtypeId;
    }
    return payload;
}
function getRunningEntryValidationError() {
    if (!currentRunningEntry) {
        return null;
    }
    return parseSelectedId(document.querySelector('#time-entry-activity')) === null
        ? 'Activity is required.'
        : null;
}
function bindTimeLogPage() {
    const timeLogRoot = document.querySelector('#time-log-app');
    if (!timeLogRoot) {
        return;
    }
    const form = document.querySelector('#time-entry-start-form');
    const notesField = document.querySelector('#time-entry-notes');
    const activityField = document.querySelector('#time-entry-activity');
    const subtypeField = document.querySelector('#time-entry-subtype');
    const saveButton = document.querySelector('#time-entry-save-button');
    const stopButton = document.querySelector('#time-entry-stop-button');
    const submitButton = document.querySelector('#time-entry-start-button');
    const resetButton = document.querySelector('#time-entry-reset-button');
    const editNotesButton = document.querySelector('#time-entry-notes-edit-button');
    activityField === null || activityField === void 0 ? void 0 : activityField.addEventListener('change', () => {
        clearInlineError('#time-entry-error');
        void loadSubtypeOptionsForSelect(subtypeField, parseSelectedId(activityField), null, 'Select activity first', 'No subtypes yet', 'Select an activity first')
            .then(() => {
            syncCurrentEntryControls();
        })
            .catch((error) => {
            const message = error instanceof Error
                ? error.message
                : 'Unable to load subtypes right now.';
            showTimeLogMessage(message, 'danger');
        });
    });
    subtypeField === null || subtypeField === void 0 ? void 0 : subtypeField.addEventListener('change', () => {
        clearInlineError('#time-entry-error');
        syncCurrentEntryControls();
    });
    notesField === null || notesField === void 0 ? void 0 : notesField.addEventListener('input', () => {
        clearInlineError('#time-entry-error');
        syncCurrentEntryControls();
    });
    editNotesButton === null || editNotesButton === void 0 ? void 0 : editNotesButton.addEventListener('click', () => {
        if (!currentRunningEntry || !notesField) {
            return;
        }
        isNotesEditMode = true;
        notesField.readOnly = false;
        notesField.focus();
        syncCurrentEntryControls();
    });
    resetButton === null || resetButton === void 0 ? void 0 : resetButton.addEventListener('click', () => {
        void resetCurrentEntryFormToSnapshot();
    });
    saveButton === null || saveButton === void 0 ? void 0 : saveButton.addEventListener('click', () => __awaiter(this, void 0, void 0, function* () {
        if (!currentRunningEntry) {
            return;
        }
        const validationError = getRunningEntryValidationError();
        if (validationError) {
            showInlineError('#time-entry-error', validationError);
            syncCurrentEntryControls();
            return;
        }
        clearInlineError('#time-entry-error');
        clearTimeLogMessage();
        setButtonLoading(saveButton, true, 'Saving...');
        try {
            yield fetchJson('/time-entries/save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(Object.assign(Object.assign({}, buildCurrentEntryPayload()), { date: buildTimeLogRequestDate() })),
            });
            yield refreshTimeLogPage();
            clearTimeLogMessage();
        }
        catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'Unable to save the current entry right now.';
            showInlineError('#time-entry-error', message);
        }
        finally {
            setButtonLoading(saveButton, false, 'Saving...');
            syncCurrentEntryControls();
        }
    }));
    stopButton === null || stopButton === void 0 ? void 0 : stopButton.addEventListener('click', () => __awaiter(this, void 0, void 0, function* () {
        if (!currentRunningEntry) {
            return;
        }
        const validationError = getRunningEntryValidationError();
        if (validationError) {
            showInlineError('#time-entry-error', validationError);
            syncCurrentEntryControls();
            return;
        }
        clearInlineError('#time-entry-error');
        clearTimeLogMessage();
        setButtonLoading(stopButton, true, 'Stopping...');
        try {
            yield fetchJson('/time-entries/end.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(Object.assign(Object.assign({}, buildCurrentEntryPayload()), { date: buildTimeLogRequestDate(), end: buildTimeKey(new Date()) })),
            });
            yield refreshTimeLogPage();
            clearTimeLogMessage();
        }
        catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'Unable to stop the current entry right now.';
            showInlineError('#time-entry-error', message);
        }
        finally {
            setButtonLoading(stopButton, false, 'Stopping...');
            syncCurrentEntryControls();
        }
    }));
    if (form) {
        form.addEventListener('submit', (event) => __awaiter(this, void 0, void 0, function* () {
            event.preventDefault();
            const validationError = getRunningEntryValidationError();
            if (validationError) {
                showInlineError('#time-entry-error', validationError);
                syncCurrentEntryControls();
                return;
            }
            clearInlineError('#time-entry-error');
            clearTimeLogMessage();
            setButtonLoading(submitButton, true, 'Starting...');
            try {
                const payload = currentRunningEntry
                    ? Object.assign(Object.assign({}, buildCurrentEntryPayload()), { date: buildTimeLogRequestDate(), start: buildTimeKey(new Date()) }) : {
                    date: getClientTodayDateKey(),
                    start: buildTimeKey(new Date()),
                };
                yield fetchJson('/time-entries/start.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                yield refreshTimeLogPage();
                clearTimeLogMessage();
            }
            catch (error) {
                const message = error instanceof Error
                    ? error.message
                    : 'Unable to start the next entry right now.';
                showInlineError('#time-entry-error', message);
            }
            finally {
                setButtonLoading(submitButton, false, 'Starting...');
                syncCurrentEntryControls();
            }
        }));
    }
    void Promise.all([loadActivityOptions(), refreshTimeLogPage()]).catch((error) => {
        const message = error instanceof Error
            ? error.message
            : 'Unable to load the time log right now.';
        showTimeLogMessage(message, 'danger');
        renderCurrentEntry([]);
        renderEntries([]);
    });
}
bindTimeLogPage();
const REPORT_ACTIVITY_COLORS = [
    'var(--color-tag-01)',
    'var(--color-tag-02)',
    'var(--color-tag-03)',
    'var(--color-tag-04)',
    'var(--color-tag-05)',
    'var(--color-tag-06)',
    'var(--color-tag-07)',
    'var(--color-tag-08)',
    'var(--color-tag-09)',
    'var(--color-tag-10)',
    'var(--color-tag-11)',
    'var(--color-tag-12)',
    'var(--color-tag-13)',
    'var(--color-tag-14)',
    'var(--color-tag-15)',
    'var(--color-tag-16)',
    'var(--color-tag-17)',
    'var(--color-tag-18)',
    'var(--color-tag-19)',
    'var(--color-tag-20)',
];
let reportSelectedDate = getRequestedReportDate();
let reportActivitiesLoaded = false;
let reportEditingEntryId = null;
let currentReportEntries = [];
let currentReportSegments = [];
let reportsTimelineSortOrder = 'desc';
function buildDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
function parseDateKey(value) {
    const [year, month, day] = value.split('-').map(Number);
    if ([year, month, day].some(Number.isNaN)) {
        return null;
    }
    return new Date(year, month - 1, day);
}
function addDays(value, amount) {
    const date = parseDateKey(value);
    if (!date) {
        return buildDateKey(new Date());
    }
    date.setDate(date.getDate() + amount);
    return buildDateKey(date);
}
function getRequestedReportDate() {
    var _a, _b;
    const url = new URL(window.location.href);
    const requestedDate = (_b = (_a = url.searchParams.get('date')) === null || _a === void 0 ? void 0 : _a.trim()) !== null && _b !== void 0 ? _b : '';
    return /^\d{4}-\d{2}-\d{2}$/.test(requestedDate)
        ? requestedDate
        : buildDateKey(new Date());
}
function updateUrlDate(date) {
    const url = new URL(window.location.href);
    url.searchParams.set('date', date);
    window.history.replaceState({}, '', url.toString());
}
function buildDateTimeFromParts(date, time) {
    const baseDate = parseDateKey(date);
    const timeDate = parseTimeOnly(time);
    if (!baseDate || !timeDate) {
        return null;
    }
    return new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate(), timeDate.getHours(), timeDate.getMinutes(), timeDate.getSeconds());
}
function formatCalendarLabel(value) {
    const selectedDate = parseDateKey(value);
    if (!selectedDate) {
        return 'Today';
    }
    const todayKey = buildDateKey(new Date());
    const shortLabel = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(selectedDate);
    if (value === todayKey) {
        return `Today, ${shortLabel}`;
    }
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(selectedDate);
}
function formatHoursMinutes(milliseconds) {
    const totalMinutes = Math.max(0, Math.round(milliseconds / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h ${String(minutes).padStart(2, '0')}m`;
}
function formatDateTimeDisplay(value) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    const hours = String(value.getHours()).padStart(2, '0');
    const minutes = String(value.getMinutes()).padStart(2, '0');
    return formatClockTime(`${year}-${month}-${day} ${hours}:${minutes}:00`);
}
function toTimeInputValue(value) {
    const date = parseDateTime(value);
    if (!date) {
        return '';
    }
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}
function isSleepEntry(entry) {
    var _a;
    return ((_a = entry.activity_name) !== null && _a !== void 0 ? _a : '').trim().toLowerCase() === 'sleep';
}
function compareEntryAscending(left, right) {
    var _a, _b, _c, _d;
    return (((_b = (_a = parseDateTime(left.start)) === null || _a === void 0 ? void 0 : _a.getTime()) !== null && _b !== void 0 ? _b : 0) -
        ((_d = (_c = parseDateTime(right.start)) === null || _c === void 0 ? void 0 : _c.getTime()) !== null && _d !== void 0 ? _d : 0));
}
function getReportEntryEnd(entry, date, dailyLog) {
    if (entry.end) {
        return parseDateTime(entry.end);
    }
    const wakeDate = buildDateTimeFromParts(date, dailyLog.wake_time);
    const sleepDate = buildDateTimeFromParts(date, dailyLog.sleep_time);
    if (!wakeDate || !sleepDate || sleepDate.getTime() <= wakeDate.getTime()) {
        return null;
    }
    if (date !== buildDateKey(new Date())) {
        return sleepDate;
    }
    const now = new Date();
    return now.getTime() < sleepDate.getTime() ? now : sleepDate;
}
function buildArcPath(centerX, centerY, radius, startAngle, endAngle) {
    const startX = centerX + radius * Math.cos(startAngle);
    const startY = centerY + radius * Math.sin(startAngle);
    const endX = centerX + radius * Math.cos(endAngle);
    const endY = centerY + radius * Math.sin(endAngle);
    const largeArcFlag = endAngle - startAngle > Math.PI ? 1 : 0;
    return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`;
}
function getReportActivityColor(index) {
    var _a;
    return ((_a = REPORT_ACTIVITY_COLORS[index % REPORT_ACTIVITY_COLORS.length]) !== null && _a !== void 0 ? _a : 'var(--color-primary)');
}
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
        if (!activityId) {
            showInlineError('#reports-entry-error', 'Activity is required.');
            return;
        }
        if (!start || !end) {
            showInlineError('#reports-entry-error', 'Start and end times are required.');
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
                    : { id: reportEditingEntryId })), { date: reportSelectedDate, start,
                    end, notes: (_c = notesField === null || notesField === void 0 ? void 0 : notesField.value) !== null && _c !== void 0 ? _c : '', activity_id: activityId, activity_subtype_id: subtypeId })),
            });
            closeReportsEntryModal();
            reportEditingEntryId = null;
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
