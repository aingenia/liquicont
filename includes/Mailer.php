<?php
/**
 * includes/Mailer.php
 * Wrapper de PHPMailer configurado para el SMTP de Ferozo.
 *
 * Uso:
 *     Mailer::enviar($email, $asunto, $html);
 *     Mailer::enviar($email, $asunto, $html, $textoPlano, $nombreDestinatario);
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Envía un email. Devuelve true/false y loguea el error si falla.
     */
    public static function enviar(
        string $destinatario,
        string $asunto,
        string $cuerpoHtml,
        ?string $cuerpoTexto = null,
        ?string $nombreDestinatario = null
    ): bool {
        self::cargarLibreria();

        $mail = new PHPMailer(true);

        try {
            // --- SMTP ---
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->Port       = MAIL_PORT;

            // 465 = SMTPS (SSL implícito) · 587 = STARTTLS
            $mail->SMTPSecure = MAIL_PORT === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            // En dev, aceptar certificados autofirmados (Ferozo a veces los usa)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            if (MAIL_DEBUG > 0) {
                $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("[Mailer] $str");
                };
            }

            // --- Remitente y destinatario ---
            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
            $mail->addAddress($destinatario, $nombreDestinatario ?? $destinatario);

            // --- Contenido ---
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHtml;
            $mail->AltBody = $cuerpoTexto ?? self::htmlATexto($cuerpoHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('[Mailer] Error al enviar a ' . $destinatario . ': ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Carga los archivos de PHPMailer. Funciona tanto si están en
     * PHPMailer/src/ como si están directos en PHPMailer/.
     */
    private static function cargarLibreria(): void
    {
        $bases = [
            MAIL_LIB_PATH . 'src' . DS,
            MAIL_LIB_PATH,
        ];

        $base = null;
        foreach ($bases as $b) {
            if (file_exists($b . 'PHPMailer.php')) {
                $base = $b;
                break;
            }
        }

        if ($base === null) {
            throw new \RuntimeException(
                'PHPMailer no encontrado. Verificá MAIL_LIB_PATH en config/conexion.php.'
            );
        }

        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
    }

    /**
     * Convierte HTML a texto plano, para el "AltBody".
     */
    private static function htmlATexto(string $html): string
    {
        $txt = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', $html);
        $txt = preg_replace('#</(p|div|h[1-6]|tr|li|br)#i', "\n", $txt);
        $txt = strip_tags($txt);
        $txt = preg_replace('/[ \t]+/', ' ', $txt);
        $txt = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $txt);
        return trim($txt);
    }

        /**
     * Envía un email con un adjunto en memoria (sin escribirlo a disco).
     *
     * @param string $destinatario
     * @param string $asunto
     * @param string $cuerpoHtml
     * @param array{nombre:string,contenido:string,mime:string} $adjunto
     * @param string|null $nombreDestinatario
     * @param string|null $cuerpoTexto
     * @return bool
     */
    public static function enviarConAdjunto(
        string $destinatario,
        string $asunto,
        string $cuerpoHtml,
        array $adjunto,
        ?string $nombreDestinatario = null,
        ?string $cuerpoTexto = null
    ): bool {
        self::cargarLibreria();

        $mail = new PHPMailer(true);

        try {
            // --- SMTP (mismo bloque que enviar()) ---
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->Port       = MAIL_PORT;

            $mail->SMTPSecure = MAIL_PORT === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            if (MAIL_DEBUG > 0) {
                $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("[Mailer] $str");
                };
            }

            // --- Remitente y destinatario ---
            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
            $mail->addAddress($destinatario, $nombreDestinatario ?? $destinatario);

            // --- Contenido ---
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHtml;
            $mail->AltBody = $cuerpoTexto ?? self::htmlATexto($cuerpoHtml);

            // --- Adjunto en memoria ---
            $mail->addStringAttachment(
                $adjunto['contenido'],
                $adjunto['nombre'],
                'base64',
                $adjunto['mime']
            );

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('[Mailer::enviarConAdjunto] Error a ' . $destinatario . ': ' . $mail->ErrorInfo);
            return false;
        }
    }
}