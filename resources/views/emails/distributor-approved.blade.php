<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud aprobada</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family: Arial, Helvetica, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#002366; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">Tu solicitud fue aprobada</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 16px 0; font-size:14px;">Hola {{ $distributorName }},</p>
                            <p style="margin:0 0 20px 0; font-size:14px; line-height:1.5;">
                                Tu solicitud para ser distribuidor fue aprobada. Puedes iniciar sesión con tu correo electrónico y tu CURP como contraseña.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border-radius:12px; padding:4px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#64748b;">Correo electrónico</td>
                                    <td style="padding:12px 16px; font-size:13px; font-weight:bold; text-align:right;">{{ $loginEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#64748b;">Contraseña (CURP)</td>
                                    <td style="padding:12px 16px; font-size:13px; font-weight:bold; text-align:right;">{{ $temporaryPassword }}</td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0 0; font-size:14px; line-height:1.5;">
                                Una vez que inicies sesión puedes cambiar esta contraseña por una más segura o dejar tu CURP.
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
