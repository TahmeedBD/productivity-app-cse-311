<?php
require_once __DIR__ . '/../src/auth/guard.php';
require_once __DIR__ . '/../src/activities/service.php';
require_once __DIR__ . '/../src/activity_subtypes/service.php';

$userId = (string) ($currentUser['id'] ?? '');
$error_message = '';

// --- Auto Emoji Detection ---
function getDetectedEmoji($name)
{
    $name = strtolower(trim($name));
    $map = [
        'code' => '💻',
        'dev' => '💻',
        'program' => '👨‍💻',
        'exercise' => '🏋️',
        'gym' => '💪',
        'workout' => '🏃',
        'read' => '📖',
        'book' => '📚',
        'study' => '🎓',
        'eat' => '🍔',
        'food' => '🍕',
        'lunch' => '🍱',
        'meeting' => '💼',
        'work' => '👔',
        'sleep' => '😴',
        'game' => '🎮',
        'music' => '🎵',
        'movie' => '🎬',
        'content' => '🎨',
        'design' => '🖌️',
    ];
    foreach ($map as $key => $emoji) {
        if (str_contains($name, $key)) {
            return $emoji;
        }
    }
    return '📁';
}

function getDotColor($index)
{
    $colors = [
        '#2ecc71',
        '#3498db',
        '#f39c12',
        '#e74c3c',
        '#9b59b6',
        '#1abc9c',
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

// --- Action Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
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
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$pageTitle = 'Activities';
require_once 'header.php';

$activities = list_activities($pdo, $userId);
$activitySubtypeCounts = list_activity_subtype_counts($pdo, $activities);
$selectedId = isset($_GET['id'])
    ? (int) $_GET['id']
    : $activities[0]['id'] ?? null;

$subtypes = [];
$selectedName = '';
if ($selectedId) {
    $subtypes = list_activity_subtypes($pdo, (int) $selectedId, $userId);
    foreach ($activities as $a) {
        if ($a['id'] == $selectedId) {
            $selectedName = $a['name'];
        }
    }
}
?>

<style>
    .activities-page { padding: 40px 0; color: #fff; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
    .btn-new-act { background: #ffbd8a; color: #2c1a10; border: none; padding: 10px 22px; border-radius: 8px; font-weight: bold; cursor: pointer; }
    
    .main-grid { display: grid; grid-template-columns: 380px 1fr; gap: 40px; }
    
    /* Categories Column */
    .cat-card-wrapper { position: relative; margin-bottom: 15px; }
    .cat-card { 
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        padding: 20px; padding-right: 80px; /* Action buttons er jonno jayga */
        border-radius: 12px; display: flex; align-items: center; gap: 15px; 
        text-decoration: none; color: white; transition: 0.3s;
    }
    .cat-card.active { border-color: #ffbd8a; background: rgba(255,189,138,0.05); }
    .cat-icon { background: #3d2b1f; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 22px; color: #ffbd8a; }
    
    /* Visible Actions (Using Raw SVGs) */
    .cat-actions { 
        position: absolute; right: 15px; top: 50%; transform: translateY(-50%); 
        display: flex; gap: 10px; z-index: 10;
    }
    .action-btn { 
        background: none; border: none; color: #888; /* 100% Visible Grey */
        cursor: pointer; padding: 5px; transition: 0.2s; display: flex; align-items: center; justify-content: center;
    }
    .action-btn:hover { color: #ffbd8a; }
    .action-btn.delete-btn:hover { color: #e74c3c; }

    /* Subtypes Panel */
    .subtype-container { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 40px; min-height: 500px; }
    .subtype-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .subtype-header h2 { margin: 0; font-size: 24px; color: #ffbd8a; }
    .subtype-desc { color: #888; font-size: 14px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 20px; }
    
    .st-row { background: rgba(255,255,255,0.03); padding: 18px 25px; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    .st-dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 15px; }

    /* Modals */
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); align-items: center; justify-content: center; }
    .modal-content { background: #1a1a1a; padding: 30px; border: 1px solid #333; width: 380px; border-radius: 15px; position: relative; margin: 10% auto; }
    .modal input { width: 100%; padding: 12px; margin: 15px 0; background: #000; border: 1px solid #444; color: white; border-radius: 8px; box-sizing: border-box; }
    .btn-danger { background: #e74c3c; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; }
</style>

<div class="activities-page">
    <div class="page-header">
        <h1 style="margin:0">Activities</h1>
        <button class="btn-new-act" onclick="openM('actM')">+ New Activity</button>
    </div>

    <?php if ($error_message): ?>
        <div style="background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #ff9f93; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="main-grid">
        <!-- Categories List -->
        <div>
            <span style="color: #ffbd8a; font-weight: bold; margin-bottom: 20px; display: block;">Categories</span>
            <?php foreach ($activities as $act): ?>
                <div class="cat-card-wrapper">
                    <a href="?id=<?= $act[
                        'id'
                    ] ?>" class="cat-card <?= $selectedId == $act['id']
    ? 'active'
    : '' ?>">
                        <div class="cat-icon"><?= getDetectedEmoji(
                            $act['name'],
                        ) ?></div>
                        <div class="cat-info">
                            <h4 style="margin:0"><?= htmlspecialchars(
                                $act['name'],
                            ) ?></h4>
                            <span style="font-size: 13px; color: #666;"><?= (int) ($activitySubtypeCounts[
                                (int) $act['id']
                            ] ?? 0) ?> Subtypes</span>
                        </div>
                    </a>
                    <!-- Modern Edit & Delete Buttons (SVGs) -->
                    <div class="cat-actions">
                        <button type="button" class="action-btn" onclick="openRenameModal(<?= $act[
                            'id'
                        ] ?>, '<?= htmlspecialchars(
    $act['name'],
    ENT_QUOTES,
) ?>')">
                            <!-- Pencil SVG -->
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        </button>
                        <button type="button" class="action-btn delete-btn" onclick="openDeleteModal('activity', <?= $act[
                            'id'
                        ] ?>)">
                            <!-- Trash SVG -->
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Subtypes Panel -->
        <div class="subtype-container">
            <?php if ($selectedId): ?>
                <div class="subtype-header">
                    <h2><?= getDetectedEmoji(
                        $selectedName,
                    ) ?> <?= htmlspecialchars($selectedName) ?> Subtypes</h2>
                    <button style="background:transparent; border:1px solid #2ecc71; color:#2ecc71; padding:6px 15px; border-radius:6px; cursor:pointer;" onclick="openM('subM')">+ Add Subtype</button>
                </div>
                <p class="subtype-desc">Manage sub-categories for <?= htmlspecialchars(
                    $selectedName,
                ) ?> tasks.</p>
                
                <div class="subtype-list">
                    <?php foreach ($subtypes as $index => $st): ?>
                        <div class="st-row">
                            <div style="display:flex; align-items:center;">
                                <div class="st-dot" style="background: <?= getDotColor(
                                    $index,
                                ) ?>;"></div>
                                <span><?= htmlspecialchars(
                                    $st['name'],
                                ) ?></span>
                            </div>
                            <button type="button" class="action-btn delete-btn" onclick="openDeleteModal('subtype', <?= $st[
                                'id'
                            ] ?>, <?= $selectedId ?>)">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: #444; margin-top: 150px;">Select a category to manage subtypes.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ================= MODERN MODALS ================= -->

<!-- New Activity Modal -->
<div id="actM" class="modal">
    <div class="modal-content">
        <h3 style="margin:0">New Activity</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_activity">
            <input type="text" name="name" placeholder="Category Name" required autofocus>
            <button type="submit" class="btn-new-act" style="width:100%">Create Activity</button>
            <button type="button" onclick="closeM('actM')" style="width:100%; background:none; border:none; color:#888; margin-top:15px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<!-- Add Subtype Modal -->
<div id="subM" class="modal">
    <div class="modal-content">
        <h3 style="margin:0">Add Subtype</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_subtype">
            <input type="hidden" name="activity_id" value="<?= $selectedId ?>">
            <input type="text" name="name" placeholder="Subtype Name" required autofocus>
            <button type="submit" class="btn-new-act" style="width:100%">Add Subtype</button>
            <button type="button" onclick="closeM('subM')" style="width:100%; background:none; border:none; color:#888; margin-top:15px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<!-- Modern RENAME Modal -->
<div id="renameM" class="modal">
    <div class="modal-content">
        <h3 style="margin:0">Rename Category</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename_activity">
            <input type="hidden" name="id" id="rename_act_id">
            <input type="text" name="new_name" id="rename_act_name" required autofocus>
            <button type="submit" class="btn-new-act" style="width:100%">Save Changes</button>
            <button type="button" onclick="closeM('renameM')" style="width:100%; background:none; border:none; color:#888; margin-top:15px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<!-- Modern DELETE Confirmation Modal -->
<div id="deleteM" class="modal">
    <div class="modal-content">
        <h3 style="margin:0; color: #e74c3c;">Confirm Deletion</h3>
        <p style="color: #bbb; font-size: 14px; margin-top: 15px; line-height: 1.5;">Are you sure you want to delete this? All related data will be permanently removed.</p>
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" id="delete_action">
            <input type="hidden" name="id" id="delete_id">
            <input type="hidden" name="activity_id" id="delete_activity_id">
            <button type="submit" class="btn-danger">Yes, Delete</button>
            <button type="button" onclick="closeM('deleteM')" style="width:100%; background:none; border:none; color:#888; margin-top:15px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<script>
    function openM(id) { document.getElementById(id).style.display = 'flex'; }
    function closeM(id) { document.getElementById(id).style.display = 'none'; }
    
    // Open Rename Modal instead of prompt
    function openRenameModal(id, oldName) {
        document.getElementById('rename_act_id').value = id;
        document.getElementById('rename_act_name').value = oldName;
        openM('renameM');
    }

    // Open Delete Modal instead of confirm
    function openDeleteModal(type, id, activityId = '') {
        if (type === 'activity') {
            document.getElementById('delete_action').value = 'delete_activity';
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_activity_id').value = '';
        } else {
            document.getElementById('delete_action').value = 'delete_subtype';
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_activity_id').value = activityId;
        }
        openM('deleteM');
    }

    // Click outside to close modals
    window.onclick = function(e) { 
        if(e.target.classList.contains('modal')) e.target.style.display = 'none'; 
    }
</script>

<?php require_once 'footer.php';
?>
