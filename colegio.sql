CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    tipo_documento VARCHAR(30) NOT NULL,
    numero_documento VARCHAR(40) NOT NULL UNIQUE,
    genero VARCHAR(40) NOT NULL,
    correo VARCHAR(190) NOT NULL UNIQUE,
    rol ENUM('estudiante', 'docente') NOT NULL DEFAULT 'estudiante',
    password_hash VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generado_por INT UNSIGNED NULL,
    enfrentamientos TEXT NULL,
    fecha_inicio_programada DATETIME NULL,
    fecha_fin_programada DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT calendario_usuario_fk FOREIGN KEY (generado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX calendario_creado_en (creado_en)
) ENGINE=InnoDB;

SET @columna_nombres_equipos_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'calendario'
      AND COLUMN_NAME = 'nombres_equipos'
);
SET @sql_eliminar_nombres_equipos_calendario := IF(
    @columna_nombres_equipos_calendario_existe > 0,
    'ALTER TABLE calendario DROP COLUMN nombres_equipos',
    'SELECT ''La columna calendario.nombres_equipos ya no existe'' AS resultado'
);
PREPARE sentencia_eliminar_nombres_equipos_calendario FROM @sql_eliminar_nombres_equipos_calendario;
EXECUTE sentencia_eliminar_nombres_equipos_calendario;
DEALLOCATE PREPARE sentencia_eliminar_nombres_equipos_calendario;

SET @columna_enfrentamientos_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'calendario'
      AND COLUMN_NAME = 'enfrentamientos'
);
SET @sql_agregar_enfrentamientos_calendario := IF(
    @columna_enfrentamientos_calendario_existe = 0,
    'ALTER TABLE calendario ADD COLUMN enfrentamientos TEXT NULL AFTER generado_por',
    'SELECT ''La columna calendario.enfrentamientos ya existe'' AS resultado'
);
PREPARE sentencia_enfrentamientos_calendario FROM @sql_agregar_enfrentamientos_calendario;
EXECUTE sentencia_enfrentamientos_calendario;
DEALLOCATE PREPARE sentencia_enfrentamientos_calendario;

SET @columna_fecha_inicio_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'calendario'
      AND COLUMN_NAME = 'fecha_inicio_programada'
);
SET @sql_agregar_fecha_inicio_calendario := IF(
    @columna_fecha_inicio_calendario_existe = 0,
    'ALTER TABLE calendario ADD COLUMN fecha_inicio_programada DATETIME NULL AFTER generado_por',
    'SELECT ''La columna calendario.fecha_inicio_programada ya existe'' AS resultado'
);
PREPARE sentencia_fecha_inicio_calendario FROM @sql_agregar_fecha_inicio_calendario;
EXECUTE sentencia_fecha_inicio_calendario;
DEALLOCATE PREPARE sentencia_fecha_inicio_calendario;

SET @columna_fecha_fin_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'calendario'
      AND COLUMN_NAME = 'fecha_fin_programada'
);
SET @sql_agregar_fecha_fin_calendario := IF(
    @columna_fecha_fin_calendario_existe = 0,
    'ALTER TABLE calendario ADD COLUMN fecha_fin_programada DATETIME NULL AFTER fecha_inicio_programada',
    'SELECT ''La columna calendario.fecha_fin_programada ya existe'' AS resultado'
);
PREPARE sentencia_fecha_fin_calendario FROM @sql_agregar_fecha_fin_calendario;
EXECUTE sentencia_fecha_fin_calendario;
DEALLOCATE PREPARE sentencia_fecha_fin_calendario;

CREATE TABLE IF NOT EXISTS tokens_recuperacion_contrasena (
    usuario_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_en DATETIME NOT NULL,
    CONSTRAINT token_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    deporte VARCHAR(60) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY equipo_nombre_deporte (nombre, deporte)
) ENGINE=InnoDB;

SET @jugadores_view_existe := (
    SELECT COUNT(*)
    FROM information_schema.VIEWS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jugadores'
);
SET @sql_eliminar_vista_jugadores := IF(
    @jugadores_view_existe > 0,
    'DROP VIEW jugadores',
    'SELECT ''La vista jugadores no existe'' AS resultado'
);
PREPARE sentencia_eliminar_vista_jugadores FROM @sql_eliminar_vista_jugadores;
EXECUTE sentencia_eliminar_vista_jugadores;
DEALLOCATE PREPARE sentencia_eliminar_vista_jugadores;

SET @tipo_equipo_jugadores := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'id'
);
SET @sql_crear_tabla_jugadores := CONCAT(
    'CREATE TABLE IF NOT EXISTS jugadores (
        usuario_id INT UNSIGNED PRIMARY KEY,
        equipo_id ', @tipo_equipo_jugadores, ' NOT NULL,
        posicion VARCHAR(60) NOT NULL,
        es_capitan TINYINT(1) NOT NULL DEFAULT 0,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT jugador_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        CONSTRAINT jugador_equipo_fk FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB'
);
PREPARE sentencia_crear_tabla_jugadores FROM @sql_crear_tabla_jugadores;
EXECUTE sentencia_crear_tabla_jugadores;
DEALLOCATE PREPARE sentencia_crear_tabla_jugadores;

SET @perfiles_estudiante_tabla_existe := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'perfiles_estudiante'
      AND TABLE_TYPE = 'BASE TABLE'
);
SET @sql_migrar_perfiles_estudiante := IF(
    @perfiles_estudiante_tabla_existe > 0,
    'INSERT IGNORE INTO jugadores (usuario_id, equipo_id, posicion, es_capitan, actualizado_en)
     SELECT usuario_id, equipo_id, posicion, es_capitan, actualizado_en FROM perfiles_estudiante',
    'SELECT ''No hay perfiles anteriores para migrar'' AS resultado'
);
PREPARE sentencia_migrar_perfiles_estudiante FROM @sql_migrar_perfiles_estudiante;
EXECUTE sentencia_migrar_perfiles_estudiante;
DEALLOCATE PREPARE sentencia_migrar_perfiles_estudiante;

CREATE TABLE IF NOT EXISTS tarjetas_jugador (
    usuario_id INT UNSIGNED PRIMARY KEY,
    amarillas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    rojas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT tarjeta_perfil_fk FOREIGN KEY (usuario_id) REFERENCES jugadores(usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @tarjeta_fk_perfiles_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tarjetas_jugador'
      AND COLUMN_NAME = 'usuario_id'
      AND REFERENCED_TABLE_NAME = 'perfiles_estudiante'
);
SET @sql_eliminar_tarjeta_fk_perfiles := IF(
    @tarjeta_fk_perfiles_existe > 0,
    'ALTER TABLE tarjetas_jugador DROP FOREIGN KEY tarjeta_perfil_fk',
    'SELECT ''La relación de tarjetas con perfiles antiguos no existe'' AS resultado'
);
PREPARE sentencia_eliminar_tarjeta_fk_perfiles FROM @sql_eliminar_tarjeta_fk_perfiles;
EXECUTE sentencia_eliminar_tarjeta_fk_perfiles;
DEALLOCATE PREPARE sentencia_eliminar_tarjeta_fk_perfiles;

SET @tarjeta_fk_jugadores_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tarjetas_jugador'
      AND COLUMN_NAME = 'usuario_id'
      AND REFERENCED_TABLE_NAME = 'jugadores'
);
SET @sql_agregar_tarjeta_fk_jugadores := IF(
    @tarjeta_fk_jugadores_existe = 0,
    'ALTER TABLE tarjetas_jugador
     ADD CONSTRAINT tarjeta_perfil_fk
     FOREIGN KEY (usuario_id) REFERENCES jugadores(usuario_id) ON DELETE CASCADE',
    'SELECT ''La relación de tarjetas con jugadores ya existe'' AS resultado'
);
PREPARE sentencia_agregar_tarjeta_fk_jugadores FROM @sql_agregar_tarjeta_fk_jugadores;
EXECUTE sentencia_agregar_tarjeta_fk_jugadores;
DEALLOCATE PREPARE sentencia_agregar_tarjeta_fk_jugadores;

SET @sql_eliminar_tabla_perfiles_estudiante := IF(
    @perfiles_estudiante_tabla_existe > 0,
    'DROP TABLE perfiles_estudiante',
    'SELECT ''No hay tabla antigua de perfiles para eliminar'' AS resultado'
);
PREPARE sentencia_eliminar_tabla_perfiles_estudiante FROM @sql_eliminar_tabla_perfiles_estudiante;
EXECUTE sentencia_eliminar_tabla_perfiles_estudiante;
DEALLOCATE PREPARE sentencia_eliminar_tabla_perfiles_estudiante;

CREATE TABLE IF NOT EXISTS solicitudes_equipo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    equipo_id INT NOT NULL,
    posicion VARCHAR(60) NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT solicitud_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT solicitud_equipo_fk FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    INDEX solicitud_pendiente (usuario_id, equipo_id, estado),
    INDEX solicitud_equipo_estado (equipo_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitudes_creacion_equipo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nombre_equipo VARCHAR(100) NOT NULL,
    deporte VARCHAR(60) NOT NULL,
    posicion VARCHAR(60) NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT solicitud_creacion_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX solicitud_creacion_estado (estado, creado_en),
    INDEX solicitud_creacion_equipo (nombre_equipo, deporte, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS partidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calendario_id BIGINT UNSIGNED NOT NULL,
    deporte VARCHAR(60) NOT NULL,
    equipo_local_id INT NOT NULL,
    equipo_visitante_id INT NOT NULL,
    fecha DATETIME NULL,
    lugar VARCHAR(120) NOT NULL DEFAULT '',
    goles_local SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    goles_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT partido_local_fk FOREIGN KEY (equipo_local_id) REFERENCES equipos(id) ON DELETE CASCADE,
    CONSTRAINT partido_visitante_fk FOREIGN KEY (equipo_visitante_id) REFERENCES equipos(id) ON DELETE CASCADE,
    CONSTRAINT partido_calendario_fk FOREIGN KEY (calendario_id) REFERENCES calendario(id) ON DELETE CASCADE,
    CONSTRAINT partido_equipos_distintos CHECK (equipo_local_id <> equipo_visitante_id),
    INDEX partido_deporte_fecha (deporte, fecha),
    INDEX partido_calendario (calendario_id)
) ENGINE=InnoDB;

SET @columna_goles_local_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partidos' AND COLUMN_NAME = 'goles_local'
);
SET @sql_agregar_goles_local := IF(
    @columna_goles_local_existe = 0,
    'ALTER TABLE partidos ADD COLUMN goles_local SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER lugar',
    'SELECT ''La columna partidos.goles_local ya existe'' AS resultado'
);
PREPARE sentencia_agregar_goles_local FROM @sql_agregar_goles_local;
EXECUTE sentencia_agregar_goles_local;
DEALLOCATE PREPARE sentencia_agregar_goles_local;

SET @columna_goles_visitante_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partidos' AND COLUMN_NAME = 'goles_visitante'
);
SET @sql_agregar_goles_visitante := IF(
    @columna_goles_visitante_existe = 0,
    'ALTER TABLE partidos ADD COLUMN goles_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER goles_local',
    'SELECT ''La columna partidos.goles_visitante ya existe'' AS resultado'
);
PREPARE sentencia_agregar_goles_visitante FROM @sql_agregar_goles_visitante;
EXECUTE sentencia_agregar_goles_visitante;
DEALLOCATE PREPARE sentencia_agregar_goles_visitante;

-- Conserva los partidos existentes agrupándolos como un primer calendario.
SET @columna_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partidos'
      AND COLUMN_NAME = 'calendario_id'
);
SET @sql_agregar_calendario_partido := IF(
    @columna_calendario_existe = 0,
    'ALTER TABLE partidos ADD COLUMN calendario_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT ''La columna partidos.calendario_id ya existe'' AS resultado'
);
PREPARE sentencia_calendario_partido FROM @sql_agregar_calendario_partido;
EXECUTE sentencia_calendario_partido;
DEALLOCATE PREPARE sentencia_calendario_partido;

INSERT INTO calendario (generado_por)
SELECT NULL
WHERE EXISTS (SELECT 1 FROM partidos WHERE calendario_id IS NULL);
SET @calendario_legado_id := LAST_INSERT_ID();
UPDATE partidos
SET calendario_id = @calendario_legado_id
WHERE calendario_id IS NULL AND @calendario_legado_id > 0;

ALTER TABLE partidos MODIFY calendario_id BIGINT UNSIGNED NOT NULL;

UPDATE calendario c
SET c.enfrentamientos = (
        SELECT GROUP_CONCAT(
            DISTINCT CONCAT(
                CASE WHEN p.equipo_local_id < p.equipo_visitante_id THEN local.nombre ELSE visitante.nombre END,
                ' vs. ',
                CASE WHEN p.equipo_local_id < p.equipo_visitante_id THEN visitante.nombre ELSE local.nombre END
            )
            ORDER BY LEAST(p.equipo_local_id, p.equipo_visitante_id),
                     GREATEST(p.equipo_local_id, p.equipo_visitante_id)
            SEPARATOR 0x0A
        )
        FROM partidos p
        INNER JOIN equipos local ON local.id = p.equipo_local_id
        INNER JOIN equipos visitante ON visitante.id = p.equipo_visitante_id
        WHERE p.calendario_id = c.id
    ),
    c.fecha_inicio_programada = (
        SELECT MIN(p.fecha) FROM partidos p WHERE p.calendario_id = c.id
    ),
    c.fecha_fin_programada = (
        SELECT MAX(p.fecha) FROM partidos p WHERE p.calendario_id = c.id
    )
WHERE EXISTS (SELECT 1 FROM partidos p WHERE p.calendario_id = c.id);

SET @partido_calendario_fk_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partidos'
      AND COLUMN_NAME = 'calendario_id'
      AND REFERENCED_TABLE_NAME = 'calendario'
      AND REFERENCED_COLUMN_NAME = 'id'
);
SET @sql_agregar_partido_calendario_fk := IF(
    @partido_calendario_fk_existe = 0,
    'ALTER TABLE partidos
     ADD CONSTRAINT partido_calendario_fk
     FOREIGN KEY (calendario_id) REFERENCES calendario(id) ON DELETE CASCADE',
    'SELECT ''La relación partidos → calendario ya existe'' AS resultado'
);
PREPARE sentencia_partido_calendario_fk FROM @sql_agregar_partido_calendario_fk;
EXECUTE sentencia_partido_calendario_fk;
DEALLOCATE PREPARE sentencia_partido_calendario_fk;

SET @indice_partido_calendario_existe := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partidos'
      AND INDEX_NAME = 'partido_calendario'
);
SET @sql_agregar_indice_partido_calendario := IF(
    @indice_partido_calendario_existe = 0,
    'CREATE INDEX partido_calendario ON partidos (calendario_id)',
    'SELECT ''El índice partido_calendario ya existe'' AS resultado'
);
PREPARE sentencia_indice_partido_calendario FROM @sql_agregar_indice_partido_calendario;
EXECUTE sentencia_indice_partido_calendario;
DEALLOCATE PREPARE sentencia_indice_partido_calendario;

CREATE TABLE IF NOT EXISTS posiciones (
    equipo_id INT PRIMARY KEY,
    goles_favor SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ronda_1 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ronda_2 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ronda_3 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT posicion_equipo_fk FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @columna_ronda_1_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posiciones' AND COLUMN_NAME = 'ronda_1'
);
SET @sql_agregar_ronda_1 := IF(
    @columna_ronda_1_existe = 0,
    'ALTER TABLE posiciones ADD COLUMN ronda_1 BIGINT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT ''La columna posiciones.ronda_1 ya existe'' AS resultado'
);
PREPARE sentencia_agregar_ronda_1 FROM @sql_agregar_ronda_1;
EXECUTE sentencia_agregar_ronda_1;
DEALLOCATE PREPARE sentencia_agregar_ronda_1;

SET @columna_ronda_2_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posiciones' AND COLUMN_NAME = 'ronda_2'
);
SET @sql_agregar_ronda_2 := IF(
    @columna_ronda_2_existe = 0,
    'ALTER TABLE posiciones ADD COLUMN ronda_2 BIGINT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT ''La columna posiciones.ronda_2 ya existe'' AS resultado'
);
PREPARE sentencia_agregar_ronda_2 FROM @sql_agregar_ronda_2;
EXECUTE sentencia_agregar_ronda_2;
DEALLOCATE PREPARE sentencia_agregar_ronda_2;

SET @columna_ronda_3_existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posiciones' AND COLUMN_NAME = 'ronda_3'
);
SET @sql_agregar_ronda_3 := IF(
    @columna_ronda_3_existe = 0,
    'ALTER TABLE posiciones ADD COLUMN ronda_3 BIGINT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT ''La columna posiciones.ronda_3 ya existe'' AS resultado'
);
PREPARE sentencia_agregar_ronda_3 FROM @sql_agregar_ronda_3;
EXECUTE sentencia_agregar_ronda_3;
DEALLOCATE PREPARE sentencia_agregar_ronda_3;

CREATE TABLE IF NOT EXISTS ajustes_publicacion (
    id TINYINT UNSIGNED PRIMARY KEY,
    calendario_id BIGINT UNSIGNED NULL,
    calendario_publicado TINYINT(1) NOT NULL DEFAULT 0,
    posiciones_publicadas TINYINT(1) NOT NULL DEFAULT 0,
    actualizado_por INT UNSIGNED NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT ajustes_publicacion_calendario_fk
        FOREIGN KEY (calendario_id) REFERENCES calendario(id) ON DELETE SET NULL,
    CONSTRAINT ajustes_publicacion_usuario_fk
        FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO ajustes_publicacion (id) VALUES (1);

-- Mantiene seleccionado el calendario existente después de actualizar el esquema.
SET @columna_calendario_ajustes_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes_publicacion'
      AND COLUMN_NAME = 'calendario_id'
);
SET @sql_agregar_calendario_ajustes := IF(
    @columna_calendario_ajustes_existe = 0,
    'ALTER TABLE ajustes_publicacion ADD COLUMN calendario_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT ''La columna ajustes_publicacion.calendario_id ya existe'' AS resultado'
);
PREPARE sentencia_calendario_ajustes FROM @sql_agregar_calendario_ajustes;
EXECUTE sentencia_calendario_ajustes;
DEALLOCATE PREPARE sentencia_calendario_ajustes;

UPDATE ajustes_publicacion
SET calendario_id = (SELECT MAX(p.calendario_id) FROM partidos p)
WHERE id = 1
  AND calendario_id IS NULL
  AND EXISTS (SELECT 1 FROM partidos);

SET @ajustes_calendario_fk_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes_publicacion'
      AND COLUMN_NAME = 'calendario_id'
      AND REFERENCED_TABLE_NAME = 'calendario'
      AND REFERENCED_COLUMN_NAME = 'id'
);
SET @sql_agregar_ajustes_calendario_fk := IF(
    @ajustes_calendario_fk_existe = 0,
    'ALTER TABLE ajustes_publicacion
     ADD CONSTRAINT ajustes_publicacion_calendario_fk
     FOREIGN KEY (calendario_id) REFERENCES calendario(id) ON DELETE SET NULL',
    'SELECT ''La relación ajustes_publicacion → calendario ya existe'' AS resultado'
);
PREPARE sentencia_ajustes_calendario_fk FROM @sql_agregar_ajustes_calendario_fk;
EXECUTE sentencia_ajustes_calendario_fk;
DEALLOCATE PREPARE sentencia_ajustes_calendario_fk;

-- Añade el docente responsable a instalaciones anteriores sin borrar ajustes.
SET @columna_actualizado_por_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes_publicacion'
      AND COLUMN_NAME = 'actualizado_por'
);
SET @sql_agregar_actualizado_por := IF(
    @columna_actualizado_por_existe = 0,
    'ALTER TABLE ajustes_publicacion ADD COLUMN actualizado_por INT UNSIGNED NULL AFTER posiciones_publicadas',
    'SELECT ''La columna actualizado_por ya existe'' AS resultado'
);
PREPARE sentencia_actualizado_por FROM @sql_agregar_actualizado_por;
EXECUTE sentencia_actualizado_por;
DEALLOCATE PREPARE sentencia_actualizado_por;

SET @ajustes_usuario_fk_existe := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes_publicacion'
      AND COLUMN_NAME = 'actualizado_por'
      AND REFERENCED_TABLE_NAME = 'usuarios'
      AND REFERENCED_COLUMN_NAME = 'id'
);
SET @actualizado_por_unsigned := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ajustes_publicacion'
      AND COLUMN_NAME = 'actualizado_por'
      AND COLUMN_TYPE LIKE '%unsigned%'
);
SET @sql_corregir_tipo_actualizado_por := IF(
    @ajustes_usuario_fk_existe = 0 AND @actualizado_por_unsigned = 0,
    'ALTER TABLE ajustes_publicacion MODIFY COLUMN actualizado_por INT UNSIGNED NULL',
    'SELECT ''El tipo de actualizado_por ya es compatible'' AS resultado'
);
PREPARE sentencia_corregir_tipo_actualizado_por FROM @sql_corregir_tipo_actualizado_por;
EXECUTE sentencia_corregir_tipo_actualizado_por;
DEALLOCATE PREPARE sentencia_corregir_tipo_actualizado_por;

SET @sql_agregar_ajustes_usuario_fk := IF(
    @ajustes_usuario_fk_existe = 0,
    'ALTER TABLE ajustes_publicacion
     ADD CONSTRAINT ajustes_publicacion_usuario_fk
     FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL',
    'SELECT ''La relación ajustes_publicacion → usuarios ya existe'' AS resultado'
);
PREPARE sentencia_ajustes_usuario_fk FROM @sql_agregar_ajustes_usuario_fk;
EXECUTE sentencia_ajustes_usuario_fk;
DEALLOCATE PREPARE sentencia_ajustes_usuario_fk;
