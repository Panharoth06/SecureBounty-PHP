<?php
/**
 * Program Detail View
 *
 * Displays full program information, reward policies, and action buttons.
 * Researchers can save/unsave and submit reports. Program Owners see edit/publish/close controls.
 * YouTube-style threaded comment section at the bottom.
 *
 * Variables available from ProgramController::detail():
 *   $title          - Page title
 *   $activePage     - Active navigation item ('programs')
 *   $program        - Program data array
 *   $rewardPolicies - Array of reward policy rows
 *   $isEnrolled     - Whether the current researcher is enrolled (bool)
 *   $isSaved        - Whether the current researcher has bookmarked this program (bool)
 *   $programComments- Array of program comments
 *   $csrfToken      - CSRF token for forms
 *   $success        - Flash success message (nullable)
 *   $error          - Flash error message (nullable)
 */

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isOwner = ($roleId === 2 && (int) $program['owner_id'] === $userId);
$isResearcher = ($roleId === 3);
$status = $program['status'] ?? 'draft';
?>
<?php include __DIR__ . '/../components/layout.php'; ?>

<!-- Breadcrumb -->
<nav style="margin-bottom:var(--space-md);">
    <a href="index.php?page=programs"
        style="color:var(--muted-foreground); font-size:var(--text-small); text-decoration:none;">
        ← Back to Programs
    </a>
</nav>

<!-- Flash Messages -->
<?php if (!empty($success)): ?>
    <div style="background:var(--status-accepted-bg); border:1px solid var(--status-accepted-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-accepted-text); font-size:var(--text-body);">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:var(--space-md); color:var(--status-rejected-text); font-size:var(--text-body);">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php
$logoPath = $logoPath ?? ($program['logo_path'] ?? null);
$programStatistics = $programStatistics ?? ['report_count' => 0, 'enrolled_count' => 0, 'response_rate' => null, 'badges' => ['responsive' => false, 'popular' => false]];
$assets = $assets ?? [];
$programTags = $programTags ?? [];
?>

<!-- Program Header -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:var(--space-md);">
        <div style="display:flex; gap:var(--space-md); align-items:flex-start; flex:1; min-width:0;">
            <?php if (!empty($logoPath)): ?>
                <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($program['title'] . ' logo', ENT_QUOTES, 'UTF-8') ?>"
                    style="width:64px; height:64px; object-fit:cover; border-radius:var(--radius-md); border:1px solid var(--border); flex-shrink:0;">
            <?php else: ?>
                <div aria-hidden="true"
                    style="width:64px; height:64px; display:flex; align-items:center; justify-content:center; background:var(--muted); color:var(--foreground); font-weight:600; font-size:var(--text-heading); border-radius:var(--radius-md); border:1px solid var(--border); flex-shrink:0;">
                    <?= htmlspecialchars(FormattingService::logoPlaceholder($program['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <div style="flex:1; min-width:0;">
                <h1 style="font-size:var(--text-heading); font-weight:600; margin:0 0 8px 0;">
                    <?= htmlspecialchars($program['title'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php $statusClass = match ($status) { 'active' => 'badge-accepted', 'draft' => 'badge-pending', 'closed' => 'badge-resolved', 'suspended' => 'badge-rejected', default => 'badge-pending' }; ?>
                <span class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($programStatistics['badges']['responsive'])): ?>
                    <span class="badge-responsive" style="margin-left:6px;">Responsive</span>
                <?php endif; ?>
                <?php if (!empty($programStatistics['badges']['popular'])): ?>
                    <span class="badge-popular" style="margin-left:6px;">Popular</span>
                <?php endif; ?>
                <span style="color:var(--muted-foreground); font-size:var(--text-small); margin-left:12px;">
                    Created <?= htmlspecialchars(date('M j, Y', strtotime($program['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php if ($isOwner): ?>
                <a href="index.php?page=program-edit&amp;id=<?= (int) $program['id'] ?>" class="btn-secondary">
                    <i data-lucide="pencil" style="width:14px; height:14px;"></i> Edit
                </a>
                <?php if ($status === 'draft'): ?>
                    <form method="POST" action="index.php?page=program-publish&amp;id=<?= (int) $program['id'] ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-primary"><i data-lucide="rocket" style="width:14px; height:14px;"></i> Publish</button>
                    </form>
                <?php endif; ?>
                <?php if ($status === 'active'): ?>
                    <form method="POST" action="index.php?page=program-close&amp;id=<?= (int) $program['id'] ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-destructive"><i data-lucide="x-circle" style="width:14px; height:14px;"></i> Close</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($isResearcher): ?>
                <a href="index.php?page=report-submit&amp;program_id=<?= (int) $program['id'] ?>" class="btn-primary">
                    <i data-lucide="bug" style="width:14px; height:14px;"></i> Submit Report
                </a>
                <?php if (!$isSaved): ?>
                    <form method="POST" action="index.php?page=program-save&amp;id=<?= (int) $program['id'] ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-secondary" title="Save to bookmarks"><i data-lucide="bookmark" style="width:14px; height:14px;"></i> Save</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="index.php?page=program-unsave&amp;id=<?= (int) $program['id'] ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-secondary" title="Remove from bookmarks" style="color:var(--accent);"><i data-lucide="bookmark-check" style="width:14px; height:14px;"></i> Saved</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Statistics row -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:var(--space-md);">
        <div>
            <p style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">Reports</p>
            <p style="margin:4px 0 0; font-size:var(--text-subheading); font-weight:600;"><?= (int) ($programStatistics['report_count'] ?? 0) ?></p>
        </div>
        <div>
            <p style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">Enrolled</p>
            <p style="margin:4px 0 0; font-size:var(--text-subheading); font-weight:600;"><?= (int) ($programStatistics['enrolled_count'] ?? 0) ?></p>
        </div>
        <div>
            <p style="margin:0; font-size:var(--text-caption); color:var(--muted-foreground); text-transform:uppercase; letter-spacing:0.5px;">Response Rate</p>
            <p style="margin:4px 0 0; font-size:var(--text-subheading); font-weight:600;">
                <?php $rate = $programStatistics['response_rate'] ?? null;
                echo $rate === null ? 'N/A' : ((int) $rate . '%'); ?>
            </p>
        </div>
    </div>
</div>

<!-- In-scope Assets -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 12px 0;">In-Scope Assets</h2>
    <?php if (empty($assets)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">No assets defined.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?= htmlspecialchars($asset['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="chip chip-neutral">
                                <?= htmlspecialchars($asset['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Technology Tags -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 12px 0;">Technology Stack</h2>
    <?php if (empty($programTags)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">No tags defined.</p>
    <?php else: ?>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php foreach ($programTags as $tag): ?>
                <span class="chip chip-accent">
                    <?= htmlspecialchars($tag['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Description -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 12px 0;">Description</h2>
    <p style="font-size:var(--text-body); color:var(--muted-foreground); line-height:1.6; margin:0; white-space:pre-wrap;"><?= htmlspecialchars($program['description'], ENT_QUOTES, 'UTF-8') ?></p>
</div>

<!-- Scope -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 12px 0;">Scope</h2>
    <p style="font-size:var(--text-body); color:var(--muted-foreground); line-height:1.6; margin:0; white-space:pre-wrap;"><?= htmlspecialchars($program['scope'], ENT_QUOTES, 'UTF-8') ?></p>
</div>

<!-- Reward Policies -->
<div class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0 0 16px 0;">Reward Policies</h2>
    <?php if (empty($rewardPolicies)): ?>
        <p style="color:var(--muted-foreground); font-size:var(--text-body); margin:0;">No reward policies defined yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Severity</th><th>Min Reward</th><th>Max Reward</th></tr></thead>
            <tbody>
                <?php foreach ($rewardPolicies as $policy): ?>
                    <tr>
                        <td><span class="badge-<?= htmlspecialchars($policy['severity'] ?? 'informational', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($policy['severity'] ?? 'informational'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>$<?= htmlspecialchars(number_format((float) ($policy['min_reward'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>$<?= htmlspecialchars(number_format((float) ($policy['max_reward'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ===== YouTube-Style Comments Section ===== -->
<?php
$programComments = $programComments ?? [];
$canComment = $isOwner || $isResearcher;
$topLevel = [];
$replies = [];
foreach ($programComments as $c) {
    if ($c['parent_id'] === null) {
        $topLevel[] = $c;
    } else {
        $replies[(int) $c['parent_id']][] = $c;
    }
}
?>
<div id="comments" class="card-surface" style="padding:var(--space-lg); margin-bottom:var(--space-md);">
    <!-- Comment count -->
    <div style="display:flex; align-items:center; gap:var(--space-sm); margin-bottom:var(--space-lg);">
        <span style="font-size:var(--text-subheading); font-weight:600; color:var(--foreground);">
            <?= count($programComments) ?> Comment<?= count($programComments) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Add comment (top, YouTube style) -->
    <?php if ($canComment): ?>
        <div style="display:flex; gap:12px; margin-bottom:var(--space-xl);">
            <div style="width:40px; height:40px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--accent-foreground); font-weight:600; font-size:14px;">
                <?= htmlspecialchars(strtoupper(mb_substr($_SESSION['first_name'] ?? 'U', 0, 1) . mb_substr($_SESSION['last_name'] ?? '', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <form action="index.php?page=program-add-comment" method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                <textarea id="yt-comment-input" name="body" required placeholder="Add a comment..."
                    style="width:100%; min-height:40px; resize:none; border:none; border-bottom:1px solid var(--border); padding:8px 0; background:transparent; font-size:var(--text-body); font-family:inherit; outline:none; transition:min-height 0.2s, border-color 0.2s;"
                    onfocus="this.style.minHeight='80px'; this.style.borderBottomColor='var(--accent)'; document.getElementById('yt-comment-actions').style.display='flex';"
                ></textarea>
                <div id="yt-comment-actions" style="display:none; justify-content:flex-end; gap:8px; margin-top:8px;">
                    <button type="button" style="padding:8px 16px; font-size:13px; font-weight:500; border:none; background:none; color:var(--foreground); border-radius:var(--radius-full); cursor:pointer;"
                        onclick="document.getElementById('yt-comment-input').value=''; document.getElementById('yt-comment-input').style.minHeight='40px'; document.getElementById('yt-comment-input').style.borderBottomColor='var(--border)'; this.parentElement.style.display='none';">Cancel</button>
                    <button type="submit" style="padding:8px 16px; font-size:13px; font-weight:500; border:none; background:var(--accent); color:var(--accent-foreground); border-radius:var(--radius-full); cursor:pointer;">Comment</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Comments list -->
    <?php if (empty($topLevel)): ?>
        <p style="font-size:var(--text-body); color:var(--muted-foreground); text-align:center; padding:var(--space-lg) 0; margin:0;">
            No comments yet. Be the first to start the discussion.
        </p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:var(--space-lg);">
            <?php foreach ($topLevel as $comment):
                $authorName = trim(($comment['author_first_name'] ?? '') . ' ' . ($comment['author_last_name'] ?? ''));
                $initials = strtoupper(mb_substr($comment['author_first_name'] ?? 'U', 0, 1) . mb_substr($comment['author_last_name'] ?? '', 0, 1));
                $isOwnerComment = ($comment['author_role'] ?? '') === 'Program_Owner';
                $replyCount = count($replies[(int) $comment['id']] ?? []);
            ?>
                <div style="display:flex; gap:12px;">
                    <!-- Avatar -->
                    <div style="width:40px; height:40px; border-radius:50%; background:<?= $isOwnerComment ? 'var(--accent)' : '#e0e0e0' ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:<?= $isOwnerComment ? 'var(--accent-foreground)' : '#333' ?>; font-weight:600; font-size:14px;">
                        <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <!-- Author + time -->
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:2px;">
                            <span style="font-size:13px; font-weight:600; color:var(--foreground);"><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($isOwnerComment): ?>
                                <span style="font-size:11px; background:var(--accent); color:var(--accent-foreground); padding:1px 6px; border-radius:10px; font-weight:500;">Owner</span>
                            <?php endif; ?>
                            <span style="font-size:12px; color:var(--muted-foreground);"><?= htmlspecialchars(date('M j, Y', strtotime($comment['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <!-- Body -->
                        <p style="font-size:14px; color:var(--foreground); line-height:1.5; margin:4px 0 0; white-space:pre-wrap;"><?= htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8') ?></p>

                        <!-- Reply button -->
                        <?php if ($canComment): ?>
                            <button type="button" class="reply-toggle-btn" data-comment-id="<?= (int) $comment['id'] ?>"
                                style="display:inline-flex; align-items:center; gap:4px; margin-top:8px; padding:6px 12px; font-size:12px; font-weight:500; color:var(--muted-foreground); background:none; border:none; border-radius:var(--radius-full); cursor:pointer;"
                                onmouseover="this.style.background='var(--muted)'" onmouseout="this.style.background='none'">
                                <i data-lucide="message-circle" style="width:14px; height:14px;"></i> Reply
                            </button>
                        <?php endif; ?>

                        <!-- Reply form (hidden) -->
                        <?php if ($canComment): ?>
                            <div class="reply-form-container" id="reply-form-<?= (int) $comment['id'] ?>" style="display:none; margin-top:8px;">
                                <div style="display:flex; gap:8px;">
                                    <div style="width:24px; height:24px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--accent-foreground); font-weight:600; font-size:10px;">
                                        <?= htmlspecialchars(strtoupper(mb_substr($_SESSION['first_name'] ?? 'U', 0, 1) . mb_substr($_SESSION['last_name'] ?? '', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <form action="index.php?page=program-add-reply" method="POST" style="flex:1;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
                                        <input type="hidden" name="parent_id" value="<?= (int) $comment['id'] ?>">
                                        <textarea name="body" required placeholder="Add a reply..."
                                            style="width:100%; min-height:40px; resize:none; border:none; border-bottom:1px solid var(--border); padding:6px 0; background:transparent; font-size:13px; font-family:inherit; outline:none;"></textarea>
                                        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
                                            <button type="button" style="padding:6px 12px; font-size:12px; font-weight:500; border:none; background:none; color:var(--foreground); border-radius:var(--radius-full); cursor:pointer;"
                                                onclick="this.closest('.reply-form-container').style.display='none'">Cancel</button>
                                            <button type="submit" style="padding:6px 12px; font-size:12px; font-weight:500; border:none; background:var(--accent); color:var(--accent-foreground); border-radius:var(--radius-full); cursor:pointer;">Reply</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Replies (collapsible like YouTube) -->
                        <?php if ($replyCount > 0): ?>
                            <details style="margin-top:8px;" open>
                                <summary style="font-size:13px; font-weight:500; color:var(--accent); cursor:pointer; user-select:none; padding:4px 0; list-style:none;">
                                    ▾ <?= $replyCount ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?>
                                </summary>
                                <div style="display:flex; flex-direction:column; gap:12px; margin-top:8px; padding-left:4px;">
                                    <?php foreach ($replies[(int) $comment['id']] as $reply):
                                        $replyAuthor = trim(($reply['author_first_name'] ?? '') . ' ' . ($reply['author_last_name'] ?? ''));
                                        $replyInitials = strtoupper(mb_substr($reply['author_first_name'] ?? 'U', 0, 1) . mb_substr($reply['author_last_name'] ?? '', 0, 1));
                                        $isReplyOwner = ($reply['author_role'] ?? '') === 'Program_Owner';
                                    ?>
                                        <div style="display:flex; gap:8px;">
                                            <div style="width:24px; height:24px; border-radius:50%; background:<?= $isReplyOwner ? 'var(--accent)' : '#e0e0e0' ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:<?= $isReplyOwner ? 'var(--accent-foreground)' : '#333' ?>; font-weight:600; font-size:10px;">
                                                <?= htmlspecialchars($replyInitials, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div style="flex:1; min-width:0;">
                                                <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px;">
                                                    <span style="font-size:12px; font-weight:600; color:var(--foreground);"><?= htmlspecialchars($replyAuthor, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if ($isReplyOwner): ?>
                                                        <span style="font-size:10px; background:var(--accent); color:var(--accent-foreground); padding:1px 5px; border-radius:10px; font-weight:500;">Owner</span>
                                                    <?php endif; ?>
                                                    <span style="font-size:11px; color:var(--muted-foreground);"><?= htmlspecialchars(date('M j, Y', strtotime($reply['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <p style="font-size:13px; color:var(--foreground); line-height:1.4; margin:0; white-space:pre-wrap;"><?= htmlspecialchars($reply['body'], ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Reply toggle script -->
<script>
document.querySelectorAll('.reply-toggle-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-comment-id');
        var form = document.getElementById('reply-form-' + id);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.querySelector('textarea').focus();
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../components/layout_end.php'; ?>
