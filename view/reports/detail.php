<?php
/**
 * Report Detail View
 *
 * Full report display with CVSS section, attachments, status actions, and comment thread.
 *
 * Variables available:
 * @var string $title        Page title
 * @var string $activePage   Active navigation item
 * @var array  $report       Report data (id, title, description, steps_to_reproduce, impact,
 *                           status, cvss_vector, cvss_score, cvss_severity, cvss_submitted_by,
 *                           final_severity, program_id, program_title, researcher_id,
 *                           researcher_first_name, researcher_last_name, created_at, updated_at)
 * @var array  $attachments  Array of attachment records (id, file_name, file_type, file_size, uploaded_at)
 * @var array  $comments     Array of comment records (id, user_id, author_first_name, author_last_name, body, parent_id, created_at)
 * @var string $csrfToken    CSRF protection token
 * @var bool   $isProgramOwner Whether the current user owns the program
 * @var bool   $isReporter   Whether the current user submitted this report
 *
 * @see Requirement 8.1, 8.2, 8.3, 8.4, 9.1, 9.4
 */

$attachments = $attachments ?? [];
$comments = $comments ?? [];
$isProgramOwner = $isProgramOwner ?? false;
$isReporter = $isReporter ?? false;
?>
<?php $title = $title ?? 'Report Detail';
$activePage = $activePage ?? 'reports';
include __DIR__ . '/../components/layout.php'; ?>

<!-- Breadcrumb -->
<nav style="font-size:var(--text-small); color:var(--muted-foreground); margin-bottom:var(--space-md);">
    <a href="index.php?page=programs">Programs</a>
    <span> / </span>
    <a href="index.php?page=program-detail&amp;id=<?= (int) $report['program_id'] ?>">
        <?= htmlspecialchars($report['program_title'] ?? 'Program', ENT_QUOTES, 'UTF-8') ?>
    </a>
    <span> / </span>
    <span>Report #
        <?= (int) $report['id'] ?>
    </span>
</nav>

<!-- Report Header -->
<div style="margin-bottom:var(--space-lg);">
    <div style="display:flex; align-items:center; gap:var(--space-sm); flex-wrap:wrap; margin-bottom:var(--space-sm);">
        <?php
        // Severity badge
        $severity = $report['final_severity'] ?? $report['cvss_severity'] ?? null;
        if ($severity):
            $type = 'severity';
            $value = $severity;
            include __DIR__ . '/../components/badge.php';
        endif;
        ?>
        <h1 style="font-size:var(--text-heading); font-weight:600; margin:0;">
            <?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
    </div>
    <div
        style="display:flex; align-items:center; gap:var(--space-md); font-size:var(--text-small); color:var(--muted-foreground);">
        <span>Status:
            <?php $type = 'status';
            $value = $report['status'];
            include __DIR__ . '/../components/badge.php'; ?>
        </span>
        <span>Submitted by
            <?= htmlspecialchars(($report['researcher_first_name'] ?? '') . ' ' . ($report['researcher_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span>
            <?= htmlspecialchars(date('M j, Y', strtotime($report['created_at'])), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Edit / Delete actions (reporter only) -->
    <?php if ($isReporter): ?>
        <div style="display:flex; gap:var(--space-sm); margin-top:var(--space-md);">
            <a href="index.php?page=report-edit&amp;id=<?= (int) $report['id'] ?>" class="btn-secondary"
                style="padding:6px 12px; font-size:var(--text-small);">
                <i data-lucide="pencil" style="width:14px; height:14px;"></i> Edit
            </a>
            <form method="POST" action="index.php?page=report-delete" style="display:inline;"
                onsubmit="return confirm('Are you sure you want to delete this report? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                <button type="submit" class="btn-destructive" style="padding:6px 12px; font-size:var(--text-small);">
                    <i data-lucide="trash-2" style="width:14px; height:14px;"></i> Delete
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- CVSS Section -->
<?php if (!empty($report['cvss_vector']) || $isProgramOwner): ?>
    <div class="card" style="margin-bottom:var(--space-lg);">
        <h3 style="margin-bottom:var(--space-md);">CVSS Score</h3>

        <?php if (!empty($report['cvss_vector'])): ?>
            <!-- Score Display -->
            <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:var(--space-sm);">
                <span style="font-size:var(--text-display); font-weight:700;">
                    <?= htmlspecialchars(number_format((float) $report['cvss_score'], 1), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php
                $cvssColor = match ($report['cvss_severity'] ?? 'none') {
                    'critical' => 'var(--sev-critical)',
                    'high' => 'var(--sev-high)',
                    'medium' => 'var(--sev-medium)',
                    'low' => 'var(--sev-low)',
                    default => 'var(--sev-info)',
                };
                ?>
                <!-- Score bar -->
                <div style="flex:1; height:8px; background:var(--muted); border-radius:var(--radius-full); overflow:hidden;">
                    <div
                        style="height:100%; width:<?= (float) $report['cvss_score'] * 10 ?>%; background:<?= $cvssColor ?>; border-radius:var(--radius-full);">
                    </div>
                </div>
                <?php $type = 'severity';
                $value = $report['cvss_severity'] ?? 'none';
                include __DIR__ . '/../components/badge.php'; ?>
            </div>

            <!-- Vector string -->
            <p
                style="font-family:var(--font-mono); font-size:var(--text-small); color:var(--muted-foreground); margin-bottom:var(--space-sm); word-break:break-all;">
                <?= htmlspecialchars($report['cvss_vector'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <?php if ($report['cvss_submitted_by']): ?>
                <p style="font-size:var(--text-small); color:var(--muted-foreground);">
                    Scored by:
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $report['cvss_submitted_by'])), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p style="font-size:var(--text-small); color:var(--muted-foreground);">No CVSS score provided.</p>
        <?php endif; ?>

        <?php if ($isProgramOwner): ?>
            <!-- Final Severity (program owner only) -->
            <hr style="border:none; border-top:1px solid var(--border); margin:var(--space-md) 0;">
            <form action="index.php?page=report-set-severity" method="POST"
                style="display:flex; align-items:flex-end; gap:var(--space-sm);">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                <div>
                    <label for="final-severity" class="form-label">Final Severity</label>
                    <select id="final-severity" name="final_severity" class="form-select" style="width:auto; min-width:160px;">
                        <option value="">— Select —</option>
                        <?php
                        $severities = ['critical', 'high', 'medium', 'low', 'informational'];
                        foreach ($severities as $sev):
                            ?>
                            <option value="<?= $sev ?>" <?= ($report['final_severity'] ?? '') === $sev ? 'selected' : '' ?>>
                                <?= ucfirst($sev) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-secondary btn-sm">Set Severity</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Description -->
<div class="card" style="margin-bottom:var(--space-lg);">
    <h3 style="margin-bottom:var(--space-md);">Description</h3>
    <?php if (!empty(trim($report['description']))): ?>
        <div style="font-size:var(--text-body); color:var(--foreground); line-height:1.6; white-space:pre-wrap;">
            <?= htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php else: ?>
        <p style="font-size:var(--text-body); color:var(--muted-foreground); font-style:italic; margin:0;">
            No description provided.
        </p>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
        <hr style="border:none; border-top:1px solid var(--border); margin:var(--space-lg) 0;">
        <h3 style="margin-bottom:var(--space-md);">Attachments</h3>
        <div style="display:flex; flex-direction:column; gap:var(--space-sm);">
            <?php foreach ($attachments as $attachment): ?>
                <div
                    style="display:flex; align-items:center; gap:var(--space-sm); padding:var(--space-sm) var(--space-md); background:var(--muted); border-radius:var(--radius-md);">
                    <i data-lucide="file" style="width:16px; height:16px; color:var(--muted-foreground);"></i>
                    <span style="font-size:var(--text-body); color:var(--foreground); flex:1;">
                        <?= htmlspecialchars($attachment['file_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span style="font-size:var(--text-small); color:var(--muted-foreground);">
                        <?= htmlspecialchars(round($attachment['file_size'] / 1024, 1) . ' KB', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <a href="index.php?page=download-attachment&amp;id=<?= (int) $attachment['id'] ?>" class="btn-ghost btn-sm"
                        title="Download">
                        <i data-lucide="download" style="width:14px; height:14px;"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Status Change (Program Owner only) -->
<?php if ($isProgramOwner): ?>
    <div class="card" style="margin-bottom:var(--space-lg);">
        <h3 style="margin-bottom:var(--space-md);">Change Status</h3>
        <form action="index.php?page=report-change-status" method="POST"
            style="display:flex; align-items:flex-end; gap:var(--space-sm);">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
            <div>
                <label for="report-status" class="form-label">Status</label>
                <select id="report-status" name="status" class="form-select" style="width:auto; min-width:160px;">
                    <?php
                    $statuses = ['pending', 'triaged', 'accepted', 'rejected', 'resolved'];
                    foreach ($statuses as $status):
                        ?>
                        <option value="<?= $status ?>" <?= $report['status'] === $status ? 'selected' : '' ?>>
                            <?= ucfirst($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary btn-sm">Update Status</button>
        </form>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../components/layout_end.php'; ?>