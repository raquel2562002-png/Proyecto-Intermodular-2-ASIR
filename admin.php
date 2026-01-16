<?php
require_once 'db.php';

$vista = $_GET['vista'] ?? 'todas';
$accion = $_GET['accion'] ?? 'ver';
$id = $_GET['id'] ?? 0;
$orden = $_GET['orden'] ?? null;
$direccion = $_GET['direccion'] ?? 'ASC';

// Procesar eliminaciones
if (isset($_GET['eliminar'])) {
    $tabla_eliminar = $_GET['tabla'];
    $id_eliminar = $_GET['id'];
    
    // Mapeo especial para nombres de tablas y campos ID
    $map_tablas = [
        'categorias' => ['tabla' => 'CATEGORIAS_LIBROS', 'id' => 'id_categoria'],
        'libros' => ['tabla' => 'LIBROS', 'id' => 'id_libro'],
        'ejemplares' => ['tabla' => 'EJEMPLARES', 'id' => 'id_ejemplar'],
        'alumnos' => ['tabla' => 'ALUMNOS', 'id' => 'id_alumno'],
        'prestamos' => ['tabla' => 'PRESTAMOS', 'id' => 'id_prestamo']
    ];
    
    if (isset($map_tablas[$tabla_eliminar])) {
        $nombre_tabla = $map_tablas[$tabla_eliminar]['tabla'];
        $id_field = $map_tablas[$tabla_eliminar]['id'];
        $conn->query("DELETE FROM $nombre_tabla WHERE $id_field = $id_eliminar");
    }
    
    header("Location: ?vista=$vista");
    exit;
}

// Definición de vistas con campos para formularios
$vistas = [
    'categorias' => [
        'titulo' => 'Categorías',
        'tabla' => 'CATEGORIAS_LIBROS',
        'sql' => 'SELECT id_categoria, codigo_categoria, descripcion FROM CATEGORIAS_LIBROS',
        'headers' => ['Código', 'Descripción', 'Acciones'],
        'fields' => ['codigo_categoria', 'descripcion'],
        'form_fields' => [
            'codigo_categoria' => ['type' => 'text', 'label' => 'Código'],
            'descripcion' => ['type' => 'text', 'label' => 'Descripción']
        ],
        'id_field' => 'id_categoria'
    ],
    'libros' => [
        'titulo' => 'Libros',
        'tabla' => 'LIBROS',
        'sql' => 'SELECT l.id_libro, l.titulo, l.autor, l.editorial, l.isbn, c.codigo_categoria, 
                         COUNT(e.id_ejemplar) total,
                         SUM(e.id_ejemplar NOT IN (SELECT id_ejemplar FROM PRESTAMOS WHERE devuelto = 0)) disponibles
                  FROM LIBROS l
                  LEFT JOIN CATEGORIAS_LIBROS c ON l.id_categoria = c.id_categoria
                  LEFT JOIN EJEMPLARES e ON l.id_libro = e.id_libro
                  GROUP BY l.id_libro',
        'headers' => ['Título', 'Autor', 'Editorial', 'ISBN', 'Categoría', 'Ejemplares', 'Disponibles', 'Acciones'],
        'fields' => ['titulo', 'autor', 'editorial', 'isbn', 'codigo_categoria', 'total', 'disponibles'],
        'form_fields' => [
            'titulo' => ['type' => 'text', 'label' => 'Título'],
            'autor' => ['type' => 'text', 'label' => 'Autor'],
            'editorial' => ['type' => 'text', 'label' => 'Editorial'],
            'isbn' => ['type' => 'text', 'label' => 'ISBN'],
            'id_categoria' => ['type' => 'select', 'label' => 'Categoría', 
                              'options' => 'SELECT id_categoria, CONCAT(codigo_categoria, " - ", descripcion) FROM CATEGORIAS_LIBROS']
        ],
        'id_field' => 'id_libro'
    ],
    'ejemplares' => [
        'titulo' => 'Ejemplares',
        'tabla' => 'EJEMPLARES',
        'sql' => 'SELECT e.id_ejemplar, l.titulo, e.estado_fisico,
                         IF(p.id_prestamo IS NULL, "Disponible", "Prestado") estado
                  FROM EJEMPLARES e
                  JOIN LIBROS l ON e.id_libro = l.id_libro
                  LEFT JOIN PRESTAMOS p ON e.id_ejemplar = p.id_ejemplar AND p.devuelto = 0',
        'headers' => ['Libro', 'Estado físico', 'Disponibilidad', 'Acciones'],
        'fields' => ['titulo', 'estado_fisico', 'estado'],
        'form_fields' => [
            'id_libro' => ['type' => 'select', 'label' => 'Libro',
                          'options' => 'SELECT id_libro, titulo FROM LIBROS'],
            'estado_fisico' => ['type' => 'select', 'label' => 'Estado físico',
                               'options' => ['Usar', 'Sustituir', 'Perdido']]
        ],
        'id_field' => 'id_ejemplar'
    ],
    'alumnos' => [
        'titulo' => 'Alumnos',
        'tabla' => 'ALUMNOS',
        'sql' => 'SELECT id_alumno, nombre, apellidos, dni, email, curso FROM ALUMNOS',
        'headers' => ['Nombre', 'Apellidos', 'DNI', 'Email', 'Curso', 'Acciones'],
        'fields' => ['nombre', 'apellidos', 'dni', 'email', 'curso'],
        'form_fields' => [
            'nombre' => ['type' => 'text', 'label' => 'Nombre'],
            'apellidos' => ['type' => 'text', 'label' => 'Apellidos'],
            'dni' => ['type' => 'text', 'label' => 'DNI'],
            'email' => ['type' => 'email', 'label' => 'Email'],
            'curso' => ['type' => 'text', 'label' => 'Curso']
        ],
        'id_field' => 'id_alumno'
    ],
    'prestamos' => [
        'titulo' => 'Préstamos',
        'tabla' => 'PRESTAMOS',
        'sql' => 'SELECT p.id_prestamo, l.titulo, CONCAT(a.nombre, " ", a.apellidos) alumno, 
                         fecha_prestamo, fecha_limite_devolucion,
                         IFNULL(fecha_devolucion_real, "No devuelto") devolucion, IF(devuelto, "Sí", "No") devuelto
                  FROM PRESTAMOS p
                  JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
                  JOIN LIBROS l ON e.id_libro = l.id_libro
                  JOIN ALUMNOS a ON p.id_alumno = a.id_alumno',
        'headers' => ['Libro', 'Alumno', 'Préstamo', 'Límite', 'Devolución', 'Devuelto', 'Acciones'],
        'fields' => ['titulo', 'alumno', 'fecha_prestamo', 'fecha_limite_devolucion', 'devolucion', 'devuelto'],
        'form_fields' => [
            'id_ejemplar' => ['type' => 'select', 'label' => 'Ejemplar',
                             'options' => 'SELECT e.id_ejemplar, CONCAT(l.titulo, " - Estado: ", e.estado_fisico) 
                                         FROM EJEMPLARES e 
                                         JOIN LIBROS l ON e.id_libro = l.id_libro
                                         WHERE e.id_ejemplar NOT IN (SELECT id_ejemplar FROM PRESTAMOS WHERE devuelto = 0)'],
            'id_alumno' => ['type' => 'select', 'label' => 'Alumno',
                           'options' => 'SELECT id_alumno, CONCAT(nombre, " ", apellidos) FROM ALUMNOS'],
            'fecha_prestamo' => ['type' => 'date', 'label' => 'Fecha préstamo'],
            'fecha_limite_devolucion' => ['type' => 'date', 'label' => 'Fecha límite'],
            'fecha_devolucion_real' => ['type' => 'date', 'label' => 'Fecha devolución real'],
            'devuelto' => ['type' => 'checkbox', 'label' => 'Devuelto']
        ],
        'id_field' => 'id_prestamo'
    ]
];

// Función para generar URL de ordenación
function get_sort_url($field, $vista_key, $vista_actual, $orden_actual, $direccion_actual) {
    $url = "?vista=$vista_actual";
    $anchor = '#' . $vista_key;
    
    if ($orden_actual === $field) {
        if ($direccion_actual === 'ASC') {
            return $url . "&orden=$field&direccion=DESC$anchor";
        } else {
            return $url . $anchor; // Tercer click: sin orden
        }
    } else {
        return $url . "&orden=$field&direccion=ASC$anchor";
    }
}

// Mostrar formulario para agregar/editar
function mostrar_formulario($conn, $vista_key, $data, $id = 0) {
    global $vistas;
    $config = $vistas[$vista_key];
    
    echo "<h3>" . ($id ? "Editar" : "Agregar") . " {$config['titulo']}</h3>";
    echo "<form method='post' style='border:1px solid #ccc;padding:10px;margin:10px 0;'>";
    echo "<input type='hidden' name='tabla' value='$vista_key'>";
    echo "<input type='hidden' name='id' value='$id'>";
    
    foreach ($config['form_fields'] as $field => $field_config) {
        $label = $field_config['label'];
        $value = $data[$field] ?? '';
        
        echo "<div style='margin:5px 0;'>";
        echo "<label style='display:inline-block;width:150px;'>$label:</label>";
        
        switch ($field_config['type']) {
            case 'select':
                echo "<select name='$field'>";
                echo "<option value=''>-- Seleccionar --</option>";
                
                if (is_array($field_config['options'])) {
                    foreach ($field_config['options'] as $option) {
                        $selected = ($value == $option) ? 'selected' : '';
                        echo "<option value='$option' $selected>$option</option>";
                    }
                } else {
                    $result = $conn->query($field_config['options']);
                    if ($result) {
                        while ($row = $result->fetch_row()) {
                            $selected = ($value == $row[0]) ? 'selected' : '';
                            echo "<option value='{$row[0]}' $selected>{$row[1]}</option>";
                        }
                    }
                }
                
                echo "</select>";
                break;
                
            case 'checkbox':
                $checked = $value ? 'checked' : '';
                echo "<input type='checkbox' name='$field' value='1' $checked>";
                break;
                
            case 'date':
                echo "<input type='date' name='$field' value='$value'>";
                break;
                
            case 'email':
                echo "<input type='email' name='$field' value='$value'>";
                break;
                
            default:
                echo "<input type='text' name='$field' value='$value'>";
        }
        
        echo "</div>";
    }
    
    echo "<div style='margin-top:10px;'>";
    echo "<button type='submit'>Guardar</button> ";
    echo "<a href='?vista=$vista_key'>Cancelar</a>";
    echo "</div>";
    echo "</form>";
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tabla_post = $_POST['tabla'];
    $id_post = $_POST['id'] ?? 0;
    
    $config = $vistas[$tabla_post];
    $table_name = $config['tabla'];
    
    $fields = [];
    $values = [];
    
    foreach ($config['form_fields'] as $field => $field_config) {
        $value = $_POST[$field] ?? '';
        
        if ($field_config['type'] === 'checkbox') {
            $value = isset($_POST[$field]) ? 1 : 0;
        }
        
        if ($field === 'fecha_devolucion_real' && empty($value)) {
            $fields[] = $field;
            $values[] = "NULL";
        } elseif ($value !== '') {
            $fields[] = $field;
            $values[] = "'" . $conn->real_escape_string($value) . "'";
        }
    }
    
    if ($id_post) {
        // Actualizar
        $updates = [];
        for ($i = 0; $i < count($fields); $i++) {
            $updates[] = "{$fields[$i]} = {$values[$i]}";
        }
        $id_field = $config['id_field'];
        $sql = "UPDATE $table_name SET " . implode(', ', $updates) . 
               " WHERE $id_field = $id_post";
    } else {
        // Insertar
        $sql = "INSERT INTO $table_name (" . implode(', ', $fields) . 
               ") VALUES (" . implode(', ', $values) . ")";
    }
    
    $conn->query($sql);
    header("Location: ?vista=$tabla_post");
    exit;
}

// Mostrar tabla con datos
function mostrar_tabla($conn, $vista_key, $orden = null, $direccion = 'ASC') {
    global $vistas;
    $config = $vistas[$vista_key];
    
    $sql = $config['sql'];
    if ($orden && in_array($orden, $config['fields'])) {
        $sql .= " ORDER BY $orden $direccion";
    }
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        echo "<p>No hay datos</p>";
        return;
    }
    
    echo "<div style='overflow-x:auto;'>";
    echo "<table border='1' cellspacing='0' cellpadding='5' style='width:100%;'>";
    
    // Cabeceras
    echo "<tr>";
    foreach ($config['headers'] as $i => $header) {
        echo "<th>";
        
        // Solo columnas de datos tienen ordenación
        if ($i < count($config['fields'])) {
            $field = $config['fields'][$i];
            $url = get_sort_url($field, $vista_key, $vista_key, $orden, $direccion);
            $flecha = '';
            
            if ($orden === $field) {
                $flecha = $direccion === 'ASC' ? ' ↑' : ' ↓';
            }
            
            echo "<a href='$url' style='text-decoration:none;color:black;'>$header$flecha</a>";
        } else {
            echo $header;
        }
        
        echo "</th>";
    }
    echo "</tr>";
    
    // Filas de datos
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        
        foreach ($config['fields'] as $field) {
            echo "<td>" . ($row[$field] ?? '') . "</td>";
        }
        
        // Columna de acciones
        $id_value = $row[$config['id_field']];
        
        echo "<td>";
        echo "<a href='?vista=$vista_key&accion=editar&id=$id_value'>Editar</a> | ";
        echo "<a href='?vista=$vista_key&tabla=$vista_key&eliminar=1&id=$id_value' 
              onclick='return confirm(\"¿Seguro que quieres eliminar?\")'>Eliminar</a>";
        echo "</td>";
        
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Administración Biblioteca</title>
</head>
<body>
    <h1>Biblioteca del Instituto</h1>
    
    <!-- Menú de navegación -->
    <div style="margin:20px 0;">
        <form method="get">
            <select name="vista" onchange="this.form.submit()">
                <option value="todas" <?= $vista == 'todas' ? 'selected' : '' ?>>Todas las tablas</option>
                <?php foreach ($vistas as $key => $config): ?>
                    <option value="<?= $key ?>" <?= $vista == $key ? 'selected' : '' ?>>
                        <?= $config['titulo'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <!-- Botón para agregar nuevo (si no estamos en modo "todas") -->
    <?php if ($vista != 'todas' && $accion == 'ver'): ?>
        <div style="margin:10px 0;">
            <a href="?vista=<?= $vista ?>&accion=agregar">+ Agregar nuevo</a>
        </div>
    <?php endif; ?>
    
    <!-- Mostrar formulario o tablas -->
    <?php if ($vista != 'todas' && in_array($accion, ['agregar', 'editar'])): ?>
        <?php
        $data = [];
        if ($accion == 'editar' && $id) {
            $config = $vistas[$vista];
            $id_field = $config['id_field'];
            $table_name = $config['tabla'];
            $result = $conn->query("SELECT * FROM $table_name WHERE $id_field = $id");
            $data = $result->fetch_assoc();
        }
        mostrar_formulario($conn, $vista, $data, $id);
        ?>
    <?php else: ?>
        <?php if ($vista == 'todas'): ?>
            <?php foreach ($vistas as $key => $config): ?>
                <h2 id="<?= $key ?>"><?= $config['titulo'] ?></h2>
                <?php mostrar_tabla($conn, $key); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <h2><?= $vistas[$vista]['titulo'] ?></h2>
            <?php mostrar_tabla($conn, $vista, $orden, $direccion); ?>
        <?php endif; ?>
    <?php endif; ?>
    
</body>
</html>
<?php $conn->close(); ?>