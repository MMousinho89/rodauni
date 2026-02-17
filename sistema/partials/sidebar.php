<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuarioLogado = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuarioInicial = mb_strtoupper(mb_substr($usuarioLogado, 0, 1, 'UTF-8'));
?>

<aside class="ru-sidebar" id="ruSidebar">

    <!-- TOPO -->
    <div class="ru-header">
        <div class="ru-header-info">
            <div class="ru-logo-title">RODAUNI</div>
            <div class="ru-logo-sub">Gestão de Transporte</div>
        </div>

        <div class="ru-hamburger-inside" onclick="toggleSidebarFull()">
            ☰
        </div>
    </div>

    <!-- MENUS -->
    <div class="ru-scroll-area">

        <!-- DASHBOARD -->
        <div class="ru-section">DASHBOARD</div>
        <div class="ru-menu-block"
             onclick="window.location.href='<?= BASE_URL ?>/pages/dashboard.php'">
            <div class="ru-link-head">
                <div class="ru-link-left">
                    🏠 <span class="ru-menu-text">Painel Geral</span>
                </div>
            </div>
        </div>

        <!-- CADASTROS -->
        <div class="ru-section">CADASTROS</div>
        <div class="ru-menu-block" onclick="toggleMenu('mCadastros')">
            <div class="ru-link-head">
                <div class="ru-link-left">
                    📋 <span class="ru-menu-text">Cadastros</span>
                </div>
                <span class="ru-menu-caret">▼</span>
            </div>
            <div id="mCadastros" class="ru-submenu">
                <a href="<?= BASE_URL ?>/pages/veiculos.php">🚚 Veículos</a>
                <a href="<?= BASE_URL ?>/pages/marcas.php">🏷 Marcas</a>
                <a href="<?= BASE_URL ?>/pages/filiais.php">🏢 Filiais</a>
                <a href="<?= BASE_URL ?>/pages/parceiros.php">👥 Parceiros</a>
            </div>
        </div>

        <!-- PROGRAMAÇÃO -->
        <div class="ru-section">PROGRAMAÇÃO</div>
        <div class="ru-menu-block" onclick="toggleMenu('mProg')">
            <div class="ru-link-head">
                <div class="ru-link-left">
                    📆 <span class="ru-menu-text">Programação</span>
                </div>
                <span class="ru-menu-caret">▼</span>
            </div>
            <div id="mProg" class="ru-submenu">
                <a href="#">Checklist</a>
            </div>
        </div>

        <!-- FINANCEIRO -->
        <div class="ru-section">FINANCEIRO</div>
        <div class="ru-menu-block" onclick="toggleMenu('mFin')">
            <div class="ru-link-head">
                <div class="ru-link-left">
                    💰 <span class="ru-menu-text">Financeiro</span>
                </div>
                <span class="ru-menu-caret">▼</span>
            </div>
            <div id="mFin" class="ru-submenu">
                <a href="#">Contas a pagar</a>
                <a href="#">Contas a receber</a>
            </div>
        </div>

    </div>

    <!-- RODAPÉ -->
    <div class="ru-footer-fixed">
        <div class="ru-user-block">
            <div class="ru-avatar-circle"><?= htmlspecialchars($usuarioInicial) ?></div>
            <div class="ru-user-name"><?= htmlspecialchars($usuarioLogado) ?></div>
        </div>

        <a class="ru-logout-link"
           href="<?= BASE_URL ?>/pages/dashboard.php?logout=1">
            📤 <span class="ru-logout-text">Sair</span>
        </a>
    </div>

</aside>

<script>
function toggleMenu(id){
    var el = document.getElementById(id);
    if(!el) return;

    var isVisible = el.style.display === 'block';
    document.querySelectorAll('.ru-submenu').forEach(function(box){
        box.style.display = 'none';
    });

    el.style.display = isVisible ? 'none' : 'block';
}

function toggleSidebarFull(){
    var sidebar = document.getElementById('ruSidebar');
    sidebar.classList.toggle('ru-collapsed');
}
</script>
