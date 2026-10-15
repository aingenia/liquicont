/**
 * dist/js/calendario_cheques.js
 * Calendario de cheques con FullCalendar v6.
 */
(function ($) {
    'use strict';

    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    var $modal      = $('#modalCheque');
    var $form       = $('#formCheque');
    var fechaActual = null;
    var calendar    = null;

    /* ============================================================
       HELPERS
       ============================================================ */
    function fmtMoney(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    function fechaLabel(iso) {
        // ISO YYYY-MM-DD → "Lunes 22 de septiembre de 2026"
        if (!iso) return '';
        var partes = iso.split('-');
        var d = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
        return d.toLocaleDateString('es-AR', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        });
    }

    function showToast(tipo, texto) {
        var $box = $('#formMsg');
        var cls = tipo === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(texto).show();
        setTimeout(function () { $box.fadeOut(300); }, 3500);
    }

    /* ============================================================
       MODAL
       ============================================================ */
    function abrirModal() { $modal.css('display', 'flex'); }
    function cerrarModal() {
        $modal.hide();
        resetForm();
        fechaActual = null;
    }

    function resetForm() {
        $form[0].reset();
        $('#evento_id').val('');
        $('#calFormTitulo').text('Agregar cheque');
        $('#btnGuardarCheque').text('Agregar cheque');
        $('#btnCancelarEdicion').hide();
    }

    $('#modalCerrar').on('click', cerrarModal);
    $modal.on('click', function (e) {
        if ($(e.target).hasClass('cal-modal-overlay')) cerrarModal();
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.is(':visible')) cerrarModal();
    });

    $('#btnCancelarEdicion').on('click', function () {
        resetForm();
        $('#evento_fecha').val(fechaActual);
    });

    /* ============================================================
       CARGAR CHEQUES DEL DÍA (listado dentro del modal)
       ============================================================ */
    function cargarChequesDelDia(fecha) {
        $.ajax({
            url: BASE_URL + 'calendario/listar-fecha',
            type: 'POST',
            dataType: 'json',
            data: { fecha: fecha }
        })
        .done(function (res) {
            if (!res.ok) return;
            renderChequesLista(res.cheques, res.total);
        });
    }

    function renderChequesLista(cheques, total) {
        var $lista = $('#chequesLista');

        if (!cheques || cheques.length === 0) {
            $lista.html(
                '<div class="cal-empty">' +
                '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
                '<rect x="3" y="4" width="18" height="18" rx="2"/>' +
                '<path d="M16 2v4M8 2v4M3 10h18"/></svg>' +
                '<p>No hay cheques para este día.</p>' +
                '</div>'
            );
            $('#calTotalDia').hide();
            return;
        }

        var html = '';
        cheques.forEach(function (c) {
            html += '<div class="cal-cheque-item" data-id="' + c.id + '">';
            html += '  <div class="cal-cheque-info">';
            html += '    <div class="cal-cheque-importe">' + fmtMoney(c.importe) + '</div>';
            html += '    <div class="cal-cheque-desc">' + escapeHtml(c.descripcion) + '</div>';
            html += '    <div class="cal-cheque-meta">';
            if (c.banco)       html += '<span>' + escapeHtml(c.banco) + '</span>';
            if (c.nro_cheque)  html += '<span>N° ' + escapeHtml(c.nro_cheque) + '</span>';
            html += '      <span class="cal-tipo-' + (c.tipo || 'TERCERO').toLowerCase() + '">' +
                    (c.tipo === 'PROPIO' ? 'Propio' : 'Tercero') + '</span>';
            html += '    </div>';
            html += '  </div>';
            html += '  <div class="cal-cheque-acciones">';
            html += '    <button type="button" class="cal-btn-accion cal-btn-editar" data-id="' + c.id + '" title="Editar">';
            html += '      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>';
            html += '      </svg> Editar';
            html += '    </button>';
            html += '    <button type="button" class="cal-btn-accion cal-btn-eliminar" data-id="' + c.id + '" title="Eliminar">';
            html += '      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '        <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>';
            html += '        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>';
            html += '      </svg>';
            html += '    </button>';
            html += '  </div>';
            html += '</div>';
        });

        $lista.html(html);

        if (cheques.length > 1) {
            $('#calTotalDia').show();
            $('#calTotalValor').text(fmtMoney(total));
        } else {
            $('#calTotalDia').hide();
        }

        // Guardar datos en memoria para edición
        window.__chequesDelDia = cheques;
    }

    /* ============================================================
       FULLCALENDAR
       ============================================================ */
     calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,listMonth'
        },
            buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            list: 'Agenda'
        },
        height: 'auto',
        dayMaxEvents: 3,
        events: function (info, successCallback, failureCallback) {
            $.ajax({
                url: BASE_URL + 'calendario/listar',
                type: 'POST',
                dataType: 'json',
                data: {
                    desde: info.startStr.substring(0, 10),
                    hasta: info.endStr.substring(0, 10)
                }
            })
            .done(function (res) {
                if (!res.ok) { successCallback([]); return; }
                successCallback(convertirAEventos(res.eventos));
            })
            .fail(function () { successCallback([]); });
        },
        dateClick: function (info) {
            fechaActual = info.dateStr;
            resetForm();
            $('#evento_fecha').val(fechaActual);
            $('#modalFechaLabel').text(fechaLabel(fechaActual));
            $('#modalTitulo').text('Cheques del día');
            cargarChequesDelDia(fechaActual);
            abrirModal();
        },
        eventClick: function (info) {
            var fecha = info.event.startStr.substring(0, 10);
            fechaActual = fecha;
            resetForm();
            $('#evento_fecha').val(fecha);
            $('#modalFechaLabel').text(fechaLabel(fecha));
            $('#modalTitulo').text('Cheques del día');
            cargarChequesDelDia(fecha);
            abrirModal();
        }
    });

    calendar.render();

    // ------------------------------------------------------------
    // Workaround: la última columna (sáb) no se renderiza al inicio
    // porque el ancho del contenedor aún no está calculado. Forzamos
    // un updateSize() un instante después y escuchamos cambios del
    // contenedor con ResizeObserver.
    // ------------------------------------------------------------
    setTimeout(function () {
        calendar.updateSize();
    }, 100);

    if (window.ResizeObserver) {
        var ro = new ResizeObserver(function () {
            calendar.updateSize();
        });
        ro.observe(calendarEl);
    } else {
        // Fallback para navegadores viejos
        var resizeTimer = null;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                calendar.updateSize();
            }, 100);
        });
    }

    /**
     * Convierte {fecha: [cheques]} en eventos de FullCalendar.
     * Agrupa por día: un solo evento por día con el total y la cantidad.
     */
    function convertirAEventos(porFecha) {
        var eventos = [];
        Object.keys(porFecha || {}).forEach(function (fecha) {
            var cheques = porFecha[fecha];
            var total = 0;
            cheques.forEach(function (c) { total += parseFloat(c.importe || 0); });

            var titulo;
            if (cheques.length === 1) {
                var c = cheques[0];
                var desc = c.banco ? c.banco : (c.descripcion || '').substring(0, 25);
                titulo = fmtMoney(c.importe) + (desc ? ' · ' + desc : '');
            } else {
                titulo = cheques.length + ' cheques · ' + fmtMoney(total);
            }

            eventos.push({
                id: 'dia_' + fecha,
                title: titulo,
                start: fecha,
                allDay: true,
                backgroundColor: cheques.length > 1 ? '#0F5C52' : '#B23A48',
                borderColor: cheques.length > 1 ? '#0F5C52' : '#B23A48'
            });
        });
        return eventos;
    }

    /* ============================================================
       GUARDAR / EDITAR
       ============================================================ */
    $form.on('submit', function (e) {
        e.preventDefault();

        var id = $('#evento_id').val();
        var esEdicion = !!id;
        var url = BASE_URL + 'calendario/' + (esEdicion ? 'actualizar' : 'crear');

        var $btn = $('#btnGuardarCheque');
        var original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (res) {
            if (!res.ok) {
                showToast('error', res.msg || 'Error al guardar.');
                return;
            }

            showToast('ok', res.msg);
            resetForm();
            $('#evento_fecha').val(fechaActual);

            // Recargar lista y calendario
            cargarChequesDelDia(fechaActual);
            calendar.refetchEvents();
        })
        .fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showToast('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    /* ============================================================
       EDITAR / ELIMINAR (botones delegados dentro del modal)
       ============================================================ */
    $('#chequesLista').on('click', '.cal-btn-editar', function () {
        var id = parseInt($(this).data('id'), 10);
        var cheques = window.__chequesDelDia || [];
        var c = cheques.find(function (x) { return parseInt(x.id, 10) === id; });
        if (!c) return;

        $('#evento_id').val(c.id);
        $('#evento_importe').val(parseFloat(c.importe));
        $('#evento_tipo').val(c.tipo || 'TERCERO');
        $('#evento_banco').val(c.banco || '');
        $('#evento_nro_cheque').val(c.nro_cheque || '');
        $('#evento_descripcion').val(c.descripcion || '');
        $('#evento_fecha').val(c.fecha);

        $('#calFormTitulo').text('Editar cheque');
        $('#btnGuardarCheque').text('Guardar cambios');
        $('#btnCancelarEdicion').show();

        // Scroll al form
        $('.cal-modal-body').animate({
            scrollTop: $('.cal-modal-body')[0].scrollHeight
        }, 300);
    });

    $('#chequesLista').on('click', '.cal-btn-eliminar', function () {
        var id = parseInt($(this).data('id'), 10);
        if (!confirm('¿Eliminar este cheque?')) return;

        $.ajax({
            url: BASE_URL + 'calendario/eliminar',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showToast('ok', res.msg);
                cargarChequesDelDia(fechaActual);
                calendar.refetchEvents();
            } else {
                showToast('error', res.msg || 'No se pudo eliminar.');
            }
        })
        .fail(function () { showToast('error', 'Error de comunicación.'); });
    });

})(jQuery);