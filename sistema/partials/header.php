<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'RODAUNI';
$extraHead = $extraHead ?? '';

function ru_asset(string $relativePath): string {
    $base = rtrim(BASE_URL, '/');
    $rel  = '/' . ltrim($relativePath, '/');

    // filesystem root: /sistema
    $root = realpath(__DIR__ . '/..');
    $disk = $root . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    if ($disk && file_exists($disk)) {
        return $base . $rel . '?v=' . filemtime($disk);
    }
    // fallback (ainda força cache-bust)
    return $base . $rel . '?v=' . time();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- ✅ CSS padrão do RODAUNI (com cache-bust automático) -->
  <link rel="stylesheet" href="<?= ru_asset('/assets/css/custom.css') ?>">

  <?= $extraHead ?>
</head>
<body class="ru-app">
