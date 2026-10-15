<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Emisor';

$esEdicion = ($modo === 'editar');
$e = $emisor ?? [];
$val = fn($k, $def = '') => htmlspecialchars((string)($e[$k] ?? $def));
$sel = fn($k, $v) => ((string)($e[$k] ?? '') === (string)$v) ? 'selected' : '';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esEdicion ? 'Editar emisor' : 'Nuevo emisor' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>configuracion">Configuración</a> /
            <a href="<?= BASE_URL ?>configuracion?tab=emisores">Emisores</a> /
            <?= $esEdicion ? 'Editar' : 'Nuevo' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>configuracion?tab=emisores" class="btn btn-outline">← Volver al listado</a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="emisorForm" autocomplete="off">
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$e['id'] : '' ?>">

    <!-- ============================================================
         DATOS FISCALES
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Datos fiscales del emisor</h3>
            <p>Se usan para el encabezado del remito, la factura legal y los datos que se envían a ARCA.</p>
        </div>
        <div class="card-body cfg-grid-2">

            <div class="form-group field-full">
                <label for="nombre">Nombre interno *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="50"
                       value="<?= $val('nombre') ?>"
                       placeholder="Ej: Roky MdQ, Roky Rosario">
                <div class="form-hint">Es el nombre que aparece en el selector de emisor al facturar. Corto y claro.</div>
            </div>

            <div class="form-group field-full">
                <label for="razon_social">Razón social *</label>
                <input type="text" id="razon_social" name="razon_social" required maxlength="200"
                       value="<?= $val('razon_social') ?>">
            </div>

            <div class="form-group">
                <label for="nombre_fantasia">Nombre de fantasía</label>
                <input type="text" id="nombre_fantasia" name="nombre_fantasia" maxlength="200"
                       value="<?= $val('nombre_fantasia') ?>">
            </div>

            <div class="form-group">
                <label for="cuit">CUIT *</label>
                <input type="text" id="cuit" name="cuit" required maxlength="20"
                       value="<?= $val('cuit') ?>"
                       placeholder="30-71816263-3">
                <div class="form-hint">Con o sin guiones, se normaliza solo.</div>
            </div>

            <div class="form-group">
                <label for="condicion_iva">Condición frente al IVA *</label>
                <select id="condicion_iva" name="condicion_iva" required>
                    <option value="">— Seleccione —</option>
                    <option value="Responsable Inscripto" <?= $sel('condicion_iva', 'Responsable Inscripto') ?>>Responsable Inscripto</option>
                    <option value="Monotributista" <?= $sel('condicion_iva', 'Monotributista') ?>>Monotributista</option>
                    <option value="Exento" <?= $sel('condicion_iva', 'Exento') ?>>Exento</option>
                    <option value="No alcanzado" <?= $sel('condicion_iva', 'No alcanzado') ?>>No alcanzado</option>
                </select>
            </div>

            <div class="form-group">
                <label for="ingresos_brutos">Ingresos Brutos</label>
                <input type="text" id="ingresos_brutos" name="ingresos_brutos" maxlength="30"
                       value="<?= $val('ingresos_brutos') ?>">
            </div>

            <div class="form-group">
                <label for="fecha_inicio_actividades">Fecha de inicio de actividades</label>
                <input type="text" id="fecha_inicio_actividades" name="fecha_inicio_actividades"
                       maxlength="20" value="<?= $val('fecha_inicio_actividades') ?>"
                       placeholder="dd/mm/aaaa">
            </div>

            <div class="form-group field-full">
                <label for="domicilio">Domicilio completo *</label>
                <input type="text" id="domicilio" name="domicilio" required maxlength="255"
                       value="<?= $val('domicilio') ?>"
                       placeholder="Calle, número, CP, localidad, provincia">
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" maxlength="50"
                       value="<?= $val('telefono') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="100"
                       value="<?= $val('email') ?>">
            </div>

            <div class="form-group">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" maxlength="100"
                       value="<?= $val('sitio_web') ?>">
            </div>

            <div class="form-group">
                <label for="logo_path">Logo (ruta relativa)</label>
                <input type="text" id="logo_path" name="logo_path" maxlength="255"
                       value="<?= $val('logo_path') ?>"
                       placeholder="dist/img/logo-krown-black.png">
                <div class="form-hint">Dejar vacío para usar el logo por defecto.</div>
            </div>

        </div>
    </div>

    <!-- ============================================================
         ARCA
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Configuración ARCA</h3>
            <p>Datos del certificado digital, punto de venta y comportamiento de la facturación electrónica.</p>
        </div>
        <div class="card-body cfg-grid-2">

            <div class="form-group">
                <label for="arca_entorno">Entorno ARCA *</label>
                <select id="arca_entorno" name="arca_entorno" required>
                    <option value="homologacion" <?= $sel('arca_entorno', 'homologacion') ?>>Homologación (pruebas)</option>
                    <option value="produccion"   <?= $sel('arca_entorno', 'produccion')   ?>>Producción</option>
                </select>
            </div>

            <div class="form-group">
                <label for="arca_punto_venta">Punto de venta</label>
                <input type="number" id="arca_punto_venta" name="arca_punto_venta" min="0"
                       value="<?= $val('arca_punto_venta') ?>">
                <div class="form-hint">El número habilitado en ARCA. Ej: 1, 2, 3.</div>
            </div>

            <div class="form-group">
                <label for="arca_tipo_comprobante_letra">Letra del comprobante</label>
                <select id="arca_tipo_comprobante_letra" name="arca_tipo_comprobante_letra">
                    <option value="">— Sin definir —</option>
                    <option value="A" <?= $sel('arca_tipo_comprobante_letra', 'A') ?>>A</option>
                    <option value="B" <?= $sel('arca_tipo_comprobante_letra', 'B') ?>>B</option>
                    <option value="C" <?= $sel('arca_tipo_comprobante_letra', 'C') ?>>C</option>
                </select>
            </div>

            <div class="form-group">
                <label for="arca_concepto">Concepto</label>
                <select id="arca_concepto" name="arca_concepto">
                    <option value="1" <?= $sel('arca_concepto', 1) ?>>1 = Productos</option>
                    <option value="2" <?= $sel('arca_concepto', 2) ?>>2 = Servicios</option>
                    <option value="3" <?= $sel('arca_concepto', 3) ?>>3 = Productos y Servicios</option>
                </select>
            </div>

            <div class="form-group">
                <label for="arca_iva_id">Código alícuota IVA</label>
                <select id="arca_iva_id" name="arca_iva_id">
                    <option value="3" <?= $sel('arca_iva_id', 3) ?>>3 = 0%</option>
                    <option value="4" <?= $sel('arca_iva_id', 4) ?>>4 = 10.5%</option>
                    <option value="5" <?= $sel('arca_iva_id', 5) ?>>5 = 21%</option>
                    <option value="6" <?= $sel('arca_iva_id', 6) ?>>6 = 27%</option>
                </select>
            </div>

            <div class="form-group field-full">
                <label for="cert_path">Ruta del certificado (.crt) *</label>
                <input type="text" id="cert_path" name="cert_path" maxlength="255"
                       value="<?= $val('cert_path') ?>"
                       placeholder="config/arca/emisor_1/cert.crt">
                <div class="form-hint">Ruta relativa desde la raíz de la app. Se autogenera si la dejás vacía.</div>
            </div>

            <div class="form-group field-full">
                <label for="key_path">Ruta de la clave privada (.key) *</label>
                <input type="text" id="key_path" name="key_path" maxlength="255"
                       value="<?= $val('key_path') ?>"
                       placeholder="config/arca/emisor_1/private.key">
                <div class="form-hint">Se autogenera si la dejás vacía.</div>
            </div>

            <div class="form-group">
                <label for="key_passphrase">Contraseña de la clave</label>
                <input type="password" id="key_passphrase" name="key_passphrase" maxlength="100"
                       value="<?= $val('key_passphrase') ?>"
                       placeholder="Dejar vacío si no tiene">
            </div>

            <div class="form-group">
                <label for="ta_cache_dir">Carpeta de caché de TA</label>
                <input type="text" id="ta_cache_dir" name="ta_cache_dir" maxlength="255"
                       value="<?= $val('ta_cache_dir') ?>"
                       placeholder="config/arca/emisor_1/ta_cache">
                <div class="form-hint">Se autogenera si la dejás vacía. Se crea sola cuando ARCA devuelve el primer TA.</div>
            </div>

        </div>
    </div>

    <!-- ============================================================
         REMITOS
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Remitos</h3>
            <p>Configuración del contador de remitos para este emisor.</p>
        </div>
        <div class="card-body cfg-grid-2">

            <div class="form-group">
                <label for="remito_prefijo">Prefijo</label>
                <input type="text" id="remito_prefijo" name="remito_prefijo" maxlength="5"
                       value="<?= $val('remito_prefijo') ?>"
                       placeholder="X">
                <div class="form-hint">Letra(s) antes del punto de venta. Ej: X, A, o vacío.</div>
            </div>

            <div class="form-group">
                <label for="remito_punto_venta">Punto de venta *</label>
                <input type="number" id="remito_punto_venta" name="remito_punto_venta" min="1"
                       value="<?= $val('remito_punto_venta', '1') ?>">
            </div>

            <?php if ($esEdicion): ?>
                <div class="form-group">
                    <label>Último número de remito emitido</label>
                    <input type="text" value="<?= (int)($e['remito_ultimo_nro'] ?? 0) ?>" disabled>
                    <div class="form-hint">El contador se actualiza automáticamente. No se puede editar manualmente.</div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ============================================================
         ESTADO
         ============================================================ -->
    <div class="card">
        <div class="card-body card-body-stack">
            <label class="cfg-check-activo">
                <input type="checkbox" id="activo" name="activo" value="1"
                       <?= (!$esEdicion || (int)($e['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
                Emisor activo (puede emitir facturas y remitos)
            </label>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>configuracion?tab=emisores" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar cambios' : 'Crear emisor' ?>
        </button>
    </div>

</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>