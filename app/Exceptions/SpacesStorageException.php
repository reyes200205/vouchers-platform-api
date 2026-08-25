<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Fallo real (no degradacion silenciosa) al guardar un archivo en
 * DigitalOcean Spaces -- ver SpacesStorageService::storeAndSign() y
 * assertConfigured(). Tipo dedicado (en vez de un RuntimeException generico)
 * para que bootstrap/app.php lo pueda distinguir de cualquier otro
 * RuntimeException de la app y darle su propio codigo interno (105040).
 */
final class SpacesStorageException extends RuntimeException {}
