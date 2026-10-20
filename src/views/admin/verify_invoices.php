<?php ob_start(); ?>
<h1>Verificar Faturas (Stone)</h1>
<div class="panel">
  <form method="get" class="filter-bar">
    <input class="form-control" name="subscription_id" value="<?= htmlspecialchars((string)($sid??'')) ?>" placeholder="ID da assinatura (subs_...)" required />
    <input class="form-control" name="account_id" placeholder="ID da conta" />
    <button class="btn">Consultar</button>
    <a class="btn secondary" href="<?= $prefix ?>/verify">Voltar</a>
  </form>
</div>

<?php if (!empty($error)): ?>
  <div class="panel"><div class="alert danger">Erro: <?= htmlspecialchars((string)$error) ?></div></div>
<?php endif; ?>

<?php if (!empty($remote)): ?>
  <div class="panel">
    <h3>Invoices</h3>
    <div class="table-responsive">
      <table class="table table-hover"><thead>
        <tr><th>Invoice</th><th>Status</th><th>Criada</th><th>Atualizada</th><th>Valor</th></tr>
      </thead><tbody>
        <?php $rows = $remote['data'] ?? (is_array($remote) ? $remote : []); foreach (($rows ?: []) as $inv): ?>
          <tr>
            <td><?= htmlspecialchars((string)($inv['id'] ?? ($inv['code'] ?? ''))) ?></td>
            <td><span class="badge <?= htmlspecialchars((string)($inv['status'] ?? '')) ?>"><?= htmlspecialchars((string)($inv['status'] ?? '')) ?></span></td>
            <td><?= htmlspecialchars((string)($inv['created_at'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($inv['updated_at'] ?? '')) ?></td>
            <td><?php $v=(int)($inv['amount'] ?? ($inv['amount_cents'] ?? 0)); echo 'R$ '.number_format($v/100,2,',','.'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';

