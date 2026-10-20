<?php ob_start(); ?>
<h1>Eventos da Assinatura</h1>
<div class="panel">
  <h3 style="margin-top:0;">Assinatura #<?= (int)$subscription['id'] ?> <span class="muted">(<?= htmlspecialchars((string)$subscription['stone_subscription_id']) ?>)</span></h3>
  <table class="table" id="tblSubEvents">
    <thead>
      <tr><th>ID</th><th>Evento</th><th>Ocorrência</th><th>Parcelas</th><th>Quando</th><th>Ações</th></tr>
    </thead>
    <?php
      $mapEv = [
        'customer.created'=>'Cliente criado','customer.updated'=>'Cliente atualizado',
        'card.created'=>'Cartão criado','card.updated'=>'Cartão atualizado','card.deleted'=>'Cartão excluído','card.expired'=>'Cartão expirado',
        'address.created'=>'Endereço criado','address.updated'=>'Endereço atualizado','address.deleted'=>'Endereço excluído',
        'plan.created'=>'Plano criado','plan.updated'=>'Plano atualizado','plan.deleted'=>'Plano excluído',
        'plan_item.created'=>'Item do plano criado','plan_item.updated'=>'Item do plano atualizado','plan_item.deleted'=>'Item do plano excluído',
        'subscription.created'=>'Assinatura criada','subscription.canceled'=>'Assinatura cancelada','subscription.activated'=>'Assinatura ativada','subscription.paused'=>'Assinatura pausada','subscription.resumed'=>'Assinatura retomada','subscription.past_due'=>'Assinatura em atraso','subscription.expired'=>'Assinatura expirada',
        'subscription_item.created'=>'Item da assinatura criado','subscription_item.updated'=>'Item da assinatura atualizado','subscription_item.deleted'=>'Item da assinatura excluído',
        'discount.created'=>'Desconto criado','discount.deleted'=>'Desconto excluído',
        'usage.created'=>'Uso criado','usage.deleted'=>'Uso excluído',
        'increment.created'=>'Incremento criado','increment.deleted'=>'Incremento excluído',
        'order.created'=>'Pedido criado','order.updated'=>'Pedido atualizado','order.closed'=>'Pedido fechado','order.canceled'=>'Pedido cancelado','order.paid'=>'Pedido pago','order.payment_failed'=>'Falha no pagamento do pedido',
        'order_item.created'=>'Item do pedido criado','order_item.updated'=>'Item do pedido atualizado','order_item.deleted'=>'Item do pedido excluído',
        'invoice.created'=>'Fatura criada','invoice.updated'=>'Fatura atualizada','invoice.paid'=>'Fatura paga','invoice.payment_failed'=>'Falha no pagamento da fatura','invoice.canceled'=>'Fatura cancelada','invoice.overdue'=>'Fatura em atraso',
        'charge.created'=>'Cobrança criada','charge.updated'=>'Cobrança atualizada','charge.paid'=>'Cobrança paga','charge.payment_failed'=>'Falha no pagamento da cobrança','charge.refunded'=>'Cobrança estornada','charge.pending'=>'Cobrança pendente','charge.processing'=>'Cobrança em processamento','charge.underpaid'=>'Cobrança paga a menor','charge.overpaid'=>'Cobrança paga a maior','charge.partial_canceled'=>'Cobrança cancelada parcialmente','charge.chargedback'=>'Chargeback registrado',
        'charge.antifraud_approved'=>'Antifraude aprovada','charge.antifraud_reproved'=>'Antifraude reprovada','charge.antifraud_manual'=>'Antifraude em análise manual','charge.antifraud_pending'=>'Antifraude pendente',
        'recipient.created'=>'Recebedor criado','recipient.updated'=>'Recebedor atualizado','recipient.deleted'=>'Recebedor excluído',
        'bank_account.created'=>'Conta bancária criada','bank_account.updated'=>'Conta bancária atualizada','bank_account.deleted'=>'Conta bancária excluída',
        'seller.created'=>'Vendedor criado','seller.updated'=>'Vendedor atualizado','seller.deleted'=>'Vendedor excluído',
        'transfer.pending'=>'Transferência pendente','transfer.created'=>'Transferência criada','transfer.processing'=>'Transferência em processamento','transfer.paid'=>'Transferência paga','transfer.canceled'=>'Transferência cancelada','transfer.failed'=>'Falha na transferência',
        'anticipation.pending'=>'Antecipação pendente','anticipation.created'=>'Antecipação criada','anticipation.approved'=>'Antecipação aprovada','anticipation.canceled'=>'Antecipação cancelada','anticipation.failed'=>'Falha na antecipação','anticipation.refused'=>'Antecipação recusada',
        'checkout.created'=>'Checkout criado','checkout.canceled'=>'Checkout cancelado',
        'payment.paid'=>'Pagamento aprovado','payment.failed'=>'Pagamento falhou','payment.canceled'=>'Pagamento cancelado','payment.refunded'=>'Pagamento estornado',
      ];
      $fmtBr = function($v){ if(!$v) return ''; if(preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', (string)$v)){ $ts=strtotime((string)$v); if($ts) return date('d/m/Y H:i', $ts); } $ts=strtotime((string)$v); return $ts? date('d/m/Y H:i', $ts): (string)$v; };
      foreach ($events as $e):
        $evt = (string)($e['event_type'] ?? '');
        $evtLabel = $mapEv[$evt] ?? $evt;
        $when = $fmtBr($e['occurred_at'] ?? '');
    ?>
    <tr data-provider-id="<?= htmlspecialchars((string)($e['provider_event_id'] ?? '')) ?>">
      <td><?= (int)$e['id'] ?></td>
      <td><?= htmlspecialchars($evtLabel) ?></td>
      <td><?= isset($e['occurrence']) && $e['occurrence'] !== null ? (int)$e['occurrence'] : '' ?></td>
      <td><?= isset($e['card_installments']) && $e['card_installments'] !== null ? (int)$e['card_installments'] : '' ?></td>
      <td><?= htmlspecialchars($when) ?></td>
      <td class="right" style="white-space:nowrap;">
        <form method="post" action="<?= $prefix ?>/webhooks/resend" class="js-resend-form" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
          <input type="hidden" name="ids[]" value="" />
          <button type="button" class="btn-icon info js-resend-btn" title="Reenviar"><i class="bi bi-arrow-clockwise"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <script>
  (function(){
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
      const body = new URLSearchParams({ csrf: '<?= htmlspecialchars($csrf) ?>', 'ids[]': String(id) });
      const resp = await fetch('<?= $prefix ?>/webhooks/resend?ajax=1', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-CSRF-Token':'<?= htmlspecialchars($csrf) ?>'},
        body: body.toString()
      });
      let j = {};
      try{ j = await resp.json(); }catch(e){}
      return { ok: resp.ok, json: j };
    }
    document.addEventListener('click', async function(ev){
      const btn = ev.target.closest('.js-resend-btn');
      if(!btn) return;
      ev.preventDefault();
      const tr = btn.closest('tr');
      const providerId = tr ? tr.getAttribute('data-provider-id') : '';
      const inboundId = await lookupInboundId(providerId);
      if(!inboundId){ if(window.Swal) Swal.fire({icon:'error', text:'Não foi possível localizar a notificação para reenviar.'}); return; }
      const msg = 'Reenviar este webhook agora?';
      const proceed = async function(){
        if (window.Swal) {
          Swal.fire({title:'Reenviando…', allowOutsideClick:false, didOpen: () => Swal.showLoading()});
        }
        try{
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
          if (window.Swal) Swal.fire({icon:'error', text:'Falha ao reenviar.'});
        }
      };
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
          .then(function(r){ if (r.isConfirmed) proceed(); });
      } else {
        if (confirm(msg)) proceed();
      }
    });
  })();
  </script>
  <div class="space"></div>
  <a class="btn secondary" href="<?= $prefix ?>/subscriptions">Voltar</a>
  
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
