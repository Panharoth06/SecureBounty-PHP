<?php
/**
 * Public Researcher Profile View
 *
 * Displays a public researcher profile with reputation stats, severity
 * breakdown, bio, and social links.
 *
 * Expected variables (set by UserController::publicProfile):
 *   $profile    (array)       — { id, first_name, last_name, display_name, bio,
 *                                  avatar_path, website_url, github_url,
 *                                  linkedin_url, facebook_url, youtube_url,
 *                                  instagram_url, reputation_score,
 *                                  earliest_accepted_at, role_name }
 *   $stats      (array)       — { rank: ?int, score: int, accepted_count: int,
 *                                  severity_breakdown: { critical, high, medium,
 *                                  low, informational } }
 *   $success    (string|null) — flash success message
 *   $title      (string)      — page title
 *   $activePage (string)      — sidebar highlight slug
 *
 * @see Requirement 8.11 — Avatar placeholder uses first letters of first/last name
 */

$title = $title ?? 'SecureBounty | Researcher Profile';
$activePage = $activePage ?? 'leaderboard';
$profile = $profile ?? [];
$stats = $stats ?? [];
$success = $success ?? null;

$profileId = (int) ($profile['id'] ?? 0);
$firstName = (string) ($profile['first_name'] ?? '');
$lastName = (string) ($profile['last_name'] ?? '');
$displayName = trim((string) ($profile['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = trim($firstName . ' ' . $lastName);
}
$bio = (string) ($profile['bio'] ?? '');
$avatarPath = (string) ($profile['avatar_path'] ?? '');
$roleName = (string) ($profile['role_name'] ?? '');

$rank = $stats['rank'] ?? null;
$score = (int) ($stats['score'] ?? 0);
$acceptedCount = (int) ($stats['accepted_count'] ?? 0);
$severity = $stats['severity_breakdown'] ?? [];

$socialLinks = [
    'website_url' => 'globe',
    'github_url' => 'github',
    'linkedin_url' => 'linkedin',
    'facebook_url' => 'facebook',
    'youtube_url' => 'youtube',
    'instagram_url' => 'instagram',
];

include __DIR__ . '/../components/layout.php';
?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=leaderboard"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Leaderboard
    </a>
</nav>

<?php if (!empty($success)): ?>
    <div role="status" aria-live="polite"
        style="background:var(--status-accepted-bg); border:1px solid var(--status-accepted-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-accepted-text); font-size:var(--text-body);">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-lg);">
    <div style="display:flex; gap:var(--space-lg); align-items:flex-start; flex-wrap:wrap;">
        <?php if ($avatarPath !== ''): ?>
            <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> avatar"
                style="width:96px; height:96px; object-fit:cover; border-radius:var(--radius-full); border:1px solid var(--border); flex-shrink:0;">
        <?php else: ?>
            <div aria-hidden="true"
                style="width:96px; height:96px; display:flex; align-items:center; justify-content:center; background:var(--muted); color:var(--foreground); font-weight:600; font-size:var(--text-heading); border-radius:var(--radius-full); border:1px solid var(--border); flex-shrink:0;">
                <?= htmlspecialchars(FormattingService::avatarPlaceholder($firstName, $lastName), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div style="flex:1; min-width:240px;">
            <h1 style="font-size:var(--text-heading); font-weight:600; margin:0; color:var(--foreground);">
                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <?php if ($roleName !== ''): ?>
                <p style="margin:4px 0 0; color:var(--muted-foreground); font-size:var(--text-small);">
                    <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php if ($bio !== ''): ?>
                <p
                    style="margin:var(--space-md) 0 0; color:var(--foreground); font-size:var(--text-body); line-height:1.5; white-space:pre-wrap;">
                    <?= htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <?php
            $hasSocial = false;
            foreach ($socialLinks as $field => $icon) {
                if (!empty($profile[$field])) {
                    $hasSocial = true;
                    break;
                }
            }
            ?>
            <?php if ($hasSocial): ?>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-sm); margin-top:var(--space-md);">
                    <?php foreach ($socialLinks as $field => $icon):
                        $url = (string) ($profile[$field] ?? '');
                        if ($url === '') {
                            continue;
                        }
                        ?>
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"
                            title="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"
                            style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:var(--radius-full); background:var(--muted); color:var(--foreground); text-decoration:none; border:1px solid var(--border);">
                            <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"
                                style="width:18px; height:18px;"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats grid -->
<div
    style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:var(--space-md); margin-bottom:var(--space-lg);">
    <div class="stat-card card-surface" style="padding:var(--space-lg);">
        <p
            style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">
            Rank</p>
        <p style="margin:var(--space-xs) 0 0; font-size:var(--text-heading); font-weight:600; color:var(--foreground);">
            <?php if ($rank === null): ?>
                Unranked
            <?php else: ?>
                #
                <?= (int) $rank ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="stat-card card-surface" style="padding:var(--space-lg);">
        <p
            style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">
            Reputation Score</p>
        <p style="margin:var(--space-xs) 0 0; font-size:var(--text-heading); font-weight:600; color:var(--foreground);">
            <?= htmlspecialchars((string) $score, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="stat-card card-surface" style="padding:var(--space-lg);">
        <p
            style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">
            Accepted Reports</p>
        <p style="margin:var(--space-xs) 0 0; font-size:var(--text-heading); font-weight:600; color:var(--foreground);">
            <?= htmlspecialchars((string) $acceptedCount, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>

<!-- Severity breakdown -->
<div class="card-surface" style="padding:var(--space-lg);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 var(--space-md);">Severity Breakdown</h2>
    <?php
    $severityChips = [
        'critical' => 'chip-red',
        'high' => 'chip-orange',
        'medium' => 'chip-accent',
        'low' => 'chip-neutral',
        'informational' => 'chip-neutral',
    ];
    ?>
    <div style="display:flex; flex-wrap:wrap; gap:var(--space-sm);">
        <?php foreach ($severityChips as $severityKey => $chipClass):
            $count = (int) ($severity[$severityKey] ?? 0);
            ?>
            <span class="chip <?= htmlspecialchars($chipClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(ucfirst($severityKey), ENT_QUOTES, 'UTF-8') ?>:
                <?= $count ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>