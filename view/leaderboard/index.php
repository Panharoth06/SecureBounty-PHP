<?php
/**
 * SecureBounty — Leaderboard View
 *
 * Displays researchers ranked by reputation score with severity breakdowns,
 * pagination, and the current user's rank.
 *
 * Expected variables (set by LeaderboardController):
 *   $title           (string)   — Page title
 *   $activePage      (string)   — Sidebar highlight slug
 *   $entries         (array)    — Leaderboard entries [{id, first_name, last_name, display_name, avatar_path, reputation_score, accepted_count, severity_breakdown}]
 *   $total           (int)      — Total ranked researchers
 *   $currentPage     (int)      — Current page number
 *   $totalPages      (int)      — Total number of pages
 *   $currentUserRank (int|null) — Current user's rank or null if unranked
 *
 * @see Requirement 3.3 — Display researchers ranked by Reputation_Score descending
 * @see Requirement 3.4 — Show rank, display name, score, accepted count, severity breakdown
 * @see Requirement 3.5 — Paginate with 25 entries per page
 * @see Requirement 3.8 — Show current user's rank
 * @see Requirement 3.9 — "Unranked" for zero accepted reports
 * @see Requirement 3.10 — Accessible to all authenticated users
 */

$title ??= 'SecureBounty | Leaderboard';
$activePage ??= 'leaderboard';
$entries ??= [];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 0;
$currentUserRank ??= null;

include __DIR__ . '/../components/layout.php';
?>

<!-- Page Header -->
<div style="margin-bottom: var(--space-lg);">
    <h1 style="font-size: var(--text-display); font-weight: 700; color: var(--foreground); margin: 0;">
        Researcher Leaderboard
    </h1>
    <p style="font-size: var(--text-small); color: var(--muted-foreground); margin-top: var(--space-xs);">
        Top researchers ranked by reputation score
    </p>
</div>

<!-- Current User Rank Card -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-md) var(--space-lg); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-md);">
    <i data-lucide="trophy" style="width: 20px; height: 20px; color: var(--primary);"></i>
    <span style="font-size: var(--text-body); color: var(--foreground);">
        Your Rank:
        <strong>
            <?php if ($currentUserRank !== null): ?>
                #
                <?= htmlspecialchars((string) $currentUserRank, ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
                Unranked
            <?php endif; ?>
        </strong>
    </span>
    <span style="font-size: var(--text-small); color: var(--muted-foreground); margin-left: auto;">
        <?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?> ranked researcher
        <?= $total !== 1 ? 's' : '' ?>
    </span>
</div>

<!-- Leaderboard Table -->
<div
    style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;">
    <?php if (empty($entries)): ?>
        <div class="empty-state" style="padding: var(--space-xl);">
            <div class="empty-state-icon">
                <i data-lucide="users"></i>
            </div>
            <p class="empty-state-title">No ranked researchers yet</p>
            <p class="empty-state-description">Researchers appear here once they have accepted reports with assigned
                severity levels.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--muted); border-bottom: 1px solid var(--border);">
                    <th
                        style="padding: var(--space-sm) var(--space-md); text-align: left; font-size: var(--text-small); font-weight: 600; color: var(--muted-foreground);">
                        Rank</th>
                    <th
                        style="padding: var(--space-sm) var(--space-md); text-align: left; font-size: var(--text-small); font-weight: 600; color: var(--muted-foreground);">
                        Researcher</th>
                    <th
                        style="padding: var(--space-sm) var(--space-md); text-align: right; font-size: var(--text-small); font-weight: 600; color: var(--muted-foreground);">
                        Score</th>
                    <th
                        style="padding: var(--space-sm) var(--space-md); text-align: right; font-size: var(--text-small); font-weight: 600; color: var(--muted-foreground);">
                        Accepted</th>
                    <th
                        style="padding: var(--space-sm) var(--space-md); text-align: center; font-size: var(--text-small); font-weight: 600; color: var(--muted-foreground);">
                        Severity Breakdown</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
                $rankOffset = ($currentPage - 1) * 25;
                foreach ($entries as $index => $entry):
                    $rank = $rankOffset + $index + 1;
                    $entryUserId = (int) ($entry['id'] ?? 0);
                    $displayName = !empty($entry['display_name'])
                        ? $entry['display_name']
                        : trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? ''));
                    $severity = $entry['severity_breakdown'] ?? [];
                    $isCurrentUser = $entryUserId === $currentUserId && $currentUserId > 0;
                    $rowClass = $isCurrentUser ? 'leaderboard-row-current' : '';
                    ?>
                    <tr class="<?= $rowClass ?>" style="border-bottom: 1px solid var(--border);">
                        <td
                            style="padding: var(--space-sm) var(--space-md); font-size: var(--text-body); color: var(--foreground); font-weight: 600;">
                            #
                            <?= htmlspecialchars((string) $rank, ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td
                            style="padding: var(--space-sm) var(--space-md); font-size: var(--text-body); color: var(--foreground);">
                            <a href="index.php?page=public-profile&amp;id=<?= $entryUserId ?>" class="leaderboard-name-link">
                                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <?php if ($isCurrentUser): ?>
                                <span
                                    style="margin-left:6px; font-size:var(--text-caption); color:var(--muted-foreground);">(you)</span>
                            <?php endif; ?>
                        </td>
                        <td
                            style="padding: var(--space-sm) var(--space-md); font-size: var(--text-body); color: var(--foreground); text-align: right; font-weight: 600;">
                            <?= htmlspecialchars((string) ($entry['reputation_score'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td
                            style="padding: var(--space-sm) var(--space-md); font-size: var(--text-body); color: var(--muted-foreground); text-align: right;">
                            <?= htmlspecialchars((string) ($entry['accepted_count'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="padding: var(--space-sm) var(--space-md); text-align: center;">
                            <div style="display: flex; gap: var(--space-xs); justify-content: center; flex-wrap: wrap;">
                                <?php if (($severity['critical'] ?? 0) > 0): ?>
                                    <span class="badge badge-destructive" style="font-size: 11px;">C:
                                        <?= (int) $severity['critical'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (($severity['high'] ?? 0) > 0): ?>
                                    <span class="badge badge-warning" style="font-size: 11px;">H:
                                        <?= (int) $severity['high'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (($severity['medium'] ?? 0) > 0): ?>
                                    <span class="badge badge-secondary" style="font-size: 11px;">M:
                                        <?= (int) $severity['medium'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (($severity['low'] ?? 0) > 0): ?>
                                    <span class="badge badge-outline" style="font-size: 11px;">L:
                                        <?= (int) $severity['low'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (($severity['informational'] ?? 0) > 0): ?>
                                    <span class="badge badge-outline" style="font-size: 11px;">I:
                                        <?= (int) $severity['informational'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php
        $baseUrl = 'index.php?page=leaderboard';
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>