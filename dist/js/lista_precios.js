/**
 * dist/js/lista_precios.js
 * Lista de Precios — guardado, previsualización en vivo, envío por email.
 */
(function ($) {
    'use strict';

    var $form = $('#listaForm');
    if (!$form.length) return;

    function showMsg(type, text) {
        var $box = $('#formMsg');
        var cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
        setTimeout(function () { $box.fadeOut(600); }, 4000);
    }

    function formatearFechaDMY(iso) {
        if (!iso) return '—';
        var p = iso.split('-');
        if (p.length !== 3) return iso;
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    /* ============================================================
       PREVISUALIZACIÓN EN VIVO
       Al cambiar número o fecha, actualizar el preview (sin guardar).
       ============================================================ */
    function actualizarPreview() {
        $('#lpPreviewNumero').text($('#numero').val() || '—');
        $('#lpPreviewFecha').text(formatearFechaDMY($('#fecha').val()));
    }

    $('#numero, #fecha').on('input change', actualizarPreview);

    /* Al cambiar la categoría → recargar la vista con la nueva
       (así se regenera el listado completo con esa categoría) */
    $('#categoria_id').on('change', function () {
        var catId = $(this).val();
        var url = new URL(window.location.href);
        url.searchParams.set('categoria_id', catId);
        window.location.href = url.toString();
    });

    /* ============================================================
       GUARDAR (AJAX)
       ============================================================ */
    $form.on('submit', function (e) {
        e.preventDefault();

        var $btn = $('#btnGuardar');
        var original = $btn.html();
        $btn.prop('disabled', true).html('Guardando...');

        $.ajax({
            url: BASE_URL + 'lista-precios/guardar',
            type: 'POST',
            dataType: 'json',
            data: {
                numero:        $('#numero').val(),
                fecha:         $('#fecha').val(),
                email_asunto:  $('#lpModalAsunto').val() || '',
                email_cuerpo:  $('#lpModalCuerpo').val() || '',
            }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                // Después de guardar, dejamos que el número quede como está
                // (no incrementamos hasta la próxima emisión)
            } else {
                showMsg('error', res.msg || 'Error al guardar.');
            }
        })
        .fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).html(original);
        });
    });

    /* ============================================================
       IMPRIMIR — Abre el PDF con los valores actuales
       ============================================================ */
    $('#btnImprimir').on('click', function () {
        var catId  = $('#categoria_id').val();
        var numero = $('#numero').val();
        var fecha  = $('#fecha').val();

        // Primero guardamos por las dudas (fire-and-forget),
        // después abrimos el PDF con los valores actuales
        $.ajax({
            url: BASE_URL + 'lista-precios/guardar',
            type: 'POST',
            dataType: 'json',
            data: {
                numero:       numero,
                fecha:        fecha,
                email_asunto: $('#lpModalAsunto').val() || '',
                email_cuerpo: $('#lpModalCuerpo').val() || '',
            }
        });

        var params = $.param({
            categoria_id: catId,
            numero:       numero,
            fecha:        fecha,
        });
        window.open(BASE_URL + 'lista-precios/pdf?' + params, '_blank');
    });

    /* ============================================================
       MODAL DE ENVÍO POR EMAIL
       ============================================================ */
    var $modal = $('#modalEnviar');

    function abrirModal() {
        $modal.css('display', 'flex');
    }
    function cerrarModal() {
        $modal.hide();
    }

    $('#btnAbrirEnviar').on('click', function () {
        var catId  = $('#categoria_id').val();
        var numero = $('#numero').val();

        // Actualizar subtítulo
        $('#modalEnviarSub').text('Se adjunta la lista N° ' + numero + ' a los clientes seleccionados.');

        // Cargar clientes
        $('#lpModalClientes').html('<div style="text-align:center;padding:20px;color:#7A7266;">Cargando...</div>');
        $('#lpModalClientesCount').text('0 clientes');
        $('#lpModalClientesHint').text('');

        $.ajax({
            url: BASE_URL + 'lista-precios/clientes-por-categoria',
            type: 'POST',
            dataType: 'json',
            data: { categoria_id: catId }
        })
        .done(function (res) {
            if (!res.ok) {
                $('#lpModalClientes').html('<div style="text-align:center;padding:20px;color:#B23A48;">' + (res.msg || 'Error.') + '</div>');
                return;
            }

            var clientes = res.clientes || [];

            if (clientes.length === 0) {
                $('#lpModalClientesHint').text('Esta categoría no tiene clientes con email cargado.');
                $('#lpModalClientes').html('<div style="text-align:center;padding:20px;color:#7A7266;">Sin clientes para mostrar.</div>');
                return;
            }

            var html = '';
            clientes.forEach(function (c) {
                var email = c.email_facturacion || c.email || '';
                html += '<label class="lp-cliente-item">';
                html += '  <input type="checkbox" class="lp-cliente-check" value="' + c.id + '">';
                html += '  <span class="lp-cliente-nombre">' + escapeHtml(c.razon_social) + '</span>';
                html += '  <span class="lp-cliente-email">' + escapeHtml(email) + '</span>';
                html += '</label>';
            });
            $('#lpModalClientes').html(html);
            $('#lpModalClientesCount').text(clientes.length + ' cliente' + (clientes.length === 1 ? '' : 's'));
        })
        .fail(function () {
            $('#lpModalClientes').html('<div style="text-align:center;padding:20px;color:#B23A48;">Error de conexión.</div>');
        });

        abrirModal();
    });

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    $('#modalEnviarCerrar, #lpModalCancelar').on('click', cerrarModal);

    $modal.on('click', function (e) {
        if ($(e.target).hasClass('cal-modal-overlay')) cerrarModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.is(':visible')) cerrarModal();
    });

    // Seleccionar todos
    $('#lpModalSelTodos').on('click', function () {
        var $checks = $('.lp-cliente-check');
        var allChecked = $checks.length > 0 && $checks.filter(':checked').length === $checks.length;
        $checks.prop('checked', !allChecked);
        $(this).text(!allChecked ? 'Deseleccionar todos' : 'Seleccionar todos');
    });

    /* ============================================================
       ENVIAR POR EMAIL
       ============================================================ */
    $('#lpModalEnviar').on('click', function () {
        var seleccionados = [];
        $('.lp-cliente-check:checked').each(function () {
            seleccionados.push(parseInt($(this).val(), 10));
        });

        if (seleccionados.length === 0) {
            alert('Seleccioná al menos un cliente para enviar.');
            return;
        }

        var formato = $('input[name="lpFormato"]:checked').val() || 'pdf';

        var $btn = $(this);
        var original = $btn.text();
        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: BASE_URL + 'lista-precios/enviar',
            type: 'POST',
            dataType: 'json',
            timeout: 120000, // 2 minutos (los envíos pueden tardar)
            data: {
                categoria_id: $('#categoria_id').val(),
                numero:       $('#numero').val(),
                fecha:        $('#fecha').val(),
                asunto:       $('#lpModalAsunto').val(),
                cuerpo:       $('#lpModalCuerpo').val(),
                formato:      formato,
                cliente_ids:  JSON.stringify(seleccionados),
            }
        })
        .done(function (res) {
            if (res.ok) {
                var msg = res.msg;
                if (res.fallidos && res.fallidos.length > 0) {
                    msg += '\n\nFallidos:\n- ' + res.fallidos.join('\n- ');
                }
                alert('✓ ' + msg);
                cerrarModal();
            } else {
                alert('✗ ' + (res.msg || 'No se pudo enviar.'));
            }
        })
        .fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación con el servidor.';
            alert('✗ ' + m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

})(jQuery);