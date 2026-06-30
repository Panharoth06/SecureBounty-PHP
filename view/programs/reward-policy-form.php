<?php
/**
 * Reward Policy Form View (create + edit)
 *
 * Variables available from RewardPolicyController:
 *   $title      - Page title
 *   $activePage - Active navigation item ('programs')
 *   $program    - Parent program row (id, title, ...)
 *   $mode       - 'create' or 'edit'
 *   $policy     - Existing policy row when editing (nullable)
 *   $csrfToken  - CSRF token
 *   $errors     - Validation errors (nullable)
 *   $oldInput   - Previous form input (nullable)
 *
 * @see Requirement 5.1, 5.2, 5.3
 */

$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$mode = $mode ?? 'create';
$policy = $policy ?? null;
$programId = (int) ($program['id'] ?? 0);

$severities = ['critical', 'high', 'medium', 'low', 'informational'];

$currentSeverity = $oldInput['severity'] ?? ($policy['severity'] ?? '');
$currentMin = $oldInput['min_reward'] ?? ($policy['min_reward'] ?? '');
$currentMax = $oldInput['max_reward'] ?? ($policy['max_reward'] ?? '');

$formAction = $mode === 'edit'
    ? 'index.php?page=process-reward-policy-edit'
    : 'index.php?page=process-reward-policy-create';

include __DIR__ . '/../components/layout.php';
?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=program-edit&amp;id=<?= $programId ?>"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Program
    </a>
</nav>

<h1 style="font-size:var(--text-heading); font-weight:600; margin-bottom:var(--space-xs);">
    <?= $mode === 'edit' ? 'Edit' : 'Add' ?> Reward Policy
</h1>
<p style="color:var(--muted-foreground); font-size:var(--text-small); margin-bottom:var(--space-lg);">
    <?= htmlspecialchars($program['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
</p>

<?php include __DIR__ . '/../components/form-errors.php'; ?>

<div class="card-surface" style="padding:var(--space-lg); max-width:520px;">
    <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="program_id" value="<?= $programId ?>">
        <?php if ($mode === 'edit'): ?>
            <input type="hidden" name="id" value="<?= (int) $policy['id'] ?>">
        <?php endif; ?>

        <!-- Severity -->
        <div style="margin-bottom:var(--space-lg);">
            <label for="severity" class="form-label">Severity</label>
            <?php if ($mode === 'edit'): ?>
                <input type="text" id="severity" class="form-input"
                    value="<?= htmlspecialchars(ucfirst((string) $currentSeverity), ENT_QUOTES, 'UTF-8') ?>" disabled>
                <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                    Severity cannot be changed. Delete and recreate the policy to use a different severity.
                </p>
            <?php else: ?>
                <select id="severity" name="severity"
                    class="form-select <?= isset($errors['severity']) ? 'is-error' : '' ?>" required>
                    <option value="">— Select severity —</option>
                    <?php foreach ($severities as $sev): ?>
                        <option value="<?= $sev ?>" <?= $currentSeverity === $sev ? 'selected' : '' ?>>
                            <?= ucfirst($sev) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <!-- Min / Max reward -->
        <div style="display:flex; gap:var(--space-md); margin-bottom:var(--space-lg);">
            <div style="flex:1;">
                <label for="min_reward" class="form-label">Min Reward (USD)</label>
                <input type="number" step="0.01" min="0" id="min_reward" name="min_reward"
                    class="form-input <?= isset($errors['min_reward']) ? 'is-error' : '' ?>"
                    value="<?= htmlspecialchars((string) $currentMin, ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00"
                    required>
            </div>
            <div style="flex:1;">
                <label for="max_reward" class="form-label">Max Reward (USD)</label>
                <input type="number" step="0.01" min="0" id="max_reward" name="max_reward"
                    class="form-input <?= isset($errors['max_reward']) ? 'is-error' : '' ?>"
                    value="<?= htmlspecialchars((string) $currentMax, ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00"
                    required>
            </div>
        </div>

        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <a href="index.php?page=program-edit&amp;id=<?= $programId ?>" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">
                <i data-lucide="save" style="width:14px; height:14px;"></i>
                <?= $mode === 'edit' ? 'Save Policy' : 'Add Policy' ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>