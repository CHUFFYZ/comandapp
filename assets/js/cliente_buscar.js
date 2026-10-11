// Búsqueda de clientes existentes (nombre o teléfono, sin distinguir acentos) y opciones de regalo
(function () {
  const form = document.getElementById('formPedido'); if (!form) return;
  const chk = document.getElementById('chkExistente'), box = document.getElementById('buscarBox'),
        inp = document.getElementById('buscarCliente'), res = document.getElementById('resultadosCliente'),
        idc = document.getElementById('idCliente'), info = document.getElementById('clienteInfo'),
        tel = form.elements.cliente_tel, nom = form.elements.cliente_nombre, url = form.dataset.buscar;
  const consulta = q => fetch(url + '?' + q, { credentials: 'same-origin' }).then(r => r.json()).then(j => j.clientes || []).catch(() => []);
  const el = (t, c, x) => { const n = document.createElement(t); if (c) n.className = c; if (x) n.textContent = x; return n; };

  function pintar(c) {
    info.replaceChildren(); if (!c) return;
    info.append(el('div', 'small text-muted', 'Cliente: ' + c.nombre + (c.promo ? ' · visitas ' + c.visitas + '/' + c.requeridas +
      (c.minimo > 0 ? ' (consumo mínimo $' + c.minimo.toFixed(2) + ')' : '') : '')));
    if (c.regalo && form.dataset.modo !== 'editar') {   // al editar, el regalo se cambia en su propio selector
      const caja = el('div', 'regalo-check');
      caja.append(el('div', 'mb-1', '🎁 Este cliente tiene un regalo. Elige una opción:'));
      [{ id: 0, txt: 'Sin regalo por ahora' }].concat(c.opciones).forEach(o => {
        const l = el('label'), r = document.createElement('input');
        r.type = 'radio'; r.name = 'regalo_opcion'; r.value = o.id; r.className = 'form-check-input me-2'; r.checked = o.id === 0;
        l.append(r, document.createTextNode(o.txt)); caja.append(l);
      });
      info.append(caja);
    }
  }
  function elegir(c) {
    idc.value = c.id; tel.value = c.telefono || ''; nom.value = c.nombre;
    res.replaceChildren(); if (inp) inp.value = ''; pintar(c);
  }
  if (chk) chk.addEventListener('change', () => { box.hidden = !chk.checked; if (chk.checked) inp.focus(); });
  if (inp) {
    let t;
    inp.addEventListener('input', () => {
      clearTimeout(t); const q = inp.value.trim();
      if (q.length < 2) { res.replaceChildren(); return; }
      t = setTimeout(() => consulta('q=' + encodeURIComponent(q)).then(lista => {
        res.replaceChildren();
        if (!lista.length) { res.append(el('div', 'list-group-item text-muted', 'Sin resultados')); return; }
        lista.forEach(c => {
          const b = el('button', 'list-group-item list-group-item-action', c.nombre + (c.telefono ? ' · ' + c.telefono : '') + (c.regalo ? ' · 🎁' : ''));
          b.type = 'button'; b.addEventListener('click', () => elegir(c)); res.append(b);
        });
      }), 250);
    });
  }
  [tel, nom].forEach(i => i.addEventListener('input', () => { if (idc.value) { idc.value = ''; info.replaceChildren(); } }));
  tel.addEventListener('change', () => {   // teléfono de un cliente ya registrado: se reconoce solo
    const v = tel.value.trim();
    if (v && !idc.value && !tel.readOnly) consulta('tel=' + encodeURIComponent(v)).then(l => { if (l[0]) elegir(l[0]); });
  });
})();
