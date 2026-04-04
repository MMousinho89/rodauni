<?php
/*
|--------------------------------------------------------------------------
| BOOT MASTER TOTAL - COMPATÍVEL
|--------------------------------------------------------------------------
| Mostra:
| 1) Estrutura completa em árvore
| 2) Filtros
| 3) Ambiente
| 4) Todos os códigos um embaixo do outro
|--------------------------------------------------------------------------
*/

$BOOT_TOKEN = 'rodauni123';
$IGNORED_DIRS = array('.git', '.github', '.vscode', '.idea', 'node_modules', 'vendor', '.well-known');
$IGNORED_FILES = array();
$MAX_FILE_SIZE = 1024 * 1024 * 2;

$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$isLocal = in_array($remoteAddr, array('127.0.0.1', '::1')) || strpos($host, 'localhost') !== false;

if (!$isLocal) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    if ($token !== $BOOT_TOKEN) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

header('Content-Type: text/html; charset=UTF-8');

$projectRoot = realpath(__DIR__);
if ($projectRoot === false) {
    $projectRoot = __DIR__;
}

$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$bootBaseUrl = rtrim($protocol . $host . ($scriptDir === '/' ? '' : $scriptDir), '/');

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$folder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
$ext    = isset($_GET['ext']) ? trim($_GET['ext']) : 'php';
$showServer = isset($_GET['server']) && $_GET['server'] === '1';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizePath($path)
{
    return str_replace('\\', '/', $path);
}

function startsWithCompat($haystack, $needle)
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function relativePathCompat($fullPath, $root)
{
    $fullPath = normalizePath($fullPath);
    $root = rtrim(normalizePath($root), '/');
    if (startsWithCompat($fullPath, $root)) {
        return ltrim(substr($fullPath, strlen($root)), '/');
    }
    return $fullPath;
}

function isIgnoredCompat($path, $ignoredDirs)
{
    $path = '/' . trim(normalizePath($path), '/') . '/';
    foreach ($ignoredDirs as $ignored) {
        $needle = '/' . trim($ignored, '/') . '/';
        if (strpos($path, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function formatBytesCompat($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
}

function buildUrlCompat($params)
{
    global $bootBaseUrl, $isLocal, $BOOT_TOKEN;

    if (!$isLocal) {
        $params['token'] = $BOOT_TOKEN;
    }

    $query = http_build_query($params);
    return $bootBaseUrl . ($query ? '?' . $query : '');
}

function collectFilesCompat($dir, $root, $ignoredDirs, $ignoredFiles, $extension)
{
    $result = array();

    $items = @scandir($dir);
    if ($items === false) {
        return $result;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = normalizePath($dir . '/' . $item);

        if (isIgnoredCompat($full, $ignoredDirs)) {
            continue;
        }

        if (is_dir($full)) {
            $result = array_merge($result, collectFilesCompat($full, $root, $ignoredDirs, $ignoredFiles, $extension));
            continue;
        }

        if (in_array($item, $ignoredFiles, true)) {
            continue;
        }

        if ($extension !== '' && strtolower(pathinfo($full, PATHINFO_EXTENSION)) !== strtolower($extension)) {
            continue;
        }

        $size = 0;
        if (is_file($full)) {
            $tmpSize = filesize($full);
            $size = ($tmpSize !== false) ? $tmpSize : 0;
        }

        $result[] = array(
            'name' => basename($full),
            'full' => $full,
            'relative' => relativePathCompat($full, $root),
            'size' => $size,
            'readable' => is_readable($full),
        );
    }

    usort($result, function ($a, $b) {
        return strcasecmp($a['relative'], $b['relative']);
    });

    return $result;
}

function collectTreeCompat($dir, $root, $ignoredDirs)
{
    $tree = array();
    $items = @scandir($dir);
    if ($items === false) {
        return $tree;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = normalizePath($dir . '/' . $item);

        if (isIgnoredCompat($full, $ignoredDirs)) {
            continue;
        }

        $isDir = is_dir($full);

        $node = array(
            'name' => $item,
            'full' => $full,
            'relative' => relativePathCompat($full, $root),
            'type' => $isDir ? 'dir' : 'file',
            'children' => array(),
        );

        if ($isDir) {
            $node['children'] = collectTreeCompat($full, $root, $ignoredDirs);
        }

        $tree[] = $node;
    }

    usort($tree, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return ($a['type'] === 'dir') ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    return $tree;
}

function renderTreeCompat($tree, $level)
{
    foreach ($tree as $node) {
        $padding = $level * 18;
        echo '<div class="tree-line" style="padding-left:' . (int)$padding . 'px;">';

        if ($node['type'] === 'dir') {
            echo '<span class="tree-icon">📁</span> <strong>' . h($node['name']) . '</strong>';
        } else {
            echo '<span class="tree-icon">📄</span> ' . h($node['name']);
        }

        echo ' <span class="tree-path">(' . h($node['relative']) . ')</span>';
        echo '</div>';

        if (!empty($node['children'])) {
            renderTreeCompat($node['children'], $level + 1);
        }
    }
}

function readFileSafeCompat($file, $maxSize)
{
    if (!file_exists($file)) {
        return array('ok' => false, 'error' => 'Arquivo não existe.');
    }

    if (!is_readable($file)) {
        return array('ok' => false, 'error' => 'Sem permissão de leitura.');
    }

    $size = filesize($file);
    if ($size === false) {
        return array('ok' => false, 'error' => 'Não foi possível obter o tamanho.');
    }

    if ($size > $maxSize) {
        return array('ok' => false, 'error' => 'Arquivo maior que o limite de visualização (' . formatBytesCompat($size) . ').');
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return array('ok' => false, 'error' => 'Falha ao ler o arquivo.');
    }

    return array(
        'ok' => true,
        'content' => $content,
        'size' => $size,
    );
}

$tree = collectTreeCompat($projectRoot, $projectRoot, $IGNORED_DIRS);
$files = collectFilesCompat($projectRoot, $projectRoot, $IGNORED_DIRS, $IGNORED_FILES, $ext);

if ($folder !== '') {
    $folderNormalized = trim(normalizePath($folder), '/');
    $filtered = array();

    foreach ($files as $file) {
        $relativeNorm = trim(normalizePath($file['relative']), '/');
        if (startsWithCompat($relativeNorm, $folderNormalized)) {
            $filtered[] = $file;
        }
    }

    $files = $filtered;
}

if ($filter !== '') {
    $filtered = array();

    foreach ($files as $file) {
        if (stripos($file['relative'], $filter) !== false || stripos($file['name'], $filter) !== false) {
            $filtered[] = $file;
        }
    }

    $files = $filtered;
}

$totalFiles = count($files);
$totalSize = 0;
foreach ($files as $f) {
    $totalSize += (int)$f['size'];
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>BOOT MASTER TOTAL - CÓDIGOS EM BLOCO + ÁRVORE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #1f2937; font-family: Arial, Helvetica, sans-serif; }
        .wrap { max-width: 1600px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.08); margin-bottom: 16px; }
        h1, h2, h3 { margin-top: 0; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta th, .meta td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 14px; }
        .meta th { background: #f8fafc; width: 220px; }
        .mono { font-family: Consolas, Monaco, monospace; word-break: break-word; }
        .btn { display: inline-block; text-decoration: none; background: #0d6efd; color: #fff; border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-size: 14px; }
        .btn.gray { background: #6b7280; }
        .form-grid { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 12px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: bold; }
        .field input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; }
        .actions { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill { background: #eef2ff; color: #1d4ed8; border-radius: 999px; padding: 6px 10px; font-size: 12px; }
        .tree-box { background: #f8fafc; border: 1px solid #dbe3ee; border-radius: 12px; padding: 12px; max-height: 700px; overflow: auto; font-family: Consolas, Monaco, monospace; font-size: 13px; }
        .tree-line { padding: 4px 0; white-space: nowrap; }
        .tree-icon { display: inline-block; width: 18px; }
        .tree-path { color: #6b7280; font-size: 12px; }
        .file-block { margin-bottom: 18px; border: 1px solid #dbe3ee; border-radius: 14px; overflow: hidden; background: #fff; }
        .file-head { background: #f8fafc; border-bottom: 1px solid #e5e7eb; padding: 12px 14px; }
        .file-title { margin: 0 0 6px 0; font-size: 16px; }
        .file-meta { font-size: 12px; color: #6b7280; }
        .code-box { margin: 0; padding: 16px; background: #0f172a; color: #e5e7eb; font-family: Consolas, Monaco, monospace; font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; overflow-x: auto; }
        .error-box { padding: 14px 16px; background: #fff7ed; color: #9a3412; font-size: 14px; }
        .small { font-size: 12px; color: #6b7280; }
        pre.server-box { background: #111827; color: #e5e7eb; padding: 14px; border-radius: 12px; overflow: auto; max-height: 450px; font-size: 12px; }
        @media (max-width: 1100px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>BOOT MASTER TOTAL - CÓDIGOS EM BLOCO + ÁRVORE</h1>
        <div class="summary">
            <span class="pill">Ambiente: <?php echo $isLocal ? 'LOCAL' : 'PRODUÇÃO'; ?></span>
            <span class="pill">Host: <?php echo h($host); ?></span>
            <span class="pill">Projeto: <?php echo h($projectRoot); ?></span>
            <span class="pill">Arquivos listados: <?php echo $totalFiles; ?></span>
            <span class="pill">Tamanho total: <?php echo formatBytesCompat($totalSize); ?></span>
            <span class="pill">Extensão: <?php echo h($ext); ?></span>
        </div>
    </div>

    <div class="card">
        <h2>Estrutura completa (Árvore)</h2>
        <div class="tree-box">
            <?php renderTreeCompat($tree, 0); ?>
        </div>
    </div>

    <div class="card">
        <h2>Filtros</h2>
        <form method="get">
            <?php if (!$isLocal) { ?>
                <input type="hidden" name="token" value="<?php echo h($BOOT_TOKEN); ?>">
            <?php } ?>

            <div class="form-grid">
                <div class="field">
                    <label>Filtro por nome/caminho</label>
                    <input type="text" name="filter" value="<?php echo h($filter); ?>" placeholder="Ex.: index, parceiros, .htaccess">
                </div>

                <div class="field">
                    <label>Limitar à pasta</label>
                    <input type="text" name="folder" value="<?php echo h($folder); ?>" placeholder="Ex.: sistema/pages">
                </div>

                <div class="field">
                    <label>Extensão</label>
                    <input type="text" name="ext" value="<?php echo h($ext); ?>" placeholder="php">
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Aplicar</button>
                    <a href="<?php echo h(buildUrlCompat(array())); ?>" class="btn gray">Limpar</a>
                    <a href="<?php echo h(buildUrlCompat(array('server' => '1'))); ?>" class="btn gray">$_SERVER</a>
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
            <tr><th>DOCUMENT_ROOT</th><td class="mono"><?php echo h($documentRoot); ?></td></tr>
            <tr><th>SCRIPT_NAME</th><td class="mono"><?php echo h($scriptName); ?></td></tr>
            <tr><th>REQUEST_URI</th><td class="mono"><?php echo h(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''); ?></td></tr>
            <tr><th>PHP</th><td class="mono"><?php echo h(PHP_VERSION); ?></td></tr>
            <tr><th>Boot URL</th><td class="mono"><?php echo h(buildUrlCompat(array())); ?></td></tr>
        </table>
    </div>

    <?php if ($showServer) { ?>
        <div class="card">
            <h2>$_SERVER</h2>
            <pre class="server-box"><?php print_r($_SERVER); ?></pre>
        </div>
    <?php } ?>

    <div class="card">
        <h2>Arquivos e códigos</h2>

        <?php if (empty($files)) { ?>
            <p>Nenhum arquivo encontrado com os filtros atuais.</p>
        <?php } else { ?>
            <?php foreach ($files as $file) { ?>
                <?php $read = readFileSafeCompat($file['full'], $MAX_FILE_SIZE); ?>
                <section class="file-block">
                    <div class="file-head">
                        <h3 class="file-title"><?php echo h($file['relative']); ?></h3>
                        <div class="file-meta">
                            Tamanho: <?php echo formatBytesCompat($file['size']); ?> |
                            Leitura: <?php echo $file['readable'] ? 'OK' : 'SEM ACESSO'; ?> |
                            Caminho: <span class="mono"><?php echo h($file['full']); ?></span>
                        </div>
                    </div>

                    <?php if ($read['ok']) { ?>
                        <pre class="code-box"><?php echo h($read['content']); ?></pre>
                    <?php } else { ?>
                        <div class="error-box"><?php echo h($read['error']); ?></div>
                    <?php } ?>
                </section>
            <?php } ?>
        <?php } ?>
    </div>

</div>
</body>
</html>