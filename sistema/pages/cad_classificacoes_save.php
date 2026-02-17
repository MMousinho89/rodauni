<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function tableColumns(PDO $pdo, string $table): array {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
  ");
  $st->execute([':t'=>$table]);
  return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
function hasCol(array $cols, string $c): bool { return in_array($c, $cols, true); }
function nowSql(): string { return date('Y-m-d H:i:s'); }
function normStr($v): ?string {
  $s = trim((string)$v);
  return ($s === '') ? null : $s;
}

try{
  $table = 'cad_classificacoes';
  $cols = tableColumns($pdo, $table);
  if(!$cols){
    echo json_encode(['ok'=>false,'error'=>"Tabela {$table} não encontrada."], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // garante que temos uma coluna de descrição
  $descCol = null;
  foreach (['descricao','nome','titulo','classificacao'] as $cand) {
    if (hasCol($cols, $cand)) { $descCol = $cand; break; }
  }
  if(!$descCol){
    echo json_encode(['ok'=>false,'error'=>"Tabela {$table} não possui coluna de descrição (descricao/nome/titulo/classificacao)."], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $action = $_GET['action'] ?? '';

  // LIST
  if($action === 'list'){
    $f = trim((string)($_GET['f'] ?? ''));
    $ativo = trim((string)($_GET['ativo'] ?? ''));

    $sql = "SELECT id, {$descCol} AS descricao";
    if(hasCol($cols,'ativo')) $sql .= ", ativo";
    $sql .= " FROM {$table} WHERE 1=1";
    $params = [];

    if($f !== ''){
      $sql .= " AND (CAST(id AS CHAR) LIKE :f OR {$descCol} LIKE :f)";
      $params[':f'] = "%{$f}%";
    }

    if($ativo !== '' && hasCol($cols,'ativo')){
      $sql .= " AND ativo = :ativo";
      $params[':ativo'] = ($ativo === '0') ? 0 : 1;
    }

    $sql .= " ORDER BY {$descCol} ASC, id ASC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // GET
  if($action === 'get'){
    $id = (int)($_GET['id'] ?? 0);
    if($id <= 0){
      echo json_encode(['ok'=>false,'error'=>'ID inválido.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $st = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if(!$row){
      echo json_encode(['ok'=>false,'error'=>'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // normaliza retorno pra tela (descricao sempre no mesmo nome)
    $row['descricao'] = $row[$descCol] ?? ($row['descricao'] ?? '');
    if(!isset($row['ativo'])) $row['ativo'] = 1;

    echo json_encode(['ok'=>true,'row'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // SAVE (POST JSON)
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if(!is_array($data)) $data = [];

  $id = isset($data['id']) ? (int)$data['id'] : 0;
  $usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

  $descricao = trim((string)($data['descricao'] ?? ''));
  if($descricao === ''){
    echo json_encode(['ok'=>false,'error'=>'Descrição é obrigatória.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $ativo = (string)($data['ativo'] ?? '1') === '0' ? 0 : 1;

  $payload = [];
  $payload[$descCol] = normStr($descricao);

  if(hasCol($cols,'ativo')) $payload['ativo'] = $ativo;

  $pdo->beginTransaction();

  if($id > 0){
    $sets = [];
    $params = [':id'=>$id];

    foreach($payload as $k=>$v){
      $sets[] = "`{$k}` = :{$k}";
      $params[":{$k}"] = $v;
    }

    if(hasCol($cols,'atualizado_em')){
      $sets[] = "atualizado_em = :atualizado_em";
      $params[':atualizado_em'] = nowSql();
    }
    if(hasCol($cols,'atualizado_por')){
      $sets[] = "atualizado_por = :atualizado_por";
      $params[':atualizado_por'] = $usuario;
    }

    $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $pdo->commit();
    echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // INSERT
  $fields = [];
  $marks = [];
  $params = [];

  foreach($payload as $k=>$v){
    $fields[] = "`{$k}`";
    $marks[] = ":{$k}";
    $params[":{$k}"] = $v;
  }

  if(hasCol($cols,'criado_em')){
    $fields[] = "criado_em"; $marks[]=":criado_em"; $params[':criado_em']=nowSql();
  }
  if(hasCol($cols,'criado_por')){
    $fields[] = "criado_por"; $marks[]=":criado_por"; $params[':criado_por']=$usuario;
  }
  if(hasCol($cols,'atualizado_em')){
    $fields[] = "atualizado_em"; $marks[]=":atualizado_em"; $params[':atualizado_em']=nowSql();
  }
  if(hasCol($cols,'atualizado_por')){
    $fields[] = "atualizado_por"; $marks[]=":atualizado_por"; $params[':atualizado_por']=$usuario;
  }

  $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES (" . implode(',', $marks) . ")";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $newId = (int)$pdo->lastInsertId();
  $pdo->commit();

  echo json_encode(['ok'=>true,'id'=>$newId], JSON_UNESCAPED_UNICODE);
  exit;

}catch(Throwable $e){
  if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
