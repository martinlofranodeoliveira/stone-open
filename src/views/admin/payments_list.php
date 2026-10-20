<?php use App\Helpers\Helper;

ob_start(); ?>
<h1>Pagamentos</h1>
<div class="panel">
  <?php $exportFilter = array_filter($filter, static fn($value) => $value !== '' && $value !== null); ?>
  <div class="muted" style="margin-bottom:10px">
    <b>Reenviar</b>: reentrega o último evento (webhook) ao seu site. Não cobra nada no provedor.<br/>
  </div>
  <form class="filter-bar" method="get" action="<?= $prefix ?>/payments">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['site_id']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels=['created'=>'Criado','queued'=>'Na fila','processing'=>'Processando','authorized'=>'Autorizado','paid'=>'Pago','failed'=>'Falhou','canceled'=>'Cancelado','refunded'=>'Estornado','chargeback'=>'Chargeback']; foreach (['created','queued','processing','authorized','paid','failed','canceled','refunded','chargeback'] as $st): ?>
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
    <input type="text" class="form-control" name="transaction_id" placeholder="Transação / Stone ID (or_, ch_, in_)" value="<?= htmlspecialchars($filter['transaction_id']??'') ?>" style="min-width: 250px;" />
    <input type="text" class="form-control" name="name" placeholder="Nome" value="<?= htmlspecialchars($filter['name']??'') ?>" style="min-width: 180px;" />
    <input type="text" class="form-control" name="email" placeholder="Email" value="<?= htmlspecialchars($filter['email']??'') ?>" style="min-width: 220px;" />
    <input type="text" class="form-control" name="document" placeholder="CPF/CNPJ sem pontuação" value="<?= htmlspecialchars($filter['document']??'') ?>" style="min-width: 200px;" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/payments"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/payments/export?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/payments/export-xls?<?= http_build_query($exportFilter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>
<div class="panel">
  <table id="tblPayments" class="table" data-url="<?= $prefix ?>/payments/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Ambiente</th><th>Ref</th><th>Nome</th><th>Valor</th><th>Tipo</th><th>Status</th><th>Criado</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($payments as $p): ?>
    <tr>
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
          'created'=>'Criado','queued'=>'Na fila','processing'=>'Processando','pending'=>'Pendente','authorized'=>'Autorizado','paid'=>'Pago','failed'=>'Falhou','canceled'=>'Cancelado','refunded'=>'Estornado','chargeback'=>'Chargeback'
        ];
        $stClassMap = [
          'created'=>'info','queued'=>'info','processing'=>'warn','pending'=>'warn','authorized'=>'info','paid'=>'ok','failed'=>'err','canceled'=>'warn','refunded'=>'info','chargeback'=>'err'
        ];
        $stLabel = $stLabelMap[$st] ?? $st;
        $stClass = $stClassMap[$st] ?? '';
      ?>
      <td><span class="badge <?= $stClass ?>"><?= htmlspecialchars($stLabel) ?></span></td>
      <td><?= htmlspecialchars(Helper::formatDate2BR($p['created_at'])) ?></td>
      <td class="right" style="white-space:nowrap;">
        <?php
          $bUrl = (string)($p['boleto_url'] ?? '');
          $bPdf = (string)($p['boleto_pdf'] ?? '');
          $bLine = (string)($p['boleto_line'] ?? '');
          $bDue = (string)($p['boleto_due_at'] ?? '');
          $hasBoletoLink = (($p['payment_method_type'] ?? '') === 'boleto') && ($bUrl !== '' || $bPdf !== '' || $bLine !== '');
        ?>
        <?php if ($hasBoletoLink): ?>
          <button type="button" class="btn-icon info" title="Ver boleto"
                  data-action="boleto-view"
                  data-boleto-url="<?= htmlspecialchars($bUrl, ENT_QUOTES) ?>"
                  data-boleto-pdf="<?= htmlspecialchars($bPdf, ENT_QUOTES) ?>"
                  data-boleto-line="<?= htmlspecialchars($bLine, ENT_QUOTES) ?>"
                  data-boleto-due="<?= htmlspecialchars($bDue, ENT_QUOTES) ?>">
            <i class="bi bi-receipt"></i>
          </button>
        <?php endif; ?>
        <form method="post" action="<?= $prefix ?>/payments/resend" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>" />
          <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>" />
          <button type="button" class="btn-icon js-resend-one" title="Reenviar" data-confirm-local="Reenviar a última notificação deste pagamento para o seu site? Isso não cria nova cobrança."><i class="bi bi-arrow-repeat"></i></button>
        </form>
        <?php $charge = (string)($p['stone_payment_id'] ?? ''); if ($charge !== ''): ?>
          <?php if (!in_array($st, ['canceled','refunded'], true)): ?>
            <button type="button" class="btn-icon danger" title="Cancelar" data-action="cancel" data-charge-id="<?= htmlspecialchars($charge) ?>" data-payment-id="<?= (int)$p['id'] ?>" data-confirm="Cancelar esta cobrança?"><i class="bi bi-x-circle"></i></button>
          <?php endif; ?>
          <?php if ($st === 'paid'): ?>
            <button type="button" class="btn-icon warning" title="Estornar" data-action="refund" data-charge-id="<?= htmlspecialchars($charge) ?>" data-payment-id="<?= (int)$p['id'] ?>"><i class="bi bi-arrow-return-left"></i></button>
          <?php endif; ?>
        <?php endif; ?>
        <button class="btn-icon" title="Ver detalhes" data-payment-id="<?= (int)$p['id'] ?>" data-action="show-json"><i class="bi bi-file-earmark-text"></i></button>
        <button class="btn-icon danger" title="Excluir (ocultar)" data-payment-id="<?= (int)$p['id'] ?>" data-action="soft-delete"><i class="bi bi-trash"></i></button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<script>
  (function($){
    var $form = $('form[action$="/payments"]');
    var $tbl = $('#tblPayments');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';

    // Overlay de progresso
    var overlay = $('<div id="resendOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;color:#fff;"><div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px 20px;min-width:320px;box-shadow:0 10px 30px rgba(0,0,0,.35)"><div style="font-weight:700;margin-bottom:8px">Reenviando notificações…</div><div id="resendProgressText" class="muted" style="margin-bottom:10px">0 / 0</div><div style="height:10px;background:#0f172a;border-radius:6px;overflow:hidden;margin-bottom:12px"><div id="resendProgressBar" style="height:100%;width:0%;background:#6366f1"></div></div><div class="flex" style="justify-content:flex-end;gap:8px"><button id="resendCancel" class="btn secondary">Parar</button><button id="resendClose" class="btn" style="display:none">Fechar</button></div></div></div>');
    $('body').append(overlay);
    // Modal de resumo (batch)
    var summaryModal = $('<div id="resendSummary" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:10000;align-items:center;justify-content:center;"><div style="background:#0f172a;border:1px solid #334155;border-radius:14px;max-width:880px;width:96%;color:#e2e8f0;box-shadow:0 10px 30px rgba(0,0,0,.45)"><div style="padding:14px 18px;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between"><div style="font-weight:700">Resumo do reenvio</div><button class="btn secondary js-close">Fechar</button></div><div style="padding:14px 18px"><div id="resendSummaryMeta" class="muted" style="margin-bottom:10px"></div><div style="max-height:360px;overflow:auto;border:1px solid #1e293b;border-radius:10px"><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Pagamento</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Status</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">HTTP</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Callback URL</th><th style="text-align:left;padding:8px 10px;border-bottom:1px solid #1e293b">Erro</th></tr></thead><tbody id="resendSummaryBody"></tbody></table></div></div></div></div>');
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
      if(!ids || !ids.length){ Swal.fire({icon:'info', text:'Selecione pelo menos um pagamento.'}); return; }
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
      // Encerrar overlay antes de exibir feedback final
      $('#resendCancel').hide(); $('#resendClose').show();
      hideOverlay();
      if (ids.length === 1) {
        var s = results[0] ? results[0].status : 'desconhecido';
        var icon = (s==='enviado') ? 'success' : (s==='ignorado' ? 'warning' : 'error');
        Swal.fire({icon: icon, title:'Reenvio concluído', text: (s==='enviado' ? 'Webhook reenviado com sucesso.' : (s==='ignorado' ? 'Nada para reenviar.' : 'Falha ao reenviar o webhook.'))});
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
      order:[[0,'desc']],
      columns:[
        {data:'id'},{data:'site_name'},{data:'env', render:function(v){ var env=String(v||'sandbox'); var label= env==='production' ? 'Produção' : 'Sandbox'; var cls= env==='production' ? 'ok' : 'warn'; return '<span class="badge '+cls+'">'+label+'</span>'; }},
        {data:'client_reference_id'},{data:'customer_name', render:function(v){ return v ? v : '-'; }},
        {data:'amount_cents', render:function(v){return 'R$ ' + (v/100).toFixed(2).replace('.',',');}},
        {data:'payment_method_type', render:function(v){ var mapType={'credit_card':'C. Crédito','debit_card':'C. Débito','pix':'PIX','boleto':'Boleto'}; return mapType[v]||v; }},{data:'status', render:function(v){ var mapLab={'created':'Criado','queued':'Na fila','processing':'Processando','pending':'Pendente','authorized':'Autorizado','paid':'Pago','failed':'Falhou','canceled':'Cancelado','refunded':'Estornado','chargeback':'Chargeback'}; var mapCls={'created':'info','queued':'info','processing':'warn','pending':'warn','authorized':'info','paid':'ok','failed':'err','canceled':'warn','refunded':'info','chargeback':'err'}; var lab = mapLab[v]||v; var cls = mapCls[v]||''; return '<span class="badge '+cls+'">'+lab+'</span>'; }},{data:'created_at', render:function(v){ return fmtBrDate(v); }},
        {data:null, orderable:false, render:function(row){
          var id=row.id;
          var html='<div class="action-grid">';
          var bUrl=row.boleto_url||'';
          var bPdf=row.boleto_pdf||'';
          var bLine=row.boleto_line||'';
          var bDue=row.boleto_due_at||'';
          if (row.payment_method_type === 'boleto' && (bUrl || bPdf || bLine)) {
            html+='<button type="button" class="btn-icon info" title="Ver boleto" data-action="boleto-view" data-boleto-url="'+attrEsc(bUrl)+'" data-boleto-pdf="'+attrEsc(bPdf)+'" data-boleto-line="'+attrEsc(bLine)+'" data-boleto-due="'+attrEsc(bDue)+'"><i class="bi bi-receipt"></i></button>';
          }
          html+='<form method="post" action="<?= $prefix ?>/payments/resend" style="display:inline">' + '<input type="hidden" name="csrf" value="'+csrfToken+'" />' + '<input type="hidden" name="payment_id" value="'+id+'" />' + '<button type="button" class="btn-icon info js-resend-one" title="Reenviar" data-confirm-local="Reenviar a última notificação deste pagamento para o seu site? Isso não cria nova cobrança."><i class="bi bi-arrow-repeat"></i></button>' + '</form>';
          var ch=row.stone_payment_id||'';
          var st=(row.status||'');
          if(ch){
            if(st!=='canceled' && st!=='refunded'){
              html+='<button type="button" class="btn-icon danger" title="Cancelar" data-action="cancel" data-charge-id="'+attrEsc(ch)+'" data-payment-id="'+id+'" data-confirm="Cancelar esta cobrança?"><i class="bi bi-x-circle"></i></button>';
            }
            if(String(row.status||'')==='paid'){
              html+='<button type="button" class="btn-icon warning" title="Estornar" data-action="refund" data-charge-id="'+attrEsc(ch)+'" data-payment-id="'+id+'"><i class="bi bi-arrow-return-left"></i></button>';
            }
          }
          html+='<button type="button" class="btn-icon" title="Histórico" data-action="show-history" data-payment-id="'+id+'"><i class="bi bi-clock-history"></i></button>';
          html+='<button type="button" class="btn-icon primary" title="Ver detalhes" data-action="show-json" data-payment-id="'+id+'"><i class="bi bi-file-earmark-text"></i></button>';
          html+='<button type="button" class="btn-icon danger" title="Excluir (ocultar)" data-action="soft-delete" data-payment-id="'+id+'"><i class="bi bi-trash"></i></button>';
          html+='</div>';
          return html;
        } }
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });

    // AJAX: unitário (click)
    $(document).on('click', '.js-resend-one', function(e){
      e.preventDefault(); e.stopPropagation();
      var $form = $(this).closest('form');
      var id = $form.find('input[name=payment_id]').val();
      var token = $form.find('input[name=csrf]').val() || csrfToken;
      var msg = $(this).attr('data-confirm-local') || 'Reenviar a última notificação deste pagamento para o seu site? Isso não cria nova cobrança.';
      Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
        .then(function(r){ if (r.isConfirmed) processIds([id], token); });
    });
    async function postForm(url, data){
      let resp = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body: new URLSearchParams(data) });
      let json={}; try{ json = await resp.json(); }catch(e){}
      return {ok: resp.ok, json};
    }

    async function askReason(title, confirmText){
      const res = await Swal.fire({
        title: title || 'Motivo',
        input: 'text',
        inputLabel: 'Motivo',
        inputPlaceholder: 'Informe o motivo',
        inputAttributes: { maxlength: 250 },
        showCancelButton: true,
        confirmButtonText: confirmText || 'Continuar',
        cancelButtonText: 'Cancelar',
        inputValidator: function(value){
          if (!value || !value.trim()) return 'Informe o motivo';
          return null;
        }
      });
      if (!res.isConfirmed) return null;
      return (res.value || '').trim();
    }

    function removeRowFromButton(btn){
      if ($tbl.data('DataTable')) {
        var dtApi = $tbl.DataTable();
        var tr = $(btn).closest('tr');
        dtApi.row(tr).remove().draw(false);
      } else {
        $(btn).closest('tr').remove();
      }
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
        removeRowFromButton(this);
        var labMap={'created':'Criado','queued':'Na fila','processing':'Processando','pending':'Pendente','authorized':'Autorizado','paid':'Pago','failed':'Falhou','canceled':'Cancelado','refunded':'Estornado','chargeback':'Chargeback'};
        var s = json.status||''; var sl=labMap[s]||s||'atualizado';
        Swal.fire({icon:'success', text:'Pagamento reprocessado: '+sl});
      }catch(err){ Swal.fire({icon:'error', text:'Erro ao reprocessar'}); }
    });

    // AJAX: cancel
    $(document).on('click','button[data-action="cancel"]', async function(){
      var charge = $(this).data('charge-id'); if (!charge) return;
      var paymentId = $(this).data('payment-id') || '';
      var confirmMsg = $(this).attr('data-confirm') || 'Cancelar esta cobrança?';
      var reason = await askReason('Motivo do cancelamento', 'Continuar');
      if (!reason) return;
      const action = await Swal.fire({
        icon: 'question',
        title: 'Cancelar cobrança',
        text: confirmMsg,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Cancelar cobrança',
        denyButtonText: 'Cancelar e ocultar',
        cancelButtonText: 'Voltar'
      });
      if (!action.isConfirmed && !action.isDenied) return;
      try{
        let {ok:okHTTP, json} = await postForm('<?= $prefix ?>/payments/cancel?ajax=1', { csrf: csrfToken, stone_charge_id: String(charge), reason: reason });
        if (!okHTTP){ Swal.fire({icon:'error', text:'Falha ao cancelar: '+(json.message||'erro')}); return; }
        if (action.isConfirmed) {
          removeRowFromButton(this);
          Swal.fire({icon:'success', text:'Pagamento cancelado e removido da lista.'});
          return;
        }
        if (!paymentId) { Swal.fire({icon:'error', text:'Pagamento sem ID para excluir.'}); return; }
        let {ok:okDel, json:jsonDel} = await postForm('<?= $prefix ?>/payments/delete?ajax=1', { csrf: csrfToken, payment_id: String(paymentId), reason: String(reason||'') });
        if (!okDel){ Swal.fire({icon:'error', text:'Falha ao excluir: '+(jsonDel.message||'erro')}); return; }
        removeRowFromButton(this);
        Swal.fire({icon:'success', text:'Cobrança cancelada e pagamento ocultado.'});
      }catch(err){ Swal.fire({icon:'error', text:'Erro ao cancelar'}); }
    });

    // AJAX: refund
    $(document).on('click','button[data-action="refund"]', async function(){
      var charge = $(this).data('charge-id'); if (!charge) return;
      var reason = await askReason('Motivo do estorno', 'Estornar');
      if (!reason) return;
      const ok = await Swal.fire({icon:'warning', text:'Confirmar estorno desta cobrança?', showCancelButton:true, confirmButtonText:'Estornar', cancelButtonText:'Voltar'}).then(r=>r.isConfirmed);
      if (!ok) return;
      try{
        let {ok:okHTTP, json} = await postForm('<?= $prefix ?>/payments/refund?ajax=1', { csrf: csrfToken, stone_charge_id: String(charge), reason: reason });
        if (!okHTTP){ Swal.fire({icon:'error', text:'Falha ao estornar: '+(json.message||'erro')}); return; }
        removeRowFromButton(this);
        Swal.fire({icon:'success', text:'Pagamento estornado.'});
      }catch(err){ Swal.fire({icon:'error', text:'Erro ao estornar'}); }
    });

    // Visualizar boleto
    $(document).on('click','button[data-action="boleto-view"]', async function(){
      var url = $(this).data('boleto-url') || '';
      var pdf = $(this).data('boleto-pdf') || '';
      var line = $(this).data('boleto-line') || '';
      var due = $(this).data('boleto-due') || '';
      var openUrl = url || pdf || '';
      var html = '<div style="text-align:left">';
      if (due) html += '<div class="muted" style="margin-bottom:6px">Vencimento: '+escapeHtml(String(due))+'</div>';
      if (line) html += '<div class="muted" style="margin-bottom:6px">Linha digitável: <code>'+escapeHtml(String(line))+'</code></div>';
      if (url) html += '<div class="muted" style="margin-bottom:6px">Link: '+escapeHtml(String(url))+'</div>';
      if (pdf) html += '<div class="muted" style="margin-bottom:6px">PDF: '+escapeHtml(String(pdf))+'</div>';
      html += '</div>';
      Swal.fire({
        title: 'Boleto',
        html: html,
        showCancelButton: true,
        cancelButtonText: 'Fechar',
        showConfirmButton: !!openUrl,
        confirmButtonText: 'Abrir boleto'
      }).then(function(r){
        if (r.isConfirmed && openUrl) window.open(openUrl, '_blank', 'noopener');
      });
    });

    // Mostrar JSON do pagamento (request/response)
    $(document).on('click','button[data-action="show-json"]', async function(){
      var id = $(this).data('payment-id');
      try{
        let resp = await fetch('<?= $prefix ?>/payments/json?id='+id, { headers:{'Accept':'application/json'} });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao carregar detalhes'}); return; }
        let j = await resp.json();
        let html = '<div style="text-align:left;max-height:60vh;overflow:auto">'
         +'<div class="muted" style="margin-bottom:6px">Pagamento #'+id+'</div>'
         +'<div class="muted" style="margin:6px 0">Resumo</div><pre>'+ (j.payment? JSON.stringify(j.payment,null,2):'') +'</pre>'
         +'<div class="muted" style="margin:6px 0">Request</div><pre>'+(j.request? JSON.stringify(j.request,null,2):'') +'</pre>'
         +'<div class="muted" style="margin:6px 0">Response</div><pre>'+(j.response? JSON.stringify(j.response,null,2):'') +'</pre>'
         +'</div>';
        Swal.fire({title:'Detalhes do Pagamento', html:html, width:900});
      }catch(e){ Swal.fire({icon:'error', text:'Erro ao exibir detalhes'}); }
    });

    // Soft delete com motivo
    $(document).on('click','button[data-action="soft-delete"]', async function(){
      var id = $(this).data('payment-id');
      var btn = this;
      const { value: reason } = await Swal.fire({
        title: 'Excluir (ocultar) pagamento',
        input: 'text',
        inputLabel: 'Motivo',
        inputPlaceholder: 'Informe o motivo',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar'
      });
      if (reason === undefined) return; // cancel
      try{
        let resp = await fetch('<?= $prefix ?>/payments/delete?ajax=1', {
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
          body: new URLSearchParams({ csrf: csrfToken, payment_id: String(id), reason: String(reason||'') })
        });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao excluir'}); return; }
        // Remover linha imediatamente (DataTable ou tabela simples)
        if ($tbl.data('DataTable')) {
          var dtApi = $tbl.DataTable();
          var tr = $(btn).closest('tr');
          dtApi.row(tr).remove().draw(false);
        } else {
          $(btn).closest('tr').remove();
        }
        Swal.fire({icon:'success', text:'Pagamento ocultado com sucesso.'});
      }catch(e){ Swal.fire({icon:'error', text:'Erro ao excluir'}); }
    });

    // Mostrar Histórico (modal)
    $(document).on('click','button[data-action="show-history"]', async function(){
      var id = $(this).data('payment-id');
      try{
        let resp = await fetch('<?= $prefix ?>/payments/'+id+'/history?ajax=1', { headers:{'Accept':'application/json'} });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao carregar histórico'}); return; }
        let j = await resp.json();
        var timeline = j.timeline || [];
        var html = '<div style="text-align:left;max-height:60vh;overflow:auto">';
        html += '<div class="muted" style="margin-bottom:6px">Pagamento #'+id+' — Ref: <b>'+(j.payment?.reference||'')+'</b></div>';
        var recon = j.reconciliation || {};
        var reconLabel = recon.status === 'ok' ? 'Conciliação OK' : (recon.status === 'divergente' ? 'Divergente' : 'Sem evento');
        var reconCls = recon.status === 'ok' ? 'ok' : (recon.status === 'divergente' ? 'err' : 'warn');
        html += '<div class="flex gap-sm" style="align-items:center;margin-bottom:8px;">'
          + '<span>Status interno:</span>'
          + '<span class="badge">'+escapeHtml(recon.internal_status_label || j.payment?.status || '-')+'</span>'
          + '<span>Status Stone:</span>'
          + '<span class="badge '+(recon.stone_status ? 'info' : 'warn')+'">'+escapeHtml(recon.stone_status_label || 'Sem evento')+'</span>'
          + '<span class="badge '+reconCls+'">'+reconLabel+'</span>'
          + '</div>';
        function fmtBrDateTime(s){ if(!s) return ''; var m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/); if(!m) return s; var hh=m[4]||'00', mm=m[5]||'00', ss=m[6]||'00'; return m[3]+'/'+m[2]+'/'+m[1]+' '+hh+':'+mm+':'+ss; }
        html += '<table class="table" style="min-width:760px">';
        html += '<thead><tr><th>Quando</th><th>Tipo</th><th>Detalhe</th><th>IDs</th></tr></thead><tbody>';
        for (var i=0;i<timeline.length;i++){
          var t = timeline[i] || {};
          var type = t.type || '';
          var d = t.data || {};
          var detail = '';
          var ids = '';
          if (type === 'status') {
            detail = (d.old_status_label || d.old_status || '') + ' → ' + (d.new_status_label || d.new_status || '');
            if (d.reason) detail += ' (' + d.reason + ')';
            ids = 'status#' + (d.id || '');
          } else if (type === 'webhook') {
            detail = 'Evento: ' + (d.event_type || '-') + ' | status: ' + (d.status || '-');
            ids = 'evt:' + (d.provider_event_id || '-') + ' | id:' + (d.id || '');
          } else if (type === 'forward') {
            detail = 'Tentativa ' + (d.attempt || 1) + ' | HTTP ' + (d.response_status ?? '-') + ' | ' + (Number(d.success) ? 'sucesso' : 'falha');
            if (d.error) detail += ' (' + d.error + ')';
            ids = 'notif:' + (d.notification_id || '') + ' | evt:' + (d.provider_event_id || '-');
          }
          html += '<tr>'
            + '<td>'+fmtBrDateTime(t.at||'')+'</td>'
            + '<td>'+escapeHtml(type)+'</td>'
            + '<td>'+escapeHtml(detail)+'</td>'
            + '<td>'+escapeHtml(ids)+'</td>'
            + '</tr>';
        }
        if (!timeline.length) {
          html += '<tr><td colspan="4" class="text-muted">Sem eventos encontrados.</td></tr>';
        }
        html += '</tbody></table></div>';
        Swal.fire({title:'Timeline do Pagamento', html:html, width:900});
      }catch(e){ Swal.fire({icon:'error', text:'Erro ao exibir histórico'}); }
    });

    function escapeHtml(s){ if(!s) return ''; return s.replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    function attrEsc(s){ return escapeHtml(String(s || '')); }
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
