/**
 * dist/js/estadisticas.js
 * Módulo Estadísticas — renderiza gráficos con ApexCharts.
 */
(function ($) {
    'use strict';

    var charts = {}; // Guardar las instancias para destruirlas antes de re-renderizar

    /* ============================================================
       HELPERS
       ============================================================ */
    function fmtMoney(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    function fmtNumber(n) {
        return Number(n || 0).toLocaleString('es-AR', {
            maximumFractionDigits: 2
        });
    }

    function rango() {
        return {
            desde: $('#est_desde').val() || '',
            hasta: $('#est_hasta').val() || ''
        };
    }

    function getChart(id) {
        if (charts[id]) {
            charts[id].destroy();
        }
        return null;
    }

    // Paleta Krown para gráficos
    var COLORS = [
        '#14110E', // negro
        '#C9973A', // dorado
        '#0F5C52', // teal
        '#B23A48', // crimson
        '#6B4FA0', // violeta
        '#5B6B8C', // azul gris
        '#8a6a14', // dorado oscuro
        '#17836F', // teal claro
        '#E4C077', // dorado claro
        '#7A7266', // gris
    ];

    /* ============================================================
       TABS
       ============================================================ */
    $('.fac-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.fac-tab').removeClass('active');
        $(this).addClass('active');
        $('.fac-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');

        // Cargar los datos de la tab activa
        cargarTab(tab);
    });

    function cargarTab(tab) {
        switch (tab) {
            case 'facturacion': cargarFacturacion(); break;
            case 'ventas':      cargarVentasMes();   break;
            case 'formatos':    cargarFormatos();    break;
            case 'clientes':    cargarClientes();    break;
            case 'sabores':     cargarSabores();     break;
        }
    }

    /* ============================================================
       TAB 1: FACTURACIÓN POR EMISOR
       ============================================================ */
    function cargarFacturacion() {
        var r = rango();

        $.ajax({
            url: BASE_URL + 'estadisticas/facturacion-emisor',
            type: 'POST',
            dataType: 'json',
            data: r
        })
        .done(function (res) {
            if (!res.ok) return;

            $('#estFacTotal').text(fmtMoney(res.total));

            getChart('facturacion');
            charts.facturacion = new ApexCharts(document.querySelector('#chartFacturacion'), {
                series: res.series,
                chart: {
                    type: 'bar',
                    height: 380,
                    toolbar: { show: false },
                    fontFamily: 'Work Sans, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 6,
                        distributed: true
                    }
                },
                colors: COLORS,
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return fmtMoney(val);
                    },
                    style: { fontSize: '12px', fontWeight: 700, colors: ['#FFF'] }
                },
                legend: { show: false },
                xaxis: {
                    categories: res.labels,
                    labels: { style: { fontSize: '13px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: {
                        formatter: function (val) {
                            return '$ ' + Number(val).toLocaleString('es-AR');
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return fmtMoney(val);
                        }
                    }
                }
            });
            charts.facturacion.render();
        });
    }

    /* ============================================================
       TAB 2: VENTAS MES vs MES
       ============================================================ */
    var aniosSeleccionados = [];

    function initSelectorAnios() {
        $.ajax({
            url: BASE_URL + 'estadisticas/ventas-mes',
            type: 'POST',
            dataType: 'json',
            data: { anios: '' }
        })
        .done(function (res) {
            if (!res.ok || !res.series) return;

            // Mostrar chips con los años disponibles
            var $cont = $('#estAniosSelector').empty();
            aniosSeleccionados = [];

            res.series.forEach(function (s, i) {
                var anio = s.name;
                // Por defecto seleccionamos los primeros 3
                var activo = i < 3;
                if (activo) aniosSeleccionados.push(parseInt(anio, 10));

                $cont.append(
                    '<button type="button" class="est-anio-chip' + (activo ? ' active' : '') +
                    '" data-anio="' + anio + '">' + anio + '</button>'
                );
            });
        });
    }

    $('#estAniosSelector').on('click', '.est-anio-chip', function () {
        $(this).toggleClass('active');

        // Recolectar años activos
        aniosSeleccionados = [];
        $('#estAniosSelector .est-anio-chip.active').each(function () {
            aniosSeleccionados.push(parseInt($(this).data('anio'), 10));
        });

        if (aniosSeleccionados.length === 0) {
            // Si no queda ninguno seleccionado, dejamos el último clickeado
            $(this).addClass('active');
            aniosSeleccionados = [parseInt($(this).data('anio'), 10)];
        }

        cargarVentasMes();
    });

    function cargarVentasMes() {
        $.ajax({
            url: BASE_URL + 'estadisticas/ventas-mes',
            type: 'POST',
            dataType: 'json',
            data: { anios: aniosSeleccionados.join(',') }
        })
        .done(function (res) {
            if (!res.ok) return;

            getChart('ventas');
            charts.ventas = new ApexCharts(document.querySelector('#chartVentas'), {
                series: res.series,
                chart: {
                    type: 'line',
                    height: 400,
                    toolbar: { show: false },
                    fontFamily: 'Work Sans, sans-serif'
                },
                colors: COLORS,
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 5,
                    strokeWidth: 2,
                    hover: { size: 7 }
                },
                dataLabels: {
                    enabled: true,
                    style: { fontSize: '11px', fontWeight: 600 },
                    background: {
                        enabled: true,
                        foreColor: '#FFF',
                        borderRadius: 4,
                        padding: 3
                    }
                },
                xaxis: {
                    categories: res.labels,
                    labels: { style: { fontSize: '13px', fontWeight: 600 } }
                },
                yaxis: {
                    labels: { formatter: function (v) { return Math.round(v); } },
                    title: { text: 'Cajas equivalentes', style: { fontWeight: 600 } }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontWeight: 700,
                    fontSize: '14px'
                },
                tooltip: {
                    y: { formatter: function (v) { return fmtNumber(v) + ' cajas'; } }
                }
            });
            charts.ventas.render();
        });
    }

    /* ============================================================
       TAB 3: FORMATOS
       ============================================================ */
    function cargarFormatos() {
        var r = rango();

        $.ajax({
            url: BASE_URL + 'estadisticas/formatos',
            type: 'POST',
            dataType: 'json',
            data: r
        })
        .done(function (res) {
            if (!res.ok) return;

            // Gráfico de barras con cajas
            getChart('formatos');
            charts.formatos = new ApexCharts(document.querySelector('#chartFormatos'), {
                series: [{ name: 'Cajas', data: res.cajas }],
                chart: {
                    type: 'bar',
                    height: 380,
                    toolbar: { show: false },
                    fontFamily: 'Work Sans, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 6,
                        distributed: true,
                        barHeight: '60%'
                    }
                },
                colors: COLORS,
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return fmtNumber(val) + ' cj'; },
                    style: { fontSize: '12px', fontWeight: 700, colors: ['#FFF'] }
                },
                legend: { show: false },
                xaxis: {
                    categories: res.labels,
                    labels: { style: { fontSize: '13px' } }
                },
                tooltip: {
                    y: { formatter: function (v) { return fmtNumber(v) + ' cajas'; } }
                }
            });
            charts.formatos.render();

            // Gráfico de torta con porcentajes
            getChart('formatosPct');
            charts.formatosPct = new ApexCharts(document.querySelector('#chartFormatosPct'), {
                series: res.cajas,
                chart: {
                    type: 'donut',
                    height: 380,
                    fontFamily: 'Work Sans, sans-serif'
                },
                labels: res.labels,
                colors: COLORS,
                legend: { position: 'bottom' },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return val.toFixed(1) + '%'; },
                    style: { fontSize: '13px', fontWeight: 700 }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '60%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total cajas',
                                    fontSize: '14px',
                                    fontWeight: 700,
                                    formatter: function () { return fmtNumber(res.total_cajas); }
                                },
                                value: {
                                    fontSize: '22px',
                                    fontWeight: 800
                                }
                            }
                        }
                    }
                }
            });
            charts.formatosPct.render();
        });
    }

    /* ============================================================
       TAB 4: CLIENTES
       ============================================================ */
    function cargarClientes() {
        var r = rango();
        var limite = $('#est_clientes_limite').val() || 20;

        $.ajax({
            url: BASE_URL + 'estadisticas/clientes',
            type: 'POST',
            dataType: 'json',
            data: { desde: r.desde, hasta: r.hasta, limite: limite }
        })
        .done(function (res) {
            if (!res.ok) return;

            getChart('clientes');
            charts.clientes = new ApexCharts(document.querySelector('#chartClientes'), {
                series: [{ name: 'Cajas', data: res.cajas }],
                chart: {
                    type: 'bar',
                    height: Math.max(400, res.labels.length * 32),
                    toolbar: { show: false },
                    fontFamily: 'Work Sans, sans-serif'
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 6,
                        barHeight: '65%'
                    }
                },
                colors: ['#0F5C52'],
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return fmtNumber(val); },
                    offsetX: 0,
                    style: { fontSize: '12px', fontWeight: 700, colors: ['#0F5C52'] }
                },
                xaxis: {
                    categories: res.labels,
                    labels: { style: { fontSize: '12px' } }
                },
                tooltip: {
                    y: { formatter: function (v) { return fmtNumber(v) + ' cajas'; } }
                }
            });
            charts.clientes.render();
        });
    }

    $('#est_clientes_limite').on('change', cargarClientes);

    /* ============================================================
       TAB 5: SABORES
       ============================================================ */
    function cargarSabores() {
        var r = rango();
        var formato = $('#est_sabores_formato').val() || '';

        $.ajax({
            url: BASE_URL + 'estadisticas/sabores',
            type: 'POST',
            dataType: 'json',
            data: { desde: r.desde, hasta: r.hasta, formato: formato }
        })
        .done(function (res) {
            if (!res.ok) return;

            // Donut
            getChart('sabores');
            charts.sabores = new ApexCharts(document.querySelector('#chartSabores'), {
                series: res.series,
                chart: {
                    type: 'pie',
                    height: 420,
                    fontFamily: 'Work Sans, sans-serif'
                },
                labels: res.labels,
                colors: COLORS,
                legend: { show: false },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) { return val.toFixed(1) + '%'; },
                    style: { fontSize: '12px', fontWeight: 700, colors: ['#FFF'] }
                },
                tooltip: {
                    y: { formatter: function (v) { return fmtNumber(v) + ' unidades'; } }
                }
            });
            charts.sabores.render();

            // Tabla al costado
            var html = '';
            res.items.forEach(function (it, i) {
                var color = COLORS[i % COLORS.length];
                html += '<div class="est-sabor-item">';
                html += '  <span class="est-sabor-color" style="background:' + color + '"></span>';
                html += '  <span class="est-sabor-nombre">' + escapeHtml(it.sabor) + '</span>';
                html += '  <span class="est-sabor-valor">' + fmtNumber(it.unidades) + ' u.</span>';
                html += '  <span class="est-sabor-pct">' + it.pct.toFixed(1) + '%</span>';
                html += '</div>';
            });
            $('#tablaSabores').html(html || '<p style="text-align:center;color:#7A7266;">Sin datos en el período.</p>');
        });
    }

    $('#est_sabores_formato').on('change', cargarSabores);

    function escapeHtml(s) {
        return $('<div>').text(s ?? '').html();
    }

    /* ============================================================
       FILTROS GLOBALES
       ============================================================ */
    $('#est_desde, #est_hasta').on('change', function () {
        // Recargar la tab activa
        var tabActiva = $('.fac-tab.active').data('tab');
        cargarTab(tabActiva);
    });

    $('#btnEstRangoActual').on('click', function () {
        $('#est_desde').val(new Date().getFullYear() + '-01-01');
        $('#est_hasta').val(new Date().toISOString().substring(0, 10));
        cargarTab($('.fac-tab.active').data('tab'));
    });

    $('#btnEstUltimos30').on('click', function () {
        var hoy = new Date();
        var hace30 = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
        $('#est_desde').val(hace30.toISOString().substring(0, 10));
        $('#est_hasta').val(hoy.toISOString().substring(0, 10));
        cargarTab($('.fac-tab.active').data('tab'));
    });

    $('#btnEstAnioAnterior').on('click', function () {
        var anio = new Date().getFullYear() - 1;
        $('#est_desde').val(anio + '-01-01');
        $('#est_hasta').val(anio + '-12-31');
        cargarTab($('.fac-tab.active').data('tab'));
    });

    /* ============================================================
       BOOTSTRAP
       ============================================================ */
    $(function () {
        // Cargar la primera tab + inicializar el selector de años
        cargarFacturacion();
        initSelectorAnios();
    });

})(jQuery);