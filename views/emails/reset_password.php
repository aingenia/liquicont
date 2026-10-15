<?php
/**
 * views/emails/reset_password.php
 * Variables esperadas: $nombre, $link
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña</title>
</head>
<body style="margin:0; padding:0; background:#F6F1E7; font-family:Arial,Helvetica,sans-serif; color:#211B14;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F6F1E7; padding:30px 15px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background:#FFFFFF; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="background:#14110E; padding:36px 30px; text-align:center;">
                        <div style="font-size:22px; font-weight:800; letter-spacing:3px; color:#E4C077;">KROWN SPIRITS</div>
                        <div style="font-size:11px; letter-spacing:2px; color:#7A7266; text-transform:uppercase; margin-top:6px;">Sistema de gestión</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:36px 40px 20px;">
                        <h1 style="font-size:22px; margin:0 0 16px; color:#14110E;">Hola, <?= htmlspecialchars($nombre) ?></h1>

                        <p style="font-size:15px; line-height:1.6; color:#211B14; margin:0 0 16px;">
                            Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong><?= APP_NAME ?></strong>.
                        </p>

                        <p style="font-size:15px; line-height:1.6; color:#211B14; margin:0 0 28px;">
                            Hacé clic en el siguiente botón para elegir una nueva contraseña. El enlace vence en <strong>1 hora</strong>.
                        </p>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto 28px;">
                            <tr>
                                <td style="background:#14110E; border-radius:10px;">
                                    <a href="<?= htmlspecialchars($link) ?>"
                                       style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:700; color:#E4C077; text-decoration:none;">
                                        Restablecer mi contraseña
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:13px; color:#7A7266; margin:0 0 8px;">Si el botón no funciona, copiá y pegá este enlace:</p>
                        <p style="font-size:12px; color:#0F5C52; word-break:break-all; margin:0 0 28px; font-family:Courier,monospace;">
                            <?= htmlspecialchars($link) ?>
                        </p>

                        <hr style="border:none; border-top:1px solid #E7DFCF; margin:0 0 20px;">

                        <p style="font-size:13px; color:#7A7266; margin:0;">
                            Si no solicitaste este cambio, podés ignorar este email. Tu contraseña seguirá siendo la misma.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#F6F1E7; padding:24px 40px; text-align:center; border-top:1px solid #E7DFCF;">
                        <p style="font-size:12px; color:#7A7266; margin:0;"><?= APP_NAME ?> — Sistema de gestión</p>
                        <p style="font-size:11px; color:#7A7266; margin:0;">Este es un email automático, no respondas a esta dirección.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>