(function ($) {
    'use strict';

    let timer = null;
    const soloLectura = $('#insumoForm').data('solo-lectura') === 1;

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    /* ---------- BUSCADOR (listado) ---------- */
    $('#status').on('change', listarInsumos);
    $('#search').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(listarInsumos, 300);
    });

    function listarInsumos() {
        $.ajax({
            url:  BASE_URL + 'insumos/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                search: $('#search').val() || '',
                status: $('#status').val() || ''
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#insumosTableBody').html(res.html);
            } else {
                showMsg('error', res.msg || 'No se pudieron cargar los datos.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de conexión con el servidor.');
        });
    }

    /* ---------- ELIMINAR INSUMO ---------- */
    $('#insumosTableBody').on('click', '.btn-eliminar', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        if (!confirm('¿Desactivar el insumo "' + nombre + '"?\n\nQuedará inactivo.')) return;

        $.ajax({
            url:  BASE_URL + 'insumos/eliminar',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(listarInsumos, 600);
            } else {
                showMsg('error', res.msg || 'No se pudo eliminar.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de comunicación.');
        });
    });

    /* ---------- TOGGLE: es_alcoholico ---------- */
    $('#es_alcoholico').on('change', function () {
        if (this.checked) {
            $('#bloqueImpuestosInternos').slideDown(150);
        } else {
            $('#bloqueImpuestosInternos').slideUp(150);
        }
    });

    /* ---------- FORM INSUMO ---------- */
    const $form = $('#insumoForm');
    if ($form.length && !soloLectura) {
        $form.on('submit', function (e) {
            e.preventDefault();
            const $btn = $form.find('button[type="submit"]');
            const original = $btn.text();
            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url:  BASE_URL + 'insumos/' + ($('input[name="id"]').val()
                        ? 'editar?id=' + $('input[name="id"]').val()
                        : 'crear'),
                type: 'POST',
                dataType: 'json',
                data: $form.serialize()
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                    setTimeout(function () {
                        window.location.href = res.redirect || (BASE_URL + 'insumos');
                    }, 800);
                } else {
                    showMsg('error', res.msg || 'Error al guardar.');
                }
            })
            .fail(function (xhr) {
                const m = (xhr.responseJSON && xhr.responseJSON.msg)
                          || 'Error de comunicación con el servidor.';
                showMsg('error', m);
            })
            .always(function () {
                $btn.prop('disabled', false).text(original);
            });
        });
    }

    /* ---------- AGREGAR PROVEEDOR ---------- */
    $('#btnAgregarProveedor').on('click', function () {
        const insumoId    = parseInt($('input[name="id"]').val(), 10);
        const proveedorId = parseInt($('#prov_select').val(), 10);
        const costo       = parseFloat($('#prov_costo').val());
        const codigoProv  = $('#prov_codigo').val() || '';

        if (!proveedorId) { showMsg('error', 'Seleccioná un proveedor.'); return; }
        if (!(costo > 0)) { showMsg('error', 'Ingresá un costo mayor a 0.'); return; }

        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Agregando...');

        $.ajax({
            url:  BASE_URL + 'insumos/proveedor/agregar',
            type: 'POST',
            dataType: 'json',
            data: {
                insumo_id:        insumoId,
                proveedor_id:     proveedorId,
                costo:            costo,
                codigo_proveedor: codigoProv
            }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudo agregar.');
                return;
            }

            // Quitar fila vacía si existe
            $('#proveedoresInsumoBody .fila-vacia').remove();

            const $existente = $('#proveedoresInsumoBody tr[data-row-id="' + res.rowId + '"]');
            if ($existente.length) {
                $existente.replaceWith(res.html);
            } else {
                $('#proveedoresInsumoBody').append(res.html);
            }

            actualizarCostoDisplay(res.costo);

            // ← NUEVO: refrescar historial
            if (res.historial_html !== undefined) {
                $('#historialCostoBody').html(res.historial_html);
            }

            $('#prov_select').val('');
            $('#prov_costo').val('');
            $('#prov_codigo').val('');

            showMsg('ok', res.msg);
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    /* ---------- ELIMINAR PROVEEDOR ---------- */
    $('#proveedoresInsumoBody').on('click', '.btn-eliminar-prov', function () {
        const id       = $(this).data('id');
        const insumoId = parseInt($('input[name="id"]').val(), 10);

        if (!confirm('¿Eliminar este proveedor del insumo?')) return;

        $.ajax({
            url:  BASE_URL + 'insumos/proveedor/eliminar',
            type: 'POST',
            dataType: 'json',
            data: {
                id: id,
                insumo_id: insumoId
            }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudo eliminar.');
                return;
            }

            $('#proveedoresInsumoBody tr[data-row-id="' + id + '"]').remove();

            if ($('#proveedoresInsumoBody tr').length === 0) {
                $('#proveedoresInsumoBody').append(
                    '<tr class="fila-vacia"><td colspan="4" style="text-align:center;color:#7A7266;padding:1.5rem;">' +
                    'Todavía no hay proveedores cargados.</td></tr>'
                );
            }

            actualizarCostoDisplay(res.costo);

            // ← NUEVO: refrescar historial
            if (res.historial_html !== undefined) {
                $('#historialCostoBody').html(res.historial_html);
            }

            showMsg('ok', res.msg);
        })
        .fail(function () {
            showMsg('error', 'Error de comunicación.');
        });
    });

    function actualizarCostoDisplay(costo) {
        const fmt = '$ ' + Number(costo).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        $('#costoDisplay').text(fmt);
    }

    /* ---------- EXPORTAR / IMPRIMIR / PDF ---------- */
    $('#btnExportarExcel').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'insumos/exportar?' + params;
    });
    $('#btnImprimir').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.open(BASE_URL + 'insumos/imprimir?' + params, '_blank');
    });
    $('#btnPdf').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'insumos/pdf?' + params;
    });

        /* ---------- EDITAR PROVEEDOR INLINE ---------- */
    function entrarModoEdicion($fila) {
        // Cancelar cualquier otra fila en edición
        cancelarEdicion();

        $fila.addClass('fila-editando');
        $fila.find('.cell-costo .valor, .cell-codigo .valor').hide();
        $fila.find('.cell-costo .editor, .cell-codigo .editor').show();
        $fila.find('.acciones-normal').hide();
        $fila.find('.acciones-edicion').show();
        $fila.find('.cell-costo .editor').focus();
    }

    function salirModoEdicion($fila) {
        $fila.removeClass('fila-editando');
        $fila.find('.cell-costo .editor, .cell-codigo .editor').hide();
        $fila.find('.cell-costo .valor, .cell-codigo .valor').show();
        $fila.find('.acciones-edicion').hide();
        $fila.find('.acciones-normal').show();
    }

    function cancelarEdicion() {
        $('.fila-editando').each(function () {
            salirModoEdicion($(this));
        });
    }

    $('#proveedoresInsumoBody').on('click', '.btn-editar-prov', function () {
        const $fila = $(this).closest('tr');
        entrarModoEdicion($fila);
    });

    $('#proveedoresInsumoBody').on('click', '.btn-cancelar-prov', function () {
        const $fila = $(this).closest('tr');
        salirModoEdicion($fila);
    });

    $('#proveedoresInsumoBody').on('click', '.btn-guardar-prov', function () {
        const $fila    = $(this).closest('tr');
        const rowId    = parseInt($fila.data('row-id'), 10);
        const insumoId = parseInt($('input[name="id"]').val(), 10);
        const costo    = parseFloat($fila.find('.cell-costo .editor').val());
        const codigo   = $fila.find('.cell-codigo .editor').val() || '';

        if (!(costo > 0)) {
            showMsg('error', 'El costo debe ser mayor a 0.');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url:  BASE_URL + 'insumos/proveedor/actualizar',
            type: 'POST',
            dataType: 'json',
            data: {
                id:               rowId,
                insumo_id:        insumoId,
                costo:            costo,
                codigo_proveedor: codigo
            }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudo actualizar.');
                return;
            }

            $fila.replaceWith(res.html);
            actualizarCostoDisplay(res.costo);

            // ← NUEVO: refrescar historial
            if (res.historial_html !== undefined) {
                $('#historialCostoBody').html(res.historial_html);
            }

            showMsg('ok', res.msg);
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Cancelar la edición si el usuario hace click afuera del mini-form
    $(document).on('click', function (e) {
        if ($(e.target).closest('.fila-editando').length) return;
        if ($(e.target).closest('.btn-editar-prov').length) return;
        cancelarEdicion();
    });

    // ESC cancela la edición
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') cancelarEdicion();
    });

    // Enter guarda (dentro de la fila en edición)
    $('#proveedoresInsumoBody').on('keydown', 'input.editor', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('tr').find('.btn-guardar-prov').trigger('click');
        }
    });

    /* ---------- HISTORIAL DE COSTOS ---------- */
    function cargarHistorial() {
        const $tbody = $('#historialCostoBody');
        if (!$tbody.length) return;

        const insumoId = parseInt($('input[name="id"]').val(), 10);
        if (!insumoId) return;

        $.ajax({
            url:  BASE_URL + 'insumos/historial',
            type: 'GET',
            dataType: 'json',
            data: { insumo_id: insumoId }
        })
        .done(function (res) {
            if (res.ok) {
                $tbody.html(res.html);
            } else {
                $tbody.html('<tr><td colspan="5" style="text-align:center;color:#B23A48;padding:1rem;">' +
                            (res.msg || 'Error al cargar historial.') + '</td></tr>');
            }
        })
        .fail(function () {
            $tbody.html('<tr><td colspan="5" style="text-align:center;color:#B23A48;padding:1rem;">' +
                        'Error de conexión.</td></tr>');
        });
    }

    // Cargar al inicio si estamos en edición
    if ($('#historialCostoBody').length) {
        cargarHistorial();
    }

    // Anular entrada
    $('#historialCostoBody').on('click', '.btn-anular-hist', function () {
        const id = $(this).data('id');

        if (!confirm('¿Anular este registro del historial?\n\nSe recalculará automáticamente la cadena de costos.')) {
            return;
        }

        $.ajax({
            url:  BASE_URL + 'insumos/historial/anular',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                cargarHistorial();
            } else {
                showMsg('error', res.msg || 'No se pudo anular.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de comunicación.');
        });
    });

})(jQuery);