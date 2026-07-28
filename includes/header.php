<?php
/**
 * includes/header.php
 *
 * Opens the HTML document for every page. A page includes this, then its
 * own content, then includes footer.php. $pageTitle can be set before
 * including this file to customize the <title> tag; defaults to "Auren".
 *
 * Usage:
 *   <?php
 *   $pageTitle = 'Browse Jobs';
 *   require_once __DIR__ . '/../includes/header.php';
 *   ?>
 *   ... page content ...
 *   <?php require_once __DIR__ . '/../includes/footer.php'; ?>
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Auren';
} else {
    $pageTitle = $pageTitle . ' · Auren';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (used for nav/dashboard icons) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Fonts: Space Grotesk (display) + DM Sans (body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Auren custom styles -->
    <link rel="stylesheet" href="/auren/assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main>
