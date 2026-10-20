<?php ob_start(); ?>
<h1>Calculadora de Parcelas</h1>

<div class="panel">
  <h3>Simulador de Parcelas por BIN</h3>
  <p class="muted">Informe o valor e os seis primeiros dígitos do cartão (BIN) para obter bandeira, parcelas e juros. Este cálculo usa a API do provedor quando disponível e, em fallback, a configuração local.</p>
  <div class="flex" style="gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <label>Valor <a href="#" class="help-hint" data-help="amount" title="Ajuda sobre valor"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-md" id="u_amount" type="text" placeholder="R$ 0,00" />
    </label>
    <label>BIN (6 dígitos) <a href="#" class="help-hint" data-help="bin" title="Ajuda sobre BIN"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_bin" type="text" maxlength="6" placeholder="411111" />
    </label>
    <label>Paga o juro? <a href="#" class="help-hint" data-help="payer" title="Ajuda sobre quem paga o juro"><i class="bi bi-question-circle"></i></a><br>
      <select class="form-select w-md" id="u_payer">
        <option value="user">Comprador</option>
        <option value="merchant">Vendedor</option>
      </select>
    </label>
    <label id="u_free_wrap" style="display:none;">Parcelas sem juros <a href="#" class="help-hint" data-help="free" title="Ajuda sobre parcelas sem juros"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_free" type="number" min="1" value="1" />
    </label>
    <label>Máx. parcelas (opcional) <a href="#" class="help-hint" data-help="max" title="Ajuda sobre máximo de parcelas"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_max" type="number" min="1" placeholder="12" />
    </label>
    <label>Juros (%/mês, opcional) <a href="#" class="help-hint" data-help="rate" title="Ajuda sobre juros"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_rate" type="text" placeholder="ex.: 1,99" />
    </label>
    <label>Parcela mínima (opcional) <a href="#" class="help-hint" data-help="min" title="Ajuda sobre parcela mínima"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_min" type="text" placeholder="R$ 0,00" />
    </label>
    <label>Taxa provedor (% sobre total, opcional) <a href="#" class="help-hint" data-help="fee_percent" title="Ajuda sobre taxa percentual"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_fee_percent" type="text" placeholder="ex.: 2,99" />
    </label>
    <label>Taxa fixa (opcional) <a href="#" class="help-hint" data-help="fee_fixed" title="Ajuda sobre taxa fixa"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_fee_fixed" type="text" placeholder="R$ 0,00" />
    </label>
    <label>Taxa de antecipação (%/mês, opcional) <a href="#" class="help-hint" data-help="anticip" title="Ajuda sobre antecipação"><i class="bi bi-question-circle"></i></a><br>
      <input class="form-control w-sm" id="u_anticip" type="text" placeholder="ex.: 2,50" />
    </label>
    <button class="btn" type="button" id="u_go">Calcular</button>
  </div>
  <div id="u_out" class="muted" style="margin-top:10px;white-space:normal"></div>
</div>

<script>
(function(){
  function digitsOnly(str){ return String(str||'').replace(/\D/g,''); }
  function formatBRLFromDigits(d){
    d = digitsOnly(d);
    if (d === '') d = '0';
    d = d.replace(/^0+/, '');
    if (d === '') d = '0';
    // garante pelo menos 2 casas decimais
    if (d.length === 1) d = '0' + d;
    var intPart = d.slice(0, -2) || '0';
    var decPart = d.slice(-2);
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return 'R$ ' + intPart + ',' + decPart;
  }
  function formatBRL(cents){
    var v = (cents||0)/100;
    return v.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
  }
  function centsFromMasked(str){
    var d = digitsOnly(str);
    if (d === '') return 0;
    return parseInt(d, 10);
  }
  function maskMoneyInput(inp){
    function render(){ inp.value = formatBRLFromDigits(inp.value); }
    inp.addEventListener('focus', function(){ if (!digitsOnly(inp.value)) inp.value = 'R$ 0,00'; setTimeout(render,0); });
    inp.addEventListener('blur', function(){ if (!digitsOnly(inp.value)) inp.value = 'R$ 0,00'; });
    inp.addEventListener('input', function(){ setTimeout(render, 0); });
    // init
    if (!digitsOnly(inp.value)) inp.value = 'R$ 0,00'; else render();
  }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]); }); }
  function parsePercent(str){ if(!str) return 0; var s=String(str).replace('%','').replace(',','.'); var n=parseFloat(s); return isNaN(n)?0:n; }
  function formatPercentFromDigits(d){
    d = digitsOnly(d);
    if (d === '') d = '0';
    if (d.length === 1) d = '0' + d;
    var intRaw = d.slice(0, -2);
    var decPart = d.slice(-2);
    var intPart = (intRaw || '0').replace(/^0+/, '');
    if (intPart === '') intPart = '0';
    return intPart + ',' + decPart;
  }
  function maskPercentInput(inp){
    function render(){ inp.value = formatPercentFromDigits(inp.value); try{ inp.setSelectionRange(inp.value.length, inp.value.length);}catch(e){} }
    inp.addEventListener('focus', function(){ if (!digitsOnly(inp.value)) inp.value = '0,00'; setTimeout(render,0); });
    inp.addEventListener('blur', function(){ if (!digitsOnly(inp.value)) inp.value = '0,00'; else render(); });
    inp.addEventListener('input', function(){ setTimeout(render,0); });
    if (!digitsOnly(inp.value)) inp.value = '0,00'; else render();
  }
  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('u_go');
    var out = document.getElementById('u_out');
    var inpAmt = document.getElementById('u_amount');
    var inpBin = document.getElementById('u_bin');
    var inpMax = document.getElementById('u_max');
    var inpRate = document.getElementById('u_rate');
    var inpMin = document.getElementById('u_min');
    var inpFeeP = document.getElementById('u_fee_percent');
    var inpFeeF = document.getElementById('u_fee_fixed');
    var inpAnt = document.getElementById('u_anticip');
    var selPayer = document.getElementById('u_payer');
    var freeWrap = document.getElementById('u_free_wrap');
    var inpFree = document.getElementById('u_free');
    if (!btn || !out || !inpAmt || !inpBin) return;
    maskMoneyInput(inpAmt);
    if (inpMin) maskMoneyInput(inpMin);
    if (inpFeeF) maskMoneyInput(inpFeeF);
    if (inpRate) maskPercentInput(inpRate);
    if (inpFeeP) maskPercentInput(inpFeeP);
    if (inpAnt) maskPercentInput(inpAnt);
    function toggleFree(){
      var show = (selPayer && selPayer.value === 'merchant');
      if (freeWrap) freeWrap.style.display = show ? '' : 'none';
      if (!show && inpFree) inpFree.value = '1';
    }
    if (selPayer) selPayer.addEventListener('change', toggleFree);
    toggleFree();
    // Restaurar últimas escolhas
    try{
      var saved = JSON.parse(localStorage.getItem('calcSettings')||'null');
      if (saved){
        if (selPayer && saved.payer) selPayer.value = saved.payer;
        if (inpFree && saved.free) inpFree.value = saved.free;
        if (inpMax && saved.max) inpMax.value = saved.max;
        if (inpRate && saved.rate) inpRate.value = saved.rate;
        if (inpMin && typeof saved.minc==='number') inpMin.value = formatBRL(saved.minc);
        if (inpFeeP && saved.feeP) inpFeeP.value = saved.feeP;
        if (inpFeeF && typeof saved.feeF==='number') inpFeeF.value = formatBRL(saved.feeF);
        if (inpAnt && saved.anticip) inpAnt.value = saved.anticip;
        toggleFree();
      }
    }catch(e){}
    // Help modal
    function ensureHelpModal(){
      var mod = document.getElementById('modalHelpCalc');
      if (mod) return mod;
      var html = ''+
      '<div class="modal fade" id="modalHelpCalc" tabindex="-1" aria-hidden="true">'+
      '<div class="modal-dialog"><div class="modal-content" style="background:var(--panel); color:var(--txt); border-color:var(--border)">'+
      '<div class="modal-header" style="border-color:var(--border)"><h5 class="modal-title">Ajuda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'+
      '<div class="modal-body"><div id="modalHelpCalcBody" class="muted"></div></div>'+
      '<div class="modal-footer" style="border-color:var(--border)"><button type="button" class="btn" data-bs-dismiss="modal">Fechar</button></div>'+
      '</div></div></div>';
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      document.body.appendChild(wrap.firstChild);
      return document.getElementById('modalHelpCalc');
    }
    var helpText = {
      amount: 'Valor total da compra/simulação. Informe em reais; a calculadora converte para centavos.',
      bin: 'Primeiros 6 dígitos do cartão. Usado para identificar a bandeira e regras específicas.',
      payer: 'Quem arca com os juros nas parcelas. Se for o vendedor, é possível definir um número de parcelas sem juros.',
      free: 'Número de parcelas sem juros quando o vendedor paga o juro (ex.: 1 para apenas à vista sem juros).',
      max: 'Limite máximo de parcelas a considerar no cálculo (ex.: 12).',
      rate: 'Taxa de juros mensal (% ao mês) para cálculo local quando a API não informar explicitamente.',
      min: 'Valor mínimo por parcela; parcelas abaixo deste valor são descartadas.',
      fee_percent: 'Taxa percentual do provedor aplicada sobre o total cobrado do comprador (ex.: MDR).',
      fee_fixed: 'Valor fixo descontado do vendedor além da taxa percentual (ex.: tarifa por transação).',
      anticip: 'Taxa mensal de antecipação dos recebíveis. Quando informada, calcula o valor líquido estimado se todas as parcelas forem antecipadas (valor presente dos recebíveis).'
    };
    document.querySelectorAll('.help-hint[data-help]').forEach(function(a){
      a.addEventListener('click', function(e){
        e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
        var key = this.getAttribute('data-help');
        var msg = helpText[key] || 'Sem ajuda disponível.';
        var mod = ensureHelpModal();
        var body = document.getElementById('modalHelpCalcBody');
        if (body) body.textContent = msg;
        if (window.bootstrap && window.bootstrap.Modal){
          var m = new bootstrap.Modal(mod); m.show();
        } else if (window.jQuery){
          jQuery(mod).modal('show');
        } else if (window.Swal){
          Swal.fire({text: msg, icon: 'info'});
        }
      });
    });
    btn.addEventListener('click', function(){
      var amount = centsFromMasked(inpAmt.value||'');
      var bin = (inpBin.value||'').replace(/\D/g,'').slice(0,6);
      var max = parseInt(inpMax.value||'0',10);
      var payer = (selPayer && (selPayer.value==='merchant'||selPayer.value==='user')) ? selPayer.value : 'user';
      var free = (payer === 'merchant' && inpFree) ? parseInt(inpFree.value||'1',10) : null;
      if (!amount || bin.length<6){ out.textContent='Informe um valor e BIN válidos.'; return; }
      var rate = (inpRate && inpRate.value) ? encodeURIComponent(inpRate.value.trim()) : '';
      var minc = (inpMin && inpMin.value) ? centsFromMasked(inpMin.value) : null;
      var url = '<?= $prefix ?>/utils/cards/quote?amount_cents='+amount+'&bin='+bin+(max?('&max_parcelas='+max):'')+'&payer='+encodeURIComponent(payer)+(free?('&free_installments='+free):'')+(rate?('&interest_rate='+rate):'')+(minc?('&min_installment_cents='+minc):'');
      out.innerHTML = '<span class="muted">Calculando...</span>';
      // Salvar preferências
      try{ localStorage.setItem('calcSettings', JSON.stringify({ payer:payer, free:free||1, max:max||'', rate:(inpRate&&inpRate.value)||'', minc:minc||0, feeP:(inpFeeP&&inpFeeP.value)||'', feeF:(inpFeeF?centsFromMasked(inpFeeF.value):0), anticip:(inpAnt&&inpAnt.value)||'' })); }catch(e){}
      fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(resp){
          if (resp.error){ out.textContent = 'Erro: ' + resp.error; return; }
          var html = [];
          html.push('<div class="flex" style="align-items:center; gap:10px; flex-wrap:wrap;">');
          if (resp.brand_icon){
            var icon = document.createElement('img');
            var iconPath = resp.brand_icon; var sep = iconPath.indexOf('?') === -1 ? '?' : '&';
            icon.src = iconPath + sep + 'v=' + Date.now();
            icon.style.height = '55px'; icon.alt = (resp.brand||'bandeira');
            html.push('<img src="'+esc(icon.src)+'" alt="'+esc(icon.alt)+'" style="height:55px;vertical-align:middle;"/>');
          }
          html.push('<div>');
          html.push('<div><strong>Bandeira:</strong> ' + esc(resp.brand || '-') + '</div>');
          var payerLbl = (resp.interest_payer==='merchant') ? 'Vendedor' : 'Comprador';
          html.push('<div><strong>Quem paga o juro:</strong> ' + payerLbl + '</div>');
          if (resp.interest_payer==='merchant' && resp.free_installments && resp.free_installments>1){
            html.push('<div><strong>Parcelas sem juros:</strong> ' + resp.free_installments + '</div>');
          }
          html.push('</div>');
          html.push('</div>');

          if (Array.isArray(resp.installments) && resp.installments.length){
            html.push('<div class="space"></div>');
            var addAnt = !!(inpAnt && parsePercent(inpAnt.value) > 0);
            html.push('<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Parcelas</th><th>Valor/Parcela</th><th>Total (comprador)</th><th>Juros</th><th>Receb. vendedor (líquido)</th>' + (addAnt?'<th>Receb. c/ antecipação</th>':'') + '</tr></thead><tbody>');
            resp.installments.forEach(function(p){
              var n = p.number || 1;
              var per = p.amount_formatted || formatBRL(p.amount_cents||0);
              var tot = p.total_formatted || formatBRL(p.total_cents||0);
              var j = (p.is_interest_free ? '0%' : (typeof p.interest_rate==='number' ? (p.interest_rate.toFixed(2)+'%/mês') : '-'));
              var totCents = (typeof p.total_cents==='number') ? p.total_cents : centsFromMasked(tot);
              var feePct = parsePercent(inpFeeP && inpFeeP.value);
              var feeFixed = inpFeeF ? centsFromMasked(inpFeeF.value||'') : 0;
              var net = Math.max(0, Math.round(totCents * (1 - (feePct/100)) - feeFixed));
              var row = '<tr><td>'+n+'x</td><td>'+esc(per)+'</td><td>'+esc(tot)+'</td><td>'+esc(j)+'</td><td>'+esc(formatBRL(net))+'</td>';
              if (addAnt){
                var r = parsePercent(inpAnt.value)/100;
                var perCents = (typeof p.amount_cents==='number') ? p.amount_cents : centsFromMasked(per);
                var pv = 0;
                for (var i=1;i<=n;i++){
                  pv += perCents * (1 - (feePct/100)) / Math.pow(1+r, i);
                }
                pv = Math.max(0, Math.round(pv - feeFixed));
                row += '<td>'+esc(formatBRL(pv))+'</td>';
              }
              row += '</tr>';
              html.push(row);
            });
            html.push('</tbody></table></div>');
          } else {
            html.push('<div class="alert">Nenhuma parcela disponível para este valor.</div>');
          }
          out.innerHTML = html.join('');
        })
        .catch(function(e){ out.textContent = 'Falha: ' + e; });
    });
  });
})();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/layout.php';
