<?php
require_once __DIR__ . '/../src/auth/guard.php';

$pageTitle = 'Overview';
$pageCSS = 'reports.css';

require_once 'header.php';
?>

<div id="reports-overview" class="reports-page">
    <div id="reports-message" class="alert alert-danger" hidden></div>

    <section class="reports-toolbar card card-featured">
        <div class="reports-toolbar__heading">
            <h1 class="text-h1">Day Overview</h1>
        </div>
        <div class="reports-toolbar__actions">
            <div class="reports-date-nav" role="group" aria-label="Change report day">
                <button id="reports-prev-day" type="button" class="reports-icon-button" aria-label="Previous day">
                    <span aria-hidden="true">&#8249;</span>
                </button>
                <span id="reports-date-label" class="reports-date-label">Today</span>
                <button id="reports-next-day" type="button" class="reports-icon-button" aria-label="Next day">
                    <span aria-hidden="true">&#8250;</span>
                </button>
            </div>
            <input id="reports-date-input" class="input reports-date-input" type="date" aria-label="Select report date">
            <button id="reports-today-button" type="button" class="btn btn-ghost">Today</button>
            <button id="reports-add-entry-button" type="button" class="btn btn-primary reports-add-button">
                <span aria-hidden="true">+</span>
                <span>Add Entry</span>
            </button>
        </div>
    </section>

    <div class="reports-grid">
        <section class="reports-hero card">
            <div class="reports-donut-shell">
                <svg id="reports-donut-chart" class="reports-donut-chart" viewBox="0 0 220 220" role="img" aria-labelledby="reports-donut-title reports-donut-subtitle">
                    <title id="reports-donut-title">Tracked time distribution</title>
                    <desc id="reports-donut-subtitle">Breakdown of tracked time by activity for the selected day.</desc>
                </svg>
                <div class="reports-donut-center">
                    <span id="reports-total-productive" class="reports-donut-value">0h 00m</span>
                    <span class="text-muted reports-donut-caption">tracked</span>
                </div>
            </div>

            <div id="reports-legend" class="reports-legend"></div>
        </section>

        <div class="reports-main-column">
            <section class="card reports-timeline-card">
                <div class="page-section__header">
                    <h2 class="text-h3">Day Timeline</h2>
                </div>
                <div class="reports-day-track">
                    <div id="reports-timeline-bar" class="reports-day-track__bar" aria-label="Awake-window timeline"></div>
                    <div id="reports-timeline-now" class="reports-day-track__now" hidden></div>
                </div>
                <div class="reports-day-track__ticks">
                    <span id="reports-wake-label">8:00 AM</span>
                    <span id="reports-midpoint-label">3:30 PM</span>
                    <span id="reports-sleep-label">11:00 PM</span>
                </div>
            </section>

            <section class="card reports-list-card">
                <div class="reports-list-card__header">
                    <h2 class="text-h3">Timeline</h2>
                    <button id="reports-sort-button" type="button" class="btn btn-ghost btn-sm reports-sort-button" aria-label="Show newest entries first" title="Show newest entries first">
                        <span aria-hidden="true">↓</span>
                    </button>
                    <span id="reports-entry-count" class="badge badge-neutral">0 entries</span>
                </div>
                <div id="reports-timeline-list" class="reports-timeline-list"></div>
            </section>
        </div>
    </div>

    <div id="reports-entry-modal" class="reports-modal" hidden>
        <div class="reports-modal__backdrop" data-close-modal="true"></div>
        <div class="reports-modal__dialog card" role="dialog" aria-modal="true" aria-labelledby="reports-entry-modal-title">
            <div class="reports-modal__header">
                <div>
                    <h2 id="reports-entry-modal-title" class="text-h3">Add Entry</h2>
                    <p id="reports-entry-modal-date" class="text-muted">Selected day</p>
                </div>
                <button id="reports-entry-modal-close" type="button" class="btn btn-ghost btn-sm">Close</button>
            </div>

            <form id="reports-entry-form" class="reports-entry-form" novalidate>
                <div class="reports-entry-form__grid">
                    <div class="form-group">
                        <label class="form-label" for="reports-entry-activity">Activity</label>
                        <select id="reports-entry-activity" class="select">
                            <option value="">Choose activity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reports-entry-subtype">Subtype</label>
                        <select id="reports-entry-subtype" class="select" disabled aria-disabled="true">
                            <option value="">Choose subtype</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reports-entry-start">Start</label>
                        <input id="reports-entry-start" class="input" type="time" step="60" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reports-entry-end">End</label>
                        <input id="reports-entry-end" class="input" type="time" step="60" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reports-entry-notes">Notes</label>
                    <textarea id="reports-entry-notes" class="textarea" placeholder="What did you work on?"></textarea>
                </div>
                <p id="reports-entry-error" class="reports-entry-form__error" hidden></p>
                <div class="reports-entry-form__actions">
                    <button id="reports-entry-delete" type="button" class="btn btn-danger" hidden>Delete</button>
                    <button id="reports-entry-submit" type="submit" class="btn btn-primary" data-default-label="Save Entry">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
