<?php
/**
 * SecureBounty — Program Owner Dashboard View
 *
 * Tabbed layout: Overview (stats + pending) and All Reports (filterable table).
 *
 * Expected variables (set by controller):
 *   $title              (string) — Page title
 *   $activePage         (string) — Sidebar highlight slug
 *   $ownedPrograms      (int)    — Number of programs owned
 *   $pendingReports     (int)    — Pending report count
 *   $totalReports       (int)    — Total report count
 *   $pendingReportsList (array)  — Pending reports [{id, title, program_title, severity, created_at}]
 *   $allReportsList     (array)  — All reports [{id, title, program_title, status, severity, researcher_name, created_at}]
 *   $recentComments     (array)  — Recent comments [{body, user_name, report_title, created_at}]
 *
 * @see Requirement 13.2, 13.4
 */

$title ??= 'Program Owner Dashboard';
$activePage ??= 'dashboard';
$ownedPrograms ??= 0;
$pendingReports ??= 0;
$totalReports ??= 0;
$recentComments ??= [];
$pendingReportsList ??= [];
$allReportsList ??= [];

include __DIR__ . '/../components/layout.php';
?>

<!-- Page Header -->
<div style="margin-bottom: var(--space-lg);">
    <h1 style="font-size: var(--text-display); font-weight: 700; color: var(--foreground); margin: 0;">
        Dashboard
    </h1>
    <p style="font-size: var(--text-small); color: var(--muted-foreground); margin-top: var(--space-xs);">
        Your programs and reports at a glance
    </p>
</div>

<!-- Stat Cards Grid -->
<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg); margin-bottom: var(--space-xl);">
    <?php
    $label = 'Owned Programs';
    $value = $ownedPrograms;
    $icon = 'shield';
    include __DIR__ . '/../components/stat-card.php';
    ?>
    <?php
    $label = 'Pending Reports';
    $value = $pendingReports;
    $icon = 'file-text';
    include __DIR__ . '/../components/stat-card.php';
    ?>
    <?php
    $label = 'Total Reports';
    $value = $totalReports;
    $icon = 'activity';
    include __DIR__ . '/../components/stat-card.php';
    ?>
</div>

    <!-- Pending Reports Table -->
    <div
        style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-lg); margin-bottom: var(--space-lg);">
        <h2
            style="font-size: var(--text-heading); font-weight: 600; color: var(--foreground); margin: 0 0 var(--space-md) 0;">
            <i data-lucide="file-text"
                style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
            Pending Reports
        </h2>

        <?php if (empty($pendingReportsList)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
                <p class="empty-state-title">No pending reports</p>
                <p class="empty-state-description">Reports awaiting your review will appear here.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Program</th>
                        <th>Severity</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingReportsList as $report): ?>
                        <tr style="cursor:pointer;"
                            onclick="window.location='index.php?page=report-detail&id=<?= (int) $report['id'] ?>'">
                            <td style="color: var(--foreground); font-weight: 500;">
                                <?= htmlspecialchars($report['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars($report['program_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span
                                    class="badge badge-severity-<?= htmlspecialchars($report['severity'] ?? 'medium', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(ucfirst($report['severity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-size: var(--text-small); color: var(--muted-foreground);">
                                <?= htmlspecialchars($report['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Comments Section -->
    <div
        style="background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-lg);">
        <h2
            style="font-size: var(--text-heading); font-weight: 600; color: var(--foreground); margin: 0 0 var(--space-md) 0;">
            <i data-lucide="message-square"
                style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: var(--space-sm);"></i>
            Recent Comments
        </h2>

        <?php if (empty($recentComments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i data-lucide="message-square"></i></div>
                <p class="empty-state-title">No comments yet</p>
                <p class="empty-state-description">Comments on your program reports will appear here.</p>
            </div>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($recentComments as $comment): ?>
                    <li style="padding: var(--space-md) 0; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-xs);">
                            <strong style="font-size: var(--text-body); color: var(--foreground);">
                                <?= htmlspecialchars($comment['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                            <span style="font-size: var(--text-small); color: var(--muted-foreground);">
                                on <?= htmlspecialchars($comment['program_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span
                                style="font-family: 'JetBrains Mono', monospace; font-size: var(--text-small); color: var(--muted-foreground); margin-left: auto;">
                                <?= htmlspecialchars($comment['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <p
                            style="font-size: var(--text-body); color: var(--muted-foreground); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($comment['body'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

<?php include __DIR__ . '/../components/layout_end.php'; ?>