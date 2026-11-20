<section class="panel-estudiante contenedor">
    <div class="cabecera-panel">
        <div><p class="sobretitulo sobretitulo-oscuro">ÁREA DE ESTUDIANTE</p><h1>Hola, <?= escapar($_SESSION['nombre'] ?? '') ?>.</h1><p>Tu perfil y la actividad de tu equipo.</p></div>
        <a class="boton boton-linea" href="index.php?pagina=perfil">Editar perfil</a>
    </div>
    <section class="resumen-equipo">
        <div><span class="resumen-etiqueta">TU EQUIPO · <?= escapar($perfil['deporte']) ?></span><h2><?= escapar($perfil['equipo']) ?></h2></div>
        <div class="resumen-detalle"><span>Tu posición</span><strong><?= escapar($perfil['posicion']) ?></strong></div>
        <div class="resumen-detalle"><span>Capitanía</span><strong><?= $perfil['es_capitan'] ? 'Capitán/a' : 'Integrante' ?></strong></div>
        <div class="resumen-detalle"><span>Integrantes</span><strong><?= count($miembrosEquipo) ?></strong></div>
    </section>
    <nav class="pestanas-panel" aria-label="Secciones de mi equipo">
        <button class="pestana active" type="button" data-pestana="equipo">Equipo</button>
        <button class="pestana" type="button" data-pestana="solicitudes">Solicitudes</button>
        <button class="pestana" type="button" data-pestana="calendario">Calendario</button>
        <button class="pestana" type="button" data-pestana="posiciones">Posiciones</button>
    </nav>
    <section class="seccion-panel tab-panel active" id="equipo">
        <div class="titulo-bloque"><span class="numero-bloque">01</span><div><h2>Compañeros de equipo</h2><p>Estudiantes que indicaron el mismo equipo y deporte.</p></div></div>
        <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Estudiante</th><th>Posición</th><th>Rol en el equipo</th><?php if ($perfil['es_capitan']): ?><th>Acciones</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($miembrosEquipo as $miembro): ?>
                <tr><td><strong><?= escapar($miembro['nombre'] . ' ' . $miembro['apellido']) ?></strong></td><td><?= escapar($miembro['posicion']) ?></td><td><?= $miembro['es_capitan'] ? 'Capitán/a' : 'Integrante' ?></td>
                    <?php if ($perfil['es_capitan']): ?><td>
                        <?php if (!$miembro['es_capitan']): ?>
                            <form action="procesos/acciones.php" method="post" onsubmit="return confirm('¿Retirar a este jugador del equipo?')">
                                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                                <input type="hidden" name="jugador_id" value="<?= (int) $miembro['usuario_id'] ?>">
                                <button class="boton boton-linea boton-pequeno boton-peligro" type="submit" name="accion" value="eliminar_jugador_equipo">Retirar</button>
                            </form>
                        <?php endif; ?>
                    </td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php if ($perfil['es_capitan']): ?>
        <section class="seccion-panel tab-panel" id="solicitudes">
            <div class="titulo-bloque"><span class="numero-bloque">02</span><div><h2>Solicitudes para unirse</h2><p>Aprueba o rechaza a quienes quieren entrar a tu equipo.</p></div></div>
            <?php if (!$solicitudesEquipo): ?><p class="estado-vacio">No tienes solicitudes pendientes.</p>
            <?php else: ?><div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Estudiante</th><th>Posición</th><th>Fecha de solicitud</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach ($solicitudesEquipo as $solicitud): ?>
                    <tr><td><strong><?= escapar($solicitud['nombre'] . ' ' . $solicitud['apellido']) ?></strong></td><td><?= escapar($solicitud['posicion']) ?></td><td><?= escapar(date('d/m/Y H:i', strtotime($solicitud['creado_en']))) ?></td>
                        <td><div class="acciones-tabla">
                            <?php foreach (['aprobar_solicitud_equipo' => 'Aceptar', 'rechazar_solicitud_equipo' => 'Rechazar'] as $accionSolicitud => $textoAccion): ?>
                                <form action="procesos/acciones.php" method="post">
                                    <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                                    <input type="hidden" name="solicitud_id" value="<?= (int) $solicitud['id'] ?>">
                                    <button class="boton <?= $accionSolicitud === 'aprobar_solicitud_equipo' ? 'boton-dorado' : 'boton-linea' ?> boton-pequeno" type="submit" name="accion" value="<?= escapar($accionSolicitud) ?>"><?= escapar($textoAccion) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>
    <?php endif; ?>
    <div class="bloques-panel">
        <section class="seccion-panel tab-panel" id="calendario"><div class="titulo-bloque"><span class="numero-bloque">03</span><div><h2>Calendario</h2><p>Partidos publicados por tus docentes.</p></div></div>
            <?php if (!$ajustes['calendario_publicado']): ?><p class="estado-vacio">El calendario aún no está publicado.</p>
            <?php else: ?><div class="lista-partidos">
                <?php $encuentrosEquipo = array_filter($partidos, static fn(array $partido): bool => (int) $partido['equipo_local_id'] === (int) $perfil['equipo_id'] || (int) $partido['equipo_visitante_id'] === (int) $perfil['equipo_id']); ?>
                <?php if (!$encuentrosEquipo): ?><p class="estado-vacio">Tu equipo aún no tiene partidos publicados.</p><?php endif; ?>
                <?php foreach ($encuentrosEquipo as $partido): ?><article class="partido"><div class="partido-meta"><span><?= escapar($partido['deporte']) ?></span><time><?= $partido['fecha'] ? escapar(date('d/m/Y · H:i', strtotime($partido['fecha']))) : 'Fecha por confirmar' ?></time></div><div class="partido-equipos"><strong><?= escapar($partido['equipo_local']) ?></strong><span>VS</span><strong><?= escapar($partido['equipo_visitante']) ?></strong></div></article><?php endforeach; ?>
            </div><?php endif; ?>
        </section>
        <section class="seccion-panel tab-panel" id="posiciones"><div class="titulo-bloque"><span class="numero-bloque">04</span><div><h2>Posiciones</h2><p>Clasificación publicada por tus docentes.</p></div></div>
            <?php if (!$ajustes['posiciones_publicadas']): ?><p class="estado-vacio">La tabla aún no está publicada.</p>
            <?php else: ?>
                <?php $puestoDeporte = 0; $valorAnterior = null; $esVoleibol = deporteEsVoleibol((string) $perfil['deporte']); ?>
                <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Puesto</th><th>Equipo</th>
                    <?php if ($esVoleibol): ?><th>Ronda 1</th><th>Ronda 2</th><th>Ronda 3</th><th>Total</th><?php else: ?><th><?= escapar(etiquetaMarcadorDeporte((string) $perfil['deporte'])) ?></th><?php endif; ?>
                    <th>Tarjetas por jugador</th></tr></thead><tbody>
                    <?php foreach (array_values(array_filter($posiciones, static fn(array $fila): bool => $fila['deporte'] === $perfil['deporte'])) as $indice => $fila): ?>
                        <?php $valorPosicion = (string) ($esVoleibol ? $fila['puntos_total'] : $fila['marcador']); ?>
                        <?php if ($valorAnterior === null || $valorPosicion !== $valorAnterior): $puestoDeporte = $indice + 1; $valorAnterior = $valorPosicion; endif; ?>
                        <tr><td><?= $puestoDeporte ?></td><td><?= escapar($fila['equipo']) ?></td>
                            <?php if ($esVoleibol): ?><td><?= escapar((string) $fila['ronda_1']) ?></td><td><?= escapar((string) $fila['ronda_2']) ?></td><td><?= escapar((string) $fila['ronda_3']) ?></td><td><strong><?= escapar((string) $fila['puntos_total']) ?></strong></td>
                            <?php else: ?><td><strong><?= escapar((string) $fila['marcador']) ?></strong></td><?php endif; ?>
                            <td>
                            <?php if (empty($tarjetasPorEquipo[$fila['id']])): ?>—<?php else: ?>
                                <?php foreach ($tarjetasPorEquipo[$fila['id']] as $tarjeta): ?>
                                    <small class="tarjeta-jugador"><?= escapar($tarjeta['nombre'] . ' ' . $tarjeta['apellido']) ?>: 🟨 <?= (int) $tarjeta['amarillas'] ?> · 🟥 <?= (int) $tarjeta['rojas'] ?></small>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </div>
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