(function ($) {
    'use strict';

    // Declarar el timer a nivel de scope del IIFE
    let timer = null;

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    /* ---------- BUSCADOR ---------- */
    // Al cambiar el select de estado
    $('#status').on('change', function () {
        listarUsuarios();
    });

    // Al escribir en el buscador (debounce 300ms)
    $('#search').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(listarUsuarios, 300);
    });

    // Evitar que Enter en el input recargue la página
    $('.filter-section').on('submit', function (e) {
        e.preventDefault();
        listarUsuarios();
    });

    function listarUsuarios() {
        const formData = $('.filter-section').serialize();

        $.ajax({
            url:  BASE_URL + 'usuarios/buscar',   // ← antes era usuarios/listar
            type: 'POST',
            dataType: 'json',
            data: formData
        })
        .done(function (response) {
            if (response.status === 'success') {
                $('#usuariosTableBody').html(response.html);
            } else {
                showMsg('error', response.msg || 'No se pudieron cargar los datos.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de conexión con el servidor.');
        });
    }

    /* ---------- FORM CREAR/EDITAR ---------- */
    const $form = $('#usuarioForm');
    if ($form.length) {
        $form.on('submit', function (e) {
            e.preventDefault();
            const $btn = $form.find('button[type="submit"]');
            const original = $btn.text();

            const pass = $('#password').val();
            if (pass && pass.length < 6) {
                showMsg('error', 'La contraseña debe tener al menos 6 caracteres.');
                return;
            }

            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url:  BASE_URL + 'usuarios/' + ($('input[name="id"]').val()
                        ? 'editar?id=' + $('input[name="id"]').val()
                        : 'crear'),
                type: 'POST',
                dataType: 'json',
                data: $form.serialize()
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                    setTimeout(() => window.location.href = BASE_URL + 'usuarios', 900);
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

    /* ---------- ELIMINAR ---------- */
    // Delegación: los botones vienen del AJAX, hay que delegar en el tbody
    $('#usuariosTableBody').on('click', '.btn-eliminar', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        if (!confirm('¿Desactivar al usuario "' + nombre + '"?\n\nEl usuario dejará de poder iniciar sesión.')) {
            return;
        }

        $.ajax({
            url:  BASE_URL + 'usuarios/eliminar',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(listarUsuarios, 600);  // refrescar en lugar de recargar
            } else {
                showMsg('error', res.msg || 'No se pudo eliminar.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de comunicación con el servidor.');
        });
    });

        /* ---------- EXPORTAR CSV ---------- */
    $('#btnExportarExcel').on('click', function () {
        const params = $.param({
            search: $('#search').val() || '',
            status: $('#status').val() || ''
        });
        window.location.href = BASE_URL + 'usuarios/exportar?' + params;
    });

        /* ---------- DESCARGAR PDF ---------- */
    $('#btnPdf').on('click', function () {
        const params = $.param({
            search: $('#search').val() || '',
            status: $('#status').val() || ''
        });
        window.location.href = BASE_URL + 'usuarios/pdf?' + params;
    });

    /* ---------- IMPRIMIR ---------- */
    $('#btnImprimir').on('click', function () {
        const params = $.param({
            search: $('#search').val() || '',
            status: $('#status').val() || ''
        });
        window.open(BASE_URL + 'usuarios/imprimir?' + params, '_blank');
    });

})(jQuery);