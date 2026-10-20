<?php ob_start(); ?>
<h1>Diagnóstico: Autenticação Pagar.me</h1>

<div class="panel">
  <p class="muted">Este teste realiza uma requisição segura (GET /customers) na API do Pagar.me usando a <b>conta</b> e <b>ambiente</b> selecionados. Não cria nem altera dados.</p>
  <form method="get" action="<?= $prefix ?>/diagnostics/stone-auth" class="flex" style="gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="run" value="1" />
    <label>Conta<br>
      <select class="form-select w-xl" name="account_id" required>
        <option value="">Selecione...</option>
        <?php foreach (($accounts ?? []) as $acc): $sel=(int)($selectedAccountId??0)===(int)$acc['id']?'selected':''; ?>
          <option value="<?= (int)$acc['id'] ?>" <?= $sel ?>><?= htmlspecialchars((string)$acc['name']) ?> (<?= htmlspecialchars((string)$acc['active_env']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Ambiente<br>
      <select class="form-select w-md" name="env">
        <option value="">Padrão da conta</option>
        <option value="production">Produção</option>
        <option value="sandbox">Homolog (Sandbox)</option>
      </select>
    </label>
    <button class="btn" type="submit"><i class="bi bi-plug"></i> Testar</button>
  </form>
</div>

<?php if (!empty($ran)): ?>
  <div class="space"></div>
  <div class="panel">
    <h3>Resultado do Teste</h3>
    <div class="muted">Ambiente testado: <strong><?= htmlspecialchars(($testedEnv ?? '')) ?></strong></div>
    <?php if (($result['status'] ?? '') === 'ok'): ?>
      <div class="alert success">[OK] Conexão autenticada (HTTP <?= (int)($result['http'] ?? 0) ?>).</div>
      <?php if (isset($result['count'])): ?>
        <div class="muted">Clientes retornados: <?= (int)$result['count'] ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert danger">[ERRO] HTTP <?= (int)($result['http'] ?? 0) ?><?= isset($result['errorCode']) && $result['errorCode'] ? ' - ' . htmlspecialchars((string)$result['errorCode']) : '' ?>: <?= htmlspecialchars((string)($result['message'] ?? 'Falha')) ?></div>
      <?php if (!empty($result['advice'])): ?>
        <div class="muted">Dica: <?= htmlspecialchars((string)$result['advice']) ?></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
