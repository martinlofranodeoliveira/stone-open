<?php ob_start(); ?>
<h1>Verificação Direta na Stone</h1>
<div class="panel">
  <p class="muted">Faça consultas diretamente na API da Stone/Pagar.me para validar dados gravados no Hub. Selecione o tipo de consulta no submenu abaixo.</p>
  <div class="grid cols-3">
    <div class="panel">
      <h3>Assinaturas</h3>
      <form method="get" action="<?= $prefix ?>/verify/subscriptions" class="filter-bar">
        <input class="form-control" name="q" placeholder="ID local, ID Stone (subs_...) ou referência" required />
        <select name="account_id" class="form-select">
          <option value="">Conta (opcional)</option>
          <?php foreach (($accounts??[]) as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name']) ?> (<?= htmlspecialchars((string)$a['active_env']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="btn">Consultar</button>
      </form>
      <a class="text-muted" href="<?= $prefix ?>/verify/subscriptions">Abrir página</a>
    </div>
    <div class="panel">
      <h3>Pagamentos</h3>
      <form method="get" action="<?= $prefix ?>/verify/payments" class="filter-bar">
        <input class="form-control" name="reference" placeholder="Referência (client_reference_id)" />
        <input class="form-control" name="stone_id" placeholder="ID da charge/order (ch_... ou or_...)" />
        <select name="account_id" class="form-select">
          <option value="">Conta (opcional)</option>
          <?php foreach (($accounts??[]) as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name']) ?> (<?= htmlspecialchars((string)$a['active_env']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="btn">Consultar</button>
      </form>
      <a class="text-muted" href="<?= $prefix ?>/verify/payments">Abrir página</a>
    </div>
    <div class="panel">
      <h3>Faturas</h3>
      <form method="get" action="<?= $prefix ?>/verify/invoices" class="filter-bar">
        <input class="form-control" name="subscription_id" placeholder="ID da assinatura (subs_...)" required />
        <select name="account_id" class="form-select">
          <option value="">Conta</option>
          <?php foreach (($accounts??[]) as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name']) ?> (<?= htmlspecialchars((string)$a['active_env']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button class="btn">Consultar</button>
      </form>
      <a class="text-muted" href="<?= $prefix ?>/verify/invoices">Abrir página</a>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';

