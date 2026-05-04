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
}
function renderCurrentEntry(entries) {
    return __awaiter(this, void 0, void 0, function* () {
        var _a;
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
            setElementText('#current-entry-start', 'No entry is running right now.');
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
        setElementText('#current-entry-start', ((_a = runningEntry.notes) === null || _a === void 0 ? void 0 : _a.trim())
            ? `Started at ${formatClockTime(runningEntry.start)} - ${runningEntry.notes.trim()}`
            : `Started at ${formatClockTime(runningEntry.start)}`);
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
        const [dailyLogResponse, entriesResponse] = yield Promise.all([
            fetchJson('/daily-log/today.php'),
            fetchJson('/time-entries/today.php'),
        ]);
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
                body: JSON.stringify(buildCurrentEntryPayload()),
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
                body: JSON.stringify(buildCurrentEntryPayload()),
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
                const payload = currentRunningEntry ? buildCurrentEntryPayload() : {};
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
