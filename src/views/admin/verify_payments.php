<?php ob_start(); ?>
<h1>Verificar Pagamento (Stone)</h1>
<div class="panel">
  <form method="get" class="filter-bar">
    <input class="form-control" name="reference" value="<?= htmlspecialchars((string)($ref??'')) ?>" placeholder="Referência (client_reference_id)" />
    <input class="form-control" name="stone_id" value="<?= htmlspecialchars((string)($stoneId??'')) ?>" placeholder="ID da charge/order (ch_... ou or_...)" />
    <button class="btn">Consultar</button>
    <a class="btn secondary" href="<?= $prefix ?>/verify">Voltar</a>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger">Erro: <?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($local) || !empty($remote) || !empty($remoteOrder)): ?>
  <div class="panel">
    <h3>Resultado</h3>
    <div class="grid cols-3">
      <div class="col-span-1">
        <h4>Local (Hub)</h4>
        <?php if ($local): ?>
          <table class="table"><tbody>
            <tr><th>ID</th><td><?= (int)$local['id'] ?></td></tr>
            <tr><th>Referência</th><td><?= htmlspecialchars((string)$local['client_reference_id']) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars((string)$local['status']) ?>"><?= htmlspecialchars((string)$local['status']) ?></span></td></tr>
            <tr><th>Método</th><td><?= htmlspecialchars((string)$local['payment_method_type']) ?></td></tr>
            <tr><th>Valor</th><td>R$ <?= number_format(((int)$local['amount_cents'])/100,2,',','.') ?></td></tr>
            <tr><th>Stone ID</th><td><?= htmlspecialchars((string)($local['stone_payment_id'] ?? '')) ?></td></tr>
          </tbody></table>
        <?php else: ?><div class="muted">Nenhum registro local encontrado.</div><?php endif; ?>
      </div>
      <div class="col-span-1">
        <h4>Charge (Stone)</h4>
        <?php if ($remote): ?>
          <table class="table"><tbody>
            <tr><th>ID</th><td><?= htmlspecialchars((string)($remote['id'] ?? '')) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars((string)($remote['status'] ?? '')) ?>"><?= htmlspecialchars((string)($remote['status'] ?? '')) ?></span></td></tr>
            <tr><th>Método</th><td><?= htmlspecialchars((string)($remote['payment_method'] ?? ($remote['payment_method_type'] ?? ''))) ?></td></tr>
            <tr><th>Valor</th><td><?php $tot=(int)($remote['amount'] ?? ($remote['amount_cents'] ?? 0)); echo 'R$ '.number_format($tot/100,2,',','.'); ?></td></tr>
            <tr><th>Parcela(s)</th><td><?= (int)($remote['installments'] ?? ($remote['last_transaction']['installments'] ?? 1)) ?></td></tr>
            <tr><th>Gateway</th><td><?= htmlspecialchars((string)($remote['gateway_response']['message'] ?? '')) ?></td></tr>
          </tbody></table>
        <?php else: ?><div class="muted">Charge não encontrada.</div><?php endif; ?>
      </div>
      <div class="col-span-1">
        <h4>Order (Stone)</h4>
        <?php if ($remoteOrder): ?>
          <table class="table"><tbody>
            <tr><th>ID</th><td><?= htmlspecialchars((string)($remoteOrder['id'] ?? '')) ?></td></tr>
            <tr><th>Código</th><td><?= htmlspecialchars((string)($remoteOrder['code'] ?? '')) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars((string)($remoteOrder['status'] ?? '')) ?>"><?= htmlspecialchars((string)($remoteOrder['status'] ?? '')) ?></span></td></tr>
            <tr><th>Total</th><td><?php $tot=(int)($remoteOrder['amount'] ?? ($remoteOrder['amount_cents'] ?? 0)); echo 'R$ '.number_format($tot/100,2,',','.'); ?></td></tr>
            <tr><th>Charges</th><td><?= isset($remoteOrder['charges']) ? count((array)$remoteOrder['charges']) : 0 ?></td></tr>
          </tbody></table>
        <?php else: ?><div class="muted">Order não encontrada.</div><?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';

