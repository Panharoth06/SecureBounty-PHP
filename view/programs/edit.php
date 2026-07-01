<?php
/**
 * Program Edit Form View
 *
 * Displays a pre-filled form for Program Owners to edit an existing program.
 * Includes publish/close action buttons, reward policy list, asset
 * management, and technology tag management.
 *
 * Variables available from ProgramController::edit():
 *   $title          - Page title
 *   $activePage     - Active navigation item ('programs')
 *   $program        - Program data array (id, owner_id, title, description, scope, status, logo_path)
 *   $rewardPolicies - Array of reward policy rows for this program
 *   $assets         - Array of program asset rows (id, name, type)
 *   $assetTypes     - Array of valid Asset_Type values
 *   $tags           - Array of technology tag rows (id, name) associated with the program
 *   $tagCount       - Current number of tags on the program
 *   $tagLimit       - Maximum tags allowed per program
 *   $csrfToken      - CSRF token for forms
 *   $errors         - Validation errors for the main program form (nullable)
 *   $oldInput       - Previous form input for repopulation (nullable)
 *   $assetErrors    - Validation errors from last asset operation (nullable)
 *   $assetOldInput  - Old asset form input (nullable)
 *   $tagErrors      - Validation errors from last tag operation (nullable)
 *   $tagOldInput    - Old tag form input (nullable)
 *
 * @see Requirement 1 — Program asset management UI
 * @see Requirement 2 — Technology stack tagging UI
 * @see Requirement 7 — Program logo upload
 */

$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$assets = $assets ?? [];
$assetTypes = $assetTypes ?? [];
$tags = $tags ?? [];
$tagCount = $tagCount ?? count($tags);
$tagLimit = $tagLimit ?? 20;
$assetErrors = $assetErrors ?? [];
$assetOldInput = $assetOldInput ?? [];
$tagErrors = $tagErrors ?? [];
$tagOldInput = $tagOldInput ?? [];
$status = $program['status'] ?? 'draft';
$logoPath = $program['logo_path'] ?? null;
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=program-detail&amp;id=<?= (int) $program['id'] ?>"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Program
    </a>
</nav>

<!-- Page Header -->
<div
    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-lg); flex-wrap:wrap; gap:var(--space-md);">
    <h1 style="font-size:var(--text-heading); font-weight:600; margin:0;">Edit Program</h1>
    <div style="display:flex; gap:8px;">
        <?php if ($status === 'draft'): ?>
            <form method="POST" action="index.php?page=program-publish&amp;id=<?= (int) $program['id'] ?>"
                style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-primary">
                    <i data-lucide="rocket" style="width:14px; height:14px;"></i> Publish
                </button>
            </form>
        <?php endif; ?>
        <?php if ($status === 'active'): ?>
            <form method="POST" action="index.php?page=program-close&amp;id=<?= (int) $program['id'] ?>"
                style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-destructive">
                    <i data-lucide="x-circle" style="width:14px; height:14px;"></i> Close Program
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Validation Errors (main form) -->
<?php include __DIR__ . '/../components/form-errors.php'; ?>

<!-- Edit Form -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-lg);">
    <form method="POST" action="index.php?page=process-program-edit&amp;id=<?= (int) $program['id'] ?>"
        enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Title -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="title" class="form-label">Program Title</label>
            <input type="text" id="title" name="title"
                class="form-input <?= isset($errors['title']) ? 'is-error' : '' ?>"
                placeholder="e.g., Web Application Security Program"
                value="<?= htmlspecialchars($oldInput['title'] ?? $program['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required>
        </div>

        <!-- Description -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description"
                class="form-textarea <?= isset($errors['description']) ? 'is-error' : '' ?>"
                placeholder="Describe your program's goals, rules, and what researchers should know..." rows="6"
                required><?= htmlspecialchars($oldInput['description'] ?? $program['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- Scope -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="scope" class="form-label">Scope</label>
            <textarea id="scope" name="scope" class="form-textarea <?= isset($errors['scope']) ? 'is-error' : '' ?>"
                placeholder="Define in-scope assets (domains, APIs, mobile apps, etc.)..." rows="4"
                required><?= htmlspecialchars($oldInput['scope'] ?? $program['scope'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <!-- Logo -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="logo" class="form-label">Program Logo</label>
            <?php if (!empty($logoPath)): ?>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                    <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Current logo"
                        style="width:64px; height:64px; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border);">
                    <span style="color:var(--muted-foreground); font-size:var(--text-caption);">
                        Current logo. Uploading a new image replaces this one.
                    </span>
                </div>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" class="form-input" accept="image/png,image/jpeg,image/gif">
            <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                PNG, JPG, or GIF. Max 2 MB. Will be resized to 200x200 pixels.
            </p>
        </div>

        <!-- Submit -->
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <a href="index.php?page=program-detail&amp;id=<?= (int) $program['id'] ?>" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i data-lucide="save" style="width:14px; height:14px;"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<!-- Reward Policies Section -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-lg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-md);">
        <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0;">Reward Policies</h2>
        <a href="index.php?page=reward-policy-create&amp;program_id=<?= (int) $program['id'] ?>" class="btn-secondary"
            style="padding:6px 12px; font-size:var(--text-caption);">
            <i data-lucide="plus" style="width:14px; height:14px;"></i> Add Policy
        </a>
    </div>

    <?php if (empty($rewardPolicies)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
            No reward policies defined yet. Add at least one policy before publishing.
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Min Reward</th>
                    <th>Max Reward</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rewardPolicies as $policy): ?>
                    <tr>
                        <td>
                            <?php
                            $severity = $policy['severity'] ?? 'informational';
                            $sevClass = 'badge-' . $severity;
                            ?>
                            <span class="<?= htmlspecialchars($sevClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucfirst($severity), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>$
                            <?= htmlspecialchars(number_format((float) ($policy['min_reward'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>$
                            <?= htmlspecialchars(number_format((float) ($policy['max_reward'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="index.php?page=reward-policy-edit&amp;id=<?= (int) $policy['id'] ?>&amp;program_id=<?= (int) $program['id'] ?>"
                                class="btn-secondary" style="padding:4px 10px; font-size:var(--text-caption);">
                                Edit
                            </a>
                            <form method="POST"
                                action="index.php?page=reward-policy-delete&amp;id=<?= (int) $policy['id'] ?>&amp;program_id=<?= (int) $program['id'] ?>"
                                style="display:inline; margin-left:4px;">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-danger-soft"
                                    style="padding:4px 10px; font-size:var(--text-caption);">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Assets Section -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-lg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-md);">
        <div>
            <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0;">In-Scope Assets</h2>
            <p style="margin:4px 0 0; color:var(--muted-foreground); font-size:var(--text-caption);">
                <?= count($assets) ?>
                asset<?= count($assets) === 1 ? '' : 's' ?> defined
            </p>
        </div>
    </div>

    <?php if (!empty($assetErrors)): ?>
        <div class="form-errors-box" role="alert" aria-live="polite"
            style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-rejected-text); font-size:var(--text-body);">
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($assetErrors as $msg): ?>
                    <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add Asset Form -->
    <form method="POST" action="index.php?page=asset-add"
        style="display:grid; grid-template-columns:2fr 1fr auto; gap:8px; align-items:end; margin-bottom:var(--space-md);">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">

        <div>
            <label for="asset_name" class="form-label">Name</label>
            <input type="text" id="asset_name" name="name" class="form-input"
                placeholder="e.g., api.example.com or *.example.com"
                value="<?= htmlspecialchars($assetOldInput['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255"
                required>
        </div>

        <div>
            <label for="asset_type" class="form-label">Type</label>
            <select id="asset_type" name="type" class="form-select" required>
                <?php foreach ($assetTypes as $t): ?>
                    <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" <?= (($assetOldInput['type'] ?? '') === $t) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-primary" style="height:fit-content;">
            <i data-lucide="plus" style="width:14px; height:14px;"></i> Add Asset
        </button>
    </form>

    <?php if (empty($assets)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
            No assets defined yet. Add at least one to clarify scope for researchers.
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?= htmlspecialchars($asset['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge-informational">
                                <?= htmlspecialchars($asset['type'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <form method="POST" action="index.php?page=asset-delete" style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
                                <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                                <button type="submit" class="btn-danger-soft"
                                    style="padding:4px 10px; font-size:var(--text-caption);"
                                    onclick="return confirm('Remove this asset?');">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Tags Section -->
<div class="card-surface" style="padding:var(--space-lg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-md);">
        <div>
            <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0;">Technology Tags</h2>
            <p style="margin:4px 0 0; color:var(--muted-foreground); font-size:var(--text-caption);">
                <?= (int) $tagCount ?>
                of <?= (int) $tagLimit ?> tags used
            </p>
        </div>
    </div>

    <?php if (!empty($tagErrors)): ?>
        <div class="form-errors-box" role="alert" aria-live="polite"
            style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-rejected-text); font-size:var(--text-body);">
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($tagErrors as $msg): ?>
                    <li><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add Tag Form -->
    <?php if ($tagCount < $tagLimit): ?>
        <form method="POST" action="index.php?page=tag-add"
            style="display:flex; gap:8px; align-items:end; margin-bottom:var(--space-md);">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">

            <div style="flex:1;">
                <label for="tag_name" class="form-label">Add Tag</label>
                <input type="text" id="tag_name" name="name" class="form-input" placeholder="e.g., PHP, React, AWS"
                    list="tag-suggestions" maxlength="50" pattern="[A-Za-z0-9.+\-]+" autocomplete="off"
                    value="<?= htmlspecialchars($tagOldInput['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                <datalist id="tag-suggestions"></datalist>
                <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                    Letters, numbers, hyphens, periods, plus signs only. Tags are case-insensitive.
                </p>
            </div>

            <button type="submit" class="btn-primary" style="height:fit-content;">
                <i data-lucide="plus" style="width:14px; height:14px;"></i> Add
            </button>
        </form>
    <?php else: ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin-bottom:var(--space-md);">
            You have reached the maximum of <?= (int) $tagLimit ?> tags. Remove a tag to add a different one.
        </p>
    <?php endif; ?>

    <?php if (empty($tags)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
            No tags yet. Add tags so researchers can find your program by technology.
        </p>
    <?php else: ?>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php foreach ($tags as $tag): ?>
                <form method="POST" action="index.php?page=tag-remove" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                    <input type="hidden" name="tag_id" value="<?= (int) $tag['id'] ?>">
                    <button type="submit"
                        style="display:inline-flex; align-items:center; gap:6px; background:var(--muted); color:var(--foreground); border:1px solid var(--border); border-radius:999px; padding:4px 10px; font-size:var(--text-caption); cursor:pointer;"
                        title="Remove tag">
                        <?= htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') ?>
                        <i data-lucide="x" style="width:12px; height:12px;"></i>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Tag autocomplete via tag-search JSON endpoint -->
<script>
    (function () {
        const input = document.getElementById('tag_name');
        const list = document.getElementById('tag-suggestions');
        if (!input || !list) return;

        let timer = null;
        let lastQuery = '';

        input.addEventListener('input', function () {
            const q = input.value.trim();
            if (q.length < 1 || q === lastQuery) return;
            lastQuery = q;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fetch('index.php?page=tag-search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(function (results) {
                        list.innerHTML = '';
                        (results || []).forEach(function (tag) {
                            const opt = document.createElement('option');
                            opt.value = tag.name;
                            list.appendChild(opt);
                        });
                    })
                    .catch(function () { /* ignore */ });
            }, 150);
        });
    })();
</script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>