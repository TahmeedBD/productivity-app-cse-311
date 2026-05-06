<?php
require_once __DIR__ . '/../src/auth/guard.php';

$pageTitle = 'Dashboard';
$pageCSS = 'dashboard.css';
require_once 'header.php';
?>

<div id="dashboard-app" class="dashboard-page">
	<div id="dashboard-message" class="alert alert-danger" hidden></div>

	<header class="page-heading dashboard-heading">
		<h1 class="text-h1">Dashboard</h1>
		<p class="text-muted">A quick look at today, without repeating the deeper pages.</p>
	</header>

	<section class="dashboard-hero card card-featured">
		<div class="dashboard-hero__summary">
			<div class="section-title dashboard-kicker">Today</div>
			<h2 id="dashboard-date-label" class="text-h2">Loading...</h2>
			<p id="dashboard-today-summary" class="text-muted">Loading your schedule and activity summary for today.</p>

			<div class="dashboard-pills" aria-label="Today's schedule overview">
				<span id="dashboard-wake-pill" class="badge badge-neutral">Wake --:--</span>
				<span id="dashboard-sleep-pill" class="badge badge-neutral">Sleep --:--</span>
			</div>
		</div>

		<form id="dashboard-schedule-form" class="dashboard-schedule-form" novalidate>
			<div class="dashboard-schedule-form__grid">
				<div class="form-group">
					<label class="form-label" for="dashboard-wake-time">Wake Time</label>
					<input id="dashboard-wake-time" class="input" type="time" step="60" aria-describedby="dashboard-wake-hint">
					<span id="dashboard-wake-hint" class="dashboard-field-hint">Applies from tomorrow forward.</span>
				</div>
				<div class="form-group">
					<label class="form-label" for="dashboard-sleep-time">Sleep Time</label>
					<input id="dashboard-sleep-time" class="input" type="time" step="60" aria-describedby="dashboard-sleep-hint">
					<span id="dashboard-sleep-hint" class="dashboard-field-hint">Updates tonight and future days.</span>
				</div>
			</div>

			<p class="dashboard-schedule-note text-muted">
				Saving here keeps today’s wake time as already logged, updates tonight’s sleep time, and carries the selected wake and sleep times into future days.
			</p>
			<p id="dashboard-schedule-error" class="form-error" hidden></p>

			<div class="dashboard-schedule-form__actions">
				<button id="dashboard-schedule-submit" type="submit" class="btn btn-primary" data-default-label="Save schedule">
					Save schedule
				</button>
			</div>
		</form>
	</section>

	<section class="dashboard-stats" aria-label="Today's basics">
		<article class="stat-card stat-card--primary">
			<div class="stat-card__label">Tracked Today</div>
			<div id="dashboard-tracked-time" class="stat-card__value">0h 00m</div>
			<div id="dashboard-tracked-time-sub" class="stat-card__sub">No entries yet</div>
		</article>

		<article class="stat-card stat-card--accent">
			<div class="stat-card__label">Current Focus</div>
			<div id="dashboard-current-focus" class="stat-card__value dashboard-stat-text">Idle</div>
			<div id="dashboard-current-focus-sub" class="stat-card__sub">Nothing running right now</div>
		</article>

		<article class="stat-card">
			<div class="stat-card__label">Activities</div>
			<div id="dashboard-activity-overview" class="stat-card__value">0/0</div>
			<div id="dashboard-activity-sub" class="stat-card__sub">Active today</div>
		</article>
	</section>

	<section class="page-section">
		<div class="page-section__header">
			<h2 class="text-h2">Shortcuts</h2>
		</div>

		<div class="dashboard-shortcuts">
			<a href="/time_logger.php" class="card card-sm dashboard-shortcut">
				<strong class="dashboard-shortcut__title">Time Log</strong>
				<span class="dashboard-shortcut__copy">Start or review entries for today.</span>
			</a>
			<a href="/reports.php" class="card card-sm dashboard-shortcut">
				<strong class="dashboard-shortcut__title">Overview</strong>
				<span class="dashboard-shortcut__copy">Inspect today’s day-overview timeline.</span>
			</a>
			<a href="/reports_dashboard.php" class="card card-sm dashboard-shortcut">
				<strong class="dashboard-shortcut__title">Reports</strong>
				<span class="dashboard-shortcut__copy">Review the higher-level range analytics page.</span>
			</a>
			<a href="/activities.php" class="card card-sm dashboard-shortcut">
				<strong class="dashboard-shortcut__title">Activities</strong>
				<span class="dashboard-shortcut__copy">Manage the activity list you track against.</span>
			</a>
		</div>
	</section>
</div>

<?php require_once 'footer.php'; ?>
