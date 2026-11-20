<section class="pagina-formulario pagina-ingreso contenedor">
    <div class="formulario-intro">
        <img class="marca-panel-logo" src="css/logo-torneos.png" alt="Logo de Torneos Intercursos">
        <div class="marca-panel"><p>TORNEOS</p><h1>INTERCURSOS</h1><span>Colegio Técnico Palermo I.E.D.</span></div>
        <p class="texto-panel">Competimos, nos unimos y construimos comunidad.</p>
    </div>
    <form class="formulario" action="procesos/acciones.php" method="post">
        <div class="formulario-titulo"><h2>Iniciar sesión</h2><p>Ingresa a tu cuenta para volver a los torneos intercursos.</p></div>
        <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
        <label>Correo electrónico<input name="correo" type="email" autocomplete="email" value="<?= escapar($valoresFormulario['correo'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['correo']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['correo'])): ?><small class="error-campo"><?= escapar($erroresFormulario['correo']) ?></small><?php endif; ?></label>
        <label>Contraseña<input name="contrasena" type="password" autocomplete="current-password" aria-invalid="<?= isset($erroresFormulario['contrasena']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['contrasena'])): ?><small class="error-campo"><?= escapar($erroresFormulario['contrasena']) ?></small><?php endif; ?></label>
        <button class="boton boton-dorado boton-ancho" type="submit" name="accion" value="ingresar">Iniciar sesión</button>
        <p class="enlace-formulario"><a href="index.php?pagina=recuperar">¿Olvidaste o quieres cambiar tu contraseña?</a></p>
        <p class="enlace-formulario">¿No tienes una cuenta? <a href="index.php?pagina=registro">Regístrate</a></p>
    </form>
</section>