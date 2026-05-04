<?php
require_once __DIR__ . '/../src/auth/guard.php';

$pageTitle = 'Time Log';
$pageCSS = 'time-log.css';

require_once 'header.php';
?>

<div id="time-log-app" class="time-log-page">
    <div id="time-log-message" class="alert alert-danger time-log-message" hidden></div>

    <header class="page-heading">
        <h1 class="text-h1">Time Log</h1>
        <p class="text-muted">Track and manage your daily activities.</p>
    </header>

    <section id="time-log-hero" class="time-log-hero card-featured">
        <div class="time-log-hero-body">
            <div class="time-log-timer-panel">
                <div id="current-entry-duration" class="time-log-timer">00:00:00</div>
                <span id="current-entry-state" class="time-log-running-state is-idle">
                    <span class="time-log-running-dot" aria-hidden="true"></span>
                    <span class="time-log-running-label">Idle</span>
                </span>
            </div>

            <div class="time-log-hero-right">
                <form id="time-entry-start-form" class="time-log-form" novalidate>
                    <div class="time-log-form-grid">
                        <div class="form-group">
                            <label class="form-label" for="time-entry-activity">Activity</label>
                            <select id="time-entry-activity" class="select time-log-select" disabled aria-disabled="true" title="Coming soon">
                                <option value="">Select…</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="time-entry-subtype">Subtype</label>
                            <select id="time-entry-subtype" class="select time-log-select" disabled aria-disabled="true" title="Coming soon">
                                <option value="">Select…</option>
                            </select>
                        </div>
                        <div class="form-group time-log-form-notes-span">
                            <label class="form-label" for="time-entry-notes">Notes</label>
                            <input id="time-entry-notes" class="input time-log-notes-input" type="text" placeholder="What are you working on?" autocomplete="off">
                        </div>
                    </div>

                    <div class="time-log-hero-actions">
                        <button id="time-entry-stop-button" type="button" class="btn time-log-stop-btn" disabled data-default-label="Stop">
                            Stop
                        </button>
                        <button id="time-entry-start-button" class="btn time-log-primary-action" type="submit" data-default-label="Start entry">
                            Start entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="page-section">
        <div class="page-section__header">
            <h2 class="text-h2">Today's Entries</h2>
        </div>

        <div class="table-wrap table-wrap--warm">
            <table class="table time-log-table">
                <thead>
                    <tr>
                        <th scope="col">Start</th>
                        <th scope="col">End</th>
                        <th scope="col">Duration</th>
                        <th scope="col">State</th>
                        <th scope="col">Notes</th>
                    </tr>
                </thead>
                <tbody id="time-entries-body">
                    <tr>
                        <td colspan="5" class="time-log-empty">Loading today's entries…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card card-sm card-featured time-log-add-card">
        <h2 class="text-h2 time-log-add-title">Add Past Entry</h2>

        <form id="time-log-add-form" class="time-log-add-form" novalidate>
            <div class="form-group">
                <label class="form-label" for="past-entry-activity">Activity</label>
                <select id="past-entry-activity" class="select" disabled aria-disabled="true" title="Coming soon">
                    <option value="">Select…</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="past-entry-start">Start Time</label>
                <div class="time-log-input-icon-wrap">
                    <input id="past-entry-start" class="input time-log-input-has-icon" type="time">
                    <span class="time-log-input-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="past-entry-end">End Time</label>
                <div class="time-log-input-icon-wrap">
                    <input id="past-entry-end" class="input time-log-input-has-icon" type="time">
                    <span class="time-log-input-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                </div>
            </div>

            <div class="form-group time-log-add-notes">
                <label class="form-label" for="past-entry-notes">Notes</label>
                <input id="past-entry-notes" class="input" type="text" placeholder="Optional">
            </div>

            <div class="time-log-add-button-wrap">
                <button id="past-entry-add-button" class="btn time-log-add-button" type="submit" data-default-label="+ Add Entry">
                    + Add Entry
                </button>
            </div>
        </form>
    </section>
</div>

<?php require_once 'footer.php'; ?>
