/**
 * dist/js/pedidos.js
 * Módulo Pedidos — Listado + Form crear/editar/ver
 */
(function ($) {
    'use strict';

    let timer = null;
    const $form      = $('#pedidoForm');
    const modo       = $form.data('modo');
    const esVer      = modo === 'ver';
    const sinPrecios = $form.data('sin-precios') === 1 || $form.data('sin-precios') === '1';

    let detalle = [];

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    function fmtMoney(n) {
        return '$ ' + Number(n).toLocaleString('es-AR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    /* ============================================================
       LISTADO DE PEDIDOS
       ============================================================ */
    if ($('#pedidosTableBody').length) {
        $('#filtro_estado, #filtro_desde, #filtro_hasta').on('change', listarPedidos);
        $('#filtro_search').on('keyup input', function () {
            clearTimeout(timer);
            timer = setTimeout(listarPedidos, 300);
        });
    }

    function listarPedidos() {
        $.ajax({
            url: BASE_URL + 'pedidos/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                search: $('#filtro_search').val() || '',
                estado: $('#filtro_estado').val() || '',
                desde:  $('#filtro_desde').val() || '',
                hasta:  $('#filtro_hasta').val() || '',
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#pedidosTableBody').html(res.html);
                $('#totalPedidos').text(res.total);
            } else {
                showMsg('error', res.msg || 'Error al cargar.');
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    /* ============================================================
       FORM — carga del detalle inicial (modo editar/ver)
       ============================================================ */
    if (typeof window.PEDIDO_DETALLE_INICIAL !== 'undefined'
        && Array.isArray(window.PEDIDO_DETALLE_INICIAL)) {
        detalle = window.PEDIDO_DETALLE_INICIAL.slice();
        if ($('#detalleBody').length) {
            renderDetalle();
        }
    }

    /* ============================================================
       EXPORTAR — siempre disponible
       ============================================================ */
    function bindExportar() {
        $('#btnExportarExcel').on('click', function () {
            const p = $.param({
                search: $('#filtro_search').val() || '',
                estado: $('#filtro_estado').val() || '',
                desde:  $('#filtro_desde').val() || '',
                hasta:  $('#filtro_hasta').val() || '',
            });
            window.location.href = BASE_URL + 'pedidos/exportar?' + p;
        });
    }
    bindExportar();

    /* ============================================================
       Si es modo VER, no bindeamos handlers de edición
       ============================================================ */
    if (!$form.length || esVer) {
        return;
    }

    /* ============================================================
       FILTRO DE BÚSQUEDA DE ARTÍCULOS
       ============================================================ */
    $('#buscar_articulo').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#add_articulo option').each(function () {
            if ($(this).val() === '') return;
            const txt = $(this).text().toLowerCase();
            $(this).toggle(txt.indexOf(q) !== -1);
        });
    });

    /* ============================================================
       AL ELEGIR ARTÍCULO — repoblar empaque y cargar precio
       ============================================================ */
    $('#add_articulo').on('change', function () {
        const $opt       = $(this).find('option:selected');
        const articuloId = parseInt($(this).val(), 10);
        const clienteId  = parseInt($('#cliente_id').val(), 10);

        // Reset aviso y checkbox
        $('#pedAvisoPrecio').hide().text('');
        $('#add_sin_cargo').prop('checked', false).trigger('change');

        // --- Repoblar select de empaque según el artículo elegido ---
        const pkId     = parseInt($opt.data('pakaging-id') || 0, 10);
        const pkDesc   = $opt.data('pakaging-desc') || '';
        const pkFactor = parseFloat($opt.data('pakaging-factor') || 1);

        const $pakaging = $('#add_pakaging');
        $pakaging.empty();
        $pakaging.append('<option value="">Unidad</option>');

        if (pkId > 0 && pkDesc) {
            $pakaging.append(
                $('<option>', {
                    value: pkId,
                    'data-factor': pkFactor,
                    text: pkDesc
                })
            );
            $pakaging.val(pkId);
        } else {
            $pakaging.val('');
        }

        // Deshabilitar si solo hay "Unidad"
        $pakaging.prop('disabled', $pakaging.find('option').length <= 1);

        // --- Pedir precio al servidor ---
        if (!articuloId || !clienteId || sinPrecios) return;

        $.ajax({
            url: BASE_URL + 'pedidos/precio-articulo',
            type: 'POST',
            dataType: 'json',
            data: { articulo_id: articuloId, cliente_id: clienteId }
        })
        .done(function (res) {
            if (!res.ok) return;

            if (res.tiene_precio) {
                $('#add_precio').val(Number(res.precio_unitario).toFixed(2));
            } else {
                $('#add_precio').val('0.00');
                $('#pedAvisoPrecio')
                    .text('⚠ Este artículo no tiene precio cargado para la categoría "' +
                          res.categoria_desc + '". Verificá el precio antes de guardar.')
                    .show();
            }
        });
    });

    /* ============================================================
       AL CAMBIAR CLIENTE — avisar si hay artículos cargados
       ============================================================ */
    $('#cliente_id').on('change', function () {
        const nombre = $(this).find('option:selected').text() || 'Sin elegir';
        $('#pedClienteNombre').text(nombre.split('·')[0].trim() || 'Sin elegir');

        if (detalle.length > 0) {
            if (!confirm('Cambiaste el cliente. Los precios de los artículos ya cargados pueden quedar desactualizados.\n\n¿Querés que se recarguen automáticamente? (Si decís No, se mantienen los precios actuales)')) {
                return;
            }
            detalle.forEach(function (l) {
                $.ajax({
                    url: BASE_URL + 'pedidos/precio-articulo',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        articulo_id: l.articulo_id,
                        cliente_id:  parseInt($('#cliente_id').val(), 10)
                    },
                    success: function (r) {
                        if (r.ok && r.tiene_precio) {
                            l.precio_unitario = r.precio_unitario;
                        }
                    }
                });
            });
            setTimeout(renderDetalle, 400);
        }
    });

    /* ============================================================
       CHECKBOX "SIN CARGO"
       ============================================================ */
    $('#add_sin_cargo').on('change', function () {
        const sinCargo = this.checked;
        $('#add_precio').prop('disabled', sinCargo);
        $('#add_desc').prop('disabled', sinCargo);
        if (sinCargo) {
            $('#add_precio').val('0');
            $('#add_desc').val('0');
        }
    });

    /* ============================================================
       BOTÓN AGREGAR ARTÍCULO
       ============================================================ */
    $('#btnAgregarArticulo').on('click', function () {
        const $opt       = $('#add_articulo').find('option:selected');
        const articuloId = parseInt($('#add_articulo').val(), 10);
        const cantidad   = parseFloat($('#add_cantidad').val()) || 0;
        const pkId       = $('#add_pakaging').val()
                            ? parseInt($('#add_pakaging').val(), 10)
                            : null;
        const pkFactor   = pkId
                            ? (parseFloat($('#add_pakaging option:selected').data('factor')) || 1)
                            : 1;
        const sinCargo   = $('#add_sin_cargo').is(':checked') ? 1 : 0;
        const precio     = sinCargo ? 0 : (parseFloat($('#add_precio').val()) || 0);
        const desc       = sinCargo ? 0 : (parseFloat($('#add_desc').val()) || 0);

        if (!articuloId)    { showMsg('error', 'Seleccioná un artículo.'); return; }
        if (cantidad <= 0)  { showMsg('error', 'La cantidad debe ser mayor a 0.'); return; }

        const linea = {
            articulo_id:          articuloId,
            articulo_nombre:      $opt.data('nombre'),
            articulo_codigo:      $opt.data('codigo'),
            pakaging_id:          pkId,
            pakaging_desc:        pkId ? $('#add_pakaging option:selected').text().trim() : 'Unidad',
            cantidad_pakaging:    cantidad,
            cantidad_unidades:    cantidad * pkFactor,
            precio_unitario:      precio,
            descuento_porcentaje: desc,
            sin_precio:           sinCargo,
        };

        const idx = detalle.findIndex(x =>
            x.articulo_id === articuloId && (x.pakaging_id || null) === pkId
        );
        if (idx >= 0) {
            detalle[idx].cantidad_pakaging += cantidad;
            detalle[idx].cantidad_unidades += cantidad * pkFactor;
            detalle[idx].precio_unitario   = precio;
            detalle[idx].descuento_porcentaje = desc;
        } else {
            detalle.push(linea);
        }

        renderDetalle();

        // Limpiar formulario
        $('#add_articulo').val('');
        $('#add_cantidad').val(1);
        $('#add_pakaging').empty().append('<option value="">Unidad</option>')
                                 .prop('disabled', true).val('');
        $('#add_precio').val('0');
        $('#add_desc').val('0');
        $('#pedAvisoPrecio').hide();
        $('#buscar_articulo').val('').trigger('input');
        $('#add_sin_cargo').prop('checked', false);
        $('#add_precio').prop('disabled', false);
        $('#add_desc').prop('disabled', false);
    });

    /* ============================================================
       RENDER DEL DETALLE
       ============================================================ */
    function renderDetalle() {
        const $tbody = $('#detalleBody');
        if (!$tbody.length) return;

        const colspan = sinPrecios ? 5 : 8;

        if (detalle.length === 0) {
            $tbody.html('<tr class="fila-vacia"><td colspan="' + colspan +
                '" style="text-align:center;color:#7A7266;padding:1.5rem;">' +
                'Todavía no hay artículos en este pedido.</td></tr>');
            calcularTotales();
            return;
        }

        let html = '';
        detalle.forEach(function (d, i) {
            const sinCargo = d.sin_precio === 1;
            const sub = sinCargo ? 0
                : d.cantidad_unidades * d.precio_unitario * (1 - (d.descuento_porcentaje || 0) / 100);

            html += '<tr data-index="' + i + '"' + (sinCargo ? ' class="fila-sin-cargo"' : '') + '>';
            html += '<td><strong>' + escapeHtml(d.articulo_nombre) + '</strong>';
            if (sinCargo) {
                html += ' <span class="badge-sin-cargo">S/C</span>';
            }
            if (d.articulo_codigo) {
                html += '<br><span style="font-size:12px;color:#7A7266;">Cód: ' +
                        escapeHtml(d.articulo_codigo) + '</span>';
            }
            html += '</td>';
            html += '<td style="text-align:center;font-size:12px;">' + escapeHtml(d.pakaging_desc) + '</td>';
            html += '<td style="text-align:right;">' + Number(d.cantidad_pakaging).toLocaleString('es-AR') + '</td>';
            html += '<td style="text-align:right;">' + Number(d.cantidad_unidades).toLocaleString('es-AR') + '</td>';

            if (!sinPrecios) {
                if (sinCargo) {
                    html += '<td colspan="3" style="text-align:center;color:#0F5C52;font-style:italic;font-weight:600;">Sin cargo — no se factura</td>';
                } else {
                    html += '<td style="text-align:right;">' + fmtMoney(d.precio_unitario) + '</td>';
                    html += '<td style="text-align:right;">' + Number(d.descuento_porcentaje || 0).toFixed(2) + '%</td>';
                    html += '<td style="text-align:right;font-weight:600;">' + fmtMoney(sub) + '</td>';
                }
            }

            html += '<td style="text-align:right;width:60px;">';
            html += '<button type="button" class="btn-icon btn-quitar-linea" data-index="' + i + '" title="Quitar">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M18 6 6 18M6 6l12 12"/></svg></button>';
            html += '</td></tr>';
        });
        $tbody.html(html);
        calcularTotales();
    }

    /* ============================================================
       QUITAR LÍNEA
       ============================================================ */
    $('#detalleBody').on('click', '.btn-quitar-linea', function () {
        const idx = parseInt($(this).data('index'), 10);
        if (isNaN(idx)) return;
        detalle.splice(idx, 1);
        renderDetalle();
    });

    /* ============================================================
       TOTALES
       ============================================================ */
    $('#descuento_general_porcentaje').on('input change', calcularTotales);

    function calcularTotales() {
        let subtotal = 0;
        detalle.forEach(function (d) {
            if (d.sin_precio === 1) return;
            subtotal += d.cantidad_unidades * d.precio_unitario * (1 - (d.descuento_porcentaje || 0) / 100);
        });
        const descGral = parseFloat($('#descuento_general_porcentaje').val() || 0);
        const total = subtotal * (1 - descGral / 100);

        $('#pedSubtotal').text(fmtMoney(subtotal));
        $('#pedDescGral').text(Number(descGral).toFixed(2) + '%');
        $('#pedTotal').text(fmtMoney(total));
    }

    /* ============================================================
       SUBMIT
       ============================================================ */
    $form.on('submit', function (e) {
        e.preventDefault();

        if (detalle.length === 0) {
            showMsg('error', 'Cargá al menos un artículo.');
            return;
        }

        const $btn = $form.find('button[type="submit"]');
        const original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        const formData = $form.serializeArray();
        formData.push({ name: 'detalle', value: JSON.stringify(detalle) });

        const nuevoEstado = $('#estado_display').length ? $('#estado_display').val() : null;

        $.ajax({
            url: BASE_URL + 'pedidos/' + (modo === 'editar'
                    ? 'editar?id=' + $('input[name="id"]').val()
                    : 'crear'),
            type: 'POST',
            dataType: 'json',
            data: $.param(formData)
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'Error al guardar.');
                return;
            }

            const pedidoId = $('input[name="id"]').val();

            function redirigir() {
                showMsg('ok', res.msg);
                setTimeout(function () {
                    window.location.href = res.redirect || (BASE_URL + 'pedidos');
                }, 800);
            }

            if (nuevoEstado && pedidoId && modo === 'editar') {
                $.ajax({
                    url: BASE_URL + 'pedidos/cambiar-estado',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: pedidoId, estado: nuevoEstado }
                }).always(redirigir);
            } else {
                redirigir();
            }
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

        /**
     * Aplica al select de estado la clase de color correspondiente.
     */
    function actualizarColorEstado() {
        const $sel = $('#estado_display');
        if (!$sel.length) return;

        const val = ($sel.val() || '').toLowerCase();
        $sel.removeClass(
            'estado-recibido estado-procesando estado-preparado ' +
            'estado-facturado estado-entregado estado-anulado'
        );
        if (val) {
            $sel.addClass('estado-' + val);
        }
    }

        /* ============================================================
       COLOR DEL SELECT DE ESTADO
       ============================================================ */
    if ($('#estado_display').length) {
        actualizarColorEstado();
        $('#estado_display').on('change', actualizarColorEstado);
    }

})(jQuery);