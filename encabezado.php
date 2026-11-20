<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171717">
    <title>Torneos Intercursos | ITDI</title>
    <link rel="stylesheet" href="css/estilos.css?v=<?= (int) filemtime(__DIR__ . '/../css/estilos.css') ?>">
</head>
<body>
<header class="barra-superior">
    <a class="marca" href="index.php" aria-label="Torneos Intercursos, inicio"><img class="marca-icono" src="css/logo-torneos.png" alt=""></a>
    <nav class="navegacion" aria-label="Navegación principal">
        <?php if (empty($_SESSION['usuario_id'])): ?>
            <a href="index.php">Inicio</a>
        <?php endif; ?>
        <a href="index.php#calendario">Calendario</a>
        <a href="index.php#posiciones">Posiciones</a>
        <?php if (!empty($_SESSION['usuario_id'])): ?>
            <?php if (($_SESSION['rol'] ?? '') === 'docente'): ?>
                <a href="index.php?pagina=docente">Administración</a>
            <?php else: ?>
                <a href="index.php?pagina=estudiante">Mi perfil</a>
            <?php endif; ?>
            <form action="procesos/acciones.php" method="post" class="form-salir">
                <input type="hidden" name="csrf" value="<?= escapar(tokenFormulario()) ?>">
                <button class="boton boton-linea boton-pequeno" type="submit" name="accion" value="salir">Cerrar sesión</button>
            </form>
        <?php else: ?>
            <a href="index.php?pagina=registro">Registro</a>
            <a href="index.php?pagina=ingresar">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php if ($mensajeActual): ?>
        <div class="contenedor alerta alerta-<?= escapar($mensajeActual['tipo']) ?>" role="status"><?= escapar($mensajeActual['texto']) ?></div>
    <?php endif; ?>