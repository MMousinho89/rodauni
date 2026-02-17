<?php
// pages/marcas.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Conexão PDO compatível com qualquer padrão do seu database.php
 */
function ru_get_pdo(): PDO {
  if (function_exists('getPDO')) return getPDO();
  if (function_exists('get_pdo')) return get_pdo();

  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
  if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) return $GLOBALS['conn'];
  if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) return $GLOBALS['db'];

  $host = defined('DB_HOST') ? DB_HOST : (defined('MYSQL_HOST') ? MYSQL_HOST : '127.0.0.1');
  $name = defined('DB_NAME') ? DB_NAME : (defined('MYSQL_DB') ? MYSQL_DB : 'rodauni');
  $user = defined('DB_USER') ? DB_USER : (defined('MYSQL_USER') ? MYSQL_USER : 'root');
  $pass = defined('DB_PASS') ? DB_PASS : (defined('MYSQL_PASS') ? MYSQL_PASS : '');

  $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

/**
 * Base /rodauni/sistema para montar paths.
 */
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'); // /rodauni/sistema/pages
$root = preg_replace('#/pages$#', '', $scriptDir);              // /rodauni/sistema

function ru_inject_before(string $html, string $needle, string $inject): string {
  if (stripos($html, $needle) === false) return $html . $inject;
  return str_ireplace($needle, $inject . $needle, $html);
}

$cssInject = "\n" . implode("\n", [
  // Bootstrap local + fallback CDN
  "<link rel=\"stylesheet\" href=\"{$root}/assets/bootstrap.min.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/public/bootstrap.min.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/assets/css/bootstrap.min.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/public/css/bootstrap.min.css\">",
  "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\">",

  // Seu CSS ru-*
  "<link rel=\"stylesheet\" href=\"{$root}/assets/custom.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/assets/css/custom.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/public/custom.css\">",
  "<link rel=\"stylesheet\" href=\"{$root}/public/css/custom.css\">",
]) . "\n";

$jsInject = "\n" . implode("\n", [
  "<script src=\"{$root}/assets/bootstrap.bundle.min.js\"></script>",
  "<script src=\"{$root}/public/bootstrap.bundle.min.js\"></script>",
  "<script src=\"{$root}/assets/js/bootstrap.bundle.min.js\"></script>",
  "<script src=\"{$root}/public/js/bootstrap.bundle.min.js\"></script>",
  "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>",
]) . "\n";

// Header com injeção de CSS
ob_start();
include __DIR__ . '/../partials/header.php';
$headerHtml = ob_get_clean();
$headerHtml = ru_inject_before($headerHtml, "</head>", $cssInject);
echo $headerHtml;

include __DIR__ . '/../partials/sidebar.php';

$usuarioLogado = $_SESSION['usuario_nome'] ?? '';
?>
<div class="ru-main">
  <div class="container-fluid py-3">

    <!-- Cabeçalho + Toolbar (igual Veículos) -->
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
      <div>
        <div class="text-muted">Cadastros</div>
        <h2 class="mb-0">Marcas</h2>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button id="btnIncluir" class="btn btn-success">
          <span class="me-1">+</span> Incluir
        </button>
        <button id="btnEditar" class="btn btn-primary">
          Editar
        </button>
        <button id="btnSalvar" class="btn btn-primary" style="background:#4f7cff;border-color:#4f7cff;">
          Salvar
        </button>
        <button id="btnCancelar" class="btn btn-warning">
          Cancelar
        </button>
        <button id="btnExcluir" class="btn btn-danger">
          Excluir
        </button>
        <button id="btnVoltar" class="btn btn-dark" onclick="history.back()">
          Voltar
        </button>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">

        <!-- Abas (igual Veículos) -->
        <ul class="nav nav-tabs" id="tabsMarcas" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-dados-btn" data-bs-toggle="tab" data-bs-target="#tabDados" type="button" role="tab">
              DADOS
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-lista-btn" data-bs-toggle="tab" data-bs-target="#tabLista" type="button" role="tab">
              LISTA
            </button>
          </li>
        </ul>

        <form id="formMarca" class="mt-3" autocomplete="off">
          <div class="tab-content">

            <!-- DADOS -->
            <div class="tab-pane fade show active" id="tabDados" role="tabpanel">

              <div class="row g-3">
                <div class="col-12 col-md-2">
                  <label class="form-label">Código</label>
                  <input type="text" id="id" name="id" class="form-control" readonly>
                </div>

                <div class="col-12 col-md-7">
                  <label class="form-label">Marca <span class="text-danger">*</span></label>
                  <input type="text" id="nome" name="nome" class="form-control" maxlength="100" placeholder="Ex.: Volvo, Scania, Mercedes...">
                  <div class="invalid-feedback">Informe o nome da marca.</div>
                </div>

                <div class="col-12 col-md-3">
                  <label class="form-label">Ativo</label>
                  <select id="ativo" name="ativo" class="form-select">
                    <option value="1">SIM</option>
                    <option value="0">NÃO</option>
                  </select>
                </div>
              </div>

            </div>

            <!-- LISTA -->
            <div class="tab-pane fade" id="tabLista" role="tabpanel">

              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-9">
                  <label class="form-label">Buscar</label>
                  <input type="text" id="filtroLista" class="form-control" placeholder="Nome da marca...">
                </div>
                <div class="col-12 col-md-3 d-grid">
                  <button type="button" id="btnAtualizarLista" class="btn btn-outline-primary">
                    Atualizar
                  </button>
                </div>
              </div>

              <div class="table-responsive mt-3">
                <table class="table table-hover align-middle" id="tblLista">
                  <thead>
                    <tr>
                      <th style="width:90px;">ID</th>
                      <th>Marca</th>
                      <th style="width:120px;">Ativo</th>
                      <th style="width:210px;">Criado em</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="text-muted small">
                Clique em uma linha para selecionar. Duplo clique abre o cadastro.
              </div>
            </div>

          </div>
        </form>

        <hr class="my-3">

        <!-- Rodapé auditoria (igual Veículos) -->
        <div class="d-flex flex-wrap gap-2">
          <span class="badge bg-light text-dark border">Logado: <strong><?= htmlspecialchars($usuarioLogado) ?></strong></span>
          <span class="badge bg-light text-dark border">Criado: <strong id="badgeCriado">—</strong></span>
          <span class="badge bg-light text-dark border">Atualizado: <strong id="badgeAtualizado">—</strong></span>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
  // =========================
  // Estado (padrão Veículos)
  // =========================
  const state = {
    mode: 'view',      // view | new | edit
    selectedId: null,  // ID selecionado na lista
    loadedId: null     // ID carregado no form
  };

  const el = (id) => document.getElementById(id);

  function setToolbar() {
    const isView = state.mode === 'view';
    const isNew  = state.mode === 'new';
    const isEdit = state.mode === 'edit';
    const hasSelected = !!state.selectedId;

    el('btnIncluir').disabled  = !isView;
    el('btnEditar').disabled   = !(isView && hasSelected);
    el('btnSalvar').disabled   = !(isNew || isEdit);
    el('btnCancelar').disabled = !(isNew || isEdit);
    el('btnExcluir').disabled  = !(isView && hasSelected);
  }

  function setFormEnabled(enabled) {
    // Regra: SOMENTE DADOS trava/destrava. LISTA sempre ativa.
    const form = el('formMarca');
    const elements = form.querySelectorAll('input, select, textarea, button');

    elements.forEach(x => {
      // ignora tudo dentro da LISTA (busca/atualizar sempre habilitados)
      if (x.closest('#tabLista')) return;
      if (x.id === 'id') { x.readOnly = true; return; }

      if (x.tagName === 'INPUT') {
        x.readOnly = !enabled;
        x.disabled = false;
      } else {
        x.disabled = !enabled;
      }
    });
  }

  function clearErrors() {
    el('nome').classList.remove('is-invalid');
  }

  function validateForm() {
    clearErrors();
    const nome = (el('nome').value || '').trim();
    if (!nome) {
      el('nome').classList.add('is-invalid');
      return false;
    }
    return true;
  }

  function fillForm(data) {
    el('id').value = data.id ?? '';
    el('nome').value = data.nome ?? '';
    el('ativo').value = (String(data.ativo) === '0') ? '0' : '1';

    state.loadedId = data.id ?? null;

    // Auditoria (tabela só tem criado_em)
    el('badgeCriado').textContent = data.criado_em ? String(data.criado_em) : '—';
    el('badgeAtualizado').textContent = data.atualizado_em ? String(data.atualizado_em) : '—';
  }

  function clearForm() {
    fillForm({ id: '', nome: '', ativo: 1, criado_em: null });
    state.loadedId = null;
  }

  // =========================
  // Bootstrap tab helper
  // =========================
  function goTab(btnSelector) {
    const btn = document.querySelector(btnSelector);
    if (!btn || typeof bootstrap === 'undefined') return;
    const t = new bootstrap.Tab(btn);
    t.show();
  }

  // =========================
  // API
  // =========================
  async function apiGet(url) {
    const r = await fetch(url, { credentials: 'same-origin' });
    const j = await r.json();
    if (!j || j.status !== 'ok') throw new Error(j?.message || 'Erro na API');
    return j;
  }

  async function apiPost(url, payload) {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
    const j = await r.json();
    if (!j || j.status !== 'ok') throw new Error(j?.message || 'Erro na API');
    return j;
  }

  // =========================
  // LISTA
  // =========================
  function renderLista(rows) {
    const tbody = el('tblLista').querySelector('tbody');
    tbody.innerHTML = '';

    rows.forEach(row => {
      const tr = document.createElement('tr');
      tr.dataset.id = row.id;

      if (String(state.selectedId) === String(row.id)) {
        tr.classList.add('table-primary');
      }

      tr.innerHTML = `
        <td>${row.id}</td>
        <td>${escapeHtml(row.nome || '')}</td>
        <td>${String(row.ativo) === '1' ? 'SIM' : 'NÃO'}</td>
        <td>${row.criado_em ? escapeHtml(String(row.criado_em)) : '—'}</td>
      `;

      // Clique simples: seleciona e carrega em background (não troca aba)
      tr.addEventListener('click', async () => {
        selectRow(tr);
        await carregarRegistro(state.selectedId, true);
      });

      // Duplo clique: abre DADOS em modo view
      tr.addEventListener('dblclick', async () => {
        selectRow(tr);
        await carregarRegistro(state.selectedId, true);
        state.mode = 'view';
        setToolbar();
        setFormEnabled(false);
        goTab('#tab-dados-btn');
      });

      tbody.appendChild(tr);
    });
  }

  function selectRow(tr) {
    const tbody = el('tblLista').querySelector('tbody');
    [...tbody.querySelectorAll('tr')].forEach(x => x.classList.remove('table-primary'));
    tr.classList.add('table-primary');
    state.selectedId = tr.dataset.id;
    setToolbar();
  }

  async function carregarLista() {
    const f = (el('filtroLista').value || '').trim();
    const j = await apiGet(`marcas_save.php?action=list&f=${encodeURIComponent(f)}`);
    renderLista(j.rows || []);
  }

  async function carregarRegistro(id, silent) {
    if (!id) return;
    const j = await apiGet(`marcas_save.php?action=get&id=${encodeURIComponent(id)}`);
    fillForm(j.data || {});
    if (!silent) {
      // nada
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // =========================
  // Toolbar actions
  // =========================
  el('btnIncluir').addEventListener('click', () => {
    state.mode = 'new';
    state.selectedId = null;
    setToolbar();
    clearErrors();
    clearForm();
    setFormEnabled(true);
    goTab('#tab-dados-btn');
    el('nome').focus();
  });

  el('btnEditar').addEventListener('click', async () => {
    if (!state.selectedId) return;
    await carregarRegistro(state.selectedId, true);
    state.mode = 'edit';
    setToolbar();
    setFormEnabled(true);
    goTab('#tab-dados-btn');
    el('nome').focus();
  });

  el('btnCancelar').addEventListener('click', async () => {
    state.mode = 'view';
    clearErrors();
    setToolbar();
    setFormEnabled(false);

    if (state.selectedId) {
      await carregarRegistro(state.selectedId, true);
    } else {
      clearForm();
    }
  });

  el('btnSalvar').addEventListener('click', async () => {
    if (!validateForm()) return;

    const payload = {
      id: el('id').value ? Number(el('id').value) : null,
      nome: (el('nome').value || '').trim(),
      ativo: Number(el('ativo').value)
    };

    try {
      const j = await apiPost('marcas_save.php', payload);

      state.mode = 'view';
      state.selectedId = j.id;
      setToolbar();
      setFormEnabled(false);

      await carregarLista();

      // re-seleciona linha salva
      const tr = el('tblLista').querySelector(`tbody tr[data-id="${j.id}"]`);
      if (tr) selectRow(tr);

      await carregarRegistro(j.id, true);
    } catch (e) {
      alert(e.message || e);
    }
  });

  el('btnExcluir').addEventListener('click', async () => {
    if (!state.selectedId) return;
    const ok = confirm(`Confirma excluir a marca ID ${state.selectedId}?`);
    if (!ok) return;

    try {
      await apiPost('marcas_delete.php', { id: Number(state.selectedId) });

      state.selectedId = null;
      state.loadedId = null;
      state.mode = 'view';

      clearForm();
      setToolbar();
      setFormEnabled(false);

      await carregarLista();
    } catch (e) {
      alert(e.message || e);
    }
  });

  // LISTA: buscar/atualizar
  el('btnAtualizarLista').addEventListener('click', carregarLista);
  el('filtroLista').addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      carregarLista();
    }
  });

  // Init
  (async function init() {
    state.mode = 'view';
    setToolbar();
    setFormEnabled(false);
    await carregarLista();
    // começa em DADOS igual veículos (se quiser iniciar na LISTA, descomenta)
    // goTab('#tab-lista-btn');
  })();
</script>

<?php
// Footer com injeção de JS (bootstrap.bundle)
ob_start();
include __DIR__ . '/../partials/footer.php';
$footerHtml = ob_get_clean();
$footerHtml = ru_inject_before($footerHtml, "</body>", $jsInject);
echo $footerHtml;
