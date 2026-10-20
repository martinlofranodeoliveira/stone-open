<?php ob_start(); ?>
<h1>Links de Pagamento</h1>
<div class="panel">
  <?php $exportFilter = array_filter($filter, static fn($value) => $value !== '' && $value !== null); ?>
  <form class="filter-bar" method="get" action="<?= $prefix ?>/payment-links">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['site_id']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels = ['open'=>'Aberto','closed'=>'Fechado','paid'=>'Pago','canceled'=>'Cancelado','expired'=>'Expirado','failed'=>'Falhou']; foreach (['open','closed','paid','canceled','expired','failed'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filter['status']??'')===$st?'selected':'' ?>><?= $labels[$st] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="env" class="form-select" style="min-width: 150px;">
      <option value="">Todos ambientes</option>
      <option value="production" <?= ($filter['env']??'')==='production'?'selected':'' ?>>Produção</option>
      <option value="sandbox" <?= ($filter['env']??'')==='sandbox'?'selected':'' ?>>Sandbox</option>
    </select>
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="text" class="form-control" name="q" placeholder="Código / referência" value="<?= htmlspecialchars($filter['q']??'') ?>" style="min-width: 190px;" />
    <input type="text" class="form-control" name="transaction_id" placeholder="Transação / Order ID (or_)" value="<?= htmlspecialchars($filter['transaction_id']??'') ?>" style="min-width: 240px;" />
    <input type="text" class="form-control" name="name" placeholder="Nome" value="<?= htmlspecialchars($filter['name']??'') ?>" style="min-width: 180px;" />
    <input type="text" class="form-control" name="email" placeholder="Email" value="<?= htmlspecialchars($filter['email']??'') ?>" style="min-width: 220px;" />
    <input type="text" class="form-control" name="document" placeholder="CPF/CNPJ sem pontuação" value="<?= htmlspecialchars($filter['document']??'') ?>" style="min-width: 200px;" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/payment-links"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/payment-links/export?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/payment-links/export-xls?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
  <div class="muted" style="margin-top:6px">Para abrir o link, clique no ícone na coluna URL.</div>
  <div class="space"></div>
  <div class="flex" style="justify-content:flex-end;gap:8px;margin-bottom:8px">
    <button type="button" class="btn secondary js-toggle-all">Marcar todos</button>
    <button type="button" class="btn js-resend-batch" data-confirm-local="Reenviar a última notificação dos links selecionados para o seu site? Isso não cria nova cobrança.">
      <i class="bi bi-arrow-repeat"></i> Reenviar selecionados
    </button>
  </div>
  <table id="tblLinks" class="table" data-url="<?= $prefix ?>/payment-links/data">
    <thead>
      <tr><th></th><th>ID</th><th>Site</th><th>Ambiente</th><th>Código</th><th>Nome</th><th>Valor</th><th>Status</th><th>URL</th><th>Expira</th><th>Criado</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($links as $l): ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?= (int)$l['id'] ?>" /></td>
      <td><?= (int)$l['id'] ?></td>
      <td><?= htmlspecialchars($l['site_name']) ?></td>
      <?php $env = (string)($l['env'] ?? 'sandbox'); $envLabel = $env === 'production' ? 'Produção' : 'Sandbox'; $envClass = $env === 'production' ? 'ok' : 'warn'; ?>
      <td><span class="badge <?= $envClass ?>"><?= $envLabel ?></span></td>
      <td class="muted"><?= htmlspecialchars((string)$l['code']) ?></td>
      <td class="muted"><?= htmlspecialchars((string)($l['customer_name'] ?? '-')) ?></td>
      <td>R$ <?= number_format(((int)$l['amount_cents'])/100,2,',','.') ?></td>
      <?php
        $st = (string)($l['status'] ?? '');
        $labMap = ['open'=>'Aberto','closed'=>'Fechado','paid'=>'Pago','canceled'=>'Cancelado','expired'=>'Expirado','failed'=>'Falhou'];
        $clsMap = ['open'=>'info','closed'=>'info','paid'=>'ok','canceled'=>'warn','expired'=>'warn','failed'=>'err'];
        $lab = $labMap[$st] ?? $st; $cls = $clsMap[$st] ?? '';
      ?>
      <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($lab) ?></span></td>
      <td><?php if(!empty($l['checkout_url'])): ?><a target="_blank" href="<?= htmlspecialchars($l['checkout_url']) ?>" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></td>
      <td><?= htmlspecialchars((string)$l['expires_at']) ?></td>
      <td><?= htmlspecialchars((string)$l['created_at']) ?></td>
      <td class="right">
        <form method="post" action="<?= $prefix ?>/payment-links/resend" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>" />
          <input type="hidden" name="payment_link_id" value="<?= (int)$l['id'] ?>" />
          <button type="button" class="btn-icon info js-resend-one" title="Reenviar" data-confirm-local="Reenviar a última notificação deste link para o seu site? Isso não cria nova cobrança."><i class="bi bi-arrow-repeat"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<script>
  (function($){
    var $form = $('form[action$="/payment-links"]');
    var $tbl = $('#tblLinks');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';

    // Overlay de progresso
    var overlay = $('<div id="resendOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;color:#fff;"><div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;min-width:320px;box-shadow:0 10px 30px rgba(0,0,0,.35)"><div style="font-weight:700;margin-bottom:8px">Reenviando notificações…</div><div id="resendProgressText" class="muted" style="margin-bottom:10px">0 / 0</div><div style="height:10px;background:#0f172a;border-radius:6px;overflow:hidden;margin-bottom:12px"><div id="resendProgressBar" style="height:100%;width:0%;background:#6366f1"></div></div><div class="flex" style="justify-content:flex-end;gap:8px"><button id="resendCancel" class="btn secondary">Parar</button><button id="resendClose" class="btn" style="display:none">Fechar</button></div></div></div>');
    $('body').append(overlay);
    var summaryModal = $('<div id="resendSummary" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:10000;align-items:center;justify-content:center;"><div style="background:#0f172a;border:1px solid #334155;border-radius:14px;max-width:880px;width:96%;color:#e2e8f0;box-shadow:0 10px 30px rgba(0,0,0,.45)"><div style="padding:14px 18px;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between"><div style="font-weight:700">Resumo do reenvio</div><button class="btn secondary js-close">Fechar</button></div><div style="padding:14px 18px"><div id="resendSummaryMeta" class="muted" style="margin-bottom:10px"></div><div style="max-height:360px;overflow:auto;border:1px solid #1e293b;border-radius:10px"><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Link</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Status</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">HTTP</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Callback URL</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Erro</th></tr></thead><tbody id="resendSummaryBody"></tbody></table></div></div></div></div>');
    $('body').append(summaryModal);
    var abortAll=false;
    function showOverlay(total){ abortAll=false; $('#resendProgressBar').css('width','0%'); $('#resendProgressText').text('0 / '+total); $('#resendClose').hide(); $('#resendCancel').show(); overlay.css('display','flex'); }
    function hideOverlay(){ overlay.hide(); }
    function updateProgress(done,total){ var pct = total? Math.round(done*100/total):0; $('#resendProgressBar').css('width', pct+'%'); $('#resendProgressText').text(done+' / '+total+' ('+pct+'%)'); }
    $('#resendCancel').on('click', function(){ abortAll=true; $(this).prop('disabled', true).text('Parando…'); });
    $('#resendClose').on('click', function(){ hideOverlay(); });
    summaryModal.on('click', '.js-close', function(){ summaryModal.hide(); });

    async function resendOne(id, token){
      try{
        let resp = await fetch('<?= $prefix ?>/payment-links/resend?ajax=1', {
          method:'POST',
          credentials:'same-origin',
          headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token': token || ''},
          body: new URLSearchParams({ csrf: token || '', payment_link_id: String(id) })
        });
        if(!resp.ok) return {failed:1};
        let json={}; try{ json = await resp.json(); }catch(e){ return {failed:1}; }
        return json;
      }catch(e){ return {failed:1}; }
    }
    function showSummaryModal(results, ok, fail, skip){
      $('#resendSummaryMeta').text('Sucesso: '+ok+' · Falhas: '+fail+' · Ignorados: '+skip);
      var rows = '';
      results.forEach(function(r){
        var st = r.status || 'desconhecido';
        var cls = (st==='enviado') ? 'color:#22c55e' : (st==='falhou' ? 'color:#ef4444' : (st==='ignorado' ? 'color:#f59e0b' : ''));
        var http = (r.http_status!==null && r.http_status!==undefined && r.http_status!=='') ? String(r.http_status) : '-';
        var url = r.target_url ? String(r.target_url) : '-';
        var err = r.error ? String(r.error) : '';
        rows += '<tr>'
          +'<td style="padding:8px 10px;border-bottom:1px solid #1e293b">'+r.id+'</td>'
          +'<td style="padding:8px 10px;border-bottom:1px solid #1e293b"><span style="'+cls+'">'+st+'</span></td>'
          +'<td style="padding:8px 10px;border-bottom:1px solid #1e293b">'+http+'</td>'
          +'<td style="padding:8px 10px;border-bottom:1px solid #1e293b;max-width:320px;word-break:break-all">'+url+'</td>'
          +'<td style="padding:8px 10px;border-bottom:1px solid #1e293b;max-width:320px;word-break:break-word">'+err+'</td>'
          +'</tr>';
      });
      $('#resendSummaryBody').html(rows || '<tr><td colspan="5" style="padding:10px" class="muted">Nenhum item processado</td></tr>');
      summaryModal.css('display','flex');
    }
    async function processIds(ids, token){
      if(!ids || !ids.length){ if(window.Swal) Swal.fire({icon:'info', text:'Selecione pelo menos um link.'}); return; }
      showOverlay(ids.length);
      let done=0, ok=0, fail=0, skip=0;
      let results = [];
      for(const id of ids){
        if(abortAll) break;
        let r = await resendOne(id, token);
        var log = (r && r.log) ? r.log : null;
        var httpStatus = log ? log.response_status : null;
        var targetUrl = log ? log.target_url : '';
        var err = log ? (log.error || '') : (r && (r.error || r.message) ? (r.error || r.message) : '');
        let status = (r && r.resent) ? 'enviado' : ((r && r.failed) ? 'falhou' : ((r && r.skipped) ? 'ignorado' : 'desconhecido'));
        results.push({id:id, status:status, http_status:httpStatus, target_url:targetUrl, error:err});
        ok+=(r.resent||0); fail+=(r.failed||0); skip+=(r.skipped||0);
        done++; updateProgress(done, ids.length);
        await new Promise(res=>setTimeout(res,50));
      }
      $('#resendCancel').hide(); $('#resendClose').show();
      hideOverlay();
      if (ids.length === 1) {
        var s = results[0] ? results[0].status : 'desconhecido';
        if (window.Swal) {
          var icon = (s==='enviado') ? 'success' : (s==='ignorado' ? 'warning' : 'error');
          Swal.fire({icon: icon, title:'Reenvio concluído', text: (s==='enviado' ? 'Link reenviado com sucesso.' : (s==='ignorado' ? 'Nada para reenviar.' : 'Falha ao reenviar o link.'))});
        }
      } else {
        showSummaryModal(results, ok, fail, skip);
      }
    }
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax: {
        url: $tbl.data('url'),
        data: function(d){
          d.site_id = $form.find('[name=site_id]').val();
          d.status = $form.find('[name=status]').val();
          d.env = $form.find('[name=env]').val();
          d.from = $form.find('[name=from]').val();
          d.to = $form.find('[name=to]').val();
          d.q = $form.find('[name=q]').val();
          d.transaction_id = $form.find('[name=transaction_id]').val();
          d.name = $form.find('[name=name]').val();
          d.email = $form.find('[name=email]').val();
          d.document = $form.find('[name=document]').val();
        }
      },
      order:[[1,'desc']],
      columns:[
        {data:null, orderable:false, render:function(row){ return '<input type="checkbox" name="ids[]" value="'+row.id+'" />'; }},
        {data:'id'},{data:'site_name'},{data:'env', render:function(v){ var env=String(v||'sandbox'); var label= env==='production' ? 'Produção' : 'Sandbox'; var cls= env==='production' ? 'ok' : 'warn'; return '<span class="badge '+cls+'">'+label+'</span>'; }},
        {data:'code'},{data:'customer_name', render:function(v){ return v ? v : '-'; }},
        {data:'amount_cents', render:function(v){return 'R$ ' + (v/100).toFixed(2).replace('.',',');}},
        {data:'status', render:function(v){ var mapLab={'open':'Aberto','closed':'Fechado','paid':'Pago','canceled':'Cancelado','expired':'Expirado','failed':'Falhou'}; var mapCls={'open':'info','closed':'info','paid':'ok','canceled':'warn','expired':'warn','failed':'err'}; var lab=mapLab[v]||v; var cls=mapCls[v]||''; return '<span class="badge '+cls+'">'+lab+'</span>'; }},
        {data:'checkout_url', orderable:false, render:function(v){ return v ? '<a target="_blank" href="'+v+'" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>' : '' }},
        {data:'expires_at'},{data:'created_at'},
        {data:null, orderable:false, render:function(row){ var id=row.id; var html='<form method="post" action=\"<?= $prefix ?>/payment-links/resend\" style=\"display:inline\">' + '<input type=\"hidden\" name=\"csrf\" value=\"'+csrfToken+'\" />' + '<input type=\"hidden\" name=\"payment_link_id\" value=\"'+id+'\" />' + '<button type=\"button\" class=\"btn-icon info js-resend-one\" title=\"Reenviar\" data-confirm-local=\"Reenviar a última notificação deste link para o seu site? Isso não cria nova cobrança.\"><i class=\"bi bi-arrow-repeat\"></i></button>' + '</form>'; return html; }}
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });

    function updateToggleAll(){
      var $btn = $('.js-toggle-all');
      var $checks = $('#tblLinks').find('input[name="ids[]"]');
      if (!$checks.length) { $btn.text('Marcar todos'); return; }
      var allChecked = $checks.length === $checks.filter(':checked').length;
      $btn.text(allChecked ? 'Desmarcar todos' : 'Marcar todos');
    }
    $(document).on('change', '#tblLinks input[name="ids[]"]', updateToggleAll);
    $(document).on('click', '.js-toggle-all', function(e){
      e.preventDefault();
      var $checks = $('#tblLinks').find('input[name="ids[]"]');
      if (!$checks.length) return;
      var allChecked = $checks.length === $checks.filter(':checked').length;
      $checks.prop('checked', !allChecked);
      updateToggleAll();
    });
    if ($tbl.data('DataTable')) { dt.on('draw', updateToggleAll); }

    $(document).on('click', '.js-resend-one', function(e){
      e.preventDefault(); e.stopPropagation();
      var $form = $(this).closest('form');
      var id = $form.find('input[name=payment_link_id]').val();
      var token = $form.find('input[name=csrf]').val() || csrfToken;
      var msg = $(this).attr('data-confirm-local') || 'Reenviar a última notificação deste link para o seu site? Isso não cria nova cobrança.';
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) processIds([id], token); });
      } else {
        if (confirm(msg)) processIds([id], token);
      }
    });
    $(document).on('click', '.js-resend-batch', function(e){
      e.preventDefault(); e.stopPropagation();
      var ids=[]; $('#tblLinks').find('input[name="ids[]"]:checked').each(function(){ ids.push($(this).val()); });
      var token = csrfToken;
      var msg = $(this).attr('data-confirm-local') || 'Reenviar a última notificação dos links selecionados para o seu site? Isso não cria nova cobrança.';
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) processIds(ids, token); });
      } else {
        if (confirm(msg)) processIds(ids, token);
      }
    });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
