<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$field = preg_replace('/[^a-zA-Z0-9_]/','', (string)($_GET['field'] ?? 'classificacao_id'));
$q = trim((string)($_GET['q'] ?? ''));

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

$table = 'cad_classificacoes';
$cols  = tableColumns($pdo, $table);

if(!$cols){
  http_response_code(500);
  echo "Tabela {$table} não encontrada.";
  exit;
}

$descCol =
  hasCol($cols,'descricao') ? 'descricao' :
  (hasCol($cols,'nome') ? 'nome' :
  (hasCol($cols,'titulo') ? 'titulo' : null));

if(!$descCol){
  http_response_code(500);
  echo "Tabela {$table} precisa ter coluna descricao/nome/titulo.";
  exit;
}

$sql = "SELECT id, {$descCol} AS descr";
if(hasCol($cols,'ativo')) $sql .= ", ativo";
$sql .= " FROM {$table} WHERE 1=1 ";

$params = [];
if($q !== ''){
  $sql .= " AND (CAST(id AS CHAR) LIKE :q OR {$descCol} LIKE :q) ";
  $params[':q'] = "%{$q}%";
}

$sql .= " ORDER BY {$descCol} ASC LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RODAUNI — Lookup Classificações</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/custom.css?v=<?= @filemtime(__DIR__ . '/../assets/css/custom.css') ?>">

  <style>
    body{ background:#e9ecef; }
    .ru-card{ border:0; box-shadow:0 .5rem 1rem rgba(0,0,0,.10); }
    .ru-field-label{ font-size:.75rem; font-weight:600; color:#6b7280; margin-bottom:.25rem; }
    .ru-row{ cursor:pointer; }
    .ru-row:hover{ background: rgba(13,110,253,.06); }
    .ru-row-selected{ outline:2px solid rgba(13,110,253,.35); background: rgba(13,110,253,.06); }
    .small-dim{ color:#6b7280; font-size:.85rem; }
  </style>
</head>
<body>
  <div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="text-muted small">Consultar</div>
        <h5 class="mb-0"><i class="bi bi-tags me-1"></i>Classificações</h5>
      </div>
      <button class="btn btn-outline-dark btn-sm" onclick="window.close()">
        <i class="bi bi-x-lg me-1"></i>Fechar
      </button>
    </div>

    <div class="card ru-card">
      <div class="card-body">
        <form class="row g-2 align-items-end mb-2" method="get">
          <input type="hidden" name="field" value="<?= htmlspecialchars($field) ?>">
          <div class="col-md-8">
            <label class="ru-field-label">Buscar (ID ou Descrição)</label>
            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ex.: 10 ou CLIENTE">
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" type="submit">
              <i class="bi bi-search me-1"></i>Buscar
            </button>
          </div>
          <div class="col-md-2">
            <a class="btn btn-outline-secondary btn-sm w-100" href="?field=<?= urlencode($field) ?>">
              <i class="bi bi-eraser me-1"></i>Limpar
            </a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:90px;">ID</th>
                <th>Descrição</th>
                <?php if(hasCol($cols,'ativo')): ?>
                  <th style="width:90px;">Ativo</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="<?= hasCol($cols,'ativo')?3:2 ?>" class="text-muted small">Nenhum registro.</td></tr>
              <?php else: ?>
                <?php foreach($rows as $r): ?>
                  <tr class="ru-row" data-id="<?= (int)$r['id'] ?>" data-label="<?= htmlspecialchars((string)$r['descr']) ?>">
                    <td><?= (int)$r['id'] ?></td>
                    <td><strong><?= htmlspecialchars((string)$r['descr']) ?></strong></td>
                    <?php if(hasCol($cols,'ativo')): ?>
                      <td><?= ((string)($r['ativo'] ?? '1') === '1') ? 'SIM' : 'NÃO' ?></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="small-dim mt-2">
          Clique para selecionar. Duplo clique confirma e fecha.
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const field = <?= json_encode($field) ?>;

  function send(id, label){
    try{
      if(window.opener && typeof window.opener.ruReceiveLookup === 'function'){
        window.opener.ruReceiveLookup(field, id, label);
      } else if(window.opener){
        window.opener.postMessage({ type:'ru_lookup', field, id, label }, '*');
      }
    }catch(e){}
  }

  let selectedId = null;

  document.querySelectorAll('tr.ru-row').forEach(tr=>{
    let clickTimer = null;

    tr.addEventListener('click', ()=>{
      clearTimeout(clickTimer);
      clickTimer = setTimeout(()=>{
        document.querySelectorAll('tr.ru-row').forEach(x=>x.classList.remove('ru-row-selected'));
        tr.classList.add('ru-row-selected');
        selectedId = tr.dataset.id;
      }, 180);
    });

    tr.addEventListener('dblclick', ()=>{
      clearTimeout(clickTimer);
      const id = tr.dataset.id;
      const label = tr.dataset.label || ('#'+id);
      send(id, label);
      window.close();
    });
  });

  window.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter' && selectedId){
      e.preventDefault();
      const tr = document.querySelector(`tr.ru-row[data-id="${selectedId}"]`);
      if(!tr) return;
      send(tr.dataset.id, tr.dataset.label || ('#'+tr.dataset.id));
      window.close();
    }
    if(e.key === 'Escape') window.close();
  });
})();
</script>
</body>
</html>
