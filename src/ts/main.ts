type AlertVariant = 'success' | 'danger' | 'warning';

type DailyLog = {
  id: number | string;
  user_id: string;
  date: string;
  wake_time: string;
  sleep_time: string;
};

type TimeEntry = {
  id: number | string;
  daily_log_id: number | string;
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

function renderEntries(entries: TimeEntry[]): void {
  const body =
    document.querySelector<HTMLTableSectionElement>('#time-entries-body');

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
      const isRunning = entry.end === null || entry.state === 'running';

      return `
				<tr class="time-log-entry-row ${isRunning ? 'is-running' : ''}">
					<td>${escapeHtml(formatClockTime(entry.start))}</td>
					<td>${escapeHtml(entry.end ? formatClockTime(entry.end) : '--')}</td>
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

function renderCurrentEntry(entries: TimeEntry[]): void {
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

  const renderDuration = (): void => {
    setElementText(
      '#current-entry-duration',
      formatEntryDuration(runningEntry),
    );
  };

  renderDuration();
  runningEntryTimerId = window.setInterval(renderDuration, 1000);
  setElementText(
    '#current-entry-start',
    runningEntry.notes?.trim()
      ? `Started at ${formatClockTime(runningEntry.start)} - ${runningEntry.notes.trim()}`
      : `Started at ${formatClockTime(runningEntry.start)}`,
  );
  setFieldValue('#time-entry-notes', '');
  setButtonText('#time-entry-start-button', 'End current + start new');
  renderCurrentEntryState('Running', true);
}

function renderDailyLog(dailyLog: DailyLog): void {
  setElementText('#time-log-day-pill', formatDayLabel(dailyLog.date));
  setElementText(
    '#time-log-schedule',
    `${formatClockTime(dailyLog.wake_time, true)} - ${formatClockTime(dailyLog.sleep_time, true)}`,
  );
}

async function loadTimeLogPage(): Promise<void> {
  const [dailyLogResponse, entriesResponse] = await Promise.all([
    fetchJson<DailyLogResponse>('/daily-log/today.php'),
    fetchJson<TimeEntriesResponse>('/time-entries/today.php'),
  ]);

  renderDailyLog(dailyLogResponse.daily_log);
  renderCurrentEntry(entriesResponse.entries);
  renderEntries(entriesResponse.entries);
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
  const submitButton = document.querySelector<HTMLButtonElement>(
    '#time-entry-start-button',
  );

  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearTimeLogMessage();
      setButtonLoading(submitButton, true, 'Saving...');

      try {
        const notes = notesField?.value.trim() ?? '';

        await fetchJson<StartEntryResponse>('/time-entries/start.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(notes ? { notes } : {}),
        });

        await loadTimeLogPage();
        clearTimeLogMessage();
      } catch (error) {
        const message =
          error instanceof Error
            ? error.message
            : 'Unable to start the next entry right now.';
        showTimeLogMessage(message, 'danger');
      } finally {
        setButtonLoading(submitButton, false, 'Saving...');
      }
    });
  }

  void loadTimeLogPage().catch((error: unknown) => {
    const message =
      error instanceof Error
        ? error.message
        : 'Unable to load the time log right now.';
    showTimeLogMessage(message, 'danger');
    renderCurrentEntry([]);
    renderEntries([]);
  });
}

bindTimeLogPage();
