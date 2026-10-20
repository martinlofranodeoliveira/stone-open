<?php ob_start(); ?>
<div class="mb-3">
  <h1 class="mb-1">Relatórios por Conta</h1>
  <p class="text-muted">Visualize receita e transações agregadas por conta do Pagar.me no período selecionado.</p>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link btn btn-outline" href="<?= $prefix ?>/reports">Por Site</a></li>
    <li class="nav-item"><a class="nav-link btn btn-primary" href="<?= $prefix ?>/reports/accounts">Por Conta</a></li>
  </ul>
</div>

<form class="panel mb-4" method="get" action="<?= $prefix ?>/reports/accounts">
  <div class="cols-6 gap-lg row">
    <div class="col-sm-12 col-md-3">
      <label class="form-label">De</label>
      <input class="form-control" type="date" name="from" value="<?= htmlspecialchars((string)($from ?? ''), ENT_QUOTES) ?>" />
    </div>
    <div class="col-sm-12 col-md-3">
      <label class="form-label">Até</label>
      <input class="form-control" type="date" name="to" value="<?= htmlspecialchars((string)($to ?? ''), ENT_QUOTES) ?>" />
    </div>
  </div>
  <div class="mt-3 flex gap-sm">
    <button class="btn primary" type="submit"><i class="bi bi-funnel me-1"></i> Aplicar Filtros</button>
    <a class="btn secondary" href="<?= $prefix ?>/reports/accounts/export?<?= http_build_query(['from'=>$from,'to'=>$to]) ?>" target="_blank"><i class="bi bi-filetype-csv me-1"></i> Exportar CSV</a>
    <a class="btn secondary" href="<?= $prefix ?>/reports/accounts/export-xls?<?= http_build_query(['from'=>$from,'to'=>$to]) ?>" target="_blank"><i class="bi bi-file-earmark-excel me-1"></i> Exportar XLS</a>
  </div>
  <div class="text-muted mt-2">Período: <?= htmlspecialchars(($from ?: '—')) ?> a <?= htmlspecialchars(($to ?: '—')) ?></div>
</form>

<?php
  $rows = $rows ?? [];
  $sumCents = 0; $sumTx = 0; $labels=[]; $valsRevenue=[]; $valsTx=[];
  foreach ($rows as $r) {
    $sumCents += (int)($r['paid_cents'] ?? 0);
    $sumTx += (int)($r['total_tx'] ?? 0);
    $labels[] = (string)($r['account_name'] ?? ('#'.(int)($r['account_id'] ?? 0)));
    $valsRevenue[] = (int)($r['paid_cents'] ?? 0);
    $valsTx[] = (int)($r['total_tx'] ?? 0);
  }
?>

<div class="grid cols-3 gap-lg mb-4">
  <div class="panel">
    <h3 class="mb-1">Receita (paid)</h3>
    <div class="value" style="font-size:1.6em">R$ <?= number_format($sumCents/100, 2, ',', '.') ?></div>
    <div class="text-muted">Soma de pagamentos pagos no período</div>
  </div>
  <div class="panel">
    <h3 class="mb-1">Transações</h3>
    <div class="value" style="font-size:1.6em"><?= (int)$sumTx ?></div>
    <div class="text-muted">Total de transações</div>
  </div>
  <div class="panel">
    <h3 class="mb-1">Qtd Contas</h3>
    <div class="value" style="font-size:1.6em"><?= count($rows) ?></div>
    <div class="text-muted">Contas com movimentação</div>
  </div>
</div>

<div class="grid cols-2 gap-lg mb-4">
  <div class="panel">
    <div class="flex flex-between mb-3"><h3 style="margin:0">Receita por Conta</h3><div class="text-muted">R$ por conta</div></div>
    <div style="position:relative;height:320px"><canvas id="accRevenue"></canvas></div>
    <div class="mt-2"><button class="btn small secondary" data-export="accRevenue">Baixar PNG</button></div>
  </div>
  <div class="panel">
    <div class="flex flex-between mb-3"><h3 style="margin:0">Transações por Conta</h3><div class="text-muted">QTDE por conta</div></div>
    <div style="position:relative;height:320px"><canvas id="accTx"></canvas></div>
    <div class="mt-2"><button class="btn small secondary" data-export="accTx">Baixar PNG</button></div>
  </div>
</div>

<div class="panel">
  <h3 class="mb-3">Detalhamento</h3>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr><th>ID</th><th>Conta</th><th>Ambiente</th><th>Account UID</th><th>Receita (paid)</th><th>Transações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): $env=strtolower((string)($r['active_env']??'sandbox')); ?>
        <tr>
          <td><?= (int)$r['account_id'] ?></td>
          <td><?= htmlspecialchars((string)$r['account_name']) ?></td>
          <td><span class="badge <?= $env==='production'?'success':'warning' ?>"><?= $env==='production'?'Produção':'Homolog (Sandbox)' ?></span></td>
          <td><?= htmlspecialchars((string)($r['stone_account_uid'] ?? '')) ?></td>
          <td>R$ <?= number_format(((int)$r['paid_cents'])/100, 2, ',', '.') ?></td>
          <td><?= (int)$r['total_tx'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="/assets/chart.js"></script>
<script>
(function(){
  const theme = getComputedStyle(document.documentElement);
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.color = theme.getPropertyValue('--txt-sec').trim();
  Chart.defaults.scale.grid.borderColor = theme.getPropertyValue('--border').trim();
  Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';
  Chart.defaults.devicePixelRatio = 2;

  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const valsRev = <?= json_encode($valsRevenue) ?>;
  const valsTx = <?= json_encode($valsTx) ?>;
  const fmtBRL = new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' });
  const fmtInt = new Intl.NumberFormat('pt-BR');

  function mkChart(id, cfg){
    const el = document.getElementById(id); if (!el) return null;
    const ctx = el.getContext('2d');
    return new Chart(ctx, cfg);
  }
  const cRev = mkChart('accRevenue', {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Receita (paid)', data: valsRev.map(v=>v/100), backgroundColor: '#3b82f6' }] },
    options: { plugins:{ tooltip:{ callbacks:{ label:ctx=> fmtBRL.format(ctx.parsed.y) } } }, scales:{ y:{ ticks:{ callback:v=>fmtBRL.format(v) } } } }
  });
  const cTx = mkChart('accTx', {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Transações', data: valsTx, backgroundColor: '#10b981' }] },
    options: { plugins:{ tooltip:{ callbacks:{ label:ctx=> fmtInt.format(ctx.parsed.y) } } }, scales:{ y:{ ticks:{ callback:v=>fmtInt.format(v) } } } }
  });

  document.addEventListener('click', function(ev){
    var b = ev.target.closest('button[data-export]'); if (!b) return;
    var id = b.getAttribute('data-export'); var cnv = document.getElementById(id); if (!cnv) return;
    var a = document.createElement('a'); a.href = cnv.toDataURL('image/png'); a.download = id + '.png'; a.click();
  });
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
