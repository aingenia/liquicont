/**
 * dist/js/compras.js
 * Módulo Compras — listado, alta, edición, anulación y adjuntos.
 */
(function ($) {
    'use strict';

    let timer = null;
    const $form = $('#compraForm');
    const modo = $form.data('modo');
    const esVer = modo === 'ver';
    const esEditar = modo === 'editar';

    let detalle = [];

    /* ============================================================
       HELPERS
       ============================================================ */
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
        return $('<div>').text(s == null ? '' : s).html();
    }

    function alicuotaLabel(v) {
        const n = parseFloat(v) || 0;
        return n.toLocaleString('es-AR', {maximumFractionDigits: 2}) + '%';
    }

    function condicionLabel(c) {
        return c === 'GRAVADO' ? 'Gravado'
             : c === 'NO_GRAVADO' ? 'No gravado'
             : 'Exento';
    }

    /* ============================================================
       LISTADO
       ============================================================ */
    if ($('#comprasTableBody').length) {
        $('#filtro_desde, #filtro_hasta, #filtro_proveedor, #filtro_estado').on('change', listarCompras);
        $('#filtro_busqueda').on('keyup input', function () {
            clearTimeout(timer);
            timer = setTimeout(listarCompras, 300);
        });
    }

    function listarCompras() {
        $.ajax({
            url: BASE_URL + 'comprobantes/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                desde:        $('#filtro_desde').val(),
                hasta:        $('#filtro_hasta').val(),
                proveedor_id: $('#filtro_proveedor').val(),
                busqueda:     $('#filtro_busqueda').val(),
                estado:       $('#filtro_estado').val(),
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#comprasTableBody').html(res.html);
                $('#rangoInfo').text(res.desde);
                $('#rangoInfo2').text(res.hasta);
            } else {
                showMsg('error', res.msg || 'Error al cargar.');
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    /* ============================================================
       ANULAR
       ============================================================ */
    $('#comprasTableBody').on('click', '.btn-anular-compra', function () {
        const id   = $(this).data('id');
        const info = $(this).data('info');

        if (!confirm('¿Anular el comprobante?\n\n' + info +
                     '\n\nSe va a REVERTIR el stock de los insumos.\n\n' +
                     'Esta acción no se puede deshacer.')) return;

        $.ajax({
            url: BASE_URL + 'comprobantes/anular',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(listarCompras, 700);
            } else {
                showMsg('error', res.msg || 'No se pudo anular.');
            }
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); });
    });

    /* ============================================================
       FORM CREAR / EDITAR
       ============================================================ */
    if (!$form.length || esVer) return;

    /* ---------- Checkbox IVA manual ---------- */
    function aplicarModoIva() {
        const manual = $('#iva_manual_chk').is(':checked');
        if (manual) {
            $('#iva').prop('readonly', false).addClass('iva-manual');
        } else {
            $('#iva').prop('readonly', true).removeClass('iva-manual');
        }
    }
    $('#iva_manual_chk').on('change', function () {
        aplicarModoIva();
        recalcularTotales();
    });

    /* ---------- Alta de INSUMO ---------- */
    $('#add_insumo').on('change', function () {
        const $opt = $(this).find('option:selected');
        const costo = parseFloat($opt.data('costo') || 0);
        if (costo > 0) $('#add_costo').val(costo.toFixed(2));
    });

    // Al cambiar la condición a NO_GRAVADO o EXENTO, deshabilitar la alícuota
    $('#add_condicion_iva').on('change', function () {
        const noGravado = $(this).val() !== 'GRAVADO';
        $('#add_alicuota').prop('disabled', noGravado);
    });
    $('#add_condicion_iva_serv').on('change', function () {
        const noGravado = $(this).val() !== 'GRAVADO';
        $('#add_alicuota_serv').prop('disabled', noGravado);
    });

    $('#btnAgregarLinea').on('click', function () {
        const $opt = $('#add_insumo').find('option:selected');
        const insumoId = parseInt($('#add_insumo').val(), 10);
        const cantidad = parseFloat($('#add_cantidad').val());
        const costo    = parseFloat($('#add_costo').val());
        const cond     = $('#add_condicion_iva').val() || 'GRAVADO';
        const alic     = cond === 'GRAVADO'
                          ? parseFloat($('#add_alicuota').val() || 21)
                          : 0;

        if (!insumoId) { showMsg('error', 'Seleccioná un insumo.'); return; }
        if (!(cantidad > 0)) { showMsg('error', 'La cantidad debe ser mayor a 0.'); return; }
        if (!(costo > 0))    { showMsg('error', 'El costo debe ser mayor a 0.'); return; }

        const idx = detalle.findIndex(x => x.insumo_id === insumoId);
        const linea = {
            insumo_id:      insumoId,
            insumo_nombre:  $opt.data('nombre'),
            insumo_codigo:  $opt.data('codigo'),
            unidad_abrev:   $opt.data('unidad'),
            cantidad:       cantidad,
            costo_unitario: costo,
            condicion_iva:  cond,
            alicuota_iva:   alic,
        };

        if (idx >= 0) detalle[idx] = linea;
        else detalle.push(linea);

        renderDetalle();
        $('#add_insumo').val('');
        $('#add_cantidad').val('');
        $('#add_costo').val('');
        $('#add_condicion_iva').val('GRAVADO');
        $('#add_alicuota').val('21').prop('disabled', false);
    });

    /* ---------- Alta de CONCEPTO ---------- */
    $('#btnAgregarConcepto').on('click', function () {
        const concepto = ($('#add_concepto').val() || '').trim();
        const importe  = parseFloat($('#add_importe').val());
        const cond     = $('#add_condicion_iva_serv').val() || 'GRAVADO';
        const alic     = cond === 'GRAVADO'
                          ? parseFloat($('#add_alicuota_serv').val() || 21)
                          : 0;

        if (!concepto)      { showMsg('error', 'Ingresá el concepto.'); return; }
        if (!(importe > 0)) { showMsg('error', 'El importe debe ser mayor a 0.'); return; }

        const key = 'serv_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);

        detalle.push({
            _key:           key,
            insumo_id:      null,
            concepto:       concepto,
            cantidad:       1,
            costo_unitario: importe,
            condicion_iva:  cond,
            alicuota_iva:   alic,
        });

        renderDetalle();
        $('#add_concepto').val('');
        $('#add_importe').val('');
        $('#add_condicion_iva_serv').val('GRAVADO');
        $('#add_alicuota_serv').val('21').prop('disabled', false);
        $('#add_concepto').focus();
    });

    /* ---------- Quitar línea ---------- */
    $('#detalleBody').on('click', '.btn-quitar-linea', function () {
        const key = $(this).data('key');
        if (typeof key === 'string' && key.indexOf('serv_') === 0) {
            detalle = detalle.filter(x => x._key !== key);
        } else {
            const insumoId = parseInt(String(key).replace('insumo_', ''), 10);
            detalle = detalle.filter(x => x.insumo_id !== insumoId);
        }
        renderDetalle();
    });

    /* ---------- Render del detalle ---------- */
    function renderDetalle() {
        const $tbody = $('#detalleBody');

        if (detalle.length === 0) {
            $tbody.html(
                '<tr class="fila-vacia"><td colspan="8" ' +
                'style="text-align:center;color:#7A7266;padding:1.5rem;">' +
                'Todavía no cargaste líneas.</td></tr>'
            );
            recalcularTotales();
            return;
        }

        let html = '';
        detalle.forEach(function (d, i) {
            const sub = d.cantidad * d.costo_unitario;
            const esServicio = !d.insumo_id;
            const cond = d.condicion_iva || 'GRAVADO';
            const alic = parseFloat(d.alicuota_iva || 0);
            const impIva = cond === 'GRAVADO'
                           ? +(sub * alic / 100).toFixed(2)
                           : 0;

            const descripcion = esServicio
                ? '<em>' + escapeHtml(d.concepto || '') + '</em>'
                : '<strong>' + escapeHtml(d.insumo_nombre || '') + '</strong>' +
                  (d.insumo_codigo
                      ? '<br><span style="font-size:12px;color:#7A7266;">Cód: ' +
                        escapeHtml(d.insumo_codigo) + '</span>'
                      : '');

            const cantTxt = esServicio
                ? '—'
                : d.cantidad.toLocaleString('es-AR') + ' ' +
                  escapeHtml(d.unidad_abrev || '');

            const costoTxt = esServicio
                ? '—'
                : fmtMoney(d.costo_unitario);

            const rowKey = esServicio ? d._key : ('insumo_' + d.insumo_id);

            html += '<tr data-index="' + i + '" data-key="' + rowKey + '">' +
                '<td>' + descripcion + '</td>' +
                '<td style="text-align:right;">' + cantTxt + '</td>' +
                '<td style="text-align:right;">' + costoTxt + '</td>' +
                '<td style="text-align:right;font-weight:600;">' + fmtMoney(sub) + '</td>' +
                '<td>' + condicionLabel(cond) + '</td>' +
                '<td style="text-align:right;">' + alicuotaLabel(alic) + '</td>' +
                '<td style="text-align:right;">' + fmtMoney(impIva) + '</td>' +
                '<td style="text-align:right;width:50px;">' +
                    '<button type="button" class="btn-icon btn-quitar-linea" ' +
                            'data-key="' + rowKey + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" ' +
                             'fill="none" stroke="currentColor" stroke-width="2">' +
                            '<path d="M18 6 6 18M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                '</td></tr>';
        });
        $tbody.html(html);

        recalcularTotales();
    }

    /* ---------- Cálculo de totales ---------- */
    $('#iva, #impuestos_internos, #perc_iva, #perc_iibb, #neto_no_gravado, #neto_exento')
        .on('input change', recalcularTotales);

    function recalcularTotales() {
        let subtotal    = 0;
        let netoGrav    = 0;
        let noGravDet   = 0;
        let exentoDet   = 0;
        let ivaDet      = 0;

        detalle.forEach(function (d) {
            const sub = d.cantidad * d.costo_unitario;
            const cond = d.condicion_iva || 'GRAVADO';
            const alic = parseFloat(d.alicuota_iva || 0);

            subtotal += sub;
            if (cond === 'GRAVADO') {
                netoGrav += sub;
                ivaDet += sub * alic / 100;
            } else if (cond === 'NO_GRAVADO') {
                noGravDet += sub;
            } else {
                exentoDet += sub;
            }
        });

        subtotal = round(subtotal);
        netoGrav = round(netoGrav);
        ivaDet = round(ivaDet);

        // Neto no gravado y exento: sumar los del detalle + los del input manual (por si el usuario agrega)
        const noGravManual = parseFloat($('#neto_no_gravado').val() || 0);
        const exentoManual = parseFloat($('#neto_exento').val() || 0);

        // Neto gravado calculado (input readonly)
        $('#neto_gravado').val(netoGrav.toFixed(2));

        // IVA: usar el del detalle salvo que el usuario marque manual
        const manualIva = $('#iva_manual_chk').is(':checked');
        if (!manualIva) {
            $('#iva').val(ivaDet.toFixed(2));
        }
        const iva = parseFloat($('#iva').val() || 0);

        const percIva  = parseFloat($('#perc_iva').val() || 0);
        const percIibb = parseFloat($('#perc_iibb').val() || 0);
        const impInt   = parseFloat($('#impuestos_internos').val() || 0);

        const total = subtotal + iva + percIva + percIibb + impInt;

        $('#subtotal').val(subtotal.toFixed(2));
        $('#totalDisplay').text(fmtMoney(total));
    }

    function round(n) {
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    /* ---------- Submit ---------- */
    $form.on('submit', function (e) {
        e.preventDefault();

        if (detalle.length === 0) {
            showMsg('error', 'Cargá al menos una línea en el detalle.');
            return;
        }

        const $btn = $form.find('button[type="submit"]');
        const original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        const formData = $form.serializeArray();
        formData.push({ name: 'detalle', value: JSON.stringify(detalle) });
        formData.push({
            name: 'iva_manual',
            value: $('#iva_manual_chk').is(':checked') ? '1' : '0'
        });

        const id = $('input[name="id"]').val();
        const url = (esEditar && id)
            ? BASE_URL + 'comprobantes/editar?id=' + id
            : BASE_URL + 'comprobantes/crear';

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: $.param(formData)
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () {
                    window.location.href = res.redirect ||
                        (BASE_URL + 'comprobantes');
                }, 800);
            } else {
                showMsg('error', res.msg || 'Error al guardar.');
            }
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) ||
                      'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    /* ============================================================
       ARCA — constatar
       ============================================================ */
    $('#btnArcaConstatar').on('click', function () {
        const proveedorId = parseInt($('#proveedor_id').val(), 10);
        if (!proveedorId) { showMsg('error', 'Seleccioná un proveedor.'); return; }

        const $opt = $('#proveedor_id').find('option:selected');
        const cuit = ($opt.text().match(/(\d{11})/) || [])[1] || '';

        const cae = $('#cae').val().trim();
        const pv = $('#punto_venta').val().trim();
        const numero = $('#numero').val().trim();
        const fecha = $('#fecha_comprobante').val();
        const tipoId = parseInt($('#tipo_comprobante_id').val(), 10);
        const iva = parseFloat($('#iva').val() || 0);
        const impInt = parseFloat($('#impuestos_internos').val() || 0);
        const subtotal = parseFloat($('#subtotal').val() || 0);
        const total = subtotal + iva + impInt;

        if (!cuit || cuit.length !== 11) {
            showMsg('error', 'El proveedor seleccionado no tiene CUIT válido (11 dígitos).');
            return;
        }
        if (!cae) { showMsg('error', 'Cargá el CAE primero.'); return; }
        if (!numero) { showMsg('error', 'Cargá el número del comprobante.'); return; }
        if (!total) { showMsg('error', 'Cargá al menos una línea para tener el importe total.'); return; }

        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Constatan...');

        $.ajax({
            url: BASE_URL + 'comprobantes/arca/constatar',
            type: 'POST',
            dataType: 'json',
            data: {
                cuit_emisor: cuit,
                cae: cae,
                punto_venta: parseInt(pv, 10) || 0,
                numero: parseInt(numero, 10) || 0,
                fecha: fecha,
                tipo_comprobante_id: tipoId,
                importe_total: total,
            }
        })
        .done(function (res) {
            if (res.ok) showMsg('ok', res.msg);
            else {
                let m = res.msg || 'No se pudo constatar.';
                if (res.observaciones && res.observaciones.length) {
                    m += ' — ' + res.observaciones.join(' | ');
                }
                showMsg('error', m);
            }
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) ||
                      'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () { $btn.prop('disabled', false).text(original); });
    });

    /* ============================================================
       ADJUNTO
       ============================================================ */
    $('#btnSubirArchivo').on('click', function () {
        $('#archivoInput').val('').trigger('click');
    });

    $('#archivoInput').on('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) {
            showMsg('error', 'El archivo no puede superar 8 MB.');
            return;
        }
        if (!$('input[name="id"]').length) {
            showMsg('error', 'Guardá primero el comprobante. Después vas a poder ' +
                             'adjuntar el archivo desde la vista "Ver".');
            return;
        }
        subirArchivo(file);
    });

    function subirArchivo(file) {
        const compraId = parseInt($('input[name="id"]').val(), 10);
        if (!compraId) return;

        const fd = new FormData();
        fd.append('compra_id', compraId);
        fd.append('archivo', file);
        fd.append('nombre_original', file.name);

        const $btn = $('#btnSubirArchivo');
        const original = $btn.text();
        $btn.prop('disabled', true).text('Subiendo...');

        $.ajax({
            url: BASE_URL + 'comprobantes/subir-archivo',
            type: 'POST',
            dataType: 'json',
            data: fd, processData: false, contentType: false
        })
        .done(function (res) {
            if (res.ok) {
                $('#adjuntoInfo').html(
                    '<a href="' + res.path + '" target="_blank">' +
                    escapeHtml(file.name) + '</a>'
                );
                $('#btnSubirArchivo').text('Cambiar archivo');
                if (!$('#btnEliminarArchivo').length) {
                    $('.comp-adjunto-botones').append(
                        '<button type="button" class="btn btn-outline" ' +
                        'id="btnEliminarArchivo" ' +
                        'style="color:#B23A48;border-color:rgba(178,58,72,.3);">' +
                        'Eliminar</button>'
                    );
                }
                showMsg('ok', res.msg);
            } else {
                showMsg('error', res.msg || 'Error al subir.');
            }
        })
        .fail(function () { showMsg('error', 'Error de comunicación.'); })
        .always(function () { $btn.prop('disabled', false).text(original); });
    }

    $(document).on('click', '#btnEliminarArchivo', function () {
        if (!confirm('¿Eliminar el archivo adjunto?')) return;
        const compraId = parseInt($('input[name="id"]').val(), 10);
        if (!compraId) return;

        $.ajax({
            url: BASE_URL + 'comprobantes/eliminar-archivo',
            type: 'POST',
            dataType: 'json',
            data: { compra_id: compraId }
        })
        .done(function (res) {
            if (res.ok) {
                $('#adjuntoInfo').html('<span style="color:#7A7266;">Sin adjunto</span>');
                $('#btnEliminarArchivo').remove();
                $('#btnSubirArchivo').text('Adjuntar archivo');
                showMsg('ok', res.msg);
            }
        });
    });

    /* ============================================================
       EXPORTAR / IMPRIMIR / PDF
       ============================================================ */
    function paramsFiltros() {
        return $.param({
            desde:        $('#filtro_desde').val() || '',
            hasta:        $('#filtro_hasta').val() || '',
            proveedor_id: $('#filtro_proveedor').val() || 0,
            busqueda:     $('#filtro_busqueda').val() || '',
            estado:       $('#filtro_estado').val() || '',
        });
    }

    $('#btnExportarExcel').on('click', function () {
        window.location.href = BASE_URL + 'comprobantes/exportar?' + paramsFiltros();
    });
    $('#btnImprimir').on('click', function () {
        window.open(BASE_URL + 'comprobantes/imprimir?' + paramsFiltros(), '_blank');
    });
    $('#btnPdf').on('click', function () {
        window.location.href = BASE_URL + 'comprobantes/pdf?' + paramsFiltros();
    });

    /* ============================================================
       BOOTSTRAP — detalle inicial (modo editar)
       ============================================================ */
    $(function () {
        aplicarModoIva();

        if (!$('#detalleBody').length) return;
        if (typeof window.__DETALLE_INICIAL__ === 'undefined') return;
        if (!Array.isArray(window.__DETALLE_INICIAL__)) return;
        if (window.__DETALLE_INICIAL__.length === 0) {
            recalcularTotales();
            return;
        }

        detalle = window.__DETALLE_INICIAL__.map(function (d) {
            const base = {
                condicion_iva: d.condicion_iva || 'GRAVADO',
                alicuota_iva:  parseFloat(d.alicuota_iva) || 21,
            };
            if (d.insumo_id) {
                return Object.assign(base, {
                    insumo_id:      parseInt(d.insumo_id, 10),
                    insumo_nombre:  d.insumo_nombre || '',
                    insumo_codigo:  d.insumo_codigo || '',
                    unidad_abrev:   d.unidad_abrev  || '',
                    cantidad:       parseFloat(d.cantidad)       || 0,
                    costo_unitario: parseFloat(d.costo_unitario) || 0,
                });
            } else {
                return Object.assign(base, {
                    _key:           'serv_' + Math.random().toString(36).slice(2),
                    insumo_id:      null,
                    concepto:       d.concepto || '',
                    cantidad:       parseFloat(d.cantidad)       || 1,
                    costo_unitario: parseFloat(d.costo_unitario) || 0,
                });
            }
        });

        renderDetalle();
    });

})(jQuery);