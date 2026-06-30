<?php
/**
 * Report Management List View
 *
 * Displays all reports in a single table with filter dropdowns and action buttons.
 *
 * Variables available:
 * @var string $title       Page title
 * @var string $activePage  Active navigation item
 * @var array  $reports     Array of report records grouped by status
 * @var string $csrfToken   CSRF token (from controller)
 */

$reports = $reports ?? [];
$csrfToken = $csrfToken ?? '';
$statuses = ['pending', 'triaged', 'accepted', 'rejected', 'resolved'];

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isResearcherView = ($roleId === 3);

// Flatten all reports
$allReports = [];
foreach ($statuses as $s) {
    foreach ($reports[$s] ?? [] as $rpt) {
        $allReports[] = $rpt;
    }
}
$totalAll = count($allReports);
?>
<?php $title = $title ?? 'Reports';
$activePage = $activePage ?? 'reports';
include __DIR__ . '/../components/layout.php'; ?>

<!-- Page Header -->
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:var(--space-lg);">
    <h1 style="font-size:var(--text-heading); font-weight:600;">Reports</h1>
</div>

<!-- Filter Bar -->
<div style="display:flex; align-items:center; gap:var(--space-sm); margin-bottom:var(--space-md); flex-wrap:wrap;">
    <i data-lucide="filter" style="width:16px; height:16px; color:var(--muted-foreground);"></i>
    <label for="filter-status" style="font-size:var(--text-small); color:var(--muted-foreground);">Status:</label>
    <select id="filter-status" class="form-select" style="width:auto; min-width:130px; padding:6px 10px; font-size:var(--text-small);">
        <option value="all">All</option>
        <option value="pending">Pending</option>
        <option value="triaged">Triaged</option>
        <option value="accepted">Accepted</option>
        <option value="rejected">Rejected</option>
        <option value="resolved">Resolved</option>
    </select>
    <label for="filter-severity" style="font-size:var(--text-small); color:var(--muted-foreground); margin-left:var(--space-sm);">Severity:</label>
    <select id="filter-severity" class="form-select" style="width:auto; min-width:130px; padding:6px 10px; font-size:var(--text-small);">
        <option value="all">All</option>
        <option value="critical">Critical</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
        <option value="informational">Informational</option>
    </select>
    <span style="font-size:var(--text-small); color:var(--muted-foreground); margin-left:auto;" id="report-count"><?= $totalAll ?> report<?= $totalAll !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($allReports)): ?>
    <div style="text-align:center; padding:var(--space-2xl) 0;">
        <i data-lucide="inbox" style="width:32px; height:32px; color:var(--muted-foreground); margin-bottom:var(--space-sm);"></i>
        <p style="font-size:var(--text-body); color:var(--muted-foreground);">No reports yet</p>
    </div>
<?php else: ?>
    <table class="table" id="reports-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Program</th>
                <th>Researcher</th>
                <th>Status</th>
                <th>Severity</th>
                <th>Submitted</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allReports as $rpt):
                $rptStatus = $rpt['status'] ?? 'pending';
                $rptSeverity = $rpt['final_severity'] ?? $rpt['cvss_severity'] ?? '';
                $isOwnReport = ($isResearcherView && (int) ($rpt['researcher_id'] ?? 0) === $currentUserId);
            ?>
                <tr data-status="<?= htmlspecialchars($rptStatus, ENT_QUOTES, 'UTF-8') ?>" data-severity="<?= htmlspecialchars($rptSeverity, ENT_QUOTES, 'UTF-8') ?>"
                    style="cursor:pointer;" onclick="if(!event.target.closest('a,button,form'))window.location='index.php?page=report-detail&id=<?= (int) $rpt['id'] ?>'">
                    <td style="color:var(--foreground); font-weight:500;">
                        <a href="index.php?page=report-detail&amp;id=<?= (int) $rpt['id'] ?>" style="color:var(--foreground); text-decoration:none;">
                            <?= htmlspecialchars($rpt['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($rpt['program_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(($rpt['researcher_first_name'] ?? '') . ' ' . ($rpt['researcher_last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php
                        $statusClass = match ($rptStatus) {
                            'pending' => 'badge-pending',
                            'triaged' => 'badge-triaged',
                            'accepted' => 'badge-accepted',
                            'rejected' => 'badge-rejected',
                            'resolved' => 'badge-resolved',
                            default => 'badge-pending',
                        };
                        ?>
                        <span class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($rptStatus), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td>
                        <?php if ($rptSeverity): ?>
                            <?php $type = 'severity'; $value = $rptSeverity; include __DIR__ . '/../components/badge.php'; ?>
                        <?php else: ?>
                            <span style="font-size:var(--text-small); color:var(--muted-foreground);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:var(--text-small); white-space:nowrap;">
                        <?= htmlspecialchars(date('M j, Y', strtotime($rpt['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="index.php?page=report-detail&amp;id=<?= (int) $rpt['id'] ?>" class="btn-secondary" style="padding:4px 8px; font-size:var(--text-caption);">View</a>
                        <?php if ($isOwnReport): ?>
                            <a href="index.php?page=report-edit&amp;id=<?= (int) $rpt['id'] ?>" class="btn-secondary" style="padding:4px 8px; font-size:var(--text-caption); margin-left:4px;">Edit</a>
                            <form method="POST" action="index.php?page=report-delete" style="display:inline; margin-left:4px;"
                                onsubmit="return confirm('Delete this report? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="report_id" value="<?= (int) $rpt['id'] ?>">
                                <button type="submit" class="btn-destructive" style="padding:4px 8px; font-size:var(--text-caption);">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Filter Script -->
<script>
(function () {
    const filterStatus = document.getElementById('filter-status');
    const filterSeverity = document.getElementById('filter-severity');
    const table = document.getElementById('reports-table');
    const countEl = document.getElementById('report-count');

    function applyFilters() {
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        const status = filterStatus.value;
        const severity = filterSeverity.value;
        let visible = 0;

        rows.forEach(function (row) {
            const rowStatus = row.getAttribute('data-status');
            const rowSeverity = row.getAttribute('data-severity');
            const matchStatus = (status === 'all' || rowStatus === status);
            const matchSeverity = (severity === 'all' || rowSeverity === severity);
            const show = matchStatus && matchSeverity;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countEl) {
            countEl.textContent = visible + ' report' + (visible !== 1 ? 's' : '');
        }
    }

    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (filterSeverity) filterSeverity.addEventListener('change', applyFilters);
})();
</script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>
