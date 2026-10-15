<?php
/**
 * includes/factura_helpers.php
 *
 * Helpers compartidos por los generadores de PDF de facturas (Fasón y
 * Facturación de Pedidos): fecha, moneda y armado del texto del QR
 * fiscal. El formato de una factura con CAE es el mismo sin importar
 * de dónde salió, así que esta lógica vive en un solo lugar.
 */

/**
 * Formatea una fecha Y-m-d a d/m/Y.
 */
function _fh_fecha(?string $fechaYmd): string
{
    if (!$fechaYmd) {
        return '';
    }
    $t = strtotime($fechaYmd);
    return $t ? date('d/m/Y', $t) : '';
}

/**
 * Formatea un número como moneda argentina: $ 1.234,56
 */
function _fh_moneda(float $valor): string
{
    return '$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Genera el texto que va codificado en el QR fiscal, según la
 * especificación oficial de ARCA (ex-AFIP): una URL con un JSON en
 * Base64.
 *
 *   https://www.arca.gob.ar/fe/qr/?p=<json en base64>
 *
 * Verificado con facturas reales emitidas con este mismo código:
 * el JSON decodificado del QR contiene exactamente los campos que
 * ARCA espera (cuit, ptoVta, tipoCmp, nroCmp, importe, codAut, etc).
 */
function _fh_texto_qr(
    string $cuitEmisor,
    int    $puntoVenta,
    string $letra,
    int    $numeroCbte,
    float  $importeTotal,
    string $fechaEmision,
    string $cuitReceptor,
    string $cae
): string {
    $tipoCmp = ['A' => 1, 'B' => 6, 'C' => 11][$letra] ?? 11;

    $datos = [
        'ver'        => 1,
        'fecha'      => $fechaEmision,
        'cuit'       => (int) preg_replace('/\D/', '', $cuitEmisor),
        'ptoVta'     => $puntoVenta,
        'tipoCmp'    => $tipoCmp,
        'nroCmp'     => $numeroCbte,
        'importe'    => round($importeTotal, 2),
        'moneda'     => 'PES',
        'ctz'        => 1,
        'tipoDocRec' => 80,
        'nroDocRec'  => (int) preg_replace('/\D/', '', $cuitReceptor),
        'tipoCodAut' => 'E',
        'codAut'     => (int) $cae,
    ];

    return 'https://www.arca.gob.ar/fe/qr/?p=' . base64_encode(json_encode($datos));
}