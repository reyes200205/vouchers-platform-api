<?php

declare(strict_types=1);

return [
    // Numero de digitos del codigo enviado por correo.
    'code_length' => 6,

    // Minutos que el codigo es valido desde que se genera.
    'expires_in_minutes' => 5,

    // Intentos incorrectos permitidos antes de invalidar el codigo activo.
    'max_attempts' => 5,

    // Minutos que vive el "challenge" (puente entre login y verificacion de
    // OTP) en cache. Es independiente de expires_in_minutes: el usuario
    // puede reenviar el codigo (mfa/resend) varias veces dentro de esta
    // ventana sin tener que volver a capturar usuario/contrasena.
    'challenge_ttl_minutes' => 10,
];
