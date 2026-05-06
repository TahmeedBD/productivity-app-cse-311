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

const REPORT_ACTIVITY_COLORS = [
    'var(--color-primary)',
    'var(--color-accent)',
    'var(--color-success)',
    'var(--color-danger)',
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
let reportEditingEntryIsRunning = false;
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