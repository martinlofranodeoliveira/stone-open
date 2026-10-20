<?php ob_start(); ?>
<h1>Verificar Assinatura (Stone)</h1>
<div class="panel">
  <form method="get" class="filter-bar">
    <input class="form-control" name="q" value="<?= htmlspecialchars((string)($q??'')) ?>" placeholder="ID local, ID Stone (subs_...) ou referência" required />
    <button class="btn">Consultar</button>
    <a class="btn secondary" href="<?= $prefix ?>/verify">Voltar</a>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger">Erro: <?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($local) || !empty($remote)): ?>
  <div class="panel">
    <h3>Resultado</h3>
    <div class="grid cols-2">
      <div>
        <h4>Local (Hub)</h4>
        <?php if ($local): ?>
          <table class="table"><tbody>
            <tr><th>ID</th><td><?= (int)$local['id'] ?></td></tr>
            <tr><th>Stone ID</th><td><?= htmlspecialchars((string)($local['stone_subscription_id'] ?? '')) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars((string)$local['status']) ?>"><?= htmlspecialchars((string)$local['status']) ?></span></td></tr>
            <tr><th>Plano</th><td><?= htmlspecialchars((string)($local['plan_id'] ?? '')) ?></td></tr>
            <tr><th>Valor</th><td>R$ <?= number_format(((int)($local['amount_cents'] ?? 0))/100, 2, ',', '.') ?></td></tr>
            <tr><th>Moeda</th><td><?= htmlspecialchars((string)($local['currency'] ?? 'BRL')) ?></td></tr>
            <tr><th>Início</th><td><?= htmlspecialchars((string)($local['start_date'] ?? '')) ?></td></tr>
            <tr><th>Atualizado</th><td><?= htmlspecialchars((string)($local['updated_at'] ?? '')) ?></td></tr>
          </tbody></table>
        <?php else: ?>
          <div class="muted">Nenhum registro local.</div>
        <?php endif; ?>
      </div>
      <div>
        <h4>Stone</h4>
        <?php if ($remote): ?>
          <table class="table"><tbody>
            <tr><th>ID</th><td><?= htmlspecialchars((string)($remote['id'] ?? '')) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars((string)($remote['status'] ?? '')) ?>"><?= htmlspecialchars((string)($remote['status'] ?? '')) ?></span></td></tr>
            <tr><th>Plano</th><td><?= htmlspecialchars((string)($remote['plan']['id'] ?? ($remote['plan_id'] ?? ''))) ?></td></tr>
            <tr><th>Valor</th><td><?php $v=(int)($remote['amount'] ?? ($remote['amount_cents'] ?? 0)); echo 'R$ '.number_format($v/100,2,',','.'); ?></td></tr>
            <tr><th>Moeda</th><td><?= htmlspecialchars((string)($remote['currency'] ?? 'BRL')) ?></td></tr>
            <tr><th>Próximo Pgto</th><td><?= htmlspecialchars((string)($remote['next_billing_at'] ?? ($remote['next_billing_date'] ?? ''))) ?></td></tr>
          </tbody></table>
        <?php else: ?>
          <div class="muted">Não encontrado na Stone.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';

