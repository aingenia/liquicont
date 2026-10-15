/**
 * dist/js/cobros.js
 * Módulo Cobros — listado + ver + form.
 */
(function ($) {
    'use strict';

    var timer = null;

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
    if ($('#cobrosTableBody').length) {
        $('#filtro_desde, #filtro_hasta, #filtro_cliente, #filtro_estado').on('change', listarCobros);
        $('#filtro_busqueda').on('keyup input', function () {
            clearTimeout(timer);
            timer = setTimeout(listarCobros, 300);
        });
    }

    function listarCobros() {
        $.ajax({
            url: BASE_URL + 'cobros/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                desde:      $('#filtro_desde').val() || '',
                hasta:      $('#filtro_hasta').val() || '',
                cliente_id: $('#filtro_cliente').val() || 0,
                busqueda:   $('#filtro_busqueda').val() || '',
                estado:     $('#filtro_estado').val() || '',
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#cobrosTableBody').html(res.html);
                $('#cob_total').text(res.total);
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); });
    }

    $('#btnExportarExcel').on('click', function () {
        var p = $.param({
            desde:      $('#filtro_desde').val() || '',
            hasta:      $('#filtro_hasta').val() || '',
            cliente_id: $('#filtro_cliente').val() || 0,
            busqueda:   $('#filtro_busqueda').val() || '',
            estado:     $('#filtro_estado').val() || '',
        });
        window.location.href = BASE_URL + 'cobros/exportar?' + p;
    });

    /* ============================================================
       VER — anular cobro
       ============================================================ */
    $('#btnAnularCobro').on('click', function () {
        var id = $(this).data('id');
        var info = $(this).data('info');

        var motivo = prompt(
            'Vas a anular: ' + info + '\n\n' +
            'Se va a REVERTIR la cuenta corriente del cliente y se eliminarán\n' +
            'los cheques asociados del calendario.\n\n' +
            'Motivo de la anulación (opcional):'
        );
        if (motivo === null) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'cobros/anular',
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
       FORM DE COBRO
       ============================================================ */
    var $formCobro = $('#cobroForm');
    if (!$formCobro.length) return;

    // Estado del formulario
    var facturasPendientes = [];  // [{id, fecha, total, cobrado, saldo_pendiente, ...}]
    var medios = [];              // [{tempId, medio, importe, ...camposCheque}]
    var contadorMedios = 0;

    /* ------------------------------------------------------------
       Al cambiar el cliente → cargar facturas pendientes + saldo
       ------------------------------------------------------------ */
    $('#cliente_id').on('change', function () {
        var clienteId = parseInt($(this).val(), 10);
        facturasPendientes = [];
        medios = [];
        renderMedios();

        if (!clienteId) {
            $('#bloqueSaldoCliente').hide();
            $('#cobFacturasLista').hide().empty();
            $('#cobFacturasAcciones').hide();
            $('#cobFacturasEmpty').hide();
            actualizarTotales();
            return;
        }

        $('#bloqueSaldoCliente').show();
        $('#cobSaldoCliente').text('Cargando...');
        $('#cobFacturasLoader').show();
        $('#cobFacturasLista').hide().empty();
        $('#cobFacturasEmpty').hide();
        $('#cobFacturasAcciones').hide();

        $.ajax({
            url: BASE_URL + 'cobros/facturas-pendientes',
            type: 'POST',
            dataType: 'json',
            data: { cliente_id: clienteId }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudieron cargar las facturas.');
                return;
            }

            // Mostrar saldo
            var saldo = parseFloat(res.saldo || 0);
            var color = saldo > 0 ? 'var(--krown-crimson)' :
                        (saldo < 0 ? 'var(--krown-teal)' : 'var(--krown-text-muted)');
            var texto = saldo > 0 ? 'El cliente NOS DEBE este monto' :
                        (saldo < 0 ? 'El cliente tiene SALDO A FAVOR' :
                                    'Cuenta al día');
            $('#cobSaldoCliente')
                .css('color', color)
                .html(fmtMoney(saldo) + ' <span style="font-size:12px;font-weight:400;">— ' + texto + '</span>');

            // Facturas
            facturasPendientes = res.facturas || [];

            if (facturasPendientes.length === 0) {
                $('#cobFacturasEmpty').show();
                $('#cobFacturasEmptyMsg').text('Este cliente no tiene facturas pendientes de cobro.');
            } else {
                renderFacturas();
                $('#cobFacturasLista').show();
                $('#cobFacturasAcciones').show();
            }
        })
        .fail(function () { showMsg('error', 'Error de conexión.'); })
        .always(function () { $('#cobFacturasLoader').hide(); });
    });

    /* ------------------------------------------------------------
       Renderizar lista de facturas pendientes
       ------------------------------------------------------------ */
        /* ------------------------------------------------------------
       Renderizar lista de facturas pendientes
       ------------------------------------------------------------ */
    function renderFacturas() {
        var html = '';
        facturasPendientes.forEach(function (f) {
            var comp = armarComprobante(f);
            var fechaFmt = formatearFecha(f.fecha);

            html += '<div class="cob-factura-item" data-fid="' + f.id + '">';
            html += '  <div class="cob-factura-check">';
            html += '    <input type="checkbox" class="cob-fac-check" data-id="' + f.id + '">';
            html += '  </div>';
            html += '  <div class="cob-factura-info">';
            html += '    <div class="cob-factura-titulo">' + escapeHtml(comp) + '</div>';
            html += '    <div class="cob-factura-meta">' + fechaFmt + '</div>';
            html += '  </div>';
            html += '  <div class="cob-factura-importes">';
            html += '    <span class="total">Total: ' + fmtMoney(f.total) + '</span>';
            html += '    <span class="saldo">Saldo: ' + fmtMoney(f.saldo_pendiente) + '</span>';
            html += '  </div>';
            html += '  <div class="cob-factura-input">';
            html += '    <input type="number" class="cob-fac-importe" data-id="' + f.id + '"';
            html += '           step="0.01" min="0" max="' + f.saldo_pendiente + '" value="0" disabled>';
            html += '  </div>';
            html += '</div>';
        });
        $('#cobFacturasLista').html(html);
    }

    /**
     * Arma el texto del comprobante de una factura.
     * Si tiene número de ARCA, lo usa (ej: "C 0001-00000042").
     * Si no, cae al remito interno (ej: "X 0001-00000010 (remito)").
     */
    function armarComprobante(f) {
        if (f.arca_numero_comprobante) {
            var letra = f.arca_tipo_comprobante_letra || 'X';
            return letra + ' '
                + String(f.arca_punto_venta).padStart(4, '0') + '-'
                + String(f.arca_numero_comprobante).padStart(8, '0');
        }
        // Fallback: usar el remito
        var prefijo = f.remito_prefijo ? f.remito_prefijo + ' ' : '';
        return prefijo
            + String(f.remito_punto_venta).padStart(4, '0') + '-'
            + String(f.remito_numero).padStart(8, '0')
            + ' (remito)';
    }

    function formatearFecha(iso) {
        if (!iso) return '';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function formatearFecha(iso) {
        if (!iso) return '';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    /* ------------------------------------------------------------
       Checkbox de factura → habilitar el input de importe
       ------------------------------------------------------------ */
    $('#cobFacturasLista').on('change', '.cob-fac-check', function () {
        var fid = parseInt($(this).data('id'), 10);
        var $item = $(this).closest('.cob-factura-item');
        var $input = $item.find('.cob-fac-importe');

        if (this.checked) {
            $item.addClass('selected');
            // Pre-cargar el saldo pendiente como valor por defecto
            var f = facturasPendientes.find(function (x) { return parseInt(x.id, 10) === fid; });
            $input.val(f ? parseFloat(f.saldo_pendiente).toFixed(2) : '0.00');
            $input.prop('disabled', false).focus();
        } else {
            $item.removeClass('selected');
            $input.val('0').prop('disabled', true);
        }
        actualizarTotales();
    });

    $('#cobFacturasLista').on('input', '.cob-fac-importe', function () {
        var fid = parseInt($(this).data('id'), 10);
        var val = parseFloat($(this).val() || 0);
        var f = facturasPendientes.find(function (x) { return parseInt(x.id, 10) === fid; });
        var max = f ? parseFloat(f.saldo_pendiente) : 0;

        if (val > max) {
            $(this).val(max.toFixed(2));
        }
        actualizarTotales();
    });

    /* ------------------------------------------------------------
       Botón "Imputar automáticamente"
       ------------------------------------------------------------ */
    $('#btnImputarAuto').on('click', function () {
        var totalMedios = calcularTotalMedios();
        if (totalMedios <= 0) {
            showMsg('error', 'Primero cargá al menos un medio de pago.');
            return;
        }

        // Ordenar facturas por fecha (más antiguas primero)
        var ordenadas = facturasPendientes.slice().sort(function (a, b) {
            return a.fecha.localeCompare(b.fecha);
        });

        var restante = totalMedios;

        // Limpiar todo
        $('.cob-fac-check').prop('checked', false);
        $('.cob-fac-importe').val('0').prop('disabled', true);
        $('.cob-factura-item').removeClass('selected');

        ordenadas.forEach(function (f) {
            if (restante <= 0) return;
            var saldo = parseFloat(f.saldo_pendiente);
            var aplicar = Math.min(saldo, restante);
            restante = Math.round((restante - aplicar) * 100) / 100;

            var $item = $('.cob-factura-item[data-fid="' + f.id + '"]');
            $item.find('.cob-fac-check').prop('checked', true);
            $item.addClass('selected');
            $item.find('.cob-fac-importe').val(aplicar.toFixed(2)).prop('disabled', false);
        });

        actualizarTotales();
    });

    $('#btnLimpiarImputacion').on('click', function () {
        $('.cob-fac-check').prop('checked', false);
        $('.cob-fac-importe').val('0').prop('disabled', true);
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
        var $lista = $('#cobMediosLista');

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
        h += '      <select class="cob-medio-tipo" data-tempid="' + m.tempId + '">';
        opts.forEach(function (o) {
            h += '<option value="' + o + '"' + (m.medio === o ? ' selected' : '') + '>' + labels[o] + '</option>';
        });
        h += '      </select>';
        h += '    </div>';
        h += '    <div class="form-group">';
        h += '      <label>Importe *</label>';
        h += '      <input type="number" class="cob-medio-importe" data-tempid="' + m.tempId + '"';
        h += '             step="0.01" min="0" value="' + (parseFloat(m.importe) || 0).toFixed(2) + '">';
        h += '    </div>';

        // Campos extra según el medio
        if (m.medio === 'CHEQUE') {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group">';
            h += '        <label>Banco</label>';
            h += '        <input type="text" class="cob-medio-cheque-banco" data-tempid="' + m.tempId + '" maxlength="100" value="' + escapeHtml(m.cheque_banco) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>N° de cheque</label>';
            h += '        <input type="text" class="cob-medio-cheque-numero" data-tempid="' + m.tempId + '" maxlength="50" value="' + escapeHtml(m.cheque_numero) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>Fecha de cobro *</label>';
            h += '        <input type="date" class="cob-medio-cheque-fecha" data-tempid="' + m.tempId + '" value="' + escapeHtml(m.cheque_fecha_cobro) + '">';
            h += '        <div class="form-hint">Se agrega automáticamente al Calendario de Cheques.</div>';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>Titular / Firmante</label>';
            h += '        <input type="text" class="cob-medio-cheque-titular" data-tempid="' + m.tempId + '" maxlength="150" value="' + escapeHtml(m.cheque_titular) + '">';
            h += '      </div>';
            h += '    </div>';
        } else if (m.medio === 'RETENCION') {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group">';
            h += '        <label>Tipo de retención *</label>';
            h += '        <input type="text" class="cob-medio-ret-tipo" data-tempid="' + m.tempId + '" maxlength="50" placeholder="IVA, IIBB, Ganancias..." value="' + escapeHtml(m.retencion_tipo) + '">';
            h += '      </div>';
            h += '      <div class="form-group">';
            h += '        <label>N° de retención</label>';
            h += '        <input type="text" class="cob-medio-ret-nro" data-tempid="' + m.tempId + '" maxlength="50" value="' + escapeHtml(m.retencion_nro) + '">';
            h += '      </div>';
            h += '    </div>';
        } else {
            h += '    <div class="cob-medio-fields-extra">';
            h += '      <div class="form-group field-full">';
            h += '        <label>Observaciones</label>';
            h += '        <input type="text" class="cob-medio-obs" data-tempid="' + m.tempId + '" maxlength="255" value="' + escapeHtml(m.observaciones) + '">';
            h += '      </div>';
            h += '    </div>';
        }

        h += '  </div>';
        h += '</div>';
        return h;
    }

    // Agregar medio de pago (botón genérico → EFECTIVO por defecto)
    $('#btnAgregarMedio').on('click', function () {
        medios.push(crearMedio('EFECTIVO'));
        renderMedios();
    });

    // Agregar medio específico (botones de atajo)
    $('[data-add-medio]').on('click', function () {
        var medio = $(this).data('add-medio');
        medios.push(crearMedio(medio));
        renderMedios();
    });

    // Quitar medio
    $('#cobMediosLista').on('click', '.cob-medio-remove', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        medios = medios.filter(function (m) { return m.tempId !== tempid; });
        renderMedios();
    });

    // Cambiar tipo de medio → re-renderizar (para mostrar los campos correctos)
    $('#cobMediosLista').on('change', '.cob-medio-tipo', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (m) {
            m.medio = $(this).val();
            renderMedios();
        }
    });

    // Actualizar importe
    $('#cobMediosLista').on('input', '.cob-medio-importe', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (m) {
            m.importe = parseFloat($(this).val() || 0);
            actualizarTotales();
        }
    });

    // Actualizar campos de cheque
    $('#cobMediosLista').on('input change', '.cob-medio-cheque-banco, .cob-medio-cheque-numero, .cob-medio-cheque-fecha, .cob-medio-cheque-titular', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (!m) return;
        if ($(this).hasClass('cob-medio-cheque-banco'))       m.cheque_banco = $(this).val();
        if ($(this).hasClass('cob-medio-cheque-numero'))      m.cheque_numero = $(this).val();
        if ($(this).hasClass('cob-medio-cheque-fecha'))       m.cheque_fecha_cobro = $(this).val();
        if ($(this).hasClass('cob-medio-cheque-titular'))     m.cheque_titular = $(this).val();
    });

    // Actualizar campos de retención
    $('#cobMediosLista').on('input', '.cob-medio-ret-tipo, .cob-medio-ret-nro', function () {
        var tempid = parseInt($(this).data('tempid'), 10);
        var m = medios.find(function (x) { return x.tempId === tempid; });
        if (!m) return;
        if ($(this).hasClass('cob-medio-ret-tipo')) m.retencion_tipo = $(this).val();
        if ($(this).hasClass('cob-medio-ret-nro'))  m.retencion_nro  = $(this).val();
    });

    $('#cobMediosLista').on('input', '.cob-medio-obs', function () {
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
        $('.cob-fac-check:checked').each(function () {
            var fid = parseInt($(this).data('id'), 10);
            var val = parseFloat($('.cob-fac-importe[data-id="' + fid + '"]').val() || 0);
            t += val;
        });
        return Math.round(t * 100) / 100;
    }

    function actualizarTotales() {
        var tm = calcularTotalMedios();
        var ti = calcularTotalImputado();
        var dif = Math.round((tm - ti) * 100) / 100;

        $('#cobTotalMedios').text(fmtMoney(tm));
        $('#cobTotalImputado').text(fmtMoney(ti));

        var $dif = $('#cobDiferencia');
        if (Math.abs(dif) < 0.01) {
            $dif.text('$ 0,00').removeClass('cob-totales-ok cob-totales-error');
        } else {
            $dif.text((dif > 0 ? '+ ' : '- ') + fmtMoney(Math.abs(dif)))
                .removeClass('cob-totales-ok cob-totales-error')
                .addClass(dif > 0 ? 'cob-totales-error' : 'cob-totales-ok');
        }

        // Alerta
        var $al = $('#cobAlertaDiferencia');
        if (tm === 0 && ti === 0) {
            $al.hide();
        } else if (Math.abs(dif) < 0.01) {
            $al.removeClass('cob-alerta-error').addClass('cob-alerta-ok')
               .html('✓ <strong>Perfecto:</strong> el total de medios coincide con el total imputado.')
               .show();
        } else if (dif > 0) {
            $al.removeClass('cob-alerta-ok').addClass('cob-alerta-error')
               .html('⚠ <strong>Atención:</strong> hay <strong>' + fmtMoney(dif) + '</strong> de más en los medios de pago. Imputá ese monto a alguna factura o reducí un medio.')
               .show();
        } else {
            $al.removeClass('cob-alerta-ok').addClass('cob-alerta-error')
               .html('⚠ <strong>Atención:</strong> falta cobrar <strong>' + fmtMoney(-dif) + '</strong>. Ajustá los medios o la imputación.')
               .show();
        }
    }

    /* ------------------------------------------------------------
       SUBMIT
       ------------------------------------------------------------ */
    $formCobro.on('submit', function (e) {
        e.preventDefault();

        var clienteId = parseInt($('#cliente_id').val(), 10);
        if (!clienteId) { showMsg('error', 'Seleccioná un cliente.'); return; }

        if (medios.length === 0) { showMsg('error', 'Cargá al menos un medio de pago.'); return; }

        // Validar cheques
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
        $('.cob-fac-check:checked').each(function () {
            var fid = parseInt($(this).data('id'), 10);
            var val = parseFloat($('.cob-fac-importe[data-id="' + fid + '"]').val() || 0);
            if (val > 0) {
                imputacion.push({ factura_id: fid, importe_aplicado: val });
            }
        });

        if (imputacion.length === 0) {
            showMsg('error', 'Marcá al menos una factura para imputar el cobro.');
            return;
        }

        var totalMedios   = calcularTotalMedios();
        var totalImputado = calcularTotalImputado();
        if (Math.abs(totalMedios - totalImputado) > 0.01) {
            showMsg('error', 'El total de medios no coincide con el total imputado.');
            return;
        }

        // Enviar
        var $btn = $('#btnGuardarCobro');
        var original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: BASE_URL + 'cobros/crear',
            type: 'POST',
            dataType: 'json',
            data: {
                cliente_id: clienteId,
                fecha:      $('#fecha').val(),
                notas:      $('#notas').val() || '',
                medios:     JSON.stringify(medios),
                imputacion: JSON.stringify(imputacion),
            }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () {
                    window.location.href = res.redirect || (BASE_URL + 'cobros');
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

})(jQuery);