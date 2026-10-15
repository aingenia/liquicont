(function ($) {
    'use strict';

    let timer = null;

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    /* ---------- BUSCADOR ---------- */
    $('#status').on('change', listarArticulos);
    $('#search').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(listarArticulos, 300);
    });

    function listarArticulos() {
        $.ajax({
            url: BASE_URL + 'articulos/buscar',
            type: 'POST',
            dataType: 'json',
            data: { search: $('#search').val() || '', status: $('#status').val() || '' }
        })
        .done(function (res) {
            if (res.status === 'success') $('#articulosTableBody').html(res.html);
            else showMsg('error', res.msg || 'Error al cargar.');
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    /* ---------- ELIMINAR ---------- */
    $('#articulosTableBody').on('click', '.btn-eliminar', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        if (!confirm('¿Desactivar el artículo "' + nombre + '"?')) return;

        $.ajax({
            url: BASE_URL + 'articulos/eliminar',
            type: 'POST', dataType: 'json', data: { id: id }
        })
        .done(function (res) {
            if (res.ok) { showMsg('ok', res.msg); setTimeout(listarArticulos, 600); }
            else showMsg('error', res.msg || 'No se pudo eliminar.');
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); });
    });

    /* ---------- FORM ---------- */
    const $form = $('#articuloForm');
    if ($form.length) {
        $form.on('submit', function (e) {
            e.preventDefault();

            const $btn = $form.find('button[type="submit"]');
            const original = $btn.text();
            $btn.prop('disabled', true).text('Guardando...');

            const articuloId = parseInt($('input[name="id"]').val(), 10);

            // Función que guarda el artículo (post-guardado de precios)
            function guardarArticulo() {
                $.ajax({
                    url: BASE_URL + 'articulos/' + (articuloId
                            ? 'editar?id=' + articuloId : 'crear'),
                    type: 'POST', dataType: 'json', data: $form.serialize()
                })
                .done(function (res) {
                    if (res.ok) {
                        showMsg('ok', res.msg);
                        setTimeout(function () {
                            window.location.href = res.redirect || (BASE_URL + 'articulos');
                        }, 800);
                    } else showMsg('error', res.msg || 'Error al guardar.');
                })
                .fail(function (xhr) {
                    const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
                    showMsg('error', m);
                })
                .always(function () { $btn.prop('disabled', false).text(original); });
            }

            // Si estamos en edición y hay tabla de precios, guardarlos ANTES del artículo
            if (articuloId && $('#tablaPrecios tbody tr').length) {
                const promesas = [];

                $('#tablaPrecios tbody tr').each(function () {
                    const $fila   = $(this);
                    const catId   = parseInt($fila.data('cat-id'), 10);
                    const precio  = parseFloat($fila.find('.precio-input').val() || 0);
                    const visible = $fila.find('.precio-visible').is(':checked') ? 1 : 0;

                    if (!catId) return;

                    promesas.push(
                        $.ajax({
                            url: BASE_URL + 'articulos/precio/guardar',
                            type: 'POST', dataType: 'json',
                            data: {
                                articulo_id:  articuloId,
                                categoria_id: catId,
                                precio:       precio,
                                visible:      visible
                            }
                        })
                    );
                });

                // Cuando TODOS los precios se guarden (éxito o error), guardamos el artículo
                $.when.apply($, promesas).always(function () {
                    guardarArticulo();
                });
            } else {
                // Modo crear: no hay precios todavía
                guardarArticulo();
            }
        });
    }

    function actualizarCostoDisplay(costo) {
        $('#costoDisplay').text('$ ' + Number(costo).toLocaleString('es-AR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        }));
    }

    /* ---------- AGREGAR INSUMO A LA FÓRMULA ---------- */
    $('#btnAgregarInsumo').on('click', function () {
        const articuloId = parseInt($('input[name="id"]').val(), 10);
        const insumoId   = parseInt($('#formula_insumo').val(), 10);
        const cantidad   = parseFloat($('#formula_cantidad').val());

        if (!insumoId) { showMsg('error', 'Seleccioná un insumo.'); return; }
        if (!(cantidad > 0)) { showMsg('error', 'Ingresá una cantidad mayor a 0.'); return; }

        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Agregando...');

        $.ajax({
            url: BASE_URL + 'articulos/formula/agregar',
            type: 'POST', dataType: 'json',
            data: { articulo_id: articuloId, insumo_id: insumoId, cantidad: cantidad }
        })
        .done(function (res) {
            if (!res.ok) { showMsg('error', res.msg); return; }
            $('#formulaBody .fila-vacia').remove();
            const $existente = $('#formulaBody tr[data-row-id="' + res.rowId + '"]');
            if ($existente.length) $existente.replaceWith(res.html);
            else $('#formulaBody').append(res.html);
            actualizarCostoDisplay(res.costo);
            $('#formula_insumo').val('');
            $('#formula_cantidad').val('');
            showMsg('ok', res.msg);
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); })
        .always(function () { $btn.prop('disabled', false).text(original); });
    });

    /* ---------- EDITAR CANTIDAD DE INSUMO (inline) ---------- */
    function entrarEdicion($fila) {
        cancelarEdicion();
        $fila.addClass('fila-editando');
        $fila.find('.cell-cantidad .valor').hide();
        $fila.find('.cell-cantidad .editor').show().focus();
        $fila.find('.acciones-normal').hide();
        $fila.find('.acciones-edicion').show();
    }
    function salirEdicion($fila) {
        $fila.removeClass('fila-editando');
        $fila.find('.cell-cantidad .editor').hide();
        $fila.find('.cell-cantidad .valor').show();
        $fila.find('.acciones-edicion').hide();
        $fila.find('.acciones-normal').show();
    }
    function cancelarEdicion() { $('.fila-editando').each(function () { salirEdicion($(this)); }); }

    $('#formulaBody').on('click', '.btn-editar-formula', function () {
        entrarEdicion($(this).closest('tr'));
    });
    $('#formulaBody').on('click', '.btn-cancelar-formula', function () {
        salirEdicion($(this).closest('tr'));
    });
    $('#formulaBody').on('click', '.btn-guardar-formula', function () {
        const $fila = $(this).closest('tr');
        const rowId = parseInt($fila.data('row-id'), 10);
        const articuloId = parseInt($('input[name="id"]').val(), 10);
        const cantidad = parseFloat($fila.find('.cell-cantidad .editor').val());

        if (!(cantidad > 0)) { showMsg('error', 'Cantidad inválida.'); return; }

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'articulos/formula/actualizar',
            type: 'POST', dataType: 'json',
            data: { id: rowId, articulo_id: articuloId, cantidad: cantidad }
        })
        .done(function (res) {
            if (!res.ok) { showMsg('error', res.msg); return; }
            $fila.replaceWith(res.html);
            actualizarCostoDisplay(res.costo);
            showMsg('ok', res.msg);
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); })
        .always(function () { $btn.prop('disabled', false); });
    });
    $(document).on('click', function (e) {
        if ($(e.target).closest('.fila-editando').length) return;
        if ($(e.target).closest('.btn-editar-formula').length) return;
        cancelarEdicion();
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') cancelarEdicion();
    });
    $('#formulaBody').on('keydown', 'input.editor', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).closest('tr').find('.btn-guardar-formula').trigger('click'); }
    });

    /* ---------- ELIMINAR INSUMO DE FÓRMULA ---------- */
    $('#formulaBody').on('click', '.btn-eliminar-formula', function () {
        const rowId = $(this).data('id');
        const articuloId = parseInt($('input[name="id"]').val(), 10);
        if (!confirm('¿Quitar este insumo de la fórmula?')) return;

        $.ajax({
            url: BASE_URL + 'articulos/formula/eliminar',
            type: 'POST', dataType: 'json',
            data: { id: rowId, articulo_id: articuloId }
        })
        .done(function (res) {
            if (!res.ok) { showMsg('error', res.msg); return; }
            $('#formulaBody tr[data-row-id="' + rowId + '"]').remove();
            if ($('#formulaBody tr').length === 0) {
                $('#formulaBody').append(
                    '<tr class="fila-vacia"><td colspan="4" style="text-align:center;color:#7A7266;padding:1.5rem;">' +
                    'Todavía no hay insumos en la fórmula.</td></tr>'
                );
            }
            actualizarCostoDisplay(res.costo);
            showMsg('ok', res.msg);
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); });
    });

    /* ---------- GUARDAR PRECIOS ---------- */
    $('#tablaPrecios').on('click', '.btn-guardar-precio', function () {
        const $fila = $(this).closest('tr');
        const catId = parseInt($fila.data('cat-id'), 10);
        const articuloId = parseInt($('input[name="id"]').val(), 10);
        const precio = parseFloat($fila.find('.precio-input').val() || 0);
        const visible = $fila.find('.precio-visible').is(':checked') ? 1 : 0;

        if (precio < 0) { showMsg('error', 'El precio no puede ser negativo.'); return; }

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'articulos/precio/guardar',
            type: 'POST', dataType: 'json',
            data: { articulo_id: articuloId, categoria_id: catId, precio: precio, visible: visible }
        })
        .done(function (res) {
            if (res.ok) showMsg('ok', 'Precio guardado.');
            else showMsg('error', res.msg || 'Error al guardar.');
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); })
        .always(function () { $btn.prop('disabled', false); });
    });

    /* ---------- HISTORIAL DE COSTOS ---------- */
    function cargarHistorial() {
        const $tbody = $('#historialCostoBody');
        if (!$tbody.length) return;
        const articuloId = parseInt($('input[name="id"]').val(), 10);
        if (!articuloId) return;

        $.ajax({
            url: BASE_URL + 'articulos/historial',
            type: 'GET', dataType: 'json',
            data: { articulo_id: articuloId }
        })
        .done(function (res) {
            if (res.ok) $tbody.html(res.html);
            else $tbody.html('<tr><td colspan="5" style="text-align:center;color:#B23A48;padding:1rem;">' + (res.msg || 'Error.') + '</td></tr>');
        })
        .fail(function () {
            $tbody.html('<tr><td colspan="5" style="text-align:center;color:#B23A48;padding:1rem;">Error de conexión.</td></tr>');
        });
    }
    if ($('#historialCostoBody').length) cargarHistorial();

    $('#historialCostoBody').on('click', '.btn-anular-hist', function () {
        const id = $(this).data('id');
        if (!confirm('¿Anular este registro del historial?')) return;

        $.ajax({
            url: BASE_URL + 'articulos/historial/anular',
            type: 'POST', dataType: 'json', data: { id: id }
        })
        .done(function (res) {
            if (res.ok) { showMsg('ok', res.msg); cargarHistorial(); }
            else showMsg('error', res.msg);
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); });
    });

    /* ---------- IMAGEN con Cropper ---------- */
    let cropper = null;

    $('#btnSubirImagen').on('click', function () {
        $('#imagenInput').val('').trigger('click');
    });

    $('#imagenInput').on('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            showMsg('error', 'La imagen no puede superar los 2 MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (ev) {
            $('#cropperImg').attr('src', ev.target.result);
            $('#cropperModal').css('display', 'flex');

            setTimeout(function () {
                if (cropper) cropper.destroy();
                cropper = new Cropper(document.getElementById('cropperImg'), {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    background: false,
                    movable: true,
                    zoomable: true,
                    rotatable: false,
                    scalable: false
                });
            }, 100);
        };
        reader.readAsDataURL(file);
    });

    $('#cropperClose, #cropperCancelar').on('click', cerrarCropper);

    function cerrarCropper() {
        $('#cropperModal').hide();
        if (cropper) { cropper.destroy(); cropper = null; }
        $('#cropperImg').attr('src', '');
    }

    $('#cropperGuardar').on('click', function () {
        if (!cropper) return;

        const canvas = cropper.getCroppedCanvas({
            width: 600, height: 600,
            imageSmoothingQuality: 'high'
        });

        canvas.toBlob(function (blob) {
            const articuloId = parseInt($('input[name="id"]').val(), 10);
            const fd = new FormData();
            fd.append('articulo_id', articuloId);
            fd.append('imagen', blob, 'imagen.jpg');
            fd.append('nombre_original', $('#imagenInput')[0].files[0].name);

            const $btn = $('#cropperGuardar');
            const original = $btn.text();
            $btn.prop('disabled', true).text('Subiendo...');

            $.ajax({
                url: BASE_URL + 'articulos/imagen/subir',
                type: 'POST', dataType: 'json',
                data: fd, processData: false, contentType: false
            })
            .done(function (res) {
                if (!res.ok) { showMsg('error', res.msg); return; }
                $('#imagenPreview').html('<img src="' + res.path + '?t=' + Date.now() + '" alt="Imagen">');
                if (!$('#btnEliminarImagen').length) {
                    $('.art-imagen-acciones').append(
                        '<button type="button" class="btn btn-outline" id="btnEliminarImagen" ' +
                        'style="color:#B23A48;border-color:rgba(178,58,72,.3);">Eliminar imagen</button>'
                    );
                }
                cerrarCropper();
                showMsg('ok', 'Imagen actualizada.');
            })
            .fail(function () { showMsg('error', 'Error al subir la imagen.'); })
            .always(function () { $btn.prop('disabled', false).text(original); });
        }, 'image/jpeg', 0.9);
    });

    $(document).on('click', '#btnEliminarImagen', function () {
        if (!confirm('¿Eliminar la imagen del artículo?')) return;
        const articuloId = parseInt($('input[name="id"]').val(), 10);

        $.ajax({
            url: BASE_URL + 'articulos/imagen/eliminar',
            type: 'POST', dataType: 'json',
            data: { articulo_id: articuloId }
        })
        .done(function (res) {
            if (res.ok) {
                $('#imagenPreview').html(
                    '<div class="art-imagen-vacia">' +
                    '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                    '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>' +
                    '<path d="m21 15-5-5L5 21"/></svg><span>Sin imagen</span></div>'
                );
                $('#btnEliminarImagen').remove();
                $('#btnSubirImagen').text('Subir imagen');
                showMsg('ok', res.msg);
            }
        });
    });

    /* ---------- EXPORTAR / IMPRIMIR / PDF ---------- */
    $('#btnExportarExcel').on('click', function () {
        const p = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'articulos/exportar?' + p;
    });
    $('#btnImprimir').on('click', function () {
        const p = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.open(BASE_URL + 'articulos/imprimir?' + p, '_blank');
    });
    $('#btnPdf').on('click', function () {
        const p = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'articulos/pdf?' + p;
    });

})(jQuery);