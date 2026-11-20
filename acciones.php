<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexionBDD.php';
require_once __DIR__ . '/../datos/estudianteDAO.php';
require_once __DIR__ . '/../logica/validarEstudiante.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

validarTokenFormulario();
$accion = (string) ($_POST['accion'] ?? '');

try {
    switch ($accion) {
        case 'registrar':
            $datos = validarRegistro($_POST);
            $usuarioId = crearUsuario($pdo, $datos);
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuarioId;
            $_SESSION['nombre'] = $datos['nombre'];
            $_SESSION['rol'] = $datos['rol'];
            mensaje('correcto', 'Tu cuenta se creó correctamente.');
            header('Location: ../index.php?pagina=' . ($datos['rol'] === 'docente' ? 'docente' : 'perfil'));
            exit;

        case 'ingresar':
            $usuario = autenticarUsuario(
                $pdo,
                strtolower(trim((string) ($_POST['correo'] ?? ''))),
                (string) ($_POST['contrasena'] ?? '')
            );
            if (!$usuario) {
                throw new ErrorValidacion([
                    'correo' => 'Correo electrónico o contraseña incorrectos.',
                    'contrasena' => 'Verifica los datos e inténtalo de nuevo.',
                ]);
            }
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            header('Location: ../index.php?pagina=' . ($usuario['rol'] === 'docente' ? 'docente' : 'estudiante'));
            exit;

        case 'solicitar_recuperacion':
            $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new ErrorValidacion(['correo' => 'Escribe un correo electrónico válido.']);
            }
            solicitarRestablecimientoContrasena($pdo, $correo);
            mensaje('correcto', 'Si existe una cuenta con ese correo, recibirás un enlace para cambiar la contraseña.');
            header('Location: ../index.php?pagina=recuperar');
            exit;

        case 'restablecer_contrasena':
            $token = (string) ($_POST['token'] ?? '');
            $nuevaContrasena = (string) ($_POST['nueva_contrasena'] ?? '');
            $confirmarContrasena = (string) ($_POST['confirmar_contrasena'] ?? '');
            $errores = [];
            if (mb_strlen($nuevaContrasena) < 8) {
                $errores['nueva_contrasena'] = 'La contraseña debe tener al menos 8 caracteres.';
            }
            if ($confirmarContrasena !== $nuevaContrasena) {
                $errores['confirmar_contrasena'] = 'Las contraseñas no coinciden.';
            }
            if ($errores) {
                throw new ErrorValidacion($errores);
            }
            if (!restablecerContrasena($pdo, $token, $nuevaContrasena)) {
                throw new InvalidArgumentException('El enlace venció o ya fue utilizado. Solicita uno nuevo.');
            }
            mensaje('correcto', 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión.');
            header('Location: ../index.php?pagina=ingresar');
            exit;

        case 'guardar_perfil':
            exigirSesion();
            if (($_SESSION['rol'] ?? '') !== 'estudiante') {
                throw new InvalidArgumentException('Solo los estudiantes pueden completar esta encuesta.');
            }
            $perfil = validarPerfilEstudiante($_POST);
            $resultado = guardarPerfilEstudiante(
                $pdo,
                (int) $_SESSION['usuario_id'],
                $perfil['equipo'],
                $perfil['deporte'],
                $perfil['posicion'],
                $perfil['capitan'],
                $perfil['cantidad_integrantes']
            );
            if ($resultado['estado'] === 'solicitud_creacion') {
                if ($resultado['error_correo'] !== null) {
                    $avisos = ' La solicitud quedó guardada, pero no se pudo enviar el correo: ' . $resultado['error_correo'];
                } elseif ($resultado['docentes'] === 0) {
                    $avisos = ' No hay cuentas docentes registradas para recibir la notificación; la solicitud aparecerá en el panel cuando un docente tenga acceso.';
                } else {
                    $avisos = $resultado['correos_enviados'] < $resultado['docentes']
                        ? ' La solicitud quedó guardada, pero no fue posible enviar todos los correos; estará visible en el panel docente.'
                        : ' Se notificó a los docentes por correo.';
                }
                mensaje('aviso', 'Tu solicitud para crear el equipo quedó pendiente de aprobación docente.' . $avisos);
                header('Location: ../index.php?pagina=perfil');
            } elseif ($resultado['estado'] === 'solicitud') {
                if ($resultado['error_correo'] !== null) {
                    $avisos = ' La solicitud quedó registrada, pero no se pudo enviar el correo: ' . $resultado['error_correo'];
                } else {
                    $avisos = $resultado['correos_enviados'] < $resultado['capitanes']
                        ? ' La solicitud quedó registrada, pero no fue posible enviar todos los correos; el capitán puede verla al ingresar.'
                        : ' Se notificó al capitán por correo.';
                }
                mensaje('aviso', 'Tu solicitud para unirte al equipo quedó pendiente de aprobación.' . $avisos);
                header('Location: ../index.php?pagina=' . ($resultado['tiene_perfil'] ? 'estudiante' : 'perfil'));
            } elseif ($resultado['estado'] === 'actualizado') {
                mensaje('correcto', 'Tu posición quedó actualizada.');
                header('Location: ../index.php?pagina=estudiante');
            } else {
                mensaje('correcto', $resultado['estado'] === 'creado'
                    ? 'Tu perfil quedó guardado y ahora eres capitán/a del equipo.'
                    : 'Tu perfil quedó guardado.');
                header('Location: ../index.php?pagina=estudiante');
            }
            exit;

        case 'aprobar_creacion_equipo':
        case 'rechazar_creacion_equipo':
            exigirDocente();
            $solicitudId = filter_var($_POST['solicitud_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$solicitudId) {
                throw new InvalidArgumentException('La solicitud seleccionada no es válida.');
            }
            $aprobar = $accion === 'aprobar_creacion_equipo';
            responderSolicitudCreacionEquipo($pdo, (int) $_SESSION['usuario_id'], (int) $solicitudId, $aprobar);
            mensaje('correcto', $aprobar ? 'El equipo fue creado y el estudiante quedó como capitán.' : 'La solicitud de creación fue rechazada.');
            header('Location: ../index.php?pagina=docente#solicitudes-creacion-equipo');
            exit;

        case 'aprobar_solicitud_equipo':
        case 'rechazar_solicitud_equipo':
            exigirSesion();
            if (($_SESSION['rol'] ?? '') !== 'estudiante') {
                throw new InvalidArgumentException('Solo el capitán del equipo puede responder solicitudes.');
            }
            $solicitudId = filter_var($_POST['solicitud_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$solicitudId) {
                throw new InvalidArgumentException('La solicitud seleccionada no es válida.');
            }
            if ($accion === 'aprobar_solicitud_equipo') {
                responderSolicitudEquipo($pdo, (int) $_SESSION['usuario_id'], (int) $solicitudId, true);
                mensaje('correcto', 'El jugador fue aceptado en el equipo.');
            } else {
                responderSolicitudEquipo($pdo, (int) $_SESSION['usuario_id'], (int) $solicitudId, false);
                mensaje('correcto', 'La solicitud fue rechazada.');
            }
            header('Location: ../index.php?pagina=estudiante#solicitudes');
            exit;

        case 'eliminar_jugador_equipo':
            exigirSesion();
            if (($_SESSION['rol'] ?? '') !== 'estudiante') {
                throw new InvalidArgumentException('Solo el capitán del equipo puede retirar jugadores.');
            }
            $jugadorId = filter_var($_POST['jugador_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$jugadorId) {
                throw new InvalidArgumentException('El jugador seleccionado no es válido.');
            }
            retirarJugadorEquipo($pdo, (int) $_SESSION['usuario_id'], (int) $jugadorId);
            mensaje('correcto', 'El jugador fue retirado del equipo.');
            header('Location: ../index.php?pagina=estudiante');
            exit;

        case 'generar_calendario':
            exigirDocente();
            $equiposValidos = $pdo->query(
                'SELECT COUNT(*) FROM equipos e
                 INNER JOIN jugadores pe ON pe.equipo_id = e.id
                 GROUP BY e.id
                 HAVING COUNT(pe.usuario_id) >= 5'
            )->fetchAll(PDO::FETCH_COLUMN);
            if (count($equiposValidos) < 6) {
                throw new InvalidArgumentException('Necesitas al menos 6 equipos con 5 o más integrantes para generar el calendario.');
            }
            $cantidad = generarCalendarioAleatorio($pdo, (int) $_SESSION['usuario_id']);
            mensaje($cantidad > 0 ? 'correcto' : 'aviso', $cantidad > 0
                ? "Se generaron {$cantidad} partidos. El calendario quedó sin publicar para que puedas revisarlo."
                : 'Se necesitan al menos 6 equipos válidos para generar el calendario.');
            header('Location: ../index.php?pagina=docente#calendario');
            exit;

        case 'guardar_partidos':
            exigirDocente();
            $partidos = $_POST['partidos'] ?? [];
            if (!is_array($partidos)) {
                throw new InvalidArgumentException('Los datos del calendario no son válidos.');
            }
            foreach ($partidos as $partido) {
                if (!is_array($partido) || (string) ($partido['fecha'] ?? '') !== '' && strtotime((string) $partido['fecha']) === false) {
                    throw new InvalidArgumentException('Revisa la fecha de cada partido.');
                }
            }
            guardarPartidos($pdo, $partidos);
            mensaje('correcto', 'El calendario quedó actualizado.');
            header('Location: ../index.php?pagina=docente#calendario');
            exit;

        case 'publicar_calendario':
        case 'publicar_posiciones':
            exigirDocente();
            $calendario = $accion === 'publicar_calendario';
            $publicado = ($_POST['publicado'] ?? '') === '1';
            if ($calendario && $publicado && count(listarPartidos($pdo)) === 0) {
                throw new InvalidArgumentException('Genera el calendario antes de publicarlo.');
            }
            if (!$calendario && $publicado && count(listarEquipos($pdo)) === 0) {
                throw new InvalidArgumentException('Registra equipos antes de publicar las posiciones.');
            }
            actualizarPublicacion(
                $pdo,
                $calendario ? 'calendario_publicado' : 'posiciones_publicadas',
                $publicado,
                (int) $_SESSION['usuario_id']
            );
            mensaje('correcto', $publicado ? 'La información ya está visible para los estudiantes.' : 'La información dejó de estar visible.');
            header('Location: ../index.php?pagina=docente');
            exit;

        case 'guardar_posiciones':
            exigirDocente();
            $filas = $_POST['posiciones'] ?? [];
            if (!is_array($filas)) {
                throw new InvalidArgumentException('Los datos de posiciones no son válidos.');
            }
            $posicionesVoleibol = array_values(array_filter(
                listarPosiciones($pdo),
                static fn(array $fila): bool => deporteEsVoleibol((string) $fila['deporte'])
            ));
            if (!$posicionesVoleibol) {
                throw new InvalidArgumentException('Todavía no hay equipos de voleibol para clasificar.');
            }
            $equiposValidos = array_column($posicionesVoleibol, 'id');
            $equiposValidos = array_map('intval', $equiposValidos);
            $filasValidadas = [];
            $limitePuntos = '18446744073709551615';
            foreach ($filas as $equipoId => $datos) {
                if (!in_array((int) $equipoId, $equiposValidos, true) || !is_array($datos)) {
                    throw new InvalidArgumentException('Hay un equipo inválido en la tabla.');
                }
                $rondas = [];
                foreach (['ronda_1', 'ronda_2', 'ronda_3'] as $ronda) {
                    $entrada = $datos[$ronda] ?? null;
                    if (!is_string($entrada) && !is_int($entrada)) {
                        throw new InvalidArgumentException('Los puntos de cada ronda deben ser números enteros no negativos.');
                    }
                    $puntos = (string) $entrada;
                    if (!preg_match('/\A\d+\z/', $puntos)) {
                        throw new InvalidArgumentException('Los puntos de cada ronda deben ser números enteros no negativos.');
                    }
                    $puntos = ltrim($puntos, '0');
                    $puntos = $puntos === '' ? '0' : $puntos;
                    if (strlen($puntos) > strlen($limitePuntos)
                        || strlen($puntos) === strlen($limitePuntos) && strcmp($puntos, $limitePuntos) > 0) {
                        throw new InvalidArgumentException('El puntaje por ronda supera el máximo permitido por la base de datos.');
                    }
                    $rondas[$ronda] = $puntos;
                }
                $filasValidadas[(int) $equipoId] = $rondas;
            }
            if (count($filasValidadas) !== count($equiposValidos)) {
                throw new InvalidArgumentException('Debes ingresar los puntos de las tres rondas para cada equipo de voleibol.');
            }
            guardarPosiciones($pdo, $filasValidadas);
            mensaje('correcto', 'Los puntos de las tres rondas quedaron guardados y la clasificación fue actualizada.');
            header('Location: ../index.php?pagina=docente#posiciones');
            exit;

        case 'guardar_tarjetas':
            exigirDocente();
            $tarjetas = $_POST['tarjetas'] ?? [];
            if (!is_array($tarjetas)) {
                throw new InvalidArgumentException('Los datos de tarjetas no son válidos.');
            }
            foreach ($tarjetas as $datos) {
                if (!is_array($datos)) {
                    throw new InvalidArgumentException('Los datos de tarjetas no son válidos.');
                }
                foreach (['amarillas', 'rojas'] as $color) {
                    $cantidad = filter_var($datos[$color] ?? null, FILTER_VALIDATE_INT);
                    if ($cantidad === false || $cantidad < 0 || $cantidad > 999) {
                        throw new InvalidArgumentException('La cantidad de tarjetas debe ser un número entero entre 0 y 999.');
                    }
                }
            }
            guardarTarjetasJugadores($pdo, $tarjetas);
            mensaje('correcto', 'Las tarjetas de los jugadores quedaron actualizadas.');
            header('Location: ../index.php?pagina=docente#tarjetas');
            exit;

        case 'salir':
            $_SESSION = [];
            session_destroy();
            header('Location: ../index.php');
            exit;

        default:
            throw new InvalidArgumentException('La acción solicitada no es válida.');
    }
} catch (ErrorValidacion $error) {
    $erroresFormulario = $error->errores();
    mensaje('error', $error->getMessage());
    guardarFormularioConError($accion, $erroresFormulario);
} catch (InvalidArgumentException $error) {
    mensaje('error', $error->getMessage());
    guardarFormularioConError($accion, []);
} catch (PDOException $error) {
    $mensajeError = str_contains($error->getMessage(), 'Duplicate entry')
        ? 'Ya existe una cuenta con ese correo o número de documento.'
        : 'No se pudo guardar la información. Revisa los datos y vuelve a intentar.';
    mensaje('error', $mensajeError);
    if (str_contains($error->getMessage(), 'Duplicate entry')) {
        $campoDuplicado = str_contains($error->getMessage(), 'numero_documento') ? 'numero_documento' : 'correo';
        guardarFormularioConError($accion, [$campoDuplicado => 'Este dato ya está registrado.']);
    } else {
        guardarFormularioConError($accion, []);
    }
} catch (Throwable $error) {
    mensaje('error', 'Ocurrió un error al procesar la solicitud.');
    guardarFormularioConError($accion, []);
}

$paginaAnterior = match ($accion) {
    'registrar' => 'registro',
    'ingresar' => 'ingresar',
    'solicitar_recuperacion' => 'recuperar',
    'restablecer_contrasena' => 'restablecer',
    'guardar_perfil' => 'perfil',
    'aprobar_creacion_equipo', 'rechazar_creacion_equipo' => 'docente',
    'aprobar_solicitud_equipo', 'rechazar_solicitud_equipo', 'eliminar_jugador_equipo' => 'estudiante',
    'guardar_partidos', 'generar_calendario', 'publicar_calendario', 'guardar_posiciones', 'publicar_posiciones', 'guardar_tarjetas' => 'docente',
    default => 'docente',
};
header('Location: ' . urlInicio($paginaAnterior));
exit;

function guardarFormularioConError(string $accion, array $errores): void
{
    $paginas = [
        'registrar' => 'registro',
        'ingresar' => 'ingresar',
        'solicitar_recuperacion' => 'recuperar',
        'restablecer_contrasena' => 'restablecer',
        'guardar_perfil' => 'perfil',
    ];
    if (!isset($paginas[$accion])) {
        return;
    }
    $valores = [];
    foreach ($_POST as $campo => $valor) {
        if (in_array($campo, ['csrf', 'contrasena', 'nueva_contrasena', 'confirmar_contrasena', 'clave_docente', 'accion'], true)) {
            continue;
        }
        if (is_string($valor) || is_array($valor)) {
            $valores[$campo] = $valor;
        }
    }
    $_SESSION['formulario_error'] = [
        'pagina' => $paginas[$accion],
        'valores' => $valores,
        'errores' => $errores,
    ];
}