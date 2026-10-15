/**
 * dist/js/facturas.js
 */
(function ($) {
    'use strict';

    let timer = null;
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

    function condicionLabel(c) {
        return c === 'GRAVADO' ? 'Gravado'
             : c === 'NO_GRAVADO' ? 'No gravado'
             : 'Exento';
    }

    function alicuotaLabel(v) {
        const n = parseFloat(v) || 0;
        return n.toLocaleString('es-AR', {maximumFractionDigits: 2}) + '%';
    }

    /* ============================================================
       LISTADO — tabs
       ============================================================ */
    $('.fac-tab').on('click', function () {
        const tab = $(this).data('tab');
        $('.fac-tab').removeClass('active');
        $(this).addClass('active');
        $('.fac-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    $('#ped_search').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(buscarPedidos, 300);
    });

    function buscarPedidos() {
        $.ajax({
            url: BASE_URL + 'facturas/buscar-pedidos',
            type: 'POST',
            dataType: 'json',
            data: { search: $('#ped_search').val() || '' }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#pedidosTableBody').html(res.html);
                $('.fac-tab[data-tab="pedidos"] .fac-tab-count').text(res.total);
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    $('#fac_desde, #fac_hasta, #fac_emisor, #fac_arca, #fac_estado').on('change', buscarFacturas);
    $('#fac_busqueda').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(buscarFacturas, 300);
    });

    function buscarFacturas() {
        $.ajax({
            url: BASE_URL + 'facturas/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                desde:       $('#fac_desde').val() || '',
                hasta:       $('#fac_hasta').val() || '',
                emisor_id:   $('#fac_emisor').val() || 0,
                busqueda:    $('#fac_busqueda').val() || '',
                estado:      $('#fac_estado').val() || '',
                arca_estado: $('#fac_arca').val() || '',
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#facturasTableBody').html(res.html);
                $('#fac_total').text(res.total);
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    $('#btnExportarExcel').on('click', function () {
        const p = $.param({
            desde:       $('#fac_desde').val() || '',
            hasta:       $('#fac_hasta').val() || '',
            emisor_id:   $('#fac_emisor').val() || 0,
            busqueda:    $('#fac_busqueda').val() || '',
            arca_estado: $('#fac_arca').val() || '',
        });
        window.location.href = BASE_URL + 'facturas/exportar?' + p;
    });

    /* ============================================================
       FORM — carga desde pedido
       ============================================================ */
    if (typeof window.FACTURA_DETALLE_INICIAL !== 'undefined'
        && Array.isArray(window.FACTURA_DETALLE_INICIAL)) {
        detalle = window.FACTURA_DETALLE_INICIAL.slice();
        renderDetalle();
    }

    /* ============================================================
       FORM — venta inmediata
       ============================================================ */
    const $form = $('#facturaForm');
    const esVentaInmediata = $('input[name="pedido_id"]').val() === '0';

    if (esVentaInmediata) {
        $('#cliente_id, #categoria_override').on('change', function () {
            const catId = $('#categoria_override').val();
            const catTxt = catId
                ? $('#categoria_override option:selected').text().trim()
                : ($('#cliente_id option:selected').data('categoria') || '');

            $('#pedClienteNombre').text(
                ($('#cliente_id option:selected').text().split('·')[0] || 'Sin elegir').trim()
            );

            if (detalle.length > 0) {
                if (!confirm('Cambiaste el cliente/categoría. ¿Recargar los precios del detalle?')) {
                    return;
                }
                recargarPreciosDetalle();
            }
        });

        $('#add_articulo').on('change', function () {
            const $opt = $(this).find('option:selected');
            const artId = parseInt($(this).val(), 10);

            const pkId = parseInt($opt.data('pakaging-id') || 0, 10);
            const pkDesc = $opt.data('pakaging-desc') || '';
            const pkFactor = parseFloat($opt.data('pakaging-factor') || 1);

            const $pak = $('#add_pakaging');
            $pak.empty().append('<option value="">Unidad</option>');
            if (pkId > 0 && pkDesc) {
                $pak.append($('<option>', { value: pkId, 'data-factor': pkFactor, text: pkDesc }));
                $pak.val(pkId);
            }
            $pak.prop('disabled', $pak.find('option').length <= 1);

            if (!artId) return;
            cargarPrecioDesdeCliente(artId, 0);
        });

        $('#add_sin_cargo').on('change', function () {
            const on = this.checked;
            $('#add_precio, #add_desc').prop('disabled', on);
            if (on) { $('#add_precio').val('0'); $('#add_desc').val('0'); }
        });

        $('#add_condicion_iva').on('change', function () {
            const noGrav = $(this).val() !== 'GRAVADO';
            $('#add_alicuota').prop('disabled', noGrav);
        });

        $('#btnAgregarArticulo').on('click', function () {
            agregarLineaDesdeForm();
        });
    }

    function cargarPrecioDesdeCliente(articuloId, index) {
        const clienteId = parseInt($('#cliente_id').val(), 10);
        const catOverride = parseInt($('#categoria_override').val(), 10) || 0;
        if (!clienteId) return;

        $.ajax({
            url: BASE_URL + 'pedidos/precio-articulo',
            type: 'POST',
            dataType: 'json',
            data: {
                articulo_id: articuloId,
                cliente_id:  clienteId,
                categoria_id: catOverride || undefined
            }
        })
        .done(function (res) {
            if (!res.ok) return;

            const $precio = index === 0 ? $('#add_precio') : null;
            if ($precio && res.tiene_precio) {
                $precio.val(Number(res.precio_unitario).toFixed(2));
            } else if ($precio) {
                $precio.val('0.00');
                $('#facAvisoPrecio')
                    .text('⚠ Este artículo no tiene precio para la categoría "' + (res.categoria_desc || '') + '".')
                    .show();
            }

            if (index > 0 && detalle[index - 1] && res.tiene_precio) {
                detalle[index - 1].precio_unitario = res.precio_unitario;
            }
        });
    }

    function recargarPreciosDetalle() {
        const clienteId = parseInt($('#cliente_id').val(), 10);
        const catOverride = parseInt($('#categoria_override').val(), 10) || 0;
        if (!clienteId) return;

        detalle.forEach(function (d, i) {
            $.ajax({
                url: BASE_URL + 'pedidos/precio-articulo',
                type: 'POST',
                dataType: 'json',
                data: {
                    articulo_id: d.articulo_id,
                    cliente_id:  clienteId,
                    categoria_id: catOverride || undefined
                },
                success: function (res) {
                    if (res.ok && res.tiene_precio) {
                        detalle[i].precio_unitario = res.precio_unitario;
                    }
                }
            });
        });
        setTimeout(renderDetalle, 500);
    }

    function agregarLineaDesdeForm() {
        const $opt = $('#add_articulo').find('option:selected');
        const articuloId = parseInt($('#add_articulo').val(), 10);
        const cantidad = parseFloat($('#add_cantidad').val()) || 0;
        const pkId = $('#add_pakaging').val() ? parseInt($('#add_pakaging').val(), 10) : null;
        const pkFactor = pkId ? (parseFloat($('#add_pakaging option:selected').data('factor')) || 1) : 1;
        const sinCargo = $('#add_sin_cargo').is(':checked') ? 1 : 0;
        const precio = sinCargo ? 0 : (parseFloat($('#add_precio').val()) || 0);
        const desc = sinCargo ? 0 : (parseFloat($('#add_desc').val()) || 0);
        const cond = $('#add_condicion_iva').val() || 'GRAVADO';
        const alic = cond === 'GRAVADO'
                      ? parseFloat($('#add_alicuota').val() || 21)
                      : 0;

        if (!articuloId) { showMsg('error', 'Seleccioná un artículo.'); return; }
        if (cantidad <= 0) { showMsg('error', 'La cantidad debe ser mayor a 0.'); return; }

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
            condicion_iva:        cond,
            alicuota_iva:         alic,
        };

        const idx = detalle.findIndex(x => x.articulo_id === articuloId && (x.pakaging_id || null) === pkId);
        if (idx >= 0) {
            detalle[idx].cantidad_pakaging += cantidad;
            detalle[idx].cantidad_unidades += cantidad * pkFactor;
            detalle[idx].precio_unitario = precio;
            detalle[idx].descuento_porcentaje = desc;
            detalle[idx].condicion_iva = cond;
            detalle[idx].alicuota_iva = alic;
        } else {
            detalle.push(linea);
        }

        renderDetalle();

        $('#add_articulo').val('');
        $('#add_cantidad').val(1);
        $('#add_pakaging').empty().append('<option value="">Unidad</option>').prop('disabled', true).val('');
        $('#add_precio').val('0');
        $('#add_desc').val('0');
        $('#add_sin_cargo').prop('checked', false);
        $('#add_precio, #add_desc').prop('disabled', false);
        $('#add_condicion_iva').val('GRAVADO');
        $('#add_alicuota').val('21').prop('disabled', false);
        $('#facAvisoPrecio').hide();
    }

    /* ============================================================
       RENDER DEL DETALLE
       ============================================================ */
    function renderDetalle() {
        const $tbody = $('#detalleBody');
        if (!$tbody.length) return;

        if (detalle.length === 0) {
            $tbody.html('<tr class="fila-vacia"><td colspan="11" style="text-align:center;color:#7A7266;padding:1.5rem;">' +
                'Todavía no hay artículos.</td></tr>');
            calcularTotales();
            return;
        }

        let html = '';
        detalle.forEach(function (d, i) {
            const sinCargo = d.sin_precio === 1;
            const sub = sinCargo ? 0
                : d.cantidad_unidades * d.precio_unitario * (1 - (d.descuento_porcentaje || 0) / 100);
            const cond = d.condicion_iva || 'GRAVADO';
            const alic = parseFloat(d.alicuota_iva || 0);
            const impIva = (sinCargo || cond !== 'GRAVADO') ? 0
                : sub * alic / 100;

            html += '<tr data-index="' + i + '"' + (sinCargo ? ' class="fila-sin-cargo"' : '') + '>';
            html += '<td><strong>' + escapeHtml(d.articulo_nombre) + '</strong>';
            if (sinCargo) html += ' <span class="badge-sin-cargo">S/C</span>';
            if (d.articulo_codigo) {
                html += '<br><span style="font-size:12px;color:#7A7266;">Cód: ' + escapeHtml(d.articulo_codigo) + '</span>';
            }
            html += '</td>';
            html += '<td style="text-align:center;font-size:12px;">' + escapeHtml(d.pakaging_desc) + '</td>';
            html += '<td style="text-align:right;">' + Number(d.cantidad_pakaging).toLocaleString('es-AR') + '</td>';
            html += '<td style="text-align:right;">' + Number(d.cantidad_unidades).toLocaleString('es-AR') + '</td>';
            if (sinCargo) {
                html += '<td colspan="3" style="text-align:center;color:#0F5C52;font-style:italic;font-weight:600;">Sin cargo</td>';
            } else {
                html += '<td style="text-align:right;">' + fmtMoney(d.precio_unitario) + '</td>';
                html += '<td style="text-align:right;">' + Number(d.descuento_porcentaje || 0).toFixed(2) + '%</td>';
                html += '<td style="text-align:right;font-weight:600;">' + fmtMoney(sub) + '</td>';
            }
            html += '<td>' + condicionLabel(cond) + '</td>';
            html += '<td style="text-align:right;">' + alicuotaLabel(alic) + '</td>';
            html += '<td style="text-align:right;">' + fmtMoney(impIva) + '</td>';
            html += '<td style="text-align:right;width:50px;">';
            html += '<button type="button" class="btn-icon btn-quitar-linea" data-index="' + i + '" title="Quitar">';
            html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<path d="M18 6 6 18M6 6l12 12"/></svg></button>';
            html += '</td></tr>';
        });
        $tbody.html(html);
        calcularTotales();
    }

    $('#detalleBody').on('click', '.btn-quitar-linea', function () {
        const idx = parseInt($(this).data('index'), 10);
        if (isNaN(idx)) return;
        detalle.splice(idx, 1);
        renderDetalle();
    });

    $('#descuento_general_porcentaje, #perc_iva, #perc_iibb').on('input change', calcularTotales);

    function calcularTotales() {
        let subtotal = 0, netoGrav = 0, netoNoGrav = 0, netoExento = 0, ivaDet = 0;

        detalle.forEach(function (d) {
            if (d.sin_precio === 1) return;
            const linea = d.cantidad_unidades * d.precio_unitario * (1 - (d.descuento_porcentaje || 0) / 100);
            subtotal += linea;
            const cond = d.condicion_iva || 'GRAVADO';
            if (cond === 'GRAVADO') {
                netoGrav += linea;
                ivaDet += linea * (parseFloat(d.alicuota_iva) || 0) / 100;
            } else if (cond === 'NO_GRAVADO') {
                netoNoGrav += linea;
            } else {
                netoExento += linea;
            }
        });

        const desc = parseFloat($('#descuento_general_porcentaje').val() || 0);
        const factor = 1 - desc / 100;

        subtotal   = Math.round(subtotal   * factor * 100) / 100;
        netoGrav   = Math.round(netoGrav   * factor * 100) / 100;
        netoNoGrav = Math.round(netoNoGrav * factor * 100) / 100;
        netoExento = Math.round(netoExento * factor * 100) / 100;
        ivaDet     = Math.round(ivaDet     * factor * 100) / 100;

        const percIva  = parseFloat($('#perc_iva').val()  || 0);
        const percIibb = parseFloat($('#perc_iibb').val() || 0);

        const total = subtotal + ivaDet + percIva + percIibb;

        $('#pedSubtotal').text(fmtMoney(subtotal));
        $('#pedIva').text(fmtMoney(ivaDet));
        $('#pedTotal').text(fmtMoney(total));

        $('#facNetoGrav').text(fmtMoney(netoGrav));
        $('#facIva').text(fmtMoney(ivaDet));
    }

    /* ============================================================
       SUBMIT
       ============================================================ */
    if ($form.length) {
        $form.on('submit', function (e) {
            e.preventDefault();

            if (detalle.length === 0) {
                showMsg('error', 'Cargá al menos un artículo.');
                return;
            }

            // Validación: alícuotas mixtas
            const alicuotas = new Set();
            detalle.forEach(function (d) {
                if (d.sin_precio === 1) return;
                if ((d.condicion_iva || 'GRAVADO') !== 'GRAVADO') return;
                alicuotas.add(parseFloat(d.alicuota_iva || 0));
            });
            if (alicuotas.size > 1) {
                showMsg('error',
                    'ARCA no permite alícuotas mixtas. ' +
                    'Separá la factura en dos: una con cada alícuota.');
                return;
            }

            const $btn = $form.find('button[type="submit"]');
            const original = $btn.text();
            $btn.prop('disabled', true).text('Guardando...');

            const data = $form.serializeArray();
            data.push({ name: 'detalle', value: JSON.stringify(detalle) });

            $.ajax({
                url: BASE_URL + 'facturas/crear',
                type: 'POST',
                dataType: 'json',
                data: $.param(data)
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                    setTimeout(function () {
                        window.location.href = res.redirect || (BASE_URL + 'facturas');
                    }, 800);
                } else {
                    showMsg('error', res.msg || 'Error al guardar.');
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
    }

    /* ============================================================
       VER — acciones
       ============================================================ */
    $('#btnEnviarArca').on('click', function () {
        const id = $(this).data('id');
        if (!confirm('¿Enviar la factura a ARCA para obtener el CAE?\n\nEsto puede tardar unos segundos.')) return;

        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: BASE_URL + 'facturas/enviar-arca',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                showMsg('error', res.msg || 'No se pudo enviar a ARCA.');
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

    $('#btnAnularFactura').on('click', function () {
        const id = $(this).data('id');
        const info = $(this).data('info');

        const motivo = prompt(
            'Vas a anular: ' + info + '\n\n' +
            'Se va a REVERTIR el stock de artículos e insumos.\n\n' +
            'Motivo de la anulación (opcional):'
        );
        if (motivo === null) return;

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'facturas/anular',
            type: 'POST',
            dataType: 'json',
            data: { id: id, motivo: motivo || '' }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                showMsg('error', res.msg || 'No se pudo anular.');
            }
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); })
        .always(function () { $btn.prop('disabled', false); });
    });

})(jQuery);