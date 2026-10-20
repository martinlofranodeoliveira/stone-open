<?php
$prefix = $prefix ?? '/_admin';
$__env = strtolower(\App\Support\Env::get('STONE_ENV', 'homolog'));
$__label = ($__env === 'production') ? 'PRODUÇÃO' : 'HOMOLOG';
$__badge = ($__env === 'production') ? 'bg-danger' : 'bg-secondary';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
    <link id="favicon" rel="shortcut icon" href="/assets/images/speedometer.png?rand=<?= rand() ?>" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'Admin', ENT_QUOTES) ?></title>
  <link rel="stylesheet" href="/assets/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/dataTables.bootstrap5.min.css" />
  <link rel="stylesheet" href="/assets/bootstrap-icons.min.css">
  <script>
    (function(){try{var t=localStorage.getItem('adminTheme');document.documentElement.setAttribute('data-theme', t||'light');}catch(e){document.documentElement.setAttribute('data-theme','light');}})();
  </script>
  <link href="/assets/sweetalert2.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/admin.css?Ver=<?= rand() ?>" />
  <style>
    :root {
      --admin-gutter: clamp(16px, 2.2vw, 36px);
      --admin-surface: rgba(255, 255, 255, 0.72);
      --admin-surface-dark: rgba(16, 20, 28, 0.72);
      --admin-edge: rgba(0, 0, 0, 0.04);
    }

    body {
      background:
        linear-gradient(90deg, rgba(0, 0, 0, 0.04) 0%, rgba(0, 0, 0, 0) 14%),
        linear-gradient(270deg, rgba(0, 0, 0, 0.05) 0%, rgba(0, 0, 0, 0) 14%);
    }

    .admin-shell {
      width: 100%;
      padding-left: var(--admin-gutter);
      padding-right: var(--admin-gutter);
      padding-top: 24px;
      padding-bottom: 32px;
    }

    .admin-surface {
      border-radius: 18px;
      padding: clamp(16px, 2vw, 28px);
      background: var(--admin-surface);
      box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
      border: 1px solid var(--admin-edge);
      backdrop-filter: blur(6px);
    }

    [data-theme="dark"] .admin-surface {
      background: var(--admin-surface-dark);
      border-color: rgba(255, 255, 255, 0.06);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    }

    @media (max-width: 768px) {
      .admin-shell {
        padding-left: 12px;
        padding-right: 12px;
      }
    }
  </style>
  <!-- Load JS libs early so inline page scripts can use jQuery/DataTables safely -->
  <script src="/assets/jquery-3.7.1.min.js"></script>
  <script src="/assets/jquery.dataTables.min.js"></script>
  <script src="/assets/dataTables.bootstrap5.min.js"></script>
  <script>
    if ($.fn.dataTable) {
      $.extend(true, $.fn.dataTable.defaults, {
        language: {
            "emptyTable": "Nenhum registro encontrado",
            "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 até 0 de 0 registros",
            "infoFiltered": "(Filtrados de _MAX_ registros)",
            "infoThousands": ".",
            "lengthMenu": "_MENU_ resultados por página",
            "loadingRecords": "Carregando...",
            "processing": "Processando...",
            "zeroRecords": "Nenhum registro encontrado",
            "search": "Pesquisar",
            "paginate": {
                "next": "Próximo",
                "previous": "Anterior",
                "first": "Primeiro",
                "last": "Último"
            },
            "aria": {
                "sortAscending": ": Ordenar colunas de forma ascendente",
                "sortDescending": ": Ordenar colunas de forma descendente"
            }
        }
      });
    }
  </script>
</head>
<body>
  <header class="navbar navbar-expand-lg">
    <div class="container-fluid">
      <a class="navbar-brand" href="<?= $prefix ?>">
        <i class="bi bi-house-door-fill me-2"></i> Stone Hub Admin
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="topNav">
        <ul class="navbar-nav mb-2 mb-lg-0 main-nav">
          <li class="nav-item"><a class="nav-link" href="<?= $prefix ?>">Dashboard</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Relatórios</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= $prefix ?>/reports">Por Site</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/reports/accounts">Por Conta</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Cadastros</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= $prefix ?>/sites">Sites</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/pagarme-accounts">Contas Pagar.me</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/customers">Clientes</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/users">Usuários</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/plans">Planos</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Transações</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= $prefix ?>/payments">Pagamentos</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/payments?type=boleto">Boletos</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/invoices">Faturas</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/payment-links">Links de Pagamento</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/subscriptions">Assinaturas</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Integrações</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= $prefix ?>/webhooks">Webhooks</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/forward-logs">Forward Logs</a></li>
              <li><a class="dropdown-item" href="/api/docs" target="_blank" rel="noopener">API Docs (Swagger)</a></li>
              <li><a class="dropdown-item" href="/api/openapi.json" target="_blank" rel="noopener">OpenAPI JSON</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Verificação Direta</h6></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/verify">Consultas na Stone</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/diagnostics/stone-auth">Teste de Autenticação</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Utils</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= $prefix ?>/access-logs">Logs de Acesso</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/calculadora">Calculadora</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/utils/pix">Gerar PIX</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/utils/boleto">Gerar Boleto</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/utils/card">Pagamento Cartão</a></li>
              <li><a class="dropdown-item" href="<?= $prefix ?>/utils/link">Link de Pagamento</a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="<?= $prefix ?>/help">Ajuda</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $prefix ?>/account">Conta</a></li>
        </ul>
        <div class="navbar-actions d-flex align-items-center">
          <a href="<?= $prefix ?>/logout" class="btn secondary me-2"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
          <a href="#" id="themeToggle" class="btn secondary">
            <i class="bi bi-sun-fill me-1" id="themeIcon"></i>
            <span id="themeLabel">Tema</span>
          </a>
        </div>
      </div>
    </div>
  </header>
  <div class="container-fluid admin-shell">
    <div class="admin-surface">
      <?= $content ?? '' ?>
    </div>
  </div>
  <script src="/assets/sweetalert2.all.min.js"></script>
  <script src="/assets/qrcode.min.js"></script>
  <script src="/assets/admin.js?Ver=<?= VERSAO_SISTEMA ?>"></script>
  <script src="/assets/bootstrap.bundle.min.js"></script>
  <script>
    // Ensure Bootstrap modals are attached to <body> to avoid stacking-context issues
    document.addEventListener('show.bs.modal', function(ev){
      var modal = ev.target;
      if (modal && modal.parentElement && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
      }
    });
  </script>
</body>
</html>
