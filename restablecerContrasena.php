<section class="pagina-formulario pagina-ingreso contenedor">
    <div class="formulario-intro">
        <img class="marca-panel-logo" src="css/logo-torneos.png" alt="Logo de Torneos Intercursos">
        <div class="marca-panel"><p>TORNEOS</p><h1>INTERCURSOS</h1><span>Colegio Técnico Palermo I.E.D.</span></div>
        <p class="texto-panel">Elige una contraseña nueva para tu cuenta.</p>
    </div>
    <div class="formulario">
        <?php if (!$restablecimientoValido): ?>
            <div class="formulario-titulo"><h2>Enlace no válido</h2><p>El enlace venció o ya fue utilizado. Solicita uno nuevo para continuar.</p></div>
            <a class="boton boton-dorado boton-ancho" href="index.php?pagina=recuperar">Solicitar otro enlace</a>
        <?php else: ?>
            <form action="procesos/acciones.php" method="post">
                <div class="formulario-titulo"><h2>Nueva contraseña</h2><p>Debe tener al menos 8 caracteres.</p></div>
                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                <input type="hidden" name="token" value="<?= escapar($tokenRestablecimiento) ?>">
                <label>Nueva contraseña<input name="nueva_contrasena" type="password" minlength="8" autocomplete="new-password" aria-invalid="<?= isset($erroresFormulario['nueva_contrasena']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['nueva_contrasena'])): ?><small class="error-campo"><?= escapar($erroresFormulario['nueva_contrasena']) ?></small><?php endif; ?></label>
                <label>Confirmar contraseña<input name="confirmar_contrasena" type="password" minlength="8" autocomplete="new-password" aria-invalid="<?= isset($erroresFormulario['confirmar_contrasena']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['confirmar_contrasena'])): ?><small class="error-campo"><?= escapar($erroresFormulario['confirmar_contrasena']) ?></small><?php endif; ?></label>
                <button class="boton boton-dorado boton-ancho" type="submit" name="accion" value="restablecer_contrasena">Guardar contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</section>
