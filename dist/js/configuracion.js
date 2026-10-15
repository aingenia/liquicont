/**
 * dist/js/configuracion.js
 * Módulo Configuración — tabs + guardado de parámetros + emisores.
 */
(function ($) {
    'use strict';

    function showMsg(type, text) {
        var $box = $('#formMsg');
        var cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
        setTimeout(function () { $box.fadeOut(600); }, 4000);
    }

    /* ============================================================
       TABS
       ============================================================ */
    $('.cfg-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.cfg-tab').removeClass('active');
        $(this).addClass('active');
        $('.cfg-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');

        // Actualizar URL (para poder refrescar sin perder la tab)
        if (history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            history.replaceState(null, '', url);
        }
    });

    // Activar la tab que venga en la URL
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab === 'emisores') {
        $('.cfg-tab[data-tab="emisores"]').trigger('click');
    }

    /* ============================================================
       GUARDAR PARÁMETROS DEL SISTEMA
       ============================================================ */
    var $formParams = $('#formParametros');
    if ($formParams.length) {
        $formParams.on('submit', function (e) {
            e.preventDefault();
            var $btn = $('#btnGuardarParametros');
            var original = $btn.text();
            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url: BASE_URL + 'configuracion/guardar-parametros',
                type: 'POST',
                dataType: 'json',
                data: $formParams.serialize()
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                } else {
                    showMsg('error', res.msg || 'Error al guardar.');
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
       LISTADO DE EMISORES — activar/desactivar
       ============================================================ */
    $(document).on('click', '.btn-toggle-emisor', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var activo = parseInt($btn.data('activo'), 10);
        var nombre = $btn.data('nombre');
        var nuevoEstado = activo === 1 ? 0 : 1;
        var accion = activo === 1 ? 'desactivar' : 'activar';

        if (!confirm('¿Seguro que querés ' + accion + ' el emisor "' + nombre + '"?')) return;

        $btn.prop('disabled', true);

        $.ajax({
            url: BASE_URL + 'configuracion/emisor-estado',
            type: 'POST',
            dataType: 'json',
            data: { id: id, activo: nuevoEstado }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                showMsg('error', res.msg || 'No se pudo actualizar.');
            }
        })
        .fail(function (xhr) {
            var m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ============================================================
       FORM DE EMISOR (crear / editar)
       ============================================================ */
    var $formEmisor = $('#emisorForm');
    if ($formEmisor.length) {
        $formEmisor.on('submit', function (e) {
            e.preventDefault();

            var id = $('input[name="id"]').val();
            var url = BASE_URL + 'configuracion/' + (id ? 'emisor-editar' : 'emisor-crear');
            var $btn = $formEmisor.find('button[type="submit"]');
            var original = $btn.text();

            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $formEmisor.serialize()
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                    setTimeout(function () {
                        window.location.href = res.redirect || (BASE_URL + 'configuracion?tab=emisores');
                    }, 800);
                } else {
                    showMsg('error', res.msg || 'Error al guardar.');
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

})(jQuery);