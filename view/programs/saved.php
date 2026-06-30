<?php
/**
 * Saved Programs View
 *
 * Displays the researcher's bookmarked programs with option to unsave.
 *
 * Variables available from ProgramController::savedPrograms():
 *   $title      - Page title
 *   $activePage - Active navigation item ('saved-programs')
 *   $programs   - Array of saved program rows (from SavedProgramRepository)
 *   $csrfToken  - CSRF token for unsave actions
 *   $success    - Flash success message (nullable)
 *   $error      - Flash error message (nullable)
 *
 * @see Requirement 6.3, 6.4
 */
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Page Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-lg);">
    <h1 style="font-size:var(--text-heading); font-weight:600; margin:0;">Saved Programs</h1>
</div>

<!-- Flash Messages -->
<?php if (!empty($success)): ?>
    <div
        style="background:var(--status-accepted-bg); border:1px solid var(--status-accepted-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-accepted-text); font-size:var(--text-body);">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div
        style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-rejected-text); font-size:var(--text-body);">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- Saved Programs Table -->
<?php if (empty($programs)): ?>
    <div class="card-surface" style="padding:var(--space-2xl); text-align:center;">
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
            You haven't saved any programs yet. Browse <a href="index.php?page=programs" style="color:var(--accent);">active
                programs</a> and bookmark the ones you're interested in.
        </p>
    </div>
<?php else: ?>
    <div class="card-surface" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Saved On</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $prog): ?>
                    <tr style="cursor:pointer;"
                        onclick="if(!event.target.closest('a,button,form'))window.location='index.php?page=program-detail&id=<?= (int) $prog['program_id'] ?>'">
                        <td>
                            <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['program_id'] ?>"
                                style="color:var(--foreground); font-weight:500; text-decoration:none;">
                                <?= htmlspecialchars($prog['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $status = $prog['program_status'] ?? 'draft';
                            $statusClass = match ($status) {
                                'active' => 'badge-accepted',
                                'draft' => 'badge-pending',
                                'closed' => 'badge-resolved',
                                'suspended' => 'badge-rejected',
                                default => 'badge-pending',
                            };
                            ?>
                            <span class="<?= $statusClass ?>">
                                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td style="color:var(--muted-foreground); font-size:var(--text-small);">
                            <?= htmlspecialchars($prog['saved_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['program_id'] ?>"
                                class="btn-secondary" style="padding:6px 12px; font-size:var(--text-caption);">
                                View
                            </a>
                            <form method="post" action="index.php?page=program-unsave&amp;id=<?= (int) $prog['program_id'] ?>"
                                style="display:inline; margin-left:4px;">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-secondary"
                                    style="padding:6px 12px; font-size:var(--text-caption); color:var(--status-rejected-text); cursor:pointer;">
                                    <i data-lucide="bookmark-minus" style="width:14px; height:14px; vertical-align:middle;"></i>
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>