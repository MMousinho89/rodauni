<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

session_start();

$debug = (isset($_GET['debug']) && $_GET['debug'] == '1');
$erro = '';
$info = [];
$info_sql = '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db_name(PDO $pdo): string {
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    return $db ? (string)$db : '';
}
function table_exists(PDO $pdo, string $db, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $stmt->execute([$db,$table]);
    return (int)$stmt->fetchColumn() > 0;
}
function columns(PDO $pdo, string $db, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $stmt->execute([$db,$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
function pick_first(array $cands, array $avail): ?string {
    $availL = array_map('strtolower',$avail);
    foreach($cands as $c){
        $i = array_search(strtolower($c), $availL, true);
        if ($i !== false) return $avail[$i];
    }
    return null;
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new Exception("PDO não encontrado em config/database.php");

    $db = db_name($pdo);
    if ($db === '') throw new Exception("Nenhum DB selecionado (SELECT DATABASE() vazio).");

    // Detecta tabela
    $userTable = null;
    foreach (['cad_usuarios','usuarios','tb_usuarios','users','user'] as $t) {
        if (table_exists($pdo, $db, $t)) { $userTable = $t; break; }
    }
    if (!$userTable) {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
              AND (TABLE_NAME LIKE '%usuario%' OR TABLE_NAME LIKE '%user%')
            ORDER BY (TABLE_NAME LIKE 'cad_%') DESC, TABLE_NAME ASC
            LIMIT 1
        ");
        $stmt->execute([$db]);
        $userTable = $stmt->fetchColumn() ?: null;
    }
    if (!$userTable) throw new Exception("Nenhuma tabela de usuários encontrada.");

    $cols = columns($pdo, $db, $userTable);

    $colId    = pick_first(['id','usuario_id','id_usuario'], $cols);
    $colNome  = pick_first(['nome','nome_usuario','name','usuario_nome'], $cols);
    $colEmail = pick_first(['email','usuario','login','username','user','user_email'], $cols);
    $colSenha = pick_first(['senha','password','pass','senha_hash'], $cols);

    if (!$colId || !$colEmail || !$colSenha) {
        throw new Exception("Tabela '$userTable' não tem colunas necessárias. Colunas: ".implode(', ', $cols));
    }

    if ($debug) {
        $info[] = "DB: $db";
        $info[] = "Tabela: $userTable";
        $info[] = "Colunas: id=$colId | nome=".($colNome?:'-')." | email=$colEmail | senha=$colSenha";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        if ($debug) {
            $info[] = "POST recebido: email=".($email !== '' ? $email : '[vazio]')." | senha_len=".strlen($pass);
        }

        if ($email === '' || $pass === '') {
            $erro = "Preencha e-mail e senha.";
        } else {
            $sql = "SELECT * FROM `$userTable` WHERE `$colEmail` = ? LIMIT 1";
            $info_sql = $sql;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                $erro = "Usuário não encontrado para esse e-mail/login.";
            } else {
                $senhaDb = (string)$u[$colSenha];

                // aceita hash e texto puro
                $ok = password_verify($pass, $senhaDb) || hash_equals($senhaDb, $pass);

                if (!$ok) {
                    $erro = "Senha incorreta (comparação falhou).";
                } else {
                    $_SESSION['usuario_id'] = (int)$u[$colId];
                    $_SESSION['usuario_nome'] = $colNome && !empty($u[$colNome]) ? (string)$u[$colNome] : $email;

                    if ($debug) {
                        $info[] = "Sessão setada: usuario_id=".$_SESSION['usuario_id']." | usuario_nome=".$_SESSION['usuario_nome'];
                        $info[] = "Redirect: ".BASE_URL."/pages/dashboard.php";
                    }

                    header("Location: " . BASE_URL . "/pages/dashboard.php");
                    exit;
                }
            }
        }
    }

} catch (Throwable $e) {
    $erro = "Erro interno do sistema.";
    if ($debug) $info[] = "ERRO: ".$e->getMessage();
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
  body{background:#e9ecef;display:flex;justify-content:center;align-items:center;height:100vh;}
  .login-card{width:100%;max-width:520px;padding:32px;border-radius:10px;background:#fff;box-shadow:0 4px 15px rgba(0,0,0,.10);}
  .btn-login{background:linear-gradient(90deg,#212529,#343a40);border:none;color:#fff;}
</style>
</head>
<body>
<div class="login-card">

  <h2 class="text-center mb-4 fw-bold">RODAUNI</h2>

  <?php if (!empty($_GET['msg']) && $_GET['msg']==='logout_ok'): ?>
    <div class="alert alert-success">Você saiu do sistema.</div>
  <?php endif; ?>

  <?php if ($erro): ?>
    <div class="alert alert-danger mb-3">
      <?= h($erro) ?>
      <?php if ($debug && !empty($info_sql)): ?>
        <div class="small mt-2"><b>SQL:</b> <?= h($info_sql) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($debug && !empty($info)): ?>
    <div class="alert alert-secondary small">
      <?php foreach($info as $line): ?>
        <div><?= h($line) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="on">
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input type="email" name="email" class="form-control" required autocomplete="username">
    </div>
    <div class="mb-3">
      <label class="form-label">Senha</label>
      <input type="password" name="password" class="form-control" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-login w-100 fw-semibold">Entrar</button>
  </form>

</div>
</body>
</html>
