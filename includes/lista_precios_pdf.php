<?php
/**
 * includes/lista_precios_pdf.php
 * Genera el PDF de la Lista de Precios con TCPDF.
 *
 * Uso:
 *   $pdf = generarPdfListaPrecios($categoriaId);
 *   $pdf = generarPdfListaPrecios($categoriaId, 39, '2026-09-01');
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/ListaPrecios.php';
require_once __DIR__ . '/../models/ConfiguracionApp.php';

define('K_TCPDF_EXTERNAL_CONFIG', false);
require_once __DIR__ . '/tcpdf/tcpdf.php';

/**
 * @param int         $categoriaId   Categoría de cliente a listar.
 * @param int|null    $numeroOverride Si se pasa, sobreescribe el número del config.
 * @param string|null $fechaOverride  Si se pasa (Y-m-d), sobreescribe la fecha del config.
 * @return string Bytes del PDF.
 */
function generarPdfListaPrecios(
    int $categoriaId,
    ?int $numeroOverride = null,
    ?string $fechaOverride = null
): string {
    $listaModel = new ListaPrecios();
    $config = new ConfiguracionApp();

    // Categorías: para mostrar en el subtítulo
    $catDescripcion = '';
    foreach ($listaModel->categorias() as $c) {
        if ((int)$c['id'] === $categoriaId) {
            $catDescripcion = $c['descripcion'];
            break;
        }
    }

    // Número y fecha
    $numero = $numeroOverride !== null
        ? $numeroOverride
        : (int)$config->obtener('lista_precios_numero', '0');

    $fecha = $fechaOverride
        ?: ($config->obtener('lista_precios_vigente_desde', '') ?: date('Y-m-d'));

    $fechaFmt = _lp_fecha_ddmmaaaa($fecha);

    // Empresa
    $empresa = [
        'razon_social' => $config->obtener('empresa_razon_social', ''),
        'domicilio'    => $config->obtener('empresa_domicilio', ''),
        'telefono'     => $config->obtener('empresa_telefono', ''),
        'sitio_web'    => $config->obtener('empresa_sitio_web', ''),
        'cuit'         => $config->obtener('empresa_cuit', ''),
    ];

    // Grupos (con la categoría aplicada)
    $grupos = $listaModel->articulosAgrupados($categoriaId);

    // ============================================================
    // PDF
    // ============================================================
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('LiquiCont');
    $pdf->SetAuthor($empresa['razon_social']);
    $pdf->SetTitle('Lista de Precios N° ' . $numero);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 14, 15);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    // ---------- LOGO ----------
    $logoPath = ROOT_PATH . 'dist' . DS . 'img' . DS . 'logo-liquicont-black.png';
    if (is_file($logoPath)) {
        $anchoLogo = 55;
        $dim = @getimagesize($logoPath);
        $altoLogo = $dim ? $anchoLogo * ($dim[1] / $dim[0]) : $anchoLogo * 0.48;
        $x = (210 - $anchoLogo) / 2;
        $pdf->Image($logoPath, $x, $pdf->GetY(), $anchoLogo, $altoLogo, 'PNG', '', '', false, 300);
        $pdf->Ln($altoLogo + 6);
    } else {
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->Cell(0, 8, $empresa['razon_social'], 0, 1, 'C');
        $pdf->Ln(2);
    }

    // ---------- BARRA: N° | LEYENDA | VIGENCIA ----------
    $anchoUtil = $pdf->getPageWidth() - 30; // 180mm

    $pdf->SetLineWidth(0.2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);

    // Fila con 2 columnas: N° a la izquierda, fecha a la derecha
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell($anchoUtil * 0.5, 6, 'Lista N° ' . $numero, 0, 0, 'L');

    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell($anchoUtil * 0.5, 6, 'Vigente desde el: ' . $fechaFmt, 0, 1, 'R');

    $pdf->SetLineWidth(0.2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(6);

    // ---------- GRUPOS ----------
    if (empty($grupos)) {
        $pdf->SetFont('dejavusans', 'I', 10);
        $pdf->Cell(0, 8, 'No hay artículos visibles para la categoría "' . $catDescripcion . '".', 0, 1, 'C');
    }

    foreach ($grupos as $volumen => $g) {
        if (empty($g['items'])) continue;

        // Título del grupo
        $tituloGrupo = $g['nombre'];
        if ($volumen > 0) {
            $tituloGrupo .= ' ' . $volumen . ' cc';
        }

        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, $tituloGrupo, 'B', 1, 'L');
        $pdf->Ln(1);

        // Cabecera de la tabla
        $colCaja = $g['unidades_por_caja'] > 0
            ? 'Cajas x ' . $g['unidades_por_caja'] . ' unidades'
            : '';

        $html  = '<table cellpadding="3" cellspacing="0" style="width:100%; font-size:8.5pt;">';
        $html .= '<thead><tr style="font-weight:bold; border-bottom:0.5pt solid #000;">';
        $html .= '<th width="10%" align="left"></th>';
        $html .= '<th width="48%" align="left"></th>';
        $html .= '<th width="22%" align="right">' . htmlspecialchars($colCaja) . '</th>';
        $html .= '<th width="20%" align="right">Unidad</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($g['items'] as $it) {
            $precioCaja = $it['precio_caja'] !== null
                ? '$ ' . number_format($it['precio_caja'], 0, ',', '.')
                : '—';
            $html .= '<tr>';
            $html .= '<td align="left">' . htmlspecialchars($it['codigo_corto']) . '</td>';
            $html .= '<td align="left">' . htmlspecialchars($it['nombre']) . '</td>';
            $html .= '<td align="right">' . $precioCaja . '</td>';
            $html .= '<td align="right">$ ' . number_format($it['precio_unidad'], 0, ',', '.') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        if ($g['unidades_por_caja'] === 0) {
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetTextColor(178, 58, 72);
            $pdf->Cell(0, 5, "No hay unidades por caja configuradas para {$volumen} cc.", 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(4);
    }

    if (!empty($grupos)) {
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetTextColor(20, 17, 14);        // negro LiquiCont
        $pdf->Cell(0, 7, 'LOS PRECIOS NO INCLUYEN IVA - FLETE A CARGO DEL CLIENTE', 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(4);
    }

    // ---------- PIE ----------
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(80, 80, 80);

    $piePartes = array_filter([$empresa['razon_social'], $empresa['domicilio']]);
    foreach ($piePartes as $linea) {
        $pdf->Cell(0, 4.5, $linea, 0, 1, 'C');
    }

    $ultimaLinea = array_filter([
        $empresa['telefono'] ? 'Tel: ' . $empresa['telefono'] : '',
        strtoupper($empresa['sitio_web']),
        $empresa['cuit'] ? 'CUIT ' . $empresa['cuit'] : '',
    ]);
    if ($ultimaLinea) {
        $pdf->Cell(0, 4.5, implode(' – ', $ultimaLinea), 0, 1, 'C');
    }

    return $pdf->Output('', 'S');
}

/* ============================================================
   HELPERS
   ============================================================ */

function _lp_fecha_ddmmaaaa(?string $fechaYmd): string
{
    if (!$fechaYmd) return '';
    $t = strtotime($fechaYmd);
    return $t ? date('d-m-Y', $t) : '';
}

/**
 * true si el server puede convertir el PDF a imagen (Imagick + Ghostscript).
 */
function puedeGenerarImagenListaPrecios(): bool
{
    if (!class_exists('Imagick')) return false;
    try {
        $formatos = (new Imagick())->queryFormats('PDF');
        return in_array('PDF', $formatos, true);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Convierte la primera página de un PDF (en memoria) a PNG (en memoria).
 * Devuelve null si no se pudo.
 */
function convertirPdfAImagen(string $pdfBytes): ?string
{
    if (!puedeGenerarImagenListaPrecios()) return null;
    try {
        $img = new Imagick();
        $img->setResolution(150, 150);
        $img->readImageBlob($pdfBytes);
        $img->setIteratorIndex(0);
        $img->setImageFormat('png');
        $img->setImageBackgroundColor('white');
        $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $img->setImageFormat('png');
        return $img->getImageBlob();
    } catch (\Throwable $e) {
        return null;
    }
}