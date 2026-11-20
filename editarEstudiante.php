<?php
declare(strict_types=1);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: ../index.php?pagina=listar_estudiantes');
    exit;
}

header('Location: ../index.php?pagina=editar_estudiante&id=' . $id);
exit;