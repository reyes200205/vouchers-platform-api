<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | El load balancer y nginx reenvian la peticion con X-Forwarded-For/
    | X-Real-IP; sin decirle a Laravel que confie en ese salto, Request::ip()
    | regresa la IP del proxy, no la del cliente real. Se aplica en
    | AppServiceProvider::boot() (no en bootstrap/app.php: 'config' todavia no
    | esta disponible en el contenedor en ese punto). Lista separada por comas
    | de IPs/CIDRs (ej. "10.120.0.4,192.168.10.4"). Vacio = se confia en
    | cualquier proxy inmediato ('*'), valido solo si esta API nunca es
    | alcanzable directamente desde fuera de la red interna.
    |
    */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('TRUSTED_PROXIES', '')
    )))),

    /*
    |--------------------------------------------------------------------------
    | Rangos CIDR de la VPN interna
    |--------------------------------------------------------------------------
    |
    | Gerente general y gerente de sucursal solo pueden usar los endpoints de
    | aprobacion (Bandeja de Aprobaciones) si la IP del cliente cae en uno de
    | estos rangos. Ver App\Http\Middleware\EnsureVpnAccessForRoles. Lista
    | separada por comas (ej. "192.168.10.0/24"). Vacio = se niega el acceso
    | a esos roles por seguridad (fail closed), no se asume que todo mundo
    | esta en la VPN.
    |
    */
    'vpn_cidrs' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('VPN_TRUSTED_CIDRS', '')
    )))),

];
