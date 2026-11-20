<section class="pagina-formulario pagina-ingreso contenedor">
    <div class="formulario-intro">
        <img class="marca-panel-logo" src="css/logo-torneos.png" alt="Logo de Torneos Intercursos">
        <div class="marca-panel"><p>TORNEOS</p><h1>INTERCURSOS</h1><span>Colegio Técnico Palermo I.E.D.</span></div>
        <p class="texto-panel">Recupera el acceso a tu cuenta.</p>
    </div>
    <form class="formulario" action="procesos/acciones.php" method="post">
        <div class="formulario-titulo"><h2>Cambiar contraseña</h2><p>Te enviaremos un enlace si el correo corresponde a una cuenta.</p></div>
        <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
        <label>Correo electrónico<input name="correo" type="email" autocomplete="email" value="<?= escapar($valoresFormulario['correo'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['correo']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['correo'])): ?><small class="error-campo"><?= escapar($erroresFormulario['correo']) ?></small><?php endif; ?></label>
        <button class="boton boton-dorado boton-ancho" type="submit" name="accion" value="solicitar_recuperacion">Enviar enlace</button>
        <p class="enlace-formulario"><a href="index.php?pagina=ingresar">Volver a iniciar sesión</a></p>
    </form>
</section>
