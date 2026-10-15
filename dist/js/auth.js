/**
 * dist/js/auth.js
 * Maneja login, forgot y reset con AJAX.
 */
(function ($) {
    'use strict';
    $('.auth-screen').hide().fadeIn(600);
    // ----------------------------------------------------------
    // Mostrar / ocultar passwords
    // ----------------------------------------------------------

    $('#togglePassword').on('click', function(e) {
        e.preventDefault();
        const passwordInput = $('#loginpass');
        const imgIcon = $('#imgpass');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-open.png'); // Cambia a ojo abierto (asegúrate de tener el icono)
        } else {
            passwordInput.attr('type', 'password');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-closed.png'); // Vuelve al ojo cerrado
        }
    });

    $('#togglePasswordn').on('click', function(e) {
        e.preventDefault();
        const passwordInput = $('#new_password');
        const imgIcon = $('#imgpassn');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-open.png'); // Cambia a ojo abierto (asegúrate de tener el icono)
        } else {
            passwordInput.attr('type', 'password');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-closed.png'); // Vuelve al ojo cerrado
        }
    });

     $('#togglePasswordc').on('click', function(e) {
        e.preventDefault();
        const passwordInput = $('#confirm_password');
        const imgIcon = $('#imgpassc');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-open.png'); // Cambia a ojo abierto (asegúrate de tener el icono)
        } else {
            passwordInput.attr('type', 'password');
            imgIcon.attr('src', BASE_URL + 'dist/img/icon-eye-closed.png'); // Vuelve al ojo cerrado
        }
    });
    // ----------------------------------------------------------
    // Helpers comunes
    // ----------------------------------------------------------
    function showMsg($box, type, text) {
        const cls = type === 'ok'    ? 'msg-ok'
                  : type === 'error' ? 'msg-error'
                  : 'msg-info';
        $box.removeClass('msg-ok msg-error msg-info')
            .addClass(cls)
            .text(text)
            .show();
    }

    function hideMsg($box) { $box.hide().text(''); }

    function post(url, data, $btn, $msg, onSuccess) {
        const original = $btn.text();
        $btn.prop('disabled', true).text('Procesando...');
        showMsg($msg, 'info', 'Procesando...');
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: data,
        })
        .done(function (res) {
            if (res && res.ok) {
                showMsg($msg, 'ok', res.msg || 'Operación exitosa.');
                if (typeof onSuccess === 'function') onSuccess(res);
            } else {
                showMsg($msg, 'error', (res && res.msg) || 'Error desconocido.');
            }
        })
        .fail(function (xhr) {
            let m = 'Error de comunicación con el servidor.';
            if (xhr.responseJSON && xhr.responseJSON.msg) m = xhr.responseJSON.msg;
            else if (xhr.status === 0) m = 'No hay conexión.';
            else if (xhr.status === 404) m = 'Recurso no encontrado (404).';
            else if (xhr.status === 500) m = 'Error interno del servidor (500).';
            showMsg($msg, 'error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    }

    // ----------------------------------------------------------
    // LOGIN
    // ----------------------------------------------------------
    function initLogin() {
        const $form = $('#loginForm');
        if (!$form.length) return;

        const $msg = $('#formMsg');
        const $btn = $form.find('button[type="submit"]');

        $form.on('submit', function (e) {
            e.preventDefault();
            hideMsg($msg);
            post(
                BASE_URL + 'login',
                {
                    email:    $('#loginemail').val(),
                    password: $('#loginpass').val(),
                },
                $btn,
                $msg,
                function (res) {
                    setTimeout(function () {
                        window.location.href = res.redirect || (BASE_URL + 'dashboard');
                    }, 700);
                }
            );
        });
    }

    // ----------------------------------------------------------
    // FORGOT
    // ----------------------------------------------------------
    function initForgot() {
        const $form = $('#forgotForm');
        if (!$form.length) return;

        const $msg = $('#formMsg');
        const $btn = $form.find('button[type="submit"]');

        $form.on('submit', function (e) {
            e.preventDefault();
            hideMsg($msg);

            post(
                BASE_URL + 'forgot',
                { email: $('#forgotemail').val() },
                $btn,
                $msg,
                function (res) {
                    // En dev el controller devuelve debug_link para testear
                    if (res.debug_link) {
                        console.log('[DEV] Link de reset:', res.debug_link);
                    }
                    $form.find('input').val(''); // limpiar
                }
            );
        });
    }

    // ----------------------------------------------------------
    // RESET
    // ----------------------------------------------------------
    function initReset() {
        const $form = $('#resetForm');
        if (!$form.length) return;

        const $msg = $('#formMsg');
        const $btn = $form.find('button[type="submit"]');

        $form.on('submit', function (e) {
            e.preventDefault();
            hideMsg($msg);

            const pass = $('#new_password').val();
            const conf = $('#confirm_password').val();

            if (pass !== conf) {
                showMsg($msg, 'error', 'Las contraseñas no coinciden.');
                return;
            }

            post(
                BASE_URL + 'reset',
                {
                    token:            $form.find('input[name="token"]').val(),
                    password:         pass,
                    confirm_password: conf,
                },
                $btn,
                $msg,
                function (res) {
                    setTimeout(function () {
                        window.location.href = res.redirect || (BASE_URL + 'login');
                    }, 1200);
                }
            );
        });
    }

    // ----------------------------------------------------------
    // Bootstrap
    // ----------------------------------------------------------
    $(function () {
        initLogin();
        initForgot();
        initReset();
    });



})(jQuery);