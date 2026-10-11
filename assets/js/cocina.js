// Panel de cocina: refresco automático y cierre de comandas
(function () {
  const box = document.getElementById('comandas'); if (!box) return;
  const load = () => fetch(box.dataset.url, { credentials: 'same-origin' }).then(r => {
      if (r.status === 401 || r.redirected) { location.reload(); return null; } return r.text(); })
    .then(h => { if (h !== null) box.innerHTML = h; }).catch(() => {});
  box.addEventListener('click', e => {
    const b = e.target.closest('.js-terminar'); if (!b) return;
    b.disabled = true;
    const fd = new FormData(); fd.append('id', b.dataset.id); fd.append('csrf', box.dataset.csrf);
    fetch(box.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(load);
  });
  load(); setInterval(load, 10000);
})();
