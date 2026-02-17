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

function hasCol(array $cols, string $c): bool {
    return in_array($c, $cols, true);
}

function nowSql(): string {
    return date('Y-m-d H:i:s');
}

function normStr($v): ?string {
    $s = trim((string)$v);
    return ($s === '') ? null : $s;
}

function normCep($v): ?string {
    $s = preg_replace('/\D/', '', (string)$v);
    if ($s === '') return null;
    $s = substr($s, 0, 8);
    if (strlen($s) === 8) return substr($s,0,5) . '-' . substr($s,5);
    return $s;
}

function normUF($v): ?string {
    $s = strtoupper(trim((string)$v));
    $s = preg_replace('/[^A-Z]/', '', $s);
    if ($s === '') return null;
    return substr($s, 0, 2);
}

function isValidEmail($v): bool {
    $s = trim((string)$v);
    if ($s === '') return true;
    return (bool)filter_var($s, FILTER_VALIDATE_EMAIL);
}

function digitsOnly($v): string {
    return preg_replace('/\D/', '', (string)$v);
}

function formatCnpj($v): ?string {
    $d = digitsOnly($v);
    if ($d === '') return null;
    $d = substr($d, 0, 14);
    if (strlen($d) !== 14) return $d; // deixa “como veio” (vai falhar na validação se exigida)
    return substr($d,0,2) . '.' . substr($d,2,3) . '.' . substr($d,5,3) . '/' . substr($d,8,4) . '-' . substr($d,12,2);
}

function isValidCnpj($cnpjRaw): bool {
    $cnpj = digitsOnly($cnpjRaw);
    if ($cnpj === '') return true; // vazio permitido
    if (strlen($cnpj) !== 14) return false;
    if (preg_match('/^(\d)\1+$/', $cnpj)) return false;

    $calc = function($base) {
        $len = strlen($base);
        $sum = 0;
        $pos = $len - 7;
        for ($i = $len; $i >= 1; $i--) {
            $sum += (int)$base[$len - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        $mod = $sum % 11;
        return ($mod < 2) ? 0 : 11 - $mod;
    };

    $base12 = substr($cnpj, 0, 12);
    $dv1 = $calc($base12);
    $dv2 = $calc($base12 . $dv1);

    return $cnpj === ($base12 . $dv1 . $dv2);
}

/**
 * Busca estado/pais em cad_estados pela UF
 */
function lookupEstado(PDO $pdo, string $uf): ?array {
    $st = $pdo->prepare("SELECT uf, nome, pais, ativo FROM cad_estados WHERE uf = :uf LIMIT 1");
    $st->execute([':uf' => $uf]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

try {
    $table = 'cad_filiais';
    $cols = tableColumns($pdo, $table);
    if (!$cols) {
        echo json_encode(['ok' => false, 'error' => "Tabela {$table} não encontrada."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_GET['action'] ?? '';

    // =========================
    // LIST
    // =========================
    if ($action === 'list') {
        $f = trim((string)($_GET['f'] ?? ''));

        $sql = "SELECT id";
        if (hasCol($cols, 'nome_filial')) $sql .= ", nome_filial";
        if (hasCol($cols, 'cnpj'))       $sql .= ", cnpj";
        if (hasCol($cols, 'municipio'))  $sql .= ", municipio";
        if (hasCol($cols, 'uf'))         $sql .= ", uf";
        if (hasCol($cols, 'ativo'))      $sql .= ", ativo";
        $sql .= " FROM {$table} ";

        $params = [];
        if ($f !== '') {
            $where = [];

            if (hasCol($cols, 'nome_filial')) $where[] = "nome_filial LIKE :f";
            if (hasCol($cols, 'cnpj'))       $where[] = "cnpj LIKE :f";
            if (hasCol($cols, 'municipio'))  $where[] = "municipio LIKE :f";
            if (hasCol($cols, 'uf'))         $where[] = "uf LIKE :f";

            if ($where) {
                $sql .= " WHERE (" . implode(" OR ", $where) . ") ";
                $params[':f'] = '%' . $f . '%';
            }
        }

        $sql .= " ORDER BY " . (hasCol($cols, 'nome_filial') ? "nome_filial" : "id") . " ASC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================
    // GET
    // =========================
    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $st = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'row' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================
    // SAVE (POST JSON)
    // =========================
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

    $payload = [];

    // Obrigatório: nome_filial
    if (hasCol($cols, 'nome_filial')) {
        $nome = trim((string)($data['nome_filial'] ?? ''));
        if ($nome === '') {
            echo json_encode(['ok' => false, 'error' => 'Nome da filial é obrigatório.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payload['nome_filial'] = $nome;
    }

    // Ativo
    if (hasCol($cols, 'ativo')) {
        $ativoRaw = $data['ativo'] ?? 1;
        $ativo = 1;

        if (is_string($ativoRaw)) {
            $u = strtoupper(trim($ativoRaw));
            if ($u === '0' || $u === 'NAO' || $u === 'NÃO' || $u === 'INATIVO') $ativo = 0;
            else $ativo = 1;
        } else {
            $ativo = ((int)$ativoRaw) ? 1 : 0;
        }

        $payload['ativo'] = $ativo;
    }

    // ===== Campos Empresa (se existirem) =====
    if (hasCol($cols, 'razao_social'))  $payload['razao_social']  = normStr($data['razao_social'] ?? null);
    if (hasCol($cols, 'nome_fantasia')) $payload['nome_fantasia'] = normStr($data['nome_fantasia'] ?? null);

    if (hasCol($cols, 'cnpj')) {
        $cnpjIn = $data['cnpj'] ?? null;
        $cnpjFmt = formatCnpj($cnpjIn);
        if ($cnpjFmt !== null && !isValidCnpj($cnpjFmt)) {
            echo json_encode(['ok' => false, 'error' => 'CNPJ inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payload['cnpj'] = $cnpjFmt;
    }

    if (hasCol($cols, 'inscricao_estadual'))  $payload['inscricao_estadual']  = normStr($data['inscricao_estadual'] ?? null);
    if (hasCol($cols, 'inscricao_municipal')) $payload['inscricao_municipal'] = normStr($data['inscricao_municipal'] ?? null);

    // Campos opcionais
    $map = [
        'cep'           => 'normCep',
        'endereco'      => 'normStr',
        'numero'        => 'normStr',
        'complemento'   => 'normStr',
        'bairro'        => 'normStr',
        'municipio'     => 'normStr',
        'cod_municipio' => 'normStr',
        'uf'            => 'normUF',
        'estado'        => 'normStr',
        'pais'          => 'normStr',
        'telefone1'     => 'normStr',
        'email'         => 'normStr',
    ];

    foreach ($map as $col => $fn) {
        if (!hasCol($cols, $col)) continue;

        $val = $data[$col] ?? null;

        if ($col === 'email') {
            $email = trim((string)$val);
            if ($email !== '' && !isValidEmail($email)) {
                echo json_encode(['ok' => false, 'error' => 'E-mail inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $payload[$col] = normStr($email);
            continue;
        }

        $payload[$col] = $fn($val);
    }

    // País default
    if (hasCol($cols, 'pais')) {
        if (!isset($payload['pais']) || $payload['pais'] === null) {
            $payload['pais'] = 'BRASIL';
        } else {
            $payload['pais'] = strtoupper((string)$payload['pais']);
        }
    }

    // Se UF veio preenchida, tenta completar Estado/Pais pelo cad_estados
    if (hasCol($cols, 'uf') && hasCol($cols, 'estado')) {
        $uf = $payload['uf'] ?? null;
        if ($uf) {
            try {
                $st = $pdo->query("SHOW TABLES LIKE 'cad_estados'");
                $exists = (bool)$st->fetchColumn();
                if ($exists) {
                    $rowUF = lookupEstado($pdo, $uf);
                    if ($rowUF) {
                        if (!($payload['estado'] ?? null)) $payload['estado'] = $rowUF['nome'] ?? null;
                        if (hasCol($cols, 'pais') && !($payload['pais'] ?? null)) $payload['pais'] = $rowUF['pais'] ?? 'BRASIL';
                    }
                }
            } catch (Throwable $e) {
                // silencioso
            }
        }
    }

    $pdo->beginTransaction();

    if ($id > 0) {
        // UPDATE
        $sets = [];
        $params = [':id' => $id];

        foreach ($payload as $k => $v) {
            $sets[] = "`{$k}` = :{$k}";
            $params[":{$k}"] = $v;
        }

        if (hasCol($cols, 'atualizado_em')) {
            $sets[] = "atualizado_em = :atualizado_em";
            $params[':atualizado_em'] = nowSql();
        }
        if (hasCol($cols, 'atualizado_por')) {
            $sets[] = "atualizado_por = :atualizado_por";
            $params[':atualizado_por'] = $usuario;
        }

        if (!$sets) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Nada para atualizar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
        $st = $pdo->prepare($sql);
        $st->execute($params);

        $pdo->commit();
        echo json_encode([
            'ok' => true,
            'id' => $id,
            'label' => $payload['nome_filial'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // INSERT
    $fields = [];
    $marks  = [];
    $params = [];

    foreach ($payload as $k => $v) {
        $fields[] = "`{$k}`";
        $marks[]  = ":{$k}";
        $params[":{$k}"] = $v;
    }

    if (hasCol($cols, 'criado_em')) {
        $fields[] = "criado_em";
        $marks[]  = ":criado_em";
        $params[':criado_em'] = nowSql();
    }
    if (hasCol($cols, 'criado_por')) {
        $fields[] = "criado_por";
        $marks[]  = ":criado_por";
        $params[':criado_por'] = $usuario;
    }
    if (hasCol($cols, 'atualizado_em')) {
        $fields[] = "atualizado_em";
        $marks[]  = ":atualizado_em";
        $params[':atualizado_em'] = nowSql();
    }
    if (hasCol($cols, 'atualizado_por')) {
        $fields[] = "atualizado_por";
        $marks[]  = ":atualizado_por";
        $params[':atualizado_por'] = $usuario;
    }

    if (!$fields) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Payload vazio.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES (" . implode(',', $marks) . ")";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $newId = (int)$pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'id' => $newId,
        'label' => $payload['nome_filial'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
