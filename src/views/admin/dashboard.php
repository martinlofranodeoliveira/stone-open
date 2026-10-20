<?php ob_start(); ?>
<div class="mb-4 flex flex-between items-end">
  <div>
    <h1 class="mb-1">Olá, <?= htmlspecialchars($user) ?></h1>
    <p class="text-muted">Aqui está o resumo da sua plataforma hoje.</p>
  </div>
  <div class="flex gap-sm">
    <a href="<?= $prefix ?>/sites/create" class="btn secondary"><i class="bi bi-plus-lg"></i> Novo Site</a>
  </div>
</div>

<div class="grid cols-4 gap-lg mb-4">
  <a class="panel stat-card d-block" href="<?= $prefix ?>/sites">
    <div class="icon-box bg-primary-subtle"><i class="bi bi-hdd-network"></i></div>
    <div><div class="value"><?= (int)$counts['sites'] ?></div><div class="label">Sites Ativos</div></div>
  </a>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/customers">
    <div class="icon-box bg-success-subtle"><i class="bi bi-people-fill"></i></div>
    <div><div class="value"><?= (int)$counts['customers'] ?></div><div class="label">Clientes</div></div>
  </a>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/payments">
    <div class="icon-box bg-warning-subtle"><i class="bi bi-credit-card-fill"></i></div>
    <div><div class="value"><?= (int)$counts['payments'] ?></div><div class="label">Pagamentos</div></div>
  </a>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/webhooks">
    <div class="icon-box bg-danger-subtle"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div><div class="value"><?= (int)$counts['webhooks_pending'] ?></div><div class="label">Webhooks Pendentes</div></div>
  </a>
</div>

<div class="grid cols-3 gap-lg mb-4">
  <?php $appr = (int)($paid30 ?? 0); $aptr = (int)($total30 ?? 0); $apprPct = $aptr > 0 ? round(($appr / $aptr) * 100, 1) : 0; ?>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/payments?status=paid" title="Ir para Pagamentos (filtrados: pagos)">
    <div class="icon-box bg-success-subtle"><i class="bi bi-check2-circle"></i></div>
    <div>
      <div class="value"><?= number_format($apprPct, 1, ',', '.') ?>%</div>
      <div class="label">Aprovação (30d)</div>
      <div class="text-muted" style="font-size:0.8em; margin-top:4px;"><?= (int)($paid30 ?? 0) ?>/<?= (int)($total30 ?? 0) ?> aprovados</div>
    </div>
  </a>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/payments?status=refunded" title="Ir para Pagamentos (filtrados: reembolsados)">
    <div class="icon-box bg-warning-subtle"><i class="bi bi-arrow-counterclockwise"></i></div>
    <div style="flex:1;">
      <div class="value"><?= (int)($refunds30['c'] ?? 0) ?></div>
      <div class="label">Reembolsos (30d)</div>
      <div class="text-muted" style="font-size:0.8em; margin-top:4px;">R$ <?= number_format(((int)($refunds30['v'] ?? 0))/100, 2, ',', '.') ?></div>
      <div style="position:relative;height:46px;margin-top:6px;"><canvas id="sparkRefunds"></canvas></div>
    </div>
  </a>
  <a class="panel stat-card d-block" href="<?= $prefix ?>/payments?status=chargeback" title="Ir para Pagamentos (filtrados: chargeback)">
    <div class="icon-box bg-danger-subtle"><i class="bi bi-arrow-repeat"></i></div>
    <div style="flex:1;">
      <div class="value"><?= (int)($chargebacks30['c'] ?? 0) ?></div>
      <div class="label">Chargebacks (30d)</div>
      <div class="text-muted" style="font-size:0.8em; margin-top:4px;">R$ <?= number_format(((int)($chargebacks30['v'] ?? 0))/100, 2, ',', '.') ?></div>
      <div style="position:relative;height:46px;margin-top:6px;"><canvas id="sparkChargebacks"></canvas></div>
    </div>
  </a>
</div>

<div class="grid cols-3 gap-lg">
  <div class="panel col-span-2">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Receita, Transações & Ticket Médio</h3>
      <div class="text-muted" style="font-size:0.9em">Últimos 14 dias</div>
    </div>
    <div style="position:relative;height:320px"><canvas id="chartRevenue"></canvas></div>
  </div>
  <div class="panel">
    <h3 class="mb-4">Métodos de Pagamento</h3>
    <div style="position:relative;height:280px"><canvas id="chartMethods"></canvas></div>
    <div class="space"></div>
    <h3 class="mb-4">Status Recentes</h3>
    <div style="position:relative;height:200px"><canvas id="chartStatus"></canvas></div>
  </div>
</div>

<div class="grid cols-2 gap-lg mb-4">
  <div class="panel">
    <div class="flex flex-between mb-3">
      <h3 style="margin:0">Saúde por Site</h3>
      <div class="text-muted" style="font-size:0.85em">Últimas <?= (int)($healthWindowHours ?? 24) ?>h</div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Site</th>
            <th>Rate/Min</th>
            <th>Pendente</th>
            <th>Processando</th>
            <th>Falhas</th>
            <th>Avg Proc</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($healthBySite ?? []) as $r): ?>
            <?php
              $limit = (int)($r['rate_limit_per_minute'] ?? 0);
              $used = (int)($r['rate_used_minute'] ?? 0);
              $pct = $limit > 0 ? (int)round(($used / max(1, $limit)) * 100) : null;
              $rateCls = $pct === null ? '' : ($pct >= 90 ? 'err' : ($pct >= 70 ? 'warn' : 'ok'));
              $pending = (int)($r['webhooks_pending'] ?? 0);
              $processing = (int)($r['webhooks_processing'] ?? 0);
              $failed = (int)($r['webhooks_failed_24h'] ?? 0);
              $avg = $r['avg_processing_sec'] !== null ? number_format((float)$r['avg_processing_sec'], 1, ',', '.') . 's' : '-';
            ?>
            <tr>
              <td><?= htmlspecialchars((string)($r['name'] ?? ''), ENT_QUOTES) ?></td>
              <td>
                <?php if ($limit > 0): ?>
                  <span class="badge <?= $rateCls ?>"><?= $used ?>/<?= $limit ?> (<?= (int)$pct ?>%)</span>
                <?php else: ?>
                  <span class="badge"><?= $used ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $pending > 0 ? 'warn' : 'ok' ?>"><?= $pending ?></span></td>
              <td><span class="badge <?= $processing > 0 ? 'warn' : 'ok' ?>"><?= $processing ?></span></td>
              <td><span class="badge <?= $failed > 0 ? 'err' : 'ok' ?>"><?= $failed ?></span></td>
              <td><?= $avg ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($healthBySite ?? [])): ?>
            <tr><td colspan="6" class="text-muted">Sem dados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <div class="flex flex-between mb-3">
      <h3 style="margin:0">Falha por Tipo</h3>
      <div class="text-muted" style="font-size:0.85em">Últimos <?= (int)($failByTypeDays ?? 7) ?> dias</div>
    </div>
    <table class="table">
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Falhas</th>
          <th>Total</th>
          <th>Taxa</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($failByType ?? []) as $r): ?>
          <?php
            $total = (int)($r['total'] ?? 0);
            $failed = (int)($r['failed'] ?? 0);
            $rate = $total > 0 ? ($failed / $total) * 100 : 0;
            $rateCls = $rate >= 20 ? 'err' : ($rate >= 5 ? 'warn' : 'ok');
            $label = trim((string)($r['event_type'] ?? ''));
          ?>
          <tr>
            <td><?= htmlspecialchars($label !== '' ? $label : 'unknown', ENT_QUOTES) ?></td>
            <td><span class="badge <?= $failed > 0 ? $rateCls : 'ok' ?>"><?= $failed ?></span></td>
            <td><?= $total ?></td>
            <td><span class="badge <?= $rateCls ?>"><?= number_format($rate, 1, ',', '.') ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($failByType ?? [])): ?>
          <tr><td colspan="4" class="text-muted">Sem dados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid cols-3 gap-lg">
  <div class="panel">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Assinaturas: Ativas x Atrasadas</h3>
      <div class="text-muted" style="font-size:0.9em">MRR estimado: R$ <?= number_format((int)($mrrCents ?? 0)/100, 2, ',', '.') ?></div>
    </div>
    <div style="position:relative;height:220px"><canvas id="chartSubsAp"></canvas></div>
  </div>
  <div class="panel">
    <h3 class="mb-4">Status de Assinaturas</h3>
    <div style="position:relative;height:240px"><canvas id="chartSubsStatus"></canvas></div>
  </div>
  <div class="panel">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Novas Assinaturas</h3>
      <div class="text-muted" style="font-size:0.9em">Últimos 14 dias</div>
    </div>
    <div style="position:relative;height:220px"><canvas id="chartSubsNew"></canvas></div>
  </div>
</div>

<div class="grid cols-3 gap-lg">
  <div class="panel col-span-2">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Taxa de Aprovação</h3>
      <div class="text-muted" style="font-size:0.9em">Últimos 14 dias</div>
    </div>
    <div style="position:relative;height:220px"><canvas id="chartApproval"></canvas></div>
  </div>
  <div class="panel">
    <h3 class="mb-4">Top 5 Sites por Receita (30d)</h3>
    <div style="position:relative;height:240px"><canvas id="chartTopSites"></canvas></div>
  </div>
  <div class="panel">
    <h3 class="mb-4">Top Incidentes (30d)</h3>
    <div style="position:relative;height:240px"><canvas id="chartIncidents"></canvas></div>
  </div>
  
  <div class="panel">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Assinaturas: Ativas x Atrasadas</h3>
      <div class="text-muted" style="font-size:0.9em">MRR estimado: R$ <?= number_format((int)($mrrCents ?? 0)/100, 2, ',', '.') ?></div>
    </div>
    <div style="position:relative;height:220px"><canvas id="chartSubsAp"></canvas></div>
  </div>
  <div class="panel">
    <h3 class="mb-4">Status de Assinaturas</h3>
    <div style="position:relative;height:240px"><canvas id="chartSubsStatus"></canvas></div>
  </div>
  <div class="panel">
    <div class="flex flex-between mb-4">
      <h3 style="margin:0">Novas Assinaturas</h3>
      <div class="text-muted" style="font-size:0.9em">Últimos 14 dias</div>
    </div>
    <div style="position:relative;height:220px"><canvas id="chartSubsNew"></canvas></div>
  </div>
</div>

<script src="/assets/chart.js"></script>
<script>
  (function(){
    // Dados do PHP
    const days = <?= json_encode(array_map(fn($r)=>$r['d'],$seriesDays??[])) ?>;
    const counts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$seriesDays??[])) ?>;
    const revDays = <?= json_encode(array_map(fn($r)=>$r['d'],$seriesRevenue??[])) ?>;
    const revVals = <?= json_encode(array_map(fn($r)=>(int)$r['v']/100,$seriesRevenue??[])) ?>;
    
    const byMethodLabels = <?= json_encode(array_map(fn($r)=>$r['m']?:'unknown',$byMethod??[])) ?>;
    const byMethodCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$byMethod??[])) ?>;
    
    const byStatusLabels = <?= json_encode(array_map(fn($r)=>$r['s']?:'unknown',$byStatus??[])) ?>;
    const byStatusCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$byStatus??[])) ?>;

    // Aprovação diária
    const apprDaysPaid = <?= json_encode(array_map(fn($r)=>$r['d'],$paidByDay??[])) ?>;
    const apprPaidCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$paidByDay??[])) ?>;
    const apprDaysTotal = <?= json_encode(array_map(fn($r)=>$r['d'],$totalByDay??[])) ?>;
    const apprTotalCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$totalByDay??[])) ?>;

    // Top sites receita (BRL)
    const topSiteNames = <?= json_encode(array_map(fn($r)=>$r['n']??'N/A',$topSites??[])) ?>;
    const topSiteValues = <?= json_encode(array_map(fn($r)=>(int)$r['v']/100,$topSites??[])) ?>;

    // Sparkline dados (14d) para Reembolsos e Chargebacks
    const sparkRefDays = <?= json_encode(array_map(fn($r)=>$r['d'],$refundsSeries??[])) ?>;
    const sparkRefVals = <?= json_encode(array_map(fn($r)=>(int)$r['v']/100,$refundsSeries??[])) ?>;
    const sparkCbkDays = <?= json_encode(array_map(fn($r)=>$r['d'],$chargebacksSeries??[])) ?>;
    const sparkCbkVals = <?= json_encode(array_map(fn($r)=>(int)$r['v']/100,$chargebacksSeries??[])) ?>;

    // Top Incidentes (status) 30d
    const incLabels = <?= json_encode(array_map(fn($r)=>$r['s']??'unknown',$incidentsTop??[])) ?>;
    const incCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$incidentsTop??[])) ?>;

    // Assinaturas (dados)
    const subsActive = <?= (int)($subsActive ?? 0) ?>;
    const subsPastDue = <?= (int)($subsPastDue ?? 0) ?>;
    const subsStatusLabels = <?= json_encode(array_map(fn($r)=>$r['s']??'unknown',$subsByStatus??[])) ?>;
    const subsStatusCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$subsByStatus??[])) ?>;
    const subsNewDays = <?= json_encode(array_map(fn($r)=>$r['d'],$subsNewByDay??[])) ?>;
    const subsNewCounts = <?= json_encode(array_map(fn($r)=>(int)$r['c'],$subsNewByDay??[])) ?>;

    // Traduções para legenda/labels
    const methodMap = {
      'credit_card': 'Cartão de Crédito',
      'debit_card': 'Cartão de Débito',
      'boleto': 'Boleto',
      'pix': 'Pix',
      'voucher': 'Voucher',
      'cash': 'Dinheiro',
      'transfer': 'Transferência',
      'bank_transfer': 'Transferência',
      'unknown': 'Desconhecido'
    };
    const statusMap = {
      'paid': 'Pago',
      'pending': 'Pendente',
      'processing': 'Processando',
      'authorized': 'Autorizado',
      'authorized_pending_capture': 'Autorizado (Pendente de Captura)',
      'canceled': 'Cancelado',
      'cancelled': 'Cancelado',
      'failed': 'Falhou',
      'refused': 'Recusado',
      'refunded': 'Reembolsado',
      'partially_refunded': 'Reembolsado (Parcial)',
      'chargeback': 'Chargeback',
      'chargedback': 'Chargeback',
      'created': 'Criado',
      'on_hold': 'Em espera',
      'settled': 'Liquidado',
      'unknown': 'Desconhecido'
    };
    const byMethodLabelsPt = (byMethodLabels || []).map(m => methodMap[(m||'').toString().toLowerCase()] || m || 'Desconhecido');
    const byStatusLabelsPt = (byStatusLabels || []).map(s => statusMap[(s||'').toString().toLowerCase()] || s || 'Desconhecido');

    // Configurações comuns
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--txt-sec').trim();
    Chart.defaults.scale.grid.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border').trim();
    Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';

    // Formatadores pt-BR
    const fmtInt = new Intl.NumberFormat('pt-BR');
    const fmtCur = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const fmtDateTitle = (l) => {
      if (!l) return '';
      if (/^\d{4}-\d{2}-\d{2}$/.test(l)) { const [y,m,d] = l.split('-'); return `${d}/${m}/${y}`; }
      return l;
    };

    // Alinhar séries (dias) para receita, transações e ticket médio
    const cntMap = {}; days.forEach((d,i)=>{ cntMap[d]=counts[i]||0; });
    const revMap = {}; revDays.forEach((d,i)=>{ revMap[d]=revVals[i]||0; });
    const labelsAll = Array.from(new Set([...Object.keys(cntMap), ...Object.keys(revMap)])).sort();
    const countsAligned = labelsAll.map(d => cntMap[d] || 0);
    const revAligned = labelsAll.map(d => revMap[d] || 0);
    const avgAligned = labelsAll.map((d, i) => (countsAligned[i] ? (revAligned[i] / countsAligned[i]) : 0));

    const ctxRev = document.getElementById('chartRevenue').getContext('2d');
    new Chart(ctxRev, {
      type: 'line',
      data: {
        labels: labelsAll,
        datasets: [
          {
            label: 'Receita (R$)',
            data: revAligned,
            borderColor: '#10b981', // Emerald
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            yAxisID: 'y'
          },
          {
            label: 'Transações',
            data: countsAligned,
            type: 'bar',
            backgroundColor: '#6366f1', // Indigo
            borderRadius: 4,
            yAxisID: 'y1'
          },
          {
            label: 'Ticket Médio (R$)',
            data: avgAligned,
            borderColor: '#f59e0b', // Amber
            backgroundColor: 'rgba(245, 158, 11, 0.15)',
            borderDash: [6,4],
            borderWidth: 2,
            tension: 0.4,
            fill: false,
            yAxisID: 'y'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            mode: 'index', intersect: false,
            padding: 10, cornerRadius: 8,
            backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--panel').trim(),
            titleColor: getComputedStyle(document.documentElement).getPropertyValue('--txt').trim(),
            bodyColor: getComputedStyle(document.documentElement).getPropertyValue('--txt-sec').trim(),
            borderColor: getComputedStyle(document.documentElement).getPropertyValue('--border').trim(),
            borderWidth: 1,
            callbacks: {
              title: function(items){ if (!items || !items.length) return ''; return fmtDateTitle(items[0].label); },
              label: function(ctx){
                const label = ctx.dataset.label || '';
                const v = typeof ctx.parsed?.y === 'number' ? ctx.parsed.y : 0;
                // Decide pelo eixo
                if (ctx.dataset.yAxisID === 'y') {
                  return label + ': ' + fmtCur.format(v);
                }
                return label + ': ' + fmtInt.format(v);
              }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            type: 'linear', display: true, position: 'left',
            grid: { color: getComputedStyle(document.documentElement).getPropertyValue('--border').trim() },
            ticks: {
              callback: (v) => fmtCur.format(v)
            }
          },
          y1: {
            type: 'linear', display: true, position: 'right',
            grid: { drawOnChartArea: false },
            ticks: {
              callback: (v) => fmtInt.format(v)
            }
          }
        }
      }
    });

    // Taxa de aprovação (paid/total %)
    const paidMap = {}; apprDaysPaid.forEach((d,i)=>{ paidMap[d] = apprPaidCounts[i]||0; });
    const totalMap = {}; apprDaysTotal.forEach((d,i)=>{ totalMap[d] = apprTotalCounts[i]||0; });
    const apprLabels = Array.from(new Set([...Object.keys(paidMap), ...Object.keys(totalMap)])).sort();
    const apprRate = apprLabels.map(d => {
      const tot = totalMap[d]||0; const ok = paidMap[d]||0; return tot ? (ok/tot*100) : 0;
    });
    const ctxApproval = document.getElementById('chartApproval').getContext('2d');
    new Chart(ctxApproval, {
      type: 'line',
      data: { labels: apprLabels, datasets: [{ label: 'Taxa de Aprovação (%)', data: apprRate, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.15)', borderWidth: 2, tension: 0.3, fill: true }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { title: (items)=> items&&items[0]? fmtDateTitle(items[0].label):'', label: (ctx)=> 'Taxa: ' + (typeof ctx.parsed?.y==='number' ? ctx.parsed.y.toFixed(1) : '0.0') + '%' } } },
        scales: { x: { grid: { display: false } }, y: { ticks: { callback: v => (typeof v==='number'? v.toFixed(0):v) + '%' } } }
      }
    });

    // Top sites por receita (30d)
    const ctxTopSites = document.getElementById('chartTopSites').getContext('2d');
    new Chart(ctxTopSites, {
      type: 'bar',
      data: { labels: topSiteNames, datasets: [{ label: 'Receita (R$)', data: topSiteValues, backgroundColor: '#10b981', borderRadius: 4 }] },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx)=> 'Receita: ' + fmtCur.format(typeof ctx.parsed?.x==='number'?ctx.parsed.x:0) } } },
        scales: { x: { ticks: { callback: (v)=> fmtCur.format(v) } }, y: { grid: { display: false } } }
      }
    });

    // Sparklines (Reembolsos & Chargebacks)
    function sparkline(elId, labels, values, color){
      const ctx = document.getElementById(elId).getContext('2d');
      return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ data: values, borderColor: color, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3, pointRadius: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { x: { display:false }, y: { display:false } }, plugins: { legend: { display:false }, tooltip: { callbacks: { title: (items)=> items&&items[0]? fmtDateTitle(items[0].label):'', label: (ctx)=> fmtCur.format(typeof ctx.parsed?.y==='number'?ctx.parsed.y:0) } } } }
      });
    }
    sparkline('sparkRefunds', sparkRefDays, sparkRefVals, '#f59e0b');
    sparkline('sparkChargebacks', sparkCbkDays, sparkCbkVals, '#ef4444');

    // Top Incidentes (30d) - traduz status
    const incLabelsPt = (incLabels||[]).map(s => ({
      'failed':'Falhou','canceled':'Cancelado','refunded':'Reembolsado','chargeback':'Chargeback','processing':'Processando','authorized':'Autorizado','created':'Criado','queued':'Na fila'
    }[(s||'').toLowerCase()] || s || 'Desconhecido'));
    const ctxInc = document.getElementById('chartIncidents').getContext('2d');
    new Chart(ctxInc, {
      type: 'bar',
      data: { labels: incLabelsPt, datasets: [{ label: 'Ocorrências', data: incCounts, backgroundColor: '#ef4444', borderRadius: 4 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display:false }, tooltip: { callbacks: { label: (ctx)=> 'Ocorrências: ' + fmtInt.format(typeof ctx.parsed?.x==='number'?ctx.parsed.x:0) } } }, scales: { x: { ticks: { callback: (v)=> fmtInt.format(v) } }, y: { grid: { display:false } } } }
    });

    const ctxMethod = document.getElementById('chartMethods').getContext('2d');
    new Chart(ctxMethod, {
      type: 'doughnut',
      data: {
        labels: byMethodLabelsPt,
        datasets: [{
          data: byMethodCounts,
          backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: function(ctx){
                const label = ctx.label || '';
                const v = typeof ctx.parsed === 'number' ? ctx.parsed : 0;
                const dataArr = (ctx.dataset && Array.isArray(ctx.dataset.data)) ? ctx.dataset.data : [];
                const total = dataArr.reduce((s, n) => s + (typeof n === 'number' ? n : 0), 0);
                const perc = total ? ((v / total) * 100).toFixed(1) : '0.0';
                return label + ': ' + fmtInt.format(v) + ' (' + perc + '%)';
              }
            }
          }
        }
      }
    });

    const ctxStatus = document.getElementById('chartStatus').getContext('2d');
    new Chart(ctxStatus, {
      type: 'bar',
      data: {
        labels: byStatusLabelsPt,
        datasets: [{
          label: 'Pagamentos',
          data: byStatusCounts,
          backgroundColor: '#3b82f6',
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx){
                const label = ctx.dataset?.label || 'Pagamentos';
                const v = typeof ctx.parsed?.x === 'number' ? ctx.parsed.x : (typeof ctx.parsed === 'number' ? ctx.parsed : 0);
                return label + ': ' + fmtInt.format(v);
              }
            }
          }
        },
        scales: { x: { display: false }, y: { grid: { display: false } } }
      }
    });

    // Assinaturas: Ativas x Atrasadas
    const ctxSubsAp = document.getElementById('chartSubsAp').getContext('2d');
    new Chart(ctxSubsAp, {
      type: 'doughnut',
      data: {
        labels: ['Ativas', 'Atrasadas'],
        datasets: [{ data: [subsActive, subsPastDue], backgroundColor: ['#10b981', '#f59e0b'], borderWidth: 0 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { position: 'right' },
          tooltip: { callbacks: { label: (ctx) => (ctx.label||'') + ': ' + fmtInt.format((typeof ctx.parsed==='number'?ctx.parsed:0)) } }
        }
      }
    });

    // Assinaturas: por status
    const subsStatusLabelsPt = (subsStatusLabels||[]).map(s => ({
      'active':'Ativa','trialing':'Em teste','past_due':'Atrasada','paused':'Pausada','canceled':'Cancelada','expired':'Expirada','deleted':'Excluída'
    }[(s||'').toLowerCase()] || s || 'Desconhecido'));
    const ctxSubsStatus = document.getElementById('chartSubsStatus').getContext('2d');
    new Chart(ctxSubsStatus, {
      type: 'bar',
      data: { labels: subsStatusLabelsPt, datasets: [{ label: 'Assinaturas', data: subsStatusCounts, backgroundColor: '#6366f1', borderRadius: 4 }] },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx)=> 'Assinaturas: ' + fmtInt.format(typeof ctx.parsed?.x==='number'?ctx.parsed.x:0) } } },
        scales: { x: { display: false }, y: { grid: { display: false } } }
      }
    });

    // Novas Assinaturas (14 dias)
    const ctxSubsNew = document.getElementById('chartSubsNew').getContext('2d');
    new Chart(ctxSubsNew, {
      type: 'line',
      data: { labels: subsNewDays, datasets: [{ label: 'Novas', data: subsNewCounts, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.15)', borderWidth: 2, tension: 0.3, fill: true }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { title: (items)=> items&&items[0]? fmtDateTitle(items[0].label):'', label: (ctx)=> 'Novas: ' + fmtInt.format(typeof ctx.parsed?.y==='number'?ctx.parsed.y:0) } } },
        scales: { x: { grid: { display: false } }, y: { ticks: { callback: (v)=> fmtInt.format(v) } } }
      }
    });
  })();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
