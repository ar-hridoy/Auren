<?php
/**
 * includes/auth_header.php
 *
 * A dedicated, minimal document shell for the split-screen auth pages
 * (login / register). Unlike the normal header.php, this deliberately
 * omits the site navbar and footer so the sign-in / sign-up screens can
 * be full-bleed, two-panel layouts (a branded colour panel on one side,
 * the form on the other) — matching the approved reference design.
 *
 * The including page sets:
 *   $pageTitle       - <title> text
 *   $panelSide       - 'left' or 'right': which side the colour panel is on
 *   $panelHeading    - the big line shown on the colour panel
 *   $panelSub        - the smaller line under it
 *   $panelAttribution- optional small credit line (e.g. a quote author)
 * then renders the form column, then includes auth_footer.php.
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Auren';
} else {
    $pageTitle = $pageTitle . ' · Auren';
}
$panelSide = $panelSide ?? 'left';
$panelStyle = $panelStyle ?? 'heading';   // 'quote' | 'heading'
$panelHeading = $panelHeading ?? '';
$panelSub = $panelSub ?? '';
$panelQuote = $panelQuote ?? '';
$panelAttribution = $panelAttribution ?? '';
$panelStat = $panelStat ?? '';

// Panel gets a modifier class so CSS can position the body (quote sits low;
// heading is vertically centered) and move the brand to the top-right.
$panelModifier = $panelStyle === 'quote' ? 'auren-auth-panel--quote' : 'auren-auth-panel--center';

if ($panelStyle === 'quote') {
    // Testimonial: big pulled quote + attribution, brand top-left, stat bottom.
    $bodyHtml =
        '<div class="auren-auth-panel-body">'
        . '<div class="auren-auth-panel-quote">' . htmlspecialchars($panelQuote) . '</div>'
        . ($panelAttribution !== '' ? '<p class="auren-auth-panel-attr">' . htmlspecialchars($panelAttribution) . '</p>' : '')
        . '</div>';
    $footerHtml = $panelStat !== ''
        ? '<div class="auren-auth-panel-footer">' . htmlspecialchars($panelStat) . '</div>'
        : '<div class="auren-auth-panel-footer">&copy; ' . date('Y') . ' Auren</div>';
} else {
    // Centered heading + sub + stat, brand pinned top-right.
    $bodyHtml =
        '<div class="auren-auth-panel-body">'
        . '<h2 class="auren-auth-panel-heading">' . htmlspecialchars($panelHeading) . '</h2>'
        . ($panelSub !== '' ? '<p class="auren-auth-panel-sub">' . htmlspecialchars($panelSub) . '</p>' : '')
        . ($panelStat !== '' ? '<p class="auren-auth-panel-stat">' . htmlspecialchars($panelStat) . '</p>' : '')
        . '</div>';
    $footerHtml = '<div class="auren-auth-panel-footer">&copy; ' . date('Y') . ' Auren</div>';
}

$panelHtml =
    '<div class="auren-auth-panel ' . $panelModifier . '">'
    . '<a href="/auren/index.php" class="auren-auth-brand">'
    . '<span class="auren-auth-brand-badge"><i class="bi bi-briefcase-fill"></i></span> Auren'
    . '</a>'
    . $bodyHtml
    . $footerHtml
    . '</div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/auren/assets/css/style.css">
</head>
<body class="auren-auth-body">
<div class="auren-auth-split auren-auth-panel-<?= htmlspecialchars($panelSide) ?>">
    <?php if ($panelSide === 'left') { echo $panelHtml; } ?>
    <div class="auren-auth-form-col">
        <div class="auren-auth-form-inner">
