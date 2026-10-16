<?php
/**
 * views/layouts/header.php
 * Layout superior: <head>, sidebar, topbar.
 * Requiere que el controlador haya cargado:
 *   - $page_title
 *   - $headerGreeting
 *   - (opcional) $pageStyle
 */

// Variables de sesión normalizadas
$userName   = $_SESSION['user_nombre']      ?? 'Usuario';
$userRolId  = (int) ($_SESSION['user_rol']  ?? 0);
$userAvatar = $_SESSION['user_avatar']      ?? null;

// 1 = Desarrollador, 2 = Administrador
$isAdmin = in_array($userRolId, [1, 2], true);

// Defaults si el controlador no los setea
$page_title     = $page_title     ?? 'Escritorio';
$headerGreeting = $headerGreeting ?? ('HOLA. ' . strtoupper($userName));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>dist/img/icon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/style.css">

    <?php if (!empty($pageStyle)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/<?= $pageStyle ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/ayuda.css">
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>
<body>

<div class="app-container">

    <!-- ===================== SIDEBAR ===================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= BASE_URL ?>dist/img/logo-liquicont-small.png" alt="LiquiCont" class="sidebar-logo-small">
            <span class="sidebar-logo-text">LIQUICONT</span>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <!-- INICIO / DASHBOARD -->
                <li class="menu-item">
                    <a href="<?= BASE_URL ?>dashboard" class="menu-link">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </span>
                        <span class="menu-text">Inicio</span>
                    </a>
                </li>

                <!-- VENTAS -->
                <li class="menu-item has-submenu">
                    <a href="#" class="menu-link" data-target="menu-ventas">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/>
                                <path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.8h7.2a2 2 0 0 0 2-1.6L21 8H6"/>
                            </svg>
                        </span>
                        <span class="menu-text">Ventas</span>
                        <span class="menu-arrow">›</span>
                    </a>
                    <ul class="submenu" id="menu-ventas">
                        <li class="submenu-title">VENTAS</li>
                        <li><a href="<?= BASE_URL ?>clientes">Clientes</a></li>
                        <li><a href="<?= BASE_URL ?>pedidos">Pedidos</a></li>
                        <li><a href="<?= BASE_URL ?>facturacion">Facturación</a></li>
                        <li><a href="<?= BASE_URL ?>cobros">Cobros</a></li>
                        <li><a href="<?= BASE_URL ?>lista-precios">Lista de precios</a></li>
                    </ul>
                </li>

                <!-- COMPRAS -->
                <li class="menu-item has-submenu">
                    <a href="#" class="menu-link" data-target="menu-compras">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>
                            </svg>
                        </span>
                        <span class="menu-text">Compras</span>
                        <span class="menu-arrow">›</span>
                    </a>
                    <ul class="submenu" id="menu-compras">
                        <li><a href="<?= BASE_URL ?>proveedores">Proveedores</a></li>
                        <li><a href="<?= BASE_URL ?>comprobantes">Comprobantes</a></li>
                        <li><a href="<?= BASE_URL ?>pagos">Pagos</a></li>
                    </ul>
                </li>

                <!-- PRODUCCIÓN -->
                <li class="menu-item has-submenu">
                    <a href="#" class="menu-link" data-target="menu-produccion">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 2h6"/><path d="M10 2v6.2L4.6 18a2 2 0 0 0 1.8 3h11.2a2 2 0 0 0 1.8-3L14 8.2V2"/><path d="M7.5 14h9"/>
                            </svg>
                        </span>
                        <span class="menu-text">Producción</span>
                        <span class="menu-arrow">›</span>
                    </a>
                    <ul class="submenu" id="menu-produccion">
                        <li><a href="<?= BASE_URL ?>insumos">Insumos</a></li>
                        <li><a href="<?= BASE_URL ?>articulos">Artículos</a></li>
                        <li><a href="<?= BASE_URL ?>ingreso_stock">Ingreso de stock</a></li>
                        <li><a href="<?= BASE_URL ?>historial_stock">Historial de stock</a></li>
                    </ul>
                </li>

                <!-- ESTADÍSTICAS -->
                <li class="menu-item">
                    <a href="<?= BASE_URL ?>estadisticas" class="menu-link">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                            </svg>
                        </span>
                        <span class="menu-text">Estadisticas</span>
                    </a>
                </li>

                <!-- CALENDARIO -->
                <li class="menu-item">
                    <a href="<?= BASE_URL ?>calendario" class="menu-link">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="16" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/>
                                <circle cx="7" cy="13" r="1"/><circle cx="12" cy="13" r="1"/><circle cx="17" cy="13" r="1"/>
                                <circle cx="7" cy="17" r="1"/><circle cx="12" cy="17" r="1"/><circle cx="17" cy="17" r="1"/>
                                <line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>
                            </svg>
                        </span>
                        <span class="menu-text">Calendario de cheques</span>
                    </a>
                </li>

                <!-- USUARIOS (solo admin/desarrollador) -->
                <?php if ($isAdmin): ?>
                <li class="menu-item">
                    <a href="<?= BASE_URL ?>usuarios" class="menu-link">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>
                            </svg>
                        </span>
                        <span class="menu-text">Usuarios</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <!-- CONTABILIDAD (solo admin/desarrollador) -->
                    <li class="menu-item has-submenu">
                        <a href="#" class="menu-link" data-target="menu-contabilidad">
                            <span class="menu-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"/>
                                    <path d="M7 14h2v4H7zM11 9h2v9h-2zM15 11h2v7h-2z"/>
                                </svg>
                            </span>
                            <span class="menu-text">Contabilidad</span>
                            <span class="menu-arrow">›</span>
                        </a>
                        <ul class="submenu" id="menu-contabilidad">
                            <li class="submenu-title">CONTABILIDAD</li>
                            <li><a href="<?= BASE_URL ?>contabilidad">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/asientos">Asientos</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/plan-cuentas">Plan de cuentas</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/ejercicios">Ejercicios</a></li>
                            <li class="submenu-title" style="border-top:1px solid var(--liqui-border);margin-top:6px;">REPORTES</li>
                            <li><a href="<?= BASE_URL ?>contabilidad/libro-diario">Libro Diario</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/libro-mayor">Libro Mayor</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/balance">Sumas y Saldos</a></li>
                            <li class="submenu-title" style="border-top:1px solid var(--liqui-border);margin-top:6px;">ESTADOS CONTABLES</li>
                            <li><a href="<?= BASE_URL ?>contabilidad/estado-resultados">Estado de Resultados</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/balance-general">Balance General</a></li>
                            <li class="submenu-title" style="border-top:1px solid var(--liqui-border);margin-top:6px;">IVA</li>
                            <li><a href="<?= BASE_URL ?>contabilidad/libro-iva-compras">Libro IVA Compras</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/libro-iva-ventas">Libro IVA Ventas</a></li>
                            <li><a href="<?= BASE_URL ?>contabilidad/comparativa">Comparativa Vtas vs Compras</a></li>
                            <li class="submenu-title" style="border-top:1px solid var(--liqui-border);margin-top:6px;">CONFIGURACIÓN</li>
                            <li><a href="<?= BASE_URL ?>contabilidad/configuracion">Config. contable</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- CONFIGURACIÓN -->
                <li class="menu-item">
                    <a href="<?= BASE_URL ?>configuracion" class="menu-link">
                        <span class="menu-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </span>
                        <span class="menu-text">Configuración</span>
                    </a>
                </li>

            </ul>
        </nav>

        <div class="sidebar-footer">
            <button class="btn-sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <span class="btn-toggle-text">Cerrar menú</span>
            </button>
            <button class="btn-close-menu">✕ Cerrar menú</button>
        </div>
    </aside>

    <!-- ===================== CONTENIDO ===================== -->
    <main class="main-content">

        <header class="top-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="header-title">
                    <span class="header-greeting"><?= htmlspecialchars($headerGreeting) ?></span>
                    <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
                </div>
            </div>

            <div class="header-right">
                <?php if (!empty($userAvatar)): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($userAvatar) ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                <?php endif; ?>

                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($userName) ?></span>
                    <div class="user-links">
                        <a href="<?= BASE_URL ?>perfil">Mi perfil</a> •
                        <a href="<?= BASE_URL ?>logout">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-area">
            <?= flashRender() ?>