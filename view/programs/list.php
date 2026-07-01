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
    <!-- ===== Researcher: Filter Panel + Card Grid ===== -->
    <div class="programs-layout">
        <?php include __DIR__ . '/components/filter-panel.php'; ?>

        <div>
            <?php if (empty($programs)): ?>
                <div class="card-surface" style="padding:var(--space-2xl); text-align:center;">
                    <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">
                        No programs match the current filters.
                    </p>
                    <?php if (!empty($filters)): ?>
                        <p style="margin-top:var(--space-md);">
                            <a href="index.php?page=programs&amp;clear_filters=1" class="btn-secondary">Clear Filters</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($programs as $prog):
                        $progId = (int) $prog['id'];
                        $progStats = $statistics[$progId] ?? [
                            'report_count' => 0,
                            'enrolled_count' => 0,
                            'response_rate' => null,
                            'badges' => ['responsive' => false, 'popular' => false],
                        ];
                        $progAssetCounts = $assetCounts[$progId] ?? [];
                        $progTags = $tags[$progId] ?? [];
                        include __DIR__ . '/components/program-card.php';
                    endforeach; ?>
                </div>

                <?php if (($totalCount ?? 0) > ($perPage ?? 12)): ?>
                    <p
                        style="text-align:center; color:var(--muted-foreground); font-size:var(--text-small); margin-top:var(--space-lg);">
                        Showing <?= count($programs) ?> of <?= (int) $totalCount ?> matching programs.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>