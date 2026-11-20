<?php
declare(strict_types=1);

class ErrorValidacion extends InvalidArgumentException
{
    public function __construct(private array $errores)
    {
        parent::__construct((string) reset($errores));
    }

    public function errores(): array
    {
        return $this->errores;
    }
}

function validarRegistro(array $entrada): array
{
    $datos = [
        'nombre' => trim((string) ($entrada['nombre'] ?? '')),
        'apellido' => trim((string) ($entrada['apellido'] ?? '')),
        'tipo_documento' => trim((string) ($entrada['tipo_documento'] ?? '')),
        'numero_documento' => trim((string) ($entrada['numero_documento'] ?? '')),
        'genero' => trim((string) ($entrada['genero'] ?? '')),
        'correo' => strtolower(trim((string) ($entrada['correo'] ?? ''))),
        'rol' => (string) ($entrada['rol'] ?? ''),
        'contrasena' => (string) ($entrada['contrasena'] ?? ''),
    ];

    $errores = [];
    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 80) {
        $errores['nombre'] = 'Escribe un nombre válido (máximo 80 caracteres).';
    }
    if ($datos['apellido'] === '' || mb_strlen($datos['apellido']) > 100) {
        $errores['apellido'] = 'Escribe un apellido válido (máximo 100 caracteres).';
    }
    if (!in_array($datos['tipo_documento'], ['Cédula de ciudadanía', 'Tarjeta de identidad', 'Pasaporte', 'Otro'], true)) {
        $errores['tipo_documento'] = 'Selecciona un tipo de documento válido.';
    }
    try {
        validarNumeroDocumento($datos['numero_documento']);
    } catch (InvalidArgumentException $error) {
        $errores['numero_documento'] = $error->getMessage();
    }
    if (!in_array($datos['genero'], ['Femenino', 'Masculino'], true)) {
        $errores['genero'] = 'Selecciona Femenino o Masculino.';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Escribe un correo electrónico válido.';
    }
    if (!in_array($datos['rol'], ['estudiante', 'docente'], true)) {
        $errores['rol'] = 'Selecciona un tipo de usuario válido.';
    }
    if (mb_strlen($datos['contrasena']) < 8) {
        $errores['contrasena'] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($datos['rol'] === 'docente') {
        $clave = (string) ($entrada['clave_docente'] ?? '');
        if (CLAVE_REGISTRO_DOCENTE === '' || !hash_equals(CLAVE_REGISTRO_DOCENTE, $clave)) {
            $errores['clave_docente'] = 'La clave de registro docente no es válida o aún no está configurada.';
        }
    }
    if ($errores) {
        throw new ErrorValidacion($errores);
    }

    return $datos;
}

function validarNumeroDocumento(string $numero): void
{
    if (!preg_match('/\A[0-9]{1,10}\z/', $numero)) {
        throw new InvalidArgumentException('El número de documento debe tener entre 1 y 10 dígitos.');
    }
}

function opcionesPosicionesPorDeporte(): array
{
    return [
        'Fútbol' => [
            'Portero', 'Líbero', 'Central', 'Lateral', 'Carrilero', 'Pivote',
            'Interior', 'Volante', 'Media punta', 'Extremo', 'Segundo delantero',
            'Delantero centro',
        ],
        'Fútbol sala' => ['Portero', 'Cierre', 'Ala', 'Pívot'],
        'Baloncesto' => ['Base', 'Escolta', 'Alero', 'Ala-pívot', 'Pívot'],
        'Voleibol' => [
            'Receptor atacante (punta)', 'Central', 'Rematador opuesto',
            'Líbero', 'Receptor zaguero', 'Colocador',
        ],
        'Atletismo' => ['Velocidad', 'Medio fondo', 'Fondo', 'Saltos', 'Lanzamientos'],
    ];
}

function validarPerfilEstudiante(array $entrada): array
{
    $deporte = trim((string) ($entrada['deporte'] ?? ''));
    $posiciones = $entrada['posiciones'] ?? [];
    if (!is_array($posiciones)) {
        $posiciones = [];
    }
    $opcionesPorDeporte = opcionesPosicionesPorDeporte();
    $deporteConPosiciones = null;
    foreach (array_keys($opcionesPorDeporte) as $deporteDisponible) {
        if (mb_strtolower($deporte) === mb_strtolower($deporteDisponible)) {
            $deporteConPosiciones = $deporteDisponible;
            $deporte = $deporteDisponible;
            break;
        }
    }
    if ($deporteConPosiciones !== null) {
        $posiciones = array_values(array_intersect($posiciones, $opcionesPorDeporte[$deporteConPosiciones]));
        $posicion = implode(', ', $posiciones);
    } else {
        $posicion = trim((string) ($entrada['posicion_otro'] ?? ''));
    }
    $cantidadIntegrantes = filter_var($entrada['cantidad_integrantes'] ?? 10, FILTER_VALIDATE_INT);
    $cantidadIntegrantes = $cantidadIntegrantes !== false ? $cantidadIntegrantes : 10;
    $datos = [
        'equipo' => trim((string) ($entrada['equipo'] ?? '')),
        'deporte' => $deporte,
        'posicion' => $posicion,
        'capitan' => isset($entrada['capitan']) && $entrada['capitan'] === '1',
        'cantidad_integrantes' => max(5, min(10, $cantidadIntegrantes)),
    ];

    $errores = [];
    foreach (['equipo' => 100, 'deporte' => 60, 'posicion' => 60] as $campo => $maximo) {
        if ($datos[$campo] === '' || mb_strlen($datos[$campo]) > $maximo) {
            $errores[$campo] = 'Completa este campo con un valor válido (máximo ' . $maximo . ' caracteres).';
        }
    }
    if ($datos['cantidad_integrantes'] < 5 || $datos['cantidad_integrantes'] > 10) {
        $errores['cantidad_integrantes'] = 'El equipo debe tener entre 5 y 10 integrantes.';
    }
    if ($deporteConPosiciones !== null && !$posiciones) {
        $errores['posicion'] = 'Elige al menos una posición para este deporte.';
    }
    if ($errores) {
        throw new ErrorValidacion($errores);
    }
    return $datos;
}
