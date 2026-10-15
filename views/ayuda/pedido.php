<h3>¿Qué es un pedido?</h3>
<p>
    Un pedido es la solicitud de un cliente. Se carga con los artículos que va a llevar,
    la cantidad, el empaque (unidad o caja) y el precio según la categoría del cliente.
    El stock <strong>NO se descuenta al crear el pedido</strong>, sino al facturarlo.
</p>

<h3>Estados del pedido</h3>
<ul>
    <li><strong>Recibido</strong> — recién cargado. Vendedor y productor pueden editarlo.</li>
    <li><strong>Procesando</strong> — en revisión o armado. Aún editable por vendedor y productor.</li>
    <li><strong>Preparado</strong> — listo para facturar. Solo Admin/Dev pueden modificarlo.</li>
    <li><strong>Facturado</strong> — ya tiene comprobante. Solo Admin/Dev con acciones limitadas.</li>
    <li><strong>Entregado</strong> — cerrado.</li>
    <li><strong>Anulado</strong> — eliminado lógicamente.</li>
</ul>

<h3>Cómo cargar un pedido</h3>
<ol>
    <li>Elegí el <strong>cliente</strong>. El sistema cargará automáticamente su categoría.</li>
    <li>Agregá los artículos con su <strong>cantidad</strong> y el <strong>empaque</strong> (unidad o caja).</li>
    <li>El <strong>precio</strong> se completa desde la lista de precios según la categoría del cliente. Podés ajustarlo a mano si hace falta.</li>
    <li>Aplicá descuentos por línea si querés, o un descuento general al final.</li>
    <li>Escribí notas visibles (para todos) y notas internas (solo admin/dev/productor).</li>
    <li>Guardá.</li>
</ol>

<div class="ayuda-tip">
    <strong>Empaques:</strong> si el artículo tiene empaque configurado (por ejemplo "Caja x 6"),
    podés elegirlo y la cantidad se multiplica automáticamente. Ej: 2 cajas x 6 = 12 unidades.
</div>

<h3>Modificaciones del vendedor</h3>
<p>
    Si el vendedor modifica un pedido (en estado Recibido o Procesando), el sistema
    marca el pedido con un <strong>punto dorado</strong> en el listado y un aviso en el detalle.
    El productor y el admin verán la marca hasta que abran el pedido por primera vez.
</p>

<h3>Permisos por rol</h3>
<ul>
    <li><strong>Admin/Dev:</strong> acceso total. Pueden editar hasta antes de facturar.</li>
    <li><strong>Vendedor:</strong> solo pedidos de sus clientes. Puede crear y editar hasta Procesando.</li>
    <li><strong>Productor:</strong> puede ver todos los pedidos <strong>sin precios</strong>. Puede editar hasta Procesando (solo cantidades y artículos).</li>
</ul>

<h3>Impresión</h3>
<p>
    Se puede imprimir la orden con o sin precios. El productor siempre imprime sin precios.
    Admin y Dev pueden elegir ambas variantes.
</p>