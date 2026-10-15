<?php
/**
 * controllers/ListaPreciosController.php
 * Solo Admin/Dev para guardar y enviar.
 * Vendedor puede ver la lista.
 */

class ListaPreciosController
{
    private ListaPrecios $model;
    private ConfiguracionApp $config;

    public function __construct()
    {
        if (!puedeVerListaPrecios()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->model  = new ListaPrecios();
        $this->config = new ConfiguracionApp();
    }

    /* ==========================================================
       INDEX — vista principal
       ========================================================== */
        /* ==========================================================
       INDEX — vista principal
       ========================================================== */
    public function index(): void
    {
        $categorias = $this->model->categorias();
        $catSel = (int)($_GET['categoria_id'] ?? ($categorias[0]['id'] ?? 0));

        // --- Número y fecha sugeridos según el mes ---
        $numeroGuardado = (int)$this->config->obtener('lista_precios_numero', '0');
        $fechaGuardada  = $this->config->obtener('lista_precios_vigente_desde', '');

        // Si no hay fecha guardada, usar el día 1 del mes actual
        if (!$fechaGuardada) {
            $fechaGuardada = date('Y-m-01');
        }

        $mesGuardado = date('Y-m', strtotime($fechaGuardada));
        $mesActual   = date('Y-m');

        if ($mesActual === $mesGuardado) {
            // Mismo mes: mismo número, misma fecha
            $numeroSugerido = $numeroGuardado > 0 ? $numeroGuardado : 1;
            $fechaDefault   = $fechaGuardada;
        } elseif ($mesActual > $mesGuardado) {
            // Mes nuevo: número + 1, fecha = día 1 del mes actual
            $numeroSugerido = $numeroGuardado + 1;
            $fechaDefault   = date('Y-m-01');
        } else {
            // Fecha guardada en el futuro (override manual): mantener
            $numeroSugerido = $numeroGuardado > 0 ? $numeroGuardado : 1;
            $fechaDefault   = $fechaGuardada;
        }

        // Override por query string (para preview sin guardar)
        if (isset($_GET['numero'])) {
            $numeroSugerido = (int)$_GET['numero'];
        }
        if (isset($_GET['fecha'])) {
            $fechaDefault = $_GET['fecha'];
        }

        // Grupos (con la categoría aplicada)
        $grupos = $this->model->articulosAgrupados($catSel);

        // Asunto y cuerpo por defecto del email
        $emailAsunto = $this->config->obtener('email_lista_precios_asunto', 'Lista de Precios N° {{lista_numero}}');
        $emailCuerpo = $this->config->obtener('email_lista_precios_cuerpo', '');

        // ¿Podemos generar imagen en este servidor?
        require_once ROOT_PATH . 'includes' . DS . 'lista_precios_pdf.php';
        $puedeGenerarImagen = puedeGenerarImagenListaPrecios();

        $page_title     = 'Lista de Precios';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'lista_precios.js';
        $pageStyle      = 'lista_precios.css';

        require_once ROOT_PATH . 'views' . DS . 'lista_precios.php';
    }

    /* ==========================================================
       GUARDAR CONFIGURACIÓN (número, fecha, asunto, cuerpo)
       ========================================================== */
    public function guardar(): void
    {
        if (!puedeEditarListaPrecios()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $numero = (int)($_POST['numero'] ?? 0);
        $fecha  = trim($_POST['fecha'] ?? '');
        $asunto = trim($_POST['email_asunto'] ?? '');
        $cuerpo = trim($_POST['email_cuerpo'] ?? '');

        if ($numero <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'El número de lista debe ser mayor a 0.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }

        $this->config->guardar('lista_precios_numero', (string)$numero);
        $this->config->guardar('lista_precios_vigente_desde', $fecha);

        if ($asunto !== '') {
            $this->config->guardar('email_lista_precios_asunto', $asunto);
        }
        if ($cuerpo !== '') {
            $this->config->guardar('email_lista_precios_cuerpo', $cuerpo);
        }

        jsonResponse(['ok' => true, 'msg' => 'Lista de precios actualizada.']);
    }

    /* ==========================================================
       GENERAR PDF (descarga)
       ========================================================== */
    public function generarPdf(): void
    {
        $categoriaId = (int)($_GET['categoria_id'] ?? 0);
        if ($categoriaId <= 0) {
            header('Location: ' . BASE_URL . 'lista-precios');
            exit;
        }

        // Si vinieron número/fecha por query string, se usan esos.
        // Si no, se leen del config.
        $numeroOverride = isset($_GET['numero']) ? (int)$_GET['numero'] : null;
        $fechaOverride  = $_GET['fecha'] ?? null;

        require_once ROOT_PATH . 'includes' . DS . 'lista_precios_pdf.php';

        try {
            $pdf = generarPdfListaPrecios($categoriaId, $numeroOverride, $fechaOverride);
            $nombre = 'Lista-Precios-' . ($numeroOverride ?: (int)$this->config->obtener('lista_precios_numero', '0')) . '.pdf';
            $this->_streamPdf($pdf, $nombre);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '<h2>No se pudo generar el PDF</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        exit;
    }

    /* ==========================================================
       CLIENTES CON EMAIL DE UNA CATEGORÍA (AJAX — modal envío)
       ========================================================== */
    public function clientesPorCategoria(): void
    {
        if (!puedeEditarListaPrecios()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $catId = (int)($_POST['categoria_id'] ?? 0);
        if ($catId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Categoría inválida.']);
        }

        $clientes = $this->model->clientesConEmailPorCategoria($catId);

        jsonResponse(['ok' => true, 'clientes' => $clientes]);
    }

    /* ==========================================================
       ENVIAR POR EMAIL
       ========================================================== */
    public function enviar(): void
    {
        if (!puedeEditarListaPrecios()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $numero      = (int)($_POST['numero'] ?? 0);
        $fecha       = trim($_POST['fecha'] ?? '');
        $asunto      = trim($_POST['asunto'] ?? '');
        $cuerpo      = trim($_POST['cuerpo'] ?? '');
        $formato     = $_POST['formato'] ?? 'pdf'; // 'pdf' o 'imagen'
        $clienteIds  = json_decode($_POST['cliente_ids'] ?? '[]', true) ?: [];

        if ($categoriaId <= 0 || $numero <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }
        if (empty($clienteIds)) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná al menos un cliente.']);
        }
        if ($formato !== 'pdf' && $formato !== 'imagen') {
            $formato = 'pdf';
        }

        // Guardar los valores por si cambiaron (autoguardado)
        $this->config->guardar('lista_precios_numero', (string)$numero);
        $this->config->guardar('lista_precios_vigente_desde', $fecha);
        $this->config->guardar('email_lista_precios_asunto', $asunto);
        $this->config->guardar('email_lista_precios_cuerpo', $cuerpo);

        // Generar el PDF (una sola vez)
        require_once ROOT_PATH . 'includes' . DS . 'lista_precios_pdf.php';
        require_once ROOT_PATH . 'includes' . DS . 'Mailer.php';
        require_once ROOT_PATH . 'includes' . DS . 'email_helpers.php';


        try {
            $pdfBytes = generarPdfListaPrecios($categoriaId, $numero, $fecha);
        } catch (\Throwable $e) {
            jsonResponse(['ok' => false, 'msg' => 'Error al generar el PDF: ' . $e->getMessage()]);
        }

        // Convertir a imagen si se pidió
        $adjunto = null;
        if ($formato === 'imagen') {
            $imagenBytes = convertirPdfAImagen($pdfBytes);
            if ($imagenBytes === null) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo convertir el PDF a imagen en este servidor.']);
            }
            $adjunto = [
                'nombre'    => 'Lista-Precios-' . $numero . '.png',
                'contenido' => $imagenBytes,
                'mime'      => 'image/png',
            ];
        } else {
            $adjunto = [
                'nombre'    => 'Lista-Precios-' . $numero . '.pdf',
                'contenido' => $pdfBytes,
                'mime'      => 'application/pdf',
            ];
        }

        // Traer clientes seleccionados
        $todos = $this->model->clientesConEmailPorCategoria($categoriaId);
        $destinatarios = array_filter($todos, function ($c) use ($clienteIds) {
            return in_array((int)$c['id'], $clienteIds, true);
        });

        if (empty($destinatarios)) {
            jsonResponse(['ok' => false, 'msg' => 'No se encontraron clientes para enviar.']);
        }

        $fechaFmt = date('d/m/Y', strtotime($fecha));
        $enviados = 0;
        $fallidos = [];

        foreach ($destinatarios as $c) {
            $nombre = $c['razon_social'];

            // Emails: preferimos email_facturacion, después email
            $emails = [];
            if (!empty($c['email_facturacion'])) {
                foreach (explode(',', $c['email_facturacion']) as $e) {
                    $e = trim($e);
                    if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            }
            if (!empty($c['email']) && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
                $emails[] = $c['email'];
            }
            $emails = array_values(array_unique($emails));

            if (empty($emails)) {
                $fallidos[] = $nombre . ' (sin email válido)';
                continue;
            }

            // Reemplazar placeholders
            $valores = [
                'cliente_nombre' => $nombre,
                'lista_numero'   => $numero,
                'vigente_desde'  => $fechaFmt,
            ];
            $asuntoFinal = reemplazarPlaceholdersEmail($asunto, $valores);
            $cuerpoFinal = reemplazarPlaceholdersEmail($cuerpo, $valores);
            $cuerpoFinal = nl2br(htmlspecialchars($cuerpoFinal));

            // Enviar al primero (o a todos los emails del cliente, según política)
            $emailDestino = $emails[0];
            $ok = \Mailer::enviarConAdjunto(
                $emailDestino,
                $asuntoFinal,
                $cuerpoFinal,
                $adjunto,
                $nombre
            );

            if ($ok) {
                $enviados++;
            } else {
                $fallidos[] = $nombre . ' (ver log de Apache)';
            }
        }

        $msg = "Enviados: $enviados";
        if (!empty($fallidos)) {
            $msg .= " · Fallidos: " . count($fallidos);
        }

        jsonResponse([
            'ok'       => true,
            'msg'      => $msg,
            'enviados' => $enviados,
            'fallidos' => $fallidos,
        ]);
    }

    /* ==========================================================
       HELPER — Stream del PDF
       ========================================================== */
    private function _streamPdf(string $bytes, string $nombre): void
    {
        $descargar = !empty($_GET['descargar']);
        $disposicion = $descargar ? 'attachment' : 'inline';

        if (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposicion . '; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $bytes;
        exit;
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'lista_precios.php';
    }
}