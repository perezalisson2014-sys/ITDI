<?php
declare(strict_types=1);

$host = getenv('ITDI_DB_HOST') ?: 'sql301.infinityfree.com';
$nombreBaseDatos = getenv('ITDI_DB_NAME') ?: 'if0_43047451_itdi';
$usuarioBaseDatos = getenv('ITDI_DB_USER') ?: 'if0_43047451';
$contrasenaBaseDatos = getenv('ITDI_DB_PASSWORD') ?: 'proyectoITDI01';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$nombreBaseDatos};charset=utf8mb4",
        $usuarioBaseDatos,
        $contrasenaBaseDatos,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS calendario (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            generado_por INT UNSIGNED NULL,
            enfrentamientos TEXT NULL,
            fecha_inicio_programada DATETIME NULL,
            fecha_fin_programada DATETIME NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ajustes_publicacion (
            id TINYINT UNSIGNED PRIMARY KEY,
            calendario_id BIGINT UNSIGNED NULL,
            calendario_publicado TINYINT(1) NOT NULL DEFAULT 0,
            posiciones_publicadas TINYINT(1) NOT NULL DEFAULT 0,
            actualizado_por INT UNSIGNED NULL,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    $pdo->exec('INSERT IGNORE INTO ajustes_publicacion (id) VALUES (1)');

    $columns = [
        ['calendario' => 'calendario', 'columna' => 'generado_por', 'sentencia' => 'ALTER TABLE calendario ADD COLUMN generado_por INT UNSIGNED NULL AFTER id'],
        ['calendario' => 'calendario', 'columna' => 'enfrentamientos', 'sentencia' => 'ALTER TABLE calendario ADD COLUMN enfrentamientos TEXT NULL AFTER generado_por'],
        ['calendario' => 'calendario', 'columna' => 'fecha_inicio_programada', 'sentencia' => 'ALTER TABLE calendario ADD COLUMN fecha_inicio_programada DATETIME NULL AFTER generado_por'],
        ['calendario' => 'calendario', 'columna' => 'fecha_fin_programada', 'sentencia' => 'ALTER TABLE calendario ADD COLUMN fecha_fin_programada DATETIME NULL AFTER fecha_inicio_programada'],
        ['calendario' => 'equipos', 'columna' => 'cantidad_integrantes', 'sentencia' => 'ALTER TABLE equipos ADD COLUMN cantidad_integrantes INT NOT NULL DEFAULT 10 AFTER deporte'],
        ['calendario' => 'solicitudes_creacion_equipo', 'columna' => 'cantidad_integrantes', 'sentencia' => 'ALTER TABLE solicitudes_creacion_equipo ADD COLUMN cantidad_integrantes INT NOT NULL DEFAULT 10 AFTER posicion'],
        ['calendario' => 'partidos', 'columna' => 'calendario_id', 'sentencia' => 'ALTER TABLE partidos ADD COLUMN calendario_id BIGINT UNSIGNED NULL AFTER id'],
        ['calendario' => 'partidos', 'columna' => 'fecha', 'sentencia' => 'ALTER TABLE partidos ADD COLUMN fecha DATETIME NULL AFTER equipo_visitante_id'],
        ['calendario' => 'partidos', 'columna' => 'lugar', 'sentencia' => 'ALTER TABLE partidos ADD COLUMN lugar VARCHAR(120) NOT NULL DEFAULT "" AFTER fecha'],
        ['calendario' => 'partidos', 'columna' => 'goles_local', 'sentencia' => 'ALTER TABLE partidos ADD COLUMN goles_local SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER lugar'],
        ['calendario' => 'partidos', 'columna' => 'goles_visitante', 'sentencia' => 'ALTER TABLE partidos ADD COLUMN goles_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER goles_local'],
        ['calendario' => 'ajustes_publicacion', 'columna' => 'calendario_id', 'sentencia' => 'ALTER TABLE ajustes_publicacion ADD COLUMN calendario_id BIGINT UNSIGNED NULL AFTER id'],
        ['calendario' => 'ajustes_publicacion', 'columna' => 'actualizado_por', 'sentencia' => 'ALTER TABLE ajustes_publicacion ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER posiciones_publicadas'],
        ['calendario' => 'ajustes_publicacion', 'columna' => 'actualizado_en', 'sentencia' => 'ALTER TABLE ajustes_publicacion ADD COLUMN actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER actualizado_por'],
    ];

    foreach ($columns as $columnaConfig) {
        $tabla = $columnaConfig['calendario'];
        $nombreColumna = $columnaConfig['columna'];
        $sentencia = $columnaConfig['sentencia'];
        $existe = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = '{$nombreColumna}'"
        )->fetchColumn();
        if ((int) $existe === 0) {
            $pdo->exec($sentencia);
        }
    }

    $tipoJugadores = $pdo->query(
        "SELECT TABLE_TYPE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jugadores'"
    )->fetchColumn();
    if ($tipoJugadores === 'VIEW') {
        $pdo->exec('DROP VIEW jugadores');
    }

    $tipoEquipoId = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'id'"
    )->fetchColumn();
    if (!is_string($tipoEquipoId) || !preg_match('/\Aint(?:\(\d+\))?(?: unsigned)?\z/i', $tipoEquipoId)) {
        throw new RuntimeException('No se pudo determinar el tipo de la clave de equipos.id.');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS jugadores (
            usuario_id INT UNSIGNED PRIMARY KEY,
            equipo_id {$tipoEquipoId} NOT NULL,
            posicion VARCHAR(60) NOT NULL,
            es_capitan TINYINT(1) NOT NULL DEFAULT 0,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT jugador_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT jugador_equipo_fk FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB"
    );

    $tipoPerfiles = $pdo->query(
        "SELECT TABLE_TYPE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_estudiante'"
    )->fetchColumn();
    if ($tipoPerfiles === 'BASE TABLE') {
        $pdo->exec(
            'INSERT IGNORE INTO jugadores (usuario_id, equipo_id, posicion, es_capitan, actualizado_en)
             SELECT usuario_id, equipo_id, posicion, es_capitan, actualizado_en FROM perfiles_estudiante'
        );
    }

    $tipoTarjetas = $pdo->query(
        "SELECT TABLE_TYPE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tarjetas_jugador'"
    )->fetchColumn();
    if ($tipoTarjetas === 'BASE TABLE') {
        $buscarRelacionTarjetas = $pdo->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tarjetas_jugador'
               AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME = 'perfiles_estudiante'"
        );
        $relacionAnterior = $buscarRelacionTarjetas->fetchColumn();
        if ($relacionAnterior !== false) {
            if (!preg_match('/\A[a-zA-Z0-9_]+\z/', (string) $relacionAnterior)) {
                throw new RuntimeException('El nombre de la clave foránea de tarjetas no es válido.');
            }
            $pdo->exec('ALTER TABLE tarjetas_jugador DROP FOREIGN KEY `' . $relacionAnterior . '`');
        }

        $buscarRelacionJugadores = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tarjetas_jugador'
               AND COLUMN_NAME = 'usuario_id' AND REFERENCED_TABLE_NAME = 'jugadores'"
        );
        if ((int) $buscarRelacionJugadores->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE tarjetas_jugador
                 ADD CONSTRAINT tarjeta_perfil_fk FOREIGN KEY (usuario_id)
                 REFERENCES jugadores(usuario_id) ON DELETE CASCADE'
            );
        }
    }

    if ($tipoPerfiles === 'BASE TABLE') {
        $pdo->exec('DROP TABLE perfiles_estudiante');
    }

    $pdo->exec('UPDATE equipos SET cantidad_integrantes = 5 WHERE cantidad_integrantes < 5');
    $pdo->exec('UPDATE equipos SET cantidad_integrantes = 10 WHERE cantidad_integrantes > 10');
    $pdo->exec('UPDATE solicitudes_creacion_equipo SET cantidad_integrantes = 5 WHERE cantidad_integrantes < 5');
    $pdo->exec('UPDATE solicitudes_creacion_equipo SET cantidad_integrantes = 10 WHERE cantidad_integrantes > 10');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posiciones (
            equipo_id {$tipoEquipoId} PRIMARY KEY,
            ronda_1 BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ronda_2 BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ronda_3 BIGINT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT posicion_equipo_fk FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );
    foreach (['ronda_1', 'ronda_2', 'ronda_3'] as $columnaRonda) {
        $columnaExiste = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posiciones' AND COLUMN_NAME = ?"
        );
        $columnaExiste->execute([$columnaRonda]);
        if ((int) $columnaExiste->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE posiciones ADD COLUMN {$columnaRonda} BIGINT UNSIGNED NOT NULL DEFAULT 0");
        }
    }

    $rowCount = (int) $pdo->query('SELECT COUNT(*) FROM ajustes_publicacion WHERE id = 1')->fetchColumn();
    if ($rowCount === 0) {
        $pdo->exec('INSERT INTO ajustes_publicacion (id) VALUES (1)');
    }
} catch (PDOException $error) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Revisa config/conexionBDD.php y que MySQL esté iniciado.');
}