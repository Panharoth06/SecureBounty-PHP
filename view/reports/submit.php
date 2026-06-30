<?php
/**
 * Report Submission View
 *
 * Form for researchers to submit a vulnerability report with title, description,
 * steps to reproduce, impact, file upload, and inline CVSS 3.1 calculator.
 *
 * Variables available:
 * @var string $title       Page title
 * @var string $activePage  Active navigation item
 * @var int    $programId   Target program ID
 * @var string $programTitle Target program title
 * @var string $csrfToken   CSRF protection token
 * @var array  $errors      Validation errors (optional)
 * @var array  $old         Previous form values on validation failure (optional)
 *
 * @see Requirement 7.1, 7.2, 7.3
 */

$errors = $errors ?? [];
$old = $old ?? [];
?>
<?php $title = $title ?? 'Submit Report';
$activePage = $activePage ?? 'my-reports';
include __DIR__ . '/../components/layout.php'; ?>

<!-- Page Header -->
<div style="margin-bottom:var(--space-lg);">
    <nav style="font-size:var(--text-small); color:var(--muted-foreground); margin-bottom:var(--space-sm);">
        <a href="index.php?page=programs">Programs</a>
        <span> / </span>
        <a href="index.php?page=program-detail&amp;id=<?= (int) $programId ?>">
            <?= htmlspecialchars($programTitle ?? 'Program', ENT_QUOTES, 'UTF-8') ?>
        </a>
        <span> / </span>
        <span>Submit Report</span>
    </nav>
    <h1 style="font-size:var(--text-heading); font-weight:600;">Submit Vulnerability Report</h1>
</div>

<!-- Report Submission Form -->
<form action="index.php?page=process-report-submit" method="POST" enctype="multipart/form-data"
    style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="program_id" value="<?= (int) $programId ?>">

    <!-- Title -->
    <div style="margin-bottom:var(--space-md);">
        <label for="report-title" class="form-label">Title <span style="color:var(--status-rejected-text);">*</span></label>
        <input type="text" id="report-title" name="title"
            class="form-input <?= isset($errors['title']) ? 'is-error' : '' ?>"
            placeholder="Brief summary of the vulnerability"
            value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        <?php if (isset($errors['title'])): ?>
            <p class="error-msg">
                <?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Description (optional) -->
    <div style="margin-bottom:var(--space-lg);">
        <label for="report-description" class="form-label">Description <span style="font-size:var(--text-small); color:var(--muted-foreground); font-weight:normal;">(optional)</span></label>
        <textarea id="report-description" name="description"
            class="form-textarea"
            placeholder="Detailed description of the vulnerability, steps to reproduce, impact, etc."
            style="min-height:150px;"><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>

    <!-- File Upload -->
    <div style="margin-bottom:var(--space-lg);">
        <label for="report-attachment" class="form-label">Attachment <span style="font-size:var(--text-small); color:var(--muted-foreground); font-weight:normal;">(optional)</span></label>
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
            Optional. Select metrics to compute a CVSS base score.
        </p>

        <!-- Exploitability Metrics -->
        <p
            style="font-size:var(--text-caption); font-weight:500; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted-foreground); margin-bottom:var(--space-md);">
            Exploitability Metrics
        </p>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Attack Vector (AV)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                How the vulnerability is exploited. Network = remotely over the internet; Adjacent = via shared network
                (e.g. Bluetooth, Wi-Fi); Local = requires local system access; Physical = requires physical device
                access.
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
                The conditions beyond the attacker's control that must exist to exploit the vulnerability. Low = no
                special conditions needed; High = requires specific configuration or timing.
            </p>
            <div class="cvss-toggle-group" data-metric="AC">
                <button type="button" class="cvss-btn" data-value="L" title="Low">L</button>
                <button type="button" class="cvss-btn" data-value="H" title="High">H</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Privileges Required (PR)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                The level of privileges an attacker must have before exploitation. None = no prior access needed; Low =
                basic user account; High = admin or elevated privileges required.
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
                Whether a user other than the attacker must participate for the exploit to succeed. None = no victim
                action needed; Required = victim must click a link, open a file, etc.
            </p>
            <div class="cvss-toggle-group" data-metric="UI">
                <button type="button" class="cvss-btn" data-value="N" title="None">N</button>
                <button type="button" class="cvss-btn" data-value="R" title="Required">R</button>
            </div>
        </div>

        <div style="margin-bottom:var(--space-md);">
            <label class="form-label">Scope (S)</label>
            <p style="font-size:var(--text-small); color:var(--muted-foreground); margin:0 0 8px 0;">
                Whether the vulnerability impacts resources beyond its security scope. Unchanged = only the vulnerable
                component is affected; Changed = other components (e.g. the underlying OS or other applications) are
                also impacted.
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
                The impact on data confidentiality. None = no disclosure; Low = some restricted data exposed; High =
                total information disclosure (e.g. all files, credentials, database).
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
                The impact on data integrity. None = no modification possible; Low = some data can be modified but scope
                is limited; High = attacker can modify any data, with serious consequences.
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
                The impact on system availability. None = no disruption; Low = reduced performance or minor service
                interruption; High = complete denial of service or total shutdown of the affected resource.
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
            value="<?= htmlspecialchars($old['cvss_vector'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="cvss_score" id="cvss-score-input"
            value="<?= htmlspecialchars($old['cvss_score'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="cvss_severity" id="cvss-severity-input"
            value="<?= htmlspecialchars($old['cvss_severity'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <!-- Submit Button -->
    <div style="display:flex; align-items:center; gap:var(--space-sm);">
        <button type="submit" class="btn-primary">Submit Report</button>
        <a href="index.php?page=program-detail&amp;id=<?= (int) $programId ?>" class="btn-secondary">Cancel</a>
    </div>
</form>

<!-- CVSS Calculator JavaScript -->
<script>
    (function () {
        const metrics = {};
        const AV_WEIGHTS = { N: 0.85, A: 0.62, L: 0.55, P: 0.20 };
        const AC_WEIGHTS = { L: 0.77, H: 0.44 };
        const PR_WEIGHTS_UNCHANGED = { N: 0.85, L: 0.62, H: 0.27 };
        const PR_WEIGHTS_CHANGED = { N: 0.85, L: 0.68, H: 0.50 };
        const UI_WEIGHTS = { N: 0.85, R: 0.62 };
        const C_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };
        const I_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };
        const A_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };

        function roundUp(value) {
            return Math.ceil(value * 10) / 10.0;
        }

        function calculateScore() {
            const required = ['AV', 'AC', 'PR', 'UI', 'S', 'C', 'I', 'A'];
            for (let i = 0; i < required.length; i++) {
                if (!metrics[required[i]]) return null;
            }

            const scopeChanged = metrics.S === 'C';
            const prWeight = scopeChanged ? PR_WEIGHTS_CHANGED[metrics.PR] : PR_WEIGHTS_UNCHANGED[metrics.PR];

            const iss = 1 - ((1 - C_WEIGHTS[metrics.C]) * (1 - I_WEIGHTS[metrics.I]) * (1 - A_WEIGHTS[metrics.A]));

            let impact;
            if (scopeChanged) {
                impact = 7.52 * (iss - 0.029) - 3.25 * Math.pow(iss - 0.02, 15);
            } else {
                impact = 6.42 * iss;
            }

            if (impact <= 0) return 0.0;

            const exploitability = 8.22 * AV_WEIGHTS[metrics.AV] * AC_WEIGHTS[metrics.AC] * prWeight * UI_WEIGHTS[metrics.UI];

            let baseScore;
            if (scopeChanged) {
                baseScore = roundUp(Math.min(1.08 * (impact + exploitability), 10.0));
            } else {
                baseScore = roundUp(Math.min(impact + exploitability, 10.0));
            }

            return baseScore;
        }

        function deriveSeverity(score) {
            if (score === 0.0) return 'none';
            if (score <= 3.9) return 'low';
            if (score <= 6.9) return 'medium';
            if (score <= 8.9) return 'high';
            return 'critical';
        }

        function buildVector() {
            const order = ['AV', 'AC', 'PR', 'UI', 'S', 'C', 'I', 'A'];
            const parts = order.map(function (m) { return m + ':' + metrics[m]; });
            return 'CVSS:3.1/' + parts.join('/');
        }

        function updateDisplay() {
            const score = calculateScore();
            const resultEl = document.getElementById('cvss-result');
            const placeholderEl = document.getElementById('cvss-placeholder');

            if (score === null) {
                resultEl.style.display = 'none';
                placeholderEl.style.display = 'block';
                document.getElementById('cvss-vector-input').value = '';
                document.getElementById('cvss-score-input').value = '';
                document.getElementById('cvss-severity-input').value = '';
                return;
            }

            const severity = deriveSeverity(score);
            const vector = buildVector();

            resultEl.style.display = 'block';
            placeholderEl.style.display = 'none';

            document.getElementById('cvss-score-display').textContent = score.toFixed(1);
            document.getElementById('cvss-vector-display').textContent = vector;

            const badgeClasses = {
                none: 'badge-informational',
                low: 'badge-low',
                medium: 'badge-medium',
                high: 'badge-high',
                critical: 'badge-critical'
            };
            document.getElementById('cvss-severity-badge').className = badgeClasses[severity] || 'badge-informational';
            document.getElementById('cvss-severity-badge').textContent = severity.charAt(0).toUpperCase() + severity.slice(1);

            document.getElementById('cvss-vector-input').value = vector;
            document.getElementById('cvss-score-input').value = score.toFixed(1);
            document.getElementById('cvss-severity-input').value = severity;
        }

        // Bind toggle buttons
        document.querySelectorAll('.cvss-toggle-group').forEach(function (group) {
            const metric = group.getAttribute('data-metric');
            group.querySelectorAll('.cvss-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    // Deselect siblings
                    group.querySelectorAll('.cvss-btn').forEach(function (b) {
                        b.classList.remove('cvss-btn-active');
                    });
                    // Select this button
                    btn.classList.add('cvss-btn-active');
                    metrics[metric] = btn.getAttribute('data-value');
                    updateDisplay();
                });
            });
        });
    })();
</script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>