<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexionBDD.php';
require_once __DIR__ . '/../datos/estudianteDAO.php';
require_once __DIR__ . '/../logica/validarEstudiante.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?pagina=listar_estudiantes');
    exit;
}

validarTokenFormulario();
exigirDocente();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

try {
    if (!$id || !obtenerEstudiante($pdo, (int) $id)) {
        throw new InvalidArgumentException('No se encontró el estudiante solicitado.');
    }

    $datos = [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'apellido' => trim((string) ($_POST['apellido'] ?? '')),
        'tipo_documento' => trim((string) ($_POST['tipo_documento'] ?? '')),
        'numero_documento' => trim((string) ($_POST['numero_documento'] ?? '')),
        'genero' => trim((string) ($_POST['genero'] ?? '')),
        'correo' => strtolower(trim((string) ($_POST['correo'] ?? ''))),
    ];

    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 80 || $datos['apellido'] === '' || mb_strlen($datos['apellido']) > 100) {
        throw new InvalidArgumentException('El nombre y el apellido son obligatorios.');
    }
    if (!in_array($datos['tipo_documento'], ['Cédula de ciudadanía', 'Tarjeta de identidad', 'Pasaporte', 'Otro'], true)) {
        throw new InvalidArgumentException('Selecciona un tipo de documento válido.');
    }
    validarNumeroDocumento($datos['numero_documento']);
    if ($datos['genero'] === '' || mb_strlen($datos['genero']) > 40) {
        throw new InvalidArgumentException('Revisa el número de documento y el género.');
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Escribe un correo electrónico válido.');
    }

    actualizarEstudiante($pdo, (int) $id, $datos);
    mensaje('correcto', 'Los datos del estudiante quedaron actualizados.');
} catch (InvalidArgumentException $error) {
    mensaje('error', $error->getMessage());
} catch (PDOException $error) {
    mensaje('error', str_contains($error->getMessage(), 'Duplicate entry')
        ? 'Ese nombre completo, documento o correo ya está asignado a otra cuenta.'
        : 'No se pudieron actualizar los datos.');
}

header('Location: ../index.php?pagina=listar_estudiantes');
exit;