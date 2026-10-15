/**
 * dist/js/pagos.js
 * Módulo Pagos a Proveedores — listado + ver + form.
 */
(function ($) {
    'use strict';

    var timer = null;

      /* ============================================================
       TABS
       ============================================================ */
    $('.fac-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.fac-tab').removeClass('active');
        $(this).addClass('active');
        $('.fac-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });   

    function showMsg(type, text) {
        var $box = $('#formMsg');
        var cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
        setTimeout(function () { $box.fadeOut(600); }, 4500);
    }

    function fmtMoney(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    /* ============================================================
       LISTADO — filtros en vivo
       ============================================================ */
    if ($('#pagosTableBody').length) {
        $('#filtro_desde, #filtro_hasta, #filtro_proveedor, #filtro_estado').on('change', listarPagos);
        $('#filtro_busqueda').on('keyup input', function () {
            clearTimeout(timer);
            timer = setTimeout(listarPagos, 300);
        });
    }

    function listarPagos() {
        $.ajax({
            url: BASE_URL + 'pagos/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                desde:        $('#filtro_desde').val() || '',
                hasta:        $('#filtro_hasta').val() || '',
                proveedor_id: $('#filtro_proveedor').val() || 0,
                busqueda:     $('#filtro_busqueda').val() || '',
                estado:       $('#filtro_estado').val() || '',
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#pagosTableBody').html(res.html);
                $('#pag_total').text(res.total);
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    $('#btnExportarExcel').on('click', function () {
        var p = $.param({
            desde:        $('#filtro_desde').val() || '',
            hasta:        $('#filtro_hasta').val() || '',
            proveedor_id: $('#filtro_proveedor').val() || 0,
            busqueda:     $('#filtro_busqueda').val() || '',
            estado:       $('#filtro_estado').val() || '',
        });
        window.location.href = BASE_URL + 'pagos/exportar?' + p;
    });

    /* ============================================================
       VER — anular pago
       ============================================================ */
    $('#btnAnularPago').on('click', function () {
        var id = $(this).data('id');
        var info = $(this).data('info');

        var motivo = prompt(
            'Vas a anular: ' + info + '\n\n' +
            'Se va a REVERTIR la cuenta corriente del proveedor y se eliminarán\n' +
            'los cheques propios asociados del calendario.\n\n' +
            'Motivo de la anulación (opcional):'
        );
        if (motivo === null) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'pagos/anular',
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

    /* ============================================================
       FORM DE PAGO
       ============================================================ */
        var $formPago = $('#pagoForm');

        if ($formPago.length) {
            // Todo el código del form va acá adentro
            initFormPago();
        }

        function initFormPago() {
        // Estado del formulario
        var comprasPendientes = [];
        var medios = [];
        var contadorMedios = 0;

    /* ------------------------------------------------------------
       Al cambiar el proveedor → cargar compras + saldo
       ------------------------------------------------------------ */
    $('#proveedor_id').on('change', function () {
        var proveedorId = parseInt($(this).val(), 10);
        comprasPendientes = [];
        medios = [];
        renderMedios();

        if (!proveedorId) {
            $('#bloqueSaldoProveedor').hide();
            $('#pagComprasLista').hide().empty();
            $('#pagComprasAcciones').hide();
            $('#pagComprasEmpty').hide();
            actualizarTotales();
            return;
        }

        $('#bloqueSaldoProveedor').show();
        $('#pagSaldoProveedor').text('Cargando...');
        $('#pagComprasLoader').show();
        $('#pagComprasLista').hide().empty();
        $('#pagComprasEmpty').hide();
        $('#pagComprasAcciones').hide();

        $.ajax({
            url: BASE_URL + 'pagos/compras-pendientes',
            type: 'POST',
            dataType: 'json',
            data: { proveedor_id: proveedorId }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudieron cargar las compras.');
                return;
            }

            // Mostrar saldo
            var saldo = parseFloat(res.saldo || 0);
            var color = saldo > 0 ? 'var(--krown-crimson)' :
                        (saldo < 0 ? 'var(--krown-teal)' : 'var(--krown-text-muted)');
            var texto = saldo > 0 ? 'LE DEBEMOS este monto al proveedor' :
                        (saldo < 0 ? 'Tenemos SALDO A FAVOR con el proveedor' :
                                    'Cuenta al día');
            $('#pagSaldoProveedor')
                .css('color', color)
                .html(fmtMoney(saldo) + ' <span style="font-size:12px;font-weight:400;">— ' + texto + '</span>');

            // Compras
            comprasPendientes = res.compras || [];

            if (comprasPendientes.length === 0) {
                $('#pagComprasEmpty').show();
                $('#pagComprasEmptyMsg').text('Este proveedor no tiene compras pendientes de pago.');
            } else {
                renderCompras();
                $('#pagComprasLista').show();
                $('#pagComprasAcciones').show();
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); })
        .always(function () { $('#pagComprasLoader').hide(); });
    });

    /* ------------------------------------------------------------
       Renderizar lista de compras pendientes
       ------------------------------------------------------------ */
    function renderCompras() {
        var html = '';
        comprasPendientes.forEach(function (c) {
            var comp = armarComprobante(c);
            var fechaFmt = formatearFecha(c.fecha_comprobante);

            html += '<div class="cob-factura-item" data-cid="' + c.id + '">';
            html += '  <div class="cob-factura-check">';
            html += '    <input type="checkbox" class="pag-com-check" data-id="' + c.id + '">';
            html += '  </div>';
            html += '  <div class="cob-factura-info">';
            html += '    <div class="cob-factura-titulo">' + escapeHtml(comp) + '</div>';
            html += '    <div class="cob-factura-meta">' + fechaFmt + '</div>';
            html += '  </div>';
            html += '  <div class="cob-factura-importes">';
            html += '    <span class="total">Total: ' + fmtMoney(c.total) + '</span>';
            html += '    <span class="saldo">Saldo: ' + fmtMoney(c.saldo_pendiente) + '</span>';
            html += '  </div>';
            html += '  <div class="cob-factura-input">';
            html += '    <input type="number" class="pag-com-importe" data-id="' + c.id + '"';
            html += '           step="0.01" min="0" max="' + c.saldo_pendiente + '" value="0" disabled>';
            html += '  </div>';
            html += '</div>';
        });
        $('#pagComprasLista').html(html);
    }

    /**
     * Arma el texto del comprobante de una compra.
     * Prioriza el comprobante (punto-venta + número). Si no hay, cae a un
     * identificador genérico.
     */
    function armarComprobante(c) {
        if (c.punto_venta || c.numero) {
            var desc = c.tipo_comprobante_desc || 'Comprobante';
            return desc + ' '
                + String(c.punto_venta || 0).padStart(4, '0') + '-'
                + String(c.numero || 0).padStart(8, '0');
        }
        return 'Compra #' + c.id;
    }

    function formatearFecha(iso) {
        if (!iso) return '';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    /* ------------------------------------------------------------
       Checkbox de compra → habilitar input
       ------------------------------------------------------------ */
    $('#pagComprasLista').on('change', '.pag-com-check', function () {
        var cid = parseInt($(this).data('id'), 10);
        var $item = $(this).closest('.cob-factura-item');
        var $input = $item.find('.pag-com-importe');

        if (this.checked) {
            $item.addClass('selected');
            var c = comprasPendientes.find(function (x) { return parseInt(x.id, 10) === cid; });
            $input.val(c ? parseFloat(c.saldo_pendiente).toFixed(2) : '0.00');
            $input.prop('disabled', false).focus();
        } else {
            $item.removeClass('selected');
            $input.val('0').prop('disabled', true);
        }
        actualizarTotales();
    });

    $('#pagComprasLista').on('input', '.pag-com-importe', function () {
        var cid = parseInt($(this).data('id'), 10);
        var val = parseFloat($(this).val() || 0);
        var c = comprasPendientes.find(function (x) { return parseInt(x.id, 10) === cid; });
        var max = c ? parseFloat(c.saldo_pendiente) : 0;

        if (val > max) {
            $(this).val(max.toFixed(2));
        }
        actualizarTotales();
    });

    /* ------------------------------------------------------------
       Imputar automáticamente (más antiguas primero)
       ------------------------------------------------------------ */
    $('#btnImputarAuto').on('click', function () {
        var totalMedios = calcularTotalMedios();
        if (totalMedios <= 0) {
            showMsg('error', 'Primero cargá al menos un medio de pago.');
            return;
        }

        var ordenadas = comprasPendientes.slice().sort(function (a, b) {
            return (a.fecha_comprobante || '').localeCompare(b.fecha_comprobante || '');
        });

        var restante = totalMedios;

        $('.pag-com-check').prop('checked', false);
        $('.pag-com-importe').val('0').prop('disabled', true);
        $('.cob-factura-item').removeClass('selected');

        ordenadas.forEach(function (c) {
            if (restante <= 0) return;
            var saldo = parseFloat(c.saldo_pendiente);
            var aplicar = Math.min(saldo, restante);
            restante = Math.round((restante - aplicar) * 100) / 100;

            var $item = $('.cob-factura-item[data-cid="' + c.id + '"]');
            $item.find('.pag-com-check').prop('checked', true);
            $item.addClass('selected');
            $item.find('.pag-com-importe').val(aplicar.toFixed(2)).prop('disabled', false);
        });

        actualizarTotales();
    });

    $('#btnLimpiarImputacion').on('click', function () {
        $('.pag-com-check').prop('checked', false);
        $('.pag-com-importe').val('0').prop('disabled', true);
        $('.cob-factura-item').removeClass('selected');
        actualizarTotales();
    });

    /* ------------------------------------------------------------
       MEDIOS DE PAGO
       ------------------------------------------------------------ */
    function crearMedio(medio) {
        contadorMedios++;
        return {
            tempId: contadorMedios,
            medio: medio || 'EFECTIVO',
            importe: 0,
            cheque_banco: '',
            cheque_numero: '',
            cheque_fecha_cobro: '',
            cheque_titular: '',
            retencion_tipo: '',
            retencion_nro: '',
            observaciones: '',
        };
    }

    function renderMedios() {
        var $lista = $('#pagMediosLista');

        if (medios.length === 0) {
            $lista.html('<div class="cob-empty" style="text-align:center;padding:20px;color:var(--krown-text-muted);">Todavía no cargaste ningún medio de pago.</div>');
            actualizarTotales();
            return;
        }

        var html = '';
        medios.forEach(function (m, i) {
            html += renderMedioItem(m, i);
        });
        $lista.html(html);
        actualizarTotales();
    }

    function renderMedioItem(m, idx) {
        var opts = ['EFECTIVO','TRANSFERENCIA','CHEQUE','TARJETA','RETENCION','OTRO'];
        var labels = {
            EFECTIVO: 'Efectivo', TRANSFERENCIA: 'Transferencia',
            CHEQUE: 'Cheque', TARJETA: 'Tarjeta',
            RETENCION: 'Retención', OTRO: 'Otro'
        };

        var h = '<div class="cob-medio-item" data-tempid="' + m.tempId + '">';
        h += '  <div class="cob-medio-item-header">';
        h += '    <span class="cob-medio-item-num">Medio #' + (idx + 1) + '</span>';
        h += '    <button type="button" class="cob-medio-remove" data-tempid="' + m.tempId + '">✕ Quitar</button>';
        h += '  </div>';
        h += '  <div class="cob-medio-grid">';
        h += '    <div class="form-group">';
        h += '      <label>Medio de pago *</label>';
        h += '      <select class="pag-medio-tipo" data-tempid="' + m.tempId + '">';
        opts.forEach(function (o) {
            h += '<option value="' + o + '"' + (m.medio === o ? ' selected' : '') + '>' + labels[o] + '</option>';
        });
        h += '      </select>';
        h += '    </div>';
        h += '    <div class="form-group">';
        h += '      <label>Importe *</label>';
        h += '      <input type="number" class="pag-medio-importe" data-tempid="' + m.tempId + '"';
        h += '             step="0.01" min="0" value="' + (parseFloat(m.importe) || 0).toFixed(2) + '">';
        h += '    </div>';

        if (m.medio === 'CHEQUE') {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group">';
            h += '        <label>Banco</label>';
            h += '        <input type="text" class="pag-medio-cheque-banco" data-tempid="' + m.tempId + '" maxlength="100" value="' + escapeHtml(m.cheque_banco) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>N° de cheque</label>';
            h += '        <input type="text" class="pag-medio-cheque-numero" data-tempid="' + m.tempId + '" maxlength="50" value="' + escapeHtml(m.cheque_numero) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>Fecha de cobro *</label>';
            h += '        <input type="date" class="pag-medio-cheque-fecha" data-tempid="' + m.tempId + '" value="' + escapeHtml(m.cheque_fecha_cobro) + '">';
            h += '        <div class="form-hint">Se agrega al Calendario de Cheques como PROPIO.</div>';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>Titular / Firmante</label>';
            h += '        <input type="text" class="pag-medio-cheque-titular" data-tempid="' + m.tempId + '" maxlength="150" value="' + escapeHtml(m.cheque_titular) + '">';
            h += '      </div>';
            h += '    </div>';
        } else if (m.medio === 'RETENCION') {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group">';
            h += '        <label>Tipo de retención *</label>';
            h += '        <input type="text" class="pag-medio-ret-tipo" data-tempid="' + m.tempId + '" maxlength="50" placeholder="IVA, IIBB, Ganancias, SUSS..." value="' + escapeHtml(m.retencion_tipo) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>N° de retención</label>';
            h += '        <input type="text" class="pag-medio-ret-nro" data-tempid="' + m.tempId + '" maxlength="50" value="' + escapeHtml(m.retencion_nro) + '">';
            h += '      </div>';
            h += '    </div>';
        } else {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group field-full">';
            h += '        <label>Observaciones</label>';
            h += '        <input type="text" class="pag-medio-obs" data-tempid="' + m.tempId + '" maxlength="255" value="' + escapeHtml(m.observaciones) + '">';
            h += '      </div>';
            h += '    </div>';
        }

        h += '  </div>';
        h += '</div>';
        return h;
    }

    // Agregar medio
    $('#btnAgregarMedio').on('click', function () {
        medios.push(crearMedio('EFECTIVO'));
        renderMedios();
    });

    $('[data-add-medio]').on('click', function () {
        var medio = $(this).data('add-medio');
        medios.push(crearMedio(medio));
        renderMedios();
    });

    // Quitar medio
    $('#pagMediosLista').on('click', '.cob-medio-remove', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        medios = medios.filter(function (m) { return m.tempId !== tempid; });
        renderMedios();
    });

    // Cambiar tipo de medio
    $('#pagMediosLista').on('change', '.pag-medio-tipo', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (m) {
            m.medio = $(this).val();
            renderMedios();
        }
    });

    // Importe
    $('#pagMediosLista').on('input', '.pag-medio-importe', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (m) {
            m.importe = parseFloat($(this).val() || 0);
            actualizarTotales();
        }
    });

    // Campos de cheque
    $('#pagMediosLista').on('input change', '.pag-medio-cheque-banco, .pag-medio-cheque-numero, .pag-medio-cheque-fecha, .pag-medio-cheque-titular', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (!m) return;
        if ($(this).hasClass('pag-medio-cheque-banco'))    m.cheque_banco = $(this).val();
        if ($(this).hasClass('pag-medio-cheque-numero'))   m.cheque_numero = $(this).val();
        if ($(this).hasClass('pag-medio-cheque-fecha'))    m.cheque_fecha_cobro = $(this).val();
        if ($(this).hasClass('pag-medio-cheque-titular'))  m.cheque_titular = $(this).val();
    });

    // Campos de retención
    $('#pagMediosLista').on('input', '.pag-medio-ret-tipo, .pag-medio-ret-nro', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (!m) return;
        if ($(this).hasClass('pag-medio-ret-tipo')) m.retencion_tipo = $(this).val();
        if ($(this).hasClass('pag-medio-ret-nro'))  m.retencion_nro = $(this).val();
    });

    // Observaciones
    $('#pagMediosLista').on('input', '.pag-medio-obs', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (m) m.observaciones = $(this).val();
    });

    /* ------------------------------------------------------------
       CÁLCULO DE TOTALES
       ------------------------------------------------------------ */
    function calcularTotalMedios() {
        var t = 0;
        medios.forEach(function (m) { t += parseFloat(m.importe || 0); });
        return Math.round(t * 100) / 100;
    }

    function calcularTotalImputado() {
        var t = 0;
        $('.pag-com-check:checked').each(function () {
            var cid = parseInt($(this).data('id'), 10);
            var val = parseFloat($('.pag-com-importe[data-id="' + cid + '"]').val() || 0);
            t += val;
        });
        return Math.round(t * 100) / 100;
    }

    function actualizarTotales() {
        var tm = calcularTotalMedios();
        var ti = calcularTotalImputado();
        var dif = Math.round((tm - ti) * 100) / 100;

        $('#pagTotalMedios').text(fmtMoney(tm));
        $('#pagTotalImputado').text(fmtMoney(ti));

        var $dif = $('#pagDiferencia');
        if (Math.abs(dif) < 0.01) {
            $dif.text('$ 0,00').removeClass('cob-totales-ok cob-totales-error');
        } else {
            $dif.text((dif > 0 ? '+ ' : '- ') + fmtMoney(Math.abs(dif)))
                .removeClass('cob-totales-ok cob-totales-error')
                .addClass(dif > 0 ? 'cob-totales-error' : 'cob-totales-ok');
        }

        var $al = $('#pagAlertaDiferencia');
        if (tm === 0 && ti === 0) {
            $al.hide();
        } else if (Math.abs(dif) < 0.01) {
            $al.removeClass('cob-alerta-error').addClass('cob-alerta-ok')
               .html('✓ <strong>Perfecto:</strong> el total de medios coincide con el total imputado.')
               .show();
        } else if (dif > 0) {
            $al.removeClass('cob-alerta-ok').addClass('cob-alerta-error')
               .html('⚠ <strong>Atención:</strong> hay <strong>' + fmtMoney(dif) + '</strong> de más en los medios de pago. Imputá ese monto a alguna compra o reducí un medio.')
               .show();
        } else {
            $al.removeClass('cob-alerta-ok').addClass('cob-alerta-error')
               .html('⚠ <strong>Atención:</strong> falta pagar <strong>' + fmtMoney(-dif) + '</strong>. Ajustá los medios o la imputación.')
               .show();
        }
    }

    /* ------------------------------------------------------------
       SUBMIT
       ------------------------------------------------------------ */
    $formPago.on('submit', function (e) {
        e.preventDefault();

        var proveedorId = parseInt($('#proveedor_id').val(), 10);
        if (!proveedorId) { showMsg('error', 'Seleccioná un proveedor.'); return; }

        if (medios.length === 0) { showMsg('error', 'Cargá al menos un medio de pago.'); return; }

        // Validar cada medio
        for (var i = 0; i < medios.length; i++) {
            var m = medios[i];
            if (parseFloat(m.importe) <= 0) {
                showMsg('error', 'El medio #' + (i + 1) + ' tiene importe 0.');
                return;
            }
            if (m.medio === 'CHEQUE' && !m.cheque_fecha_cobro) {
                showMsg('error', 'El cheque del medio #' + (i + 1) + ' necesita una fecha de cobro.');
                return;
            }
            if (m.medio === 'RETENCION' && !m.retencion_tipo) {
                showMsg('error', 'La retención del medio #' + (i + 1) + ' necesita un tipo.');
                return;
            }
        }

        // Imputación
        var imputacion = [];
        $('.pag-com-check:checked').each(function () {
            var cid = parseInt($(this).data('id'), 10);
            var val = parseFloat($('.pag-com-importe[data-id="' + cid + '"]').val() || 0);
            if (val > 0) {
                imputacion.push({ compra_id: cid, importe_aplicado: val });
            }
        });

        if (imputacion.length === 0) {
            showMsg('error', 'Marcá al menos una compra para imputar el pago.');
            return;
        }

        var totalMedios   = calcularTotalMedios();
        var totalImputado = calcularTotalImputado();
        if (Math.abs(totalMedios - totalImputado) > 0.01) {
            showMsg('error', 'El total de medios no coincide con el total imputado.');
            return;
        }

        // Enviar
        var $btn = $('#btnGuardarPago');
        var original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: BASE_URL + 'pagos/crear',
            type: 'POST',
            dataType: 'json',
            data: {
                proveedor_id: proveedorId,
                fecha:        $('#fecha').val(),
                notas:        $('#notas').val() || '',
                medios:       JSON.stringify(medios),
                imputacion:   JSON.stringify(imputacion),
            }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () {
                    window.location.href = res.redirect || (BASE_URL + 'pagos');
                }, 900);
            } else {
                showMsg('error', res.msg || 'No se pudo guardar.');
            }
        })
        .fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });
        
}

        /* ============================================================
       TAB 2: ESTADO DE COMPRAS
       ============================================================ */
    if ($('#comprasTableBody').length) {
        var timerCompras = null;

        $('#com_desde, #com_hasta, #com_proveedor, #com_estado_pago').on('change', buscarComprasEstado);
        $('#com_busqueda').on('keyup input', function () {
            clearTimeout(timerCompras);
            timerCompras = setTimeout(buscarComprasEstado, 300);
        });
    }

    function buscarComprasEstado() {
        $.ajax({
            url: BASE_URL + 'pagos/buscar-compras',
            type: 'POST',
            dataType: 'json',
            data: {
                desde:       $('#com_desde').val() || '',
                hasta:       $('#com_hasta').val() || '',
                proveedor_id: $('#com_proveedor').val() || 0,
                busqueda:    $('#com_busqueda').val() || '',
                estado_pago: $('#com_estado_pago').val() || '',
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#comprasTableBody').html(res.html);
                $('#com_total').text(res.total);
                $('#facTabComprasCount').text(res.total);
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

})(jQuery);