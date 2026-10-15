/**
 * dist/js/contabilidad.js
 * Módulo Contabilidad — interacciones del front.
 */
(function ($) {
    'use strict';

    /* ============================================================
       HELPERS
       ============================================================ */
    function money(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function num(v) {
        if (typeof v === 'number') return v;
        v = String(v || '').replace(/\s/g, '').replace(',', '.');
        var f = parseFloat(v);
        return isNaN(f) ? 0 : f;
    }

    function post(url, data, onOk, onErr) {
        $.ajax({
            url: BASE_URL + url,
            type: 'POST',
            dataType: 'json',
            data: data
        })
        .done(function (res) {
            if (res && res.ok) {
                if (onOk) onOk(res);
            } else {
                var msg = (res && res.msg) ? res.msg : 'Operación rechazada.';
                if (onErr) onErr(msg); else alert(msg);
            }
        })
        .fail(function (xhr) {
            var msg = 'Error de comunicación.';
            try { msg = JSON.parse(xhr.responseText).msg || msg; } catch (e) {}
            if (onErr) onErr(msg); else alert(msg);
        });
    }

    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    /* ============================================================
       FORM DE ASIENTO — líneas dinámicas
       ============================================================ */
    var CUENTAS_OPCIONES = []; // se llena al inicializar

    function initCuentasOpciones() {
        var $src = $('#cont_cuentas_src');
        if (!$src.length) return;
        $src.find('option').each(function () {
            CUENTAS_OPCIONES.push({
                id: $(this).val(),
                codigo: $(this).data('codigo'),
                nombre: $(this).data('nombre'),
                tipo: $(this).data('tipo')
            });
        });
    }

    function renderOpcionesCuenta(selected) {
        var html = '<option value="">— Seleccionar cuenta —</option>';
        CUENTAS_OPCIONES.forEach(function (c) {
            var sel = (String(c.id) === String(selected)) ? ' selected' : '';
            html += '<option value="' + c.id + '"' + sel + '>' +
                    escapeHtml(c.codigo + ' · ' + c.nombre) +
                    '</option>';
        });
        return html;
    }

    function agregarLinea(datos) {
        datos = datos || {};
        var $tbody = $('#cont_lineas_tbody');
        if (!$tbody.length) return;

        var $tr = $('<tr>');
        $tr.append(
            '<td class="col-cuenta">' +
                '<select name="linea_cuenta_id[]" class="linea-cuenta">' +
                    renderOpcionesCuenta(datos.cuenta_id) +
                '</select>' +
            '</td>'
        );
        $tr.append(
            '<td class="col-detalle">' +
                '<input type="text" name="linea_detalle[]" maxlength="255" ' +
                       'value="' + escapeHtml(datos.detalle || '') + '">' +
            '</td>'
        );
        $tr.append(
            '<td class="col-importe">' +
                '<input type="text" inputmode="decimal" name="linea_debe[]" ' +
                       'class="num linea-debe" value="' +
                       (datos.debe != null && datos.debe !== '' ? datos.debe : '') + '">' +
            '</td>'
        );
        $tr.append(
            '<td class="col-importe">' +
                '<input type="text" inputmode="decimal" name="linea_haber[]" ' +
                       'class="num linea-haber" value="' +
                       (datos.haber != null && datos.haber !== '' ? datos.haber : '') + '">' +
            '</td>'
        );
        $tr.append(
            '<td class="col-accion">' +
                '<button type="button" class="btn-remove-line" title="Quitar">&times;</button>' +
            '</td>'
        );

        $tbody.append($tr);
        recalcular();
    }

    function recalcular() {
        var td = 0, th = 0;
        $('#cont_lineas_tbody tr').each(function () {
            var $tr = $(this);
            var d = num($tr.find('.linea-debe').val());
            var h = num($tr.find('.linea-haber').val());

            if (d > 0 && h > 0) {
                $tr.addClass('fila-invalida');
            } else {
                $tr.removeClass('fila-invalida');
            }

            td += d;
            th += h;
        });

        $('#cont_total_debe').text(money(td));
        $('#cont_total_haber').text(money(th));

        var diff = Math.abs(td - th);
        var $tot = $('#cont_asiento_totales');
        if (diff < 0.01 && td > 0) {
            $tot.removeClass('desbalanceado');
            $('#cont_estado_balance').html('<span class="ok">✓ Balanceado</span>');
        } else {
            $tot.addClass('desbalanceado');
            $('#cont_estado_balance').text(diff >= 0.01 ? ('Diferencia: ' + money(diff)) : 'Sin importes');
        }
    }

    // Al cargar en Debe, bloquea Haber de la misma fila (y viceversa)
    // Usamos readonly (no disabled) porque disabled NO se envía en el submit
    $(document).on('input', '.linea-debe', function () {
        var v = num($(this).val());
        var $h = $(this).closest('tr').find('.linea-haber');
        if (v > 0) {
            $h.val('');
            $h.prop('readonly', true).css('background-color', '#F1F1F1');
        } else {
            $h.prop('readonly', false).css('background-color', '');
        }
        recalcular();
    });

    $(document).on('input', '.linea-haber', function () {
        var v = num($(this).val());
        var $d = $(this).closest('tr').find('.linea-debe');
        if (v > 0) {
            $d.val('');
            $d.prop('readonly', true).css('background-color', '#F1F1F1');
        } else {
            $d.prop('readonly', false).css('background-color', '');
        }
        recalcular();
    });

    $(document).on('click', '.btn-remove-line', function () {
        if ($('#cont_lineas_tbody tr').length <= 2) {
            alert('Un asiento necesita al menos 2 líneas.');
            return;
        }
        $(this).closest('tr').remove();
        recalcular();
    });

    $(document).on('click', '#btn_cont_add_linea', function () {
        agregarLinea();
        $('#cont_lineas_tbody tr:last .linea-cuenta').focus();
    });

    // Guardar asiento
    $(document).on('submit', '#form_cont_asiento', function (e) {
        e.preventDefault();
        var $form = $(this);

        var td = 0, th = 0;
        $('#cont_lineas_tbody tr').each(function () {
            td += num($(this).find('.linea-debe').val());
            th += num($(this).find('.linea-haber').val());
        });

        if (Math.abs(td - th) > 0.01) {
            alert('El asiento no balancea. Diferencia: ' + money(Math.abs(td - th)));
            return;
        }
        if (td === 0) {
            alert('El asiento no puede tener importe cero.');
            return;
        }

        var $btn = $form.find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'contabilidad/asiento-guardar',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (res) {
            if (res && res.ok) {
                window.location.href = BASE_URL + 'contabilidad/asiento-ver?id=' + res.id;
            } else {
                alert((res && res.msg) ? res.msg : 'No se pudo guardar.');
            }
        })
        .fail(function (xhr) {
            var msg = 'Error de comunicación.';
            try { msg = JSON.parse(xhr.responseText).msg || msg; } catch (e) {}
            alert(msg);
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ============================================================
       ACCIONES SOBRE ASIENTOS
       ============================================================ */
    $(document).on('click', '.btn-cont-confirmar', function () {
        var id = $(this).data('id');
        if (!confirm('¿Confirmar el asiento? Una vez confirmado no se puede editar.')) return;
        post('contabilidad/asiento-confirmar', { id: id },
            function () { location.reload(); });
    });

    $(document).on('click', '.btn-cont-anular', function () {
        var id = $(this).data('id');
        var motivo = prompt('Motivo de anulación:');
        if (!motivo) return;
        post('contabilidad/asiento-anular', { id: id, motivo: motivo },
            function () { location.reload(); });
    });

    $(document).on('click', '.btn-cont-eliminar', function () {
        var id = $(this).data('id');
        if (!confirm('¿Eliminar el asiento en borrador? Esta acción no se puede deshacer.')) return;
        post('contabilidad/asiento-eliminar', { id: id },
            function () { location.reload(); });
    });

    /* ============================================================
       EJERCICIOS
       ============================================================ */
    $(document).on('submit', '#form_cont_ejercicio', function (e) {
        e.preventDefault();
        var $form = $(this);
        post('contabilidad/ejercicio-guardar', $form.serialize(),
            function () {
                window.location.href = BASE_URL + 'contabilidad/ejercicios';
            });
    });

    $(document).on('click', '.btn-cont-cerrar-ejercicio', function () {
        var id = $(this).data('id');
        if (!confirm('¿Cerrar el ejercicio? No se podrán agregar asientos con fechas dentro del período.')) return;
        post('contabilidad/ejercicio-cerrar', { id: id },
            function () { location.reload(); });
    });

    $(document).on('click', '.btn-cont-reabrir-ejercicio', function () {
        var id = $(this).data('id');
        if (!confirm('¿Reabrir el ejercicio?')) return;
        post('contabilidad/ejercicio-reabrir', { id: id },
            function () { location.reload(); });
    });

    $(document).on('click', '.btn-cont-eliminar-ejercicio', function () {
        var id = $(this).data('id');
        if (!confirm('¿Eliminar el ejercicio? Solo se permite si no tiene asientos.')) return;
        post('contabilidad/ejercicio-eliminar', { id: id },
            function () { location.reload(); });
    });

    /* ============================================================
       PLAN DE CUENTAS
       ============================================================ */
    $(document).on('submit', '#form_cont_cuenta', function (e) {
        e.preventDefault();
        var $form = $(this);
        post('contabilidad/cuenta-guardar', $form.serialize(),
            function () {
                window.location.href = BASE_URL + 'contabilidad/plan-cuentas';
            });
    });

    $(document).on('click', '.btn-cont-toggle-cuenta', function () {
        var id = $(this).data('id');
        var estado = $(this).data('estado'); // ACTIVA o INACTIVA
        var nuevo = estado === 'ACTIVA' ? 'INACTIVA' : 'ACTIVA';
        post('contabilidad/cuenta-estado', { id: id, estado: nuevo },
            function () { location.reload(); });
    });

    // Toggle de ramas en el árbol
    $(document).on('click', '.cont-tree-toggle', function () {
        var $btn = $(this);
        var $ul = $btn.closest('li').children('ul.cont-tree-children');
        if (!$ul.length) return;
        $ul.slideToggle(120);
        $btn.text($ul.is(':visible') ? '▾' : '▸');
    });

    /* ============================================================
       CONFIGURACIÓN CONTABLE (delegado)
       ============================================================ */
    $(document).on('submit', '#form_cont_configuracion', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'contabilidad/configuracion-guardar',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (res) {
            if (res && res.ok) {
                window.location.reload();
            } else {
                alert((res && res.msg) ? res.msg : 'No se pudo guardar.');
            }
        })
        .fail(function (xhr) {
            alert('Error de comunicación (' + xhr.status + ').');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ============================================================
       BOOTSTRAP
       ============================================================ */
    $(function () {
        initCuentasOpciones();

        // Form de asiento: si está vacío, arranca con 2 líneas
        if ($('#cont_lineas_tbody').length) {
            if ($('#cont_lineas_tbody tr').length === 0) {
                agregarLinea();
                agregarLinea();
            } else {
                // Hay filas renderizadas server-side: recalcular totales
                // y bloquear la columna opuesta cuando corresponda
                $('#cont_lineas_tbody tr').each(function () {
                    var d = num($(this).find('.linea-debe').val());
                    var h = num($(this).find('.linea-haber').val());
                    if (d > 0) $(this).find('.linea-haber').prop('disabled', true);
                    if (h > 0) $(this).find('.linea-debe').prop('disabled', true);
                });
                recalcular();
            }
        }
    });

})(jQuery);