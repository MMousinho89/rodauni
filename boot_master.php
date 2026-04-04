<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BOOT MASTER TOTAL - VISUALIZAÇÃO EM BLOCO
|--------------------------------------------------------------------------
| Mostra TODOS os códigos dos arquivos um embaixo do outro.
| Funciona em LOCAL e PRODUÇÃO.
| Em produção exige token.
|--------------------------------------------------------------------------
*/

$BOOT_TOKEN = 'rodauni123';
$IGNORED_DIRS = ['.git', '.github', '.vscode', '.idea', 'node_modules', 'vendor', '.well-known'];
$IGNORED_FILES = [];
$MAX_FILE_SIZE = 1024 * 1024 * 2; // 2 MB por arquivo

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true) || str_contains($host, 'localhost');

if (!$isLocal) {
    $token = $_GET['token'] ?? '';
    if ($token !== $BOOT_TOKEN) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

header('Content-Type: text/html; charset=UTF-8');

$projectRoot = realpath(__DIR__) ?: __DIR__;
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$bootBaseUrl = rtrim($protocol . $host . ($scriptDir === '/' ? '' : $scriptDir), '/');

$filter = trim((string)($_GET['filter'] ?? ''));
$folder = trim((string)($_GET['folder'] ?? ''));
$ext    = trim((string)($_GET['ext'] ?? 'php'));
$showServer = isset($_GET['server']) && $_GET['server'] === '1';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function relativePath(string $fullPath, string $root): string
{
    $fullPath = normalizePath($fullPath);
    $root = rtrim(normalizePath($root), '/');
    if (str_starts_with($fullPath, $root)) {
        return ltrim(substr($fullPath, strlen($root)), '/');
    }
    return $fullPath;
}

function isIgnored(string $path, array $ignoredDirs): bool
{
    $path = '/' . trim(normalizePath($path), '/') . '/';
    foreach ($ignoredDirs as $ignored) {
        $needle = '/' . trim($ignored, '/') . '/';
        if (str_contains($path, $needle)) {
            return true;
        }
    }
    return false;
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
}

function buildUrl(array $params = []): string
{
    global $bootBaseUrl, $isLocal, $BOOT_TOKEN;

    if (!$isLocal) {
        $params['token'] = $BOOT_TOKEN;
    }

    $query = http_build_query($params);
    return $bootBaseUrl . ($query ? '?' . $query : '');
}

function collectFiles(
    string $dir,
    string $root,
    array $ignoredDirs,
    array $ignoredFiles,
    string $extension = 'php'
): array {
    $result = [];

    $items = @scandir($dir);
    if ($items === false) {
        return $result;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = normalizePath($dir . '/' . $item);

        if (isIgnored($full, $ignoredDirs)) {
            continue;
        }

        if (is_dir($full)) {
            $result = array_merge($result, collectFiles($full, $root, $ignoredDirs, $ignoredFiles, $extension));
            continue;
        }

        if (in_array($item, $ignoredFiles, true)) {
            continue;
        }

        if ($extension !== '' && strtolower(pathinfo($full, PATHINFO_EXTENSION)) !== strtolower($extension)) {
            continue;
        }

        $result[] = [
            'name' => basename($full),
            'full' => $full,
            'relative' => relativePath($full, $root),
            'size' => is_file($full) ? (filesize($full) ?: 0) : 0,
            'readable' => is_readable($full),
        ];
    }

    usort($result, fn($a, $b) => strcasecmp($a['relative'], $b['relative']));
    return $result;
}

function readFileSafe(string $file, int $maxSize): array
{
    if (!file_exists($file)) {
        return ['ok' => false, 'error' => 'Arquivo não existe.'];
    }

    if (!is_readable($file)) {
        return ['ok' => false, 'error' => 'Sem permissão de leitura.'];
    }

    $size = filesize($file);
    if ($size === false) {
        return ['ok' => false, 'error' => 'Não foi possível obter o tamanho.'];
    }

    if ($size > $maxSize) {
        return ['ok' => false, 'error' => 'Arquivo maior que o limite de visualização (' . formatBytes($size) . ').'];
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Falha ao ler o arquivo.'];
    }

    return [
        'ok' => true,
        'content' => $content,
        'size' => $size,
    ];
}

$files = collectFiles($projectRoot, $projectRoot, $IGNORED_DIRS, $IGNORED_FILES, $ext);

if ($folder !== '') {
    $folderNormalized = trim(normalizePath($folder), '/');
    $files = array_values(array_filter($files, function ($file) use ($folderNormalized) {
        return str_starts_with(trim(normalizePath($file['relative']), '/'), $folderNormalized);
    }));
}

if ($filter !== '') {
    $files = array_values(array_filter($files, function ($file) use ($filter) {
        return stripos($file['relative'], $filter) !== false
            || stripos($file['name'], $filter) !== false;
    }));
}

$totalFiles = count($files);
$totalSize = array_sum(array_map(fn($f) => (int)$f['size'], $files));

?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>BOOT MASTER TOTAL - CÓDIGOS EM BLOCO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f7;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
        }
        .wrap {
            max-width: 1600px;
            margin: 0 auto;
            padding: 18px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            margin-bottom: 16px;
        }
        h1, h2, h3 {
            margin-top: 0;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
        }
        .meta th, .meta td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }
        .meta th {
            background: #f8fafc;
            width: 220px;
        }
        .mono {
            font-family: Consolas, Monaco, monospace;
            word-break: break-word;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            background: #0d6efd;
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn.gray {
            background: #6b7280;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 12px;
        }
        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
        }
        .field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
        }
        .actions {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .pill {
            background: #eef2ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
        }
        .file-block {
            margin-bottom: 18px;
            border: 1px solid #dbe3ee;
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }
        .file-head {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 14px;
        }
        .file-title {
            margin: 0 0 6px 0;
            font-size: 16px;
        }
        .file-meta {
            font-size: 12px;
            color: #6b7280;
        }
        .code-box {
            margin: 0;
            padding: 16px;
            background: #0f172a;
            color: #e5e7eb;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
        }
        .error-box {
            padding: 14px 16px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 14px;
        }
        .small {
            font-size: 12px;
            color: #6b7280;
        }
        pre.server-box {
            background: #111827;
            color: #e5e7eb;
            padding: 14px;
            border-radius: 12px;
            overflow: auto;
            max-height: 450px;
            font-size: 12px;
        }
        @media (max-width: 1100px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>BOOT MASTER TOTAL - CÓDIGOS EM BLOCO</h1>
        <div class="summary">
            <span class="pill">Ambiente: <?= $isLocal ? 'LOCAL' : 'PRODUÇÃO' ?></span>
            <span class="pill">Host: <?= h($host) ?></span>
            <span class="pill">Projeto: <?= h($projectRoot) ?></span>
            <span class="pill">Arquivos listados: <?= $totalFiles ?></span>
            <span class="pill">Tamanho total: <?= formatBytes((int)$totalSize) ?></span>
            <span class="pill">Extensão: <?= h($ext) ?></span>
        </div>
    </div>

    <div class="card">
        <h2>Filtros</h2>
        <form method="get">
            <?php if (!$isLocal): ?>
                <input type="hidden" name="token" value="<?= h($BOOT_TOKEN) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="field">
                    <label>Filtro por nome/caminho</label>
                    <input type="text" name="filter" value="<?= h($filter) ?>" placeholder="Ex.: index, parceiros, .htaccess">
                </div>

                <div class="field">
                    <label>Limitar à pasta</label>
                    <input type="text" name="folder" value="<?= h($folder) ?>" placeholder="Ex.: sistema/pages">
                </div>

                <div class="field">
                    <label>Extensão</label>
                    <input type="text" name="ext" value="<?= h($ext) ?>" placeholder="php">
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Aplicar</button>
                    <a href="<?= h(buildUrl()) ?>" class="btn gray">Limpar</a>
                    <a href="<?= h(buildUrl(['server' => '1'])) ?>" class="btn gray">$_SERVER</a>
                </div>
            </div>
        </form>

        <div style="margin-top:12px" class="small">
            Exemplos:
            <span class="mono">folder=sistema/pages</span>,
            <span class="mono">filter=index</span>,
            <span class="mono">ext=php</span>
        </div>
    </div>

    <div class="card">
        <h2>Ambiente</h2>
        <table class="meta">
            <tr><th>DOCUMENT_ROOT</th><td class="mono"><?= h((string)$documentRoot) ?></td></tr>
            <tr><th>SCRIPT_NAME</th><td class="mono"><?= h($scriptName) ?></td></tr>
            <tr><th>REQUEST_URI</th><td class="mono"><?= h((string)($_SERVER['REQUEST_URI'] ?? '')) ?></td></tr>
            <tr><th>PHP</th><td class="mono"><?= h(PHP_VERSION) ?></td></tr>
            <tr><th>Boot URL</th><td class="mono"><?= h(buildUrl()) ?></td></tr>
        </table>
    </div>

    <?php if ($showServer): ?>
        <div class="card">
            <h2>$_SERVER</h2>
            <pre class="server-box"><?php print_r($_SERVER); ?></pre>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Arquivos e códigos</h2>

        <?php if (empty($files)): ?>
            <p>Nenhum arquivo encontrado com os filtros atuais.</p>
        <?php else: ?>
            <?php foreach ($files as $file): ?>
                <?php $read = readFileSafe($file['full'], $MAX_FILE_SIZE); ?>
                <section class="file-block">
                    <div class="file-head">
                        <h3 class="file-title"><?= h($file['relative']) ?></h3>
                        <div class="file-meta">
                            Tamanho: <?= formatBytes((int)$file['size']) ?> |
                            Leitura: <?= $file['readable'] ? 'OK' : 'SEM ACESSO' ?> |
                            Caminho: <span class="mono"><?= h($file['full']) ?></span>
                        </div>
                    </div>

                    <?php if ($read['ok']): ?>
                        <pre class="code-box"><?= h((string)$read['content']) ?></pre>
                    <?php else: ?>
                        <div class="error-box"><?= h((string)$read['error']) ?></div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>