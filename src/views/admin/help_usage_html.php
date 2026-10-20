<?php $title = 'Exemplos de Uso (HTML)'; ob_start(); ?>
<h1>Exemplos de Uso (HTML)</h1>
<div class="panel help">
  <?= $contentHtml ?>
  <div class="space"></div>
  <a class="btn secondary" href="<?= $prefix ?>/help">Voltar</a>
  <a class="btn" href="<?= $prefix ?>/help/client-php"><i class="bi bi-download"></i> Baixar classe PHP</a>
  <a class="btn" href="<?= $prefix ?>/help/usage"><i class="bi bi-download"></i> Baixar exemplos (Markdown)</a>
  <a class="btn" href="<?= $prefix ?>/help/postman"><i class="bi bi-download"></i> Baixar collection (Postman)</a>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
