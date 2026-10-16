<?php
require_once __DIR__ . '/../models/Cliente.php';


requireLogin(); // Seguridad


if (!puedeAccederClientes()) {
    header('Location: '. BASE_URL. 'dashboard');
    exit;
}

$esGestor   = esGestorDeUsuarios();

$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$clienteModel = new Cliente();

$c = ($clienteId > 0) ? $clienteModel->buscarPorId($clienteId) : [];

$miPropioId = (int) ($_SESSION['liquicont_id'] ?? 0);

if ($clienteId && !$c) {
    header('Location: '. BASE_URL .'clientes');
    exit;
}

// Un Vendedor solo puede entrar a SUS propios clientes, aunque
// adivine el id de otro en la URL.
if ($c && !$esGestor && (int) $c['vendedor_id'] !== $miPropioId) {
    header('Location: '. BASE_URL . 'clientes');
    exit;
}

// Obtener datos para todos los selects
$categorias      = $clienteModel->getCategorias();
$condicionesIVA  = $clienteModel->getCondicionesIVA();
$condicionesIIBB = $clienteModel->getCondicionesIIBB();
$provincias      = $clienteModel->getProvincias();
$tiposDocumento  = $clienteModel->getTiposDocumento();
$vendedores      = $esGestor ? $clienteModel->vendedoresActivos() : [];

$headerGreeting  = 'Ventas / Administración';
$pageTitle      = 'Clientes';
$pageScript      = 'cliente_edit.js';
$pageStyle       = 'cliente.css';

require_once 'header.php';
?>

<div class="page-content">
     <div class="page-header">
        <div>
            <h2><?= ($c['id'] ?? 0) > 0 ? 'Editar cliente' : 'Agregar cliente'; ?></h2>
            <div class="breadcrumb"><a href="<?= BASE_URL; ?>dashboard">Inicio</a> / <a href="<?= BASE_URL; ?>clientes">Clientes</a> / <?= ($c['id'] ?? 0) > 0 ? 'Editar' : 'Agregar'; ?></div>
        </div>
        <a href="<?= BASE_URL; ?>clientes" class="btn btn-outline">← Volver al listado</a>
    </div>

    <div id="formMsg" class="form-msg" style="max-width:none;"></div>
    
    <form id="clienteForm">
        <input type="hidden" name="id" value="<?= $c['id'] ?? 0; ?>">
        
        <div class="form-grid">
            <!-- TARJETA 1: Datos Principales -->
            <div class="card">
                <div class="card-header">
                    <h3>Datos principales</h3>
                    <p>Completá los campos obligatorios (*). Usá el botón ARCA para autocompletar a partir del CUIT.</p>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>TIPO DE DOCUMENTO</label>
                        <select name="tipo_documento_id">
                            <?php foreach ($tiposDocumento as $td): ?>
                                <option value="<?= $td['id']; ?>" <?= ($c['tipo_documento_id'] ?? '') == $td['id'] ? 'selected' : ''; ?>><?= $td['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                   <div class="form-group">
                        <label>NÚMERO</label>
                        <div class="input-group">
                            <input type="text" name="cuit" id="cuit" value="<?= $c['cuit'] ?? ''; ?>" placeholder="00000000000" required>
                            <button type="button" id="btnArca" class="btn btn-arca">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                ARCA
                            </button>
                        </div>
                    </div>          
                    <div class="form-group">
                        <label>* RAZÓN SOCIAL / NOMBRE</label>
                        <input type="text" name="razon_social" id="razon_social" value="<?= $c['razon_social'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>NOMBRE DE FANTASÍA</label>
                        <input type="text" name="nombre_fantasia" value="<?= $c['nombre_fantasia'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>CÓDIGO</label>
                        <input type="text" name="codigo" value="<?= $c['codigo'] ?? ''; ?>" placeholder="Código interno (opcional)">
                    </div>
                    <div class="form-group">
                        <label>* PERSONERÍA</label>
                        <select name="personeria">
                            <option value="FISICA" <?= ($c['personeria'] ?? 'FISICA') === 'FISICA' ? 'selected' : ''; ?>>Física</option>
                            <option value="JURIDICA" <?= ($c['personeria'] ?? '') === 'JURIDICA' ? 'selected' : ''; ?>>Jurídica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>* CATEGORÍA IMPOSITIVA</label>
                        <select name="id_condicion_iva" required>
                            <?php foreach ($condicionesIVA as $iva): ?>
                                <option value="<?= $iva['id']; ?>" <?= ($c['id_condicion_iva'] ?? '') == $iva['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($iva['descripcion']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ESTADO</label>
                        <?php if ($clienteId && $esGestor): ?>
                        <select name="estado">
                            <option value="ACTIVO" <?= ($c['estado'] ?? 'ACTIVO') === 'ACTIVO' ? 'selected' : ''; ?>>Activo</option>
                            <option value="INACTIVO" <?= ($c['estado'] ?? '') === 'INACTIVO' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                        <?php elseif ($clienteId): ?>
                        <div class="stat-card <?= $c['estado'] === 'ACTIVO' ? 'tone-teal' : 'tone-gold' ?>" style="padding:12px 16px;">
                            <p class="stat-value mono" style="font-size:16px;"><?= $c['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></p>
                        </div>
                        <p class="form-hint" style="margin-top:6px;">Solo un Administrador puede dar de baja a un cliente.</p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>EMAIL</label>
                        <input type="email" name="email" value="<?= $c['email'] ?? ''; ?>" placeholder="ejemplo@ejemplo.com">
                    </div>
                    <div class="form-group">
                        <label>EMAIL FACTURACIÓN</label>
                        <input type="text" name="email_facturacion" value="<?= $c['email_facturacion'] ?? ''; ?>" placeholder="ejemplo@ejemplo.com">
                        <p class="form-hint" style="margin-top:6px;">Si querés más de una dirección, sepáralas con coma.</p>
                    </div>
                </div>
            </div>

            <!-- TARJETA 2: Datos Fiscales y de Contacto -->
            <div class="card">
                <div class="card-header">
                    <h3>Datos fiscales y de contacto</h3>
                    <p>Se completan solos si usás el botón ARCA.</p>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>TELÉFONO</label>
                        <input type="text" name="telefono" value="<?= $c['telefono'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>CATEGORÍA DE CLIENTE</label>
                        <select name="categoria">
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id']; ?>" <?= ($c['categoria'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?= $cat['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CONDICIÓN IIBB</label>
                        <select name="id_condicion_iibb">
                            <?php foreach ($condicionesIIBB as $iibb): ?>
                                <option value="<?= $iibb['id']; ?>" <?= ($c['id_condicion_iibb'] ?? '') == $iibb['id'] ? 'selected' : ''; ?>><?= $iibb['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>NRO. INSCRIPCIÓN IIBB</label>
                        <input type="text" name="nro_inscripcion_iibb" value="<?= $c['nro_inscripcion_iibb'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>DOMICILIO FISCAL</label>
                        <input type="text" name="domicilio_fiscal" value="<?= $c['domicilio_fiscal'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>DOMICILIO ENTREGA</label>
                        <input type="text" name="domicilio_entrega" value="<?= $c['domicilio_entrega'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>LOCALIDAD</label>
                        <input type="text" name="localidad" value="<?= $c['localidad'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>PROVINCIA</label>
                        <select name="provincia_id">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option value="<?= $prov['codigo']; ?>" <?= ($c['provincia_id'] ?? '') == $prov['codigo'] ? 'selected' : ''; ?>><?= $prov['descripcion']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CONDICIÓN DE VENTA</label>
                        <select name="condicion_venta">
                            <option value="CONTADO" <?= ($c['condicion_venta'] ?? 'CONTADO') === 'CONTADO' ? 'selected' : ''; ?>>Contado</option>
                            <option value="CUENTA_CORRIENTE" <?= ($c['condicion_venta'] ?? '') === 'CUENTA_CORRIENTE' ? 'selected' : ''; ?>>Cuenta corriente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>LIMITE CRÉDITO</label>
                        <input type="number" step="0.01" name="limite_credito" value="<?= $c['limite_credito'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>VENDEDOR ASIGNADO</label>
                        <?php if ($esGestor): ?>
                        <select id="vendedor_id" name="vendedor_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($vendedores as $vend): ?>
                                <option value="<?= (int) $vend['id'] ?>" <?= (string) $c['vendedor_id'] === (string) $vend['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vend['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php elseif ($clienteId): ?>
                            <div class="stat-card tone-black" style="padding:12px 16px;">
                                <p class="stat-value mono" style="font-size:16px;">Vos</p>
                            </div>
                        <?php endif; ?>
                        </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="window.history.back()">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar cliente</button>
        </div>
        <div id="formMsg" class="form-msg"></div>
    </form>
</div>
<?php require_once 'footer.php'; ?>