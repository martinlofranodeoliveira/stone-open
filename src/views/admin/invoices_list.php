<?php use App\Helpers\Helper;

ob_start(); ?>
<h1>Faturas</h1>
<div class="panel">
  <?php $exportFilter = array_filter($filter, static fn($value) => $value !== '' && $value !== null); ?>
  <div class="muted" style="margin-bottom:10px">
    <b>Reenviar</b>: reentrega o último evento (webhook) ao seu site. Não cobra nada no provedor.<br/>
  </div>
  <form class="filter-bar" method="get" action="<?= $prefix ?>/invoices">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['site_id']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels=['created'=>'Criada','processing'=>'Processando','paid'=>'Paga','failed'=>'Falhou','canceled'=>'Cancelada','refunded'=>'Estornada','chargeback'=>'Chargeback']; foreach (['created','processing','paid','failed','canceled','refunded','chargeback'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filter['status']??'')===$st?'selected':'' ?>><?= $labels[$st] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="form-select" style="min-width: 150px;">
      <option value="">Todos tipos</option>
      <?php $labType=['credit_card'=>'Cartão de crédito','debit_card'=>'Cartão de débito','pix'=>'Pix','boleto'=>'Boleto']; foreach (['credit_card','debit_card','pix','boleto'] as $tp): ?>
        <option value="<?= $tp ?>" <?= ($filter['type']??'')===$tp?'selected':'' ?>><?= $labType[$tp] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="env" class="form-select" style="min-width: 150px;">
      <option value="">Todos ambientes</option>
      <option value="production" <?= ($filter['env']??'')==='production'?'selected':'' ?>>Produção</option>
      <option value="sandbox" <?= ($filter['env']??'')==='sandbox'?'selected':'' ?>>Sandbox</option>
    </select>
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="text" class="form-control" name="q" placeholder="Referência do cliente" value="<?= htmlspecialchars($filter['q']??'') ?>" style="min-width: 190px;" />
    <input type="text" class="form-control" name="transaction_id" placeholder="Transação / Stone ID (or_, in_, ch_)" value="<?= htmlspecialchars($filter['transaction_id']??'') ?>" style="min-width: 250px;" />
    <input type="text" class="form-control" name="name" placeholder="Nome" value="<?= htmlspecialchars($filter['name']??'') ?>" style="min-width: 180px;" />
    <input type="text" class="form-control" name="email" placeholder="Email" value="<?= htmlspecialchars($filter['email']??'') ?>" style="min-width: 220px;" />
    <input type="text" class="form-control" name="document" placeholder="CPF/CNPJ sem pontuação" value="<?= htmlspecialchars($filter['document']??'') ?>" style="min-width: 200px;" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/invoices"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/invoices/export?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/invoices/export-xls?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>
<div class="panel">
  <form method="post" action="<?= $prefix ?>/payments/resend-batch">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>" />
    <div class="flex" style="justify-content:flex-end;margin-bottom:8px;gap:8px">
      <button type="button" class="btn secondary js-toggle-all">Marcar todos</button>
      <button type="button" class="btn js-resend-batch" data-confirm-local="Reenviar a última notificação das faturas selecionadas para o seu site? Isso não cria nova cobrança.">
        <i class="bi bi-arrow-repeat"></i> Reenviar selecionados
      </button>
    </div>
  <table id="tblInvoices" class="table" data-url="<?= $prefix ?>/invoices/data">
    <thead>
      <tr><th></th><th>ID</th><th>Site</th><th>Ambiente</th><th>Ref</th><th>Cliente</th><th>Valor</th><th>Tipo</th><th>Status</th><th>Criado</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($payments as $p): ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" /></td>
      <td><?= (int)$p['id'] ?></td>
      <td><?= htmlspecialchars($p['site_name']) ?></td>
      <?php $env = (string)($p['env'] ?? 'sandbox'); $envLabel = $env === 'production' ? 'Produção' : 'Sandbox'; $envClass = $env === 'production' ? 'ok' : 'warn'; ?>
      <td><span class="badge <?= $envClass ?>"><?= $envLabel ?></span></td>
      <td class="muted"><?= htmlspecialchars($p['client_reference_id']) ?></td>
      <td class="muted"><?= htmlspecialchars((string)($p['customer_name'] ?? '-')) ?></td>
      <td>R$ <?= number_format(((int)$p['amount_cents'])/100,2,',','.') ?></td>
      <?php $labType=['credit_card'=>'C. Crédito','debit_card'=>'C. Débito','pix'=>'PIX','boleto'=>'Boleto']; ?>
      <td><?= htmlspecialchars($labType[$p['payment_method_type']] ?? (string)$p['payment_method_type']) ?></td>
      <?php
        $st = (string)$p['status'];
        $stLabelMap = [
          'created'=>'Criada','processing'=>'Processando','paid'=>'Paga','failed'=>'Falhou','canceled'=>'Cancelada','refunded'=>'Estornada','chargeback'=>'Chargeback'
        ];
        $stClassMap = [
          'created'=>'info','processing'=>'warn','paid'=>'ok','failed'=>'err','canceled'=>'warn','refunded'=>'info','chargeback'=>'err'
        ];
        $stLabel = $stLabelMap[$st] ?? $st;
        $stClass = $stClassMap[$st] ?? '';
      ?>
      <td><span class="badge <?= $stClass ?>"><?= htmlspecialchars($stLabel) ?></span></td>
      <td><?= htmlspecialchars(Helper::formatDate2BR($p['created_at'])) ?></td>
      <td class="right" style="white-space:nowrap;">
        <form method="post" action="<?= $prefix ?>/payments/resend" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>" />
          <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>" />
          <button type="button" class="btn-icon js-resend-one" title="Reenviar" data-confirm-local="Reenviar a última notificação desta fatura para o seu site? Isso não cria nova cobrança."><i class="bi bi-arrow-repeat"></i></button>
        </form>
        <?php $charge = (string)($p['stone_payment_id'] ?? ''); if ($charge !== ''): ?>
          <button type="button" class="btn-icon danger" title="Cancelar" data-action="cancel" data-charge-id="<?= htmlspecialchars($charge) ?>" data-confirm="Cancelar esta cobrança?"><i class="bi bi-x-circle"></i></button>
        <?php endif; ?>
        <button class="btn-icon" title="Ver detalhes" data-payment-id="<?= (int)$p['id'] ?>" data-action="show-json"><i class="bi bi-file-earmark-text"></i></button>
        <button class="btn-icon danger" title="Excluir (ocultar)" data-payment-id="<?= (int)$p['id'] ?>" data-action="soft-delete"><i class="bi bi-trash"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </form>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<script>
  (function($){
    var $form = $('form[action$="/invoices"]');
    var $tbl = $('#tblInvoices');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';

    // Overlay de progresso
    var overlay = $('<div id="resendOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;color:#fff;"><div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;min-width:320px;box-shadow:0 10px 30px rgba(0,0,0,.35)"><div style="font-weight:700;margin-bottom:8px">Reenviando notificações…</div><div id="resendProgressText" class="muted" style="margin-bottom:10px">0 / 0</div><div style="height:10px;background:#0f172a;border-radius:6px;overflow:hidden;margin-bottom:12px"><div id="resendProgressBar" style="height:100%;width:0%;background:#6366f1"></div></div><div class="flex" style="justify-content:flex-end;gap:8px"><button id="resendCancel" class="btn secondary">Parar</button><button id="resendClose" class="btn" style="display:none">Fechar</button></div></div></div>');
    $('body').append(overlay);
    var summaryModal = $('<div id="resendSummary" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:10000;align-items:center;justify-content:center;"><div style="background:#0f172a;border:1px solid #334155;border-radius:14px;max-width:880px;width:96%;color:#e2e8f0;box-shadow:0 10px 30px rgba(0,0,0,.45)"><div style="padding:14px 18px;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between"><div style="font-weight:700">Resumo do reenvio</div><button class="btn secondary js-close">Fechar</button></div><div style="padding:14px 18px"><div id="resendSummaryMeta" class="muted" style="margin-bottom:10px"></div><div style="max-height:360px;overflow:auto;border:1px solid #1e293b;border-radius:10px"><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Fatura</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Status</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">HTTP</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Callback URL</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Erro</th></tr></thead><tbody id="resendSummaryBody"></tbody></table></div></div></div></div>');
    $('body').append(summaryModal);
    var abortAll=false;
    function showOverlay(total){ abortAll=false; $('#resendProgressBar').css('width','0%'); $('#resendProgressText').text('0 / '+total); $('#resendClose').hide(); $('#resendCancel').show(); overlay.css('display','flex'); }
    function hideOverlay(){ overlay.hide(); }
    function updateProgress(done,total){ var pct = total? Math.round(done*100/total):0; $('#resendProgressBar').css('width', pct+'%'); $('#resendProgressText').text(done+' / '+total+' ('+pct+'%)'); }
    $('#resendCancel').on('click', function(){ abortAll=true; $(this).prop('disabled', true).text('Parando…'); });
    $('#resendClose').on('click', function(){ hideOverlay(); });
    summaryModal.on('click', '.js-close', function(){ summaryModal.hide(); });

    async function resendOne(id, token){ try{ let resp = await fetch('<?= $prefix ?>/payments/resend?ajax=1', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token': token || ''}, body: new URLSearchParams({ csrf: token || '', payment_id: String(id) }) }); if(!resp.ok) return {failed:1}; let json={}; try{ json = await resp.json(); }catch(e){ return {failed:1}; } return json; } catch(e){ return {failed:1}; } }
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
      if(!ids || !ids.length){ if(window.Swal) Swal.fire({icon:'info', text:'Selecione pelo menos uma fatura.'}); return; }
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
          Swal.fire({icon: icon, title:'Reenvio concluído', text: (s==='enviado' ? 'Webhook reenviado com sucesso.' : (s==='ignorado' ? 'Nada para reenviar.' : 'Falha ao reenviar o webhook.'))});
        }
      } else {
        showSummaryModal(results, ok, fail, skip);
      }
    }

    function fmtBrDate(v){
      if (!v) return '';
      var m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
      if (!m) return v;
      var y=m[1], mo=m[2], d=m[3], hh=m[4], mm=m[5];
      var out = d + '/' + mo + '/' + y;
      if (hh && mm) out += ' ' + hh + ':' + mm;
      return out;
    }
    function toIsoDate(v){
      if(!v) return '';
      var m = String(v).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
      if(m) return m[3]+'-'+m[2]+'-'+m[1];
      return v;
    }
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax: { url: $tbl.data('url'), data: function(d){ d.site_id=$form.find('[name=site_id]').val(); d.status=$form.find('[name=status]').val(); d.type=$form.find('[name=type]').val(); d.env=$form.find('[name=env]').val(); var f=$form.find('[name=from]').val(); var t=$form.find('[name=to]').val(); d.from=toIsoDate(f); d.to=toIsoDate(t); d.q=$form.find('[name=q]').val(); d.transaction_id=$form.find('[name=transaction_id]').val(); d.name=$form.find('[name=name]').val(); d.email=$form.find('[name=email]').val(); d.document=$form.find('[name=document]').val(); } },
      order:[[1,'desc']],
      columns:[
        {data:null, orderable:false, render:function(row){ return '<input type="checkbox" name="ids[]" value="'+row.id+'" />'; }},
        {data:'id'},{data:'site_name'},{data:'env', render:function(v){ var env=String(v||'sandbox'); var label= env==='production' ? 'Produção' : 'Sandbox'; var cls= env==='production' ? 'ok' : 'warn'; return '<span class="badge '+cls+'">'+label+'</span>'; }},
        {data:'client_reference_id'},{data:'customer_name', render:function(v){ return v ? v : '-'; }},
        {data:'amount_cents', render:function(v){return 'R$ ' + (v/100).toFixed(2).replace('.',',');}},
        {data:'payment_method_type', render:function(v){ var mapType={'credit_card':'C. Crédito','debit_card':'C. Débito','pix':'PIX','boleto':'Boleto'}; return mapType[v]||v; }},{data:'status', render:function(v){ var mapLab={'created':'Criada','processing':'Processando','paid':'Paga','failed':'Falhou','canceled':'Cancelada','refunded':'Estornada','chargeback':'Chargeback'}; var mapCls={'created':'info','processing':'warn','paid':'ok','failed':'err','canceled':'warn','refunded':'info','chargeback':'err'}; var lab = mapLab[v]||v; var cls = mapCls[v]||''; return '<span class="badge '+cls+'">'+lab+'</span>'; }},{data:'created_at', render:function(v){ return fmtBrDate(v); }},
        {data:null, orderable:false, render:function(row){ var id=row.id; var html='<div class="action-grid">'; html+='<form method="post" action="<?= $prefix ?>/payments/resend" style="display:inline">' + '<input type="hidden" name="csrf" value="'+csrfToken+'" />' + '<input type="hidden" name="payment_id" value="'+id+'" />' + '<button type="button" class="btn-icon info js-resend-one" title="Reenviar" data-confirm-local="Reenviar a última notificação desta fatura para o seu site? Isso não cria nova cobrança."><i class="bi bi-arrow-repeat"></i></button>' + '</form>'; var ch=row.stone_payment_id||''; if(ch){ html+='<button type="button" class="btn-icon danger" title="Cancelar" data-action="cancel" data-charge-id="'+ch+'" data-confirm="Cancelar esta cobrança?"><i class="bi bi-x-circle"></i></button>'; } html+='<button type="button" class="btn-icon" title="Histórico" data-action="show-history" data-payment-id="'+id+'"><i class="bi bi-clock-history"></i></button>'; html+='<button type="button" class="btn-icon primary" title="Ver detalhes" data-action="show-json" data-payment-id="'+id+'"><i class="bi bi-file-earmark-text"></i></button>'; html+='<button type="button" class="btn-icon danger" title="Excluir (ocultar)" data-action="soft-delete" data-payment-id="'+id+'"><i class="bi bi-trash"></i></button>'; html+='</div>'; return html; } }
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });

    function updateToggleAll(){
      var $btn = $('.js-toggle-all');
      var $checks = $('#tblInvoices').find('input[name="ids[]"]');
      if (!$checks.length) { $btn.text('Marcar todos'); return; }
      var allChecked = $checks.length === $checks.filter(':checked').length;
      $btn.text(allChecked ? 'Desmarcar todos' : 'Marcar todos');
    }
    $(document).on('change', '#tblInvoices input[name="ids[]"]', updateToggleAll);
    $(document).on('click', '.js-toggle-all', function(e){
      e.preventDefault();
      var $checks = $('#tblInvoices').find('input[name="ids[]"]');
      if (!$checks.length) return;
      var allChecked = $checks.length === $checks.filter(':checked').length;
      $checks.prop('checked', !allChecked);
      updateToggleAll();
    });
    if ($tbl.data('DataTable')) { dt.on('draw', updateToggleAll); }

    // AJAX: unitário (click)
    $(document).on('click', '.js-resend-one', function(e){
      e.preventDefault(); e.stopPropagation();
      var $form = $(this).closest('form');
      var id = $form.find('input[name=payment_id]').val();
      var token = $form.find('input[name=csrf]').val() || csrfToken;
      var msg = $(this).attr('data-confirm-local') || 'Reenviar a última notificação desta fatura para o seu site? Isso não cria nova cobrança.';
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) processIds([id], token); });
      } else {
        if (confirm(msg)) processIds([id], token);
      }
    });
    // AJAX: em lote (click)
    $(document).on('click', '.js-resend-batch', function(e){
      e.preventDefault(); e.stopPropagation();
      var ids=[]; $('#tblInvoices').find('input[name="ids[]"]:checked').each(function(){ ids.push($(this).val()); });
      var token = $('form[action$="/payments/resend-batch"]').find('input[name=csrf]').val() || csrfToken;
      var msg = $(this).attr('data-confirm-local') || 'Reenviar a última notificação das faturas selecionadas para o seu site? Isso não cria nova cobrança.';
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) processIds(ids, token); });
      } else {
        if (confirm(msg)) processIds(ids, token);
      }
    });

    async function postForm(url, data){
      let resp = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body: new URLSearchParams(data) });
      let json={}; try{ json = await resp.json(); }catch(e){}
      return {ok: resp.ok, json};
    }

    // AJAX: retry
    $(document).on('click','button[data-action="retry"]', async function(){
      var charge = $(this).data('charge-id');
      if (!charge) return;
      const ok = await Swal.fire({icon:'question', text:'Reprocessar esta cobrança?', showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Não'}).then(r=>r.isConfirmed);
      if (!ok) return;
      try{
        let {ok:okHTTP, json} = await postForm('<?= $prefix ?>/payments/retry?ajax=1', { csrf: csrfToken, stone_charge_id: String(charge) });
        if (!okHTTP){ Swal.fire({icon:'error', text:'Falha ao reprocessar: '+(json.message||'erro')}); return; }
        if ($tbl.data('DataTable')) { var dtApi=$tbl.DataTable(); var tr=$(this).closest('tr'); dtApi.row(tr).remove().draw(false); }
        var labMap={'created':'Criada','processing':'Processando','paid':'Paga','failed':'Falhou','canceled':'Cancelada','refunded':'Estornada','chargeback':'Chargeback'};
        var s = json.status||''; var sl=labMap[s]||s||'atualizado';
        Swal.fire({icon:'success', text:'Status atualizado: '+sl});
      }catch(e){ Swal.fire({icon:'error', text:'Falha ao reprocessar'}); }
    });

    // AJAX: cancel
    $(document).on('click','button[data-action="cancel"]', async function(){
      var charge = $(this).data('charge-id');
      if (!charge) return;
      var confirmMsg = $(this).attr('data-confirm') || 'Cancelar esta cobrança?';
      const ok = await Swal.fire({icon:'question', text:confirmMsg, showCancelButton:true, confirmButtonText:'Sim', cancelButtonText:'Não'}).then(r=>r.isConfirmed);
      if (!ok) return;
      try{
        let {ok:okHTTP, json} = await postForm('<?= $prefix ?>/payments/cancel?ajax=1', { csrf: csrfToken, stone_charge_id: String(charge) });
        if (!okHTTP){ Swal.fire({icon:'error', text:'Falha ao cancelar: '+(json.message||'erro')}); return; }
        Swal.fire({icon:'success', text:'Cobrança cancelada.'});
        if ($tbl.data('DataTable')) dt.ajax.reload(null,false);
      }catch(e){ Swal.fire({icon:'error', text:'Falha ao cancelar'}); }
    });

    // AJAX: detalhes/histórico/exclusão (reaproveita endpoints de pagamentos)
    $(document).on('click','button[data-action="show-json"]', async function(){
      var id=$(this).data('payment-id'); if(!id) return;
      try{
        let resp = await fetch('<?= $prefix ?>/payments/json?id='+id, { headers:{'Accept':'application/json'} });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao carregar detalhes'}); return; }
        let j = await resp.json();
        var payload = j && j.payment ? j.payment : {};
        var req = j && j.request ? j.request : {};
        var res = j && j.response ? j.response : {};
        var html = '<div style="text-align:left;max-height:60vh;overflow:auto">'
          +'<h4>Pagamento</h4><pre style="white-space:pre-wrap">'+JSON.stringify(payload,null,2)+'</pre>'
          +'<h4>Request</h4><pre style="white-space:pre-wrap">'+JSON.stringify(req,null,2)+'</pre>'
          +'<h4>Response</h4><pre style="white-space:pre-wrap">'+JSON.stringify(res,null,2)+'</pre>'
          +'</div>';
        Swal.fire({title:'Detalhes', html:html, width:900});
      }catch(e){ Swal.fire({icon:'error', text:'Erro ao exibir detalhes'}); }
    });
    $(document).on('click','button[data-action="show-history"]', async function(){
      var id=$(this).data('payment-id'); if(!id) return;
      try{
        let resp = await fetch('<?= $prefix ?>/payments/history?id='+id, { headers:{'Accept':'application/json'} });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao carregar histórico'}); return; }
        let j = await resp.json();
        var rows = (j && j.history) ? j.history : [];
        var html = '<div style="text-align:left;max-height:60vh;overflow:auto"><table class="table"><thead><tr><th>Quando</th><th>De</th><th>Para</th><th>Motivo</th></tr></thead><tbody>';
        rows.forEach(function(r){ html += '<tr><td>'+r.created_at+'</td><td>'+r.old_status+'</td><td>'+r.new_status+'</td><td>'+ (r.reason||'') +'</td></tr>'; });
        html += '</tbody></table></div>';
        Swal.fire({title:'Histórico', html:html, width:800});
      }catch(e){ Swal.fire({icon:'error', text:'Erro ao exibir histórico'}); }
    });
    $(document).on('click','button[data-action="soft-delete"]', async function(){
      var id=$(this).data('payment-id'); if(!id) return;
      const ok = await Swal.fire({icon:'warning', text:'Excluir (ocultar) esta fatura?', showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed);
      if (!ok) return;
      try{
        let {ok:okHTTP, json} = await postForm('<?= $prefix ?>/payments/delete?ajax=1', { csrf: csrfToken, id: String(id) });
        if (!okHTTP){ Swal.fire({icon:'error', text:'Falha ao excluir: '+(json.message||'erro')}); return; }
        Swal.fire({icon:'success', text:'Fatura removida.'});
        if ($tbl.data('DataTable')) dt.ajax.reload(null,false);
      }catch(e){ Swal.fire({icon:'error', text:'Falha ao excluir'}); }
    });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
