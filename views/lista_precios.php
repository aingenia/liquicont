<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Lista de Precios';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Lista de Precios</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Ventas / Lista de Precios</div>
    </div>
    <div class="lp-header-actions">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>lista-precios/ayuda"
                data-ayuda-titulo="Ayuda — Lista de Precios">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <?php if (puedeEditarListaPrecios()): ?>
            <button type="button" class="btn btn-outline" id="btnAbrirEnviar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Enviar por correo
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" id="btnImprimir">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Imprimir
        </button>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<!-- ============================================================
     CONTROLES
     ============================================================ -->
<form id="listaForm" class="lp-controles" autocomplete="off">
    <div class="form-group" style="flex:0 0 260px;">
        <label for="categoria_id">Categoría de cliente</label>
        <select id="categoria_id" name="categoria_id">
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $catSel === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['descripcion']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="flex:0 0 130px;">
        <label for="numero">Lista N°</label>
        <input type="number" id="numero" name="numero" min="1" value="<?= (int)$numeroSugerido ?>">
    </div>
    <div class="form-group" style="flex:0 0 180px;">
        <label for="fecha">Vigente desde</label>
        <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fechaDefault) ?>">
    </div>
    <?php if (puedeEditarListaPrecios()): ?>
        <div class="form-group" style="flex:0 0 auto;">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-outline" id="btnGuardar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Guardar
            </button>
        </div>
    <?php endif; ?>
</form>

<!-- ============================================================
     PREVISUALIZACIÓN DE LA LISTA
     ============================================================ -->
<div class="content-card lp-preview" id="lpPreview">

    <!-- Logo -->
    <div class="lp-logo-wrap">
        <img src="<?= BASE_URL ?>dist/img/logo-krown-black.png"
             alt="Krown" class="lp-logo">
    </div>


        <!-- Barra de datos: N° a la izquierda, fecha a la derecha -->
    <div class="lp-barra">
        <span class="lp-barra-item lp-barra-numero">Lista N° <strong id="lpPreviewNumero"><?= (int)$numeroSugerido ?></strong></span>
        <span class="lp-barra-item lp-barra-vigencia">Vigente desde el: <strong id="lpPreviewFecha"><?= date('d-m-Y', strtotime($fechaDefault)) ?></strong></span>
    </div>

    <!-- Grupos -->
    <div id="lpPreviewBody">
        <?php if (empty($grupos)): ?>
            <div class="lp-empty">
                <p>No hay artículos visibles para esta categoría.</p>
                <p class="lp-empty-hint">
                    Verificá que los artículos tengan precios cargados y el checkbox "Visible en lista"
                    tildado para esta categoría.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($grupos as $volumen => $g): ?>
                <?php if (empty($g['items'])) continue; ?>

                <div class="lp-grupo">
                    <h3 class="lp-grupo-titulo">
                        <?= htmlspecialchars($g['nombre']) ?>
                        <?php if ($volumen > 0): ?>
                            <?= (int)$volumen ?> cc
                        <?php endif; ?>
                    </h3>

                    <table class="lp-tabla">
                        <thead>
                            <tr>
                                <th class="lp-col-cod"></th>
                                <th class="lp-col-nombre"></th>
                                <?php if ($g['unidades_por_caja'] > 0): ?>
                                    <th class="lp-col-caja">Cajas x <?= (int)$g['unidades_por_caja'] ?> unidades</th>
                                <?php else: ?>
                                    <th class="lp-col-caja"></th>
                                <?php endif; ?>
                                <th class="lp-col-unidad">Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($g['items'] as $it): ?>
                                <tr>
                                    <td class="lp-col-cod"><?= htmlspecialchars($it['codigo_corto']) ?></td>
                                    <td class="lp-col-nombre"><?= htmlspecialchars($it['nombre']) ?></td>
                                    <td class="lp-col-caja">
                                        <?= $it['precio_caja'] !== null
                                            ? '$ ' . number_format($it['precio_caja'], 0, ',', '.')
                                            : '—' ?>
                                    </td>
                                    <td class="lp-col-unidad">$ <?= number_format($it['precio_unidad'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

            <p class="lp-leyenda-pie">LOS PRECIOS NO INCLUYEN IVA - FLETE A CARGO DEL CLIENTE</p>
        <?php endif; ?>
    </div>

    <!-- Pie con datos del emisor -->
    <div class="lp-pie">
        <?php
        $emp = [
            'razon_social' => $this->config->obtener('empresa_razon_social', '') ?? '',
            'domicilio'    => $this->config->obtener('empresa_domicilio', '') ?? '',
            'telefono'     => $this->config->obtener('empresa_telefono', '') ?? '',
            'sitio_web'    => $this->config->obtener('empresa_sitio_web', '') ?? '',
            'cuit'         => $this->config->obtener('empresa_cuit', '') ?? '',
        ];
        ?>
        <div><?= htmlspecialchars($emp['razon_social']) ?></div>
        <div><?= htmlspecialchars($emp['domicilio']) ?></div>
        <div>
            Tel: <?= htmlspecialchars($emp['telefono']) ?>
            <?php if ($emp['sitio_web']): ?> – <?= strtoupper(htmlspecialchars($emp['sitio_web'])) ?><?php endif; ?>
            <?php if ($emp['cuit']): ?> – CUIT <?= htmlspecialchars($emp['cuit']) ?><?php endif; ?>
        </div>
    </div>

</div>

<!-- ============================================================
     MODAL DE ENVÍO POR EMAIL
     ============================================================ -->
<div class="cal-modal-overlay" id="modalEnviar" style="display:none;">
    <div class="cal-modal" style="max-width:640px;">
        <div class="cal-modal-header">
            <div>
                <h3 id="modalEnviarTitulo">Enviar Lista de Precios por correo</h3>
                <span class="cal-modal-fecha" id="modalEnviarSub">
                    Se adjunta la lista N° <?= (int)$numeroSugerido ?> a los clientes seleccionados.
                </span>
            </div>
            <button type="button" class="cal-modal-close" id="modalEnviarCerrar" title="Cerrar">&times;</button>
        </div>

        <div class="cal-modal-body">

            <h4 class="lp-modal-subtitulo">Clientes</h4>
            <div class="lp-modal-clientes-hint" id="lpModalClientesHint">
                Cargando clientes con email de esta categoría...
            </div>
            <div class="lp-modal-clientes-top">
                <span id="lpModalClientesCount">0 clientes</span>
                <button type="button" class="btn btn-outline btn-sm" id="lpModalSelTodos">
                    Seleccionar todos
                </button>
            </div>
            <div class="lp-modal-clientes" id="lpModalClientes"></div>

            <h4 class="lp-modal-subtitulo">Asunto</h4>
            <input type="text" id="lpModalAsunto" class="lp-modal-input"
                   value="<?= htmlspecialchars($emailAsunto) ?>">

            <h4 class="lp-modal-subtitulo">Cuerpo del email</h4>
            <textarea id="lpModalCuerpo" class="lp-modal-textarea"
                      rows="6"><?= htmlspecialchars($emailCuerpo) ?></textarea>
            <div class="lp-modal-hint">
                Podés usar <code>{{cliente_nombre}}</code>, <code>{{lista_numero}}</code>
                y <code>{{vigente_desde}}</code>; se completan solos al enviar.
            </div>

            <h4 class="lp-modal-subtitulo">Adjuntar como</h4>
            <div class="lp-modal-formato">
                <label class="lp-radio">
                    <input type="radio" name="lpFormato" value="pdf" checked>
                    PDF
                </label>
                <label class="lp-radio <?= !$puedeGenerarImagen ? 'lp-radio-disabled' : '' ?>">
                    <input type="radio" name="lpFormato" value="imagen" <?= !$puedeGenerarImagen ? 'disabled' : '' ?>>
                    Imagen
                </label>
            </div>
            <?php if (!$puedeGenerarImagen): ?>
                <div class="lp-modal-hint">
                    La opción de imagen no está disponible en este servidor
                    (requiere la extensión Imagick con Ghostscript).
                </div>
            <?php endif; ?>

            <div class="lp-modal-actions">
                <button type="button" class="btn btn-outline" id="lpModalCancelar">Cancelar</button>
                <button type="button" class="btn btn-primary" id="lpModalEnviar">
                    Enviar
                </button>
            </div>

        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>