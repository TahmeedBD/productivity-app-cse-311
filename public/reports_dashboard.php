<?php
require_once __DIR__ . '/../src/auth/guard.php';

$pageTitle = 'Reports';
$pageCSS = 'reports_dashboard.css';

require_once 'header.php';
?>

<div id="reports-dashboard" class="reports-dashboard-page">
    <div id="reports-dashboard-message" class="alert alert-danger" hidden></div>

    <section class="reports-dashboard-toolbar">
        <div class="reports-dashboard-toolbar__heading">
            <h1 class="text-h1">Reports</h1>
        </div>

        <form id="reports-dashboard-filter-form" class="reports-dashboard-filter-card" novalidate>
            <div class="reports-dashboard-filter-group">
                <label class="form-label" for="reports-dashboard-from-input">From</label>
                <input id="reports-dashboard-from-input" class="input reports-dashboard-filter-input" type="date" aria-label="Start report date">
            </div>
            <div class="reports-dashboard-filter-group">
                <label class="form-label" for="reports-dashboard-to-input">To</label>
                <input id="reports-dashboard-to-input" class="input reports-dashboard-filter-input" type="date" aria-label="End report date">
            </div>
            <button id="reports-dashboard-filter-button" type="submit" class="btn btn-ghost reports-dashboard-filter-button" data-default-label="Filter">
                <span class="reports-dashboard-button-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                        <path d="M4 6h16v2l-6 6v4l-4 2v-6L4 8V6Z" fill="currentColor"></path>
                    </svg>
                </span>
                <span>Filter</span>
            </button>
        </form>
    </section>

    <section class="reports-dashboard-summary-grid" aria-label="Report highlights">
        <article class="reports-dashboard-summary-card reports-dashboard-summary-card--primary">
            <div class="reports-dashboard-summary-card__art" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path d="M12 1.75a10.25 10.25 0 1 0 10.25 10.25A10.26 10.26 0 0 0 12 1.75Zm0 18.5A8.25 8.25 0 1 1 20.25 12 8.26 8.26 0 0 1 12 20.25Zm.75-13.5h-1.5v5.56l4.53 2.72.78-1.28-3.81-2.29Z" fill="currentColor"></path>
                </svg>
            </div>
            <div class="reports-dashboard-summary-card__label">Total Time</div>
            <div id="reports-dashboard-total-time-value" class="reports-dashboard-summary-card__value">0h 00m</div>
            <div id="reports-dashboard-total-time-trend" class="reports-dashboard-trend" data-state="muted">No prior period</div>
        </article>

        <article class="reports-dashboard-summary-card reports-dashboard-summary-card--accent">
            <div class="reports-dashboard-summary-card__art" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path d="M19 6h-1V4a2 2 0 0 0-2-2h-3v8h2V8h4ZM11 2H8a2 2 0 0 0-2 2v2H5a2 2 0 0 0-2 2v3h8Zm2 20h3a2 2 0 0 0 2-2v-2h1a2 2 0 0 0 2-2v-3h-8Zm-2 0v-8H3v3a2 2 0 0 0 2 2h1v2a2 2 0 0 0 2 2Z" fill="currentColor"></path>
                </svg>
            </div>
            <div class="reports-dashboard-summary-card__label">Most Active Activity</div>
            <div id="reports-dashboard-most-active-value" class="reports-dashboard-summary-card__value reports-dashboard-summary-card__value--accent">No activity</div>
            <div id="reports-dashboard-most-active-sub" class="reports-dashboard-summary-card__sub">No time logged in this range</div>
        </article>

        <article class="reports-dashboard-summary-card reports-dashboard-summary-card--tertiary">
            <div class="reports-dashboard-summary-card__art" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path d="m9.55 17.8-4.9-4.9 1.4-1.4 3.5 3.48 8.4-8.39 1.4 1.42Z" fill="currentColor"></path>
                </svg>
            </div>
            <div class="reports-dashboard-summary-card__label">Consistency Rate</div>
            <div id="reports-dashboard-completion-rate-value" class="reports-dashboard-summary-card__value reports-dashboard-summary-card__value--tertiary">0%</div>
            <div id="reports-dashboard-completion-rate-trend" class="reports-dashboard-trend" data-state="muted">No activities configured</div>
        </article>
    </section>

    <div class="reports-dashboard-content-grid">
        <section class="card reports-dashboard-panel" aria-labelledby="reports-dashboard-activity-title">
            <div class="reports-dashboard-panel__header">
                <h2 id="reports-dashboard-activity-title" class="text-h2 reports-dashboard-panel__title">
                    <span class="reports-dashboard-panel__icon reports-dashboard-panel__icon--primary" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M4 19h16v2H4Zm1-3h3V9H5Zm5 0h4V5h-4Zm6 0h3v-7h-3Z" fill="currentColor"></path>
                        </svg>
                    </span>
                    <span>Time by Activity</span>
                </h2>
            </div>
            <div id="reports-dashboard-activity-bars" class="reports-dashboard-activity-bars"></div>
        </section>

        <section class="card reports-dashboard-panel" aria-labelledby="reports-dashboard-habits-title">
            <div class="reports-dashboard-panel__header">
                <h2 id="reports-dashboard-habits-title" class="text-h2 reports-dashboard-panel__title">
                    <span class="reports-dashboard-panel__icon reports-dashboard-panel__icon--accent" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="m9.55 17.8-4.9-4.9 1.4-1.4 3.5 3.48 8.4-8.39 1.4 1.42Z" fill="currentColor"></path>
                        </svg>
                    </span>
                    <span>Activity Consistency</span>
                </h2>
            </div>
            <div class="table-wrap reports-dashboard-table-wrap">
                <table class="table reports-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Activity</th>
                            <th scope="col" class="reports-dashboard-table__numeric">Active</th>
                            <th scope="col" class="reports-dashboard-table__numeric">Rate</th>
                        </tr>
                    </thead>
                    <tbody id="reports-dashboard-habits-body"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require_once 'footer.php'; ?>
