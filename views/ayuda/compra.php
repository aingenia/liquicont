<h3>¿Qué es un comprobante de compra?</h3>
<p>
    Es una factura (o nota de crédito) que te emitió un proveedor. Al cargarla,
    el sistema <strong>suma stock</strong> a los insumos comprados y
    <strong>actualiza el costo</strong> que ese proveedor te cobra.
</p>

<h3>Cómo cargar un comprobante</h3>
<ol>
    <li><strong>Datos del comprobante</strong>: proveedor, tipo, punto de venta, número y fecha.</li>
    <li><strong>CAE</strong>: copialo del comprobante físico. Si lo dejás vacío, se guarda sin CAE.</li>
    <li><strong>Constatar en ARCA</strong> (opcional): valida que la factura sea genuina.
        Requiere CUIT del emisor, CAE, tipo y número cargados.</li>
    <li><strong>Cuenta contable y vencimientos</strong>: opcionales. Se usarán cuando implementemos la parte contable.</li>
    <li><strong>Adjunto</strong>: subí una foto o PDF del comprobante (JPG, PNG, WebP o PDF, máx 8 MB).</li>
    <li><strong>Importes</strong>: el subtotal se calcula desde los insumos. Cargá IVA e impuestos
        internos a mano (tal cual figuran en la factura).</li>
    <li><strong>Insumos comprados</strong>: cargá cada insumo con su cantidad y costo unitario.</li>
</ol>

<div class="ayuda-tip">
    <strong>Tip:</strong> al guardar, el sistema hace 3 cosas automáticamente:
    <ul>
        <li>Suma la cantidad al stock de cada insumo.</li>
        <li>Actualiza el costo del proveedor para ese insumo (queda como el precio más reciente).</li>
        <li>Recalcula el costo de cada insumo (y por cascada, el de los artículos que lo usan).</li>
    </ul>
</div>

<h3>Notas de crédito</h3>
<p>
    Si cargás una <strong>Nota de Crédito</strong> de compra, el sistema
    <strong>resta stock</strong> en lugar de sumarlo. Es la forma correcta de
    registrar una devolución o un ajuste del proveedor.
</p>

<h3>¿Se puede editar un comprobante?</h3>
<p>
    <strong>No.</strong> Los comprobantes son inmutables, para garantizar la integridad
    contable. Si cargaste algo mal:
</p>
<ol>
    <li>Anulá el comprobante (ícono de la <strong>×</strong> en el listado).</li>
    <li>Cargá uno nuevo con los datos correctos.</li>
</ol>

<div class="ayuda-warning">
    <strong>Importante:</strong> al anular un comprobante, el sistema
    <strong>revierte el stock</strong> automáticamente (suma lo que había restado
    una nota de crédito, o resta lo que había sumado una factura).
    <br><br>
    El <strong>costo del proveedor NO se revierte</strong>, porque es información
    de mercado: aunque esa factura se anule, el precio que te cotizaron sigue siendo real.
</div>

<h3>Filtros del listado</h3>
<p>
    Por defecto se muestran los comprobantes del <strong>último mes</strong>.
    Podés cambiar el rango de fechas, filtrar por proveedor, buscar por número o CAE,
    y filtrar por estado.
</p>

<h3>Exportar</h3>
<p>
    En cualquier momento podés:
</p>
<ul>
    <li><strong>Exportar Excel</strong>: descarga un CSV con los comprobantes del rango actual.</li>
    <li><strong>Imprimir</strong>: abre una vista optimizada para imprimir o guardar como PDF.</li>
    <li><strong>Descargar PDF</strong>: genera un PDF del listado directamente.</li>
</ul>