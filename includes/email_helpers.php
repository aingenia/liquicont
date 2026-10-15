<?php
/**
 * includes/email_helpers.php
 * Helpers para envío de emails: sanitización de HTML y
 * reemplazo de placeholders en plantillas.
 */

/**
 * Sanitiza el HTML del cuerpo de un email a una lista blanca de
 * etiquetas de formato de texto. Se aplica antes de enviar por SMTP
 * para evitar HTML malicioso, aunque quien lo carga sea un admin.
 */
function sanitizarHtmlEmail(string $html): string
{
    $permitidas = '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><span><div><blockquote>';
    $limpio = strip_tags($html, $permitidas);

    // Sacar atributos de evento (onclick, onerror, etc.)
    $limpio = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $limpio);

    // Sacar href con esquema "javascript:"
    $limpio = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $limpio);

    return $limpio;
}

/**
 * Reemplaza los placeholders {{lista_numero}}, {{vigente_desde}} y
 * {{cliente_nombre}} en el asunto/cuerpo del email.
 *
 * @param string $texto  Texto con placeholders
 * @param array  $valores ['lista_numero' => ..., 'vigente_desde' => ..., 'cliente_nombre' => ...]
 */
function reemplazarPlaceholdersEmail(string $texto, array $valores): string
{
    return str_replace(
        ['{{lista_numero}}', '{{vigente_desde}}', '{{cliente_nombre}}'],
        [
            $valores['lista_numero']  ?? '',
            $valores['vigente_desde'] ?? '',
            $valores['cliente_nombre'] ?? '',
        ],
        $texto
    );
}