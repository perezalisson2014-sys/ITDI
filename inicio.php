<?php if (empty($_SESSION['usuario_id'])): ?>
    <section class="hero">
        <img class="hero-logo" src="css/logo-torneos.png" alt="Escudo de Torneos Intercursos">
        <div class="hero-contenido">
            <h1>TORNEOS<br><span>INTERCURSOS</span></h1>
            <div class="acciones-hero">
                <a class="boton boton-dorado" href="index.php?pagina=registro">Registrarse</a>
                <a class="boton boton-transparente" href="index.php?pagina=ingresar">Iniciar sesión</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="seccion-publica" aria-label="Información de torneos">
    <div class="bloques-publicos">
        <details class="tarjeta-acceso" id="calendario">
            <summary><span class="numero-bloque">01</span><span class="tarjeta-texto"><strong>Calendario de partidos</strong><small>Consulta las fechas de los próximos encuentros deportivos de los equipos.</small></span><span class="boton boton-rojo">Ver calendario</span></summary>
            <div class="contenido-acceso">
            <?php if (!$ajustes['calendario_publicado']): ?>
                <p class="estado-vacio">El calendario todavía no ha sido publicado.</p>
            <?php elseif (!$partidos): ?>
                <p class="estado-vacio">No hay partidos programados.</p>
            <?php else: ?>
                <div class="lista-partidos">
                    <?php foreach ($partidos as $partido): ?>
                        <article class="partido">
                            <div class="partido-meta"><span><?= escapar($partido['deporte']) ?></span><time><?= $partido['fecha'] ? escapar(date('d/m/Y · H:i', strtotime($partido['fecha']))) : 'Fecha por confirmar' ?></time></div>
                            <div class="partido-equipos"><strong><?= escapar($partido['equipo_local']) ?></strong><span>VS</span><strong><?= escapar($partido['equipo_visitante']) ?></strong></div>
                            <?php if ($partido['lugar'] !== ''): ?><p class="partido-lugar"><?= escapar($partido['lugar']) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </details>
        <details class="tarjeta-acceso" id="posiciones">
            <summary><span class="numero-bloque">02</span><span class="tarjeta-texto"><strong>Tabla de posiciones</strong><small>Sigue el rendimiento de cada equipo y descubre quién lidera la clasificación.</small></span><span class="boton boton-rojo">Ver posiciones</span></summary>
            <div class="contenido-acceso">
            <?php if (!$ajustes['posiciones_publicadas']): ?>
                <p class="estado-vacio">La tabla de posiciones todavía no ha sido publicada.</p>
            <?php elseif (!$posiciones): ?>
                <p class="estado-vacio">Aún no hay equipos inscritos.</p>
            <?php else: ?>
                <?php $deporteActual = null; $puestoDeporte = 0; $indiceDeporte = 0; $valorAnterior = null; ?>
                <?php foreach ($posiciones as $fila): ?>
                    <?php if ($deporteActual !== $fila['deporte']): ?>
                        <?php if ($deporteActual !== null): ?></tbody></table></div><?php endif; ?>
                        <?php $deporteActual = $fila['deporte']; $puestoDeporte = 0; $indiceDeporte = 0; $valorAnterior = null; $esVoleibol = deporteEsVoleibol((string) $deporteActual); ?>
                        <h4 class="etiqueta-deporte"><?= escapar($deporteActual) ?></h4>
                        <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>#</th><th>Equipo</th>
                            <?php if ($esVoleibol): ?><th>Ronda 1</th><th>Ronda 2</th><th>Ronda 3</th><th>Total</th><?php else: ?><th><?= escapar(etiquetaMarcadorDeporte((string) $deporteActual)) ?></th><?php endif; ?>
                            <th>Tarjetas por jugador</th></tr></thead><tbody>
                    <?php endif; ?>
                    <?php $indiceDeporte++; ?>
                    <?php $valorPosicion = (string) ($esVoleibol ? $fila['puntos_total'] : $fila['marcador']); ?>
                    <?php if ($valorAnterior === null || $valorPosicion !== $valorAnterior): $puestoDeporte = $indiceDeporte; $valorAnterior = $valorPosicion; endif; ?>
                    <tr><td><?= $puestoDeporte ?></td><td><strong><?= escapar($fila['equipo']) ?></strong></td>
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
                <?php if ($deporteActual !== null): ?></tbody></table></div><?php endif; ?>
            <?php endif; ?>
            </div>
        </details>
    </div>
</section>
<script>
document.querySelectorAll('a[href$="#calendario"], a[href$="#posiciones"]').forEach((enlace) => {
    enlace.addEventListener('click', () => {
        const seccion = document.querySelector(enlace.getAttribute('href'));
        if (seccion instanceof HTMLDetailsElement) {
            seccion.open = true;
        }
    });
});
if (window.location.hash) {
    const seccionInicial = document.querySelector(window.location.hash);
    if (seccionInicial instanceof HTMLDetailsElement) {
        seccionInicial.open = true;
    }
}
</script>