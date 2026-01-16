<?php
require_once 'db.php';

$vista      = $_GET['vista'] ?? 'todas';
$sort_by    = $_GET['sort_by'] ?? null;
$sort_order = $_GET['sort_order'] ?? 'ASC';
$sort_tabla = $_GET['sort_tabla'] ?? null;

// Definición de vistas
$vistas = [
    'categorias' => [
        'titulo'  => 'Categorías',
        'sql'     => 'SELECT codigo_categoria, descripcion FROM CATEGORIAS_LIBROS',
        'headers' => ['Código', 'Descripción'],
        'fields'  => ['codigo_categoria', 'descripcion']
    ],
    'libros' => [
        'titulo'  => 'Libros',
        'sql'     => 'SELECT l.titulo, l.autor, l.editorial, l.isbn, c.codigo_categoria, c.descripcion,
                             COUNT(e.id_ejemplar) total,
                             SUM(e.id_ejemplar NOT IN (SELECT id_ejemplar FROM PRESTAMOS WHERE devuelto = 0)) disponibles
                      FROM LIBROS l
                      LEFT JOIN CATEGORIAS_LIBROS c ON l.id_categoria = c.id_categoria
                      LEFT JOIN EJEMPLARES e ON l.id_libro = e.id_libro
                      GROUP BY l.id_libro',
        'headers' => ['Título', 'Autor', 'Editorial', 'ISBN', 'Código Categoría', 'Descripción', 'Ejemplares', 'Disponibles'],
        'fields'  => ['titulo', 'autor', 'editorial', 'isbn', 'codigo_categoria', 'descripcion', 'total', 'disponibles']
    ],
    'ejemplares' => [
        'titulo'  => 'Ejemplares',
        'sql'     => 'SELECT l.titulo, e.estado_fisico,
                             IF(p.id_prestamo IS NULL, "Disponible", "Prestado") estado
                      FROM EJEMPLARES e
                      JOIN LIBROS l ON e.id_libro = l.id_libro
                      LEFT JOIN PRESTAMOS p ON e.id_ejemplar = p.id_ejemplar AND p.devuelto = 0',
        'headers' => ['Título', 'Estado físico', 'Disponibilidad'],
        'fields'  => ['titulo', 'estado_fisico', 'estado']
    ],
    'alumnos' => [
        'titulo'  => 'Alumnos',
        'sql'     => 'SELECT nombre, apellidos, dni, email, curso FROM ALUMNOS',
        'headers' => ['Nombre', 'Apellidos', 'DNI', 'Email', 'Curso'],
        'fields'  => ['nombre', 'apellidos', 'dni', 'email', 'curso']
    ],
    'prestamos' => [
        'titulo'  => 'Préstamos',
        'sql'     => 'SELECT l.titulo, CONCAT(a.nombre, " ", a.apellidos) alumno, fecha_prestamo, fecha_limite_devolucion,
                             IFNULL(fecha_devolucion_real, "No") devolucion, IF(devuelto, "Sí", "No") devuelto
                      FROM PRESTAMOS p
                      JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
                      JOIN LIBROS l ON e.id_libro = l.id_libro
                      JOIN ALUMNOS a ON p.id_alumno = a.id_alumno',
        'headers' => ['Título', 'Alumno', 'Préstamo', 'Límite', 'Devolución', 'Devuelto'],
        'fields'  => ['titulo', 'alumno', 'fecha_prestamo', 'fecha_limite_devolucion', 'devolucion', 'devuelto']
    ]
];

// Genera URL de ordenación con anchor
function get_sort_url($field, $tabla_key, $vista, $sort_by, $sort_order) {
    $anchor = '#' . str_replace(' ', '_', $tabla_key);
    if ($sort_by === $field) {
        return $sort_order === 'ASC'
            ? "?vista={$vista}&sort_by={$field}&sort_order=DESC&sort_tabla={$tabla_key}{$anchor}"
            : "?vista={$vista}{$anchor}";
    }
    return "?vista={$vista}&sort_by={$field}&sort_order=ASC&sort_tabla={$tabla_key}{$anchor}";
}

// Muestra la tabla
function mostrar_tabla($conn, $sql, $headers, $fields, $sort_by, $sort_order, $tabla_key, $vista) {
    if ($sort_by && in_array($sort_by, $fields)) $sql .= " ORDER BY {$sort_by} {$sort_order}";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) return;

    echo "<table border='1'><tr>";
    foreach ($headers as $i => $h) {
        $field = $fields[$i];
        $url = get_sort_url($field, $tabla_key, $vista, $sort_by, $sort_order);

        // Agregar flecha si esta columna es la que se ordena
        $flecha = '';
        if ($sort_by === $field) {
            $flecha = $sort_order === 'ASC' ? ' ↑' : ' ↓';
        }

        echo "<th><a href='{$url}' style='text-decoration:none;color:black;'>{$h}{$flecha}</a></th>";
    }
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($fields as $f) echo "<td>{$row[$f]}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

?>

<h1 id="tablas">Biblioteca</h1>

<form method="get">
    <select name="vista" onchange="this.form.submit()">
        <option value="todas">Todas</option>
        <?php
        foreach ($vistas as $key => $v) {
            $selected = $vista === $key ? 'selected' : '';
            echo "<option value='$key' $selected>{$v['titulo']}</option>";
        }
        ?>
    </select>
</form>

<?php
if ($vista === 'todas') {
    foreach ($vistas as $key => $v) {
        echo "<h2 id='" . str_replace(' ', '_', $key) . "'>{$v['titulo']}</h2>";
        mostrar_tabla($conn, $v['sql'], $v['headers'], $v['fields'],
                      ($sort_tabla === $key ? $sort_by : null),
                      $sort_order, $key, $vista);
    }
} elseif (isset($vistas[$vista])) {
    $v = $vistas[$vista];
    echo "<h2 id='" . str_replace(' ', '_', $vista) . "'>{$v['titulo']}</h2>";
    mostrar_tabla($conn, $v['sql'], $v['headers'], $v['fields'], $sort_by, $sort_order, $vista, $vista);
}

$conn->close();
?>
