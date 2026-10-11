// Sombra en el header al hacer scroll y cierre del menú móvil
(function () {
  const header = document.querySelector('.site-header');
  if (!header) return;
  const toggle = () => header.classList.toggle('scrolled', window.scrollY > 10);
  toggle();
  window.addEventListener('scroll', toggle, { passive: true });

  const menu = document.getElementById('menuPrincipal');
  document.querySelectorAll('#menuPrincipal .nav-link').forEach(link =>
    link.addEventListener('click', () => {
      if (menu.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(menu).hide();
    })
  );
})();
