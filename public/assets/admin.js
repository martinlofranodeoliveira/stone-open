// Admin theme toggle (persist in localStorage). Default = light
(function(){
  var storageKey = 'adminTheme';
  function apply(theme){
    if(!theme || theme==='auto'){
      document.documentElement.setAttribute('data-theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', theme);
    }
  }
  function current(){
    return localStorage.getItem(storageKey) || 'light';
  }
  function updateToggle(){
    var t = document.documentElement.getAttribute('data-theme') || 'light';
    var label = document.getElementById('themeLabel');
    var icon = document.getElementById('themeIcon');
    if(label){ label.textContent = t === 'light' ? 'Tema: Claro' : 'Tema: Escuro'; }
    if(icon){
      icon.className = 'bi ' + (t==='light' ? 'bi-sun-fill' : 'bi-moon-stars-fill') + ' me-1';
    }
  }
  function init(){
    apply(current());
    updateToggle();
    var btn = document.getElementById('themeToggle');
    if(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var t = document.documentElement.getAttribute('data-theme') || 'light';
        var next = t === 'light' ? 'dark' : 'light';
        localStorage.setItem(storageKey, next);
        apply(next);
        updateToggle();
      });
    }

    // Add copy buttons to help code blocks
    try{
      var pres = document.querySelectorAll('.panel.help pre');
      pres.forEach(function(pre){
        // avoid duplicates
        if (pre.previousElementSibling && pre.previousElementSibling.classList && pre.previousElementSibling.classList.contains('copy-btn')) return;
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn secondary copy-btn';
        b.textContent = 'Copiar';
        b.style.marginBottom = '6px';
        b.addEventListener('click', function(){
          var txt = pre.innerText || pre.textContent || '';
          var done = function(){ b.textContent = 'Copiado!'; b.classList.add('ok'); setTimeout(function(){ b.textContent = 'Copiar'; b.classList.remove('ok'); }, 1200); };
          if (navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(txt).then(done).catch(function(){ fallbackCopy(txt, done); });
          } else {
            fallbackCopy(txt, done);
          }
        });
        pre.parentNode.insertBefore(b, pre);
      });
      function fallbackCopy(text, cb){
        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta); if (cb) cb();
      }
    }catch(e){ /* ignore */ }

    // SweetAlert2 confirmation for elements with [data-confirm]
    document.body.addEventListener('click', function(ev){
      var el = ev.target.closest('[data-confirm]');
      if (!el) return;
      var msg = el.getAttribute('data-confirm') || 'Confirmar?';
      var form = el.closest('form');
      if (!form) return; // only intercept form buttons/links
      ev.preventDefault();
      if (window.Swal) {
        Swal.fire({ text: msg, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' }).then(function(res){
          if (res.isConfirmed) form.submit();
        });
      } else {
        // fallback: submit directly (no alert)
        form.submit();
      }
    });

    // Contextual help registry and auto-injection
    document.body.addEventListener('click', function(ev){
      var el = ev.target.closest('.help-hint');
      if (!el) return;
      ev.preventDefault();
      var key = el.getAttribute('data-help') || '';
      var title = 'Ajuda';
      var html = '';
      var help = {
        'billing_cycles': {
          title: 'Ciclos de Cobrança',
          html: '<p>Quantidade de vezes que a cobrança do plano será executada e, após isso, a assinatura é encerrada automaticamente.</p>' +
                '<ul style="margin-left:1rem">' +
                '<li><b>1</b>: cobra uma única vez (padrão).</li>' +
                '<li><b>3</b>: cobra por 3 ciclos (ex.: 3 meses se intervalo for mensal).</li>' +
                '<li><b>Indefinido</b>: não suportado por padrão aqui; ajuste manualmente conforme necessidade.</li>' +
                '</ul>'
        },
        'interval_unit': {
          title: 'Intervalo',
          html: '<p>Período básico da recorrência.</p><ul style="margin-left:1rem">' +
                '<li><b>Dia</b>, <b>Semana</b>, <b>Mês</b>, <b>Ano</b></li>' +
                '<li>Ex.: Mês com Quantidade = 1 → cobranças mensais.</li>' +
                '</ul>'
        },
        'interval_count': {
          title: 'Qtd Intervalo',
          html: '<p>Número de unidades do intervalo por ciclo.</p>' +
                '<p>Ex.: Intervalo = Mês, Quantidade = 3 → a cada 3 meses.</p>'
        },
        'billing_type': {
          title: 'Tipo de Cobrança',
          html: '<p>Define quando a cobrança ocorre.</p><ul style="margin-left:1rem">' +
                '<li><b>Pré-pago</b>: cobra no início do ciclo.</li>' +
                '<li><b>Pós-pago</b>: cobra no fim do ciclo.</li>' +
                '<li><b>Dia exato</b>: cobra sempre em um dia específico (exige Dia da Cobrança).</li>' +
                '</ul>'
        },
        'billing_day': {
          title: 'Dia da Cobrança',
          html: '<p>Usado apenas com "Dia exato". Valor 1..31. Meses com menos dias são ajustados automaticamente (ex.: 31 em fevereiro → 28/29).</p>'
        },
        'installments': {
          title: 'Parcelas',
          html: '<p>Lista de parcelas permitidas para cartão. Use valores separados por vírgula, ex.: 1,2,3,6,12.</p>' +
                '<p>Serve como configuração/limite; a oferta efetiva depende das regras do meio de pagamento.</p>'
        },
        // Sites (admin)
        'site_name': {
          title: 'Nome do Site',
          html: '<p>Identificação amigável para uso no Admin e logs. Não impacta integrações.</p>'
        },
        'domain': {
          title: 'Domínio',
          html: '<p>Domínio principal do seu site (sem protocolo). Ex.: <code>merchant.example.invalid</code>. Usado em referências e validações simples.</p>'
        },
        'callback_url': {
          title: 'Callback URL',
          html: '<p>URL pública do seu sistema que receberá os forwards de eventos (ex.: mudanças de status de pagamento/assinatura).</p>' +
                '<p>Ex.: <code>https://merchant.example.invalid/webhooks/stone</code>. Garanta que aceite POST com JSON e valide o HMAC (FORWARD_SECRET).</p>'
        },
        'site_status': {
          title: 'Status do Site',
          html: '<ul style="margin-left:1rem">' +
                '<li><b>Ativo</b>: funcionamento normal.</li>' +
                '<li><b>Suspenso</b>: uso temporariamente bloqueado (manutenção).</li>' +
                '<li><b>Desativado</b>: desligado (não processa operações).</li>' +
                '</ul>'
        },
        'interest_payer': {
          title: 'Quem paga o juro?',
          html: '<p>Afeta a simulação de parcelas retornada pelo Hub.</p>' +
                '<ul style="margin-left:1rem">' +
                '<li><b>Usuário</b>: comprador arca com juros (parcelas com juros).</li>' +
                '<li><b>Vendedor</b>: o site paga o juro até um limite de parcelas sem juro.</li>' +
                '</ul>'
        },
        'installments_free': {
          title: 'Parcelas sem juro',
          html: '<p>Válido quando o vendedor paga o juro. Número máximo de parcelas isentas de juros para o cliente.</p>'
        },
        'rate_limit': {
          title: 'Rate limit/minuto',
          html: '<p>Limite de requisições por minuto nas rotas privadas para este site.</p>' +
                '<p>Se excedido, o Hub retorna 429 temporariamente.</p>'
        },
        // Plan fields (aliases)
        'site_id': { title:'Site', html:'<p>Site ao qual o plano pertence.</p>' },
        'name': { title:'Nome', html:'<p>Nome público do plano (aparece em listagens).</p>' },
        'code': { title:'Código', html:'<p>Identificador único do seu plano. Útil para integrações e buscas.</p>' },
        'description': { title:'Descrição', html:'<p>Descrição breve do plano (opcional).</p>' },
        'amount_cents': { title:'Valor (centavos)', html:'<p>Preço total do plano/itens em centavos. Ex.: R$ 189,90 → 18990.</p>' },
        'currency': { title:'Moeda', html:'<p>Moeda no formato de 3 letras (ISO). Ex.: BRL.</p>' },
        'statement_descriptor': { title:'Descrição na Fatura', html:'<p>Texto curto (≤13) que pode aparecer na fatura/cartão do cliente.</p>' },
        'trial_period_days': { title:'Dias de Teste', html:'<p>Período de teste gratuito em dias (opcional). Ex.: 14.</p>' },
        'payment_methods': { title:'Métodos de Pagamento', html:'<p>Selecione os meios aceitos para o plano: cartão e/ou boleto.</p>' },
        // Users
        'user_name': { title:'Nome', html:'<p>Nome do usuário administrador.</p>' },
        'user_email': { title:'Email', html:'<p>Email de acesso do usuário/admin.</p>' },
        'user_role': { title:'Função', html:'<p>Permissão do usuário: admin (pleno) ou operator (restrito).</p>' },
        'user_status': { title:'Status', html:'<p>Ativo ou Desativado.</p>' },
        'user_password': { title:'Senha', html:'<p>Defina uma senha forte (mínimo 8 caracteres). Na edição, deixe em branco para manter a atual.</p>' }
      };
      if (help[key]) { title = help[key].title; html = help[key].html; }
      if (window.Swal) {
        Swal.fire({ title: title, html: html, icon: 'info', confirmButtonText: 'Entendi' });
      } else {
        alert(title + '\n\n' + (el.getAttribute('title') || ''));
      }
    });

    // Auto-inject help icons based on input names
    try{
      var aliases = {
        // map input name -> help key
        'name': 'name',
        'code': 'code',
        'description': 'description',
        'amount_cents': 'amount_cents',
        'currency': 'currency',
        'interval_unit': 'interval_unit',
        'interval_count': 'interval_count',
        'billing_cycles': 'billing_cycles',
        'billing_type': 'billing_type',
        'billing_day': 'billing_day',
        'installments': 'installments',
        'statement_descriptor': 'statement_descriptor',
        'trial_period_days': 'trial_period_days',
        'payment_methods[]': 'payment_methods',
        'site_id': 'site_id',
        'domain': 'domain',
        'callback_url': 'callback_url',
        'status': 'site_status',
        'interest_payer': 'interest_payer',
        'installments_free': 'installments_free',
        'rate_limit_per_minute': 'rate_limit',
        // users
        'role': 'user_role',
        'user_status': 'user_status',
        'email': 'user_email',
        'password': 'user_password',
      };
      var labels = document.querySelectorAll('label');
      labels.forEach(function(label){
        if (label.querySelector('.help-hint')) return;
        var input = label.querySelector('input, select, textarea');
        if (!input) return;
        var nm = (input.getAttribute('name')||'').trim();
        if (!nm) return;
        var key = aliases[nm];
        if (!key || !help[key]) return;
        var a = document.createElement('a');
        a.href = '#';
        a.className = 'help-hint';
        a.setAttribute('data-help', key);
        a.setAttribute('title', 'Ajuda');
        a.innerHTML = '<i class="bi bi-question-circle"></i>';
        var br = label.querySelector('br');
        if (br) label.insertBefore(a, br);
        else label.appendChild(a);
      });
    }catch(e){ /* ignore */ }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  // early apply to reduce FOUC
  try{ apply(localStorage.getItem(storageKey) || 'light'); }catch(_){ apply('light'); }
})();
