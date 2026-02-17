<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Confere tabela
    $st = $pdo->query("SHOW TABLES LIKE 'cad_estados'");
    if (!$st->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'Tabela cad_estados não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_GET['action'] ?? '';

    // LISTA para popular select (somente ativos)
    if ($action === 'list') {
        $f = trim((string)($_GET['f'] ?? ''));

        $sql = "SELECT uf, nome, pais, ativo
                FROM cad_estados
                WHERE ativo = 1";
        $params = [];

        if ($f !== '') {
            $sql .= " AND (uf LIKE :f OR nome LIKE :f)";
            $params[':f'] = '%' . $f . '%';
        }

        $sql .= " ORDER BY nome ASC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GET por UF
    $uf = strtoupper(trim((string)($_GET['uf'] ?? '')));
    $uf = preg_replace('/[^A-Z]/', '', $uf);
    $uf = substr($uf, 0, 2);

    if ($uf === '') {
        echo json_encode(['ok' => false, 'error' => 'UF não informada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $pdo->prepare("SELECT uf, nome, pais, ativo FROM cad_estados WHERE uf = :uf LIMIT 1");
    $st->execute([':uf' => $uf]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'UF não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'row' => $row], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
