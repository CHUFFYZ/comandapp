<?php /* Vars: $id (id_pedido). Abre el ticket en PDF en otra pestaña para imprimirlo. */ ?>
<a class="btn btn-sm btn-outline-secondary" href="<?= url('pages/ticket.php?id=' . (int)$id) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Ticket</a>
