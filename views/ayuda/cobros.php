<h3>¿Qué es un cobro?</h3>
<p>
    Un cobro es el registro de que un cliente <strong>te pagó</strong>. Puede ser contra una
    factura específica o contra varias facturas a la vez, con uno o varios medios de pago
    (efectivo + transferencia, por ejemplo).
</p>

<h3>Cómo registrar un cobro</h3>
<ol>
    <li>Elegí el <strong>cliente</strong>. Se carga automáticamente su saldo actual y la lista de facturas pendientes.</li>
    <li>Marcá las <strong>facturas</strong> que estás cobrando y ajustá el importe a imputar a cada una.</li>
    <li>Usá el botón <strong>"Imputar automáticamente"</strong> si querés saldar las más antiguas primero con el total de los medios.</li>
    <li>Cargá los <strong>medios de pago</strong>. Podés combinar varios (ej: $50.000 en efectivo + $30.000 por transferencia).</li>
    <li>Verificá que el <strong>total de medios</strong> coincida con el <strong>total imputado</strong>.</li>
    <li>Guardá.</li>
</ol>

<div class="ayuda-tip">
    <strong>Tip:</strong> si te sobra plata del cobro, podés cargar un monto mayor al que imputás.
    La diferencia queda como <strong>saldo a favor</strong> del cliente para la próxima factura.
</div>

<h3>Medios de pago</h3>
<ul>
    <li><strong>Efectivo</strong> — el más simple. Solo importe.</li>
    <li><strong>Transferencia</strong> — podés agregar una observación con el número de operación.</li>
    <li><strong>Cheque</strong> — cargá banco, número, fecha de cobro y titular.
        Al guardar, el cheque se agrega <strong>automáticamente al Calendario de Cheques</strong>
        con la fecha de cobro.</li>
    <li><strong>Tarjeta</strong> — para pagos con débito/crédito.</li>
    <li><strong>Retención</strong> — cuando el cliente te descuenta impuestos al pagar
        (IVA, IIBB, Ganancias, etc.).</li>
    <li><strong>Otro</strong> — para casos especiales.</li>
</ul>

<div class="ayuda-warning">
    <strong>Cheques:</strong> cuando registrás un cobro con cheque, se crea automáticamente
    un evento en el <strong>Calendario de Cheques</strong>. Si anulás el cobro, el cheque
    también se elimina del calendario.
</div>

<h3>Imputación a facturas</h3>
<p>
    El <strong>total de los medios de pago</strong> debe coincidir exactamente con el
    <strong>total imputado a facturas</strong>. El sistema te muestra la diferencia en la
    barra superior en tiempo real.
</p>
<p>Si la diferencia no es cero al guardar, la operación se rechaza.</p>

<h3>Anulación</h3>
<p>Solo el <strong>administrador</strong> puede anular un cobro. Al hacerlo:</p>
<ul>
    <li>Se marca el cobro como <strong>ANULADO</strong> (no se borra).</li>
    <li>Se revierte el movimiento de <strong>cuenta corriente</strong> del cliente.</li>
    <li>Si había cheques asociados, se eliminan del <strong>Calendario de Cheques</strong>.</li>
</ul>

<h3>Permisos</h3>
<ul>
    <li><strong>Admin / Dev:</strong> cobrar, ver, anular.</li>
    <li><strong>Vendedor:</strong> cobrar y ver solo los cobros de sus clientes asignados.</li>
    <li><strong>Productor:</strong> sin acceso.</li>
</ul>