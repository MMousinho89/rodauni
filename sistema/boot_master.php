<?php
// ======================================================
// BOOT MASTER TOTAL - RODAUNI (AUTO SCAN)
// ======================================================

// 🔐 PROTEÇÃO (MUDE O TOKEN)
$TOKEN = 'rodauni123';

if (!isset($_GET['token']) || $_GET['token'] !== $TOKEN) {
    http_response_code(403);
    exit('Acesso negado');
}

header('Content-Type: text/html; charset=UTF-8');

// BASE
$ROOT = $_SERVER['DOCUMENT_ROOT'];
$BASE_URL = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

// IGNORAR
$ignore = ['.git', '.vscode', '.idea', 'vendor'];

// FUNÇÃO SCAN
function scanAll($dir, $ignore = [])
{
    $result = [];

    $items = @scandir($dir);
    if (!$items) return [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $ignore)) continue;

        $full = $dir . '/' . $item;

        $data = [
            'name' => $item,
            'path' => $full,
            'type' => is_dir($full) ? 'dir' : 'file',
            'size' => is_file($full) ? filesize($full) : null,
            'readable' => is_readable($full),
            'children' => []
        ];

        if (is_dir($full)) {
            $data['children'] = scanAll($full, $ignore);
        }

        $result[] = $data;
    }

    return $result;
}

// BUSCA
$search = $_GET['search'] ?? '';

// LISTAR PHP
function listPHP($tree, &$list = [])
{
    foreach ($tree as $item) {
        if ($item['type'] === 'file' && str_ends_with($item['name'], '.php')) {
            $list[] = $item;
        }
        if (!empty($item['children'])) {
            listPHP($item['children'], $list);
        }
    }
    return $list;
}

// GERAR URL
function makeUrl($path)
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $relative = str_replace($docRoot, '', $path);
    return $relative;
}

// SCAN TOTAL
$tree = scanAll($ROOT, $ignore);
$phpFiles = listPHP($tree);

// SERVIDOR
$server = $_SERVER;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BOOT MASTER RODAUNI</title>

<style>
body { font-family: Arial; background:#f4f6f8; margin:20px }
h1 { margin-bottom:10px }
.card { background:#fff; padding:15px; border-radius:10px; margin-bottom:15px; box-shadow:0 4px 10px rgba(0,0,0,.05) }
.ok { color:green }
.bad { color:red }
a { text-decoration:none; color:#0d6efd }
.tree { font-family: monospace; font-size:13px }
input { padding:8px; width:300px }
</style>

</head>
<body>

<h1>🚀 BOOT MASTER RODAUNI</h1>

<div class="card">
<b>URL Base:</b> <?= $BASE_URL ?><br>
<b>DOCUMENT_ROOT:</b> <?= $ROOT ?><br>
<b>REQUEST_URI:</b> <?= $_SERVER['REQUEST_URI'] ?><br>
<b>PHP:</b> <?= PHP_VERSION ?>
</div>

<div class="card">
<h2>🔎 Buscar arquivo</h2>
<form>
<input type="hidden" name="token" value="<?= $TOKEN ?>">
<input type="text" name="search" placeholder="buscar..." value="<?= htmlspecialchars($search) ?>">
<button>Buscar</button>
</form>
</div>

<div class="card">
<h2>📄 Arquivos PHP detectados</h2>

<table width="100%">
<tr>
<th>Arquivo</th>
<th>Status</th>
<th>Abrir</th>
</tr>

<?php foreach ($phpFiles as $file): 

if ($search && stripos($file['path'], $search) === false) continue;

$url = makeUrl($file['path']);
?>

<tr>
<td><?= $file['path'] ?></td>
<td><?= $file['readable'] ? '<span class="ok">OK</span>' : '<span class="bad">SEM ACESSO</span>' ?></td>
<td><a href="<?= $url ?>" target="_blank">Abrir</a></td>
</tr>

<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>📂 Estrutura completa</h2>

<div class="tree">
<?php
function renderTree($tree, $level = 0)
{
    foreach ($tree as $item) {
        echo str_repeat('&nbsp;&nbsp;', $level);

        if ($item['type'] === 'dir') {
            echo "📁 <b>{$item['name']}</b><br>";
            renderTree($item['children'], $level + 1);
        } else {
            echo "📄 {$item['name']}<br>";
        }
    }
}

renderTree($tree);
?>
</div>

</div>

<div class="card">
<h2>⚙️ $_SERVER</h2>
<pre><?php print_r($server); ?></pre>
</div>

</body>
</html>