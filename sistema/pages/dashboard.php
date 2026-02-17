<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// LOGOUT
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/public/login.php?msg=logout_ok");
    exit;
}

// ======= EXEMPLOS DE DADOS MOCKADOS =======
// Aqui você depois troca pra SELECT real no banco
$frotaAtiva  = '--';
$manutencao  = '--';
$disponiveis = '--';
$emRota      = '--';

$alertas = [
    "Pneu dianteiro da placa ASY6E00 no limite.",
    "Seguro do veículo ATM8A55 vence em 3 dias."
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>RODAUNI — Dashboard</title>

  <!-- Bootstrap + Ícones -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- CSS do sistema -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/../partials/sidebar.php'; ?>

<main class="ru-main">

    <h2 class="mb-4">Dashboard</h2>

    <!-- Cards de status da frota -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Frota Ativa</h6>
                    <h3 class="fw-bold text-primary"><?php echo $frotaAtiva; ?></h3>
                    <div class="small text-muted">Veículos operando</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Manutenção</h6>
                    <h3 class="fw-bold text-warning"><?php echo $manutencao; ?></h3>
                    <div class="small text-muted">Em oficina / parada</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Disponíveis</h6>
                    <h3 class="fw-bold text-success"><?php echo $disponiveis; ?></h3>
                    <div class="small text-muted">Prontos pra rodar</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Em Rota</h6>
                    <h3 class="fw-bold text-info"><?php echo $emRota; ?></h3>
                    <div class="small text-muted">Viagens em andamento</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas operacionais -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 pb-0">
            <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Alertas Operacionais</h6>
        </div>

        <div class="card-body pt-2">
            <?php if (count($alertas) === 0): ?>
                <div class="text-muted small">Nenhum alerta no momento.</div>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($alertas as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Botão Importar Programação -->
            <div class="mt-3">
                <form action="<?= BASE_URL ?>/public/importar_programacao.php" method="post">
                    <button
                        type="submit"
                        class="btn btn-primary fw-semibold shadow-sm"
                        style="min-width:220px;"
                    >
                        <i class="bi bi-cloud-download me-1"></i> Importar Programação
                    </button>
                </form>
            </div>

        </div>
    </div>

</main>

<!-- Bootstrap bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
