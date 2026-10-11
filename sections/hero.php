<section id="inicio" class="hero">
  <div class="container">
    <div class="row align-items-center gy-5">
      <div class="col-lg-6">
        <h1>La comanda llega a cocina antes de que el mesero cruce el salón.</h1>
        <p class="lead-ca">Gestiona pedidos, cocina e inventario de uno o varios locales desde cualquier dispositivo. Sin papeles, sin gritos y sin sorpresas al cierre del día.</p>
        <div class="d-flex flex-wrap gap-2">
          <?php component('button', ['texto' => 'Probar el sistema', 'href' => url('pages/registro.php'), 'variante' => 'primary', 'icono' => 'arrow-right']); ?>
          <?php component('button', ['texto' => 'Ver precios', 'href' => '#precios', 'variante' => 'dark']); ?>
        </div>
      </div>
      <div class="col-lg-6">
        <!-- Comanda de ejemplo: refleja los estados reales del sistema -->
        <div class="ticket" aria-label="Ejemplo de comanda">
          <div class="ticket-head"><strong>Pedido #1042</strong><span>Mesa 6 · Sucursal Centro</span></div>
          <ul class="ticket-items">
            <li><span>2 × Tacos al pastor</span><b>$8.00</b></li>
            <li><span>1 × Agua de jamaica</span><b>$2.50</b></li>
            <li><span>1 × Flan</span><b>$3.00</b></li>
          </ul>
          <div class="ticket-foot"><span>Total</span><b>$13.50</b></div>
          <div class="ticket-estados">
            <span class="estado estado-proceso">en proceso</span>
            <span class="estado estado-listo">terminado</span>
            <span class="estado estado-cancel">cancelado</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
