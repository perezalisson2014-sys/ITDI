<?php
declare(strict_types=1);

function crearUsuario(PDO $pdo, array $datos): int
{
    $consulta = $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellido, tipo_documento, numero_documento, genero, correo, rol, password_hash)
         VALUES (:nombre, :apellido, :tipo_documento, :numero_documento, :genero, :correo, :rol, :password_hash)'
    );
    $consulta->execute([
        'nombre' => $datos['nombre'],
        'apellido' => $datos['apellido'],
        'tipo_documento' => $datos['tipo_documento'],
        'numero_documento' => $datos['numero_documento'],
        'genero' => $datos['genero'],
        'correo' => $datos['correo'],
        'rol' => $datos['rol'],
        'password_hash' => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
    ]);
    return (int) $pdo->lastInsertId();
}

function autenticarUsuario(PDO $pdo, string $correo, string $contrasena): ?array
{
    $consulta = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ? LIMIT 1');
    $consulta->execute([$correo]);
    $usuario = $consulta->fetch();

    if (!$usuario || !password_verify($contrasena, $usuario['password_hash'])) {
        return null;
    }

    return $usuario;
}

function asegurarTablaTokensContrasena(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tokens_recuperacion_contrasena (
            usuario_id INT NOT NULL PRIMARY KEY,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expira_en DATETIME NOT NULL
        ) ENGINE=InnoDB'
    );

    $tipoUsuario = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id'"
    )->fetchColumn();
    if (!is_string($tipoUsuario) || !preg_match('/\Aint(?:\(\d+\))?(?: unsigned)?\z/i', $tipoUsuario)) {
        throw new RuntimeException('No se pudo determinar el tipo de la clave de usuarios.id.');
    }

    $tipoToken = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokens_recuperacion_contrasena'
           AND COLUMN_NAME = 'usuario_id'"
    )->fetchColumn();
    $tipoTokenUnsigned = is_string($tipoToken) && stripos($tipoToken, 'unsigned') !== false;
    $tipoUsuarioUnsigned = stripos($tipoUsuario, 'unsigned') !== false;
    if ($tipoTokenUnsigned !== $tipoUsuarioUnsigned) {
        $tipoSql = $tipoUsuarioUnsigned ? 'INT UNSIGNED' : 'INT';
        $pdo->exec("ALTER TABLE tokens_recuperacion_contrasena MODIFY COLUMN usuario_id {$tipoSql} NOT NULL");
    }

    $buscarRelacion = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tokens_recuperacion_contrasena'
           AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME = 'usuarios'
           AND REFERENCED_COLUMN_NAME = 'id'"
    );
    $buscarRelacion->execute();
    if ((int) $buscarRelacion->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE tokens_recuperacion_contrasena
             ADD CONSTRAINT token_usuario_fk FOREIGN KEY (usuario_id)
             REFERENCES usuarios(id) ON DELETE CASCADE'
        );
    }
}

function solicitarRestablecimientoContrasena(PDO $pdo, string $correo): void
{
    asegurarTablaTokensContrasena($pdo);
    $buscarUsuario = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ? LIMIT 1');
    $buscarUsuario->execute([$correo]);
    $usuarioId = $buscarUsuario->fetchColumn();
    if ($usuarioId === false) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $guardarToken = $pdo->prepare(
        'INSERT INTO tokens_recuperacion_contrasena (usuario_id, token_hash, expira_en)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
         ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expira_en = VALUES(expira_en)'
    );
    $guardarToken->execute([(int) $usuarioId, hash('sha256', $token)]);
    try {
        $enlace = urlAplicacionPublica() . '/index.php?pagina=restablecer&token=' . rawurlencode($token);
        $enviado = enviarCorreoAplicacion(
            $correo,
            'Restablecer contraseña',
            "Recibimos una solicitud para cambiar la contraseña de tu cuenta.\n\nAbre este enlace en la próxima hora:\n{$enlace}\n\nSi no solicitaste este cambio, ignora este mensaje."
        );
        if (!$enviado) {
            throw new RuntimeException('No se pudo enviar el correo de recuperación.');
        }
    } catch (Throwable $error) {
        error_log($error->getMessage());
        $eliminarToken = $pdo->prepare('DELETE FROM tokens_recuperacion_contrasena WHERE usuario_id = ?');
        $eliminarToken->execute([(int) $usuarioId]);
    }
}

function tokenRestablecimientoValido(PDO $pdo, string $token): bool
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return false;
    }
    asegurarTablaTokensContrasena($pdo);
    $consulta = $pdo->prepare(
        'SELECT 1 FROM tokens_recuperacion_contrasena WHERE token_hash = ? AND expira_en > NOW() LIMIT 1'
    );
    $consulta->execute([hash('sha256', $token)]);
    return $consulta->fetchColumn() !== false;
}

function restablecerContrasena(PDO $pdo, string $token, string $nuevaContrasena): bool
{
    if (!tokenRestablecimientoValido($pdo, $token)) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $buscarToken = $pdo->prepare(
            'SELECT usuario_id FROM tokens_recuperacion_contrasena
             WHERE token_hash = ? AND expira_en > NOW() LIMIT 1 FOR UPDATE'
        );
        $buscarToken->execute([hash('sha256', $token)]);
        $usuarioId = $buscarToken->fetchColumn();
        if ($usuarioId === false) {
            $pdo->rollBack();
            return false;
        }
        $actualizar = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
        $actualizar->execute([password_hash($nuevaContrasena, PASSWORD_DEFAULT), (int) $usuarioId]);
        $eliminarTokens = $pdo->prepare('DELETE FROM tokens_recuperacion_contrasena WHERE usuario_id = ?');
        $eliminarTokens->execute([(int) $usuarioId]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function obtenerPerfilEstudiante(PDO $pdo, int $usuarioId): ?array
{
    $consulta = $pdo->prepare(
        'SELECT pe.posicion, pe.es_capitan, e.id AS equipo_id, e.nombre AS equipo, e.deporte
         FROM jugadores pe
         INNER JOIN equipos e ON e.id = pe.equipo_id
         WHERE pe.usuario_id = ?'
    );
    $consulta->execute([$usuarioId]);
    $perfil = $consulta->fetch();
    return $perfil ?: null;
}

function guardarPerfilEstudiante(PDO $pdo, int $usuarioId, string $equipo, string $deporte, string $posicion, bool $capitan, int $cantidadIntegrantes = 10): array
{
    $cantidadIntegrantes = max(5, min(10, $cantidadIntegrantes));
    $pdo->beginTransaction();
    try {
        $perfilActual = $pdo->prepare('SELECT equipo_id FROM jugadores WHERE usuario_id = ? FOR UPDATE');
        $perfilActual->execute([$usuarioId]);
        $perfil = $perfilActual->fetch();
        if ($perfil) {
            $actualizar = $pdo->prepare('UPDATE jugadores SET posicion = ? WHERE usuario_id = ?');
            $actualizar->execute([$posicion, $usuarioId]);
            $pdo->commit();
            return ['estado' => 'actualizado', 'tiene_perfil' => true, 'correos_enviados' => 0, 'capitanes' => 0];
        }

        $buscarEquipo = $pdo->prepare('SELECT id FROM equipos WHERE nombre = ? AND deporte = ? LIMIT 1 FOR UPDATE');
        $buscarEquipo->execute([$equipo, $deporte]);
        $equipoId = $buscarEquipo->fetchColumn();
        if ($equipoId === false) {
            if (!$capitan) {
                throw new InvalidArgumentException('Ese equipo aún no existe. Para crearlo debes registrarte como capitán/a.');
            }
            $solicitudUnionPendiente = $pdo->prepare(
                "SELECT id FROM solicitudes_equipo WHERE usuario_id = ? AND estado = 'pendiente' LIMIT 1"
            );
            $solicitudUnionPendiente->execute([$usuarioId]);
            if ($solicitudUnionPendiente->fetchColumn() !== false) {
                throw new InvalidArgumentException('Ya tienes una solicitud pendiente para unirte a un equipo; espera la respuesta antes de solicitar crear otro.');
            }
            $solicitudExistente = $pdo->prepare(
                "SELECT id FROM solicitudes_creacion_equipo
                 WHERE usuario_id = ? AND estado = 'pendiente' LIMIT 1"
            );
            $solicitudExistente->execute([$usuarioId]);
            if ($solicitudExistente->fetchColumn() !== false) {
                throw new InvalidArgumentException('Ya tienes una solicitud de creación de equipo pendiente.');
            }
            $solicitud = $pdo->prepare(
                "INSERT INTO solicitudes_creacion_equipo (usuario_id, nombre_equipo, deporte, posicion, cantidad_integrantes, estado)
                 VALUES (?, ?, ?, ?, ?, 'pendiente')"
            );
            $solicitud->execute([$usuarioId, $equipo, $deporte, $posicion, $cantidadIntegrantes]);
            $solicitante = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id = ?');
            $solicitante->execute([$usuarioId]);
            $datosSolicitante = $solicitante->fetch();
            if (!$datosSolicitante) {
                throw new RuntimeException('No se encontró la cuenta del solicitante.');
            }
            $nombreSolicitante = $datosSolicitante['nombre'] . ' ' . $datosSolicitante['apellido'];
            $docentes = $pdo->query(
                "SELECT correo, CONCAT(nombre, ' ', apellido) AS nombre FROM usuarios WHERE rol = 'docente'"
            )->fetchAll();
            $pdo->commit();

            $errorCorreo = null;
            try {
                $correosEnviados = enviarNotificacionCreacionEquipo(
                    $docentes,
                    $nombreSolicitante,
                    $equipo,
                    $deporte
                );
            } catch (RuntimeException $error) {
                $correosEnviados = 0;
                $errorCorreo = $error->getMessage();
            }
            return [
                'estado' => 'solicitud_creacion',
                'tiene_perfil' => false,
                'correos_enviados' => $correosEnviados,
                'docentes' => count($docentes),
                'error_correo' => $errorCorreo,
            ];
        }

        $equipoId = (int) $equipoId;
        $equipoActual = $pdo->prepare('SELECT cantidad_integrantes FROM equipos WHERE id = ? LIMIT 1');
        $equipoActual->execute([$equipoId]);
        $capacidadEquipo = (int) $equipoActual->fetchColumn();
        $cantidadActual = $pdo->prepare('SELECT COUNT(*) FROM jugadores WHERE equipo_id = ?');
        $cantidadActual->execute([$equipoId]);
        $miembrosActuales = (int) $cantidadActual->fetchColumn();
        if ($miembrosActuales >= $capacidadEquipo) {
            throw new InvalidArgumentException('El equipo ya alcanzó su capacidad máxima de ' . $capacidadEquipo . ' integrantes.');
        }
        $capitanes = $pdo->prepare(
            "SELECT u.correo, CONCAT(u.nombre, ' ', u.apellido) AS nombre
             FROM jugadores pe INNER JOIN usuarios u ON u.id = pe.usuario_id
             WHERE pe.equipo_id = ? AND pe.es_capitan = 1"
        );
        $capitanes->execute([$equipoId]);
        $destinatarios = $capitanes->fetchAll();
        if (!$destinatarios) {
            $guardarPerfil = $pdo->prepare(
                'INSERT INTO jugadores (usuario_id, equipo_id, posicion, es_capitan) VALUES (?, ?, ?, 1)'
            );
            $guardarPerfil->execute([$usuarioId, $equipoId, $posicion]);
            $pdo->prepare("UPDATE solicitudes_equipo SET estado = 'rechazada' WHERE usuario_id = ? AND estado = 'pendiente'")
                ->execute([$usuarioId]);
            $pdo->commit();
            return ['estado' => 'creado', 'tiene_perfil' => false, 'correos_enviados' => 0, 'capitanes' => 0];
        }

        $creacionPendiente = $pdo->prepare(
            "SELECT id FROM solicitudes_creacion_equipo
             WHERE usuario_id = ? AND estado = 'pendiente' LIMIT 1"
        );
        $creacionPendiente->execute([$usuarioId]);
        if ($creacionPendiente->fetchColumn() !== false) {
            throw new InvalidArgumentException('Ya tienes una solicitud de creación de equipo pendiente; espera la respuesta del docente.');
        }
        $pendiente = $pdo->prepare(
            "SELECT id FROM solicitudes_equipo WHERE usuario_id = ? AND estado = 'pendiente' LIMIT 1"
        );
        $pendiente->execute([$usuarioId]);
        if ($pendiente->fetchColumn() !== false) {
            throw new InvalidArgumentException('Ya tienes una solicitud pendiente para un equipo; espera la respuesta del capitán.');
        }
        $insertarSolicitud = $pdo->prepare(
            "INSERT INTO solicitudes_equipo (usuario_id, equipo_id, posicion, estado) VALUES (?, ?, ?, 'pendiente')"
        );
        $insertarSolicitud->execute([$usuarioId, $equipoId, $posicion]);
        $equipo = $pdo->prepare('SELECT cantidad_integrantes FROM equipos WHERE id = ? LIMIT 1');
        $equipo->execute([$equipoId]);
        $capacidad = (int) $equipo->fetchColumn();
        $cantidadMiembros = $pdo->prepare('SELECT COUNT(*) FROM jugadores WHERE equipo_id = ?');
        $cantidadMiembros->execute([$equipoId]);
        if ((int) $cantidadMiembros->fetchColumn() >= $capacidad) {
            throw new InvalidArgumentException('Este equipo ya tiene la capacidad máxima de ' . $capacidad . ' integrantes.');
        }
        $pdo->commit();

        $errorCorreo = null;
        try {
            $correosEnviados = enviarCorreosSolicitudEquipo($pdo, $destinatarios, $usuarioId, $equipo, $deporte);
        } catch (RuntimeException $error) {
            $correosEnviados = 0;
            $errorCorreo = $error->getMessage();
        }
        return [
            'estado' => 'solicitud',
            'tiene_perfil' => false,
            'correos_enviados' => $correosEnviados,
            'capitanes' => count($destinatarios),
            'error_correo' => $errorCorreo,
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function enviarCorreosSolicitudEquipo(PDO $pdo, array $capitanes, int $solicitanteId, string $equipo, string $deporte): int
{
    if (!function_exists('mail')) {
        throw new RuntimeException('La función de correo de PHP no está disponible.');
    }
    $consulta = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id = ?');
    $consulta->execute([$solicitanteId]);
    $solicitante = $consulta->fetch();
    if (!$solicitante) {
        throw new RuntimeException('No se encontró la cuenta del solicitante.');
    }

    $enlace = urlAplicacionPublica() . '/index.php?pagina=estudiante#solicitudes';
    $nombreSolicitante = $solicitante['nombre'] . ' ' . $solicitante['apellido'];
    $asunto = 'Solicitud para unirse a un equipo';
    $enviados = 0;
    foreach ($capitanes as $capitan) {
        $cuerpo = "Hola {$capitan['nombre']},\n\n{$nombreSolicitante} quiere unirse al equipo {$equipo} ({$deporte}).\n"
            . "Inicia sesión para aprobar o rechazar la solicitud:\n{$enlace}\n";
        if (enviarCorreoAplicacion($capitan['correo'], $asunto, $cuerpo)) {
            $enviados++;
        }
    }
    return $enviados;
}

function enviarNotificacionCreacionEquipo(array $docentes, string $nombreSolicitante, string $equipo, string $deporte): int
{
    if (!$docentes) {
        return 0;
    }

    $enlace = urlAplicacionPublica() . '/index.php?pagina=docente#solicitudes-creacion-equipo';
    $asunto = 'Solicitud de aprobación para crear un equipo';
    $enviados = 0;
    foreach ($docentes as $docente) {
        $cuerpo = "Hola {$docente['nombre']},\n\n{$nombreSolicitante} solicita autorización para crear el equipo {$equipo} ({$deporte}) y ser su capitán/a.\n"
            . "Inicia sesión para aprobar o rechazar la solicitud:\n{$enlace}\n";
        if (enviarCorreoAplicacion($docente['correo'], $asunto, $cuerpo)) {
            $enviados++;
        }
    }
    return $enviados;
}

function urlAplicacionPublica(): string
{
    $urlAplicacion = rtrim((string) (getenv('ITDI_APP_URL') ?: ''), '/');
    if ($urlAplicacion === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/\A[a-zA-Z0-9.-]+(?::[0-9]{1,5})?\z/', $host)) {
            throw new RuntimeException('Configura ITDI_APP_URL para enviar el enlace de solicitudes por correo.');
        }
        $protocolo = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $directorio = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        $directorio = preg_replace('~/procesos$~', '', $directorio) ?: '';
        $urlAplicacion = $protocolo . '://' . $host . rtrim($directorio, '/');
    }
    if (!filter_var($urlAplicacion, FILTER_VALIDATE_URL) || !in_array(parse_url($urlAplicacion, PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new RuntimeException('ITDI_APP_URL debe ser una dirección http o https válida.');
    }
    return $urlAplicacion;
}

function enviarCorreoAplicacion(string $destinatario, string $asunto, string $cuerpo): bool
{
    if (!function_exists('mail')) {
        throw new RuntimeException('La función de correo de PHP no está disponible.');
    }
    $correoRemitente = getenv('ITDI_MAIL_FROM') ?: 'no-reply@localhost';
    if (!filter_var($correoRemitente, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('ITDI_MAIL_FROM debe contener un correo válido.');
    }
    $cabeceras = "From: {$correoRemitente}\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($destinatario, $asunto, $cuerpo, $cabeceras);
}

function listarSolicitudesCreacionEquipo(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT id, nombre_equipo AS equipo, deporte, estado, creado_en
         FROM solicitudes_creacion_equipo WHERE usuario_id = ?
         ORDER BY (estado = 'pendiente') DESC, creado_en DESC LIMIT 5"
    );
    $consulta->execute([$usuarioId]);
    return $consulta->fetchAll();
}

function listarSolicitudesCreacionEquipoPendientes(PDO $pdo): array
{
    return $pdo->query(
        "SELECT sce.id, sce.nombre_equipo, sce.deporte, sce.posicion, sce.creado_en,
                u.nombre, u.apellido, u.correo
         FROM solicitudes_creacion_equipo sce
         INNER JOIN usuarios u ON u.id = sce.usuario_id
         WHERE sce.estado = 'pendiente'
         ORDER BY sce.creado_en, sce.id"
    )->fetchAll();
}

function responderSolicitudCreacionEquipo(PDO $pdo, int $docenteId, int $solicitudId, bool $aprobar): void
{
    $pdo->beginTransaction();
    try {
        $docente = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'docente' FOR UPDATE");
        $docente->execute([$docenteId]);
        if ($docente->fetchColumn() === false) {
            throw new InvalidArgumentException('Solo un docente puede responder esta solicitud.');
        }
        $consulta = $pdo->prepare(
            "SELECT sce.usuario_id, sce.nombre_equipo, sce.deporte, sce.posicion, sce.cantidad_integrantes
             FROM solicitudes_creacion_equipo sce
             WHERE sce.id = ? AND sce.estado = 'pendiente' FOR UPDATE"
        );
        $consulta->execute([$solicitudId]);
        $solicitud = $consulta->fetch();
        if (!$solicitud) {
            throw new InvalidArgumentException('La solicitud ya fue respondida o no está disponible.');
        }

        if ($aprobar) {
            $cantidadPermitida = max(5, min(10, (int) $solicitud['cantidad_integrantes']));
            $equipoExistente = $pdo->prepare('SELECT id FROM equipos WHERE nombre = ? AND deporte = ? LIMIT 1 FOR UPDATE');
            $equipoExistente->execute([$solicitud['nombre_equipo'], $solicitud['deporte']]);
            if ($equipoExistente->fetchColumn() !== false) {
                throw new InvalidArgumentException('Ya existe un equipo con ese nombre y deporte. Rechaza esta solicitud y pide al estudiante que solicite unirse.');
            }
            $crearEquipo = $pdo->prepare('INSERT INTO equipos (nombre, deporte, cantidad_integrantes) VALUES (?, ?, ?)');
            $crearEquipo->execute([$solicitud['nombre_equipo'], $solicitud['deporte'], $cantidadPermitida]);
            $equipoId = (int) $pdo->lastInsertId();
            $perfilExistente = $pdo->prepare('SELECT usuario_id FROM jugadores WHERE usuario_id = ? FOR UPDATE');
            $perfilExistente->execute([(int) $solicitud['usuario_id']]);
            if ($perfilExistente->fetchColumn() !== false) {
                throw new InvalidArgumentException('El estudiante ya pertenece a un equipo y no puede crear otro.');
            }
            $guardarPerfil = $pdo->prepare(
                'INSERT INTO jugadores (usuario_id, equipo_id, posicion, es_capitan) VALUES (?, ?, ?, 1)'
            );
            $guardarPerfil->execute([(int) $solicitud['usuario_id'], $equipoId, $solicitud['posicion']]);
            $rechazarDuplicadas = $pdo->prepare(
                "UPDATE solicitudes_creacion_equipo SET estado = 'rechazada'
                 WHERE nombre_equipo = ? AND deporte = ? AND estado = 'pendiente' AND id <> ?"
            );
            $rechazarDuplicadas->execute([$solicitud['nombre_equipo'], $solicitud['deporte'], $solicitudId]);
            $estado = 'aprobada';
        } else {
            $estado = 'rechazada';
        }

        $actualizar = $pdo->prepare('UPDATE solicitudes_creacion_equipo SET estado = ? WHERE id = ?');
        $actualizar->execute([$estado, $solicitudId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function listarSolicitudesPendientesEquipo(PDO $pdo, int $capitanId): array
{
    $consulta = $pdo->prepare(
        "SELECT se.id, se.posicion, se.creado_en, u.nombre, u.apellido, e.nombre AS equipo, e.deporte
         FROM solicitudes_equipo se
         INNER JOIN usuarios u ON u.id = se.usuario_id
         INNER JOIN equipos e ON e.id = se.equipo_id
         INNER JOIN jugadores cap ON cap.equipo_id = se.equipo_id
         WHERE cap.usuario_id = ? AND cap.es_capitan = 1 AND se.estado = 'pendiente'
         ORDER BY se.creado_en, se.id"
    );
    $consulta->execute([$capitanId]);
    return $consulta->fetchAll();
}

function listarSolicitudesPendientesEstudiante(PDO $pdo, int $usuarioId): array
{
    $consulta = $pdo->prepare(
        "SELECT e.nombre AS equipo, e.deporte, se.creado_en
         FROM solicitudes_equipo se INNER JOIN equipos e ON e.id = se.equipo_id
         WHERE se.usuario_id = ? AND se.estado = 'pendiente'
         ORDER BY se.creado_en DESC"
    );
    $consulta->execute([$usuarioId]);
    return $consulta->fetchAll();
}

function responderSolicitudEquipo(PDO $pdo, int $capitanId, int $solicitudId, bool $aprobar): void
{
    $pdo->beginTransaction();
    try {
        $consulta = $pdo->prepare(
            "SELECT se.usuario_id, se.equipo_id, se.posicion
             FROM solicitudes_equipo se
             INNER JOIN jugadores cap ON cap.equipo_id = se.equipo_id
             WHERE se.id = ? AND cap.usuario_id = ? AND cap.es_capitan = 1
               AND se.estado = 'pendiente'
             FOR UPDATE"
        );
        $consulta->execute([$solicitudId, $capitanId]);
        $solicitud = $consulta->fetch();
        if (!$solicitud) {
            throw new InvalidArgumentException('La solicitud ya fue respondida o no pertenece a tu equipo.');
        }

        if ($aprobar) {
            $perfilExistente = $pdo->prepare('SELECT usuario_id FROM jugadores WHERE usuario_id = ? FOR UPDATE');
            $perfilExistente->execute([(int) $solicitud['usuario_id']]);
            if ($perfilExistente->fetchColumn() !== false) {
                throw new InvalidArgumentException('El estudiante ya pertenece a otro equipo; debe salir de ese equipo antes de ser aceptado.');
            }
            $guardar = $pdo->prepare(
                'INSERT INTO jugadores (usuario_id, equipo_id, posicion, es_capitan) VALUES (?, ?, ?, 0)'
            );
            $guardar->execute([(int) $solicitud['usuario_id'], (int) $solicitud['equipo_id'], $solicitud['posicion']]);
            $limpiarPendientes = $pdo->prepare(
                "UPDATE solicitudes_equipo SET estado = 'rechazada'
                 WHERE usuario_id = ? AND estado = 'pendiente' AND id <> ?"
            );
            $limpiarPendientes->execute([(int) $solicitud['usuario_id'], $solicitudId]);
        }

        $estado = $aprobar ? 'aprobada' : 'rechazada';
        $actualizar = $pdo->prepare('UPDATE solicitudes_equipo SET estado = ? WHERE id = ?');
        $actualizar->execute([$estado, $solicitudId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function retirarJugadorEquipo(PDO $pdo, int $capitanId, int $jugadorId): void
{
    if ($capitanId === $jugadorId) {
        throw new InvalidArgumentException('No puedes retirarte a ti mismo como capitán.');
    }
    $consulta = $pdo->prepare(
        'DELETE jugador FROM jugadores jugador
         INNER JOIN jugadores cap ON cap.equipo_id = jugador.equipo_id
         WHERE cap.usuario_id = ? AND cap.es_capitan = 1
           AND jugador.usuario_id = ? AND jugador.es_capitan = 0'
    );
    $consulta->execute([$capitanId, $jugadorId]);
    if ($consulta->rowCount() === 0) {
        throw new InvalidArgumentException('Solo puedes retirar jugadores que pertenezcan a tu equipo.');
    }
}

function listarEstudiantes(PDO $pdo): array
{
    asegurarTablaTarjetas($pdo);
    return $pdo->query(
        "SELECT u.id, u.nombre, u.apellido, u.tipo_documento, u.numero_documento, u.genero, u.correo,
                u.creado_en, e.nombre AS equipo, e.deporte, pe.posicion, pe.es_capitan,
                COALESCE(t.amarillas, 0) AS tarjetas_amarillas, COALESCE(t.rojas, 0) AS tarjetas_rojas
         FROM usuarios u
         LEFT JOIN jugadores pe ON pe.usuario_id = u.id
         LEFT JOIN equipos e ON e.id = pe.equipo_id
         LEFT JOIN tarjetas_jugador t ON t.usuario_id = u.id
         WHERE u.rol = 'estudiante'
         ORDER BY u.apellido, u.nombre"
    )->fetchAll();
}

function listarJugadores(PDO $pdo): array
{
    return $pdo->query(
        'SELECT u.id AS jugador_id, u.nombre, u.apellido, e.nombre AS equipo, e.deporte,
                j.posicion, j.es_capitan
         FROM jugadores j
         INNER JOIN usuarios u ON u.id = j.usuario_id
         INNER JOIN equipos e ON e.id = j.equipo_id
         WHERE u.rol = \'estudiante\'
         ORDER BY u.apellido, u.nombre'
    )->fetchAll();
}

function asegurarTablaTarjetas(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tarjetas_jugador (
            usuario_id INT PRIMARY KEY,
            amarillas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            rojas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT tarjeta_perfil_fk
                FOREIGN KEY (usuario_id) REFERENCES jugadores(usuario_id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
}

function listarTarjetasJugadores(PDO $pdo): array
{
    asegurarTablaTarjetas($pdo);
    return $pdo->query(
        'SELECT pe.equipo_id, u.nombre, u.apellido, t.amarillas, t.rojas
         FROM tarjetas_jugador t
         INNER JOIN jugadores pe ON pe.usuario_id = t.usuario_id
         INNER JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.amarillas > 0 OR t.rojas > 0
         ORDER BY u.apellido, u.nombre'
    )->fetchAll();
}

function guardarTarjetasJugadores(PDO $pdo, array $tarjetas): void
{
    asegurarTablaTarjetas($pdo);
    $pdo->beginTransaction();
    try {
        $perfiles = $pdo->query(
            "SELECT pe.usuario_id FROM jugadores pe
             INNER JOIN usuarios u ON u.id = pe.usuario_id
             WHERE u.rol = 'estudiante'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $idsValidos = array_map('intval', $perfiles);
        $guardar = $pdo->prepare(
            'INSERT INTO tarjetas_jugador (usuario_id, amarillas, rojas) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE amarillas = VALUES(amarillas), rojas = VALUES(rojas)'
        );

        foreach ($tarjetas as $usuarioId => $datos) {
            if (!is_array($datos) || !in_array((int) $usuarioId, $idsValidos, true)) {
                throw new InvalidArgumentException('Solo puedes modificar las tarjetas de estudiantes con equipo.');
            }
            $guardar->execute([(int) $usuarioId, (int) $datos['amarillas'], (int) $datos['rojas']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function obtenerEstudiante(PDO $pdo, int $id): ?array
{
    $consulta = $pdo->prepare(
        "SELECT id, nombre, apellido, tipo_documento, numero_documento, genero, correo
         FROM usuarios WHERE id = ? AND rol = 'estudiante' LIMIT 1"
    );
    $consulta->execute([$id]);
    $estudiante = $consulta->fetch();
    return $estudiante ?: null;
}

function actualizarEstudiante(PDO $pdo, int $id, array $datos): void
{
    $consulta = $pdo->prepare(
        "UPDATE usuarios SET nombre = ?, apellido = ?, tipo_documento = ?, numero_documento = ?,
         genero = ?, correo = ? WHERE id = ? AND rol = 'estudiante'"
    );
    $consulta->execute([
        $datos['nombre'], $datos['apellido'], $datos['tipo_documento'],
        $datos['numero_documento'], $datos['genero'], $datos['correo'], $id,
    ]);
}

function eliminarEstudiante(PDO $pdo, int $id): bool
{
    $pdo->beginTransaction();
    try {
        $buscar = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'estudiante' FOR UPDATE");
        $buscar->execute([$id]);
        if ($buscar->fetchColumn() === false) {
            $pdo->commit();
            return false;
        }

        $eliminarToken = $pdo->prepare('DELETE FROM tokens_recuperacion_contrasena WHERE usuario_id = ?');
        $eliminarToken->execute([$id]);

        $eliminarEstudiante = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'estudiante'");
        $eliminarEstudiante->execute([$id]);
        $pdo->commit();
        return $eliminarEstudiante->rowCount() > 0;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function listarEquipos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT e.id, e.nombre, e.deporte, e.cantidad_integrantes, COUNT(pe.usuario_id) AS estudiantes
         FROM equipos e
         LEFT JOIN jugadores pe ON pe.equipo_id = e.id
         GROUP BY e.id, e.nombre, e.deporte, e.cantidad_integrantes
         HAVING COUNT(pe.usuario_id) > 0
         ORDER BY e.deporte, e.nombre'
    )->fetchAll();
}

function listarIntegrantesEquipos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT j.equipo_id, u.nombre, u.apellido, j.posicion, j.es_capitan
         FROM jugadores j
         INNER JOIN usuarios u ON u.id = j.usuario_id
         ORDER BY j.equipo_id, j.es_capitan DESC, u.apellido, u.nombre'
    )->fetchAll();
}

function listarPartidos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT p.id, p.deporte, p.fecha, p.lugar,
            p.equipo_local_id, p.equipo_visitante_id,
            p.goles_local, p.goles_visitante,
            local.nombre AS equipo_local, visitante.nombre AS equipo_visitante
         FROM partidos p
         INNER JOIN equipos local ON local.id = p.equipo_local_id
         INNER JOIN equipos visitante ON visitante.id = p.equipo_visitante_id
         WHERE p.calendario_id = (
             SELECT calendario_id FROM ajustes_publicacion WHERE id = 1
         )
         ORDER BY p.deporte, p.fecha, p.id'
    )->fetchAll();
}

function listarCalendarios(PDO $pdo): array
{
    return $pdo->query(
        "SELECT c.id, c.creado_en, c.enfrentamientos,
                c.fecha_inicio_programada, c.fecha_fin_programada,
                COUNT(p.id) AS partidos,
                COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Sistema') AS generado_por,
                c.id = (SELECT calendario_id FROM ajustes_publicacion WHERE id = 1) AS actual
         FROM calendario c
         LEFT JOIN usuarios u ON u.id = c.generado_por
         LEFT JOIN partidos p ON p.calendario_id = c.id
         GROUP BY c.id, c.creado_en, u.nombre, u.apellido
         ORDER BY c.id DESC"
    )->fetchAll();
}

function generarCalendarioAleatorio(PDO $pdo, int $docenteId): int
{
    $equiposPorDeporte = $pdo->query(
        'SELECT e.id, e.deporte, COUNT(pe.usuario_id) AS total_integrantes
         FROM equipos e
         INNER JOIN jugadores pe ON pe.equipo_id = e.id
         GROUP BY e.id, e.deporte
         HAVING COUNT(pe.usuario_id) >= 5
         ORDER BY e.deporte'
    )->fetchAll();
    $grupos = [];
    foreach ($equiposPorDeporte as $equipo) {
        $grupos[$equipo['deporte']][] = (int) $equipo['id'];
    }
    $grupos = array_filter($grupos, static fn(array $ids): bool => count($ids) >= 6);
    if (!$grupos) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $crearCalendario = $pdo->prepare('INSERT INTO calendario (generado_por) VALUES (?)');
        $crearCalendario->execute([$docenteId]);
        $calendarioId = (int) $pdo->lastInsertId();
        $insertar = $pdo->prepare(
            'INSERT INTO partidos (calendario_id, deporte, equipo_local_id, equipo_visitante_id, fecha)
             VALUES (?, ?, ?, ?, ?)'
        );
        $total = 0;
        $inicio = new DateTimeImmutable('next Monday 09:00');

        foreach ($grupos as $deporte => $ids) {
            shuffle($ids);
            if (count($ids) % 2 !== 0) {
                $ids[] = null;
            }
            $rotacion = $ids;
            $cantidad = count($rotacion);
            $crucesGenerados = [];

            for ($ronda = 0; $ronda < $cantidad - 1; $ronda++) {
                for ($i = 0; $i < $cantidad / 2; $i++) {
                    $local = $rotacion[$i];
                    $visitante = $rotacion[$cantidad - 1 - $i];
                    if ($local === null || $visitante === null) {
                        continue;
                    }
                    $claveCruce = min($local, $visitante) . ':' . max($local, $visitante);
                    if (isset($crucesGenerados[$claveCruce])) {
                        continue;
                    }
                    $crucesGenerados[$claveCruce] = true;
                    if (($ronda + $i) % 2 === 1) {
                        [$local, $visitante] = [$visitante, $local];
                    }
                    $fecha = $inicio->modify('+' . ($ronda * 7 + $i) . ' days')->format('Y-m-d H:i:s');
                    $insertar->execute([$calendarioId, $deporte, $local, $visitante, $fecha]);
                    $total++;
                }
                $fijo = $rotacion[0];
                $resto = array_slice($rotacion, 1);
                array_unshift($resto, array_pop($resto));
                $rotacion = array_merge([$fijo], $resto);
            }
        }

        $actualizarCalendario = $pdo->prepare(
            'UPDATE ajustes_publicacion SET calendario_id = ?, calendario_publicado = 0 WHERE id = 1'
        );
        $actualizarCalendario->execute([$calendarioId]);
        actualizarFechasProgramadasCalendario($pdo, $calendarioId);
        actualizarEnfrentamientosCalendario($pdo, $calendarioId);
        $pdo->commit();
        return $total;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function guardarPartidos(PDO $pdo, array $partidos): void
{
    $pdo->beginTransaction();
    try {
        $consultaActuales = $pdo->query(
            'SELECT p.id, p.deporte, p.goles_local, p.goles_visitante
             FROM partidos p
             WHERE p.calendario_id = (SELECT calendario_id FROM ajustes_publicacion WHERE id = 1)'
        );
        $partidosActuales = [];
        foreach ($consultaActuales->fetchAll() as $partidoActual) {
            $partidosActuales[(int) $partidoActual['id']] = $partidoActual;
        }
        $actualizar = $pdo->prepare(
            'UPDATE partidos SET fecha = ?, lugar = ?, goles_local = ?, goles_visitante = ?
             WHERE id = ? AND calendario_id = (
                SELECT calendario_id FROM ajustes_publicacion WHERE id = 1
            )'
        );
        foreach ($partidos as $id => $datos) {
            $partidoId = (int) $id;
            if (!is_array($datos) || !isset($partidosActuales[$partidoId])) {
                throw new InvalidArgumentException('El partido seleccionado no pertenece al calendario actual.');
            }
            $fechaEntrada = $datos['fecha'] ?? '';
            $lugar = $datos['lugar'] ?? null;
            if (!is_string($fechaEntrada) || !is_string($lugar)) {
                throw new InvalidArgumentException('Revisa la fecha y el lugar de cada partido.');
            }
            $marcadorLocal = $partidosActuales[$partidoId]['goles_local'];
            $marcadorVisitante = $partidosActuales[$partidoId]['goles_visitante'];
            if (!deporteEsVoleibol((string) $partidosActuales[$partidoId]['deporte'])) {
                foreach (['goles_local', 'goles_visitante'] as $campo) {
                    $entrada = $datos[$campo] ?? null;
                    if (!is_string($entrada) || !preg_match('/\A\d{1,5}\z/', $entrada) || (int) $entrada > 65535) {
                        throw new InvalidArgumentException('Los marcadores deben ser números enteros entre 0 y 65535.');
                    }
                    if ($campo === 'goles_local') {
                        $marcadorLocal = (int) $entrada;
                    } else {
                        $marcadorVisitante = (int) $entrada;
                    }
                }
            }
            $fecha = $fechaEntrada !== '' ? str_replace('T', ' ', $fechaEntrada) . ':00' : null;
            $actualizar->execute([$fecha, trim($lugar), $marcadorLocal, $marcadorVisitante, $partidoId]);
        }
        $calendarioId = $pdo->query(
            'SELECT calendario_id FROM ajustes_publicacion WHERE id = 1'
        )->fetchColumn();
        if ($calendarioId !== false && $calendarioId !== null) {
            actualizarFechasProgramadasCalendario($pdo, (int) $calendarioId);
            actualizarEnfrentamientosCalendario($pdo, (int) $calendarioId);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function actualizarFechasProgramadasCalendario(PDO $pdo, int $calendarioId): void
{
    $actualizar = $pdo->prepare(
        'UPDATE calendario
         SET fecha_inicio_programada = (
                 SELECT MIN(fecha) FROM partidos WHERE calendario_id = ?
             ),
             fecha_fin_programada = (
                 SELECT MAX(fecha) FROM partidos WHERE calendario_id = ?
             )
         WHERE id = ?'
    );
    $actualizar->execute([$calendarioId, $calendarioId, $calendarioId]);
}

function actualizarEnfrentamientosCalendario(PDO $pdo, int $calendarioId): void
{
    $buscarPartidos = $pdo->prepare(
        'SELECT p.equipo_local_id, p.equipo_visitante_id, local.nombre AS equipo_local,
                visitante.nombre AS equipo_visitante
         FROM partidos p
         INNER JOIN equipos local ON local.id = p.equipo_local_id
         INNER JOIN equipos visitante ON visitante.id = p.equipo_visitante_id
         WHERE p.calendario_id = ?
         ORDER BY p.fecha, p.id'
    );
    $buscarPartidos->execute([$calendarioId]);
    $partidos = $buscarPartidos->fetchAll();
    $cruces = [];
    $crucesUnicos = [];
    foreach ($partidos as $partido) {
        $localId = (int) $partido['equipo_local_id'];
        $visitanteId = (int) $partido['equipo_visitante_id'];
        $claveCruce = min($localId, $visitanteId) . ':' . max($localId, $visitanteId);
        if (isset($crucesUnicos[$claveCruce])) {
            continue;
        }
        $crucesUnicos[$claveCruce] = true;
        $cruces[] = $localId < $visitanteId
            ? $partido['equipo_local'] . ' vs. ' . $partido['equipo_visitante']
            : $partido['equipo_visitante'] . ' vs. ' . $partido['equipo_local'];
    }
    $actualizar = $pdo->prepare('UPDATE calendario SET enfrentamientos = ? WHERE id = ?');
    $actualizar->execute([$cruces ? implode("\n", $cruces) : null, $calendarioId]);
}

function listarPosiciones(PDO $pdo): array
{
    return $pdo->query(
        "SELECT e.id, e.nombre AS equipo, e.deporte,
                COALESCE(pos.ronda_1, 0) AS ronda_1,
                COALESCE(pos.ronda_2, 0) AS ronda_2,
                COALESCE(pos.ronda_3, 0) AS ronda_3,
                COALESCE(pos.ronda_1, 0) + COALESCE(pos.ronda_2, 0) + COALESCE(pos.ronda_3, 0) AS puntos_total,
                COALESCE(marcadores.marcador, 0) AS marcador
         FROM equipos e
         LEFT JOIN posiciones pos ON pos.equipo_id = e.id
         LEFT JOIN (
             SELECT resultados.equipo_id, SUM(resultados.marcador) AS marcador
             FROM (
                 SELECT equipo_local_id AS equipo_id, goles_local AS marcador
                 FROM partidos
                 WHERE calendario_id = (SELECT calendario_id FROM ajustes_publicacion WHERE id = 1)
                 UNION ALL
                 SELECT equipo_visitante_id AS equipo_id, goles_visitante AS marcador
                 FROM partidos
                 WHERE calendario_id = (SELECT calendario_id FROM ajustes_publicacion WHERE id = 1)
             ) resultados
             GROUP BY resultados.equipo_id
         ) marcadores ON marcadores.equipo_id = e.id
         WHERE EXISTS (SELECT 1 FROM jugadores pe WHERE pe.equipo_id = e.id)
         ORDER BY e.deporte,
             CASE WHEN e.deporte = 'Voleibol'
                 THEN COALESCE(pos.ronda_1, 0) + COALESCE(pos.ronda_2, 0) + COALESCE(pos.ronda_3, 0)
                 ELSE COALESCE(marcadores.marcador, 0)
             END DESC,
             e.nombre"
    )->fetchAll();
}

function deporteEsVoleibol(string $deporte): bool
{
    return mb_strtolower(trim($deporte), 'UTF-8') === 'voleibol';
}

function etiquetaMarcadorDeporte(string $deporte): string
{
    $deporte = strtr(mb_strtolower(trim($deporte), 'UTF-8'), [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
    ]);
    return in_array($deporte, ['futbol', 'futbol sala', 'futsal'], true) ? 'Goles' : 'Puntos';
}

function guardarPosiciones(PDO $pdo, array $filas): void
{
    $pdo->beginTransaction();
    try {
        $guardar = $pdo->prepare(
            'INSERT INTO posiciones (equipo_id, ronda_1, ronda_2, ronda_3) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                ronda_1 = VALUES(ronda_1),
                ronda_2 = VALUES(ronda_2),
                ronda_3 = VALUES(ronda_3)'
        );
        foreach ($filas as $equipoId => $puntos) {
            $guardar->execute([
                (int) $equipoId,
                $puntos['ronda_1'],
                $puntos['ronda_2'],
                $puntos['ronda_3'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function obtenerAjustesPublicacion(PDO $pdo): array
{
    return $pdo->query(
        'SELECT calendario_id, calendario_publicado, posiciones_publicadas
         FROM ajustes_publicacion WHERE id = 1'
    )->fetch();
}

function actualizarPublicacion(PDO $pdo, string $columna, bool $publicado, int $docenteId): void
{
    $columnasPermitidas = ['calendario_publicado', 'posiciones_publicadas'];
    if (!in_array($columna, $columnasPermitidas, true)) {
        throw new InvalidArgumentException('Opción de publicación no válida.');
    }
    $consulta = $pdo->prepare("UPDATE ajustes_publicacion SET {$columna} = ?, actualizado_por = ? WHERE id = 1");
    $consulta->execute([$publicado ? 1 : 0, $docenteId]);
}
