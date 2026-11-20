<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

define('CLAVE_REGISTRO_DOCENTE', getenv('ITDI_CLAVE_DOCENTE') ?: 'palermo2026');

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tokenFormulario(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validarTokenFormulario(): void
{
    $token = $_POST['csrf'] ?? '';
    $tokenEsperado = $_SESSION['csrf'] ?? '';

    if (!is_string($token) || $token === '' || !is_string($tokenEsperado) || $tokenEsperado === '' || !hash_equals($tokenEsperado, $token)) {
        http_response_code(419);
        exit('El formulario venció. Regresa e inténtalo de nuevo.');
    }
}

function mensaje(string $tipo, string $texto): void
{
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtenerMensaje(): ?array
{
    $mensaje = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return is_array($mensaje) ? $mensaje : null;
}

function urlInicio(string $pagina): string
{
    $directorio = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    if (basename($directorio) === 'procesos') {
        $directorio = dirname($directorio);
    }
    $directorio = rtrim($directorio, '/');
    return ($directorio === '' ? '' : $directorio) . '/index.php?pagina=' . rawurlencode($pagina);
}

function exigirSesion(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . urlInicio('ingresar'));
        exit;
    }
}

function exigirDocente(): void
{
    exigirSesion();
    if (($_SESSION['rol'] ?? '') !== 'docente') {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección.');
    }
}
