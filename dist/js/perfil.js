(function ($) {
    'use strict';

    const $form = $('#perfilForm');
    if (!$form.length) return;

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    $form.on('submit', function (e) {
        e.preventDefault();

        const pass = $('#password').val();
        const conf = $('#confirm_password').val();

        if (pass !== conf) {
            showMsg('error', 'Las contraseñas no coinciden.');
            return;
        }
        if (pass && pass.length < 6) {
            showMsg('error', 'La contraseña debe tener al menos 6 caracteres.');
            return;
        }

        const $btn = $form.find('button[type="submit"]');
        const original = $btn.text();
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url:  BASE_URL + 'perfil',
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                // Actualizar nombre en el topbar sin recargar
                $('.user-name').text($('#nombre').val());
                $('.header-greeting').text('HOLA. ' + $('#nombre').val().toUpperCase());
            } else {
                showMsg('error', res.msg || 'No se pudo guardar.');
            }
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación con el servidor.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });
})(jQuery);