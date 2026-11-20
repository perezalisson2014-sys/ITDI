<?php
if (!isset($estudiante) || !is_array($estudiante)) {
    throw new LogicException('No se cargaron los datos del estudiante que se va a editar.');
}
?>
<section class="pagina-formulario contenedor">
    <div class="formulario-intro">
        <p class="sobretitulo sobretitulo-oscuro">ADMINISTRACIÓN DOCENTE</p>
        <h1>Editar estudiante.</h1>
        <p>Actualiza los datos de inscripción. La contraseña no se muestra ni se modifica desde este formulario.</p>
    </div>
    <form class="formulario" action="procesos/actualizarEstudiante.php" method="post">
        <div class="formulario-titulo"><h2>Datos personales</h2><p><?= escapar($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></p></div>
        <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
        <input type="hidden" name="id" value="<?= (int) $estudiante['id'] ?>">
        <div class="rejilla-campos">
            <label>Nombre<input name="nombre" type="text" maxlength="80" value="<?= escapar($estudiante['nombre']) ?>" required></label>
            <label>Apellido<input name="apellido" type="text" maxlength="100" value="<?= escapar($estudiante['apellido']) ?>" required></label>
            <label>Tipo de documento<select name="tipo_documento" required><?php foreach (['Cédula de ciudadanía', 'Tarjeta de identidad', 'Pasaporte', 'Otro'] as $tipo): ?><option value="<?= escapar($tipo) ?>" <?= $estudiante['tipo_documento'] === $tipo ? 'selected' : '' ?>><?= escapar($tipo) ?></option><?php endforeach; ?></select></label>
            <label>Número de documento<input name="numero_documento" type="text" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" title="Escribe entre 1 y 10 dígitos." value="<?= escapar($estudiante['numero_documento']) ?>" required></label>
            <label>Género<input name="genero" type="text" maxlength="40" value="<?= escapar($estudiante['genero']) ?>" required></label>
            <label>Correo electrónico<input name="correo" type="email" maxlength="190" value="<?= escapar($estudiante['correo']) ?>" required></label>
        </div>
        <div class="acciones-formulario"><a class="boton boton-linea" href="index.php?pagina=listar_estudiantes">Cancelar</a><button class="boton boton-dorado" type="submit">Guardar cambios</button></div>
    </form>
</section>