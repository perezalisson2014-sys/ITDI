<section class="pagina-formulario contenedor">
    <div class="formulario-intro">
        <img class="marca-panel-logo" src="css/logo-torneos.png" alt="Logo de Torneos Intercursos">
        <div class="marca-panel"><p>TORNEOS</p><h1>INTERCURSOS</h1><span>Colegio Técnico Palermo I.E.D.</span></div>
        <p class="texto-panel">Competimos, nos unimos y construimos comunidad.</p>
    </div>
    <form class="formulario formulario-amplio" action="procesos/guardarEstudiante.php" method="post">
        <div class="formulario-titulo"><h2>Registro de usuarios</h2><p>Crea una cuenta para acceder a la plataforma de torneos deportivos intercursos.</p></div>
        <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
        <div class="rejilla-campos">
            <fieldset class="campo-rol"><legend>Tipo de usuario</legend>
                <label class="opcion-rol"><input type="radio" name="rol" value="estudiante" checked required><span>🎓 Estudiante</span></label>
                <label class="opcion-rol"><input type="radio" name="rol" value="docente"><span>👩‍🏫 Docente</span></label>
                <?php if (isset($erroresFormulario['rol'])): ?><small class="error-campo"><?= escapar($erroresFormulario['rol']) ?></small><?php endif; ?>
            </fieldset>
            <label>Nombre<input name="nombre" type="text" maxlength="80" autocomplete="given-name" value="<?= escapar($valoresFormulario['nombre'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['nombre']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['nombre'])): ?><small class="error-campo"><?= escapar($erroresFormulario['nombre']) ?></small><?php endif; ?></label>
            <label>Apellido<input name="apellido" type="text" maxlength="100" autocomplete="family-name" value="<?= escapar($valoresFormulario['apellido'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['apellido']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['apellido'])): ?><small class="error-campo"><?= escapar($erroresFormulario['apellido']) ?></small><?php endif; ?></label>
            <label>Tipo de documento<select name="tipo_documento" aria-invalid="<?= isset($erroresFormulario['tipo_documento']) ? 'true' : 'false' ?>" required><option value="">Selecciona una opción</option><?php foreach (['Cédula de ciudadanía', 'Tarjeta de identidad', 'Pasaporte', 'Otro'] as $tipoDocumento): ?><option <?= ($valoresFormulario['tipo_documento'] ?? '') === $tipoDocumento ? 'selected' : '' ?>><?= escapar($tipoDocumento) ?></option><?php endforeach; ?></select><?php if (isset($erroresFormulario['tipo_documento'])): ?><small class="error-campo"><?= escapar($erroresFormulario['tipo_documento']) ?></small><?php endif; ?></label>
            <label>Número de documento<input name="numero_documento" type="text" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" title="Escribe entre 1 y 10 dígitos." value="<?= escapar($valoresFormulario['numero_documento'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['numero_documento']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['numero_documento'])): ?><small class="error-campo"><?= escapar($erroresFormulario['numero_documento']) ?></small><?php endif; ?></label>
            <label>Género<select name="genero" aria-invalid="<?= isset($erroresFormulario['genero']) ? 'true' : 'false' ?>" required><option value="">Selecciona una opción</option><?php foreach (['Femenino', 'Masculino'] as $genero): ?><option <?= ($valoresFormulario['genero'] ?? '') === $genero ? 'selected' : '' ?>><?= escapar($genero) ?></option><?php endforeach; ?></select><?php if (isset($erroresFormulario['genero'])): ?><small class="error-campo"><?= escapar($erroresFormulario['genero']) ?></small><?php endif; ?></label>
            <label>Correo electrónico<input name="correo" type="email" maxlength="190" autocomplete="email" value="<?= escapar($valoresFormulario['correo'] ?? '') ?>" aria-invalid="<?= isset($erroresFormulario['correo']) ? 'true' : 'false' ?>" required><?php if (isset($erroresFormulario['correo'])): ?><small class="error-campo"><?= escapar($erroresFormulario['correo']) ?></small><?php endif; ?></label>
            <label class="campo-contrasena">Contraseña<input name="contrasena" type="password" minlength="8" autocomplete="new-password" placeholder="Crea una contraseña segura" aria-invalid="<?= isset($erroresFormulario['contrasena']) ? 'true' : 'false' ?>" required><small>Mínimo 8 caracteres.</small><?php if (isset($erroresFormulario['contrasena'])): ?><small class="error-campo"><?= escapar($erroresFormulario['contrasena']) ?></small><?php endif; ?></label>
            <label class="campo-clave-docente" id="campo-clave-docente" <?= ($valoresFormulario['rol'] ?? 'estudiante') === 'docente' ? '' : 'hidden' ?>>Clave de autorización docente<input name="clave_docente" type="password" autocomplete="off" aria-invalid="<?= isset($erroresFormulario['clave_docente']) ? 'true' : 'false' ?>"><small>Solicítala al administrador de la institución.</small><?php if (isset($erroresFormulario['clave_docente'])): ?><small class="error-campo"><?= escapar($erroresFormulario['clave_docente']) ?></small><?php endif; ?></label>
        </div>
        <button class="boton boton-dorado boton-ancho" type="submit" name="accion" value="registrar">Registrarse</button>
        <p class="enlace-formulario">¿Ya tienes una cuenta? <a href="index.php?pagina=ingresar">Inicia sesión</a></p>
    </form>
</section>
<script>
const campoClaveDocente = document.querySelector('#campo-clave-docente');
const rolInicial = <?= json_encode(in_array($valoresFormulario['rol'] ?? '', ['estudiante', 'docente'], true) ? $valoresFormulario['rol'] : 'estudiante', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const selectorRolInicial = document.querySelector(`input[name="rol"][value="${rolInicial}"]`);
selectorRolInicial.checked = true;
campoClaveDocente.querySelector('input').required = rolInicial === 'docente';
document.querySelectorAll('input[name="rol"]').forEach((selectorRol) => selectorRol.addEventListener('change', () => {
    const esDocente = document.querySelector('input[name="rol"]:checked').value === 'docente';
    campoClaveDocente.hidden = !esDocente;
    campoClaveDocente.querySelector('input').required = esDocente;
}));
</script>