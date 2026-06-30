<?php
/**
 * SecureBounty — Badge Component
 *
 * Renders severity or status badges with appropriate CSS classes.
 *
 * Parameters:
 *   $type  — 'severity' or 'status'
 *   $value — The badge value (e.g., 'critical', 'high', 'medium', 'low', 'informational'
 *            for severity; 'pending', 'triaged', 'accepted', 'rejected', 'resolved' for status)
 */

$type = $type ?? 'severity';
$value = $value ?? '';

$normalizedValue = strtolower(trim($value));

// Map values to CSS class suffixes
$severityClasses = [
    'critical' => 'badge-critical',
    'high' => 'badge-high',
    'medium' => 'badge-medium',
    'low' => 'badge-low',
    'informational' => 'badge-informational',
    'none' => 'badge-informational',
];

$statusClasses = [
    'pending' => 'badge-pending',
    'triaged' => 'badge-triaged',
    'accepted' => 'badge-accepted',
    'rejected' => 'badge-rejected',
    'resolved' => 'badge-resolved',
];

// Determine CSS class based on type
if ($type === 'severity') {
    $cssClass = $severityClasses[$normalizedValue] ?? 'badge-informational';
} else {
    $cssClass = $statusClasses[$normalizedValue] ?? 'badge-pending';
}

// Display label (capitalize first letter)
$displayLabel = ucfirst($normalizedValue);
?>
<span class="<?php echo htmlspecialchars($cssClass); ?>">
    <?php echo htmlspecialchars($displayLabel); ?>
</span>