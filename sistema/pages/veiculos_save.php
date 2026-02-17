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
  $st->execute([':t' => $table]);
  return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
function hasCol(array $cols, string $c): bool { return in_array($c, $cols, true); }
function nowSql(): string { return date('Y-m-d H:i:s'); }

function normStr($v): ?string {
  $s = trim((string)$v);
  return ($s === '') ? null : $s;
}
function normUpperNoSpace($v): ?string {
  $s = strtoupper(trim((string)$v));
  $s = preg_replace('/\s+/', '', $s);
  return ($s === '') ? null : $s;
}
function normInt($v): ?int {
  if ($v === null || $v === '') return null;
  return (int)$v;
}
function normTinyInt($v): ?int {
  if ($v === null || $v === '') return null;
  return ((string)$v === '0') ? 0 : 1;
}

function detectLabelColumn(PDO $pdo, string $table): string {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME IN ('descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    ORDER BY FIELD(COLUMN_NAME,'descricao','nome','titulo','razao_social','nome_fantasia','fantasia','sigla')
    LIMIT 1
  ");
  $stmt->execute([':t' => $table]);
  $col = $stmt->fetchColumn();
  return $col ?: 'id';
}

try {
  $table = 'cad_veiculos';
  $cols  = tableColumns($pdo, $table);
  if (!$cols) {
    echo json_encode(['ok' => false, 'error' => "Tabela {$table} não encontrada."], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $action = $_GET['action'] ?? '';

  // ===================== LIST =====================
  if ($action === 'list') {
    $f = trim((string)($_GET['f'] ?? ''));

    $filLabel = detectLabelColumn($pdo, 'cad_filiais');
    $tipLabel = detectLabelColumn($pdo, 'cad_tipos_veiculo');
    $marLabel = detectLabelColumn($pdo, 'cad_marcas_veiculo');
    $sitLabel = detectLabelColumn($pdo, 'cad_situacoes');

    $sql = "
      SELECT v.id, v.placa,
             f.`{$filLabel}` AS filial_label,
             tv.`{$tipLabel}` AS tipo_veiculo_label,
             m.`{$marLabel}` AS marca_label,
             s.`{$sitLabel}` AS situacao_label,
             v.status_operacional
      FROM cad_veiculos v
      LEFT JOIN cad_filiais f ON f.id = v.filial_id
      LEFT JOIN cad_tipos_veiculo tv ON tv.id = v.tipo_veiculo_id
      LEFT JOIN cad_marcas_veiculo m ON m.id = v.marca_id
      LEFT JOIN cad_situacoes s ON s.id = v.situacao_id
      WHERE 1=1
    ";

    $params = [];
    if ($f !== '') {
      $sql .= " AND (v.placa LIKE :f OR v.chassi LIKE :f OR v.renavam LIKE :f) ";
      $params[':f'] = "%{$f}%";
    }

    $sql .= " ORDER BY v.id DESC LIMIT 500";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ===================== GET =====================
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(['ok'=>false,'error'=>'ID inválido.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $st = $pdo->prepare("SELECT * FROM cad_veiculos WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      echo json_encode(['ok'=>false,'error'=>'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    echo json_encode(['ok'=>true,'row'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ===================== SAVE (POST JSON) =====================
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];

  $id = isset($data['id']) ? (int)$data['id'] : 0;
  $usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

  $placa = trim((string)($data['placa'] ?? ''));
  if ($placa === '') {
    echo json_encode(['ok'=>false,'error'=>'Placa é obrigatória.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // monta payload só com colunas existentes
  $payload = [];

  $map = [
    'placa'             => 'normUpperNoSpace',
    'placa_2'           => 'normUpperNoSpace',
    'placa_3'           => 'normUpperNoSpace',
    'filial_id'         => 'normInt',
    'parceiro_id'       => 'normInt',
    'parceiro_tipo_id'  => 'normInt',
    'tipo_veiculo_id'   => 'normInt',
    'marca_id'          => 'normInt',
    'modelo'            => 'normStr',
    'cor'               => 'normStr',
    'combustivel'       => 'normStr',
    'situacao_id'       => 'normInt',
    'status_operacional'=> 'normStr',
    'ano_fabricacao'    => 'normInt',
    'ano_modelo'        => 'normInt',
    'chassi'            => 'normStr',
    'renavam'           => 'normStr',
    'eixos'             => 'normInt',
    'data_aquisicao'    => 'normStr',
  ];

  foreach ($map as $col => $fn) {
    if (!hasCol($cols, $col)) continue;
    $val = $data[$col] ?? null;
    $payload[$col] = $fn($val);
  }

  $pdo->beginTransaction();

  if ($id > 0) {
    $sets = [];
    $params = [':id'=>$id];

    foreach ($payload as $k=>$v) {
      $sets[] = "`{$k}` = :{$k}";
      $params[":{$k}"] = $v;
    }

    if (hasCol($cols,'atualizado_em')) {
      $sets[] = "atualizado_em = :atualizado_em";
      $params[':atualizado_em'] = nowSql();
    }
    if (hasCol($cols,'atualizado_por')) {
      $sets[] = "atualizado_por = :atualizado_por";
      $params[':atualizado_por'] = $usuario;
    }

    $sql = "UPDATE cad_veiculos SET " . implode(', ', $sets) . " WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $pdo->commit();
    echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // INSERT
  $fields = [];
  $marks  = [];
  $params = [];

  foreach ($payload as $k=>$v) {
    $fields[] = "`{$k}`";
    $marks[]  = ":{$k}";
    $params[":{$k}"] = $v;
  }

  if (hasCol($cols,'criado_em')) {
    $fields[] = "criado_em"; $marks[]=":criado_em"; $params[':criado_em']=nowSql();
  }
  if (hasCol($cols,'criado_por')) {
    $fields[] = "criado_por"; $marks[]=":criado_por"; $params[':criado_por']=$usuario;
  }
  if (hasCol($cols,'atualizado_em')) {
    $fields[] = "atualizado_em"; $marks[]=":atualizado_em"; $params[':atualizado_em']=nowSql();
  }
  if (hasCol($cols,'atualizado_por')) {
    $fields[] = "atualizado_por"; $marks[]=":atualizado_por"; $params[':atualizado_por']=$usuario;
  }

  $sql = "INSERT INTO cad_veiculos (" . implode(',', $fields) . ") VALUES (" . implode(',', $marks) . ")";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $newId = (int)$pdo->lastInsertId();
  $pdo->commit();

  echo json_encode(['ok'=>true,'id'=>$newId], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
