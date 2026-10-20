<?php ob_start(); ?>
<h1>Webhooks</h1>
<div class="panel">
  <form class="filter-bar" method="get" action="<?= $prefix ?>/webhooks">
    <select name="status" class="form-select" style="min-width: 150px;">
      <option value="">Todos status</option>
      <?php $labels=['pending'=>'Pendente','processing'=>'Processando','processed'=>'Processado','discarded'=>'Descartado','failed'=>'Falhou']; foreach (['pending','processing','processed','discarded','failed'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filter['status']??'')===$st?'selected':'' ?>><?= $labels[$st] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="site_id" class="form-select" style="min-width: 200px;">
      <option value="">Todos os sites</option>
      <?php foreach ($sites as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= ((int)($filter['siteId']??0)===(int)$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" class="form-control" name="event" placeholder="Tipo de evento" value="<?= htmlspecialchars($filter['event']??'') ?>" style="min-width: 200px;" />
    <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($filter['from']??'') ?>" placeholder="dd/mm/aaaa" />
    <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($filter['to']??'') ?>" placeholder="dd/mm/aaaa" />
    <select name="kind" class="form-select" style="min-width: 180px;">
      <?php $kindSel = (string)($filter['kind'] ?? ''); ?>
      <option value="">Todos os tipos</option>
      <option value="payment" <?= $kindSel==='payment'?'selected':'' ?>>Pagamentos</option>
      <option value="subscription" <?= $kindSel==='subscription'?'selected':'' ?>>Assinaturas</option>
    </select>
    <button class="btn secondary"><i class="bi bi-search"></i> Filtrar</button>
    <a class="btn secondary" href="<?= $prefix ?>/webhooks"><i class="bi bi-arrow-counterclockwise"></i> Limpar</a>
    <div style="flex-grow: 1;"></div>
    <a class="btn btn-outline" href="<?= $prefix ?>/webhooks/export?<?= http_build_query($filter) ?>">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
    <a class="btn btn-outline" href="<?= $prefix ?>/webhooks/export-xls?<?= http_build_query($filter) ?>">
      <i class="bi bi-file-earmark-excel"></i> Exportar XLS
    </a>
  </form>
</div>

<div class="panel">
  <form method="post" action="<?= $prefix ?>/webhooks/resend">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <table id="tblWebhooks" class="table" data-url="<?= $prefix ?>/webhooks/data">
      <thead>
        <tr><th>ID</th><th>Ref</th><th>Site</th><th>Tipo</th><th>Evento</th><th>Status</th><th>Recebido</th><th>Ações</th></tr>
      </thead>
      <?php foreach ($webhooks as $w): ?>
      <tr>
        <td><?= (int)$w['id'] ?></td>
        <td><?= htmlspecialchars((string)($w['ref_code'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)$w['site_name']) ?></td>
        <?php $kind = (string)($w['kind'] ?? (str_starts_with(strtolower((string)($w['event_type'] ?? '')), 'subscription.') ? 'subscription' : 'payment')); $kindLab = $kind==='subscription' ? 'Assinatura' : 'Pagamento'; $kindCls = $kind==='subscription' ? 'kind-subscription' : 'kind-payment'; ?>
        <td><span class="badge <?= $kindCls ?>"><?= htmlspecialchars($kindLab) ?></span></td>
        <?php $ev=(string)($w['event_type']??''); $mapEv=[
          // Customers / Cards / Address
          'customer.created'=>'Cliente criado','customer.updated'=>'Cliente atualizado',
          'card.created'=>'Cartão criado','card.updated'=>'Cartão atualizado','card.deleted'=>'Cartão excluído','card.expired'=>'Cartão expirado',
          'address.created'=>'Endereço criado','address.updated'=>'Endereço atualizado','address.deleted'=>'Endereço excluído',
          // Plan / Plan item
          'plan.created'=>'Plano criado','plan.updated'=>'Plano atualizado','plan.deleted'=>'Plano excluído',
          'plan_item.created'=>'Item do plano criado','plan_item.updated'=>'Item do plano atualizado','plan_item.deleted'=>'Item do plano excluído',
          // Subscription / items / discount / usage / increment
          'subscription.created'=>'Assinatura criada','subscription.updated'=>'Assinatura atualizada','subscription.canceled'=>'Assinatura cancelada','subscription.activated'=>'Assinatura ativada','subscription.paused'=>'Assinatura pausada','subscription.resumed'=>'Assinatura retomada','subscription.past_due'=>'Assinatura em atraso','subscription.expired'=>'Assinatura expirada',
          'subscription_item.created'=>'Item da assinatura criado','subscription_item.updated'=>'Item da assinatura atualizado','subscription_item.deleted'=>'Item da assinatura excluído',
          'discount.created'=>'Desconto criado','discount.deleted'=>'Desconto excluído',
          'usage.created'=>'Uso criado','usage.deleted'=>'Uso excluído',
          'increment.created'=>'Incremento criado','increment.deleted'=>'Incremento excluído',
          // Order / order items
          'order.created'=>'Pedido criado','order.updated'=>'Pedido atualizado','order.closed'=>'Pedido fechado','order.canceled'=>'Pedido cancelado','order.paid'=>'Pedido pago','order.payment_failed'=>'Falha no pagamento do pedido',
          'order_item.created'=>'Item do pedido criado','order_item.updated'=>'Item do pedido atualizado','order_item.deleted'=>'Item do pedido excluído',
          // Invoice
          'invoice.created'=>'Fatura criada','invoice.updated'=>'Fatura atualizada','invoice.paid'=>'Fatura paga','invoice.payment_failed'=>'Falha no pagamento da fatura','invoice.canceled'=>'Fatura cancelada','invoice.overdue'=>'Fatura em atraso',
          // Charge
          'charge.created'=>'Cobrança criada','charge.updated'=>'Cobrança atualizada','charge.paid'=>'Cobrança paga','charge.payment_failed'=>'Falha no pagamento da cobrança','charge.refunded'=>'Cobrança estornada','charge.pending'=>'Cobrança pendente','charge.processing'=>'Cobrança em processamento','charge.underpaid'=>'Cobrança paga a menor','charge.overpaid'=>'Cobrança paga a maior','charge.partial_canceled'=>'Cobrança cancelada parcialmente','charge.chargedback'=>'Chargeback registrado',
          // Antifraude
          'charge.antifraud_approved'=>'Antifraude aprovada','charge.antifraud_reproved'=>'Antifraude reprovada','charge.antifraud_manual'=>'Antifraude em análise manual','charge.antifraud_pending'=>'Antifraude pendente',
          // Recipient / bank account / seller
          'recipient.created'=>'Recebedor criado','recipient.updated'=>'Recebedor atualizado','recipient.deleted'=>'Recebedor excluído',
          'bank_account.created'=>'Conta bancária criada','bank_account.updated'=>'Conta bancária atualizada','bank_account.deleted'=>'Conta bancária excluída',
          'seller.created'=>'Vendedor criado','seller.updated'=>'Vendedor atualizado','seller.deleted'=>'Vendedor excluído',
          // Transfer / Anticipation / Checkout
          'transfer.pending'=>'Transferência pendente','transfer.created'=>'Transferência criada','transfer.processing'=>'Transferência em processamento','transfer.paid'=>'Transferência paga','transfer.canceled'=>'Transferência cancelada','transfer.failed'=>'Falha na transferência',
          'anticipation.pending'=>'Antecipação pendente','anticipation.created'=>'Antecipação criada','anticipation.approved'=>'Antecipação aprovada','anticipation.canceled'=>'Antecipação cancelada','anticipation.failed'=>'Falha na antecipação','anticipation.refused'=>'Antecipação recusada',
          'checkout.created'=>'Checkout criado','checkout.canceled'=>'Checkout cancelado',
          // Pagamentos genéricos
          'payment.paid'=>'Pagamento aprovado','payment.failed'=>'Pagamento falhou','payment.canceled'=>'Pagamento cancelado','payment.refunded'=>'Pagamento estornado',
        ]; $evLab=$mapEv[$ev]??$ev; ?>
        <td><?= htmlspecialchars($evLab) ?></td>
        <?php $st=$w['status']; $lab=['pending'=>'Pendente','processing'=>'Processando','processed'=>'Processado','discarded'=>'Descartado','failed'=>'Falhou'][$st] ?? $st; $cls = ($st==='processed'?'ok':($st==='failed'?'err':($st==='processing'?'info':'warn'))); ?>
        <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($lab) ?></span></td>
        <?php $dt = !empty($w['received_at']) ? date('d/m/Y H:i', strtotime((string)$w['received_at'])) : ''; ?>
        <td><?= htmlspecialchars($dt) ?></td>
        <td class="right"><div class="action-grid">
            <button type="button" class="btn-icon primary js-view-payload" data-webhook-id="<?= (int)$w['id'] ?>" title="Ver payload"><i class="bi bi-file-earmark-text"></i></button>
            <button name="ids[]" value="<?= (int)$w['id'] ?>" class="btn-icon info" title="Reenviar" data-confirm="Reenviar este webhook agora?"><i class="bi bi-arrow-clockwise"></i></button>
        </div></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>
</div>
<div class="panel d-none"></div>
<script>
  (function($){
    var $form = $('form[action$="/webhooks"]');
    var $tbl = $('#tblWebhooks');
    var $resendForm = $('form[action$="/webhooks/resend"]');
    var csrfToken = ($resendForm.find('input[name="csrf"]').val() || $form.find('input[name="csrf"]').val() || '');
    var dt = $tbl.DataTable({
      processing:true, serverSide:true, searching:true,
      ajax:{ url:$tbl.data('url'), data:function(d){ d.status=$form.find('[name=status]').val(); d.site_id=$form.find('[name=site_id]').val(); d.event=$form.find('[name=event]').val(); d.from=$form.find('[name=from]').val(); d.to=$form.find('[name=to]').val(); d.kind=$form.find('[name=kind]').val(); }},
      order:[[0,'desc']],
      columns:[
        {data:'id'},
        {data:'ref_code', defaultContent:''},
        {data:'site_name'},
        {data:'is_subscription', render:function(v){ var isSub=(v===1||v==='1'||v===true); var lab=isSub?'Assinatura':'Pagamento'; var cls=isSub?'kind-subscription':'kind-payment'; return '<span class="badge '+cls+'">'+lab+'</span>'; }},
        {data:'event_type', render:function(v){
          var map={
            'customer.created':'Cliente criado','customer.updated':'Cliente atualizado',
            'card.created':'Cartão criado','card.updated':'Cartão atualizado','card.deleted':'Cartão excluído','card.expired':'Cartão expirado',
            'address.created':'Endereço criado','address.updated':'Endereço atualizado','address.deleted':'Endereço excluído',
            'plan.created':'Plano criado','plan.updated':'Plano atualizado','plan.deleted':'Plano excluído',
            'plan_item.created':'Item do plano criado','plan_item.updated':'Item do plano atualizado','plan_item.deleted':'Item do plano excluído',
            'subscription.created':'Assinatura criada','subscription.updated':'Assinatura atualizada','subscription.canceled':'Assinatura cancelada','subscription.activated':'Assinatura ativada','subscription.paused':'Assinatura pausada','subscription.resumed':'Assinatura retomada','subscription.past_due':'Assinatura em atraso','subscription.expired':'Assinatura expirada',
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
          }; return map[v]||v; }},
        {data:'status', render:function(v){ var mapLab={'pending':'Pendente','processing':'Processando','processed':'Processado','discarded':'Descartado','failed':'Falhou'}; var mapCls={'pending':'warn','processing':'info','processed':'ok','discarded':'warn','failed':'err'}; return '<span class="badge '+(mapCls[v]||'')+'">'+(mapLab[v]||v)+'</span>'; }},
        {data:'received_at', render:function(v){ if(!v) return ''; var d=new Date(v); if(!isNaN(d)) return d.toLocaleString('pt-BR'); return v; }},
        {data:null, orderable:false, render:function(row){ return '<div class="action-grid">'
            +'<button type="button" class="btn-icon primary js-view-payload" data-webhook-id="'+row.id+'" title="Ver payload"><i class="bi bi-file-earmark-text"></i></button> '
            +'<button class="btn-icon info" name="ids[]" value="'+row.id+'" title="Reenviar"><i class="bi bi-arrow-clockwise"></i></button>'
            +'</div>'; }}
      ]
    });
    $form.on('submit', function(e){ e.preventDefault(); dt.ajax.reload(); });
    dt.on('draw', function(){
      // Remover confirm padrão dos botões de reenvio por linha para evitar conflito com admin.js
      $tbl.find('button[name="ids[]"][data-confirm]').removeAttr('data-confirm');
    });
    // Também remover para linhas renderizadas no SSR inicial
    $tbl.find('button[name="ids[]"][data-confirm]').removeAttr('data-confirm');
    // Loader helpers
    function showLoader(){
      if (document.getElementById('pageLoader')) return;
      var html = ''
        + '<div id="pageLoader" class="modal-overlay" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:1111;">'
        + '  <div class="stone-modal" style="background:transparent;border:none;box-shadow:none;">'
        + '    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"><span class="visually-hidden">Processando...</span></div>'
        + '  </div>'
        + '</div>';
      var d = document.createElement('div'); d.innerHTML = html; document.body.appendChild(d.firstChild);
    }
    function hideLoader(){ var el=document.getElementById('pageLoader'); if(el) el.remove(); }

    // AJAX helpers
    function ajaxResend(ids){
      if (!ids || !ids.length) return Promise.resolve({resent:0, failed:0});
      var params = new URLSearchParams();
      params.set('csrf', csrfToken||'');
      ids.forEach(function(id){ params.append('ids[]', String(id)); });
      showLoader();
      return fetch('<?= $prefix ?>/webhooks/resend?ajax=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: params.toString()
      }).then(function(resp){ return resp.json().catch(function(){ return {resent:0, failed:1}; }); })
        .finally(function(){ hideLoader(); });
    }


    // Reenvio 1 a 1 via AJAX
    $(document).on('click', 'button[name="ids[]"].btn-icon.info', function(ev){
      ev.preventDefault(); ev.stopImmediatePropagation();
      var id = this.getAttribute('value') || this.value;
      if (!id) return;
      if (window.Swal) {
        Swal.fire({ text: 'Reenviar este webhook agora?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) ajaxResend([id]).then(function(j){ if (window.Swal) Swal.fire({toast:true,position:'top-end',timer:2000,showConfirmButton:false, icon:(j&&j.failed>0)?'warning':'success', title: (j&&j.failed>0)?'Falha no reenvio':'Reenviado'}); dt.ajax.reload(null,false); }); });
      } else {
        if (confirm('Reenviar este webhook agora?')) { ajaxResend([id]).then(function(){ dt.ajax.reload(null,false); }); }
      }
    });
    // Modal simples p/ payload
    function el(html){ var d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstChild; }
    var modal=null; function close(){ if(modal){ modal.remove(); modal=null; } }
    document.addEventListener('click', function(ev){ var b=ev.target.closest('.js-view-payload'); if(!b) return; ev.preventDefault(); var id=b.getAttribute('data-webhook-id'); fetch('<?= $prefix ?>/webhooks/json?id='+encodeURIComponent(id)).then(r=>r.json()).then(function(j){ var payload=(j&&j.payload)? j.payload : (j&&j.webhook&&j.webhook.raw_payload? j.webhook.raw_payload: {}); var pretty=typeof payload==='string'? payload : JSON.stringify(payload, null, 2); 
      // Tentar extrair referência e tipo para cabeçalho
      function rd(o,p){ try{ for(var i=0;i<p.length;i++){ if(!o||typeof o!=='object') return null; o=o[p[i]]; } return o; }catch(e){ return null; } }
      var ref = rd(payload,['data','charge','code']) || rd(payload,['data','order','code']) || rd(payload,['data','code']) || rd(j,['webhook','client_reference_id']) || '';
      var evt = (j&&j.webhook&&j.webhook.event_type)? j.webhook.event_type : (payload&&payload.type? payload.type : '');
      var isSub = !!( (j&&j.webhook&&j.webhook.stone_subscription_id) || (typeof evt==='string' && evt.toLowerCase().indexOf('subscription.')===0) );
      var tipoLab = isSub ? 'Assinatura' : 'Pagamento';
      var html=``
      +'<div class="modal-overlay" style="position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;">'
      +'<div class="stone-modal" style="max-width:900px;width:95%;max-height:90vh;overflow:auto;border-radius:8px;padding:16px;">'
      +'<div class="flex" style="justify-content:space-between;align-items:center;"><h3 style="margin:0;">Payload do Webhook #'+id+'</h3><button class="btn secondary js-close">Fechar</button></div>'
      +'<div class="space"></div>'
      +'<div class="flex" style="justify-content:flex-start;gap:12px;flex-wrap:wrap;"><span class="badge '+(isSub?'info':'success')+'" title="Tipo">'+tipoLab+'</span>'
          +'<span class="badge" title="Referência">Ref: '+$('<div>').text(String(ref||'')).html()+'</span>'
          +(evt? '<span class="badge info" title="Evento">'+$('<div>').text(String(evt)).html()+'</span>' : '')
          +'</div>'
      +'<div class="space"></div>'
      +'<pre style="white-space:pre-wrap;word-break:break-word;">'+$('<div>').text(pretty).html()+'</pre>'
      +'</div></div>';
      modal=el(html); document.body.appendChild(modal); modal.querySelector('.js-close').addEventListener('click', close); modal.addEventListener('click', function(e){ if(e.target.classList.contains('modal-overlay')) close(); });
    }).catch(function(){ alert('Falha ao carregar payload'); }); });
  })(jQuery);
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
