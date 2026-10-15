(function ($) {
    'use strict';

    let timer = null;

    function showMsg(type, text) {
        const $box = $('#formMsg');
        const cls = type === 'ok' ? 'msg-ok' : 'msg-error';
        $box.removeClass('msg-ok msg-error').addClass(cls).text(text).show();
        $('html, body').animate({ scrollTop: $box.offset().top - 20 }, 300);
    }

    /* ---------- BUSCADOR ---------- */
    $('#status').on('change', listarProveedores);
    $('#search').on('keyup input', function () {
        clearTimeout(timer);
        timer = setTimeout(listarProveedores, 300);
    });

    function listarProveedores() {
        $.ajax({
            url:  BASE_URL + 'proveedores/buscar',
            type: 'POST',
            dataType: 'json',
            data: {
                search: $('#search').val() || '',
                status: $('#status').val() || ''
            }
        })
        .done(function (res) {
            if (res.status === 'success') {
                $('#proveedoresTableBody').html(res.html);
            } else {
                showMsg('error', res.msg || 'No se pudieron cargar los datos.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de conexión con el servidor.');
        });
    }

    /* ---------- ELIMINAR ---------- */
    $('#proveedoresTableBody').on('click', '.btn-eliminar', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        if (!confirm('¿Desactivar al proveedor "' + nombre + '"?\n\nQuedará inactivo.')) return;

        $.ajax({
            url:  BASE_URL + 'proveedores/eliminar',
            type: 'POST',
            dataType: 'json',
            data: { id: id }
        })
        .done(function (res) {
            if (res.ok) {
                showMsg('ok', res.msg);
                setTimeout(listarProveedores, 600);
            } else {
                showMsg('error', res.msg || 'No se pudo eliminar.');
            }
        })
        .fail(function () {
            showMsg('error', 'Error de comunicación con el servidor.');
        });
    });

    /* ---------- FORM ---------- */
    const $form = $('#proveedorForm');
    if ($form.length) {
        $form.on('submit', function (e) {
            e.preventDefault();
            const $btn = $form.find('button[type="submit"]');
            const original = $btn.text();
            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url:  BASE_URL + 'proveedores/' + ($('input[name="id"]').val()
                        ? 'editar?id=' + $('input[name="id"]').val()
                        : 'crear'),
                type: 'POST',
                dataType: 'json',
                data: $form.serialize()
            })
            .done(function (res) {
                if (res.ok) {
                    showMsg('ok', res.msg);
                    setTimeout(() => window.location.href = BASE_URL + 'proveedores', 900);
                } else {
                    showMsg('error', res.msg || 'Error al guardar.');
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
    }

    /* ---------- ARCA ---------- */
    $('#btnArca').on('click', function () {
        const tipoId = parseInt($('#tipo_documento_id').val(), 10);
        const cuit   = ($('#cuit').val() || '').trim();

        if (tipoId !== 1) {
            showMsg('error', 'El botón ARCA solo funciona cuando el tipo de documento es CUIT.');
            return;
        }
        if (!/^\d{11}$/.test(cuit)) {
            showMsg('error', 'El CUIT debe tener 11 dígitos (sin guiones).');
            return;
        }

        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Consultando...');

        $.ajax({
            url:  BASE_URL + 'proveedores/arca',
            type: 'POST',
            dataType: 'json',
            data: { cuit: cuit }
        })
        .done(function (res) {
            if (!res.ok) {
                showMsg('error', res.msg || 'No se pudo consultar ARCA.');
                return;
            }
            const d = res.datos;
            if (d.razon_social)     $('#razon_social').val(d.razon_social);
            if (d.personeria)       $('#personeria').val(d.personeria);
            if (d.domicilio_fiscal) $('#domicilio_fiscal').val(d.domicilio_fiscal);
            if (d.localidad)        $('#localidad').val(d.localidad);

            if (d.provincia_nombre) {
                $('#provincia_id option').each(function () {
                    if ($(this).text().trim().toLowerCase() === d.provincia_nombre.trim().toLowerCase()) {
                        $('#provincia_id').val($(this).val());
                        return false;
                    }
                });
            }

            showMsg('ok', res.msg || 'Datos obtenidos de ARCA.');
        })
        .fail(function (xhr) {
            const m = (xhr.responseJSON && xhr.responseJSON.msg) || 'Error de comunicación con ARCA.';
            showMsg('error', m);
        })
        .always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    /* ---------- EXPORTAR / IMPRIMIR / PDF ---------- */
    $('#btnExportarExcel').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'proveedores/exportar?' + params;
    });

    $('#btnImprimir').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.open(BASE_URL + 'proveedores/imprimir?' + params, '_blank');
    });

    $('#btnPdf').on('click', function () {
        const params = $.param({ search: $('#search').val() || '', status: $('#status').val() || '' });
        window.location.href = BASE_URL + 'proveedores/pdf?' + params;
    });

})(jQuery);