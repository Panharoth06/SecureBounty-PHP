<?php
/**
 * Program Card Partial
 *
 * Renders ONE researcher-facing program card. The parent loop is responsible
 * for setting every variable listed below before the include.
 *
 * Expected variables (must be set by caller):
 *   $prog            (array)  — program row: id, title, description, scope, status,
 *                               logo_path, is_enrolled, is_saved, min_reward, max_reward
 *   $progStats       (array)  — { report_count, enrolled_count, response_rate,
 *                                 badges: { responsive: bool, popular: bool } }
 *   $progAssetCounts (array)  — map of asset type => count
 *   $progTags        (array)  — list of { id, name } rows
 *   $csrfToken       (string) — CSRF token for bookmark form
 *
 * Does NOT include layout.php — this is rendered inside an existing page.
 *
 * @see Requirement 4.2, 4.4, 4.7, 7.9 — formatting helpers via FormattingService
 */

$prog = $prog ?? [];
$progStats = $progStats ?? [];
$progAssetCounts = $progAssetCounts ?? [];
$progTags = $progTags ?? [];
$csrfToken = $csrfToken ?? '';

$progId = (int) ($prog['id'] ?? 0);
$progTitle = (string) ($prog['title'] ?? '');
$progDesc = (string) ($prog['description'] ?? '');
$progLogo = (string) ($prog['logo_path'] ?? '');
$progSaved = !empty($prog['is_saved']);

$status = $prog['status'] ?? 'draft';
$statusClass = match ($status) {
    'active' => 'badge-accepted',
    'draft' => 'badge-pending',
    'closed' => 'badge-resolved',
    'suspended' => 'badge-rejected',
    default => 'badge-pending',
};

$badges = $progStats['badges'] ?? [];
$isResponsive = !empty($badges['responsive']);
$isPopular = !empty($badges['popular']);
$reportCount = (int) ($progStats['report_count'] ?? 0);
$enrolledCount = (int) ($progStats['enrolled_count'] ?? 0);
$responseRate = $progStats['response_rate'] ?? null;

$truncatedTags = FormattingService::truncateTags($progTags, 5);
?>
<article class="card-surface program-card"
    style="padding:var(--space-lg); display:flex; flex-direction:column; gap:var(--space-md);">

    <!-- Header: logo + title + status -->
    <div style="display:flex; align-items:flex-start; gap:var(--space-md);">
        <?php if ($progLogo !== ''): ?>
            <img src="<?= htmlspecialchars($progLogo, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($progTitle . ' logo', ENT_QUOTES, 'UTF-8') ?>"
                style="width:48px; height:48px; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border); flex-shrink:0;">
        <?php else: ?>
            <div aria-hidden="true"
                style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:var(--muted); color:var(--foreground); font-weight:600; font-size:var(--text-subheading); border-radius:var(--radius-md); border:1px solid var(--border); flex-shrink:0;">
                <?= htmlspecialchars(FormattingService::logoPlaceholder($progTitle), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div style="flex:1; min-width:0;">
            <a href="index.php?page=program-detail&amp;id=<?= $progId ?>"
                style="font-size:var(--text-subheading); font-weight:600; color:var(--foreground); text-decoration:none; line-height:1.3; display:block;">
                <?= htmlspecialchars($progTitle, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span class="<?= $statusClass ?>" style="margin-top:4px; display:inline-block;">
                <?= htmlspecialchars(ucfirst((string) $status), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <!-- Responsive / Popular badges -->
    <?php if ($isResponsive || $isPopular): ?>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <?php if ($isResponsive): ?>
                <span class="badge-responsive">Responsive</span>
            <?php endif; ?>
            <?php if ($isPopular): ?>
                <span class="badge-popular">Popular</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Description -->
    <p style="font-size:var(--text-small); color:var(--muted-foreground); line-height:1.5; margin:0;">
        <?= htmlspecialchars(FormattingService::truncateDescription($progDesc), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <!-- Bounty range -->
    <div style="display:flex; align-items:center; gap:var(--space-sm);">
        <span
            style="font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px; font-weight:500;">
            Bounty
        </span>
        <span style="font-size:var(--text-body); color:var(--foreground); font-weight:500;">
            <?= htmlspecialchars(
                FormattingService::formatBountyRange(
                    isset($prog['min_reward']) ? (float) $prog['min_reward'] : null,
                    isset($prog['max_reward']) ? (float) $prog['max_reward'] : null
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>
    </div>

    <!-- Asset counts -->
    <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
        <?php if (empty($progAssetCounts)): ?>
            <span style="font-size:var(--text-small); color:var(--muted-foreground);">No assets defined</span>
        <?php else: ?>
            <?php foreach ($progAssetCounts as $assetType => $assetCount):
                $assetCount = (int) $assetCount;
                $assetLabel = (string) $assetType . ($assetCount > 1 ? 's' : '');
                ?>
                <span class="chip chip-neutral">
                    <?= $assetCount ?>
                    <?= htmlspecialchars($assetLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tags -->
    <?php if (!empty($progTags)): ?>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-xs);">
            <?php foreach ($truncatedTags['tags'] as $tag): ?>
                <span class="chip chip-accent">
                    <?= htmlspecialchars((string) ($tag['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
            <?php if ((int) ($truncatedTags['overflow'] ?? 0) > 0): ?>
                <span class="chip chip-neutral">
                    +
                    <?= (int) $truncatedTags['overflow'] ?> more
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div
        style="display:flex; flex-wrap:wrap; gap:var(--space-md); font-size:var(--text-small); color:var(--muted-foreground);">
        <span>
            <?= $reportCount ?> reports
        </span>
        <span>
            <?= $enrolledCount ?> enrolled
        </span>
        <span>
            <?php if ($responseRate === null): ?>
                N/A response
            <?php else: ?>
                <?= (int) $responseRate ?>% response
            <?php endif; ?>
        </span>
    </div>

    <!-- Footer: actions -->
    <div
        style="display:flex; align-items:center; gap:var(--space-sm); padding-top:var(--space-md); border-top:1px solid var(--border); flex-wrap:wrap;">
        <a href="index.php?page=program-detail&amp;id=<?= $progId ?>" class="btn-secondary"
            style="padding:6px 12px; font-size:var(--text-caption);">
            View Details
        </a>
        <a href="index.php?page=report-submit&amp;program_id=<?= $progId ?>" class="btn-primary"
            style="padding:6px 12px; font-size:var(--text-caption);">
            <i data-lucide="bug" style="width:12px; height:12px;"></i>
            Submit Report
        </a>

        <?php if ($progSaved): ?>
            <form method="POST" action="index.php?page=program-unsave&amp;id=<?= $progId ?>"
                style="display:inline; margin-left:auto;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" title="Remove bookmark"
                    style="background:none; border:none; color:var(--accent); cursor:pointer; padding:4px;">
                    <i data-lucide="bookmark-check" style="width:18px; height:18px;"></i>
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="index.php?page=program-save&amp;id=<?= $progId ?>"
                style="display:inline; margin-left:auto;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" title="Save to bookmarks"
                    style="background:none; border:none; color:var(--muted-foreground); cursor:pointer; padding:4px;">
                    <i data-lucide="bookmark" style="width:18px; height:18px;"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</article>