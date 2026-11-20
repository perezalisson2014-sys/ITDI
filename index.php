<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexionBDD.php';
require_once __DIR__ . '/datos/estudianteDAO.php';
require_once __DIR__ . '/logica/validarEstudiante.php';

$pagina = (string) ($_GET['pagina'] ?? 'inicio');
$mensajeActual = obtenerMensaje();
$estadoFormulario = $_SESSION['formulario_error'] ?? [];
$valoresFormulario = ($estadoFormulario['pagina'] ?? null) === $pagina ? ($estadoFormulario['valores'] ?? []) : [];
$erroresFormulario = ($estadoFormulario['pagina'] ?? null) === $pagina ? ($estadoFormulario['errores'] ?? []) : [];
if (($estadoFormulario['pagina'] ?? null) === $pagina) {
    unset($_SESSION['formulario_error']);
}
$tokenRestablecimiento = (string) ($valoresFormulario['token'] ?? $_GET['token'] ?? '');
$restablecimientoValido = $pagina === 'restablecer' && tokenRestablecimientoValido($pdo, $tokenRestablecimiento);
$ajustes = obtenerAjustesPublicacion($pdo);
$partidos = $ajustes['calendario_publicado'] ? listarPartidos($pdo) : [];
$posiciones = $ajustes['posiciones_publicadas'] ? listarPosiciones($pdo) : [];
$tarjetasPorEquipo = [];
if ($ajustes['posiciones_publicadas'] && in_array($pagina, ['inicio', 'estudiante'], true)) {
    foreach (listarTarjetasJugadores($pdo) as $tarjeta) {
        $tarjetasPorEquipo[$tarjeta['equipo_id']][] = $tarjeta;
    }
}
$perfil = null;
$miembrosEquipo = [];
$solicitudesEquipo = [];
$solicitudesPendientes = [];
$solicitudesCreacionEquipo = [];
$solicitudesCreacionEquipoPendientes = [];
$estudiantes = [];
$jugadores = [];
$equipos = [];
$integrantesPorEquipo = [];
$partidosDocente = [];
$calendariosDocente = [];
$posicionesDocente = [];
$estudianteEditar = null;

if ($pagina === 'perfil' || $pagina === 'estudiante') {
    exigirSesion();
    if (($_SESSION['rol'] ?? '') !== 'estudiante') {
        header('Location: index.php?pagina=docente');
        exit;
    }
    $perfil = obtenerPerfilEstudiante($pdo, (int) $_SESSION['usuario_id']);
    if ($pagina === 'estudiante' && !$perfil) {
        header('Location: index.php?pagina=perfil');
        exit;
    }
    if ($pagina === 'estudiante' && $perfil) {
        $consulta = $pdo->prepare(
            'SELECT u.id AS usuario_id, u.nombre, u.apellido, pe.posicion, pe.es_capitan
             FROM jugadores pe INNER JOIN usuarios u ON u.id = pe.usuario_id
             WHERE pe.equipo_id = ? ORDER BY pe.es_capitan DESC, u.apellido, u.nombre'
        );
        $consulta->execute([(int) $perfil['equipo_id']]);
        $miembrosEquipo = $consulta->fetchAll();
        if ($perfil['es_capitan']) {
            $solicitudesEquipo = listarSolicitudesPendientesEquipo($pdo, (int) $_SESSION['usuario_id']);
        }
    }
    if ($pagina === 'perfil') {
        $solicitudesPendientes = listarSolicitudesPendientesEstudiante($pdo, (int) $_SESSION['usuario_id']);
        $solicitudesCreacionEquipo = listarSolicitudesCreacionEquipo($pdo, (int) $_SESSION['usuario_id']);
    }
} elseif ($pagina === 'docente') {
    exigirDocente();
    $estudiantes = listarEstudiantes($pdo);
    $jugadores = listarJugadores($pdo);
    $equipos = listarEquipos($pdo);
    foreach (listarIntegrantesEquipos($pdo) as $integrante) {
        $integrantesPorEquipo[$integrante['equipo_id']][] = $integrante;
    }
    $partidosDocente = listarPartidos($pdo);
    $calendariosDocente = listarCalendarios($pdo);
    $posicionesDocente = listarPosiciones($pdo);
    $solicitudesCreacionEquipoPendientes = listarSolicitudesCreacionEquipoPendientes($pdo);
} elseif ($pagina === 'listar_estudiantes') {
    exigirDocente();
    $estudiantes = listarEstudiantes($pdo);
} elseif ($pagina === 'editar_estudiante') {
    exigirDocente();
    $idEstudiante = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $estudianteEditar = $idEstudiante ? obtenerEstudiante($pdo, (int) $idEstudiante) : null;
    if (!$estudianteEditar) {
        http_response_code(404);
        mensaje('error', 'No se encontró el estudiante solicitado.');
        header('Location: index.php?pagina=listar_estudiantes');
        exit;
    }
}

$vistas = [
    'inicio' => __DIR__ . '/vistas/inicio.php',
    'registro' => __DIR__ . '/vistas/registro.php',
    'ingresar' => __DIR__ . '/vistas/iniciarSesion.php',
    'recuperar' => __DIR__ . '/vistas/recuperarContrasena.php',
    'restablecer' => __DIR__ . '/vistas/restablecerContrasena.php',
    'perfil' => __DIR__ . '/vistas/estudiante/formularioEstudiante.php',
    'estudiante' => __DIR__ . '/vistas/estudiante/perfil.php',
    'docente' => __DIR__ . '/vistas/docente/panel.php',
    'listar_estudiantes' => __DIR__ . '/vistas/estudiante/listaEstudiante.php',
    'editar_estudiante' => __DIR__ . '/vistas/estudiante/formularioEditar.php',
];
if (!isset($vistas[$pagina])) {
    http_response_code(404);
    $pagina = 'inicio';
}

require __DIR__ . '/vistas/encabezado.php';
$estudiante = $estudianteEditar;
require $vistas[$pagina];
require __DIR__ . '/vistas/pie.php';