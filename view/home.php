<?php include 'view/components/header.php'; ?>

<!-- ── Hero ──────────────────────────────────────────────────── -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left copy -->
            <div class="col-lg-6">
                <p class="hero-eyebrow">
                    <i class="fa-solid fa-circle" style="font-size:6px; color:var(--success);"></i>
                    Vulnerability Disclosure Platform
                </p>
                <h1 class="hero-heading">
                    Where security research gets done.
                </h1>
                <p class="hero-sub">
                    SecureBounty connects organizations with security researchers through structured disclosure
                    workflows, transparent scoring, and fast triage.
                </p>
                <div class="hero-actions">
                    <a href="index.php?page=register" class="btn-primary-solid">
                        <i class="fa-solid fa-magnifying-glass" style="font-size:12px;"></i> Start hunting
                    </a>
                    <a href="index.php?page=register&role=owner" class="btn-ghost">
                        Launch a program
                    </a>
                </div>
            </div>

            <!-- Right — terminal card -->
            <div class="col-lg-6">
                <div class="terminal-card">
                    <div class="terminal-bar">
                        <span class="terminal-dot" style="background:var(--destructive);"></span>
                        <span class="terminal-dot" style="background:var(--accent);"></span>
                        <span class="terminal-dot" style="background:var(--success);"></span>
                        <span class="ms-3 font-mono"
                            style="font-size:12px; color:var(--muted-foreground);">report_submission.json</span>
                    </div>
                    <div class="terminal-body">
                        <span class="terminal-comment">// Incoming vulnerability report</span><br>
                        <span class="terminal-key">{</span><br>
                        &nbsp;&nbsp;<span class="terminal-key">"title"</span>: <span class="terminal-value">"SQL
                            Injection on /api/users"</span>,<br>
                        &nbsp;&nbsp;<span class="terminal-key">"severity"</span>: <span
                            class="terminal-value">"high"</span>,<br>
                        &nbsp;&nbsp;<span class="terminal-key">"cvss_score"</span>: <span
                            class="terminal-value">8.1</span>,<br>
                        &nbsp;&nbsp;<span class="terminal-key">"status"</span>: <span
                            class="terminal-prompt">"triaged"</span>,<br>
                        &nbsp;&nbsp;<span class="terminal-key">"points_awarded"</span>: <span
                            class="terminal-value">70</span>,<br>
                        &nbsp;&nbsp;<span class="terminal-key">"researcher"</span>: <span
                            class="terminal-value">"net_stalker"</span><br>
                        <span class="terminal-key">}</span>
                    </div>
                </div>

                <!-- Mini stats below terminal -->
                <div class="row g-3 mt-3">
                    <div class="col-4">
                        <div class="stat-block">
                            <div class="stat-number">500+</div>
                            <div class="stat-label">Researchers</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-block" style="border-left-color: var(--border);">
                            <div class="stat-number">120+</div>
                            <div class="stat-label">Programs</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-block" style="border-left-color: var(--border);">
                            <div class="stat-number">4.2h</div>
                            <div class="stat-label">Avg Triage</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── Stats strip ────────────────────────────────────────────── -->
<div style="border-top:1px solid var(--border); border-bottom:1px solid var(--border); background:var(--card);">
    <div class="container">
        <div class="row g-0">
            <?php
            $stats = [
                ['4,892', 'Vulnerabilities resolved'],
                ['$450K+', 'Total payouts'],
                ['99.4%', 'Satisfaction rate'],
                ['$2,500', 'Avg critical payout'],
            ];
            foreach ($stats as $i => $s):
                ?>
                <div class="col-6 col-md-3 py-4 px-4 <?php echo $i < 3 ? 'border-end' : ''; ?>"
                    style="border-color: var(--border) !important;">
                    <div
                        style="font-size:24px; font-weight:700; color:var(--foreground); font-variant-numeric:tabular-nums; letter-spacing:-0.02em; margin-bottom:4px;">
                        <?php echo $s[0]; ?>
                    </div>
                    <div
                        style="font-size:12px; font-weight:500; color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.05em;">
                        <?php echo $s[1]; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── How it works ───────────────────────────────────────────── -->
<section class="section">
    <div class="container">

        <!-- Section header -->
        <div class="row mb-5">
            <div class="col-lg-7">
                <p class="hero-eyebrow">Workflows</p>
                <h2 style="font-size:20px; margin-bottom:8px;">Built for both sides of security</h2>
                <p class="text-muted">SecureBounty structures every step — from discovery to resolution.</p>
            </div>
        </div>

        <div class="row g-4">

            <!-- Security Researcher card -->
            <div class="col-lg-6">
                <div class="card-surface h-100" style="border-top:3px solid var(--accent);">

                    <div class="d-flex align-items-center gap-3 mb-4 pb-4"
                        style="border-bottom:1px solid var(--border);">
                        <div
                            style="width:40px; height:40px; border-radius:var(--radius-lg); background:var(--accent-subtle); border:1px solid var(--accent-ring); display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-user-secret" style="color:var(--accent);"></i>
                        </div>
                        <div>
                            <h3 style="font-size:16px; margin-bottom:2px;">Security Researcher</h3>
                            <p class="text-muted mb-0" style="font-size:13px;">Hunt & earn</p>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-4">
                        <?php
                        $steps = [
                            ['Browse programs', 'Find programs with clear scope and reward tables.'],
                            ['Submit reports', 'Include PoC, CVSS estimate, and supporting evidence.'],
                            ['Track triage', 'Monitor status changes in real time via your dashboard.'],
                            ['Earn & rank', 'Collect points and cash bounties upon acceptance.'],
                        ];
                        foreach ($steps as $i => $step):
                            ?>
                            <div class="d-flex gap-3">
                                <div class="step-num"><?php echo $i + 1; ?></div>
                                <div>
                                    <p style="font-size:14px; font-weight:600; color:var(--foreground); margin-bottom:2px;">
                                        <?php echo $step[0]; ?></p>
                                    <p class="text-muted mb-0" style="font-size:13px; line-height:1.5;">
                                        <?php echo $step[1]; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>

            <!-- Program Owner card -->
            <div class="col-lg-6">
                <div class="card-surface h-100" style="border-top:3px solid var(--border);">

                    <div class="d-flex align-items-center gap-3 mb-4 pb-4"
                        style="border-bottom:1px solid var(--border);">
                        <div
                            style="width:40px; height:40px; border-radius:var(--radius-lg); background:var(--muted); border:1px solid var(--border); display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-building-shield" style="color:var(--muted-foreground);"></i>
                        </div>
                        <div>
                            <h3 style="font-size:16px; margin-bottom:2px;">Program Owner</h3>
                            <p class="text-muted mb-0" style="font-size:13px;">Manage & resolve</p>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-4">
                        <?php
                        $steps2 = [
                            ['Define program', 'Set scope, asset types, and reward tiers.'],
                            ['Receive reports', 'Structured notifications with full technical context.'],
                            ['Review & triage', 'Assign status, communicate with researchers directly.'],
                            ['Close the loop', 'Resolve findings, patch, and confirm with evidence.'],
                        ];
                        foreach ($steps2 as $i => $step):
                            ?>
                            <div class="d-flex gap-3">
                                <div class="step-num"
                                    style="background:var(--muted); border-color:var(--border); color:var(--muted-foreground);">
                                    <?php echo $i + 1; ?>
                                </div>
                                <div>
                                    <p style="font-size:14px; font-weight:600; color:var(--foreground); margin-bottom:2px;">
                                        <?php echo $step[0]; ?></p>
                                    <p class="text-muted mb-0" style="font-size:13px; line-height:1.5;">
                                        <?php echo $step[1]; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── Leaderboard preview ────────────────────────────────────── -->
<section class="section-sm"
    style="background:var(--card); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-4">
                <p class="hero-eyebrow">Leaderboard</p>
                <h2 style="font-size:20px; margin-bottom:8px;">Top researchers</h2>
                <p class="text-muted" style="font-size:14px;">Points are awarded per accepted report based on severity.
                    Climb the board to build your reputation.</p>
                <ul class="list-check mt-3">
                    <li><span class="check-icon"><i class="fa-solid fa-check"></i></span>Critical = 100 pts</li>
                    <li><span class="check-icon"><i class="fa-solid fa-check"></i></span>High = 70 pts</li>
                    <li><span class="check-icon"><i class="fa-solid fa-check"></i></span>Medium = 30 pts</li>
                    <li><span class="check-icon"><i class="fa-solid fa-check"></i></span>Low = 10 pts · Info = 5 pts
                    </li>
                </ul>
                <a href="#" class="btn-ghost mt-4 d-inline-flex">View full leaderboard</a>
            </div>

            <div class="col-lg-8">
                <div class="card-surface">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span style="font-size:14px; font-weight:600; color:var(--foreground);">Current standings</span>
                        <span class="chip chip-accent">
                            <i class="fa-solid fa-circle" style="font-size:6px;"></i> Live
                        </span>
                    </div>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Researcher</th>
                                <th>Points</th>
                                <th>Accepted</th>
                                <th>Last report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $board = [
                                ['net_stalker', '1,240', '34', '2h ago', 1],
                                ['zero_day_hero', '980', '26', '1d ago', 2],
                                ['byte_buster', '850', '19', '3d ago', 3],
                                ['null_pointer', '720', '15', '5d ago', 4],
                                ['xss_wizard', '640', '13', '1w ago', 5],
                            ];
                            foreach ($board as $r):
                                $rankClass = match ($r[4]) {
                                    1 => 'rank-1', 2 => 'rank-2', 3 => 'rank-3', default => 'rank-n'
                                };
                                ?>
                                <tr>
                                    <td><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $r[4]; ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar"><?php echo strtoupper($r[0][0]); ?></div>
                                            <span
                                                style="font-size:14px; font-weight:500; color:var(--foreground);"><?php echo $r[0]; ?></span>
                                        </div>
                                    </td>
                                    <td><span class="text-accent font-mono"
                                            style="font-size:14px;"><?php echo $r[1]; ?></span></td>
                                    <td><span class="text-muted" style="font-size:14px;"><?php echo $r[2]; ?></span></td>
                                    <td><span
                                            style="font-size:13px; color:var(--muted-foreground);"><?php echo $r[3]; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="card-surface text-center py-5"
            style="max-width:640px; margin:0 auto; border-left:3px solid var(--accent);">
            <h2 style="font-size:20px; margin-bottom:8px;">Ready to get started?</h2>
            <p class="text-muted mb-4" style="font-size:14px;">Join hundreds of researchers and organizations already
                using SecureBounty.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="index.php?page=register" class="btn-primary-solid">Create free account</a>
                <a href="index.php?page=contact" class="btn-ghost">Contact sales</a>
            </div>
        </div>
    </div>
</section>

<?php include 'view/components/footer.php'; ?>