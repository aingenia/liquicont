$(document).ready(function() {
    initTextareas();
    // 1. Cargar el estado guardado en Desktop (o expandido por defecto)
    if ($(window).width() > 768) {
        const savedState = localStorage.getItem('sidebarState');
        
        // Si no hay nada guardado aún, por defecto lo expandimos
        if (savedState === null || savedState === 'expanded') {
            $('#sidebar').addClass('expanded');
            $('.btn-toggle-text').text('Cerrar menú');
        } else {
            $('#sidebar').removeClass('expanded');
            $('.btn-toggle-text').text('Abrir menú');
        }
    }

    // 2. Expansión/Colapso en DESKTOP (Guardando preferencia)
    $('#sidebarToggle').on('click', function() {
        $('#sidebar').toggleClass('expanded');
        
        const isExpanded = $('#sidebar').hasClass('expanded');
        
        // Actualizar texto del botón
        if (isExpanded) {
            $('.btn-toggle-text').text('Cerrar menú');
            localStorage.setItem('sidebarState', 'expanded');
        } else {
            $('.btn-toggle-text').text('Abrir menú');
            localStorage.setItem('sidebarState', 'collapsed');
        }
        
        // Resetea los submenús abiertos al colapsar/expandir
        $('.menu-item').removeClass('open');
    });

    // 3. Abrir/Cerrar en MÓVIL
    $('#mobileMenuBtn').on('click', function() {
        $('#sidebar').addClass('mobile-open');
    });

    $('.btn-close-menu').on('click', function() {
        $('#sidebar').removeClass('mobile-open');
    });

    // 4. Control de Submenús Flotantes vía Clic
    $('.has-submenu > .menu-link').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $parentLi = $(this).closest('.menu-item');
        
        if ($parentLi.hasClass('open')) {
            $parentLi.removeClass('open');
        } else {
            $('.menu-item.has-submenu').removeClass('open');
            $parentLi.addClass('open');
        }
    });

    // 5. Cerrar submenú al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.has-submenu').length) {
            $('.menu-item.has-submenu').removeClass('open');
        }
    });

    // 6. Cerrar submenú al hacer clic en un enlace
    $('.submenu a').on('click', function() {
        $('.menu-item.has-submenu').removeClass('open');
    });

    // 7. Resize: Mantener consistencia si se redimensiona la ventana
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            $('#sidebar').removeClass('mobile-open');
            
            const savedState = localStorage.getItem('sidebarState');
            if (savedState === 'collapsed') {
                $('#sidebar').removeClass('expanded');
                $('.btn-toggle-text').text('Abrir menú');
            } else {
                $('#sidebar').addClass('expanded');
                $('.btn-toggle-text').text('Cerrar menú');
            }
        }
    });

    // Modal de imágenes
    $('.avatar-clickable').on('click', function(e) {
        e.preventDefault();
        var imageSrc = $(this).data('image');
        
        if (imageSrc) {
            $('#modalImage').attr('src', imageSrc);
            document.getElementById('imageModal').showModal();
        }
    });

    function mostrarMensajeFormulario(tipo, texto) {
        const $msg = $('#formMsg');
        alert(texto);
        // Configurar clase y texto del contenedor
        $msg.removeClass('error success')
            .addClass(tipo)
            .addClass('show')
            .text(texto);

        // Hacer scroll suave hacia el contenedor de mensaje
        $('html, body').animate({
            scrollTop: $msg.offset().top - 20 // 20px de margen superior
        }, 400);
    }

    /* ============================================================
   TEXTAREAS — auto-resize y contador de caracteres
   ============================================================ */
    /**
     * Ajusta la altura del textarea al contenido.
     */
    function autoResize($ta) {
        $ta.css('height', 'auto');
        const h = Math.min($ta[0].scrollHeight, 400);
        $ta.css('height', h + 'px');
    }

    /**
     * Actualiza el contador si el textarea tiene data-maxlength.
     */
    function actualizarContador($ta) {
        const max = parseInt($ta.data('maxlength'), 10);
        if (!max) return;

        const len = $ta.val().length;
        const $counter = $ta.siblings('.textarea-counter');

        if (!$counter.length) {
            $ta.after('<div class="textarea-counter"></div>');
        }

        const $c = $ta.siblings('.textarea-counter');
        $c.text(len + ' / ' + max);

        $c.removeClass('cerca exceso');
        if (len > max) $c.addClass('exceso');
        else if (len > max * 0.9) $c.addClass('cerca');
    }

    // Inicializar todos los textareas presentes
    function initTextareas() {
        $('textarea').each(function() {
            const $ta = $(this);
            autoResize($ta);
            actualizarContador($ta);
        });
    }

    // Eventos delegados (por si se agregan textareas dinámicamente)
    $(document).on('input', 'textarea', function() {
        const $ta = $(this);
        autoResize($ta);
        actualizarContador($ta);
    });

    /* ============================================================
   SUBMENÚ — auto-ajuste de posición vertical
   Cuando el submenú abierto excede la pantalla, se sube hasta
   que entra completo.
   ============================================================ */
(function initSubmenuPositioning() {
    function ajustarPosicionSubmenu($submenu) {
        if (!$submenu.length) return;

        // Resetear a posición base para medir bien
        $submenu.css({ top: '0px', bottom: 'auto' });

        const viewportH   = window.innerHeight;
        const submenuRect = $submenu[0].getBoundingClientRect();
        const submenuH    = submenuRect.height;
        const offsetTop   = submenuRect.top;   // top actual respecto al viewport
        const margenSeg   = 10;                // margen de seguridad abajo

        // Si entra completo → no tocar nada
        if (offsetTop + submenuH + margenSeg <= viewportH) return;

        // Si NO entra → calcular cuánto hay que subirlo
        // Idealmente, alinear el fondo del submenú con el fondo del viewport
        const espacioDisponible = viewportH - offsetTop - margenSeg;

        if (submenuH > espacioDisponible) {
            // El submenú es más alto que el espacio restante
            // → subirlo lo suficiente para que entre, pero sin salir por arriba
            const desplazamiento = submenuH - espacioDisponible;
            const nuevoTop = -desplazamiento;

            // Verificar que no se vaya por arriba del viewport
            const topAbsoluto = submenuRect.top + nuevoTop;
            if (topAbsoluto < margenSeg) {
                // No entra ni subiendo todo. Dejar scroll interno.
                $submenu.css({
                    top: (-submenuRect.top + margenSeg) + 'px',
                    maxHeight: (viewportH - 2 * margenSeg) + 'px',
                    overflowY: 'auto'
                });
            } else {
                $submenu.css('top', nuevoTop + 'px');
            }
        }
    }

    // Escuchar cuando se abre un submenú
    $(document).on('click', '.menu-item.has-submenu > .menu-link', function () {
        const $item = $(this).parent();
        // Esperar al siguiente tick para que el submenú sea visible
        setTimeout(function () {
            ajustarPosicionSubmenu($item.find('.submenu'));
        }, 20);
    });

    // Si el JS de la app abre submenús con la clase .open directamente
    // (en lugar de un click), agregamos un MutationObserver
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.type === 'attributes' && m.attributeName === 'class') {
                const $item = $(m.target);
                if ($item.hasClass('menu-item') && $item.hasClass('open')) {
                    setTimeout(function () {
                        ajustarPosicionSubmenu($item.find('.submenu'));
                    }, 20);
                }
            }
        });
    });

    $('.menu-item.has-submenu').each(function () {
        observer.observe(this, { attributes: true, attributeFilter: ['class'] });
    });

    // Recalcular si la ventana cambia de tamaño
    $(window).on('resize', function () {
        $('.menu-item.open').each(function () {
            ajustarPosicionSubmenu($(this).find('.submenu'));
        });
    });
})();

});

