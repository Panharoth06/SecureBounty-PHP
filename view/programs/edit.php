<?php
/**
 * Program Edit Form View
 *
 * Displays a pre-filled form for Program Owners to edit an existing program.
 * Includes publish and close action buttons depending on program status.
 *
 * Variables available from ProgramController::edit():
 *   $title          - Page title
 *   $activePage     - Active navigation item ('programs')
 *   $program        - Program data array (id, owner_id, title, description, scope, status)
 *   $rewardPolicies - Array of reward policy rows for this program
 *   $csrfToken      - CSRF token for forms
 *   $errors         - Validation errors array (nullable)
 *   $oldInput       - Previous form input for repopulation (nullable)
 *
 * @see Requirement 4.2, 4.3, 4.4, 4.5, 4.6
 */

$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$status = $program['status'] ?? 'draft';
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

<!-- Validation Errors -->
<?php include __DIR__ . '/../components/form-errors.php'; ?>

<!-- Edit Form -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-lg);">
    <form method="POST" action="index.php?page=process-program-edit&amp;id=<?= (int) $program['id'] ?>">
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
<div class="card-surface" style="padding:var(--space-lg);">
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

<?php include __DIR__ . '/../components/layout_end.php'; ?>