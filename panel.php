<section class="panel-docente contenedor">
    <div class="cabecera-panel">
        <div><p class="sobretitulo sobretitulo-oscuro">ADMINISTRACIÓN DOCENTE</p><h1>Panel de torneos.</h1><p>Controla inscripciones, equipos, partidos y clasificación.</p></div>
        <span class="etiqueta-rol">DOCENTE</span>
    </div>

    <div class="metricas">
        <div class="metrica"><span>ESTUDIANTES REGISTRADOS</span><strong><?= count($estudiantes) ?></strong></div>
        <div class="metrica"><span>EQUIPOS FORMADOS</span><strong><?= count($equipos) ?></strong></div>
        <div class="metrica"><span>PARTIDOS GENERADOS</span><strong><?= count($partidosDocente) ?></strong></div>
        <div class="metrica"><span>DEPORTES</span><strong><?= count(array_unique(array_column($equipos, 'deporte'))) ?></strong></div>
    </div>

    <nav class="pestanas-panel" aria-label="Secciones del panel docente">
        <button class="pestana active" type="button" data-pestana="estudiantes">Estudiantes</button>
        <button class="pestana" type="button" data-pestana="jugadores">Jugadores</button>
        <button class="pestana" type="button" data-pestana="equipos">Equipos</button>
        <button class="pestana" type="button" data-pestana="solicitudes-creacion-equipo">Solicitudes</button>
        <button class="pestana" type="button" data-pestana="calendario">Calendario</button>
        <button class="pestana" type="button" data-pestana="posiciones">Posiciones</button>
    </nav>
    <?php $jugadoresConEquipo = array_filter($estudiantes, static fn(array $estudiante): bool => $estudiante['equipo'] !== null); ?>

    <section class="seccion-panel tab-panel active" id="estudiantes">
        <div class="titulo-bloque"><span class="numero-bloque">01</span><div><h2>Estudiantes registrados</h2><p>Información de inscripción y encuesta deportiva.</p></div><a class="boton boton-linea boton-pequeno" href="procesos/listarEstudiantes.php">Administrar registros</a></div>
        <?php if (!$estudiantes): ?><p class="estado-vacio">Todavía no hay estudiantes registrados.</p>
        <?php else: ?><div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Nombre</th><th>Documento</th><th>Correo</th><th>Equipo</th><th>Deporte</th><th>Posición</th><th>Capitán/a</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($estudiantes as $estudiante): ?><tr><td><strong><?= escapar($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></strong></td><td><?= escapar($estudiante['tipo_documento'] . ' · ' . $estudiante['numero_documento']) ?></td><td><?= escapar($estudiante['correo']) ?></td><td><?= escapar($estudiante['equipo'] ?? 'Encuesta pendiente') ?></td><td><?= escapar($estudiante['deporte'] ?? '—') ?></td><td><?= escapar($estudiante['posicion'] ?? '—') ?></td><td><?= $estudiante['es_capitan'] ? 'Sí' : 'No' ?></td><td><a class="boton boton-linea boton-pequeno" href="procesos/editarEstudiante.php?id=<?= (int) $estudiante['id'] ?>">Editar</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

    <section class="seccion-panel tab-panel" id="jugadores">
        <section class="seccion-panel" id="tarjetas">
            <div class="titulo-bloque"><span class="numero-bloque">07</span><div><h2>Tarjetas por jugador</h2><p>Solo los docentes pueden registrar o modificar tarjetas.</p></div></div>
            <?php if (!$jugadoresConEquipo): ?><p class="estado-vacio">No hay jugadores asignados a equipos.</p>
            <?php else: ?><form action="procesos/acciones.php" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Jugador</th><th>Equipo / deporte</th><th>Amarillas</th><th>Rojas</th></tr></thead><tbody>
                    <?php foreach ($jugadoresConEquipo as $jugador): ?><tr>
                        <td><?= escapar($jugador['nombre'] . ' ' . $jugador['apellido']) ?></td>
                        <td><?= escapar($jugador['equipo'] . ' · ' . $jugador['deporte']) ?></td>
                        <?php foreach (['amarillas' => 'tarjetas_amarillas', 'rojas' => 'tarjetas_rojas'] as $campo => $columna): ?>
                            <td><label class="solo-lectores" for="t-<?= (int) $jugador['id'] ?>-<?= escapar($campo) ?>"><?= escapar($campo) ?></label><input class="entrada-tarjeta" id="t-<?= (int) $jugador['id'] ?>-<?= escapar($campo) ?>" type="number" min="0" max="999" name="tarjetas[<?= (int) $jugador['id'] ?>][<?= escapar($campo) ?>]" value="<?= (int) $jugador[$columna] ?>" required></td>
                        <?php endforeach; ?>
                    </tr><?php endforeach; ?>
                </tbody></table></div><button class="boton boton-dorado" type="submit" name="accion" value="guardar_tarjetas">Guardar tarjetas</button>
            </form><?php endif; ?>
        </section>
    </section>

    <section class="seccion-panel tab-panel" id="equipos">
        <div class="titulo-bloque"><span class="numero-bloque">03</span><div><h2>Equipos por deporte</h2><p>Selecciona un equipo para consultar sus integrantes.</p></div></div>
        <?php if (!$equipos): ?><p class="estado-vacio">Los equipos aparecerán cuando los estudiantes completen la encuesta.</p>
        <?php else: ?>
            <?php $equiposPorDeporte = []; foreach ($equipos as $equipo) { $equiposPorDeporte[$equipo['deporte']][] = $equipo; } ?>
            <?php foreach ($equiposPorDeporte as $deporte => $equiposDeporte): ?>
                <h3 class="etiqueta-deporte"><?= escapar($deporte) ?></h3>
                <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Equipo</th><th>Integrantes</th></tr></thead><tbody>
                    <?php foreach ($equiposDeporte as $equipo): ?>
                        <tr>
                            <td>
                                <details class="equipo-integrantes">
                                    <summary><?= escapar($equipo['nombre']) ?></summary>
                                    <?php $integrantesEquipo = $integrantesPorEquipo[$equipo['id']] ?? []; ?>
                                    <?php if (!$integrantesEquipo): ?><p class="estado-vacio">Este equipo todavía no tiene integrantes.</p>
                                    <?php else: ?>
                                        <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Integrante</th><th>Posición</th><th>Capitán/a</th></tr></thead><tbody>
                                            <?php foreach ($integrantesEquipo as $integrante): ?>
                                                <tr><td><?= escapar($integrante['nombre'] . ' ' . $integrante['apellido']) ?></td><td><?= escapar($integrante['posicion']) ?></td><td><?= $integrante['es_capitan'] ? 'Sí' : 'No' ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody></table></div>
                                    <?php endif; ?>
                                </details>
                            </td>
                            <td><?= (int) $equipo['estudiantes'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="seccion-panel tab-panel" id="solicitudes-creacion-equipo">
        <div class="titulo-bloque"><span class="numero-bloque">04</span><div><h2>Solicitudes para crear equipos</h2><p>Aprueba la solicitud para formar el equipo y asignar a su capitán.</p></div></div>
        <?php if (empty($solicitudesCreacionEquipoPendientes ?? [])): ?><p class="estado-vacio">No hay solicitudes pendientes de creación de equipos.</p>
        <?php else: ?><div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Solicitante</th><th>Equipo</th><th>Deporte</th><th>Posición</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach (($solicitudesCreacionEquipoPendientes ?? []) as $solicitud): ?>
                <tr><td><strong><?= escapar($solicitud['nombre'] . ' ' . $solicitud['apellido']) ?></strong><br><small><?= escapar($solicitud['correo']) ?></small></td><td><?= escapar($solicitud['nombre_equipo']) ?></td><td><?= escapar($solicitud['deporte']) ?></td><td><?= escapar($solicitud['posicion']) ?></td><td><?= escapar(date('d/m/Y H:i', strtotime($solicitud['creado_en']))) ?></td>
                    <td><div class="acciones-tabla">
                        <?php foreach (['aprobar_creacion_equipo' => 'Aprobar', 'rechazar_creacion_equipo' => 'Rechazar'] as $accionCreacion => $textoAccion): ?>
                            <form action="procesos/acciones.php" method="post">
                                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                                <input type="hidden" name="solicitud_id" value="<?= (int) $solicitud['id'] ?>">
                                <button class="boton <?= $accionCreacion === 'aprobar_creacion_equipo' ? 'boton-dorado' : 'boton-linea' ?> boton-pequeno" type="submit" name="accion" value="<?= escapar($accionCreacion) ?>"><?= escapar($textoAccion) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

    <section class="seccion-panel tab-panel" id="calendario">
        <div class="titulo-bloque"><span class="numero-bloque">05</span><div><h2>Calendario de partidos</h2><p>El sorteo cruza una vez cada pareja del mismo deporte.</p></div></div>
        <div class="barra-acciones">
            <form action="procesos/acciones.php" method="post" onsubmit="return confirm('Se reemplazará el calendario actual. ¿Continuar?')">
                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>"><button class="boton boton-oscuro" type="submit" name="accion" value="generar_calendario">↻ Generar calendario aleatorio</button>
            </form>
            <form action="procesos/acciones.php" method="post" class="control-publicacion">
                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>"><input type="hidden" name="publicado" value="<?= $ajustes['calendario_publicado'] ? '0' : '1' ?>">
                <span class="indicador-publicacion <?= $ajustes['calendario_publicado'] ? 'activo' : '' ?>"><?= $ajustes['calendario_publicado'] ? 'Publicado' : 'No publicado' ?></span>
                <button class="boton boton-linea" type="submit" name="accion" value="publicar_calendario"><?= $ajustes['calendario_publicado'] ? 'Ocultar calendario' : 'Publicar calendario' ?></button>
            </form>
        </div>
        <?php if (!$partidosDocente): ?><p class="estado-vacio">Aún no hay partidos. Deben existir al menos 6 equipos válidos con 5 o más integrantes.</p>
        <?php else: ?><form action="procesos/acciones.php" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
            <div class="lista-edicion-partidos">
            <?php foreach ($partidosDocente as $partido): ?>
                <article class="fila-partido"><div class="partido-identidad"><span class="partido-deporte"><?= escapar($partido['deporte']) ?></span><strong><?= escapar($partido['equipo_local']) ?> <i>VS</i> <?= escapar($partido['equipo_visitante']) ?></strong></div>
                    <?php if (!deporteEsVoleibol((string) $partido['deporte'])): ?>
                        <?php $etiquetaMarcador = etiquetaMarcadorDeporte((string) $partido['deporte']); ?>
                        <label><?= escapar($etiquetaMarcador) ?> · <?= escapar($partido['equipo_local']) ?><input type="number" min="0" max="65535" step="1" name="partidos[<?= (int) $partido['id'] ?>][goles_local]" value="<?= (int) $partido['goles_local'] ?>" required></label>
                        <label><?= escapar($etiquetaMarcador) ?> · <?= escapar($partido['equipo_visitante']) ?><input type="number" min="0" max="65535" step="1" name="partidos[<?= (int) $partido['id'] ?>][goles_visitante]" value="<?= (int) $partido['goles_visitante'] ?>" required></label>
                    <?php endif; ?>
                    <label>Fecha y hora<input type="datetime-local" name="partidos[<?= (int) $partido['id'] ?>][fecha]" value="<?= $partido['fecha'] ? escapar(date('Y-m-d\TH:i', strtotime($partido['fecha']))) : '' ?>"></label>
                    <label>Lugar<input type="text" maxlength="120" name="partidos[<?= (int) $partido['id'] ?>][lugar]" value="<?= escapar($partido['lugar']) ?>" placeholder="Cancha o sede"></label>
                </article>
            <?php endforeach; ?>
            </div><button class="boton boton-dorado" type="submit" name="accion" value="guardar_partidos">Guardar cambios del calendario</button>
        </form><?php endif; ?>
        <section class="seccion-panel" id="calendarios-guardados">
            <div class="titulo-bloque"><span class="numero-bloque">06</span><div><h2>Historial de calendarios</h2><p>Cada sorteo se conserva en la base de datos, incluso al generar uno nuevo.</p></div></div>
            <?php if (!$calendariosDocente): ?><p class="estado-vacio">Todavía no se ha generado ningún calendario.</p>
            <?php else: ?><div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Calendario</th><th>Enfrentamientos</th><th>Fecha de generación</th><th>Fechas programadas</th><th>Generado por</th><th>Partidos</th><th>Estado</th></tr></thead><tbody>
                <?php foreach ($calendariosDocente as $calendario): ?><tr>
                    <td><strong>#<?= (int) $calendario['id'] ?></strong></td>
                    <td><?= $calendario['enfrentamientos'] ? nl2br(escapar($calendario['enfrentamientos'])) : 'Sin partidos' ?></td>
                    <td><?= escapar(date('d/m/Y H:i', strtotime($calendario['creado_en']))) ?></td>
                    <td><?= $calendario['fecha_inicio_programada'] ? escapar(date('d/m/Y H:i', strtotime($calendario['fecha_inicio_programada']))) . ' – ' . escapar(date('d/m/Y H:i', strtotime($calendario['fecha_fin_programada']))) : 'Sin fechas' ?></td>
                    <td><?= escapar($calendario['generado_por']) ?></td>
                    <td><?= (int) $calendario['partidos'] ?></td>
                    <td><?= $calendario['actual'] ? 'Actual' : 'Histórico' ?></td>
                </tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
    </section>
    <section class="seccion-panel tab-panel" id="posiciones">
        <div class="titulo-bloque"><span class="numero-bloque">08</span><div><h2>Tabla de posiciones</h2><p>Voleibol se clasifica por el total de tres rondas; fútbol y baloncesto, por los marcadores registrados en el calendario.</p></div></div>
        <div class="barra-acciones"><span class="indicador-publicacion <?= $ajustes['posiciones_publicadas'] ? 'activo' : '' ?>"><?= $ajustes['posiciones_publicadas'] ? 'Publicado' : 'No publicado' ?></span>
            <form action="procesos/acciones.php" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>"><input type="hidden" name="publicado" value="<?= $ajustes['posiciones_publicadas'] ? '0' : '1' ?>"><button class="boton boton-linea" type="submit" name="accion" value="publicar_posiciones"><?= $ajustes['posiciones_publicadas'] ? 'Ocultar posiciones' : 'Publicar posiciones' ?></button></form>
        </div>
        <?php if (!$equipos): ?><p class="estado-vacio">Los equipos aparecerán cuando se registren estudiantes.</p>
        <?php else: ?>
            <?php $posicionesPorDeporte = []; foreach ($posicionesDocente as $fila) { $posicionesPorDeporte[$fila['deporte']][] = $fila; } ?>
            <form action="procesos/acciones.php" method="post">
                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                <?php $hayPosicionesVoleibol = false; ?>
                <?php foreach ($posicionesPorDeporte as $deporte => $filasDeporte): ?>
                    <h3 class="etiqueta-deporte"><?= escapar($deporte) ?></h3>
                    <?php $esVoleibol = deporteEsVoleibol((string) $deporte); ?>
                    <?php $hayPosicionesVoleibol = $hayPosicionesVoleibol || $esVoleibol; ?>
                    <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Puesto</th><th>Equipo</th>
                        <?php if ($esVoleibol): ?><th>Ronda 1</th><th>Ronda 2</th><th>Ronda 3</th><th>Total</th>
                        <?php else: ?><th><?= escapar(etiquetaMarcadorDeporte((string) $deporte)) ?></th><?php endif; ?>
                    </tr></thead><tbody>
                        <?php $puestoActual = 0; $valorPrevio = null; foreach ($filasDeporte as $indice => $fila): ?>
                            <?php $valor = (string) ($esVoleibol ? $fila['puntos_total'] : $fila['marcador']); ?>
                            <?php if ($valorPrevio === null || $valor !== $valorPrevio) { $puestoActual = $indice + 1; $valorPrevio = $valor; } ?>
                            <tr><td><?= (int) $puestoActual ?></td><td><strong><?= escapar($fila['equipo']) ?></strong></td>
                                <?php if ($esVoleibol): ?>
                                    <?php foreach (['ronda_1', 'ronda_2', 'ronda_3'] as $ronda): ?>
                                        <td><label class="solo-lectores" for="p-<?= (int) $fila['id'] ?>-<?= escapar($ronda) ?>"><?= escapar(str_replace('_', ' ', ucfirst($ronda))) ?> - <?= escapar($fila['equipo']) ?></label><input class="entrada-tarjeta entrada-puntos" id="p-<?= (int) $fila['id'] ?>-<?= escapar($ronda) ?>" type="number" min="0" step="1" name="posiciones[<?= (int) $fila['id'] ?>][<?= escapar($ronda) ?>]" value="<?= escapar((string) $fila[$ronda]) ?>" required></td>
                                    <?php endforeach; ?>
                                    <td><strong><?= escapar((string) $fila['puntos_total']) ?></strong></td>
                                <?php else: ?>
                                    <td><strong><?= escapar((string) $fila['marcador']) ?></strong></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                <?php endforeach; ?>
                <?php if ($hayPosicionesVoleibol): ?><button class="boton boton-dorado" type="submit" name="accion" value="guardar_posiciones">Guardar rondas de voleibol</button><?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
</section>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.pestana');
    const panels = document.querySelectorAll('.tab-panel');
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.pestana;
        tabs.forEach((button) => button.classList.toggle('active', button === tab));
        panels.forEach((panel) => {
          panel.classList.toggle('active', panel.id === target);
        });
      });
    });
  });
</script>