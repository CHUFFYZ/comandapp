<?php /* Vars: $porCat, $accion ('crear'|'editar'), $idPedido, $cli ['id','tel','nombre'], $bloqCli, $regalos,
         $opcionesRegalo (edición: opciones vigentes), $regaloActual ('keep'|'0'|id_opcion) */
$editar = $accion === 'editar'; $bloqCli = !empty($bloqCli); $regalos = $regalos ?? [];
$opcionesRegalo = $opcionesRegalo ?? []; $regaloActual = (string)($regaloActual ?? '0'); ?>
<form method="post" id="formPedido" data-buscar="clientes_buscar.php" data-modo="<?= e($accion) ?>"><?= csrf_field() ?>
  <input type="hidden" name="accion" value="<?= e($accion) ?>">
  <?php if (!empty($idPedido)): ?><input type="hidden" name="id" value="<?= (int)$idPedido ?>"><?php endif; ?>

  <?php foreach ($porCat as $cat => $prods): ?>
    <details class="cat">
      <summary><span><?= e($cat) ?></span><span class="badge text-bg-secondary cat-count">0</span></summary>
      <div class="row g-2 p-2">
      <?php foreach ($prods as $p): $max = (int)$p['stock'] + (int)$p['qty_actual']; ?>
        <div class="col-12 col-md-6 col-xl-4"><div class="prod <?= $max <= 0 ? 'prod-agotado' : '' ?>">
          <div><strong><?= e($p['nombre']) ?></strong><br><small class="text-muted"><?= money($p['precio_venta']) ?> · <?= $max <= 0 ? 'Agotado' : $max . ' disp.' ?></small></div>
          <input class="form-control qty" type="number" inputmode="numeric" min="0" max="<?= $max ?>" value="<?= (int)$p['qty_actual'] ?>"
                 name="qty[<?= (int)$p['id_producto'] ?>]" data-precio="<?= e($p['precio_venta']) ?>" data-nombre="<?= e($p['nombre']) ?>"
                 <?= $max <= 0 ? 'disabled' : '' ?> aria-label="Cantidad de <?= e($p['nombre']) ?>">
        </div></div>
      <?php endforeach; ?>
      </div>
    </details>
  <?php endforeach; if (!$porCat): ?><div class="alert alert-info">Tu gerente aún no ha cargado productos.</div><?php endif; ?>

  <div class="panel mt-3"><h2>Cliente (opcional)</h2>
    <input type="hidden" name="id_cliente" id="idCliente" value="<?= e($cli['id'] ?? '') ?>">
    <?php if (!$bloqCli): ?>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="chkExistente"><label class="form-check-label" for="chkExistente">Cliente existente: buscar por nombre o teléfono</label></div>
      <div id="buscarBox" class="mb-3" hidden>
        <input class="form-control" type="search" id="buscarCliente" placeholder="Escribe nombre o teléfono…" autocomplete="off">
        <div id="resultadosCliente" class="list-group mt-1"></div>
      </div>
    <?php endif; ?>
    <div class="row g-2">
      <div class="col-md-4"><input class="form-control" name="cliente_tel" placeholder="Teléfono (opcional)" maxlength="20" value="<?= e($cli['tel'] ?? '') ?>" <?= $bloqCli ? 'readonly' : '' ?>></div>
      <div class="col-md-4"><input class="form-control" name="cliente_nombre" placeholder="Nombre del cliente" maxlength="100" value="<?= e($cli['nombre'] ?? '') ?>" <?= $bloqCli ? 'readonly' : '' ?>></div>
    </div>
    <div id="clienteInfo" class="mt-2"></div>
    <?php if ($bloqCli): ?><p class="small text-muted mt-2 mb-0">Este pedido tiene un regalo aplicado, por eso el cliente no se puede cambiar.</p><?php endif; ?>
  </div>

  <?php if ($editar && $opcionesRegalo): ?>
    <div class="panel mt-3"><h2>🎁 Regalo de lealtad</h2>
      <select class="form-select" name="regalo_opcion" aria-label="Regalo de lealtad">
        <?php if ($regaloActual === 'keep'): ?><option value="keep" selected>Mantener el regalo actual</option><?php endif; ?>
        <option value="0" <?= $regaloActual === '0' ? 'selected' : '' ?>>Sin regalo</option>
        <?php foreach ($opcionesRegalo as $o): ?><option value="<?= (int)$o['id_opcion'] ?>" <?= $regaloActual === (string)$o['id_opcion'] ? 'selected' : '' ?>><?= (int)$o['cantidad'] ?> × <?= e($o['producto']) ?></option><?php endforeach; ?>
      </select>
      <p class="small text-muted mt-2 mb-0">Puedes cambiar el regalo por otra opción disponible o quitarlo.</p>
    </div>
  <?php endif; ?>

  <div class="panel mt-3"><h2>Resumen del pedido</h2>
    <p id="resumenVacio" class="text-muted mb-0">Aún no has agregado productos.</p>
    <ul id="resumen" class="resumen"></ul>
    <?php if ($regalos && !$opcionesRegalo): ?><ul class="resumen"><?php foreach ($regalos as $g): ?><li class="regalo"><span>🎁 Regalo: <?= (int)$g['cantidad'] ?> × <?= e($g['nombre']) ?></span><strong>$0.00</strong></li><?php endforeach; ?></ul><?php endif; ?>
  </div>

  <div class="barra-total">
    <span>Total: <strong id="total">$0.00</strong></span>
    <span class="d-flex gap-2">
      <?php if ($editar): ?><a class="btn btn-ca btn-ca-ghost" href="pedidos.php">Volver</a><?php endif; ?>
      <button class="btn btn-ca btn-ca-primary" id="enviar" disabled><?= $editar ? 'Guardar cambios' : 'Enviar a cocina' ?></button>
    </span>
  </div>
</form>
<script src="<?= asset('js/pedido.js') ?>"></script>
<script src="<?= asset('js/cliente_buscar.js') ?>"></script>
