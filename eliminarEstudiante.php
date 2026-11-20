<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexionBDD.php';
require_once __DIR__ . '/../datos/estudianteDAO.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?pagina=listar_estudiantes');
    exit;
}

validarTokenFormulario();
exigirDocente();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if ($id && eliminarEstudiante($pdo, (int) $id)) {
    mensaje('correcto', 'El estudiante y su perfil deportivo fueron eliminados.');
} else {
    mensaje('error', 'No se encontró el estudiante que intentabas eliminar.');
}

header('Location: ../index.php?pagina=listar_estudiantes');
exit;