<h3>¿Qué es la Lista de Precios?</h3>
<p>
    Es el documento que envías a tus clientes con los precios vigentes de los artículos.
    Se genera automáticamente a partir de los precios cargados en cada artículo
    (pestaña "Precios por categoría") y muestra solo los artículos con el checkbox
    <strong>"Visible en lista"</strong> tildado para esa categoría.
</p>

<h3>Cómo se genera</h3>
<ol>
    <li>Elegí la <strong>categoría de cliente</strong> en el select de arriba
        (Distribuidor, Mayorista, Local, Distribuidor Final).</li>
    <li>Verificá el <strong>número de lista</strong> — se sugiere el último + 1.</li>
    <li>Verificá la <strong>fecha de vigencia</strong> — por defecto el día 1 del mes actual.</li>
    <li>La lista se previsualiza debajo, en tiempo real.</li>
    <li>Click en <strong>Guardar</strong> para persistir número, fecha y las claves de email.</li>
    <li>Click en <strong>Imprimir</strong> para abrir el PDF o enviar a impresora.</li>
</ol>

<h3>Agrupación por volumen</h3>
<p>
    Los artículos se agrupan por el <strong>volumen</strong> que figura al inicio de su
    <strong>código</strong>. Por ejemplo:
</p>
<ul>
    <li>Código <code>200CC</code> → grupo <strong>200 cc</strong>, código corto <code>CC</code></li>
    <li>Código <code>750VL</code> → grupo <strong>750 cc</strong>, código corto <code>VL</code></li>
</ul>
<p>
    El nombre de cada grupo (<em>Petacas</em>, <em>Vidrio</em>, etc.) se configura en
    <strong>Configuración → Lista de precios</strong>.
</p>

<div class="ayuda-tip">
    <strong>Tip:</strong> si un artículo no aparece en la lista, verificá que:
    <ul>
        <li>Tenga precio cargado para esa categoría (Artículos → Editar → Precios por categoría).</li>
        <li>Tenga el checkbox "Visible en lista" tildado para esa categoría.</li>
        <li>Esté en estado ACTIVO.</li>
        <li>Tenga un código cargado que empiece con el volumen (ej: <code>200XXX</code>).</li>
    </ul>
</div>

<h3>Envío por email</h3>
<p>
    El botón <strong>Enviar por correo</strong> abre un modal donde podés:
</p>
<ol>
    <li>Elegir los <strong>clientes</strong> que van a recibir la lista. Solo se muestran
        los de la categoría activa que tengan email cargado.</li>
    <li>Editar el <strong>asunto y cuerpo</strong> del email (podés usar los placeholders
        <code>{{cliente_nombre}}</code>, <code>{{lista_numero}}</code> y
        <code>{{vigente_desde}}</code>).</li>
    <li>Elegir el formato del adjunto: <strong>PDF</strong> o <strong>imagen</strong>.
        La opción imagen requiere que el servidor tenga Imagick habilitado.</li>
</ol>

<div class="ayuda-warning">
    <strong>Importante:</strong> al enviar, el asunto, cuerpo y los datos de la lista
    (número, fecha) se guardan automáticamente en Configuración, para que el próximo
    envío use los mismos valores.
</div>

<h3>Permisos</h3>
<ul>
    <li><strong>Admin / Dev:</strong> acceso total (editar, guardar, enviar).</li>
    <li><strong>Vendedor:</strong> puede ver la lista pero no guardar ni enviar.</li>
    <li><strong>Productor:</strong> sin acceso.</li>
</ul>