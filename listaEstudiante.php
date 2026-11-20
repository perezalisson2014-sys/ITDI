<section class="panel-docente contenedor">
    <div class="cabecera-panel">
        <div><p class="sobretitulo sobretitulo-oscuro">ADMINISTRACIÓN DOCENTE</p><h1>Estudiantes.</h1><p>Consulta y administra las inscripciones.</p></div>
        <a class="boton boton-linea" href="index.php?pagina=docente">Volver al panel</a>
    </div>
    <div class="seccion-panel">
        <div class="titulo-bloque"><span class="numero-bloque">01</span><div><h2>Lista de estudiantes</h2><p><?= count($estudiantes) ?> registros</p></div></div>
        <?php if (!$estudiantes): ?><p class="estado-vacio">Todavía no hay estudiantes registrados.</p>
        <?php else: ?><div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Estudiante</th><th>Documento</th><th>Correo</th><th>Equipo / deporte</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($estudiantes as $estudiante): ?><tr>
                <td><strong><?= escapar($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></strong></td>
                <td><?= escapar($estudiante['tipo_documento'] . ' · ' . $estudiante['numero_documento']) ?></td>
                <td><?= escapar($estudiante['correo']) ?></td>
                <td><?= escapar($estudiante['equipo'] ?? 'Encuesta pendiente') ?><?= $estudiante['deporte'] ? ' · ' . escapar($estudiante['deporte']) : '' ?></td>
                <td class="acciones-tabla"><a class="boton boton-linea boton-pequeno" href="procesos/editarEstudiante.php?id=<?= (int) $estudiante['id'] ?>">Editar</a>
                    <form action="procesos/eliminarEstudiante.php" method="post" onsubmit="return confirm('¿Eliminar esta cuenta y su perfil deportivo?')"><input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>"><input type="hidden" name="id" value="<?= (int) $estudiante['id'] ?>"><button class="boton boton-peligro boton-pequeno" type="submit">Eliminar</button></form>
                </td>
            </tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
</section>