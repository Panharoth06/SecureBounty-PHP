<?php include 'view/components/header.php'; ?>

<!-- ── Page header ───────────────────────────────────────────── -->
<section class="section-sm" style="padding-top:80px; padding-bottom:32px;">
    <div class="container">
        <p class="hero-eyebrow">Documentation</p>
        <h1 style="font-size:30px; margin-bottom:8px;">Platform guide</h1>
        <p class="text-muted" style="font-size:14px;">Everything you need to submit reports, launch programs, and
            understand the rules.</p>
    </div>
</section>

<!-- ── Doc layout ────────────────────────────────────────────── -->
<section class="section-sm" style="padding-top:0; padding-bottom:80px;">
    <div class="container">
        <div class="row g-5">

            <!-- Sidebar -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="docs-sidebar">

                    <p class="docs-group-label">Getting started</p>
                    <nav>
                        <a class="docs-nav-link active" href="#intro">Introduction</a>
                        <a class="docs-nav-link" href="#quickstart">Quickstart</a>
                    </nav>

                    <p class="docs-group-label mt-4">Policy</p>
                    <nav>
                        <a class="docs-nav-link" href="#engagement">Rules of engagement</a>
                        <a class="docs-nav-link" href="#scope">Scoping targets</a>
                    </nav>

                    <p class="docs-group-label mt-4">Rewards</p>
                    <nav>
                        <a class="docs-nav-link" href="#rewards">Severity &amp; scoring</a>
                    </nav>

                </div>
            </div>

            <!-- Content -->
            <div class="col-lg-9">
                <div class="docs-content">

                    <!-- Introduction -->
                    <div id="intro" class="docs-section">
                        <h2 style="font-size:20px; margin-bottom:16px;">Introduction</h2>
                        <p class="text-muted" style="font-size:14px; line-height:1.6;">
                            SecureBounty is a native PHP platform that structures the entire vulnerability lifecycle —
                            from initial submission to confirmed resolution — in one place.
                        </p>
                        <p class="text-muted mb-0" style="font-size:14px; line-height:1.6;">
                            Reports move through clearly defined states:
                            <code>Submitted</code> → <code>Under Review</code> → <code>Triaged</code> →
                            <code>Resolved</code>
                            (or <code>Duplicate</code> / <code>Rejected</code>).
                            All communication happens in-platform through threaded comments.
                        </p>
                        <div class="callout callout-info mt-4">
                            <span class="callout-icon"><i class="fa-solid fa-circle-info"
                                    style="font-size:14px;"></i></span>
                            <span>Evidence uploads are restricted to <code>PNG</code>, <code>JPG</code>, and
                                <code>PDF</code> formats. Max 10 MB per file.</span>
                        </div>
                    </div>

                    <!-- Quickstart -->
                    <div id="quickstart" class="docs-section">
                        <h2 style="font-size:20px; margin-bottom:16px;">Quickstart</h2>

                        <h3 style="font-size:16px; margin-bottom:12px;">1. Register your account</h3>
                        <p class="text-muted mb-3" style="font-size:14px;">Choose a role during registration:</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card-elevated" style="border-left:3px solid var(--accent);">
                                    <p
                                        style="font-size:14px; font-weight:600; color:var(--foreground); margin-bottom:4px;">
                                        <i class="fa-solid fa-user-secret me-1" style="color:var(--accent);"></i>
                                        Security Researcher</p>
                                    <p class="text-muted mb-0" style="font-size:13px;">Hunt for bugs, earn points,
                                        receive bounties.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-elevated" style="border-left:3px solid var(--border);">
                                    <p
                                        style="font-size:14px; font-weight:600; color:var(--foreground); margin-bottom:4px;">
                                        <i class="fa-solid fa-building me-1" style="color:var(--muted-foreground);"></i>
                                        Program Owner</p>
                                    <p class="text-muted mb-0" style="font-size:13px;">Launch programs, review reports,
                                        coordinate fixes.</p>
                                </div>
                            </div>
                        </div>

                        <h3 style="font-size:16px; margin-bottom:12px;">2. Submit your first report</h3>
                        <p class="text-muted mb-3" style="font-size:14px;">Use the structured template below when
                            submitting a report via your dashboard:</p>
                        <pre><code># Report title
Concise description (e.g. "SQL Injection on POST /api/login")

## Severity
[Low | Medium | High | Critical]

## Affected endpoint
https://target.example.com/api/login

## Steps to reproduce
1. Navigate to the login endpoint.
2. Set the username field to: ' OR 1=1--
3. Observe the response — authentication bypassed.</code></pre>
                    </div>

                    <!-- Rules of engagement -->
                    <div id="engagement" class="docs-section">
                        <h2 style="font-size:20px; margin-bottom:16px;">Rules of engagement</h2>
                        <p class="text-muted mb-4" style="font-size:14px;">Violating these rules results in immediate
                            account suspension and forfeiture of pending rewards.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card-elevated h-100" style="border-left:3px solid var(--destructive);">
                                    <p class="mb-3"
                                        style="font-size:12px; font-weight:600; color:var(--destructive); text-transform:uppercase; letter-spacing:0.05em;">
                                        <i class="fa-solid fa-ban me-1"></i> Prohibited
                                    </p>
                                    <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                        <?php
                                        $prohibited = [
                                            'DDoS or availability testing',
                                            'Social engineering of employees',
                                            'Exfiltrating or altering user data',
                                            'Public disclosure before patch window',
                                        ];
                                        foreach ($prohibited as $p):
                                            ?>
                                            <li class="d-flex gap-2 align-items-start">
                                                <i class="fa-solid fa-xmark mt-1"
                                                    style="color:var(--destructive); flex-shrink:0; font-size:12px;"></i>
                                                <span class="text-muted" style="font-size:14px;"><?php echo $p; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-elevated h-100" style="border-left:3px solid var(--success);">
                                    <p class="mb-3"
                                        style="font-size:12px; font-weight:600; color:var(--success); text-transform:uppercase; letter-spacing:0.05em;">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Safe harbor
                                    </p>
                                    <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                        <?php
                                        $safe = [
                                            'Testing only in-scope assets',
                                            'Using benign, non-destructive payloads',
                                            'Reporting promptly through the platform',
                                            'Protecting user privacy throughout',
                                        ];
                                        foreach ($safe as $s):
                                            ?>
                                            <li class="d-flex gap-2 align-items-start">
                                                <i class="fa-solid fa-check mt-1"
                                                    style="color:var(--success); flex-shrink:0; font-size:12px;"></i>
                                                <span class="text-muted" style="font-size:14px;"><?php echo $s; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scope -->
                    <div id="scope" class="docs-section">
                        <h2 style="font-size:20px; margin-bottom:16px;">Scoping targets</h2>
                        <p class="text-muted mb-4" style="font-size:14px; line-height:1.6;">
                            Each program defines exactly what is in and out of scope. The example below shows the
                            <strong style="color:var(--foreground);">University Demo Program</strong> scope table:
                        </p>
                        <div class="table-responsive">
                            <table class="tbl">
                                <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>demo.university.edu</code></td>
                                        <td><span class="chip chip-neutral">Web app</span></td>
                                        <td><span class="sev-low"><i class="fa-solid fa-circle"
                                                    style="font-size:6px; margin-right:4px;"></i>In scope</span></td>
                                    </tr>
                                    <tr>
                                        <td><code>api.university.edu</code></td>
                                        <td><span class="chip chip-neutral">REST API</span></td>
                                        <td><span class="sev-low"><i class="fa-solid fa-circle"
                                                    style="font-size:6px; margin-right:4px;"></i>In scope</span></td>
                                    </tr>
                                    <tr>
                                        <td><code>*.thirdparty-hosting.com</code></td>
                                        <td><span class="chip chip-neutral">SaaS / CDN</span></td>
                                        <td><span class="sev-critical"><i class="fa-solid fa-circle"
                                                    style="font-size:6px; margin-right:4px;"></i>Out of scope</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rewards -->
                    <div id="rewards" class="docs-section"
                        style="border-bottom:none; padding-bottom:0; margin-bottom:0;">
                        <h2 style="font-size:20px; margin-bottom:16px;">Severity &amp; scoring</h2>
                        <p class="text-muted mb-4" style="font-size:14px; line-height:1.6;">
                            Points are awarded automatically when a report is marked <code>Triaged</code> or
                            <code>Resolved</code> by the program owner.
                        </p>
                        <div class="table-responsive">
                            <table class="tbl">
                                <thead>
                                    <tr>
                                        <th>Severity</th>
                                        <th>Points</th>
                                        <th>Example vulnerability types</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rewards = [
                                        ['Critical', '100', 'var(--sev-critical)', 'RCE, Auth bypass, SQL injection (write)'],
                                        ['High', '70', 'var(--sev-high)', 'SSRF, Broken access control, SQL injection (read)'],
                                        ['Medium', '30', 'var(--sev-medium)', 'Stored XSS, CSRF on critical actions, IDOR'],
                                        ['Low', '10', 'var(--sev-low)', 'Reflected XSS, Session fixation, Missing headers'],
                                        ['Informational', '5', 'var(--sev-info)', 'Version disclosure, SSL weak ciphers, Verbose errors'],
                                    ];
                                    foreach ($rewards as $r):
                                        ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight:600; color:<?php echo $r[2]; ?>;">
                                                    <?php echo $r[0]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="font-mono"
                                                    style="font-size:14px; font-weight:700; color:var(--foreground);"><?php echo $r[1]; ?></span>
                                            </td>
                                            <td class="text-muted" style="font-size:13px;"><?php echo $r[3]; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /docs-content -->
            </div>
        </div>
    </div>
</section>

<!-- Docs sidebar active-link scroll script -->
<script>
    (function () {
        const links = document.querySelectorAll('.docs-nav-link');
        const sections = document.querySelectorAll('.docs-section');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + id));
                }
            });
        }, { rootMargin: '-80px 0px -60% 0px' });

        sections.forEach(s => observer.observe(s));
    })();
</script>

<?php include 'view/components/footer.php'; ?>