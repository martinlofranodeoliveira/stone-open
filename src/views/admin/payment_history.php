<?php ob_start(); ?>
<h1>Histórico do Pagamento</h1>
<div class="panel">
  <h3 style="margin-top:0;">Pagamento #<?= (int)$payment['id'] ?> <span class="muted">(Ref: <?= htmlspecialchars((string)$payment['client_reference_id']) ?>, Stone: <?= htmlspecialchars((string)$payment['stone_payment_id']) ?>)</span></h3>
  <div class="muted" style="margin:6px 0 10px;">Site: <?= htmlspecialchars((string)$payment['site_name']) ?></div>
  <?php
    $recon = $reconciliation['status'] ?? 'sem_evento';
    $reconLabel = $recon === 'ok' ? 'Conciliação OK' : ($recon === 'divergente' ? 'Divergente' : 'Sem evento');
    $reconCls = $recon === 'ok' ? 'ok' : ($recon === 'divergente' ? 'err' : 'warn');
  ?>
  <div class="flex gap-sm" style="align-items:center;margin-bottom:14px;">
    <span>Status interno:</span>
    <span class="badge"><?= htmlspecialchars((string)($reconciliation['internal_status_label'] ?? $payment['status'])) ?></span>
    <span>Status Stone:</span>
    <span class="badge <?= $reconciliation['stone_status'] ? 'info' : 'warn' ?>"><?= htmlspecialchars((string)($reconciliation['stone_status_label'] ?? 'Sem evento')) ?></span>
    <span class="badge <?= $reconCls ?>"><?= $reconLabel ?></span>
  </div>

  <table class="table">
    <thead>
      <tr><th>Quando</th><th>Tipo</th><th>Detalhe</th><th>IDs</th></tr>
    </thead>
    <tbody>
      <?php foreach (($timeline ?? []) as $t): ?>
        <?php
          $type = $t['type'] ?? '';
          $d = $t['data'] ?? [];
          $when = $t['at'] ?? '';
          $detail = '';
          $ids = '';
          if ($type === 'status') {
              $detail = ($d['old_status_label'] ?? $d['old_status'] ?? '') . ' → ' . ($d['new_status_label'] ?? $d['new_status'] ?? '');
              if (!empty($d['reason'])) $detail .= ' (' . $d['reason'] . ')';
              $ids = 'status#' . (int)($d['id'] ?? 0);
          } elseif ($type === 'webhook') {
              $detail = 'Evento: ' . ($d['event_type'] ?? '-') . ' | status: ' . ($d['status'] ?? '-');
              $ids = 'evt:' . ($d['provider_event_id'] ?? '-') . ' | id:' . (int)($d['id'] ?? 0);
          } elseif ($type === 'forward') {
              $detail = 'Tentativa ' . (int)($d['attempt'] ?? 1) . ' | HTTP ' . (string)($d['response_status'] ?? '-') . ' | ' . ((int)($d['success'] ?? 0) ? 'sucesso' : 'falha');
              if (!empty($d['error'])) $detail .= ' (' . $d['error'] . ')';
              $ids = 'notif:' . (int)($d['notification_id'] ?? 0) . ' | evt:' . ($d['provider_event_id'] ?? '-');
          }
        ?>
        <tr>
          <td><?= htmlspecialchars((string)$when) ?></td>
          <td><?= htmlspecialchars((string)$type) ?></td>
          <td><?= htmlspecialchars((string)$detail) ?></td>
          <td><?= htmlspecialchars((string)$ids) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($timeline ?? [])): ?>
        <tr><td colspan="4" class="text-muted">Sem eventos encontrados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <div class="space"></div>
  <a class="btn secondary" href="<?= $prefix ?>/payments">Voltar</a>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
