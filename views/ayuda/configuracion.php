<h3>¿Qué es Configuración?</h3>
<p>
    Este módulo tiene <strong>dos partes</strong>: los parámetros generales del sistema
    y la lista de emisores de comprobantes. Es la pantalla que vas a tocar cuando
    necesites ajustar algún dato global o agregar una nueva razón social para facturar.
</p>

<h3>Parámetros del sistema</h3>
<p>Son valores que la app usa en distintas partes. Se agrupan por tema:</p>
<ul>
    <li><strong>Datos de la empresa</strong> — razón social, CUIT, domicilio, teléfono. Se imprimen en remitos, listas de precios y emails.</li>
    <li><strong>Lista de precios</strong> — número y fecha de vigencia de la lista actual.</li>
    <li><strong>Impuestos y alícuotas</strong> — porcentaje del impuesto a bebidas alcohólicas (impuesto interno no recuperable, se suma al costo del insumo).</li>
    <li><strong>Fasón</strong> — franquicia de litros que se descuenta automáticamente antes de facturar el servicio.</li>
    <li><strong>Parámetros ARCA</strong> — concepto y código de alícuota de IVA por defecto para nuevos emisores.</li>
</ul>

<div class="ayuda-tip">
    <strong>Tip:</strong> cuando terminás de editar, hacé clic en <strong>"Guardar todos los parámetros"</strong>
    al final del listado. Se guardan todos juntos en una sola operación.
</div>

<h3>Emisores</h3>
<p>
    Un <strong>emisor</strong> es una razón social que puede facturar y emitir remitos. La app
    soporta <strong>múltiples emisores</strong>: cada uno tiene su propio CUIT, certificado
    digital ARCA, punto de venta y contador de remitos.
</p>
<p>Al crear una factura, elegís con qué emisor se emite. Los pedidos, artículos, clientes y todo
    el resto son compartidos entre emisores.</p>

<h4>Cómo crear un emisor</h4>
<ol>
    <li>Presioná <strong>Nuevo emisor</strong> arriba a la derecha del listado.</li>
    <li>Completá los datos fiscales (razón social, CUIT, condición IVA, domicilio).</li>
    <li>En la sección <strong>ARCA</strong>: elegí el entorno, el punto de venta y la letra de comprobante.</li>
    <li>Dejá las rutas del certificado y la clave en blanco si no las tenés todavía —
        el sistema las autogenera con la convención <code>config/arca/&lt;nombre&gt;/</code>.</li>
    <li>Guardá. Cuando tengas los archivos del certificado, subilos a la carpeta que indica la ruta.</li>
</ol>

<div class="ayuda-warning">
    <strong>Entorno ARCA:</strong> mientras estás haciendo pruebas, dejá <code>Homologación</code>.
    Cuando estés listo para emitir facturas reales, cambiá a <code>Producción</code>.
    <br><br>
    Una factura emitida en producción <strong>no se puede deshacer</strong>. Antes de cambiar, asegurate
    de haber probado todo el flujo en homologación.
</div>

<h4>Activar y desactivar</h4>
<p>
    Un emisor <strong>inactivo</strong> no aparece en el selector al momento de facturar, pero sus
    facturas y remitos anteriores siguen existiendo. Útil cuando un emisor deja de operar
    temporalmente.
</p>
<div class="ayuda-tip">
    No podés desactivar el <strong>último emisor activo</strong> — el sistema necesita al menos uno.
</div>

<h4>Contador de remitos</h4>
<p>
    Cada emisor tiene su propio contador. Cada vez que se guarda una factura, el sistema reserva
    el siguiente número y lo incrementa. El formato es <code>PREFIJO PUNTO-NUMERO</code>
    (ej: <code>X 0001-00000042</code>).
</p>
<p>
    El contador <strong>no se puede editar manualmente</strong> — se ajusta solo. Si necesitás
    arrancar la numeración desde otro punto, contactá al desarrollador para modificar la base
    de datos directamente.
</p>

<h3>Permisos</h3>
<p>
    Solo el <strong>Administrador</strong> puede acceder a Configuración. Los demás roles no ven este módulo en el menú.
</p>