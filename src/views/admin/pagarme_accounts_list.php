<?php ob_start(); ?>
<h1>Contas Pagar.me</h1>
<div class="panel">
  <div class="flex between">
    <div></div>
    <div><a class="btn" href="<?= $prefix ?>/pagarme-accounts/create"><i class="bi bi-plus-lg"></i> Nova Conta</a></div>
  </div>
  <table class="table">
    <tr><th>ID</th><th>Nome</th><th>Account UID</th><th>Ambiente</th><th>Status</th><th>Criada</th><th>Ações</th></tr>
    <?php foreach (($accounts ?? []) as $a): ?>
      <?php
        $env = strtolower((string)($a['active_env'] ?? 'sandbox'));
        $envPt = $env==='production' ? 'Produção' : 'Homolog (Sandbox)';
        $envCls = $env==='production' ? 'badge success' : 'badge warning';
        $status = strtolower((string)($a['status'] ?? 'active'));
        $statusPt = $status==='disabled' ? 'Desativada' : 'Ativa';
        $dt = (string)($a['created_at'] ?? '');
        $ts = strtotime($dt ?: '');
        $dtBr = $ts ? date('d/m/Y H:i', $ts) : htmlspecialchars($dt);
      ?>
      <tr>
        <td><?= (int)$a['id'] ?></td>
        <td><?= htmlspecialchars((string)$a['name']) ?></td>
        <td><?= htmlspecialchars((string)($a['stone_account_uid'] ?? '')) ?></td>
        <td><span class="<?= $envCls ?>"><?= htmlspecialchars($envPt) ?></span></td>
        <td><span class="badge info"><?= htmlspecialchars($statusPt) ?></span></td>
        <td><?= $dtBr ?></td>
        <td class="nowrap">
          <a class="btn-icon" href="<?= $prefix ?>/pagarme-accounts/<?= (int)$a['id'] ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
          <button class="btn-icon js-toggle-env" data-id="<?= (int)$a['id'] ?>" title="Trocar ambiente"><i class="bi bi-arrow-left-right"></i></button>
          <form method="post" action="<?= $prefix ?>/pagarme-accounts/delete" style="display:inline" onsubmit="return confirm('Excluir esta conta?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>" />
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>" />
            <button class="btn-icon danger" type="submit" title="Excluir"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<script>
  (function(){
    document.addEventListener('click', function(ev){
      var t = ev.target.closest('.js-toggle-env');
      if (!t) return;
      ev.preventDefault();
      var id = t.getAttribute('data-id');
      if (!id) return;
      var form = new FormData();
      form.append('id', id);
      form.append('csrf', '<?= htmlspecialchars($csrf) ?>');
      var doToggle = function(){
        var url = '<?= $prefix ?>/pagarme-accounts/toggle-env?id=' + encodeURIComponent(id);
        fetch(url, {
          method: 'POST', credentials: 'same-origin', body: form,
          headers: { 'Accept': 'application/json' }
        }).then(async function(r){
          var txt = await r.text();
          var res = {};
          try { res = txt ? JSON.parse(txt) : {}; } catch(e) { throw new Error(txt||'Resposta inválida'); }
          if (!r.ok || !res || res.error || !res.ok) throw new Error((res && (res.error||res.message)) || 'Falha');
          // Atualizar badge na linha
          var tr = t.closest('tr'); if (!tr) return;
          var tdEnv = tr.children[3];
          var env = (res.active_env||'sandbox').toLowerCase();
          var label = env==='production' ? 'Produção' : 'Homolog (Sandbox)';
          var cls = env==='production' ? 'badge success' : 'badge warning';
          tdEnv.innerHTML = '<span class="'+cls+'">'+label+'</span>';
        }).catch(function(err){
          if (window.Swal) Swal.fire({text:'Não foi possível trocar o ambiente: '+err, icon:'error'});
          else alert('Não foi possível trocar o ambiente: '+err);
        });
      };
      if (window.Swal) {
        Swal.fire({
          text: 'Trocar ambiente? Isso pode interromper pagamentos se as chaves estiverem incorretas.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sim, trocar',
          cancelButtonText: 'Cancelar'
        }).then(function(res){ if (res.isConfirmed) doToggle(); });
      } else {
        if (confirm('Trocar ambiente? Isso pode interromper pagamentos se as chaves estiverem incorretas.')) doToggle();
      }
    });
  })();
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
