/**
 * dist/js/ayuda.js
 * Modal de ayuda genérico. Sirve para todos los módulos.
 *
 * Uso en cualquier vista:
 *   <button class="btn-ayuda" data-ayuda-url="<?= BASE_URL ?>insumos/ayuda">
 *       <svg>...</svg> Ayuda
 *   </button>
 *
 * El JS carga el HTML por AJAX y lo muestra en el <dialog>.
 */
(function ($) {
    'use strict';

    const $modal = $('#ayudaModal');
    const $body  = $('#ayudaModalBody');
    const $title = $('#ayudaModalTitle');

    if (!$modal.length) return;

    // Cache por URL (si abrís la misma ayuda dos veces, no repite el fetch)
    const cache = {};

    function abrirModal(url, titulo) {
        if (titulo) $title.text(titulo);

        if (cache[url]) {
            $body.html(cache[url]);
            mostrarDialogo();
            return;
        }

        $body.html('<p style="text-align:center;color:#7A7266;">Cargando...</p>');
        mostrarDialogo();

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            cache: true
        })
        .done(function (html) {
            cache[url] = html;
            $body.html(html);
        })
        .fail(function (xhr) {
            let msg = 'No se pudo cargar la ayuda.';
            if (xhr.status === 404) msg = 'Todavía no hay ayuda para este módulo.';
            if (xhr.status === 403) msg = 'No tenés permisos para ver esta ayuda.';
            $body.html('<p style="text-align:center;color:#B23A48;">' + msg + '</p>');
        });
    }

    function mostrarDialogo() {
        if (typeof $modal[0].showModal === 'function') {
            $modal[0].showModal();
            $body.scrollTop(0);
        }
    }

    function cerrarModal() {
        if (typeof $modal[0].close === 'function') {
            $modal[0].close();
        }
    }

    // Abrir: delegado (para que funcione aunque el botón se agregue dinámicamente)
    $(document).on('click', '.btn-ayuda', function (e) {
        e.preventDefault();
        const url    = $(this).data('ayuda-url');
        const titulo = $(this).data('ayuda-titulo') || 'Ayuda';
        if (!url) return;
        abrirModal(url, titulo);
    });

    // Cerrar con la X
    $(document).on('click', '.ayuda-modal-close', cerrarModal);

    // Cerrar al hacer click en el backdrop (fuera del modal)
    $modal.on('click', function (e) {
        if (e.target === $modal[0]) {
            cerrarModal();
        }
    });

    // Ajustar tamaño al redimensionar (por si cambia de landscape a portrait)
    $(window).on('resize', function () {
        if ($modal[0].open) {
            // Nada especial: el CSS ya reacciona
        }
    });

})(jQuery);