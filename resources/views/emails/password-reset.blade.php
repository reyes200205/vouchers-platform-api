<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recupera tu contraseña</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family: Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#002366; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">Recupera tu contraseña</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 16px 0; font-size:14px;">Hola {{ $recipientName }},</p>
                            <p style="margin:0 0 20px 0; font-size:14px; line-height:1.5;">
                                Recibimos una solicitud para restablecer tu contraseña en Mis Vales. Da clic en el siguiente botón para elegir una nueva:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:8px 0 20px 0; text-align:center;">
                                        <a href="{{ $resetUrl }}" style="display:inline-block; background-color:#002366; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; padding:14px 28px; border-radius:10px;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px 0; font-size:13px; color:#64748b; line-height:1.5;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $resetUrl }}" style="color:#002366; word-break:break-all;">{{ $resetUrl }}</a>
                            </p>

                            <p style="margin:20px 0 0 0; font-size:13px; color:#64748b; line-height:1.5;">
                                Este enlace expira en {{ $expiresInMinutes }} minutos. Si tú no solicitaste este cambio, ignora este correo: tu contraseña actual seguirá funcionando.
                            </p>

                            <p style="margin:20px 0 0 0; font-size:12px; color:#94a3b8; line-height:1.5;">
                                Este correo se generó automáticamente, no es necesario responderlo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
