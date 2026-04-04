<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =============================
// REQUIRE SEGURO (ANTI 500)
// =============================
$baseDir = dirname(__DIR__);

$appFile = $baseDir . '/config/app.php';
$dbFile  = $baseDir . '/config/database.php';

if (!file_exists($appFile)) {
    die("Erro: app.php não encontrado em: " . $appFile);
}

if (!file_exists($dbFile)) {
    die("Erro: database.php não encontrado em: " . $dbFile);
}

require_once $appFile;
require_once $dbFile;

// =============================
// SESSION
// =============================
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// =============================
// DEBUG
// =============================
$debug = (isset($_GET['debug']) && $_GET['debug'] == '1');
$erro = '';
$info = [];
$info_sql = '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// =============================
// FUNÇÕES DB
// =============================
function db_name(PDO $pdo): string {
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    return $db ? (string)$db : '';
}

function table_exists(PDO $pdo, string $db, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ");
    $stmt->execute([$db, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columns(PDO $pdo, string $db, string $table): array {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ");
    $stmt->execute([$db, $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function pick_first(array $cands, array $avail): ?string {
    $availL = array_map('strtolower', $avail);
    foreach ($cands as $c) {
        $i = array_search(strtolower($c), $availL, true);
        if ($i !== false) {
            return $avail[$i];
        }
    }
    return null;
}

// =============================
// PROCESSO LOGIN
// =============================
try {

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO não encontrado em config/database.php");
    }

    $db = db_name($pdo);
    if ($db === '') {
        throw new Exception("Nenhum banco selecionado.");
    }

    // -------------------------
    // IDENTIFICAR TABELA
    // -------------------------
    $userTable = null;

    foreach (['cad_usuarios', 'usuarios', 'tb_usuarios', 'users', 'user'] as $t) {
        if (table_exists($pdo, $db, $t)) {
            $userTable = $t;
            break;
        }
    }

    if (!$userTable) {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
              AND (TABLE_NAME LIKE '%usuario%' OR TABLE_NAME LIKE '%user%')
            LIMIT 1
        ");
        $stmt->execute([$db]);
        $userTable = $stmt->fetchColumn();
    }

    if (!$userTable) {
        throw new Exception("Tabela de usuários não encontrada.");
    }

    $cols = columns($pdo, $db, $userTable);

    $colId    = pick_first(['id', 'usuario_id'], $cols);
    $colNome  = pick_first(['nome', 'nome_usuario'], $cols);
    $colEmail = pick_first(['email', 'usuario', 'login'], $cols);
    $colSenha = pick_first(['senha', 'password', 'senha_hash'], $cols);

    if (!$colId || !$colEmail || !$colSenha) {
        throw new Exception("Estrutura da tabela inválida.");
    }

    // -------------------------
    // LOGIN
    // -------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if ($email === '' || $pass === '') {
            $erro = "Preencha e-mail e senha.";
        } else {

            $sql = "SELECT * FROM `$userTable` WHERE `$colEmail` = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);

            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                $erro = "Usuário não encontrado.";
            } else {

                $senhaDb = $u[$colSenha] ?? '';

                $ok = password_verify($pass, $senhaDb) || hash_equals($senhaDb, $pass);

                if (!$ok) {
                    $erro = "Senha incorreta.";
                } else {

                    $_SESSION['usuario_id']    = (int)$u[$colId];
                    $_SESSION['usuario_nome']  = $colNome ? ($u[$colNome] ?? $email) : $email;
                    $_SESSION['usuario_email'] = $email;

                    header("Location: " . BASE_URL . "/pages/dashboard.php");
                    exit;
                }
            }
        }
    }

} catch (Throwable $e) {
    $erro = "Erro interno: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - RODAUNI</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#e9ecef;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.login-card{
    width:100%;
    max-width:520px;
    padding:32px;
    border-radius:10px;
    background:#fff;
    box-shadow:0 4px 15px rgba(0,0,0,.10);
}
.btn-login{
    background:linear-gradient(90deg,#212529,#343a40);
    border:none;
    color:#fff;
}
</style>

</head>
<body>

<div class="login-card">

<h2 class="text-center mb-4 fw-bold">RODAUNI</h2>

<?php if ($erro): ?>
<div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<form method="post">

<div class="mb-3">
<label class="form-label">E-mail</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Senha</label>
<input type="password" name="password" class="form-control" required>
</div>

<button type="submit" class="btn btn-login w-100 fw-semibold">
Entrar
</button>

</form>

</div>

</body>
</html>