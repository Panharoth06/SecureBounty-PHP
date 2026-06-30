<?php include 'view/components/header.php'; ?>

<!-- ── Page header ───────────────────────────────────────────── -->
<section class="section-sm" style="padding-top:80px;">
    <div class="container" style="max-width:680px;">
        <p class="hero-eyebrow">About the platform</p>
        <h1 style="font-size:30px; margin-bottom:12px;">Built to fix how security gets reported</h1>
        <p class="text-muted" style="font-size:16px; line-height:1.6;">
            SecureBounty is a native PHP platform that replaces fragmented email chains with a structured, traceable,
            and collaborative vulnerability disclosure workflow.
        </p>
    </div>
</section>

<!-- ── Problem & Objectives ──────────────────────────────────── -->
<section class="section-sm" style="border-top:1px solid var(--border);">
    <div class="container">
        <div class="row g-5">

            <div class="col-lg-6">
                <h2 style="font-size:20px; margin-bottom:16px;">The problem</h2>
                <p class="text-muted mb-4" style="font-size:14px; line-height:1.6;">
                    Most organizations handle incoming vulnerability reports through unstructured channels — resulting
                    in delays, lost context, and strained researcher relationships.
                </p>
                <ul class="list-unstyled d-flex flex-column gap-3">
                    <?php
                    $problems = [
                        ['fa-folder-open', 'No central location for incoming reports'],
                        ['fa-clock-rotate-left', 'Long patch windows due to poor triage'],
                        ['fa-triangle-exclamation', 'Inconsistent payout and communication'],
                        ['fa-link-slash', 'Disconnected researchers and engineering teams'],
                    ];
                    foreach ($problems as $p):
                        ?>
                        <li class="d-flex align-items-start gap-3">
                            <div
                                style="width:32px; height:32px; border-radius:var(--radius-sm); background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.12); display:flex; align-items:center; justify-content:center; color:var(--destructive); flex-shrink:0; margin-top:2px;">
                                <i class="fa-solid <?php echo $p[0]; ?>" style="font-size:12px;"></i>
                            </div>
                            <span class="text-muted" style="font-size:14px; line-height:1.5;"><?php echo $p[1]; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-lg-6">
                <h2 style="font-size:20px; margin-bottom:16px;">Our objectives</h2>
                <ul class="list-check">
                    <?php
                    $objs = [
                        'Role-based authentication for researchers, owners, and admins',
                        'Structured report submission with CVSS-aligned severity levels',
                        'Real-time status tracking: Submitted → Triaged → Resolved',
                        'Transparent point-based reputation system and public leaderboard',
                        'Direct comment threads between researchers and program teams',
                        'Secure file upload (PNG, JPG, PDF) for evidence attachments',
                    ];
                    foreach ($objs as $o):
                        ?>
                        <li>
                            <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                            <?php echo $o; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>
    </div>
</section>

<!-- ── User roles ─────────────────────────────────────────────── -->
<section class="section" style="border-top:1px solid var(--border); background:var(--card);">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-6">
                <h2 style="font-size:20px; margin-bottom:8px;">Three roles, one platform</h2>
                <p class="text-muted" style="font-size:14px;">Each user type gets a tailored dashboard and purpose-built
                    tools.</p>
            </div>
        </div>

        <div class="row g-4">
            <?php
            $roles = [
                [
                    'icon' => 'fa-user-secret',
                    'title' => 'Security Researcher',
                    'desc' => 'Ethical hackers who discover and report vulnerabilities. They submit reports, track triage status, earn reputation points, and receive bounty payouts.',
                    'perks' => ['Reputation leaderboard', 'Report status tracking', 'Direct team communication'],
                    'accent' => true,
                ],
                [
                    'icon' => 'fa-building-shield',
                    'title' => 'Program Owner',
                    'desc' => 'Organizations that launch and manage bug bounty programs. They define scope, review incoming reports, and coordinate remediation with internal teams.',
                    'perks' => ['Program scope builder', 'Report triage workflow', 'Reward management'],
                    'accent' => false,
                ],
                [
                    'icon' => 'fa-user-gear',
                    'title' => 'Administrator',
                    'desc' => 'Platform operators who oversee all users, programs, and reports. They manage verification, enforce rules, and monitor system health.',
                    'perks' => ['User management', 'Platform analytics', 'Program oversight'],
                    'accent' => false,
                ],
            ];
            foreach ($roles as $role):
                $iconColor = $role['accent'] ? 'var(--accent)' : 'var(--muted-foreground)';
                $bgColor = $role['accent'] ? 'var(--accent-subtle)' : 'var(--muted)';
                $bdrColor = $role['accent'] ? 'var(--accent-ring)' : 'var(--border)';
                ?>
                <div class="col-lg-4">
                    <div class="card-surface h-100">
                        <div
                            style="width:36px; height:36px; border-radius:var(--radius-md); background:<?php echo $bgColor; ?>; border:1px solid <?php echo $bdrColor; ?>; display:flex; align-items:center; justify-content:center; color:<?php echo $iconColor; ?>; margin-bottom:20px;">
                            <i class="fa-solid <?php echo $role['icon']; ?>" style="font-size:14px;"></i>
                        </div>
                        <h3 style="font-size:16px; margin-bottom:8px;"><?php echo $role['title']; ?></h3>
                        <p class="text-muted mb-3" style="font-size:14px; line-height:1.6;"><?php echo $role['desc']; ?></p>
                        <ul class="list-check">
                            <?php foreach ($role['perks'] as $perk): ?>
                                <li>
                                    <span class="check-icon" style="color:<?php echo $iconColor; ?>;"><i
                                            class="fa-solid fa-check"></i></span>
                                    <span style="font-size:13px;"><?php echo $perk; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Values ─────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <h2 style="font-size:20px; margin-bottom:40px;">What we stand for</h2>
        <div class="row g-4">
            <?php
            $values = [
                ['fa-lock', 'Privacy first', 'All report data is access-controlled. Researchers and owners only see what they need.'],
                ['fa-scale-balanced', 'Fair triaging', 'Severity is assessed objectively using CVSS-aligned guidelines.'],
                ['fa-bolt', 'Speed to patch', 'Structured states reduce the average time from submission to resolution.'],
                ['fa-comments', 'Direct dialogue', 'Comment threads keep researchers and engineers aligned without email noise.'],
            ];
            foreach ($values as $v):
                ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="d-flex gap-3">
                        <div style="color:var(--accent); flex-shrink:0; margin-top:2px;">
                            <i class="fa-solid <?php echo $v[0]; ?>" style="font-size:14px;"></i>
                        </div>
                        <div>
                            <p style="font-size:14px; font-weight:600; color:var(--foreground); margin-bottom:4px;">
                                <?php echo $v[1]; ?></p>
                            <p class="text-muted mb-0" style="font-size:13px; line-height:1.5;"><?php echo $v[2]; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'view/components/footer.php'; ?>