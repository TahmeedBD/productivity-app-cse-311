type AlertVariant = 'success' | 'danger' | 'warning';

type DailyLog = {
  id: number | string;
  user_id: string;
  date: string;
  wake_time: string;
  sleep_time: string;
};

type Activity = {
  id: number | string;
  user_id: string;
  name: string;
};

type ActivitySubtype = {
  id: number | string;
  activity_id: number | string;
  name: string;
};

type TimeEntry = {
  id: number | string;
  daily_log_id: number | string;
  activity_id?: number | string | null;
  activity_subtype_id?: number | string | null;
  activity_name?: string | null;
  activity_subtype_name?: string | null;
  start: string;
  end: string | null;
  state: string;
  notes: string | null;
};

type ApiError = {
  ok?: false;
  error?: string;
};

type DailyLogResponse = {
  ok: true;
  daily_log: DailyLog;
};

type ActivitiesResponse = {
  ok: true;
  activities: Activity[];
};

type ActivitySubtypesResponse = {
  ok: true;
  subtypes: ActivitySubtype[];
};

type TimeEntriesResponse = {
  ok: true;
  entries: TimeEntry[];
};

type StartEntryResponse = {
  ok: true;
  entry: TimeEntry;
};

let runningEntryTimerId: number | null = null;

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function parseDateTime(value: string | null): Date | null {
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

function parseTimeOnly(value: string | null): Date | null {
  if (!value) {
    return null;
  }

  const [hours, minutes, seconds] = value.split(':').map(Number);

  if ([hours, minutes, seconds].some(Number.isNaN)) {
    return null;
  }

  return new Date(2000, 0, 1, hours, minutes, seconds);
}

function formatClockTime(
  value: string | null,
  lowercaseMeridiem = false,
): string {
  const date = value?.includes(' ')
    ? parseDateTime(value)
    : parseTimeOnly(value ?? null);

  if (!date) {
    return '--:--';
  }

  const rawHours = date.getHours();
  const displayHours = rawHours % 12 === 0 ? 12 : rawHours % 12;
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const meridiem =
    rawHours >= 12
      ? lowercaseMeridiem
        ? 'pm'
        : 'PM'
      : lowercaseMeridiem
        ? 'am'
        : 'AM';

  return `${displayHours}:${minutes} ${meridiem}`;
}

function formatStateLabel(state: string): string {
  if (state === 'completed') {
    return 'Complete';
  }

  if (state === 'running') {
    return 'Running';
  }

  return state.charAt(0).toUpperCase() + state.slice(1);
}

function formatDayLabel(value: string): string {
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

function formatDuration(milliseconds: number): string {
  const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
  const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
  const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(
    2,
    '0',
  );
  const seconds = String(totalSeconds % 60).padStart(2, '0');

  return `${hours}:${minutes}:${seconds}`;
}

function formatEntryDuration(entry: TimeEntry): string {
  const startDate = parseDateTime(entry.start);
  const endDate = entry.end ? parseDateTime(entry.end) : new Date();

  if (!startDate || !endDate) {
    return '--:--:--';
  }

  return formatDuration(endDate.getTime() - startDate.getTime());
}

function getRunningEntry(entries: TimeEntry[]): TimeEntry | null {
  for (let index = entries.length - 1; index >= 0; index -= 1) {
    const entry = entries[index];

    if (entry.end === null || entry.state === 'running') {
      return entry;
    }
  }

  return null;
}

function setElementText(selector: string, text: string): void {
  const element = document.querySelector<HTMLElement>(selector);

  if (element) {
    element.textContent = text;
  }
}

function setFieldValue(selector: string, value: string): void {
  const field = document.querySelector<HTMLInputElement | HTMLTextAreaElement>(
    selector,
  );

  if (field) {
    field.value = value;
  }
}

function setButtonText(selector: string, text: string): void {
  const button = document.querySelector<HTMLButtonElement>(selector);

  if (!button) {
    return;
  }

  button.textContent = text;
  button.dataset.defaultLabel = text;
}

function setSelectDisabledState(
  select: HTMLSelectElement | null,
  isDisabled: boolean,
  title = '',
): void {
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

function populateSelectOptions<T>(
  select: HTMLSelectElement | null,
  placeholder: string,
  items: T[],
  getValue: (item: T) => string,
  getLabel: (item: T) => string,
): void {
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

function parseSelectedId(select: HTMLSelectElement | null): number | null {
  if (!select || !select.value) {
    return null;
  }

  const parsed = Number(select.value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function getSelectedOptionLabel(
  select: HTMLSelectElement | null,
): string | null {
  if (!select || !select.value) {
    return null;
  }

  return select.selectedOptions[0]?.textContent?.trim() || null;
}

function normalizeTimeForApi(value: string): string {
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

function buildTimeKey(date: Date): string {
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');

  return `${hours}:${minutes}:${seconds}`;
}

function extractDateKeyFromTimestamp(value: string | null): string | null {
  const datePart = value?.split(' ')[0]?.trim() ?? '';

  return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : null;
}

function getClientTodayDateKey(): string {
  return buildDateKey(new Date());
}

function buildTimeLogRequestDate(): string {
  return (
    extractDateKeyFromTimestamp(currentRunningEntry?.start ?? null) ??
    timeLogActiveDate ??
    getClientTodayDateKey()
  );
}

function updatePlaceholderSelectionState(
  select: HTMLSelectElement | null,
): void {
  if (!select) {
    return;
  }

  select.classList.toggle('is-placeholder-selected', select.value === '');
}

function renderCurrentEntryState(label: string, isRunning: boolean): void {
  const stateElement = document.querySelector<HTMLElement>(
    '#current-entry-state',
  );

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

function showTimeLogMessage(message: string, variant: AlertVariant): void {
  const messageElement =
    document.querySelector<HTMLElement>('#time-log-message');

  if (!messageElement) {
    return;
  }

  messageElement.hidden = false;
  messageElement.className = `alert alert-${variant} time-log-message`;
  messageElement.textContent = message;
}

function clearTimeLogMessage(): void {
  const messageElement =
    document.querySelector<HTMLElement>('#time-log-message');

  if (!messageElement) {
    return;
  }

  messageElement.hidden = true;
  messageElement.className = 'time-log-message';
  messageElement.textContent = '';
}

function showInlineError(selector: string, message: string): void {
  const element = document.querySelector<HTMLElement>(selector);

  if (!element) {
    return;
  }

  element.hidden = false;
  element.textContent = message;
}

function clearInlineError(selector: string): void {
  const element = document.querySelector<HTMLElement>(selector);

  if (!element) {
    return;
  }

  element.hidden = true;
  element.textContent = '';
}

function setButtonLoading(
  button: HTMLButtonElement | null,
  isLoading: boolean,
  pendingLabel: string,
): void {
  if (!button) {
    return;
  }

  const defaultLabel = button.dataset.defaultLabel ?? button.textContent ?? '';
  button.dataset.defaultLabel = defaultLabel;
  button.disabled = isLoading;
  button.textContent = isLoading ? pendingLabel : defaultLabel;
}

async function fetchJson<T>(url: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set('Accept', 'application/json');

  const response = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers,
  });
  const text = await response.text();
  const payload = text
    ? (JSON.parse(text) as T & ApiError)
    : ({} as T & ApiError);

  if (!response.ok) {
    throw new Error(payload.error || 'Request failed.');
  }

  return payload as T;
}

function getEntryBadgeClass(state: string): string {
  if (state === 'completed') {
    return 'badge badge-success';
  }

  if (state === 'running') {
    return 'badge badge-accent';
  }

  return 'badge badge-neutral';
}

type EntryFormSnapshot = {
  activityId: number | null;
  activitySubtypeId: number | null;
  notes: string;
};

let availableActivities: Activity[] = [];
let currentEntries: TimeEntry[] = [];
let currentRunningEntry: TimeEntry | null = null;
let currentEntrySnapshot: EntryFormSnapshot | null = null;
let isNotesEditMode = false;
let timeLogActiveDate = getClientTodayDateKey();

function toNullableId(
  value: number | string | null | undefined,
): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function buildEntrySnapshot(entry: TimeEntry | null): EntryFormSnapshot {
  return {
    activityId: toNullableId(entry?.activity_id),
    activitySubtypeId: toNullableId(entry?.activity_subtype_id),
    notes: entry?.notes ?? '',
  };
}

function getCurrentEntryFormSnapshot(): EntryFormSnapshot {
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );
  const notesField =
    document.querySelector<HTMLTextAreaElement>('#time-entry-notes');

  return {
    activityId: parseSelectedId(activityField),
    activitySubtypeId: parseSelectedId(subtypeField),
    notes: notesField?.value ?? '',
  };
}

function areSnapshotsEqual(
  left: EntryFormSnapshot | null,
  right: EntryFormSnapshot | null,
): boolean {
  if (!left || !right) {
    return left === right;
  }

  return (
    left.activityId === right.activityId &&
    left.activitySubtypeId === right.activitySubtypeId &&
    left.notes === right.notes
  );
}

function setSelectValue(
  select: HTMLSelectElement | null,
  value: number | null,
): void {
  if (!select) {
    return;
  }

  select.value = value === null ? '' : String(value);
  updatePlaceholderSelectionState(select);
}

async function loadSubtypeOptionsForSelect(
  select: HTMLSelectElement | null,
  activityId: number | null,
  selectedSubtypeId: number | null,
  idleLabel: string,
  emptyLabel: string,
  disabledTitle: string,
): Promise<void> {
  if (!select) {
    return;
  }

  if (!activityId) {
    populateSelectOptions(
      select,
      idleLabel,
      [],
      () => '',
      () => '',
    );
    setSelectDisabledState(select, true, disabledTitle);
    return;
  }

  const response = await fetchJson<ActivitySubtypesResponse>(
    `/activity-subtypes/list.php?activity_id=${encodeURIComponent(String(activityId))}`,
  );
  const hasSubtypes = response.subtypes.length > 0;

  populateSelectOptions(
    select,
    hasSubtypes ? 'Choose subtype (optional)' : emptyLabel,
    response.subtypes,
    (subtype) => String(subtype.id),
    (subtype) => subtype.name,
  );

  if (selectedSubtypeId !== null) {
    select.value = String(selectedSubtypeId);
    updatePlaceholderSelectionState(select);
  }

  setSelectDisabledState(
    select,
    !hasSubtypes,
    hasSubtypes ? '' : disabledTitle,
  );
}

function syncCurrentEntryControls(): void {
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );
  const notesField =
    document.querySelector<HTMLTextAreaElement>('#time-entry-notes');
  const saveButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-save-button',
  );
  const stopButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-stop-button',
  );
  const startButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-start-button',
  );
  const resetButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-reset-button',
  );
  const editNotesButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-notes-edit-button',
  );
  const hasRunningEntry = currentRunningEntry !== null;
  const hasActivities = availableActivities.length > 0;
  const selectedActivityId = parseSelectedId(activityField);
  const hasSelectedActivity = (activityField?.value ?? '') !== '';
  const subtypeHasChoices = (subtypeField?.options.length ?? 0) > 1;
  const hasRequiredActivity = !hasRunningEntry || hasSelectedActivity;
  const isDirty =
    hasRunningEntry &&
    !areSnapshotsEqual(getCurrentEntryFormSnapshot(), currentEntrySnapshot);

  setSelectDisabledState(
    activityField,
    !hasRunningEntry || !hasActivities,
    hasRunningEntry
      ? hasActivities
        ? ''
        : 'Create an activity first'
      : 'Start an entry first',
  );
  setSelectDisabledState(
    subtypeField,
    !hasRunningEntry || selectedActivityId === null || !subtypeHasChoices,
    !hasRunningEntry
      ? 'Start an entry first'
      : selectedActivityId === null
        ? 'Select an activity first'
        : 'No subtypes available for this activity',
  );

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

function renderCurrentEntrySummary(): void {
  const cardElement = document.querySelector<HTMLElement>(
    '#current-entry-card',
  );
  const activityElement = document.querySelector<HTMLElement>(
    '#current-entry-activity',
  );
  const subtypeElement = document.querySelector<HTMLElement>(
    '#current-entry-subtype',
  );
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );

  if (!cardElement || !activityElement || !subtypeElement) {
    return;
  }

  if (!currentRunningEntry) {
    cardElement.hidden = true;
    subtypeElement.hidden = true;
    return;
  }

  const activityLabel =
    getSelectedOptionLabel(activityField) ??
    currentRunningEntry.activity_name?.trim() ??
    '';
  const subtypeLabel =
    getSelectedOptionLabel(subtypeField) ??
    currentRunningEntry.activity_subtype_name?.trim() ??
    '';

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

async function renderCurrentEntry(entries: TimeEntry[]): Promise<void> {
  const runningEntry = getRunningEntry(entries);
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );
  const notesField =
    document.querySelector<HTMLTextAreaElement>('#time-entry-notes');

  currentEntries = entries;
  currentRunningEntry = runningEntry;
  currentEntrySnapshot = buildEntrySnapshot(runningEntry);
  isNotesEditMode = false;

  stopRunningEntryTimer();

  if (!runningEntry) {
    setElementText('#current-entry-duration', '00:00:00');
    setSelectValue(activityField, null);
    await loadSubtypeOptionsForSelect(
      subtypeField,
      null,
      null,
      'Select activity first',
      'No subtypes yet',
      'Start an entry first',
    );
    if (notesField) {
      notesField.value = '';
    }
    renderCurrentEntryState('Idle', false);
    syncCurrentEntryControls();

    return;
  }

  const renderDuration = (): void => {
    setElementText(
      '#current-entry-duration',
      formatEntryDuration(runningEntry),
    );
    renderEntries(currentEntries);
  };

  setSelectValue(activityField, currentEntrySnapshot.activityId);
  await loadSubtypeOptionsForSelect(
    subtypeField,
    currentEntrySnapshot.activityId,
    currentEntrySnapshot.activitySubtypeId,
    'Select activity first',
    'No subtypes yet',
    'No subtypes available for this activity',
  );

  if (notesField) {
    notesField.value = currentEntrySnapshot.notes;
  }

  renderDuration();
  runningEntryTimerId = window.setInterval(renderDuration, 1000);
  renderCurrentEntryState('Running', true);
  syncCurrentEntryControls();
}

function renderEntries(entries: TimeEntry[]): void {
  const body =
    document.querySelector<HTMLTableSectionElement>('#time-entries-body');

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
					<td class="time-log-notes-cell">${escapeHtml(entry.notes?.trim() || 'No notes')}</td>
				</tr>
			`;
    })
    .join('');
}

function stopRunningEntryTimer(): void {
  if (runningEntryTimerId !== null) {
    window.clearInterval(runningEntryTimerId);
    runningEntryTimerId = null;
  }
}

function renderDailyLog(dailyLog: DailyLog): void {
  setElementText('#time-log-day-pill', formatDayLabel(dailyLog.date));
  setElementText(
    '#time-log-schedule',
    `${formatClockTime(dailyLog.wake_time, true)} - ${formatClockTime(dailyLog.sleep_time, true)}`,
  );
}

async function loadActivityOptions(): Promise<void> {
  const response = await fetchJson<ActivitiesResponse>('/activities/list.php');
  const startActivitySelect = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const hasActivities = response.activities.length > 0;

  availableActivities = response.activities;

  populateSelectOptions(
    startActivitySelect,
    hasActivities ? 'Choose activity (required)' : 'No activities yet',
    response.activities,
    (activity) => String(activity.id),
    (activity) => activity.name,
  );

  setSelectDisabledState(
    startActivitySelect,
    !hasActivities,
    hasActivities ? '' : 'Create an activity first',
  );
}

async function refreshTimeLogPage(): Promise<void> {
  const clientDate = getClientTodayDateKey();
  const [dailyLogResponse, entriesResponse] = await Promise.all([
    fetchJson<DailyLogResponse>(
      `/daily-log/today.php?date=${encodeURIComponent(clientDate)}`,
    ),
    fetchJson<TimeEntriesResponse>(
      `/time-entries/today.php?date=${encodeURIComponent(clientDate)}`,
    ),
  ]);

  timeLogActiveDate = dailyLogResponse.daily_log.date;
  renderDailyLog(dailyLogResponse.daily_log);
  await renderCurrentEntry(entriesResponse.entries);
  renderEntries(entriesResponse.entries);
}

async function resetCurrentEntryFormToSnapshot(): Promise<void> {
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );
  const notesField =
    document.querySelector<HTMLTextAreaElement>('#time-entry-notes');

  if (!currentEntrySnapshot) {
    return;
  }

  setSelectValue(activityField, currentEntrySnapshot.activityId);
  await loadSubtypeOptionsForSelect(
    subtypeField,
    currentEntrySnapshot.activityId,
    currentEntrySnapshot.activitySubtypeId,
    'Select activity first',
    'No subtypes yet',
    'No subtypes available for this activity',
  );

  if (notesField) {
    notesField.value = currentEntrySnapshot.notes;
  }

  isNotesEditMode = false;
  syncCurrentEntryControls();
}

function buildCurrentEntryPayload(): Record<string, string | number> {
  const snapshot = getCurrentEntryFormSnapshot();
  const payload: Record<string, string | number> = {
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

function getRunningEntryValidationError(): string | null {
  if (!currentRunningEntry) {
    return null;
  }

  return parseSelectedId(
    document.querySelector<HTMLSelectElement>('#time-entry-activity'),
  ) === null
    ? 'Activity is required.'
    : null;
}

function bindTimeLogPage(): void {
  const timeLogRoot = document.querySelector<HTMLElement>('#time-log-app');

  if (!timeLogRoot) {
    return;
  }

  const form = document.querySelector<HTMLFormElement>(
    '#time-entry-start-form',
  );
  const notesField =
    document.querySelector<HTMLTextAreaElement>('#time-entry-notes');
  const activityField = document.querySelector<HTMLSelectElement>(
    '#time-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#time-entry-subtype',
  );
  const saveButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-save-button',
  );
  const stopButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-stop-button',
  );
  const submitButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-start-button',
  );
  const resetButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-reset-button',
  );
  const editNotesButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-notes-edit-button',
  );

  activityField?.addEventListener('change', () => {
    clearInlineError('#time-entry-error');
    void loadSubtypeOptionsForSelect(
      subtypeField,
      parseSelectedId(activityField),
      null,
      'Select activity first',
      'No subtypes yet',
      'Select an activity first',
    )
      .then(() => {
        syncCurrentEntryControls();
      })
      .catch((error: unknown) => {
        const message =
          error instanceof Error
            ? error.message
            : 'Unable to load subtypes right now.';
        showTimeLogMessage(message, 'danger');
      });
  });
  subtypeField?.addEventListener('change', () => {
    clearInlineError('#time-entry-error');
    syncCurrentEntryControls();
  });
  notesField?.addEventListener('input', () => {
    clearInlineError('#time-entry-error');
    syncCurrentEntryControls();
  });
  editNotesButton?.addEventListener('click', () => {
    if (!currentRunningEntry || !notesField) {
      return;
    }

    isNotesEditMode = true;
    notesField.readOnly = false;
    notesField.focus();
    syncCurrentEntryControls();
  });
  resetButton?.addEventListener('click', () => {
    void resetCurrentEntryFormToSnapshot();
  });

  saveButton?.addEventListener('click', async () => {
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
      await fetchJson<StartEntryResponse>('/time-entries/save.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...buildCurrentEntryPayload(),
          date: buildTimeLogRequestDate(),
        }),
      });

      await refreshTimeLogPage();
      clearTimeLogMessage();
    } catch (error) {
      const message =
        error instanceof Error
          ? error.message
          : 'Unable to save the current entry right now.';
      showInlineError('#time-entry-error', message);
    } finally {
      setButtonLoading(saveButton, false, 'Saving...');
      syncCurrentEntryControls();
    }
  });

  stopButton?.addEventListener('click', async () => {
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
      await fetchJson<StartEntryResponse>('/time-entries/end.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...buildCurrentEntryPayload(),
          date: buildTimeLogRequestDate(),
          end: buildTimeKey(new Date()),
        }),
      });

      await refreshTimeLogPage();
      clearTimeLogMessage();
    } catch (error) {
      const message =
        error instanceof Error
          ? error.message
          : 'Unable to stop the current entry right now.';
      showInlineError('#time-entry-error', message);
    } finally {
      setButtonLoading(stopButton, false, 'Stopping...');
      syncCurrentEntryControls();
    }
  });

  if (form) {
    form.addEventListener('submit', async (event) => {
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
          ? {
              ...buildCurrentEntryPayload(),
              date: buildTimeLogRequestDate(),
              start: buildTimeKey(new Date()),
            }
          : {
              date: getClientTodayDateKey(),
              start: buildTimeKey(new Date()),
            };

        await fetchJson<StartEntryResponse>('/time-entries/start.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });

        await refreshTimeLogPage();
        clearTimeLogMessage();
      } catch (error) {
        const message =
          error instanceof Error
            ? error.message
            : 'Unable to start the next entry right now.';
        showInlineError('#time-entry-error', message);
      } finally {
        setButtonLoading(submitButton, false, 'Starting...');
        syncCurrentEntryControls();
      }
    });
  }

  void Promise.all([loadActivityOptions(), refreshTimeLogPage()]).catch(
    (error: unknown) => {
      const message =
        error instanceof Error
          ? error.message
          : 'Unable to load the time log right now.';
      showTimeLogMessage(message, 'danger');
      renderCurrentEntry([]);
      renderEntries([]);
    },
  );
}

bindTimeLogPage();

type ActivityDurationSummary = {
  name: string;
  durationMs: number;
  color: string;
};

type TimelineSegment = {
  kind: 'entry' | 'gap';
  start: Date;
  end: Date;
  durationMs: number;
  isFuture: boolean;
  entry: TimeEntry | null;
  color: string;
};

type ReportFormState = {
  notes?: string;
  start: string;
  end: string;
};

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
let reportEditingEntryId: number | null = null;
let reportEditingEntryIsRunning = false;
let currentReportEntries: TimeEntry[] = [];
let currentReportSegments: TimelineSegment[] = [];
let reportsTimelineSortOrder: 'desc' | 'asc' = 'desc';

function buildDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function parseDateKey(value: string): Date | null {
  const [year, month, day] = value.split('-').map(Number);

  if ([year, month, day].some(Number.isNaN)) {
    return null;
  }

  return new Date(year, month - 1, day);
}

function addDays(value: string, amount: number): string {
  const date = parseDateKey(value);

  if (!date) {
    return buildDateKey(new Date());
  }

  date.setDate(date.getDate() + amount);

  return buildDateKey(date);
}

function getRequestedReportDate(): string {
  const url = new URL(window.location.href);
  const requestedDate = url.searchParams.get('date')?.trim() ?? '';

  return /^\d{4}-\d{2}-\d{2}$/.test(requestedDate)
    ? requestedDate
    : buildDateKey(new Date());
}

function updateUrlDate(date: string): void {
  const url = new URL(window.location.href);
  url.searchParams.set('date', date);
  window.history.replaceState({}, '', url.toString());
}

function buildDateTimeFromParts(date: string, time: string): Date | null {
  const baseDate = parseDateKey(date);
  const timeDate = parseTimeOnly(time);

  if (!baseDate || !timeDate) {
    return null;
  }

  return new Date(
    baseDate.getFullYear(),
    baseDate.getMonth(),
    baseDate.getDate(),
    timeDate.getHours(),
    timeDate.getMinutes(),
    timeDate.getSeconds(),
  );
}

function formatCalendarLabel(value: string): string {
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

function formatHoursMinutes(milliseconds: number): string {
  const totalMinutes = Math.max(0, Math.round(milliseconds / 60000));
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  return `${hours}h ${String(minutes).padStart(2, '0')}m`;
}

function formatDateTimeDisplay(value: Date): string {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  const hours = String(value.getHours()).padStart(2, '0');
  const minutes = String(value.getMinutes()).padStart(2, '0');

  return formatClockTime(`${year}-${month}-${day} ${hours}:${minutes}:00`);
}

function toTimeInputValue(value: string | null): string {
  const date = parseDateTime(value);

  if (!date) {
    return '';
  }

  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function isSleepEntry(entry: TimeEntry): boolean {
  return (entry.activity_name ?? '').trim().toLowerCase() === 'sleep';
}

function compareEntryAscending(left: TimeEntry, right: TimeEntry): number {
  return (
    (parseDateTime(left.start)?.getTime() ?? 0) -
    (parseDateTime(right.start)?.getTime() ?? 0)
  );
}

function getReportEntryEnd(
  entry: TimeEntry,
  date: string,
  dailyLog: DailyLog,
): Date | null {
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

function buildArcPath(
  centerX: number,
  centerY: number,
  radius: number,
  startAngle: number,
  endAngle: number,
): string {
  const startX = centerX + radius * Math.cos(startAngle);
  const startY = centerY + radius * Math.sin(startAngle);
  const endX = centerX + radius * Math.cos(endAngle);
  const endY = centerY + radius * Math.sin(endAngle);
  const largeArcFlag = endAngle - startAngle > Math.PI ? 1 : 0;

  return `M ${startX} ${startY} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`;
}

function getReportActivityColor(index: number): string {
  return (
    REPORT_ACTIVITY_COLORS[index % REPORT_ACTIVITY_COLORS.length] ??
    'var(--color-primary)'
  );
}

function setReportsMessage(message: string | null): void {
  const element = document.querySelector<HTMLElement>('#reports-message');

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

function summarizeActivities(
  entries: TimeEntry[],
  date: string,
  dailyLog: DailyLog,
): ActivityDurationSummary[] {
  const durationByActivity = new Map<string, number>();

  for (const entry of entries) {
    const start = parseDateTime(entry.start);
    const end = getReportEntryEnd(entry, date, dailyLog);

    if (!start || !end) {
      continue;
    }

    const activityName =
      (entry.activity_name ?? 'Untitled').trim() || 'Untitled';
    durationByActivity.set(
      activityName,
      (durationByActivity.get(activityName) ?? 0) +
        (end.getTime() - start.getTime()),
    );
  }

  return Array.from(durationByActivity.entries())
    .sort((left, right) => right[1] - left[1])
    .map(([name, durationMs], index) => ({
      name,
      durationMs,
      color: getReportActivityColor(index),
    }));
}

function splitSegmentAtNow(
  segment: TimelineSegment,
  now: Date,
): TimelineSegment[] {
  if (
    now.getTime() <= segment.start.getTime() ||
    now.getTime() >= segment.end.getTime()
  ) {
    return [segment];
  }

  return [
    {
      ...segment,
      end: new Date(now),
      durationMs: now.getTime() - segment.start.getTime(),
      isFuture: false,
    },
    {
      ...segment,
      start: new Date(now),
      durationMs: segment.end.getTime() - now.getTime(),
      isFuture: true,
    },
  ];
}

function buildTimelineSegments(
  date: string,
  dailyLog: DailyLog,
  entries: TimeEntry[],
): TimelineSegment[] {
  const wakeDate = buildDateTimeFromParts(date, dailyLog.wake_time);
  const sleepDate = buildDateTimeFromParts(date, dailyLog.sleep_time);

  if (!wakeDate || !sleepDate || sleepDate.getTime() <= wakeDate.getTime()) {
    return [];
  }

  const colorByActivity = new Map(
    summarizeActivities(entries, date, dailyLog).map((summary) => [
      summary.name,
      summary.color,
    ]),
  );
  const segments: TimelineSegment[] = [];
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

    const activityName =
      (entry.activity_name ?? 'Untitled').trim() || 'Untitled';

    segments.push({
      kind: 'entry',
      start,
      end,
      durationMs: end.getTime() - start.getTime(),
      isFuture: isToday && start.getTime() >= now.getTime(),
      entry,
      color: colorByActivity.get(activityName) ?? getReportActivityColor(0),
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

  const resolvedSegments: TimelineSegment[] = [];

  for (const segment of segments) {
    resolvedSegments.push(...splitSegmentAtNow(segment, now));
  }

  return resolvedSegments;
}

function renderReportsDonut(
  summaries: ActivityDurationSummary[],
  segments: TimelineSegment[],
): void {
  const donut = document.querySelector<SVGElement>('#reports-donut-chart');
  const totalLabel = document.querySelector<HTMLElement>(
    '#reports-total-productive',
  );
  const legend = document.querySelector<HTMLElement>('#reports-legend');

  if (!donut || !totalLabel || !legend) {
    return;
  }

  const totalTrackedMs = summaries.reduce(
    (total, summary) => total + summary.durationMs,
    0,
  );
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
    ...summaries.map(
      (summary) => `
        <div class="reports-legend__row">
          <div class="reports-legend__left">
            <span class="reports-legend__dot" style="--legend-color: ${summary.color}"></span>
            <span class="text-sm">${escapeHtml(summary.name)}</span>
          </div>
          <span class="text-sm">${escapeHtml(formatHoursMinutes(summary.durationMs))}</span>
        </div>
      `,
    ),
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

function renderReportsTimelineBar(
  date: string,
  dailyLog: DailyLog,
  segments: TimelineSegment[],
): void {
  const bar = document.querySelector<HTMLElement>('#reports-timeline-bar');
  const nowMarker = document.querySelector<HTMLElement>(
    '#reports-timeline-now',
  );
  const wakeLabel = document.querySelector<HTMLElement>('#reports-wake-label');
  const midpointLabel = document.querySelector<HTMLElement>(
    '#reports-midpoint-label',
  );
  const sleepLabel = document.querySelector<HTMLElement>(
    '#reports-sleep-label',
  );
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
      const width = (segment.durationMs / totalWindowMs) * 100;
      const label =
        segment.kind === 'gap'
          ? ''
          : (segment.entry?.activity_name ?? 'Untitled');

      return `
        <div
          class="reports-day-track__segment ${segment.kind === 'gap' ? 'reports-day-track__segment--gap' : ''} ${segment.isFuture ? 'reports-day-track__segment--future' : ''}"
          style="width: ${width}%; ${segment.kind === 'gap' ? '' : `background: ${segment.color}`}"
          title="${escapeHtml(
            `${segment.kind === 'gap' ? 'Untracked' : label} · ${formatHoursMinutes(segment.durationMs)}`,
          )}"
        >
          <span>${escapeHtml(width >= 11 ? label : '')}</span>
        </div>
      `;
    })
    .join('');

  const now = new Date();
  const isToday = date === buildDateKey(now);

  if (
    !isToday ||
    now.getTime() < wakeDate.getTime() ||
    now.getTime() > sleepDate.getTime()
  ) {
    nowMarker.hidden = true;
    return;
  }

  nowMarker.hidden = false;
  nowMarker.style.left = `${((now.getTime() - wakeDate.getTime()) / totalWindowMs) * 100}%`;
}

function buildReportRowStatus(segment: TimelineSegment): string {
  if (segment.kind === 'gap') {
    const startValue = `${String(segment.start.getHours()).padStart(2, '0')}:${String(segment.start.getMinutes()).padStart(2, '0')}`;
    const endValue = `${String(segment.end.getHours()).padStart(2, '0')}:${String(segment.end.getMinutes()).padStart(2, '0')}`;

    return `<button type="button" class="reports-row__add" data-gap-start="${startValue}" data-gap-end="${endValue}">+ Add entry</button>`;
  }

  return `<button type="button" class="reports-row__edit" data-entry-id="${escapeHtml(String(segment.entry?.id ?? ''))}">Edit</button><span class="reports-row__status ${getEntryBadgeClass(segment.entry?.state ?? 'completed')}">${escapeHtml(formatStateLabel(segment.entry?.state ?? 'completed'))}</span>`;
}

function updateReportsSortButton(): void {
  const sortButton = document.querySelector<HTMLButtonElement>(
    '#reports-sort-button',
  );

  if (!sortButton) {
    return;
  }

  const isNewestFirst = reportsTimelineSortOrder === 'desc';
  sortButton.innerHTML = `<span aria-hidden="true">${isNewestFirst ? '↓' : '↑'}</span>`;
  sortButton.setAttribute(
    'aria-label',
    isNewestFirst ? 'Show newest entries first' : 'Show oldest entries first',
  );
  sortButton.title = isNewestFirst
    ? 'Show newest entries first'
    : 'Show oldest entries first';
}

function renderReportsTimelineList(segments: TimelineSegment[]): void {
  const container = document.querySelector<HTMLElement>(
    '#reports-timeline-list',
  );
  const countBadge = document.querySelector<HTMLElement>(
    '#reports-entry-count',
  );

  if (!container || !countBadge) {
    return;
  }

  const entryCount = segments.filter(
    (segment) => segment.kind === 'entry',
  ).length;
  const sortedSegments =
    reportsTimelineSortOrder === 'desc' ? [...segments].reverse() : segments;
  const visibleSegments = sortedSegments.filter(
    (segment) => !(segment.kind === 'gap' && segment.isFuture),
  );

  updateReportsSortButton();
  countBadge.textContent = `${entryCount} ${entryCount === 1 ? 'entry' : 'entries'}`;

  if (visibleSegments.length === 0) {
    container.innerHTML =
      '<div class="reports-empty-state">No awake-window data available for this day.</div>';
    return;
  }

  container.innerHTML = visibleSegments
    .map((segment) => {
      const title =
        segment.kind === 'gap'
          ? 'Untracked'
          : (segment.entry?.activity_name ?? 'Untitled');
      const subtitle =
        segment.kind === 'gap'
          ? segment.isFuture
            ? 'Upcoming time'
            : 'No entry logged'
          : segment.entry?.activity_subtype_name?.trim() ||
            segment.entry?.notes?.trim() ||
            'Logged entry';

      return `
        <div class="reports-row ${segment.kind === 'gap' ? 'reports-row--gap' : ''} ${segment.isFuture ? 'reports-row--future' : ''}">
          <div class="reports-row__time">${escapeHtml(formatDateTimeDisplay(segment.start))} - ${escapeHtml(formatDateTimeDisplay(segment.end))}</div>
          <div class="reports-row__details">
            <span class="reports-row__marker ${segment.kind === 'gap' ? 'reports-row__marker--gap' : ''}" style="${segment.kind === 'gap' ? '' : `--row-color: ${segment.color}`} "></span>
            <div class="reports-row__content">
              <div class="reports-row__headline">
                ${
                  segment.kind === 'gap'
                    ? `<span class="reports-row__title reports-row__title--gap">${escapeHtml(title)}</span>`
                    : `<span class="reports-row__title">${escapeHtml(title)}</span>`
                }
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

function openReportsEntryModal(prefill?: Partial<ReportFormState>): void {
  const modal = document.querySelector<HTMLElement>('#reports-entry-modal');
  const dateLabel = document.querySelector<HTMLElement>(
    '#reports-entry-modal-date',
  );
  const titleLabel = document.querySelector<HTMLElement>(
    '#reports-entry-modal-title',
  );
  const activityField = document.querySelector<HTMLSelectElement>(
    '#reports-entry-activity',
  );
  const startField = document.querySelector<HTMLInputElement>(
    '#reports-entry-start',
  );
  const endField =
    document.querySelector<HTMLInputElement>('#reports-entry-end');
  const notesField = document.querySelector<HTMLTextAreaElement>(
    '#reports-entry-notes',
  );
  const deleteButton = document.querySelector<HTMLButtonElement>(
    '#reports-entry-delete',
  );

  if (
    !modal ||
    !dateLabel ||
    !titleLabel ||
    !activityField ||
    !startField ||
    !endField ||
    !notesField ||
    !deleteButton
  ) {
    return;
  }

  clearInlineError('#reports-entry-error');
  dateLabel.textContent = formatCalendarLabel(reportSelectedDate);
  titleLabel.textContent =
    reportEditingEntryId === null ? 'Add Entry' : 'Edit Entry';
  setButtonText(
    '#reports-entry-submit',
    reportEditingEntryId === null ? 'Save Entry' : 'Save Changes',
  );
  deleteButton.hidden = reportEditingEntryId === null;
  endField.required = !reportEditingEntryIsRunning;
  startField.value = prefill?.start ?? '';
  endField.value = prefill?.end ?? '';
  notesField.value = prefill?.notes ?? '';
  modal.hidden = false;
  activityField.focus();
}

function closeReportsEntryModal(): void {
  const modal = document.querySelector<HTMLElement>('#reports-entry-modal');

  if (modal) {
    modal.hidden = true;
  }
}

async function ensureReportActivityOptions(): Promise<void> {
  const activityField = document.querySelector<HTMLSelectElement>(
    '#reports-entry-activity',
  );

  if (!activityField) {
    return;
  }

  if (!reportActivitiesLoaded) {
    const response = await fetchJson<ActivitiesResponse>(
      '/activities/list.php',
    );

    availableActivities = response.activities;
    reportActivitiesLoaded = true;
  }

  populateSelectOptions(
    activityField,
    availableActivities.length > 0
      ? 'Choose activity (required)'
      : 'No activities yet',
    availableActivities,
    (activity) => String(activity.id),
    (activity) => activity.name,
  );
  setSelectDisabledState(
    activityField,
    availableActivities.length === 0,
    availableActivities.length === 0 ? 'Create an activity first' : '',
  );
}

async function refreshReportsPage(): Promise<void> {
  updateUrlDate(reportSelectedDate);
  setReportsMessage(null);

  const [dailyLogResponse, entriesResponse] = await Promise.all([
    fetchJson<DailyLogResponse>(
      `/daily-log/today.php?date=${encodeURIComponent(reportSelectedDate)}`,
    ),
    fetchJson<TimeEntriesResponse>(
      `/time-entries/today.php?date=${encodeURIComponent(reportSelectedDate)}`,
    ),
  ]);

  const reportEntries = entriesResponse.entries
    .filter((entry) => !isSleepEntry(entry))
    .sort(compareEntryAscending);
  currentReportEntries = reportEntries;
  const summaries = summarizeActivities(
    reportEntries,
    reportSelectedDate,
    dailyLogResponse.daily_log,
  );
  const segments = buildTimelineSegments(
    reportSelectedDate,
    dailyLogResponse.daily_log,
    reportEntries,
  );
  currentReportSegments = segments;
  const dateLabel = document.querySelector<HTMLElement>('#reports-date-label');
  const dateInput = document.querySelector<HTMLInputElement>(
    '#reports-date-input',
  );
  const nextButton =
    document.querySelector<HTMLButtonElement>('#reports-next-day');

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
  renderReportsTimelineBar(
    reportSelectedDate,
    dailyLogResponse.daily_log,
    segments,
  );
  renderReportsTimelineList(segments);
}

function bindReportsPage(): void {
  const root = document.querySelector<HTMLElement>('#reports-overview');

  if (!root) {
    return;
  }

  const previousButton =
    document.querySelector<HTMLButtonElement>('#reports-prev-day');
  const nextButton =
    document.querySelector<HTMLButtonElement>('#reports-next-day');
  const todayButton = document.querySelector<HTMLButtonElement>(
    '#reports-today-button',
  );
  const dateInput = document.querySelector<HTMLInputElement>(
    '#reports-date-input',
  );
  const addButton = document.querySelector<HTMLButtonElement>(
    '#reports-add-entry-button',
  );
  const sortButton = document.querySelector<HTMLButtonElement>(
    '#reports-sort-button',
  );
  const deleteButton = document.querySelector<HTMLButtonElement>(
    '#reports-entry-delete',
  );
  const modal = document.querySelector<HTMLElement>('#reports-entry-modal');
  const closeButton = document.querySelector<HTMLButtonElement>(
    '#reports-entry-modal-close',
  );
  const activityField = document.querySelector<HTMLSelectElement>(
    '#reports-entry-activity',
  );
  const subtypeField = document.querySelector<HTMLSelectElement>(
    '#reports-entry-subtype',
  );
  const startField = document.querySelector<HTMLInputElement>(
    '#reports-entry-start',
  );
  const endField =
    document.querySelector<HTMLInputElement>('#reports-entry-end');
  const notesField = document.querySelector<HTMLTextAreaElement>(
    '#reports-entry-notes',
  );
  const form = document.querySelector<HTMLFormElement>('#reports-entry-form');
  const submitButton = document.querySelector<HTMLButtonElement>(
    '#reports-entry-submit',
  );

  previousButton?.addEventListener('click', () => {
    reportSelectedDate = addDays(reportSelectedDate, -1);
    void refreshReportsPage().catch((error: unknown) => {
      setReportsMessage(
        error instanceof Error
          ? error.message
          : 'Unable to load the selected day.',
      );
    });
  });

  nextButton?.addEventListener('click', () => {
    if (reportSelectedDate === buildDateKey(new Date())) {
      return;
    }

    reportSelectedDate = addDays(reportSelectedDate, 1);
    void refreshReportsPage().catch((error: unknown) => {
      setReportsMessage(
        error instanceof Error
          ? error.message
          : 'Unable to load the selected day.',
      );
    });
  });

  todayButton?.addEventListener('click', () => {
    reportSelectedDate = buildDateKey(new Date());
    void refreshReportsPage().catch((error: unknown) => {
      setReportsMessage(
        error instanceof Error ? error.message : 'Unable to load today.',
      );
    });
  });

  dateInput?.addEventListener('change', () => {
    const nextDate = dateInput.value.trim();

    if (!/^\d{4}-\d{2}-\d{2}$/.test(nextDate)) {
      return;
    }

    reportSelectedDate = nextDate;
    void refreshReportsPage().catch((error: unknown) => {
      setReportsMessage(
        error instanceof Error
          ? error.message
          : 'Unable to load the selected date.',
      );
    });
  });

  addButton?.addEventListener('click', async () => {
    try {
      reportEditingEntryId = null;
      reportEditingEntryIsRunning = false;
      await ensureReportActivityOptions();
      setSelectValue(activityField, null);
      await loadSubtypeOptionsForSelect(
        subtypeField,
        null,
        null,
        'Choose subtype (optional)',
        'No subtypes yet',
        'Select an activity first',
      );
      openReportsEntryModal();
    } catch (error: unknown) {
      setReportsMessage(
        error instanceof Error ? error.message : 'Unable to load activities.',
      );
    }
  });

  sortButton?.addEventListener('click', () => {
    reportsTimelineSortOrder =
      reportsTimelineSortOrder === 'desc' ? 'asc' : 'desc';
    renderReportsTimelineList(currentReportSegments);
  });

  closeButton?.addEventListener('click', closeReportsEntryModal);

  modal?.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;

    if (target.dataset.closeModal === 'true') {
      closeReportsEntryModal();
    }
  });

  activityField?.addEventListener('change', () => {
    void loadSubtypeOptionsForSelect(
      subtypeField,
      parseSelectedId(activityField),
      null,
      'Choose subtype (optional)',
      'No subtypes yet',
      'Select an activity first',
    );
  });

  document
    .querySelector<HTMLElement>('#reports-timeline-list')
    ?.addEventListener('click', async (event) => {
      const target = event.target as HTMLElement;
      const editButton =
        target.closest<HTMLButtonElement>('.reports-row__edit');
      const addGapButton =
        target.closest<HTMLButtonElement>('.reports-row__add');

      if (editButton) {
        const entryId = Number(editButton.dataset.entryId ?? '');
        const entry = currentReportEntries.find(
          (candidate) => Number(candidate.id) === entryId,
        );

        if (!entry) {
          setReportsMessage('Unable to find that entry right now.');
          return;
        }

        try {
          reportEditingEntryId = entryId;
          reportEditingEntryIsRunning =
            entry.state === 'running' || entry.end === null;
          await ensureReportActivityOptions();
          setSelectValue(activityField, toNullableId(entry.activity_id));
          await loadSubtypeOptionsForSelect(
            subtypeField,
            toNullableId(entry.activity_id),
            toNullableId(entry.activity_subtype_id),
            'Choose subtype (optional)',
            'No subtypes yet',
            'Select an activity first',
          );
          openReportsEntryModal({
            start: toTimeInputValue(entry.start),
            end: toTimeInputValue(entry.end),
            notes: entry.notes ?? '',
          });
        } catch (error: unknown) {
          setReportsMessage(
            error instanceof Error
              ? error.message
              : 'Unable to load the entry.',
          );
        }

        return;
      }

      if (!addGapButton) {
        return;
      }

      try {
        reportEditingEntryId = null;
        reportEditingEntryIsRunning = false;
        await ensureReportActivityOptions();
        setSelectValue(activityField, null);
        await loadSubtypeOptionsForSelect(
          subtypeField,
          null,
          null,
          'Choose subtype (optional)',
          'No subtypes yet',
          'Select an activity first',
        );
        openReportsEntryModal({
          start: addGapButton.dataset.gapStart ?? '',
          end: addGapButton.dataset.gapEnd ?? '',
        });
      } catch (error: unknown) {
        setReportsMessage(
          error instanceof Error ? error.message : 'Unable to load activities.',
        );
      }
    });

  deleteButton?.addEventListener('click', async () => {
    if (reportEditingEntryId === null) {
      return;
    }

    if (!window.confirm('Delete this entry?')) {
      return;
    }

    clearInlineError('#reports-entry-error');
    setButtonLoading(deleteButton, true, 'Deleting...');

    try {
      await fetchJson<{ ok: true }>('/time-entries/delete.php', {
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
      await refreshReportsPage();
    } catch (error: unknown) {
      showInlineError(
        '#reports-entry-error',
        error instanceof Error
          ? error.message
          : 'Unable to delete the entry right now.',
      );
    } finally {
      setButtonLoading(deleteButton, false, 'Deleting...');
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const activityId = parseSelectedId(activityField);
    const subtypeId = parseSelectedId(subtypeField);
    const start = normalizeTimeForApi(startField?.value ?? '');
    const end = normalizeTimeForApi(endField?.value ?? '');
    const requiresEndTime = !reportEditingEntryIsRunning;

    if (!activityId) {
      showInlineError('#reports-entry-error', 'Activity is required.');
      return;
    }

    if (!start || (requiresEndTime && !end)) {
      showInlineError(
        '#reports-entry-error',
        requiresEndTime
          ? 'Start and end times are required.'
          : 'Start time is required.',
      );
      return;
    }

    clearInlineError('#reports-entry-error');
    setButtonLoading(submitButton, true, 'Saving...');

    try {
      await fetchJson<StartEntryResponse>(
        reportEditingEntryId === null
          ? '/time-entries/add.php'
          : '/time-entries/update.php',
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            ...(reportEditingEntryId === null
              ? {}
              : { id: reportEditingEntryId }),
            date: reportSelectedDate,
            start,
            end: end || null,
            notes: notesField?.value ?? '',
            activity_id: activityId,
            activity_subtype_id: subtypeId,
          }),
        },
      );

      closeReportsEntryModal();
      reportEditingEntryId = null;
      reportEditingEntryIsRunning = false;
      await refreshReportsPage();
    } catch (error: unknown) {
      showInlineError(
        '#reports-entry-error',
        error instanceof Error
          ? error.message
          : 'Unable to save the entry right now.',
      );
    } finally {
      setButtonLoading(submitButton, false, 'Saving...');
    }
  });

  void refreshReportsPage().catch((error: unknown) => {
    setReportsMessage(
      error instanceof Error ? error.message : 'Unable to load the report.',
    );
  });
}

bindReportsPage();

type ReportsDashboardTrendState = 'up' | 'down' | 'flat' | 'muted';

type ReportsDashboardTrend = {
  icon: string;
  text: string;
  state: ReportsDashboardTrendState;
};

type ReportsDashboardRange = {
  start: string;
  end: string;
};

type ReportsDashboardActivityConsistencyRow = {
  name: string;
  activeDays: number;
  totalDays: number;
  rate: number;
  color: string;
};

const REPORTS_DASHBOARD_MAX_RANGE_DAYS = 90;
const REPORTS_DASHBOARD_DEFAULT_RANGE_DAYS = 30;
const initialReportsDashboardRange = getRequestedReportsDashboardRange();

let reportsDashboardRangeStart = initialReportsDashboardRange.start;
let reportsDashboardRangeEnd = initialReportsDashboardRange.end;

function isValidReportsDashboardDateKey(value: string): boolean {
  const parsedDate = parseDateKey(value);

  return parsedDate !== null && buildDateKey(parsedDate) === value;
}

function getDefaultReportsDashboardRange(): ReportsDashboardRange {
  const today = buildDateKey(new Date());

  return {
    start: addDays(today, -(REPORTS_DASHBOARD_DEFAULT_RANGE_DAYS - 1)),
    end: today,
  };
}

function normalizeReportsDashboardRange(
  start: string,
  end: string,
): ReportsDashboardRange | null {
  if (
    !isValidReportsDashboardDateKey(start) ||
    !isValidReportsDashboardDateKey(end) ||
    start > end
  ) {
    return null;
  }

  return { start, end };
}

function getRequestedReportsDashboardRange(): ReportsDashboardRange {
  const url = new URL(window.location.href);
  const requestedStart = url.searchParams.get('from')?.trim() ?? '';
  const requestedEnd = url.searchParams.get('to')?.trim() ?? '';
  const normalizedRange = normalizeReportsDashboardRange(
    requestedStart,
    requestedEnd,
  );

  return normalizedRange ?? getDefaultReportsDashboardRange();
}

function updateReportsDashboardUrlRange(start: string, end: string): void {
  const url = new URL(window.location.href);

  url.searchParams.delete('date');
  url.searchParams.set('from', start);
  url.searchParams.set('to', end);
  window.history.replaceState({}, '', url.toString());
}

function countReportsDashboardDaysInclusive(
  start: string,
  end: string,
): number {
  const startDate = parseDateKey(start);
  const endDate = parseDateKey(end);

  if (!startDate || !endDate) {
    return 0;
  }

  const millisecondsPerDay = 24 * 60 * 60 * 1000;

  return (
    Math.floor((endDate.getTime() - startDate.getTime()) / millisecondsPerDay) +
    1
  );
}

function buildReportsDashboardDateRange(start: string, end: string): string[] {
  const totalDays = countReportsDashboardDaysInclusive(start, end);

  return Array.from({ length: totalDays }, (_, index) => addDays(start, index));
}

function getPreviousReportsDashboardRange(
  start: string,
  end: string,
): ReportsDashboardRange {
  const totalDays = countReportsDashboardDaysInclusive(start, end);
  const previousEnd = addDays(start, -1);

  return {
    start: addDays(previousEnd, -(totalDays - 1)),
    end: previousEnd,
  };
}

function getReportsDashboardEntryEnd(entry: TimeEntry): Date | null {
  if (entry.end) {
    return parseDateTime(entry.end);
  }

  const entryDate = extractDateKeyFromTimestamp(entry.start);

  if (entryDate !== buildDateKey(new Date())) {
    return null;
  }

  return new Date();
}

function getReportsDashboardEntryDurationMs(entry: TimeEntry): number {
  const start = parseDateTime(entry.start);
  const end = getReportsDashboardEntryEnd(entry);

  if (!start || !end || end.getTime() <= start.getTime()) {
    return 0;
  }

  return end.getTime() - start.getTime();
}

function summarizeReportsDashboardActivities(
  entries: TimeEntry[],
): ActivityDurationSummary[] {
  const durationByActivity = new Map<string, number>();

  for (const entry of entries) {
    const durationMs = getReportsDashboardEntryDurationMs(entry);

    if (durationMs <= 0) {
      continue;
    }

    const activityName =
      (entry.activity_name ?? 'Untitled').trim() || 'Untitled';

    durationByActivity.set(
      activityName,
      (durationByActivity.get(activityName) ?? 0) + durationMs,
    );
  }

  return Array.from(durationByActivity.entries())
    .sort((left, right) => right[1] - left[1])
    .map(([name, durationMs], index) => ({
      name,
      durationMs,
      color: getReportActivityColor(index),
    }));
}

function setReportsDashboardMessage(message: string | null): void {
  const element = document.querySelector<HTMLElement>(
    '#reports-dashboard-message',
  );

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

function buildReportsDashboardActivityConsistencyRows(
  activities: Activity[],
  entries: TimeEntry[],
  start: string,
  end: string,
): ReportsDashboardActivityConsistencyRow[] {
  const totalDays = countReportsDashboardDaysInclusive(start, end);
  const activeDatesByActivityId = new Map<string, Set<string>>();

  for (const entry of entries) {
    const activityId = entry.activity_id;
    const entryDateKey = extractDateKeyFromTimestamp(entry.start);

    if (
      activityId === null ||
      activityId === undefined ||
      entryDateKey === null ||
      !isValidReportsDashboardDateKey(entryDateKey) ||
      entryDateKey < start ||
      entryDateKey > end
    ) {
      continue;
    }

    const key = String(activityId);
    const activeDates = activeDatesByActivityId.get(key) ?? new Set<string>();

    activeDates.add(entryDateKey);
    activeDatesByActivityId.set(key, activeDates);
  }

  return activities
    .map((activity, index) => {
      const activeDays =
        activeDatesByActivityId.get(String(activity.id))?.size ?? 0;
      const rate = totalDays > 0 ? activeDays / totalDays : 0;

      return {
        name: activity.name,
        activeDays,
        totalDays,
        rate,
        color: getReportActivityColor(index),
      };
    })
    .sort(
      (left, right) =>
        right.activeDays - left.activeDays ||
        left.name.localeCompare(right.name),
    );
}

function calculateReportsDashboardConsistencyRate(
  rows: ReportsDashboardActivityConsistencyRow[],
): number | null {
  if (rows.length === 0) {
    return null;
  }

  const totalOpportunities = rows.reduce(
    (total, row) => total + row.totalDays,
    0,
  );
  const totalActiveDays = rows.reduce(
    (total, row) => total + row.activeDays,
    0,
  );

  return totalOpportunities > 0 ? totalActiveDays / totalOpportunities : 0;
}

function formatReportsDashboardPercentage(value: number | null): string {
  if (value === null) {
    return '0%';
  }

  return `${Math.round(value * 100)}%`;
}

function buildReportsDashboardRelativeTrend(
  current: number,
  previous: number,
): ReportsDashboardTrend {
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

function buildReportsDashboardPointTrend(
  current: number | null,
  previous: number | null,
  emptyLabel: string,
): ReportsDashboardTrend {
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

function renderReportsDashboardTrend(
  selector: string,
  trend: ReportsDashboardTrend,
): void {
  const element = document.querySelector<HTMLElement>(selector);

  if (!element) {
    return;
  }

  element.dataset.state = trend.state;
  element.innerHTML = `<span aria-hidden="true">${escapeHtml(trend.icon)}</span><span>${escapeHtml(trend.text)}</span>`;
}

function renderReportsDashboardSummaryCards(
  currentSummaries: ActivityDurationSummary[],
  previousSummaries: ActivityDurationSummary[],
  currentConsistencyRate: number | null,
  previousConsistencyRate: number | null,
): void {
  const totalCurrentMs = currentSummaries.reduce(
    (total, summary) => total + summary.durationMs,
    0,
  );
  const totalPreviousMs = previousSummaries.reduce(
    (total, summary) => total + summary.durationMs,
    0,
  );
  const mostActive = currentSummaries[0] ?? null;

  setElementText(
    '#reports-dashboard-total-time-value',
    formatHoursMinutes(totalCurrentMs),
  );
  renderReportsDashboardTrend(
    '#reports-dashboard-total-time-trend',
    buildReportsDashboardRelativeTrend(totalCurrentMs, totalPreviousMs),
  );

  setElementText(
    '#reports-dashboard-most-active-value',
    mostActive?.name ?? 'No activity',
  );
  setElementText(
    '#reports-dashboard-most-active-sub',
    mostActive
      ? `${formatHoursMinutes(mostActive.durationMs)} logged`
      : 'No time logged in this range',
  );

  setElementText(
    '#reports-dashboard-completion-rate-value',
    formatReportsDashboardPercentage(currentConsistencyRate),
  );
  renderReportsDashboardTrend(
    '#reports-dashboard-completion-rate-trend',
    buildReportsDashboardPointTrend(
      currentConsistencyRate,
      previousConsistencyRate,
      'No activities configured',
    ),
  );
}

function renderReportsDashboardActivityBars(
  summaries: ActivityDurationSummary[],
): void {
  const container = document.querySelector<HTMLElement>(
    '#reports-dashboard-activity-bars',
  );

  if (!container) {
    return;
  }

  const totalDurationMs = summaries.reduce(
    (total, summary) => total + summary.durationMs,
    0,
  );

  if (summaries.length === 0 || totalDurationMs === 0) {
    container.innerHTML =
      '<div class="reports-dashboard-empty-state">No activity data in this range.</div>';
    return;
  }

  container.innerHTML = summaries
    .map((summary) => {
      const percentage = Math.max(
        1,
        Math.round((summary.durationMs / totalDurationMs) * 100),
      );

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

function renderReportsDashboardHabits(
  rows: ReportsDashboardActivityConsistencyRow[],
): void {
  const tableBody = document.querySelector<HTMLElement>(
    '#reports-dashboard-habits-body',
  );

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
    .map(
      (row) => `
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
      `,
    )
    .join('');
}

async function fetchReportsDashboardEntriesByDate(
  dateKeys: string[],
): Promise<Map<string, TimeEntry[]>> {
  const entriesByDate = new Map<string, TimeEntry[]>();
  const batchSize = 7;

  for (let index = 0; index < dateKeys.length; index += batchSize) {
    const batch = dateKeys.slice(index, index + batchSize);
    const batchEntries = await Promise.all(
      batch.map(async (dateKey) => {
        const response = await fetchJson<TimeEntriesResponse>(
          `/time-entries/today.php?date=${encodeURIComponent(dateKey)}`,
        );

        return [
          dateKey,
          response.entries
            .filter((entry) => !isSleepEntry(entry))
            .sort(compareEntryAscending),
        ] as const;
      }),
    );

    for (const [dateKey, entries] of batchEntries) {
      entriesByDate.set(dateKey, entries);
    }
  }

  return entriesByDate;
}

function syncReportsDashboardFilterInputs(): void {
  const fromInput = document.querySelector<HTMLInputElement>(
    '#reports-dashboard-from-input',
  );
  const toInput = document.querySelector<HTMLInputElement>(
    '#reports-dashboard-to-input',
  );
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

function setReportsDashboardLoading(isLoading: boolean): void {
  const root = document.querySelector<HTMLElement>('#reports-dashboard');
  const filterButton = document.querySelector<HTMLButtonElement>(
    '#reports-dashboard-filter-button',
  );

  root?.classList.toggle('is-loading', isLoading);
  setButtonLoading(filterButton, isLoading, 'Loading...');
}

function validateReportsDashboardRange(
  start: string,
  end: string,
): string | null {
  const normalizedRange = normalizeReportsDashboardRange(start, end);
  const today = buildDateKey(new Date());

  if (!normalizedRange) {
    return 'Choose a valid date range.';
  }

  if (normalizedRange.end > today) {
    return 'The report range cannot extend into the future.';
  }

  if (
    countReportsDashboardDaysInclusive(
      normalizedRange.start,
      normalizedRange.end,
    ) > REPORTS_DASHBOARD_MAX_RANGE_DAYS
  ) {
    return `Select ${REPORTS_DASHBOARD_MAX_RANGE_DAYS} days or fewer.`;
  }

  return null;
}

async function refreshReportsDashboardPage(): Promise<void> {
  updateReportsDashboardUrlRange(
    reportsDashboardRangeStart,
    reportsDashboardRangeEnd,
  );
  setReportsDashboardMessage(null);
  syncReportsDashboardFilterInputs();
  setReportsDashboardLoading(true);

  try {
    const currentRange = {
      start: reportsDashboardRangeStart,
      end: reportsDashboardRangeEnd,
    };
    const previousRange = getPreviousReportsDashboardRange(
      currentRange.start,
      currentRange.end,
    );
    const currentDates = buildReportsDashboardDateRange(
      currentRange.start,
      currentRange.end,
    );
    const previousDates = buildReportsDashboardDateRange(
      previousRange.start,
      previousRange.end,
    );
    const uniqueDates = Array.from(
      new Set([...currentDates, ...previousDates]),
    );

    const [activitiesResponse, entriesByDate] = await Promise.all([
      fetchJson<ActivitiesResponse>('/activities/list.php'),
      fetchReportsDashboardEntriesByDate(uniqueDates),
    ]);

    const currentEntries = currentDates.flatMap(
      (dateKey) => entriesByDate.get(dateKey) ?? [],
    );
    const previousEntries = previousDates.flatMap(
      (dateKey) => entriesByDate.get(dateKey) ?? [],
    );
    const currentSummaries =
      summarizeReportsDashboardActivities(currentEntries);
    const previousSummaries =
      summarizeReportsDashboardActivities(previousEntries);
    const currentConsistencyRows = buildReportsDashboardActivityConsistencyRows(
      activitiesResponse.activities,
      currentEntries,
      currentRange.start,
      currentRange.end,
    );
    const previousConsistencyRows =
      buildReportsDashboardActivityConsistencyRows(
        activitiesResponse.activities,
        previousEntries,
        previousRange.start,
        previousRange.end,
      );
    const currentConsistencyRate = calculateReportsDashboardConsistencyRate(
      currentConsistencyRows,
    );
    const previousConsistencyRate = calculateReportsDashboardConsistencyRate(
      previousConsistencyRows,
    );

    renderReportsDashboardSummaryCards(
      currentSummaries,
      previousSummaries,
      currentConsistencyRate,
      previousConsistencyRate,
    );
    renderReportsDashboardActivityBars(currentSummaries);
    renderReportsDashboardHabits(currentConsistencyRows);
  } finally {
    setReportsDashboardLoading(false);
  }
}

function bindReportsDashboardPage(): void {
  const root = document.querySelector<HTMLElement>('#reports-dashboard');

  if (!root) {
    return;
  }

  const form = document.querySelector<HTMLFormElement>(
    '#reports-dashboard-filter-form',
  );
  const fromInput = document.querySelector<HTMLInputElement>(
    '#reports-dashboard-from-input',
  );
  const toInput = document.querySelector<HTMLInputElement>(
    '#reports-dashboard-to-input',
  );

  syncReportsDashboardFilterInputs();

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const nextStart = fromInput?.value.trim() ?? '';
    const nextEnd = toInput?.value.trim() ?? '';
    const validationMessage = validateReportsDashboardRange(nextStart, nextEnd);

    if (validationMessage) {
      setReportsDashboardMessage(validationMessage);
      return;
    }

    reportsDashboardRangeStart = nextStart;
    reportsDashboardRangeEnd = nextEnd;

    try {
      await refreshReportsDashboardPage();
    } catch (error: unknown) {
      setReportsDashboardMessage(
        error instanceof Error ? error.message : 'Unable to load the report.',
      );
    }
  });

  void refreshReportsDashboardPage().catch((error: unknown) => {
    setReportsDashboardMessage(
      error instanceof Error ? error.message : 'Unable to load the report.',
    );
  });
}

bindReportsDashboardPage();

type DashboardScheduleTimes = {
  wake_time: string;
  sleep_time: string;
};

type DashboardScheduleResponse = {
  ok: true;
  today_daily_log: DailyLog;
  future_defaults: DashboardScheduleTimes;
};

function toTimeOnlyInputValue(value: string | null): string {
  if (!value) {
    return '';
  }

  const trimmed = value.trim();

  return trimmed.length >= 5 ? trimmed.slice(0, 5) : '';
}

function showDashboardMessage(
  message: string | null,
  variant: AlertVariant = 'danger',
): void {
  const messageElement =
    document.querySelector<HTMLElement>('#dashboard-message');

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

function getDashboardEntryEnd(entry: TimeEntry): Date | null {
  if (entry.end) {
    return parseDateTime(entry.end);
  }

  return new Date();
}

function getDashboardTrackedTime(entries: TimeEntry[]): number {
  return entries.reduce((total, entry) => {
    const start = parseDateTime(entry.start);
    const end = getDashboardEntryEnd(entry);

    if (!start || !end || end.getTime() <= start.getTime()) {
      return total;
    }

    return total + (end.getTime() - start.getTime());
  }, 0);
}

function renderDashboardPage(
  schedule: DashboardScheduleResponse,
  entries: TimeEntry[],
  activitiesResponse: ActivitiesResponse,
): void {
  const trackedEntries = entries.filter((entry) => !isSleepEntry(entry));
  const trackedTime = getDashboardTrackedTime(trackedEntries);
  const runningEntry = getRunningEntry(trackedEntries);
  const activeActivityCount = new Set(
    trackedEntries
      .map((entry) => (entry.activity_name ?? '').trim())
      .filter((name) => name !== ''),
  ).size;

  setElementText(
    '#dashboard-date-label',
    new Intl.DateTimeFormat(undefined, {
      weekday: 'long',
      month: 'short',
      day: 'numeric',
    }).format(new Date()),
  );
  setElementText(
    '#dashboard-today-summary',
    `${trackedEntries.length} ${trackedEntries.length === 1 ? 'entry' : 'entries'} logged today.`,
  );
  setElementText(
    '#dashboard-wake-pill',
    `Wake ${formatClockTime(schedule.today_daily_log.wake_time, true)}`,
  );
  setElementText(
    '#dashboard-sleep-pill',
    `Sleep ${formatClockTime(schedule.today_daily_log.sleep_time, true)}`,
  );
  setElementText('#dashboard-tracked-time', formatHoursMinutes(trackedTime));
  setElementText(
    '#dashboard-tracked-time-sub',
    trackedEntries.length > 0
      ? `${trackedEntries.length} ${trackedEntries.length === 1 ? 'entry' : 'entries'} so far today`
      : 'No entries yet',
  );
  setElementText(
    '#dashboard-current-focus',
    runningEntry?.activity_name?.trim() ||
      runningEntry?.activity_subtype_name?.trim() ||
      (runningEntry ? 'Active entry' : 'Idle'),
  );
  setElementText(
    '#dashboard-current-focus-sub',
    runningEntry
      ? runningEntry.activity_subtype_name?.trim() ||
          runningEntry.notes?.trim() ||
          'Timer is running'
      : 'Nothing running right now',
  );
  setElementText(
    '#dashboard-activity-overview',
    `${activeActivityCount}/${activitiesResponse.activities.length}`,
  );
  setElementText('#dashboard-activity-sub', 'Active today');

  setFieldValue(
    '#dashboard-wake-time',
    toTimeOnlyInputValue(schedule.future_defaults.wake_time),
  );
  setFieldValue(
    '#dashboard-sleep-time',
    toTimeOnlyInputValue(schedule.today_daily_log.sleep_time),
  );
}

async function refreshDashboardPage(): Promise<void> {
  const todayDateKey = buildDateKey(new Date());
  const [scheduleResponse, entriesResponse, activitiesResponse] =
    await Promise.all([
      fetchJson<DashboardScheduleResponse>('/daily-log/schedule.php'),
      fetchJson<TimeEntriesResponse>(
        `/time-entries/today.php?date=${encodeURIComponent(todayDateKey)}`,
      ),
      fetchJson<ActivitiesResponse>('/activities/list.php'),
    ]);

  renderDashboardPage(
    scheduleResponse,
    entriesResponse.entries.sort(compareEntryAscending),
    activitiesResponse,
  );
}

function bindDashboardPage(): void {
  const root = document.querySelector<HTMLElement>('#dashboard-app');

  if (!root) {
    return;
  }

  const form = document.querySelector<HTMLFormElement>(
    '#dashboard-schedule-form',
  );
  const wakeField = document.querySelector<HTMLInputElement>(
    '#dashboard-wake-time',
  );
  const sleepField = document.querySelector<HTMLInputElement>(
    '#dashboard-sleep-time',
  );
  const submitButton = document.querySelector<HTMLButtonElement>(
    '#dashboard-schedule-submit',
  );

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const wakeTime = normalizeTimeForApi(wakeField?.value ?? '');
    const sleepTime = normalizeTimeForApi(sleepField?.value ?? '');

    if (!wakeTime || !sleepTime) {
      showInlineError(
        '#dashboard-schedule-error',
        'Wake and sleep times are required.',
      );
      return;
    }

    clearInlineError('#dashboard-schedule-error');
    showDashboardMessage(null);
    setButtonLoading(submitButton, true, 'Saving...');

    try {
      await fetchJson<DashboardScheduleResponse>('/daily-log/schedule.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          wake_time: wakeTime,
          sleep_time: sleepTime,
        }),
      });

      await refreshDashboardPage();
      showDashboardMessage('Schedule updated.', 'success');
    } catch (error: unknown) {
      showInlineError(
        '#dashboard-schedule-error',
        error instanceof Error
          ? error.message
          : "Unable to update today's schedule right now.",
      );
    } finally {
      setButtonLoading(submitButton, false, 'Saving...');
    }
  });

  void refreshDashboardPage().catch((error: unknown) => {
    showDashboardMessage(
      error instanceof Error ? error.message : 'Unable to load the dashboard.',
    );
  });
}

bindDashboardPage();
