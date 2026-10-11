// Formulario de pedido: total, resumen en vivo y contador por categoría
(function () {
  const form = document.getElementById('formPedido'); if (!form) return;
  const inputs = [...form.querySelectorAll('.qty')];
  const total = document.getElementById('total'), btn = document.getElementById('enviar');
  const lista = document.getElementById('resumen'), vacio = document.getElementById('resumenVacio');
  const num = i => { const max = parseInt(i.max || '0', 10); let v = Math.max(0, parseInt(i.value || '0', 10) || 0);
    if (v > max) { v = max; i.value = max; } return v; };

  function calc() {
    let t = 0, piezas = 0; lista.replaceChildren();
    inputs.forEach(i => {
      const v = num(i); if (!v) return;
      const sub = v * parseFloat(i.dataset.precio); t += sub; piezas += v;
      const li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('strong');
      a.textContent = v + ' × ' + i.dataset.nombre; b.textContent = '$' + sub.toFixed(2);
      li.append(a, b); lista.append(li);
    });
    vacio.hidden = piezas > 0; total.textContent = '$' + t.toFixed(2); btn.disabled = piezas === 0;
    form.querySelectorAll('details.cat').forEach(d => {
      let n = 0; d.querySelectorAll('.qty').forEach(i => n += num(i));
      const c = d.querySelector('.cat-count'); c.textContent = n;
      c.classList.toggle('text-bg-warning', n > 0); c.classList.toggle('text-bg-secondary', n === 0);
    });
  }
  inputs.forEach(i => i.addEventListener('input', calc)); calc();
})();
