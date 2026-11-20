<section class="pagina-formulario contenedor formulario-encuesta">
    <div class="formulario-intro">
        <img class="marca-panel-logo" src="css/logo-torneos.png" alt="Logo de Torneos Intercursos">
        <div class="marca-panel"><p>TORNEOS</p><h1>INTERCURSOS</h1><span>Colegio Técnico Palermo I.E.D.</span></div>
        <p class="texto-panel">Competimos, nos unimos y construimos comunidad.</p>
    </div>
    <form class="formulario" action="procesos/acciones.php" method="post">
        <div class="formulario-titulo"><h2>Perfil deportivo</h2><p>Hola, <?= escapar($_SESSION['nombre'] ?? '') ?>. Completa tu encuesta.</p></div>
        <?php if (!empty($solicitudesPendientes)): ?>
            <div class="estado-vacio"><strong>Solicitudes para unirte</strong>
                <?php foreach ($solicitudesPendientes as $solicitud): ?>
                    <p><?= escapar($solicitud['equipo']) ?> · <?= escapar($solicitud['deporte']) ?>: esperando respuesta del capitán.</p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($solicitudesCreacionEquipo)): ?>
            <div class="estado-vacio"><strong>Solicitud de creación de equipo</strong>
                <?php foreach ($solicitudesCreacionEquipo as $solicitud): ?>
                    <p><?= escapar($solicitud['equipo']) ?> · <?= escapar($solicitud['deporte']) ?>:
                        <?php if ($solicitud['estado'] === 'pendiente'): ?>esperando aprobación docente.
                        <?php elseif ($solicitud['estado'] === 'aprobada'): ?>aprobada; ya puedes ingresar a tu equipo.
                        <?php else: ?>rechazada por el docente. Puedes enviar una nueva solicitud.
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php $hayCreacionPendiente = !empty(array_filter($solicitudesCreacionEquipo ?? [], static fn(array $solicitud): bool => $solicitud['estado'] === 'pendiente')); ?>
        <?php if (!$hayCreacionPendiente): ?>
            <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
            <label>Nombre del equipo<input name="equipo" type="text" maxlength="100" placeholder="Ej. Halcones" value="<?= escapar($valoresFormulario['equipo'] ?? $perfil['equipo'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['equipo']) ? 'true' : 'false' ?>" <?= $perfil ? 'readonly' : '' ?> required><?php if (isset($erroresFormulario['equipo'])): ?><small class="error-campo"><?= escapar($erroresFormulario['equipo']) ?></small><?php endif; ?></label>
            <?php
            $deportesConPosiciones = opcionesPosicionesPorDeporte();
            $deportesDisponibles = [
                'Fútbol sala' => 'Futsal',
                'Voleibol' => 'Voleibol',
                'Baloncesto' => 'Baloncesto',
            ];
            $deporteActual = (string) ($valoresFormulario['deporte'] ?? $perfil['deporte'] ?? '');
            $posicionesSeleccionadas = $valoresFormulario['posiciones'] ?? [];
            if (!is_array($posicionesSeleccionadas)) {
                $posicionesSeleccionadas = [];
            }
            ?>
            <?php if ($perfil): ?>
                <label>Deporte<input name="deporte" type="text" maxlength="60" value="<?= escapar($deporteActual) ?>" readonly></label>
            <?php else: ?>
                <label>Deporte<select name="deporte" id="deporte" aria-invalid="<?= isset($erroresFormulario['deporte']) ? 'true' : 'false' ?>" required><option value="" disabled <?= $deporteActual === '' ? 'selected' : '' ?>>Selecciona un deporte</option><?php foreach ($deportesDisponibles as $valor => $nombre): ?><option value="<?= escapar($valor) ?>" <?= $deporteActual === $valor ? 'selected' : '' ?>><?= escapar($nombre) ?></option><?php endforeach; ?></select><?php if (isset($erroresFormulario['deporte'])): ?><small class="error-campo"><?= escapar($erroresFormulario['deporte']) ?></small><?php endif; ?></label>
            <?php endif; ?>
            <div class="campo-posiciones">
                <span class="etiqueta-campo">Posiciones en el equipo</span>
                <details class="selector-posiciones" id="selector-posiciones">
                    <summary>Elegir una o varias posiciones</summary>
                    <div class="tabla-contenedor"><table class="tabla tabla-posiciones"><thead><tr><th>Elegir</th><th>Posición</th></tr></thead><tbody id="opciones-posiciones"></tbody></table></div>
                </details>
                <label id="posicion-personalizada" <?= isset($deportesConPosiciones[$deporteActual]) ? 'hidden' : '' ?>>Escribe tu posición<input name="posicion_otro" type="text" maxlength="60" value="<?= escapar($valoresFormulario['posicion_otro'] ?? $perfil['posicion'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['posicion']) ? 'true' : 'false' ?>"></label>
                <?php if (isset($erroresFormulario['posicion'])): ?><small class="error-campo"><?= escapar($erroresFormulario['posicion']) ?></small><?php endif; ?>
            </div>
            <?php if (!$perfil): ?>
                <fieldset class="campo-capitan"><legend>¿Vas a crear un equipo nuevo?</legend><label class="opcion-radio"><input type="radio" name="capitan" value="1" required><span>Sí, solicitaré crearlo</span></label><label class="opcion-radio"><input type="radio" name="capitan" value="0"><span>No, solicitaré unirme</span></label></fieldset>
                <label>Cantidad máxima de integrantes del equipo
                    <input type="number" name="cantidad_integrantes" min="5" max="10" value="<?= (int) ($valoresFormulario['cantidad_integrantes'] ?? 10) ?>" required>
                </label>
            <?php endif; ?>
            <button class="boton boton-dorado boton-ancho" type="submit" name="accion" value="guardar_perfil"><?= $perfil ? 'Actualizar posición' : 'Enviar solicitud' ?> <span aria-hidden="true">→</span></button>
        <?php endif; ?>
    </form>
</section>
<script>
const opcionesPorDeporte = <?= json_encode($deportesConPosiciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const deporteInput = document.querySelector('#deporte');
const deporteFijo = document.querySelector('input[name="deporte"][readonly]');
const tablaPosiciones = document.querySelector('#opciones-posiciones');
const selectorPosiciones = document.querySelector('#selector-posiciones');
const posicionPersonalizada = document.querySelector('#posicion-personalizada');
const valoresPosiciones = <?= json_encode($posicionesSeleccionadas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const posicionGuardada = <?= json_encode($perfil['posicion'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function actualizarOpcionesPosicion() {
    const deporte = (deporteInput?.value || deporteFijo?.value || '').trim();
    const deporteReconocido = Object.keys(opcionesPorDeporte).find(
        (opcion) => opcion.toLocaleLowerCase() === deporte.toLocaleLowerCase()
    );
    const opciones = opcionesPorDeporte[deporteReconocido] || [];
    tablaPosiciones.replaceChildren();
    selectorPosiciones.hidden = opciones.length === 0;
    posicionPersonalizada.hidden = opciones.length > 0;
    opciones.forEach((posicion) => {
        const fila = document.createElement('tr');
        const celdaControl = document.createElement('td');
        const control = document.createElement('input');
        control.type = 'checkbox';
        control.name = 'posiciones[]';
        control.value = posicion;
        control.checked = valoresPosiciones.includes(posicion) || (posicionGuardada && posicionGuardada.split(', ').includes(posicion));
        control.setAttribute('aria-label', `Seleccionar ${posicion}`);
        celdaControl.append(control);
        const celdaNombre = document.createElement('td');
        celdaNombre.textContent = posicion;
        fila.append(celdaControl, celdaNombre);
        tablaPosiciones.append(fila);
    });
        posicionPersonalizada.querySelector('input').required = opciones.length === 0;
}

deporteInput?.addEventListener('change', actualizarOpcionesPosicion);
tablaPosiciones.addEventListener('change', () => {
    tablaPosiciones.querySelectorAll('input[type="checkbox"]').forEach((control) => control.setCustomValidity(''));
});
selectorPosiciones.closest('form').addEventListener('submit', (evento) => {
    const controles = [...tablaPosiciones.querySelectorAll('input[type="checkbox"]')];
    if (controles.length && !controles.some((control) => control.checked)) {
        controles[0].setCustomValidity('Elige al menos una posición para este deporte.');
        controles[0].reportValidity();
        evento.preventDefault();
    }
});
actualizarOpcionesPosicion();
</script>