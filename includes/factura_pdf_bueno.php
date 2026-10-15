<?php
/**
 * includes/factura_pdf.php (factura_pdf_bueno.php)
 * Genera los PDFs de factura y remitos usando TCPDF.
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Factura.php';
require_once __DIR__ . '/factura_helpers.php';

define('K_TCPDF_EXTERNAL_CONFIG', false);
require_once __DIR__ . '/tcpdf/tcpdf.php';

/* ============================================================
   CONSTANTES
   ============================================================ */
if (!defined('FACTURA_LOGO_DEFAULT')) {
    define('FACTURA_LOGO_DEFAULT', 'dist/img/logo-krown-black.png');
}

/* ============================================================
   FACTURA LEGAL (con CAE + QR)
   ============================================================ */
function generarPdfFactura(int $facturaId): string
{
    $facturaModel = new Factura();
    $factura = $facturaModel->obtenerPorId($facturaId);
    if (!$factura) {
        throw new \RuntimeException('La factura no existe.');
    }
    if ($factura['arca_estado'] !== 'APROBADO' || !$factura['cae']) {
        throw new \RuntimeException('Esta factura todavía no tiene un CAE aprobado por ARCA. Enviála primero.');
    }

    $detalle = $facturaModel->obtenerDetalle($facturaId);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Krown');
    $pdf->SetAuthor($factura['emisor_razon_social']);
    $pdf->SetTitle('Factura ' . $factura['arca_tipo_comprobante_letra']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 14, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    _fac_pdf_encabezado_factura($pdf, $factura);
    _fac_pdf_datos_cliente_factura($pdf, $factura);
    _fac_pdf_tabla_detalle($pdf, $detalle, $factura);
    _fac_pdf_totales($pdf, $factura);
    _fac_pdf_cae_qr($pdf, $factura);

    return $pdf->Output('', 'S');
}

/* ============================================================
   REMITO — Estilo app anterior (pedido_factura_pdf)
   ============================================================ */
function generarPdfRemito(int $facturaId, bool $conPrecio): string
{
    $facturaModel = new Factura();
    $factura = $facturaModel->obtenerPorId($facturaId);
    if (!$factura) {
        throw new \RuntimeException('La factura no existe.');
    }

    $detalle = $facturaModel->obtenerDetalle($facturaId);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Krown');
    $pdf->SetAuthor($factura['emisor_razon_social']);
    $pdf->SetTitle('Remito ' . str_pad((string)$factura['remito_punto_venta'], 4, '0', STR_PAD_LEFT)
                    . '-' . str_pad((string)$factura['remito_numero'], 8, '0', STR_PAD_LEFT));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 14, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    _fac_pdf_remito_completo($pdf, $factura, $detalle, $conPrecio);

    return $pdf->Output('', 'S');
}

/* ============================================================
   HELPERS
   ============================================================ */

function _fac_pdf_resolver_logo(array $factura): ?string
{
    if (!empty($factura['emisor_logo'])) {
        $p = ROOT_PATH . str_replace('/', DS, $factura['emisor_logo']);
        if (is_file($p)) return $p;
    }
    $p = ROOT_PATH . str_replace('/', DS, FACTURA_LOGO_DEFAULT);
    return is_file($p) ? $p : null;
}

/* ---------- REMITO: Réplica de la maquetación original ---------- */
function _fac_pdf_remito_completo(TCPDF $pdf, array $factura, array $detalle, bool $conPrecio): void
{
    $yInicio = $pdf->GetY();

    // 1. Nombre de la empresa / Marca arriba
    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->SetXY(15, $yInicio);
    $pdf->Cell(95, 6, $factura['emisor_razon_social'], 0, 1, 'L');

    // 2. Datos del emisor
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetX(15);
    $pdf->MultiCell(95, 4.5, $factura['emisor_domicilio'], 0, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, 'Tel: ' . ($factura['emisor_telefono'] ?? ''), 0, 1, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, ($factura['emisor_email'] ?? ''), 0, 1, 'L');

    // Letra central del comprobante (X)
    $letra = $factura['remito_prefijo'] ?: 'X';
    $pdf->Rect(115, $yInicio, 15, 15);
    $pdf->SetFont('dejavusans', 'B', 22);
    $pdf->SetXY(115, $yInicio + 1);
    $pdf->Cell(15, 13, $letra, 0, 0, 'C');

    // Título y datos del Remito a la derecha
    $nroRemito = str_pad((string)$factura['remito_punto_venta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$factura['remito_numero'], 8, '0', STR_PAD_LEFT);

    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->SetXY(133, $yInicio);
    $pdf->Cell(62, 6, 'REMITO', 0, 1, 'R');

    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'Nro. Remito: ' . $nroRemito, 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'Fecha de Emisión: ' . _fh_fecha($factura['fecha']), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'CUIT: ' . $factura['emisor_cuit'], 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'Ingresos Brutos: ' . ($factura['emisor_ingresos_brutos'] ?? '—'), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'Inicio de actividades: ' . ($factura['emisor_fecha_inicio'] ?? '—'), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 4.5, 'Condición IVA: ' . $factura['emisor_condicion_iva'], 0, 1, 'R');

    // Separador horizontal
    $pdf->SetY(max($pdf->GetY(), $yInicio + 34));
    $pdf->Ln(2);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);

    // 3. Datos del Transportista y Cliente
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->Cell(0, 4.5, 'Transportista: Propio', 0, 1, 'L');
    $pdf->SetFont('dejavusans', 'B', 8.5);
    $pdf->Cell(0, 4.5, 'Apellido y Nombre / Razón Social: ' . $factura['cliente_razon_social'], 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->Cell(0, 4.5, 'Teléfono: ' . ($factura['cliente_telefono'] ?? '—'), 0, 1, 'L');
    $pdf->Cell(0, 4.5, 'Persona Contacto: ', 0, 1, 'L');
    $pdf->Cell(0, 4.5, 'Condición IVA: ' . ($factura['cliente_condicion_iva'] ?? '—'), 0, 1, 'L');
    $pdf->Cell(0, 4.5, 'CUIT: ' . ($factura['cliente_cuit'] ?? '—'), 0, 1, 'L');
    $pdf->Cell(0, 4.5, 'Domicilio De Entrega: ' . ($factura['cliente_domicilio'] ?? '—'), 0, 1, 'L');

    $pdf->Ln(2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);

    // 4. Tabla de Productos
    $html = '<table cellpadding="3" cellspacing="0" style="width:100%; font-size:8.5pt;">';
    $html .= '<thead><tr style="font-weight:bold; border-bottom:0.5pt solid #000;">';
    $html .= '<th width="15%" align="left">Código</th>';
    $html .= '<th width="' . ($conPrecio ? '45' : '65') . '%" align="left">Productos</th>';
    if ($conPrecio) {
        $html .= '<th width="20%" align="right">Precio unitario</th>';
    }
    $html .= '<th width="20%" align="right">Cantidad</th>';
    $html .= '</tr></thead><tbody>';

    $totalBultos = 0;
    foreach ($detalle as $d) {
        $cant = (float)$d['cantidad_unidades'];
        $totalBultos += $cant;
        $cantFmt = rtrim(rtrim(number_format($cant, 3, ',', '.'), '0'), ',');
        if ($cantFmt === '') $cantFmt = '0';

        $html .= '<tr>'
            . '<td align="left">' . htmlspecialchars($d['articulo_codigo'] ?? '') . '</td>'
            . '<td align="left">' . htmlspecialchars($d['articulo_nombre']) . '</td>';
        if ($conPrecio) {
            $html .= '<td align="right">' . _fh_moneda((float)$d['precio_unitario']) . '</td>';
        }
        $html .= '<td align="right">' . $cantFmt . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);

    // 5. Total de Bultos
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, 'Total Bultos: ' . rtrim(rtrim(number_format($totalBultos, 3, ',', '.'), '0'), ','), 0, 1, 'R');
    $pdf->Ln(3);

    // 6. Observaciones
    $pdf->SetFont('dejavusans', '', 8.5);
    $obsText = !empty($factura['notas']) ? htmlspecialchars($factura['notas']) : '';
    $pdf->MultiCell(0, 12, 'Observaciones: ' . $obsText, 1, 'L', false, 1);
    $pdf->Ln(3);

    // 7. Pie de Recibido / Firma
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, 'RECIBIDO', 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 8.5);

    $yFirma = $pdf->GetY();
    $pdf->Cell(90, 5, 'Firma:', 0, 0, 'L');
    $pdf->Cell(90, 5, 'Aclaración:', 0, 1, 'L');
    $pdf->Cell(90, 5, 'DNI:', 0, 0, 'L');
    $pdf->Cell(90, 5, 'Fecha:', 0, 1, 'L');
}

/* ---------- FACTURA: helpers ---------- */
function _fac_pdf_encabezado_factura(TCPDF $pdf, array $factura): void
{
    $yInicio = $pdf->GetY();

    $logoPath = _fac_pdf_resolver_logo($factura);
    if ($logoPath) {
        $ancho = 42;
        $dim = @getimagesize($logoPath);
        $alto = $dim ? $ancho * ($dim[1] / $dim[0]) : $ancho * 0.35;
        $pdf->Image($logoPath, 15, $yInicio, $ancho, $alto, 'PNG', '', '', false, 300);
    }
    $pdf->SetY($yInicio + 22);

    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetX(15);
    $pdf->Cell(95, 6, $factura['emisor_razon_social'], 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetX(15);
    $pdf->MultiCell(95, 4.5, $factura['emisor_domicilio'], 0, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, 'CUIT: ' . $factura['emisor_cuit'], 0, 1, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, 'Ingresos Brutos: ' . ($factura['emisor_ingresos_brutos'] ?? '—'), 0, 1, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, 'Inicio de actividades: ' . ($factura['emisor_fecha_inicio'] ?? '—'), 0, 1, 'L');
    $pdf->SetX(15);
    $pdf->Cell(95, 4.5, 'Condición frente al IVA: ' . $factura['emisor_condicion_iva'], 0, 1, 'L');

    $letra = $factura['arca_tipo_comprobante_letra'];
    $pdf->Rect(115, $yInicio + 22, 15, 15);
    $pdf->SetFont('dejavusans', 'B', 22);
    $pdf->SetXY(115, $yInicio + 23);
    $pdf->Cell(15, 13, $letra, 0, 0, 'C');

    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->SetXY(133, $yInicio + 22);
    $pdf->Cell(62, 7, 'FACTURA', 0, 1, 'R');

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetX(133);
    $pdf->Cell(62, 5, 'Punto de Venta: ' . str_pad((string)$factura['arca_punto_venta'], 5, '0', STR_PAD_LEFT), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 5, 'Comp. Nro: ' . str_pad((string)$factura['arca_numero_comprobante'], 8, '0', STR_PAD_LEFT), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 5, 'Fecha de Emisión: ' . _fh_fecha($factura['fecha']), 0, 1, 'R');
    $pdf->SetX(133);
    $pdf->Cell(62, 5, 'CUIT: ' . $factura['emisor_cuit'], 0, 1, 'R');

    $pdf->SetY(max($pdf->GetY(), $yInicio + 55));
    $pdf->Ln(3);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
}

function _fac_pdf_datos_cliente_factura(TCPDF $pdf, array $factura): void
{
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, 'CUIT: ' . ($factura['cliente_cuit'] ?? '—'), 0, 1, 'L');
    $pdf->Cell(0, 5, 'Razón Social: ' . $factura['cliente_razon_social'], 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 5, 'Condición frente al IVA: ' . ($factura['cliente_condicion_iva'] ?? '—'), 0, 1, 'L');
    $pdf->Cell(0, 5, 'Domicilio: ' . ($factura['cliente_domicilio'] ?? '—'), 0, 1, 'L');

    if (!empty($factura['pedido_id'])) {
        $pdf->Cell(0, 5, 'Pedido origen: #' . (int)$factura['pedido_id'], 0, 1, 'L');
    }

    $pdf->Ln(2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
}

function _fac_pdf_tabla_detalle(TCPDF $pdf, array $detalle, array $factura): void
{
    $html  = '<table cellpadding="4" cellspacing="0" style="width:100%; font-size:8.5pt;">';
    $html .= '<thead><tr style="font-weight:bold; border-bottom:0.5pt solid #000; background-color:#f0f0f0;">';
    $html .= '<th width="12%" align="left">Código</th>';
    $html .= '<th width="38%" align="left">Descripción</th>';
    $html .= '<th width="10%" align="right">Cant.</th>';
    $html .= '<th width="12%" align="right">P. Unit.</th>';
    $html .= '<th width="8%" align="right">Desc.</th>';
    $html .= '<th width="20%" align="right">Subtotal</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($detalle as $d) {
        $sinCargo = (int)$d['sin_precio'] === 1;
        $sub = $sinCargo ? 0 : (float)$d['subtotal_linea'];

        $html .= '<tr>';
        $html .= '<td align="left">' . htmlspecialchars($d['articulo_codigo'] ?? '') . '</td>';
        $html .= '<td align="left">' . htmlspecialchars($d['articulo_nombre']);
        if ($sinCargo) {
            $html .= ' <strong style="color:#0F5C52;">(SIN CARGO)</strong>';
        }
        $html .= '</td>';
        $html .= '<td align="right">' . rtrim(rtrim(number_format((float)$d['cantidad_unidades'], 2, ',', '.'), '0'), ',') . '</td>';

        if ($sinCargo) {
            $html .= '<td align="right" colspan="2"><em style="color:#0F5C52;">Sin cargo</em></td>';
            $html .= '<td align="right"><em>—</em></td>';
        } else {
            $html .= '<td align="right">' . _fh_moneda((float)$d['precio_unitario']) . '</td>';
            $html .= '<td align="right">' . number_format((float)$d['descuento_porcentaje'], 2, ',', '.') . '%</td>';
            $html .= '<td align="right">' . _fh_moneda($sub) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Ln(2);
}

function _fac_pdf_totales(TCPDF $pdf, array $factura): void
{
    $pdf->SetLineWidth(0.3);
    $pdf->Line(130, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('dejavusans', '', 9);

    $pdf->Cell(115, 5, '', 0, 0);
    $pdf->Cell(30, 5, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(50, 5, _fh_moneda((float)$factura['subtotal']), 0, 1, 'R');

    if ((float)$factura['descuento_general_porcentaje'] > 0) {
        $pdf->Cell(115, 5, '', 0, 0);
        $pdf->Cell(30, 5, 'Desc. ' . number_format((float)$factura['descuento_general_porcentaje'], 2, ',', '.') . '%:', 0, 0, 'R');
        $desc = (float)$factura['subtotal'] * (float)$factura['descuento_general_porcentaje'] / 100;
        $pdf->Cell(50, 5, '- ' . _fh_moneda($desc), 0, 1, 'R');
    }

    if ((float)$factura['iva'] > 0) {
        $pdf->Cell(115, 5, '', 0, 0);
        $pdf->Cell(30, 5, 'IVA:', 0, 0, 'R');
        $pdf->Cell(50, 5, _fh_moneda((float)$factura['iva']), 0, 1, 'R');
    }

    if ((float)$factura['impuestos_internos'] > 0) {
        $pdf->Cell(115, 5, '', 0, 0);
        $pdf->Cell(30, 5, 'Imp. Internos:', 0, 0, 'R');
        $pdf->Cell(50, 5, _fh_moneda((float)$factura['impuestos_internos']), 0, 1, 'R');
    }

    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(115, 8, '', 0, 0);
    $pdf->Cell(30, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(50, 8, _fh_moneda((float)$factura['total']), 0, 1, 'R');

    $pdf->Ln(6);
}

function _fac_pdf_cae_qr(TCPDF $pdf, array $factura): void
{
    $anchoQr = 30;
    $yQr = $pdf->GetY();

    $qrTexto = _fh_texto_qr(
        $factura['emisor_cuit'],
        (int)$factura['arca_punto_venta'],
        $factura['arca_tipo_comprobante_letra'],
        (int)$factura['arca_numero_comprobante'],
        (float)$factura['total'],
        $factura['fecha'],
        $factura['cliente_cuit'] ?? '',
        $factura['cae']
    );

    $pdf->write2DBarcode($qrTexto, 'QRCODE,M', 15, $yQr, $anchoQr, $anchoQr, [], 'N');

    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetXY(50, $yQr);
    $pdf->Cell(0, 6, 'CAE N°: ' . $factura['cae'], 0, 1, 'L');
    $pdf->SetX(50);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 6, 'Fecha de Vto. de CAE: ' . _fh_fecha($factura['cae_vencimiento']), 0, 1, 'L');
}