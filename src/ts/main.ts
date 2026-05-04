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
    setElementText('#current-entry-start', 'No entry is running right now.');
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
  setElementText(
    '#current-entry-start',
    runningEntry.notes?.trim()
      ? `Started at ${formatClockTime(runningEntry.start)} - ${runningEntry.notes.trim()}`
      : `Started at ${formatClockTime(runningEntry.start)}`,
  );
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
  const [dailyLogResponse, entriesResponse] = await Promise.all([
    fetchJson<DailyLogResponse>('/daily-log/today.php'),
    fetchJson<TimeEntriesResponse>('/time-entries/today.php'),
  ]);

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
        body: JSON.stringify(buildCurrentEntryPayload()),
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
        body: JSON.stringify(buildCurrentEntryPayload()),
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
        const payload = currentRunningEntry ? buildCurrentEntryPayload() : {};

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
