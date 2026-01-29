<?php
require_once 'db.php';

$accion = $_GET['accion'] ?? 'ver';
$id = intval($_GET['id'] ?? 0);
$orden = $_GET['orden'] ?? null;
$direccion = $_GET['direccion'] ?? 'ASC';

// Eliminar préstamo
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['id']);
    $conn->query("DELETE FROM PRESTAMOS WHERE id_prestamo = $id_eliminar");
    header("Location: prestamos.php");
    exit;
}

// Función para generar URL de ordenación
function get_sort_url($field, $orden_actual, $direccion_actual) {
    if ($orden_actual === $field) {
        return $direccion_actual === 'ASC' ? "?orden=$field&direccion=DESC" : "?";
    }
    return "?orden=$field&direccion=ASC";
}

// Mostrar formulario para agregar/editar préstamo
function mostrar_formulario_prestamo($conn, $data = [], $id = 0) {
    $ejemplares = $conn->query("
        SELECT e.id_ejemplar, CONCAT(l.titulo, ' - Ejemplar #', e.id_ejemplar) as descripcion
        FROM EJEMPLARES e
        JOIN LIBROS l ON e.id_libro = l.id_libro
        WHERE e.id_ejemplar NOT IN (SELECT id_ejemplar FROM PRESTAMOS WHERE devuelto = 0)
        ORDER BY l.titulo
    ");
    
    $alumnos = $conn->query("
        SELECT id_alumno, CONCAT(nombre, ' ', apellidos) as nombre_completo
        FROM ALUMNOS
        ORDER BY apellidos, nombre
    ");
    
    echo "<h3>" . ($id ? "Editar" : "Nuevo") . " Préstamo</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='id' value='$id'>";
    
    echo "Ejemplar:<br>";
    echo "<select name='id_ejemplar' required>";
    echo "<option value=''>-- Seleccionar --</option>";
    while ($row = $ejemplares->fetch_assoc()) {
        $selected = ($data['id_ejemplar'] ?? '') == $row['id_ejemplar'] ? 'selected' : '';
        echo "<option value='{$row['id_ejemplar']}' $selected>{$row['descripcion']}</option>";
    }
    echo "</select><br><br>";
    
    echo "Alumno:<br>";
    echo "<select name='id_alumno' required>";
    echo "<option value=''>-- Seleccionar --</option>";
    while ($row = $alumnos->fetch_assoc()) {
        $selected = ($data['id_alumno'] ?? '') == $row['id_alumno'] ? 'selected' : '';
        echo "<option value='{$row['id_alumno']}' $selected>{$row['nombre_completo']}</option>";
    }
    echo "</select><br><br>";
    
    echo "Fecha préstamo:<br>";
    echo "<input type='date' name='fecha_prestamo' value='" . ($data['fecha_prestamo'] ?? date('Y-m-d')) . "' required><br><br>";
    
    echo "Fecha límite:<br>";
    echo "<input type='date' name='fecha_limite_devolucion' value='" . ($data['fecha_limite_devolucion'] ?? date('Y-m-d', strtotime('+15 days'))) . "' required><br><br>";
    
    echo "<input type='checkbox' name='devuelto' value='1' " . (isset($data['devuelto']) && $data['devuelto'] ? 'checked' : '') . "> Devuelto<br><br>";
    
    echo "<button type='submit'>Guardar</button> ";
    echo "<a href='prestamos.php'>Cancelar</a>";
    echo "</form>";
}

// Mostrar formulario para devolver libro
function mostrar_formulario_devolver($conn) {
    $prestamos = $conn->query("
        SELECT p.id_prestamo, CONCAT(l.titulo, ' - ', a.nombre, ' ', a.apellidos) as descripcion
        FROM PRESTAMOS p
        JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
        JOIN LIBROS l ON e.id_libro = l.id_libro
        JOIN ALUMNOS a ON p.id_alumno = a.id_alumno
        WHERE p.devuelto = 0
        ORDER BY p.fecha_prestamo DESC
    ");
    
    echo "<h3>Devolver Libro</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='accion_devolver' value='1'>";
    
    echo "Préstamo:<br>";
    echo "<select name='id_prestamo_devolver' required>";
    echo "<option value=''>-- Seleccionar préstamo --</option>";
    while ($row = $prestamos->fetch_assoc()) {
        echo "<option value='{$row['id_prestamo']}'>{$row['descripcion']}</option>";
    }
    echo "</select><br><br>";
    
    echo "Fecha devolución:<br>";
    echo "<input type='date' name='fecha_devolucion' value='" . date('Y-m-d') . "' required><br><br>";
    
    echo "<button type='submit'>Registrar Devolución</button> ";
    echo "<a href='prestamos.php'>Cancelar</a>";
    echo "</form>";
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar devolución
    if (isset($_POST['accion_devolver'])) {
        $id_prestamo = intval($_POST['id_prestamo_devolver']);
        $fecha_devolucion = $conn->real_escape_string($_POST['fecha_devolucion']);
        
        $sql = "UPDATE PRESTAMOS SET 
                fecha_devolucion_real = '$fecha_devolucion',
                devuelto = 1
                WHERE id_prestamo = $id_prestamo";
        
        $conn->query($sql);
        header("Location: prestamos.php");
        exit;
    }
    
    // Procesar nuevo/editar préstamo
    $id_post = intval($_POST['id'] ?? 0);
    
    $id_ejemplar = $conn->real_escape_string($_POST['id_ejemplar']);
    $id_alumno = $conn->real_escape_string($_POST['id_alumno']);
    $fecha_prestamo = $conn->real_escape_string($_POST['fecha_prestamo']);
    $fecha_limite = $conn->real_escape_string($_POST['fecha_limite_devolucion']);
    $devuelto = isset($_POST['devuelto']) ? 1 : 0;
    
    if ($id_post) {
        $sql = "UPDATE PRESTAMOS SET 
                id_ejemplar = '$id_ejemplar',
                id_alumno = '$id_alumno',
                fecha_prestamo = '$fecha_prestamo',
                fecha_limite_devolucion = '$fecha_limite',
                devuelto = $devuelto
                WHERE id_prestamo = $id_post";
    } else {
        $sql = "INSERT INTO PRESTAMOS (id_ejemplar, id_alumno, fecha_prestamo, fecha_limite_devolucion, devuelto)
                VALUES ('$id_ejemplar', '$id_alumno', '$fecha_prestamo', '$fecha_limite', $devuelto)";
    }
    
    $conn->query($sql);
    header("Location: prestamos.php");
    exit;
}

// Mostrar tabla de préstamos activos SIN RETRASO
function mostrar_tabla_activos($conn, $orden = null, $direccion = 'ASC') {
    $sql = "SELECT p.id_prestamo, l.titulo, CONCAT(a.nombre, ' ', a.apellidos) alumno, 
                   p.fecha_prestamo, p.fecha_limite_devolucion,
                   DATEDIFF(p.fecha_limite_devolucion, CURDATE()) as dias_restantes
            FROM PRESTAMOS p
            JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
            JOIN LIBROS l ON e.id_libro = l.id_libro
            JOIN ALUMNOS a ON p.id_alumno = a.id_alumno
            WHERE p.devuelto = 0 AND p.fecha_limite_devolucion >= CURDATE()";
    
    $campos = ['titulo', 'alumno', 'fecha_prestamo', 'fecha_limite_devolucion'];
    
    if ($orden && in_array($orden, $campos)) {
        $sql .= " ORDER BY $orden $direccion";
    } else {
        $sql .= " ORDER BY p.fecha_limite_devolucion ASC";
    }
    
    $result = $conn->query($sql);
    
    echo "<table border='1'>";
    
    $headers = ['Libro', 'Alumno', 'Préstamo', 'Límite', 'Días', 'Estado', 'Acciones'];
    echo "<tr>";
    foreach ($headers as $i => $header) {
        echo "<th>";
        if ($i < count($campos)) {
            $field = $campos[$i];
            $url = get_sort_url($field, $orden, $direccion);
            $flecha = $orden === $field ? ($direccion === 'ASC' ? ' ↑' : ' ↓') : '';
            echo "<a href='$url'>$header$flecha</a>";
        } else {
            echo $header;
        }
        echo "</th>";
    }
    echo "</tr>";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['titulo']) . "</td>";
            echo "<td>" . htmlspecialchars($row['alumno']) . "</td>";
            echo "<td>" . $row['fecha_prestamo'] . "</td>";
            echo "<td>" . $row['fecha_limite_devolucion'] . "</td>";
            
            $dias = $row['dias_restantes'];
            echo "<td>$dias</td>";
            
            if ($dias <= 3) {
                echo "<td>Próximo a vencer</td>";
            } else {
                echo "<td>En tiempo</td>";
            }
            
            $id_value = $row['id_prestamo'];
            echo "<td>";
            echo "<a href='?accion=editar&id=$id_value'>Editar</a> | ";
            echo "<a href='?eliminar=1&id=$id_value' onclick='return confirm(\"¿Eliminar?\")'>Eliminar</a>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7'>No hay préstamos activos</td></tr>";
    }
    
    echo "</table>";
}

// Mostrar tabla de préstamos retrasados
function mostrar_tabla_retrasados($conn) {
    $sql = "SELECT p.id_prestamo, l.titulo, CONCAT(a.nombre, ' ', a.apellidos) alumno, 
                   p.fecha_prestamo, p.fecha_limite_devolucion,
                   DATEDIFF(CURDATE(), p.fecha_limite_devolucion) as dias_retraso
            FROM PRESTAMOS p
            JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
            JOIN LIBROS l ON e.id_libro = l.id_libro
            JOIN ALUMNOS a ON p.id_alumno = a.id_alumno
            WHERE p.devuelto = 0 AND p.fecha_limite_devolucion < CURDATE()
            ORDER BY dias_retraso DESC";
    
    $result = $conn->query($sql);
    
    echo "<table border='1'>";
    echo "<tr><th>Libro</th><th>Alumno</th><th>Préstamo</th><th>Límite</th><th>Días Retrasado</th><th>Acciones</th></tr>";
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['titulo']) . "</td>";
            echo "<td>" . htmlspecialchars($row['alumno']) . "</td>";
            echo "<td>" . $row['fecha_prestamo'] . "</td>";
            echo "<td>" . $row['fecha_limite_devolucion'] . "</td>";
            echo "<td>" . $row['dias_retraso'] . " días</td>";
            
            $id_value = $row['id_prestamo'];
            echo "<td>";
            echo "<a href='?accion=editar&id=$id_value'>Editar</a> | ";
            echo "<a href='?eliminar=1&id=$id_value' onclick='return confirm(\"¿Eliminar?\")'>Eliminar</a>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No hay préstamos retrasados</td></tr>";
    }
    
    echo "</table>";
}

// Obtener datos para edición
$data_edicion = [];
if ($accion === 'editar' && $id > 0) {
    $result = $conn->query("SELECT * FROM PRESTAMOS WHERE id_prestamo = $id");
    $data_edicion = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Préstamos</title>
</head>
<body>
    <h1>Gestión de Préstamos</h1>
    
    <div>
        <a href="?accion=nuevo">+ Nuevo Préstamo</a>
        <a href="?accion=devolver">↩ Devolver Libro</a>
    </div>
    
    <?php if ($accion === 'devolver'): ?>
        <?php mostrar_formulario_devolver($conn); ?>
    <?php endif; ?>
    
    <?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
        <?php mostrar_formulario_prestamo($conn, $data_edicion, $id); ?>
    <?php endif; ?>
    
    <h2>Préstamos en Curso</h2>
    <?php mostrar_tabla_activos($conn, $orden, $direccion); ?>
    
    <h2>Préstamos Retrasados</h2>
    <?php mostrar_tabla_retrasados($conn); ?>
    
    <?php
    $stats = $conn->query("
        SELECT 
            COUNT(*) as total_prestamos,
            SUM(devuelto = 0) as prestamos_activos,
            SUM(devuelto = 0 AND fecha_limite_devolucion < CURDATE()) as prestamos_retrasados,
            SUM(devuelto = 1) as prestamos_devueltos
        FROM PRESTAMOS
    ");
    $stat = $stats->fetch_assoc();
    ?>
    
    <div>
        <h3>Estadísticas</h3>
        <p>Total: <?= $stat['total_prestamos'] ?></p>
        <p>En curso: <?= $stat['prestamos_activos'] ?></p>
        <p>Retrasados: <?= $stat['prestamos_retrasados'] ?></p>
        <p>Devueltos: <?= $stat['prestamos_devueltos'] ?></p>
    </div>
    
</body>
</html>
<?php $conn->close(); ?>