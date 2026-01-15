<?php
require_once 'db.php';

echo "<h1>Biblioteca</h1>";

function tabla($conn, $query, $headers, $fields) {
    $r = $conn->query($query);
    if ($r->num_rows === 0) return;

    echo "<table border='1'><tr>";
    foreach ($headers as $h) echo "<th>$h</th>";
    echo "</tr>";

    while ($row = $r->fetch_assoc()) {
        echo "<tr>";
        foreach ($fields as $f) echo "<td>{$row[$f]}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Categorías</h2>";
tabla(
    $conn,
    "SELECT * FROM CATEGORIAS_LIBROS ORDER BY codigo_categoria",
    ["ID","Código","Descripción"],
    ["id_categoria","codigo_categoria","descripcion"]
);

echo "<h2>Libros</h2>";
tabla(
    $conn,
    "SELECT l.id_libro,titulo,autor,editorial,isbn,c.descripcion categoria
     FROM LIBROS l LEFT JOIN CATEGORIAS_LIBROS c USING(id_categoria)",
    ["ID","Título","Autor","Editorial","ISBN","Categoría"],
    ["id_libro","titulo","autor","editorial","isbn","categoria"]
);

echo "<h2>Ejemplares</h2>";
tabla(
    $conn,
    "SELECT e.id_ejemplar,codigo_inventario,titulo,estado_fisico
     FROM EJEMPLARES e JOIN LIBROS l USING(id_libro)",
    ["ID","Código","Título","Estado"],
    ["id_ejemplar","codigo_inventario","titulo","estado_fisico"]
);

echo "<h2>Alumnos</h2>";
tabla(
    $conn,
    "SELECT * FROM ALUMNOS ORDER BY apellidos,nombre",
    ["ID","Nombre","Apellidos","DNI","Email","Curso"],
    ["id_alumno","nombre","apellidos","dni","email","curso"]
);

echo "<h2>Préstamos</h2>";
tabla(
    $conn,
    "SELECT id_prestamo,codigo_inventario,titulo,
            CONCAT(a.nombre,' ',a.apellidos) alumno,
            fecha_prestamo,fecha_limite_devolucion,
            IFNULL(fecha_devolucion_real,'No') devolucion,
            IF(devuelto, 'Sí','No') devuelto
     FROM PRESTAMOS p
     JOIN EJEMPLARES e USING(id_ejemplar)
     JOIN LIBROS l USING(id_libro)
     JOIN ALUMNOS a USING(id_alumno)",
    ["ID","Ejemplar","Título","Alumno","Préstamo","Límite","Devolución","Devuelto"],
    ["id_prestamo","codigo_inventario","titulo","alumno","fecha_prestamo","fecha_limite_devolucion","devolucion","devuelto"]
);

$conn->close();
