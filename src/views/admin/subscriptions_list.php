<?php ob_start(); ?>
<h1>Assinaturas</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/subscriptions">
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['siteId']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels=['active'=>'Ativa','trialing'=>'Em teste','past_due'=>'Em atraso','paused'=>'Pausada','canceled'=>'Cancelada','expired'=>'Expirada']; foreach (['active','trialing','past_due','paused','canceled','expired'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filter['status']??'')===$st?'selected':'' ?>><?= $labels[$st] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="env" class="form-select" style="min-width: 150px;">
      <option value="">Todos ambientes</option>
      <option value="production" <?= ($filter['env']??'')==='production'?'selected':'' ?>>Produção</option>
      <option value="sandbox" <?= ($filter['env']??'')==='sandbox'?'selected':'' ?>>Sandbox</option>
    </select>
    <input type="text" class="form-control" name="plan_id" placeholder="Plano" value="<?= htmlspecialchars($filter['planId']??'') ?>" style="min-width: 150px;" />
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/subscriptions"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/subscriptions/export?<?= http_build_query($filter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/subscriptions/export-xls?<?= http_build_query($filter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>
<div class="panel">
  <table id="tblSubscriptions" class="table" data-url="<?= $prefix ?>/subscriptions/data">
    <thead>
      <tr><th>ID</th><th>Site</th><th>Ambiente</th><th>Cliente</th><th>Plano</th><th>Parcelas</th><th>Valor</th><th>Status</th><th>Início</th><th>Fim</th><th>Ações</th></tr>
    </thead>
    <?php foreach ($subscriptions as $s): ?>
    <tr>
      <td><?= (int)$s['id'] ?></td>
      <td><?= htmlspecialchars($s['site_name']) ?></td>
      <?php $env = (string)($s['env'] ?? 'sandbox'); $envLabel = $env === 'production' ? 'Produção' : 'Sandbox'; $envClass = $env === 'production' ? 'ok' : 'warn'; ?>
      <td><span class="badge <?= $envClass ?>"><?= $envLabel ?></span></td>
      <td><?= htmlspecialchars((string)$s['customer_name']) ?> <span class="muted"><?= htmlspecialchars((string)$s['customer_email']) ?></span></td>
      <?php $planLabel = (string)($s['plan_name'] ?? ($s['plan_id'] ?? '')); ?>
      <td><a href="#" class="js-plan-modal" data-subscription-id="<?= (int)$s['id'] ?>"><?= htmlspecialchars($planLabel) ?></a><br><span class="muted js-plan-progress" data-subscription-id="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)($s['plan_progress'] ?? '')) ?></span></td>
      <?php
        $occ = null;
        if (!empty($s['metadata'])) {
          $m = json_decode((string)$s['metadata'], true);
          if (is_array($m) && isset($m['items'][0]['cycles'])) { $occ = (int)$m['items'][0]['cycles']; }
        }
        $valor = 'R$ ' . number_format(((int)$s['amount_cents'])/100,2,',','.');
        $valorFmt = $occ ? ($occ . ' x ' . $valor) : $valor;
      ?>
      <td><?= $occ !== null ? (int)$occ : '' ?></td>
      <td><?= htmlspecialchars($valorFmt) ?></td>
      <?php
        $st = (string)$s['status'];
        $labMap = ['active'=>'Ativa','trialing'=>'Em teste','past_due'=>'Em atraso','paused'=>'Pausada','canceled'=>'Cancelada','expired'=>'Expirada'];
        $clsMap = ['active'=>'ok','trialing'=>'info','past_due'=>'warn','paused'=>'warn','canceled'=>'err','expired'=>'warn'];
        $lab = $labMap[$st] ?? $st; $cls = $clsMap[$st] ?? '';
      ?>
      <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($lab) ?></span></td>
      <?php $sd = !empty($s['start_date']) ? date('d/m/Y', strtotime((string)$s['start_date'])) : ''; $ed = !empty($s['end_date']) ? date('d/m/Y', strtotime((string)$s['end_date'])) : ''; ?>
      <td><?= htmlspecialchars($sd) ?></td>
      <td><?= htmlspecialchars($ed) ?></td>
      <td class="right" style="white-space: nowrap;">
        <div class="action-grid">
        <a class="btn-icon primary js-sub-events" href="<?= $prefix ?>/subscriptions/<?= (int)$s['id'] ?>/events" data-subscription-id="<?= (int)$s['id'] ?>" title="Eventos">
          <i class="bi bi-list-ul"></i>
        </a>
        <?php $isManual = false; if (!empty($s['metadata'])) { $m=@json_decode((string)$s['metadata'], true); if (is_array($m) && !empty($m['manual_billing'])) $isManual = true; } $next = ($isManual?'resume':'pause'); $lbl = $next==='resume'?'Ativar faturamento automático':'Ativar faturamento manual'; $ico = $next==='resume'?'play-circle':'pause-circle'; $cls = $next==='resume'?'success':'warning'; ?>
        <button class="btn-icon <?= $cls ?> js-sub-toggle" data-subscription-id="<?= (int)$s['id'] ?>" data-next="<?= $next ?>" title="<?= $lbl ?>"><i class="bi bi-<?= $ico ?>"></i></button>
        <button class="btn-icon info js-sub-webhook-quick" data-subscription-id="<?= (int)$s['id'] ?>" data-status="<?= htmlspecialchars($st) ?>" title="Reenviar status atual (<?= htmlspecialchars($lab) ?>)"><i class="bi bi-send"></i></button>
        <button class="btn-icon info js-sub-webhook" data-subscription-id="<?= (int)$s['id'] ?>" title="Enviar webhook (selecionar)"><i class="bi bi-broadcast"></i></button>
        <button class="btn-icon danger js-sub-cancel" data-subscription-id="<?= (int)$s['id'] ?>" title="Cancelar"><i class="bi bi-x-circle"></i></button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="space"></div>
  <div class="flex d-none"></div>
</div>
<script>
  (function($){
    var $form = $('form[action$="/subscriptions"]');
    var $tbl = $('#tblSubscriptions');
    var csrfToken = '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>';
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.site_id=$form.find('[name=site_id]').val(); d.status=$form.find('[name=status]').val(); d.env=$form.find('[name=env]').val(); d.plan_id=$form.find('[name=plan_id]').val(); d.from=$form.find('[name=from]').val(); d.to=$form.find('[name=to]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'},{data:'site_name'},{data:'env', render:function(v){ var env=String(v||'sandbox'); var label= env==='production' ? 'Produção' : 'Sandbox'; var cls= env==='production' ? 'ok' : 'warn'; return '<span class="badge '+cls+'">'+label+'</span>'; }},
        {data:'customer_name', defaultContent:''},{data:'plan_label', render:function(v,_,row){ return '<a href="#" class="js-plan-modal" data-subscription-id="'+row.id+'">'+(v||'')+'</a><br><span class="muted">'+(row.plan_progress||'')+'</span>'; }},
        {data:null, orderable:false, render:function(row){
          var occ = row.occurrences;
          if (occ===undefined || occ===null) {
            try{ var meta = row.metadata? (typeof row.metadata==='string'? JSON.parse(row.metadata): row.metadata): null; if(meta && meta.items && meta.items[0] && meta.items[0].cycles){ occ = parseInt(meta.items[0].cycles)||null; } }catch(e){}
          }
          return (occ!==null && !isNaN(occ))? String(occ): '';
        }},
        {data:null, render:function(row){
          var occ = row.occurrences;
          if (occ===undefined || occ===null) {
            try{ var meta = row.metadata? (typeof row.metadata==='string'? JSON.parse(row.metadata): row.metadata): null; if(meta && meta.items && meta.items[0] && meta.items[0].cycles){ occ = parseInt(meta.items[0].cycles)||null; } }catch(e){}
          }
          var val = (row.amount_cents? row.amount_cents:0)/100; var brl = 'R$ '+val.toFixed(2).replace('.',','); return occ? (occ+' x '+brl): brl; }},
        {data:'status', render:function(v){ var mapLab={'active':'Ativa','trialing':'Em teste','past_due':'Em atraso','paused':'Pausada','canceled':'Cancelada','expired':'Expirada'}; var mapCls={'active':'ok','trialing':'info','past_due':'warn','paused':'warn','canceled':'err','expired':'warn'}; var lab=mapLab[v]||v; var cls=mapCls[v]||''; return '<span class="badge '+cls+'">'+lab+'</span>'; }},
        {data:'start_date', render:function(v){ if(!v) return ''; if(/^\d{4}-\d{2}-\d{2}$/.test(v)){ var p=v.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; } var d=new Date(v); if(!isNaN(d)) return d.toLocaleDateString('pt-BR'); return v; }},
        {data:'end_date', render:function(v){ if(!v) return ''; if(/^\d{4}-\d{2}-\d{2}$/.test(v)){ var p=v.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; } var d=new Date(v); if(!isNaN(d)) return d.toLocaleDateString('pt-BR'); return v; }},
        {data:null, orderable:false, render:function(row){
          var id = row.id;
          var html = '<div class="action-grid">';
          html += '<a class="btn-icon primary js-sub-events" href="<?= $prefix ?>/subscriptions/'+id+'/events" data-subscription-id="'+id+'" title="Eventos"><i class="bi bi-list-ul"></i></a> ';
          var manual = false; try{ var meta = row.metadata? (typeof row.metadata==='string'? JSON.parse(row.metadata): row.metadata): null; if(meta && meta.manual_billing){ manual = true; } }catch(e){}
          var next = manual? 'resume' : 'pause';
          var ico = (next==='resume')? 'play-circle' : 'pause-circle';
          var title = (next==='resume'? 'Ativar faturamento automático' : 'Ativar faturamento manual');
          var cls = (next==='resume')? 'success':'warning';
          html += '<button class="btn-icon '+cls+' js-sub-toggle" data-subscription-id="'+id+'" data-next="'+next+'" title="'+title+'"><i class="bi bi-'+ico+'"></i></button> ';
          var stLabelMap = {'active':'Ativa','trialing':'Em teste','past_due':'Em atraso','paused':'Pausada','canceled':'Cancelada','expired':'Expirada'};
          var stLabel = stLabelMap[row.status] || row.status || '';
          html += '<button class="btn-icon info js-sub-webhook-quick" data-subscription-id="'+id+'" data-status="'+(row.status||'')+'" title="Reenviar status atual ('+stLabel+')"><i class="bi bi-send"></i></button> ';
          html += '<button class="btn-icon info js-sub-webhook" data-subscription-id="'+id+'" title="Enviar webhook (selecionar)"><i class="bi bi-broadcast"></i></button> ';
          html += '<button class="btn-icon danger js-sub-cancel" data-subscription-id="'+id+'" title="Cancelar"><i class="bi bi-x-circle"></i></button>';
          html += '</div>';
          return html; }
        }
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });

    // Modal de eventos
    $(document).on('click', 'a.js-sub-events', async function(e){
      e.preventDefault();
      var id = $(this).data('subscription-id');
      try{
        let resp = await fetch('<?= $prefix ?>/subscriptions/json?id='+id, { headers:{'Accept':'application/json'} });
        if(!resp.ok){ Swal.fire({icon:'error', text:'Falha ao carregar eventos'}); return; }
        let j = await resp.json();
        var s = j.subscription || {};
        var events = j.events || [];
        var val = (s.amount_cents? s.amount_cents:0)/100; var brl = 'R$ '+val.toFixed(2).replace('.',',');
        if (s.occurrences && s.occurrences>0) brl = s.occurrences+' x '+brl;
        var html = '<div style="text-align:left;max-height:60vh;overflow:auto">'
          +'<div class="muted" style="margin-bottom:6px">Assinatura #'+id+' <span class="muted">('+ (s.stone_subscription_id||'') +')</span></div>'
          +'<div><b>Status:</b> '+ (s.status_label||s.status||'') +'</div>'
          +'<div><b>Início:</b> '+ (s.start_date_br||'') +' | <b>Fim:</b> '+ (s.end_date_br||'') +'</div>'
          +'<div><b>Valor:</b> '+ brl +'</div>'
          +'<hr/>';
        if (events.length){
          html += '<table class="table"><thead><tr><th>ID</th><th>Evento</th><th>Quando</th><th>Ações</th></tr></thead><tbody>';
          var eventMap={
            'customer.created':'Cliente criado','customer.updated':'Cliente atualizado',
            'card.created':'Cartão criado','card.updated':'Cartão atualizado','card.deleted':'Cartão excluído','card.expired':'Cartão expirado',
            'address.created':'Endereço criado','address.updated':'Endereço atualizado','address.deleted':'Endereço excluído',
            'plan.created':'Plano criado','plan.updated':'Plano atualizado','plan.deleted':'Plano excluído',
            'plan_item.created':'Item do plano criado','plan_item.updated':'Item do plano atualizado','plan_item.deleted':'Item do plano excluído',
            'subscription.created':'Assinatura criada','subscription.canceled':'Assinatura cancelada','subscription.activated':'Assinatura ativada','subscription.paused':'Assinatura pausada','subscription.resumed':'Assinatura retomada','subscription.past_due':'Assinatura em atraso','subscription.expired':'Assinatura expirada',
            'subscription_item.created':'Item da assinatura criado','subscription_item.updated':'Item da assinatura atualizado','subscription_item.deleted':'Item da assinatura excluído',
            'discount.created':'Desconto criado','discount.deleted':'Desconto excluído',
            'usage.created':'Uso criado','usage.deleted':'Uso excluído',
            'increment.created':'Incremento criado','increment.deleted':'Incremento excluído',
            'order.created':'Pedido criado','order.updated':'Pedido atualizado','order.closed':'Pedido fechado','order.canceled':'Pedido cancelado','order.paid':'Pedido pago','order.payment_failed':'Falha no pagamento do pedido',
            'order_item.created':'Item do pedido criado','order_item.updated':'Item do pedido atualizado','order_item.deleted':'Item do pedido excluído',
            'invoice.created':'Fatura criada','invoice.updated':'Fatura atualizada','invoice.paid':'Fatura paga','invoice.payment_failed':'Falha no pagamento da fatura','invoice.canceled':'Fatura cancelada','invoice.overdue':'Fatura em atraso',
            'charge.created':'Cobrança criada','charge.updated':'Cobrança atualizada','charge.paid':'Cobrança paga','charge.payment_failed':'Falha no pagamento da cobrança','charge.refunded':'Cobrança estornada','charge.pending':'Cobrança pendente','charge.processing':'Cobrança em processamento','charge.underpaid':'Cobrança paga a menor','charge.overpaid':'Cobrança paga a maior','charge.partial_canceled':'Cobrança cancelada parcialmente','charge.chargedback':'Chargeback registrado',
            'charge.antifraud_approved':'Antifraude aprovada','charge.antifraud_reproved':'Antifraude reprovada','charge.antifraud_manual':'Antifraude em análise manual','charge.antifraud_pending':'Antifraude pendente',
            'recipient.created':'Recebedor criado','recipient.updated':'Recebedor atualizado','recipient.deleted':'Recebedor excluído',
            'bank_account.created':'Conta bancária criada','bank_account.updated':'Conta bancária atualizada','bank_account.deleted':'Conta bancária excluída',
            'seller.created':'Vendedor criado','seller.updated':'Vendedor atualizado','seller.deleted':'Vendedor excluído',
            'transfer.pending':'Transferência pendente','transfer.created':'Transferência criada','transfer.processing':'Transferência em processamento','transfer.paid':'Transferência paga','transfer.canceled':'Transferência cancelada','transfer.failed':'Falha na transferência',
            'anticipation.pending':'Antecipação pendente','anticipation.created':'Antecipação criada','anticipation.approved':'Antecipação aprovada','anticipation.canceled':'Antecipação cancelada','anticipation.failed':'Falha na antecipação','anticipation.refused':'Antecipação recusada',
            'checkout.created':'Checkout criado','checkout.canceled':'Checkout cancelado',
            'payment.paid':'Pagamento aprovado','payment.failed':'Pagamento falhou','payment.canceled':'Pagamento cancelado','payment.refunded':'Pagamento estornado'
          };
          for (var i=0;i<events.length;i++){
            var e1=events[i];
            var label = eventMap[e1.event_type]||e1.event_type;
            var pid = e1.provider_event_id || '';
            var btn = pid ? '<button type="button" class="btn-icon info js-sub-event-resend" data-provider-id="'+pid+'" title="Reenviar"><i class="bi bi-arrow-repeat"></i></button>' : '';
            html += '<tr data-provider-id="'+pid+'"><td>'+e1.id+'</td><td>'+label+'</td><td>'+(e1.occurred_at_br||e1.occurred_at||'')+'</td><td class="right">'+btn+'</td></tr>';
          }
          html += '</tbody></table>';
        } else {
          html += '<div class="muted">Nenhum evento encontrado.</div>';
        }
        html += '</div>';
        Swal.fire({title:'Eventos da Assinatura', html:html, width:900});
      }catch(err){ Swal.fire({icon:'error', text:'Erro ao exibir eventos'}); }
    });

    // Helpers AJAX actions
    async function actToggle(id, next){
      let url = '<?= $prefix ?>/subscriptions/'+id+'/'+(next==='resume'?'resume':'pause')+'?ajax=1';
      let resp = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body:new URLSearchParams({ csrf: csrfToken, id: String(id) }) });
      if(!resp.ok){ let j={}; try{ j=await resp.json(); }catch(e){} throw new Error(j.message||'Falha ao aplicar ação'); }
      return await resp.json();
    }
    async function actCancel(id){
      let url = '<?= $prefix ?>/subscriptions/'+id+'/cancel?ajax=1';
      let resp = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body:new URLSearchParams({ csrf: csrfToken, id: String(id) }) });
      if(!resp.ok){ let j={}; try{ j=await resp.json(); }catch(e){} throw new Error(j.message||'Falha ao cancelar'); }
      return await resp.json();
    }
    async function emitWebhook(id, eventType){
      let url = '<?= $prefix ?>/subscriptions/'+id+'/emit-event?ajax=1';
      let resp = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body:new URLSearchParams({ csrf: csrfToken, event_type: String(eventType||'') }) });
      let j = {}; try{ j=await resp.json(); }catch(e){}
      if(!resp.ok || (j && j.error)) throw new Error(j.message||j.error||'Falha ao enviar webhook');
      return j;
    }
    async function lookupInboundId(providerId){
      if(!providerId) return null;
      try{
        const resp = await fetch('<?= $prefix ?>/webhooks/lookup?provider_event_id='+encodeURIComponent(providerId), { headers:{'Accept':'application/json'} });
        if(!resp.ok) return null;
        const j = await resp.json();
        return j && j.id ? j.id : null;
      }catch(e){ return null; }
    }
    async function resendInbound(id){
      const body = new URLSearchParams({ csrf: csrfToken, 'ids[]': String(id) });
      const resp = await fetch('<?= $prefix ?>/webhooks/resend?ajax=1', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token': csrfToken},
        body: body.toString()
      });
      let j = {}; try{ j = await resp.json(); }catch(e){}
      return { ok: resp.ok, json: j };
    }
    // Toggle pause/resume
    $(document).on('click','.js-sub-toggle', async function(){
      var $btn = $(this); if ($btn.data('working')) return;
      var id = $btn.data('subscription-id'); var next = $btn.data('next');
      var origHtml = $btn.html();
      try{
        $btn.data('working', true).prop('disabled', true).attr('aria-disabled','true').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        await actToggle(id, next);
        Swal.fire({icon:'success', text: (next==='resume'?'Assinatura retomada.':'Assinatura pausada.')});
        if ($tbl.data('DataTable')) dt.ajax.reload(null,false);
      }catch(e){ Swal.fire({icon:'error', text:String(e.message||e)}); }
      finally { $btn.data('working', false).prop('disabled', false).removeAttr('aria-disabled').html(origHtml); }
    });
    // Cancel
    $(document).on('click','.js-sub-cancel', async function(){
      var $btn = $(this); if ($btn.data('working')) return; var id = $btn.data('subscription-id');
      const ok = await Swal.fire({title:'Cancelar assinatura?', icon:'warning', showCancelButton:true, confirmButtonText:'Cancelar', cancelButtonText:'Voltar'}).then(r=>r.isConfirmed);
      if(!ok) return; var origHtml = $btn.html();
      try{
        $btn.data('working', true).prop('disabled', true).attr('aria-disabled','true').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        await actCancel(id);
        Swal.fire({icon:'success', text:'Assinatura cancelada.'});
        if ($tbl.data('DataTable')) dt.ajax.reload(null,false);
      }catch(e){ Swal.fire({icon:'error', text:String(e.message||e)}); }
      finally { $btn.data('working', false).prop('disabled', false).removeAttr('aria-disabled').html(origHtml); }
    });

    $(document).on('click','.js-sub-webhook', async function(){
      var $btn = $(this); if ($btn.data('working')) return;
      var id = $btn.data('subscription-id');
      var row = null;
      try { row = $tbl.data('DataTable') ? dt.row($btn.closest('tr')).data() : null; } catch(e) { row = null; }
      var status = row && row.status ? row.status : null;
      var map = { 'active':'subscription.activated','trialing':'subscription.created','past_due':'subscription.past_due','paused':'subscription.paused','canceled':'subscription.canceled','expired':'subscription.expired' };
      var defaultEvent = map[status] || 'subscription.activated';
      var options = {
        'subscription.activated':'Assinatura ativada',
        'subscription.created':'Assinatura criada',
        'subscription.canceled':'Assinatura cancelada',
        'subscription.paused':'Assinatura pausada',
        'subscription.resumed':'Assinatura retomada',
        'subscription.past_due':'Assinatura em atraso',
        'subscription.expired':'Assinatura expirada'
      };
      var ev = null;
      if (window.Swal) {
        const res = await Swal.fire({ title:'Enviar webhook', input:'select', inputOptions: options, inputValue: defaultEvent, showCancelButton:true, confirmButtonText:'Enviar', cancelButtonText:'Cancelar' });
        if (!res.isConfirmed) return;
        ev = res.value;
      } else {
        ev = prompt('Informe o event_type', defaultEvent) || null;
      }
      if (!ev) return;
      var origHtml = $btn.html();
      try{
        $btn.data('working', true).prop('disabled', true).attr('aria-disabled','true').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        await emitWebhook(id, ev);
        if (window.Swal) Swal.fire({icon:'success', text:'Webhook enviado.'});
      }catch(e){ if (window.Swal) Swal.fire({icon:'error', text:String(e.message||e)}); }
      finally { $btn.data('working', false).prop('disabled', false).removeAttr('aria-disabled').html(origHtml); }
    });

    $(document).on('click','.js-sub-webhook-quick', async function(){
      var $btn = $(this); if ($btn.data('working')) return;
      var id = $btn.data('subscription-id');
      var status = $btn.data('status') || null;
      var map = { 'active':'subscription.activated','trialing':'subscription.created','past_due':'subscription.past_due','paused':'subscription.paused','canceled':'subscription.canceled','expired':'subscription.expired' };
      var defaultEvent = map[status] || 'subscription.activated';
      var labels = {
        'subscription.activated':'Assinatura ativada',
        'subscription.created':'Assinatura criada',
        'subscription.canceled':'Assinatura cancelada',
        'subscription.paused':'Assinatura pausada',
        'subscription.resumed':'Assinatura retomada',
        'subscription.past_due':'Assinatura em atraso',
        'subscription.expired':'Assinatura expirada'
      };
      const ok = await Swal.fire({title:'Enviar webhook', text:'Enviar '+(labels[defaultEvent]||defaultEvent)+'?', icon:'question', showCancelButton:true, confirmButtonText:'Enviar', cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed);
      if(!ok) return;
      var origHtml = $btn.html();
      try{
        $btn.data('working', true).prop('disabled', true).attr('aria-disabled','true').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
        await emitWebhook(id, defaultEvent);
        if (window.Swal) Swal.fire({icon:'success', text:'Webhook enviado.'});
      }catch(e){ if (window.Swal) Swal.fire({icon:'error', text:String(e.message||e)}); }
      finally { $btn.data('working', false).prop('disabled', false).removeAttr('aria-disabled').html(origHtml); }
    });

    $(document).on('click','.js-sub-event-resend', async function(ev){
      ev.preventDefault();
      var $btn = $(this);
      var providerId = $btn.data('provider-id') || '';
      if (!providerId) { if (window.Swal) Swal.fire({icon:'error', text:'Evento sem referência para reenvio.'}); return; }
      const ok = await Swal.fire({title:'Reenviar webhook?', icon:'question', showCancelButton:true, confirmButtonText:'Reenviar', cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed);
      if(!ok) return;
      if (window.Swal) Swal.fire({title:'Reenviando…', allowOutsideClick:false, didOpen: () => Swal.showLoading()});
      try{
        const inboundId = await lookupInboundId(providerId);
        if (!inboundId) throw new Error('Não foi possível localizar a notificação.');
        const r = await resendInbound(inboundId);
        if (window.Swal) Swal.close();
        if (r.ok && r.json && (r.json.resent || r.json.failed === 0)) {
          if (window.Swal) Swal.fire({icon:'success', text:'Webhook reenviado.'});
        } else {
          const msg = (r.json && (r.json.error || r.json.message)) || 'Falha ao reenviar.';
          if (window.Swal) Swal.fire({icon:'error', text: msg});
        }
      } catch(e){
        if (window.Swal) Swal.close();
        if (window.Swal) Swal.fire({icon:'error', text:String(e.message||e)});
      }
    });
  })(jQuery);
  // Modal simples para detalhes do plano e invoices
  (function(){
    function el(html){ var d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstChild; }
    var modal = null;
    function close(){ if(modal){ modal.remove(); modal=null; } }
    document.addEventListener('click', function(ev){
      var a = ev.target.closest('.js-plan-modal');
      if (!a) return;
      ev.preventDefault();
      var sid = a.getAttribute('data-subscription-id');
      // Build skeleton immediately to avoid multiple clicks
      if (modal) return;
      var shell = ''+
        '<div class="modal-overlay" style="position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;">'+
        '<div class="stone-modal" style="max-width:800px;width:95%;max-height:90vh;overflow:auto;border-radius:8px;padding:16px;">'+
        '<div class="flex" style="justify-content:space-between;align-items:center;"><h3 style="margin:0;">Plano</h3><button class="btn secondary js-close">Fechar</button></div>'+
        '<div class="space"></div>'+
        '<div class="flex" style="justify-content:center;align-items:center;padding:40px 0;"><span class="spinner-border" role="status" aria-hidden="true"></span><span style="margin-left:10px;">Carregando...</span></div>'+
        '</div></div>';
      modal = el(shell);
      document.body.appendChild(modal);
      var bindClose = function(){ var c=modal.querySelector('.js-close'); if(c){ c.addEventListener('click', close); } };
      bindClose();
      modal.addEventListener('click', function(e){ if(e.target.classList.contains('modal-overlay')) close(); });
      // Fetch and replace content
      fetch('<?= $prefix ?>/subscriptions/'+sid+'/plan').then(r=>r.json()).then(function(data){
        var plan = data.plan||{}; var invoices = data.invoices||[]; var prog = data.progress||{};
        var inner = ''+
          '<div class="flex" style="justify-content:space-between;align-items:center;"><h3 style="margin:0;">Plano</h3><button class="btn secondary js-close">Fechar</button></div>'+
          '<div class="space"></div>'+
          '<div class="grid cols-3">'+
          '<div><b>Nome</b><br>'+(plan.name||'-')+'</div>'+
          '<div><b>Valor</b><br>'+(plan.amount_cents? ('R$ '+(plan.amount_cents/100).toFixed(2).replace('.',',')):'-')+'</div>'+
          '<div><b>Moeda</b><br>'+(plan.currency||'-')+'</div>'+
          '<div><b>Ocorrências</b><br>'+(plan.billing_cycles||'-')+'</div>'+
          '<div><b>Intervalo</b><br>'+(plan.interval_count||'1')+' '+(plan.interval||'-')+'</div>'+
          '<div><b>Progresso</b><br>'+( (prog.total? ( (prog.paid||0)+' de '+prog.total ): '-') )+'</div>'+
          '</div>'+
          '<div class="space"></div>'+
          '<h4>Invoices</h4>'+
          '<table class="table"><thead><tr><th>ID Pagamento</th><th>Ocorrência</th><th>Valor</th><th>Status</th><th>Pago em</th><th></th></tr></thead><tbody>'+
          invoices.map(function(it){
            var brl = it.amount_cents? ('R$ '+(it.amount_cents/100).toFixed(2).replace('.',',')) : '-';
            var dt = it.paid_at? new Date(it.paid_at).toLocaleString('pt-BR'):'';
            var mapLab={'created':'Criado','queued':'Na fila','processing':'Processando','authorized':'Autorizado','paid':'Pago','failed':'Falhou','canceled':'Cancelado','refunded':'Estornado','chargeback':'Chargeback','deleted':'Excluído'};
            var mapCls={'created':'warn','queued':'info','processing':'info','authorized':'info','paid':'ok','failed':'err','canceled':'err','refunded':'warn','chargeback':'err','deleted':'warn'};
            var st = String(it.status||''); var lab = mapLab[st]||st; var cls = mapCls[st]||'';
            return '<tr>'
              +'<td>'+it.payment_id+'</td>'
              +'<td>'+(it.occurrence||'')+'</td>'
              +'<td>'+brl+'</td>'
              +'<td><span class="badge '+cls+'">'+lab+'</span></td>'
              +'<td>'+dt+'</td>'
              +'<td><button class="btn btn-sm js-fwd" data-payment-id="'+it.payment_id+'">Enviar webhook</button></td>'
            +'</tr>';
          }).join('')+
          '</tbody></table>';
        var box = modal.querySelector('.stone-modal'); if (box) { box.innerHTML = inner; bindClose(); }
        modal.addEventListener('click', function(e){
          var b=e.target.closest('.js-fwd'); if(!b) return; e.preventDefault();
          var pid=b.getAttribute('data-payment-id');
          var body = new URLSearchParams({ csrf: '<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>', payment_id: String(pid||'') });
          if (window.Swal) {
            Swal.fire({title:'Enviando…', allowOutsideClick:false, didOpen: () => Swal.showLoading()});
          }
          fetch('<?= $prefix ?>/subscriptions/forward-payment',{
            method:'POST',
            credentials:'same-origin',
            headers:{ 'Content-Type':'application/x-www-form-urlencoded', 'Accept':'application/json', 'X-CSRF-Token':'<?= htmlspecialchars(\App\Support\Security\Csrf::token()) ?>' },
            body: body.toString()
          }).then(async function(r){ var j={}; try{ j=await r.json(); }catch(_){}
            if (window.Swal) Swal.close();
            if(r.ok && j && j.ok){
              b.textContent='Enviado'; b.disabled=true;
              if (window.Swal) Swal.fire({ icon:'success', title:'Enviado ao webhook' });
            } else {
              var msg = (j && (j.error||j.message)) || 'Falha ao enviar';
              if (window.Swal) Swal.fire({ icon:'error', text: msg });
              else alert(msg);
            }
          }).catch(function(){ if (window.Swal) Swal.close(); if (window.Swal) Swal.fire({ icon:'error', text:'Falha ao enviar' }); else alert('Falha ao enviar'); });
        });
      });
    });
  })();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
