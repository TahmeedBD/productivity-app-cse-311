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
                            <div class="time-log-inline-label-row">
                                <label class="form-label" for="time-entry-notes">Notes</label>
                                <div class="time-log-inline-actions" aria-label="Current entry note actions">
                                    <button id="time-entry-notes-edit-button" type="button" class="btn btn-ghost btn-sm time-log-icon-button" aria-label="Edit notes" title="Edit notes" disabled>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button id="time-entry-reset-button" type="button" class="btn btn-ghost btn-sm time-log-icon-button" aria-label="Undo unsaved changes" title="Undo unsaved changes" disabled>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6L3 13"/></svg>
                                    </button>
                                </div>
                            </div>
                            <textarea id="time-entry-notes" class="textarea time-log-notes-input" placeholder="What are you working on?" autocomplete="off"></textarea>
                        </div>
                    </div>

                    <div class="time-log-hero-actions">
                        <button id="time-entry-save-button" type="button" class="btn btn-ghost" disabled data-default-label="Save">
                            Save
                        </button>
                        <button id="time-entry-stop-button" type="button" class="btn time-log-stop-btn" disabled data-default-label="Stop">
                            Stop
                        </button>
                        <button id="time-entry-start-button" class="btn time-log-primary-action" type="submit" data-default-label="Start entry">
                            Start
                        </button>
                    </div>

                    <p id="time-entry-error" class="time-log-inline-error" hidden></p>
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
                        <th scope="col">Time</th>
                        <th scope="col">Activity</th>
                        <th scope="col">Subtype</th>
                        <th scope="col">Duration</th>
                        <th scope="col">State</th>
                        <th scope="col">Notes</th>
                    </tr>
                </thead>
                <tbody id="time-entries-body">
                    <tr>
                        <td colspan="6" class="time-log-empty">Loading today's entries…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once 'footer.php'; ?>
