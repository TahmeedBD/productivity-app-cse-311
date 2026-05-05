<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/guard.php';
require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/activity_subtypes/service.php';

$userId = (string) ($currentUser['id'] ?? '');
$errorMessage = '';

function activity_monogram(string $name): string
{
    $normalizedName = trim($name);

    if ($normalizedName === '') {
        return 'A';
    }

    $parts = preg_split('/\s+/', $normalizedName) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        $character = mb_substr($part, 0, 1);

        if ($character !== '') {
            $letters .= mb_strtoupper($character);
        }

        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    if ($letters !== '') {
        return mb_substr($letters, 0, 2);
    }

    return mb_strtoupper(mb_substr($normalizedName, 0, 1));
}

function subtype_dot_color(int $index): string
{
    $colors = [
        'var(--color-tag-01)',
        'var(--color-tag-03)',
        'var(--color-tag-05)',
        'var(--color-tag-07)',
        'var(--color-tag-11)',
        'var(--color-tag-13)',
    ];

    return $colors[$index % count($colors)];
}

function list_activity_subtype_counts(\PDO $pdo, array $activities): array
{
    if ($activities === []) {
        return [];
    }

    $activityIds = array_map(
        static fn(array $activity): int => (int) $activity['id'],
        $activities,
    );
    $placeholders = implode(', ', array_fill(0, count($activityIds), '?'));
    $statement = $pdo->prepare(
        "SELECT activity_id, COUNT(*) AS subtype_count
         FROM activity_subtypes
         WHERE activity_id IN ($placeholders)
         GROUP BY activity_id",
    );
    $statement->execute($activityIds);

    $counts = array_fill_keys($activityIds, 0);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['activity_id']] = (int) $row['subtype_count'];
    }

    return $counts;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? '');
        $redirectActivityId = !empty($_POST['activity_id'])
            ? (int) $_POST['activity_id']
            : null;

        if ($action === 'add_activity') {
            $createdActivity = create_activity(
                $pdo,
                $userId,
                (string) ($_POST['name'] ?? ''),
            );
            $redirectActivityId = (int) $createdActivity['id'];
        } elseif ($action === 'rename_activity') {
            $updatedActivity = update_activity(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                $userId,
                (string) ($_POST['new_name'] ?? ''),
            );
            $redirectActivityId = (int) $updatedActivity['id'];
        } elseif ($action === 'delete_activity') {
            delete_activity($pdo, (int) ($_POST['id'] ?? 0), $userId);
        } elseif ($action === 'add_subtype') {
            create_activity_subtype(
                $pdo,
                (int) ($_POST['activity_id'] ?? 0),
                $userId,
                (string) ($_POST['name'] ?? ''),
            );
        } elseif ($action === 'delete_subtype') {
            delete_activity_subtype(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                (int) ($_POST['activity_id'] ?? 0),
                $userId,
            );
        }

        $target = 'activities.php';

        if ($redirectActivityId !== null && $redirectActivityId > 0) {
            $target .= '?id=' . $redirectActivityId;
        }

        header('Location: ' . $target);
        exit();
    } catch (Throwable $throwable) {
        $errorMessage = $throwable->getMessage();
    }
}

$activities = list_activities($pdo, $userId);
$activitySubtypeCounts = list_activity_subtype_counts($pdo, $activities);
$selectedId = isset($_GET['id'])
    ? (int) $_GET['id']
    : (int) ($activities[0]['id'] ?? 0);
$selectedActivity = null;

foreach ($activities as $activity) {
    if ((int) $activity['id'] === $selectedId) {
        $selectedActivity = $activity;
        break;
    }
}

if ($selectedActivity === null && $activities !== []) {
    $selectedActivity = $activities[0];
    $selectedId = (int) $selectedActivity['id'];
}

$subtypes =
    $selectedActivity === null
        ? []
        : list_activity_subtypes($pdo, (int) $selectedActivity['id'], $userId);

$pageTitle = 'Activities';
$pageCSS = 'activities.css';
require_once 'header.php';
?>

<div class="activities-page">
    <section class="card card-featured activities-toolbar">
        <div class="activities-toolbar__copy">
            <h1 class="text-h1">Activities</h1>
            <p class="text-muted">Organize top-level categories and the subtypes you actually log against during the day.</p>
        </div>
        <button type="button" class="btn btn-primary" data-open-modal="activity-create-modal">New Activity</button>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars(
            $errorMessage,
        ) ?></div>
    <?php endif; ?>

    <div class="activities-layout">
        <section class="card activities-panel">
            <div class="activities-panel__header">
                <div>
                    <h2 class="text-h3">Categories</h2>
                    <p class="text-muted">Select a category to manage its subtypes.</p>
                </div>
            </div>

            <?php if ($activities === []): ?>
                <div class="activities-empty">
                    <div class="activities-empty__content">
                        <h3 class="text-h3">No activities yet</h3>
                        <p class="text-muted">Create your first activity to start grouping work and adding subtypes.</p>
                        <button type="button" class="btn btn-primary" data-open-modal="activity-create-modal">Create First Activity</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="activities-list">
                    <?php foreach ($activities as $activity): ?>
                        <?php $activityId = (int) $activity['id']; ?>
                        <article class="activities-item <?= $activityId ===
                        $selectedId
                            ? 'activities-item--active'
                            : '' ?>">
                            <a href="?id=<?= $activityId ?>" class="activities-item__link">
                                <span class="activities-item__icon"><?= htmlspecialchars(
                                    activity_monogram(
                                        (string) $activity['name'],
                                    ),
                                ) ?></span>
                                <span class="activities-item__body">
                                    <span class="activities-item__name"><?= htmlspecialchars(
                                        (string) $activity['name'],
                                    ) ?></span>
                                    <span class="activities-item__meta"><?= (int) ($activitySubtypeCounts[
                                        $activityId
                                    ] ??
                                        0) ?> <?= (int) ($activitySubtypeCounts[
     $activityId
 ] ?? 0) === 1
     ? 'subtype'
     : 'subtypes' ?></span>
                                </span>
                            </a>
                            <div class="activities-item__actions">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm activities-icon-button"
                                    data-rename-activity
                                    data-activity-id="<?= $activityId ?>"
                                    data-activity-name="<?= htmlspecialchars(
                                        (string) $activity['name'],
                                        ENT_QUOTES,
                                    ) ?>"
                                    aria-label="Rename <?= htmlspecialchars(
                                        (string) $activity['name'],
                                    ) ?>"
                                    title="Rename activity"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm activities-icon-button"
                                    data-delete-activity
                                    data-activity-id="<?= $activityId ?>"
                                    data-activity-name="<?= htmlspecialchars(
                                        (string) $activity['name'],
                                        ENT_QUOTES,
                                    ) ?>"
                                    aria-label="Delete <?= htmlspecialchars(
                                        (string) $activity['name'],
                                    ) ?>"
                                    title="Delete activity"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card activities-panel">
            <?php if ($selectedActivity === null): ?>
                <div class="activities-empty">
                    <div class="activities-empty__content">
                        <h2 class="text-h3">Select a category</h2>
                        <p class="text-muted">Choose an activity on the left to view and manage its subtypes.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="activities-panel__header">
                    <div>
                        <h2 class="text-h3"><?= htmlspecialchars(
                            (string) $selectedActivity['name'],
                        ) ?></h2>
                        <p class="text-muted">Manage the sub-categories you use for <?= htmlspecialchars(
                            (string) $selectedActivity['name'],
                        ) ?> work.</p>
                    </div>
                    <button type="button" class="btn btn-secondary" data-open-modal="subtype-create-modal">Add Subtype</button>
                </div>

                <?php if ($subtypes === []): ?>
                    <div class="activities-empty">
                        <div class="activities-empty__content">
                            <h3 class="text-h3">No subtypes yet</h3>
                            <p class="text-muted">Subtypes help break this activity into more specific work streams.</p>
                            <button type="button" class="btn btn-primary" data-open-modal="subtype-create-modal">Create First Subtype</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="activities-subtypes">
                        <?php foreach ($subtypes as $index => $subtype): ?>
                            <article class="activities-subtype">
                                <div class="activities-subtype__body">
                                    <span class="activities-subtype__dot" style="background: <?= subtype_dot_color(
                                        $index,
                                    ) ?>"></span>
                                    <span class="activities-subtype__name"><?= htmlspecialchars(
                                        (string) $subtype['name'],
                                    ) ?></span>
                                </div>
                                <div class="activities-subtype__actions">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm activities-icon-button"
                                        data-delete-subtype
                                        data-subtype-id="<?= (int) $subtype[
                                            'id'
                                        ] ?>"
                                        data-activity-id="<?= (int) $selectedActivity[
                                            'id'
                                        ] ?>"
                                        data-subtype-name="<?= htmlspecialchars(
                                            (string) $subtype['name'],
                                            ENT_QUOTES,
                                        ) ?>"
                                        aria-label="Delete <?= htmlspecialchars(
                                            (string) $subtype['name'],
                                        ) ?>"
                                        title="Delete subtype"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<div id="activity-create-modal" class="activities-modal" hidden>
    <div class="activities-modal__backdrop" data-close-modal></div>
    <div class="card activities-modal__dialog">
        <div class="activities-modal__header">
            <h2 class="text-h3">Create activity</h2>
            <p class="text-muted">Add a top-level category for the work you want to track.</p>
        </div>
        <form method="POST" class="activities-modal__form">
            <input type="hidden" name="action" value="add_activity">
            <div class="form-group">
                <label class="form-label" for="activity-create-name">Name</label>
                <input id="activity-create-name" class="input" type="text" name="name" maxlength="100" placeholder="Activity name" required>
            </div>
            <div class="activities-modal__actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Activity</button>
            </div>
        </form>
    </div>
</div>

<div id="subtype-create-modal" class="activities-modal" hidden>
    <div class="activities-modal__backdrop" data-close-modal></div>
    <div class="card activities-modal__dialog">
        <div class="activities-modal__header">
            <h2 class="text-h3">Add subtype</h2>
            <p class="text-muted">Create a more specific label inside this activity.</p>
        </div>
        <form method="POST" class="activities-modal__form">
            <input type="hidden" name="action" value="add_subtype">
            <input type="hidden" name="activity_id" value="<?= (int) ($selectedActivity[
                'id'
            ] ?? 0) ?>">
            <div class="form-group">
                <label class="form-label" for="subtype-create-name">Name</label>
                <input id="subtype-create-name" class="input" type="text" name="name" maxlength="100" placeholder="Subtype name" required>
            </div>
            <div class="activities-modal__actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Subtype</button>
            </div>
        </form>
    </div>
</div>

<div id="activity-rename-modal" class="activities-modal" hidden>
    <div class="activities-modal__backdrop" data-close-modal></div>
    <div class="card activities-modal__dialog">
        <div class="activities-modal__header">
            <h2 class="text-h3">Rename activity</h2>
            <p class="text-muted">Update the visible name for this category.</p>
        </div>
        <form method="POST" class="activities-modal__form">
            <input type="hidden" name="action" value="rename_activity">
            <input type="hidden" name="id" id="rename-activity-id">
            <div class="form-group">
                <label class="form-label" for="rename-activity-name">Name</label>
                <input id="rename-activity-name" class="input" type="text" name="new_name" maxlength="100" required>
            </div>
            <div class="activities-modal__actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="delete-confirmation-modal" class="activities-modal" hidden>
    <div class="activities-modal__backdrop" data-close-modal></div>
    <div class="card activities-modal__dialog">
        <div class="activities-modal__header">
            <h2 class="text-h3">Confirm deletion</h2>
            <p id="delete-confirmation-copy" class="activities-modal__danger-copy">This action cannot be undone.</p>
        </div>
        <form method="POST" class="activities-modal__form">
            <input type="hidden" name="action" id="delete-action">
            <input type="hidden" name="id" id="delete-id">
            <input type="hidden" name="activity_id" id="delete-activity-id" value="">
            <div class="activities-modal__actions">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
const activitiesModals = Array.from(document.querySelectorAll('.activities-modal'));

function openActivitiesModal(modalId) {
  const modal = document.getElementById(modalId);

  if (!(modal instanceof HTMLElement)) {
    return;
  }

  modal.hidden = false;
  const focusTarget = modal.querySelector('input, button, textarea, select');
  if (focusTarget instanceof HTMLElement) {
    focusTarget.focus();
  }
}

function closeActivitiesModal(modal) {
  modal.hidden = true;
}

document.querySelectorAll('[data-open-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    openActivitiesModal(button.dataset.openModal);
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    const modal = button.closest('.activities-modal');
    if (modal instanceof HTMLElement) {
      closeActivitiesModal(modal);
    }
  });
});

document.querySelectorAll('[data-rename-activity]').forEach((button) => {
  button.addEventListener('click', () => {
    const idField = document.getElementById('rename-activity-id');
    const nameField = document.getElementById('rename-activity-name');

    if (!(idField instanceof HTMLInputElement) || !(nameField instanceof HTMLInputElement)) {
      return;
    }

    idField.value = button.dataset.activityId || '';
    nameField.value = button.dataset.activityName || '';
    openActivitiesModal('activity-rename-modal');
  });
});

document.querySelectorAll('[data-delete-activity]').forEach((button) => {
  button.addEventListener('click', () => {
    const actionField = document.getElementById('delete-action');
    const idField = document.getElementById('delete-id');
    const activityIdField = document.getElementById('delete-activity-id');
    const copy = document.getElementById('delete-confirmation-copy');

    if (!(actionField instanceof HTMLInputElement) || !(idField instanceof HTMLInputElement) || !(activityIdField instanceof HTMLInputElement) || !(copy instanceof HTMLElement)) {
      return;
    }

    actionField.value = 'delete_activity';
    idField.value = button.dataset.activityId || '';
    activityIdField.value = '';
    copy.textContent = `Delete ${button.dataset.activityName || 'this activity'}? This cannot be undone.`;
    openActivitiesModal('delete-confirmation-modal');
  });
});

document.querySelectorAll('[data-delete-subtype]').forEach((button) => {
  button.addEventListener('click', () => {
    const actionField = document.getElementById('delete-action');
    const idField = document.getElementById('delete-id');
    const activityIdField = document.getElementById('delete-activity-id');
    const copy = document.getElementById('delete-confirmation-copy');

    if (!(actionField instanceof HTMLInputElement) || !(idField instanceof HTMLInputElement) || !(activityIdField instanceof HTMLInputElement) || !(copy instanceof HTMLElement)) {
      return;
    }

    actionField.value = 'delete_subtype';
    idField.value = button.dataset.subtypeId || '';
    activityIdField.value = button.dataset.activityId || '';
    copy.textContent = `Delete ${button.dataset.subtypeName || 'this subtype'}? This cannot be undone.`;
    openActivitiesModal('delete-confirmation-modal');
  });
});

window.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') {
    return;
  }

  activitiesModals.forEach((modal) => {
    if (!modal.hidden) {
      closeActivitiesModal(modal);
    }
  });
});
</script>

<?php require_once 'footer.php'; ?>
