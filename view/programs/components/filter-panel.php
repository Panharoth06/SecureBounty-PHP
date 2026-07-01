<?php
/**
 * Programs Filter Panel Partial
 *
 * Renders the asset-type / tag / bounty-range filter panel for the program
 * listing page. The form submits via GET to index.php?page=programs so the
 * URL stays shareable.
 *
 * Expected variables (must be set by caller):
 *   $filters       (array)      — current filter selections:
 *                                 { asset_type: string[], tag: int[],
 *                                   bounty_min: ?float, bounty_max: ?float }
 *   $totalCount    (int)        — number of programs matching current filters
 *   $allTags       (array)      — full tag list ({ id, name } rows)
 *   $filterErrors  (array|null) — optional { field: message } map
 *
 * Does NOT include layout.php — this partial is rendered inside an existing page.
 */

$filters = $filters ?? [];
$totalCount = (int) ($totalCount ?? 0);
$allTags = $allTags ?? [];
$filterErrors = $filterErrors ?? [];

$selectedAssetTypes = $filters['asset_type'] ?? [];
if (!is_array($selectedAssetTypes)) {
    $selectedAssetTypes = [];
}

$selectedTagIds = $filters['tag'] ?? [];
if (!is_array($selectedTagIds)) {
    $selectedTagIds = [];
}
$selectedTagIds = array_map('intval', $selectedTagIds);

$bountyMin = $filters['bounty_min'] ?? null;
$bountyMax = $filters['bounty_max'] ?? null;

$hasAnyFilter = !empty($selectedAssetTypes)
    || !empty($selectedTagIds)
    || ($bountyMin !== null && $bountyMin !== '')
    || ($bountyMax !== null && $bountyMax !== '');

$assetTypeOptions = [
    'Domain',
    'Wildcard',
    'iOS App Store',
    'Android Play Store',
    'Windows App',
    'Other',
];
?>
<aside class="filter-panel card-surface"
    style="padding:var(--space-lg); display:flex; flex-direction:column; gap:var(--space-lg);">

    <!-- Header -->
    <div>
        <h2 style="font-size:var(--text-subheading); font-weight:600; margin:0;">Filter Programs</h2>
        <p style="margin:4px 0 0; color:var(--muted-foreground); font-size:var(--text-small);">
            <?= $totalCount ?> program
            <?= $totalCount === 1 ? '' : 's' ?> match
        </p>
    </div>

    <?php if (!empty($filterErrors)): ?>
        <div role="alert" aria-live="polite"
            style="background:var(--status-rejected-bg); border:1px solid var(--status-rejected-border); border-radius:var(--radius-md); padding:12px 16px; color:var(--status-rejected-text); font-size:var(--text-body);">
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($filterErrors as $msg): ?>
                    <li>
                        <?= htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="GET" action="index.php" style="display:flex; flex-direction:column; gap:var(--space-lg);">
        <input type="hidden" name="page" value="programs">

        <!-- Asset Type -->
        <fieldset style="border:none; padding:0; margin:0;">
            <legend class="form-label" style="margin-bottom:var(--space-sm);">Asset Type</legend>
            <div style="display:flex; flex-direction:column; gap:var(--space-xs);">
                <?php foreach ($assetTypeOptions as $assetType):
                    $isChecked = in_array($assetType, $selectedAssetTypes, true);
                    $inputId = 'asset_type_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($assetType));
                    ?>
                    <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                        style="display:flex; align-items:center; gap:var(--space-sm); font-size:var(--text-body); color:var(--foreground); cursor:pointer;">
                        <input type="checkbox" id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                            name="asset_type[]" value="<?= htmlspecialchars($assetType, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $isChecked ? 'checked' : '' ?>>
                        <?= htmlspecialchars($assetType, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- Tags -->
        <div>
            <label for="filter_tags" class="form-label">Technology Tags</label>
            <select id="filter_tags" name="tag[]" multiple size="6" class="form-select" style="width:100%;">
                <?php foreach ($allTags as $tag):
                    $tagId = (int) ($tag['id'] ?? 0);
                    $isSelected = in_array($tagId, $selectedTagIds, true);
                    ?>
                    <option value="<?= $tagId ?>" <?= $isSelected ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($tag['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size:var(--text-caption); color:var(--muted-foreground); margin-top:6px;">
                Hold Ctrl/Cmd to select multiple.
            </p>
        </div>

        <!-- Bounty Range -->
        <div>
            <span class="form-label" style="display:block; margin-bottom:var(--space-sm);">Bounty Range</span>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                <div>
                    <label for="bounty_min" class="form-label" style="font-size:var(--text-caption);">Min</label>
                    <input type="number" id="bounty_min" name="bounty_min"
                        class="form-input <?= isset($filterErrors['bounty_range']) ? 'is-error' : '' ?>" min="0"
                        step="1" placeholder="Min"
                        value="<?= ($bountyMin === null || $bountyMin === '') ? '' : htmlspecialchars((string) $bountyMin, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="bounty_max" class="form-label" style="font-size:var(--text-caption);">Max</label>
                    <input type="number" id="bounty_max" name="bounty_max"
                        class="form-input <?= isset($filterErrors['bounty_range']) ? 'is-error' : '' ?>" min="0"
                        step="1" placeholder="Max"
                        value="<?= ($bountyMax === null || $bountyMax === '') ? '' : htmlspecialchars((string) $bountyMax, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <?php if (isset($filterErrors['bounty_range'])): ?>
                <p style="font-size:var(--text-caption); color:var(--destructive); margin-top:6px;">
                    <?= htmlspecialchars((string) $filterErrors['bounty_range'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Action buttons -->
        <div style="display:flex; gap:var(--space-sm); flex-wrap:wrap;">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <?php if ($hasAnyFilter): ?>
                <a href="index.php?page=programs&amp;clear_filters=1" class="btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>
</aside>