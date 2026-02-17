<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';

function json_out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

function getColumns(PDO $pdo, string $table): array {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
  ");
  $st->execute([':t'=>$table]);
  return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function colExists(array $cols, string $c): bool {
  return in_array($c, $cols, true);
}

function onlyDigits($v): string {
  return preg_replace('/\D+/', '', (string)$v);
}

function normalizeUF($v): string {
  $s = strtoupper(trim((string)$v));
  $s = preg_replace('/[^A-Z]/', '', $s);
  return substr($s, 0, 2);
}

/* ===========================
   ROUTER
=========================== */
$action = $_GET['action'] ?? '';

$table = 'cad_parceiros';
$cols = getColumns($pdo, $table);

if ($action === 'list') {
  $f = trim((string)($_GET['f'] ?? ''));
  $ativo = (string)($_GET['ativo'] ?? '');

  // campos obrigatórios na lista
  $hasRazao = colExists($cols,'razao_social');
  $hasFant  = colExists($cols,'nome_fantasia');
  $hasAtivo = colExists($cols,'ativo');
  $hasCpf   = colExists($cols,'cpf');
  $hasCnpj  = colExists($cols,'cnpj');
  $hasTipo  = colExists($cols,'tipo_empresa');

  $where = [];
  $params = [];

  if ($ativo !== '') {
    if ($hasAtivo) {
      $where[] = "ativo = :ativo";
      $params[':ativo'] = ($ativo === '1') ? 1 : 0;
    }
  }

  if ($f !== '') {
    $fd = onlyDigits($f);

    // parte texto
    $or = [];
    if ($hasRazao) { $or[] = "razao_social LIKE :ft"; }
    if ($hasFant)  { $or[] = "nome_fantasia LIKE :ft"; }
    $params[':ft'] = "%{$f}%";

    // parte documento (com ou sem máscara)
    if ($fd !== '') {
      $params[':fd'] = "%{$fd}%";
      if ($hasCpf) {
        // remove . e -
        $or[] = "REPLACE(REPLACE(cpf,'.',''),'-','') LIKE :fd";
      }
      if ($hasCnpj) {
        // remove . / -
        $or[] = "REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-','') LIKE :fd";
      }
    }

    if (!empty($or)) {
      $where[] = '(' . implode(' OR ', $or) . ')';
    }
  }

  $sqlWhere = (!empty($where)) ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sql = "
    SELECT
      id
      ".($hasRazao ? ", razao_social" : ", '' AS razao_social")."
      ".($hasFant  ? ", nome_fantasia" : ", '' AS nome_fantasia")."
      ".($hasAtivo ? ", ativo" : ", 1 AS ativo")."
      ".($hasTipo  ? ", tipo_empresa" : ", 'JURIDICA' AS tipo_empresa")."
      ".($hasCpf   ? ", cpf" : ", '' AS cpf")."
      ".($hasCnpj  ? ", cnpj" : ", '' AS cnpj")."
    FROM {$table}
    {$sqlWhere}
    ORDER BY id DESC
    LIMIT 500
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // monta campo "documento" pra lista
  foreach ($rows as &$r) {
    $tipo = strtoupper(trim((string)($r['tipo_empresa'] ?? 'JURIDICA')));
    $doc = ($tipo === 'FISICA') ? ($r['cpf'] ?? '') : ($r['cnpj'] ?? '');
    $r['documento'] = $doc;
  }

  json_out(['ok'=>true, 'rows'=>$rows]);
}

if ($action === 'get') {
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) json_out(['ok'=>false,'error'=>'ID inválido.']);

  $st = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) json_out(['ok'=>false,'error'=>'Registro não encontrado.']);

  // campo virtual "documento" para a tela
  $tipo = strtoupper(trim((string)($row['tipo_empresa'] ?? 'JURIDICA')));
  $row['documento'] = ($tipo === 'FISICA') ? ($row['cpf'] ?? '') : ($row['cnpj'] ?? '');

  json_out(['ok'=>true,'row'=>$row]);
}

/* ===========================
   SAVE (POST JSON)
=========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) json_out(['ok'=>false,'error'=>'JSON inválido.']);

  $id = isset($data['id']) && $data['id'] !== null && $data['id'] !== '' ? (int)$data['id'] : 0;

  // validação mínima
  $razao = trim((string)($data['razao_social'] ?? ''));
  if ($razao === '') json_out(['ok'=>false,'error'=>'Razão Social é obrigatória.']);

  $tipo = strtoupper(trim((string)($data['tipo_empresa'] ?? 'JURIDICA')));
  if ($tipo !== 'FISICA' && $tipo !== 'JURIDICA') $tipo = 'JURIDICA';

  // regra principal: grava cpf OU cnpj, zera o outro
  if ($tipo === 'FISICA') {
    if (colExists($cols,'cpf'))  $data['cpf']  = $data['cpf'] ?? null;
    if (colExists($cols,'cnpj')) $data['cnpj'] = null;
  } else {
    if (colExists($cols,'cnpj')) $data['cnpj'] = $data['cnpj'] ?? null;
    if (colExists($cols,'cpf'))  $data['cpf']  = null;
  }

  // normalizações seguras
  if (isset($data['uf'])) $data['uf'] = normalizeUF($data['uf']);
  if (isset($data['pais']) && trim((string)$data['pais']) !== '') $data['pais'] = strtoupper(trim((string)$data['pais']));

  // remove chaves que não são coluna real
  $data = array_filter($data, fn($v,$k)=> colExists($cols,$k), ARRAY_FILTER_USE_BOTH);

  // auditoria
  $now = date('Y-m-d H:i:s');
  if ($id <= 0) {
    if (colExists($cols,'criado_em') && empty($data['criado_em'])) $data['criado_em'] = $now;
    if (colExists($cols,'criado_por') && empty($data['criado_por'])) $data['criado_por'] = $usuarioNome;
  }
  if (colExists($cols,'atualizado_em')) $data['atualizado_em'] = $now;
  if (colExists($cols,'atualizado_por')) $data['atualizado_por'] = $usuarioNome;

  try {
    if ($id <= 0) {
      // INSERT
      unset($data['id']);

      $fields = array_keys($data);
      $place  = array_map(fn($f)=>':'.$f, $fields);

      $sql = "INSERT INTO {$table} (".implode(',',$fields).") VALUES (".implode(',',$place).")";
      $st = $pdo->prepare($sql);
      foreach ($data as $k=>$v) $st->bindValue(':'.$k, $v);
      $st->execute();

      $newId = (int)$pdo->lastInsertId();
      json_out(['ok'=>true,'id'=>$newId]);
    }

    // UPDATE
    $data['id'] = $id;
    $sets = [];
    foreach ($data as $k=>$v) {
      if ($k === 'id') continue;
      $sets[] = "{$k} = :{$k}";
    }

    $sql = "UPDATE {$table} SET ".implode(', ', $sets)." WHERE id = :id LIMIT 1";
    $st = $pdo->prepare($sql);
    foreach ($data as $k=>$v) $st->bindValue(':'.$k, $v);
    $st->execute();

    json_out(['ok'=>true,'id'=>$id]);
  } catch (Throwable $e) {
    json_out(['ok'=>false,'error'=>'Erro ao salvar: '.$e->getMessage()]);
  }
}

json_out(['ok'=>false,'error'=>'Ação inválida.']);
