<?php
/**
 * Program Listing View
 *
 * Program Owners see a management table of their own programs.
 * Researchers see HackerOne-style program cards with enrollment/save/submit actions.
 *
 * Variables available from ProgramController::list():
 *   $title      - Page title
 *   $activePage - Active navigation item ('programs')
 *   $programs   - Array of program rows (researchers get is_enrolled, is_saved per row)
 *   $csrfToken  - CSRF token for actions
 *   $success    - Flash success message (nullable)
 *   $error      - Flash error message (nullable)
 *
 * @see Requirement 4.1, 4.2, 4.3, 4.4, 6.1
 */

$roleId = (int) ($_SESSION['role_id'] ?? 0);
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Page Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-lg);">
    <h1 style="font-size:var(--text-heading); font-weight:600; margin:0;">Programs</h1>
    <?php if ($roleId === 2): ?>
        <a href="index.php?page=program-create" class="btn-primary">
            <i data-lucide="plus" style="width:16px; height:16px;"></i>
            New Program
        </a>
    <?php endif; ?>
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

<?php if (empty($programs)): ?>
    <div class="card-surface" style="padding:var(--space-2xl); text-align:center;">
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
            <?php if ($roleId === 2): ?>
                You haven't created any programs yet. Click "New Program" to get started.
            <?php else: ?>
                No active programs available at the moment.
            <?php endif; ?>
        </p>
    </div>

<?php elseif ($roleId === 2): ?>
    <!-- ===== Program Owner: Table View ===== -->
    <div class="card-surface" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Reward Range</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $prog): ?>
                    <tr style="cursor:pointer;"
                        onclick="window.location='index.php?page=program-detail&id=<?= (int) $prog['id'] ?>'">
                        <td>
                            <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['id'] ?>"
                                style="color:var(--foreground); font-weight:500; text-decoration:none;">
                                <?= htmlspecialchars($prog['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $minReward = isset($prog['min_reward']) ? '$' . number_format((float) $prog['min_reward'], 0) : '—';
                            $maxReward = isset($prog['max_reward']) ? '$' . number_format((float) $prog['max_reward'], 0) : '—';
                            if ($minReward !== '—' && $maxReward !== '—'):
                                echo htmlspecialchars("$minReward – $maxReward", ENT_QUOTES, 'UTF-8');
                            else:
                                echo '<span style="color:var(--muted-foreground);">Not set</span>';
                            endif;
                            ?>
                        </td>
                        <td>
                            <?php
                            $status = $prog['status'] ?? 'draft';
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
                        <td style="text-align:right;">
                            <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['id'] ?>" class="btn-secondary"
                                style="padding:6px 12px; font-size:var(--text-caption);">
                                View
                            </a>
                            <a href="index.php?page=program-edit&amp;id=<?= (int) $prog['id'] ?>" class="btn-secondary"
                                style="padding:6px 12px; font-size:var(--text-caption); margin-left:4px;">
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- ===== Researcher: HackerOne-style Card Grid ===== -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:var(--space-lg);">
        <?php foreach ($programs as $prog):
            $isEnrolled = $prog['is_enrolled'] ?? false;
            $isSaved = $prog['is_saved'] ?? false;
            $description = $prog['description'] ?? '';
            $truncatedDesc = mb_strlen($description) > 120 ? mb_substr($description, 0, 120) . '…' : $description;
            ?>
            <div class="card-surface"
                style="padding:0; display:flex; flex-direction:column; overflow:hidden; transition:box-shadow 0.15s ease;">
                <!-- Card Header with accent bar -->
                <div style="height:4px; background:var(--accent);"></div>

                <div style="padding:var(--space-lg); flex:1; display:flex; flex-direction:column;">
                    <!-- Title -->
                    <div style="margin-bottom:var(--space-sm);">
                        <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['id'] ?>"
                            style="font-size:var(--text-subheading); font-weight:600; color:var(--foreground); text-decoration:none; line-height:1.3;">
                            <?= htmlspecialchars($prog['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>

                    <!-- Description snippet -->
                    <p
                        style="font-size:var(--text-small); color:var(--muted-foreground); line-height:1.5; margin:0 0 var(--space-md) 0; flex:1;">
                        <?= htmlspecialchars($truncatedDesc, ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <!-- Scope preview -->
                    <?php if (!empty($prog['scope'])): ?>
                        <div style="margin-bottom:var(--space-md);">
                            <span
                                style="font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px; font-weight:500;">Scope</span>
                            <p
                                style="font-size:var(--text-small); color:var(--foreground); margin:4px 0 0; line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= htmlspecialchars(mb_substr($prog['scope'], 0, 80), ENT_QUOTES, 'UTF-8') ?>
                                <?= mb_strlen($prog['scope']) > 80 ? '…' : '' ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Card Footer: actions -->
                    <div
                        style="display:flex; align-items:center; gap:var(--space-sm); padding-top:var(--space-md); border-top:1px solid var(--border); flex-wrap:wrap;">
                        <a href="index.php?page=program-detail&amp;id=<?= (int) $prog['id'] ?>" class="btn-secondary"
                            style="padding:6px 12px; font-size:var(--text-caption);">
                            View Details
                        </a>

                        <a href="index.php?page=report-submit&amp;program_id=<?= (int) $prog['id'] ?>" class="btn-primary"
                            style="padding:6px 12px; font-size:var(--text-caption);">
                            <i data-lucide="bug" style="width:12px; height:12px;"></i>
                            Submit Report
                        </a>

                        <!-- Bookmark toggle -->
                        <?php if ($isSaved): ?>
                            <form method="POST" action="index.php?page=program-unsave&amp;id=<?= (int) $prog['id'] ?>"
                                style="display:inline; margin-left:auto;">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" title="Remove bookmark"
                                    style="background:none; border:none; color:var(--accent); cursor:pointer; padding:4px;">
                                    <i data-lucide="bookmark-check" style="width:18px; height:18px;"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="index.php?page=program-save&amp;id=<?= (int) $prog['id'] ?>"
                                style="display:inline; margin-left:auto;">
                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" title="Save to bookmarks"
                                    style="background:none; border:none; color:var(--muted-foreground); cursor:pointer; padding:4px;">
                                    <i data-lucide="bookmark" style="width:18px; height:18px;"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>