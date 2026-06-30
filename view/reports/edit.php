<?php
/**
 * Report Edit View
 *
 * Form for researchers to edit their submitted report (title, description, file upload, CVSS).
 *
 * Variables available:
 * @var string $title      Page title
 * @var string $activePage Active navigation item
 * @var array  $report     Report data
 * @var string $csrfToken  CSRF token
 * @var array  $errors     Validation errors (optional)
 * @var array  $old        Previous form values on validation failure (optional)
 */

$errors = $errors ?? [];
$old = $old ?? [];
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Breadcrumb -->
<nav style="font-size:var(--text-small); color:var(--muted-foreground); margin-bottom:var(--space-md);">
    <a href="index.php?page=report-detail&amp;id=<?= (int) $report['id'] ?>">← Back to Report</a>
</nav>

<div style="margin-bottom:var(--space-lg);">
    <h1 style="font-size:var(--text-heading); font-weight:600;">Edit Report</h1>
</div>

<form action="index.php?page=process-report-edit" method="POST" enctype="multipart/form-data" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

    <!-- Title -->
    <div style="margin-bottom:var(--space-md);">
        <label for="report-title" class="form-label">Title <span
                style="color:var(--status-rejected-text);">*</span></label>
        <input type="text" id="report-title" name="title"
            class="form-input <?= isset($errors['title']) ? 'is-error' : '' ?>"
            value="<?= htmlspecialchars($old['title'] ?? $report['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        <?php if (isset($errors['title'])): ?>
            <p class="error-msg"><?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <!-- Description -->
    <div style="margin-bottom:var(--space-md);">
        <label for="report-description" class="form-label">Description <span
                style="font-size:var(--text-small); color:var(--muted-foreground); font-weight:normal;">(optional)</span></label>
        <textarea id="report-description" name="description" class="form-textarea"
            placeholder="Detailed description of the vulnerability, steps to reproduce, impact, etc."
            style="min-height:150px;"><?= htmlspecialchars($old['description'] ?? $report['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <!-- File Upload -->
    <div style="margin-bottom:var(--space-lg);">
        <label for="report-attachment" class="form-label">Attachment <span
                style="font-size:var(--text-small); color:var(--muted-foreground); font-weight:normal;">(optional — adds
                new file)</span></label>
        <input type="file" id="report-attachment" name="attachment" class="form-input"
            accept=".png,.jpg,.gif,.pdf,.txt,.zip" style="height:auto; padding:8px 12px;">
        <p style="font-size:var(--text-small); color:var(--muted-foreground); margin-top:var(--space-xs);">
            Allowed: PNG, JPG, GIF, PDF, TXT, ZIP. Max 10 MB.
        </p>
    </div>

    <!-- CVSS Calculator Widget -->
    <div class="card" style="margin-bottom:var(--space-lg);">
        <h3 style="margin-bottom:var(--space-md);">CVSS 3.1 Calculator</h3>
        <p style="font-size:var(--text-small); color:var(--muted-foreground); margin-bottom:var(--space-md);">
            Optional. Select metrics to update the CVSS base score.
        </p>

        <!-- Exploitability Metrics -->
        <p
            style="font-size:var(--text-caption); font-weight:500; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted-foreground); margin-bottom:var(--space-md);">
            Exploitability Metrics
        </p>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Attack Vector (AV)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                How the vulnerability is exploited. Network = remotely; Adjacent = shared network; Local = local access;
                Physical = physical access.
            </p>
            <div class="cvss-toggle-group" data-metric="AV">
                <button type="button" class="cvss-btn" data-value="N" title="Network">N</button>
                <button type="button" class="cvss-btn" data-value="A" title="Adjacent">A</button>
                <button type="button" class="cvss-btn" data-value="L" title="Local">L</button>
                <button type="button" class="cvss-btn" data-value="P" title="Physical">P</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Attack Complexity (AC)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Conditions beyond attacker control. Low = no special conditions; High = specific configuration needed.
            </p>
            <div class="cvss-toggle-group" data-metric="AC">
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Privileges Required (PR)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Required privilege level. None = no access; Low = basic user; High = admin privileges.
            </p>
            <div class="cvss-toggle-group" data-metric="PR">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">User Interaction (UI)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Whether victim action is needed. None = no action; Required = user must interact.
            </p>
            <div class="cvss-toggle-group" data-metric="UI">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="R" title="Required">R</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Scope (S)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Impact beyond the vulnerable component. Unchanged = same component; Changed = other components affected.
            </p>
            <div class="cvss-toggle-group" data-metric="S">
                <button type="button" class="cvss-btn" data-value="U" title="Unchanged">U</button>
                <button type="button" class="cvss-btn" data-value="C" title="Changed">C</button>
            </div>
        </div>

        <!-- Impact Metrics -->
        <hr style="border:none; border-top:1px solid var(--border); margin:var(--space-md) 0;">
        <p
            style="font-size:var(--text-caption); font-weight:500; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted-foreground); margin-bottom:var(--space-md);">
            Impact Metrics
        </p>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Confidentiality (C)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Data disclosure impact. None = no disclosure; Low = partial; High = total disclosure.
            </p>
            <div class="cvss-toggle-group" data-metric="C">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Integrity (I)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Data modification impact. None = no modification; Low = limited; High = any data modifiable.
            </p>
            <div class="cvss-toggle-group" data-metric="I">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Availability (A)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Service disruption impact. None = no disruption; Low = reduced performance; High = total denial of
                service.
            </p>
            <div class="cvss-toggle-group" data-metric="A">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <!-- CVSS Result -->
        <hr style="border:none; border-top:1px solid var(--border); margin:var(--space-md) 0;">
        <div id="cvss-result" style="display:none;">
            <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:var(--space-sm);">
                <span style="font-size:var(--text-heading); font-weight:700;" id="cvss-score-display">0.0</span>
                <span id="cvss-severity-badge"></span>
            </div>
            <p style="font-family:var(--font-mono); font-size:var(--text-small); color:var(--muted-foreground); word-break:break-all;"
                id="cvss-vector-display"></p>
        </div>
        <div id="cvss-placeholder" style="color:var(--muted-foreground); font-size:var(--text-small);">
            Select all metrics above to calculate the CVSS score.
        </div>

        <!-- Hidden inputs for CVSS values -->
        <input type="hidden" name="cvss_vector" id="cvss-vector-input"
            value="<?= htmlspecialchars($old['cvss_vector'] ?? $report['cvss_vector'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="cvss_score" id="cvss-score-input"
            value="<?= htmlspecialchars($old['cvss_score'] ?? $report['cvss_score'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="cvss_severity" id="cvss-severity-input"
            value="<?= htmlspecialchars($old['cvss_severity'] ?? $report['cvss_severity'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <!-- Actions -->
    <div style="display:flex; align-items:center; gap:var(--space-sm);">
        <button type="submit" class="btn-primary">Save Changes</button>
        <a href="index.php?page=report-detail&amp;id=<?= (int) $report['id'] ?>" class="btn-secondary">Cancel</a>
    </div>
</form>

<!-- CVSS Calculator JavaScript -->
<script src="view/assets/cvss-calculator.js"></script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>