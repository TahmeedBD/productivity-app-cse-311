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
}
function parseSelectedId(select) {
    if (!select || !select.value) {
        return null;
    }
    const parsed = Number(select.value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
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
function renderEntries(entries) {
    const body = document.querySelector('#time-entries-body');
    if (!body) {
        return;
    }
    if (entries.length === 0) {
        body.innerHTML = `
			<tr>
				<td colspan="5" class="time-log-empty">No entries yet for today.</td>
			</tr>
		`;
        return;
    }
    body.innerHTML = entries
        .map((entry) => {
        var _a;
        const isRunning = entry.end === null || entry.state === 'running';
        return `
				<tr class="time-log-entry-row ${isRunning ? 'is-running' : ''}">
					<td>${escapeHtml(formatClockTime(entry.start))}</td>
					<td>${escapeHtml(entry.end ? formatClockTime(entry.end) : '--')}</td>
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
function renderCurrentEntry(entries) {
    var _a;
    const runningEntry = getRunningEntry(entries);
    stopRunningEntryTimer();
    if (!runningEntry) {
        setElementText('#current-entry-duration', '00:00:00');
        setElementText('#current-entry-start', 'No entry is running right now.');
        setFieldValue('#time-entry-notes', '');
        setButtonText('#time-entry-start-button', 'Start entry');
        renderCurrentEntryState('Idle', false);
        return;
    }
    const renderDuration = () => {
        setElementText('#current-entry-duration', formatEntryDuration(runningEntry));
    };
    renderDuration();
    runningEntryTimerId = window.setInterval(renderDuration, 1000);
    setElementText('#current-entry-start', ((_a = runningEntry.notes) === null || _a === void 0 ? void 0 : _a.trim())
        ? `Started at ${formatClockTime(runningEntry.start)} - ${runningEntry.notes.trim()}`
        : `Started at ${formatClockTime(runningEntry.start)}`);
    setFieldValue('#time-entry-notes', '');
    setButtonText('#time-entry-start-button', 'Start new');
    renderCurrentEntryState('Running', true);
}
function renderDailyLog(dailyLog) {
    setElementText('#time-log-day-pill', formatDayLabel(dailyLog.date));
    setElementText('#time-log-schedule', `${formatClockTime(dailyLog.wake_time, true)} - ${formatClockTime(dailyLog.sleep_time, true)}`);
}
function loadActivityOptions() {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetchJson('/activities/list.php');
        const startActivitySelect = document.querySelector('#time-entry-activity');
        const pastActivitySelect = document.querySelector('#past-entry-activity');
        const hasActivities = response.activities.length > 0;
        populateSelectOptions(startActivitySelect, hasActivities ? 'Select...' : 'No activities yet', response.activities, (activity) => String(activity.id), (activity) => activity.name);
        populateSelectOptions(pastActivitySelect, hasActivities ? 'Select...' : 'No activities yet', response.activities, (activity) => String(activity.id), (activity) => activity.name);
        setSelectDisabledState(startActivitySelect, !hasActivities, hasActivities ? '' : 'Create an activity first');
        setSelectDisabledState(pastActivitySelect, !hasActivities, hasActivities ? '' : 'Create an activity first');
    });
}
function loadSubtypeOptions(activityId) {
    return __awaiter(this, void 0, void 0, function* () {
        const subtypeSelect = document.querySelector('#time-entry-subtype');
        if (!activityId) {
            populateSelectOptions(subtypeSelect, 'Select activity first', [], () => '', () => '');
            setSelectDisabledState(subtypeSelect, true, 'Select an activity first');
            return;
        }
        const response = yield fetchJson(`/activity-subtypes/list.php?activity_id=${encodeURIComponent(String(activityId))}`);
        const hasSubtypes = response.subtypes.length > 0;
        populateSelectOptions(subtypeSelect, hasSubtypes ? 'Select...' : 'No subtypes yet', response.subtypes, (subtype) => String(subtype.id), (subtype) => subtype.name);
        setSelectDisabledState(subtypeSelect, !hasSubtypes, hasSubtypes ? '' : 'No subtypes available for this activity');
    });
}
function bindActivitySubtypeSelects() {
    const activitySelect = document.querySelector('#time-entry-activity');
    if (!activitySelect) {
        return;
    }
    activitySelect.addEventListener('change', () => {
        void loadSubtypeOptions(parseSelectedId(activitySelect)).catch((error) => {
            const message = error instanceof Error
                ? error.message
                : 'Unable to load subtypes right now.';
            showTimeLogMessage(message, 'danger');
            void loadSubtypeOptions(null);
        });
    });
}
function loadTimeLogPage() {
    return __awaiter(this, void 0, void 0, function* () {
        const [dailyLogResponse, entriesResponse] = yield Promise.all([
            fetchJson('/daily-log/today.php'),
            fetchJson('/time-entries/today.php'),
        ]);
        renderDailyLog(dailyLogResponse.daily_log);
        renderCurrentEntry(entriesResponse.entries);
        renderEntries(entriesResponse.entries);
    });
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
    const submitButton = document.querySelector('#time-entry-start-button');
    bindActivitySubtypeSelects();
    if (form) {
        form.addEventListener('submit', (event) => __awaiter(this, void 0, void 0, function* () {
            var _a;
            event.preventDefault();
            clearTimeLogMessage();
            setButtonLoading(submitButton, true, 'Saving...');
            try {
                const notes = (_a = notesField === null || notesField === void 0 ? void 0 : notesField.value.trim()) !== null && _a !== void 0 ? _a : '';
                const activityId = parseSelectedId(activityField);
                const activitySubtypeId = parseSelectedId(subtypeField);
                const payload = {};
                if (notes) {
                    payload.notes = notes;
                }
                if (activityId !== null) {
                    payload.activity_id = activityId;
                }
                if (activitySubtypeId !== null) {
                    payload.activity_subtype_id = activitySubtypeId;
                }
                yield fetchJson('/time-entries/start.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                yield loadTimeLogPage();
                clearTimeLogMessage();
            }
            catch (error) {
                const message = error instanceof Error
                    ? error.message
                    : 'Unable to start the next entry right now.';
                showTimeLogMessage(message, 'danger');
            }
            finally {
                setButtonLoading(submitButton, false, 'Saving...');
            }
        }));
    }
    void Promise.all([
        loadActivityOptions(),
        loadSubtypeOptions(null),
        loadTimeLogPage(),
    ]).catch((error) => {
        const message = error instanceof Error
            ? error.message
            : 'Unable to load the time log right now.';
        showTimeLogMessage(message, 'danger');
        renderCurrentEntry([]);
        renderEntries([]);
    });
}
bindTimeLogPage();
