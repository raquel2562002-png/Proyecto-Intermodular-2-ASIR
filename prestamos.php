<?php
require_once 'db.php';

$accion = $_GET['accion'] ?? 'ver';
$id = $_GET['id'] ?? 0;
$orden = $_GET['orden'] ?? null;
$direccion = $_GET['direccion'] ?? 'ASC';

// Procesar eliminaciones
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['id'];
    $conn->query("DELETE FROM PRESTAMOS WHERE id_prestamo = $id_eliminar");
    header("Location: prestamos.php");
    exit;
}

// Función para generar URL de ordenación
function get_sort_url($field, $orden_actual, $direccion_actual) {
    if ($orden_actual === $field) {
        if ($direccion_actual === 'ASC') {
            return "?orden=$field&direccion=DESC";
        } else {
            return "?"; // Tercer click: sin orden
        }
    } else {
        return "?orden=$field&direccion=ASC";
    }
}

// Mostrar formulario para agregar/editar préstamo
function mostrar_formulario_prestamo($conn, $data, $id = 0) {
    // Obtener ejemplares disponibles
    $ejemplares = $conn->query("
        SELECT e.id_ejemplar, CONCAT(l.titulo, ' - Ejemplar #', e.id_ejemplar, ' (', e.estado_fisico, ')') as descripcion
        FROM EJEMPLARES e
        JOIN LIBROS l ON e.id_libro = l.id_libro
        WHERE e.id_ejemplar NOT IN (SELECT id_ejemplar FROM PRESTAMOS WHERE devuelto = 0)
        ORDER BY l.titulo
    ");
    
    // Obtener alumnos
    $alumnos = $conn->query("
        SELECT id_alumno, CONCAT(nombre, ' ', apellidos, ' - ', curso) as nombre_completo
        FROM ALUMNOS
        ORDER BY apellidos, nombre
    ");
    
    echo "<h3>" . ($id ? "Editar" : "Nuevo") . " Préstamo</h3>";
    echo "<form method='post' style='border:1px solid #ccc;padding:10px;margin:10px 0;'>";
    echo "<input type='hidden' name='id' value='$id'>";
    
    // Ejemplar
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Ejemplar:</label>";
    echo "<select name='id_ejemplar' required>";
    echo "<option value=''>-- Seleccionar ejemplar --</option>";
    while ($row = $ejemplares->fetch_assoc()) {
        $selected = ($data['id_ejemplar'] ?? '') == $row['id_ejemplar'] ? 'selected' : '';
        echo "<option value='{$row['id_ejemplar']}' $selected>{$row['descripcion']}</option>";
    }
    echo "</select>";
    echo "</div>";
    
    // Alumno
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Alumno:</label>";
    echo "<select name='id_alumno' required>";
    echo "<option value=''>-- Seleccionar alumno --</option>";
    while ($row = $alumnos->fetch_assoc()) {
        $selected = ($data['id_alumno'] ?? '') == $row['id_alumno'] ? 'selected' : '';
        echo "<option value='{$row['id_alumno']}' $selected>{$row['nombre_completo']}</option>";
    }
    echo "</select>";
    echo "</div>";
    
    // Fechas
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Fecha préstamo:</label>";
    echo "<input type='date' name='fecha_prestamo' value='" . ($data['fecha_prestamo'] ?? date('Y-m-d')) . "' required>";
    echo "</div>";
    
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Fecha límite devolución:</label>";
    echo "<input type='date' name='fecha_limite_devolucion' value='" . ($data['fecha_limite_devolucion'] ?? date('Y-m-d', strtotime('+15 days'))) . "' required>";
    echo "</div>";
    
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Fecha devolución real:</label>";
    echo "<input type='date' name='fecha_devolucion_real' value='" . ($data['fecha_devolucion_real'] ?? '') . "'>";
    echo "</div>";
    
    // Devuelto
    echo "<div style='margin:5px 0;'>";
    echo "<label style='display:inline-block;width:200px;'>Devuelto:</label>";
    $checked = isset($data['devuelto']) && $data['devuelto'] ? 'checked' : '';
    echo "<input type='checkbox' name='devuelto' value='1' $checked>";
    echo "</div>";
    
    echo "<div style='margin-top:10px;'>";
    echo "<button type='submit'>Guardar</button> ";
    echo "<a href='prestamos.php'>Cancelar</a>";
    echo "</div>";
    echo "</form>";
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_post = $_POST['id'] ?? 0;
    
    $id_ejemplar = $conn->real_escape_string($_POST['id_ejemplar']);
    $id_alumno = $conn->real_escape_string($_POST['id_alumno']);
    $fecha_prestamo = $conn->real_escape_string($_POST['fecha_prestamo']);
    $fecha_limite = $conn->real_escape_string($_POST['fecha_limite_devolucion']);
    $fecha_devolucion = !empty($_POST['fecha_devolucion_real']) ? "'" . $conn->real_escape_string($_POST['fecha_devolucion_real']) . "'" : "NULL";
    $devuelto = isset($_POST['devuelto']) ? 1 : 0;
    
    if ($id_post) {
        // Actualizar
        $sql = "UPDATE PRESTAMOS SET 
                id_ejemplar = '$id_ejemplar',
                id_alumno = '$id_alumno',
                fecha_prestamo = '$fecha_prestamo',
                fecha_limite_devolucion = '$fecha_limite',
                fecha_devolucion_real = $fecha_devolucion,
                devuelto = $devuelto
                WHERE id_prestamo = $id_post";
    } else {
        // Insertar
        $sql = "INSERT INTO PRESTAMOS (id_ejemplar, id_alumno, fecha_prestamo, fecha_limite_devolucion, fecha_devolucion_real, devuelto)
                VALUES ('$id_ejemplar', '$id_alumno', '$fecha_prestamo', '$fecha_limite', $fecha_devolucion, $devuelto)";
    }
    
    $conn->query($sql);
    header("Location: prestamos.php");
    exit;
}

// Mostrar tabla de préstamos
function mostrar_tabla_prestamos($conn, $orden = null, $direccion = 'ASC') {
    $sql = "SELECT p.id_prestamo, l.titulo, CONCAT(a.nombre, ' ', a.apellidos) alumno, 
                   fecha_prestamo, fecha_limite_devolucion,
                   IFNULL(fecha_devolucion_real, 'No devuelto') devolucion, 
                   IF(devuelto, 'Sí', 'No') devuelto,
                   DATEDIFF(CURDATE(), fecha_limite_devolucion) as dias_retraso
            FROM PRESTAMOS p
            JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
            JOIN LIBROS l ON e.id_libro = l.id_libro
            JOIN ALUMNOS a ON p.id_alumno = a.id_alumno";
    
    // Campos ordenables
    $campos = ['titulo', 'alumno', 'fecha_prestamo', 'fecha_limite_devolucion', 'devolucion', 'devuelto'];
    
    if ($orden && in_array($orden, $campos)) {
        $sql .= " ORDER BY $orden $direccion";
    } else {
        $sql .= " ORDER BY fecha_prestamo DESC";
    }
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        echo "<p>No hay préstamos registrados</p>";
        return;
    }
    
    echo "<div style='overflow-x:auto;'>";
    echo "<table border='1' cellspacing='0' cellpadding='5' style='width:100%;'>";
    
    // Cabeceras
    $headers = ['Libro', 'Alumno', 'Préstamo', 'Límite', 'Devolución', 'Devuelto', 'Estado', 'Acciones'];
    echo "<tr>";
    foreach ($headers as $i => $header) {
        echo "<th>";
        
        // Solo columnas ordenables
        if ($i < count($campos)) {
            $field = $campos[$i];
            $url = get_sort_url($field, $orden, $direccion);
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
        
        echo "<td>" . $row['titulo'] . "</td>";
        echo "<td>" . $row['alumno'] . "</td>";
        echo "<td>" . $row['fecha_prestamo'] . "</td>";
        echo "<td>" . $row['fecha_limite_devolucion'] . "</td>";
        echo "<td>" . $row['devolucion'] . "</td>";
        echo "<td>" . $row['devuelto'] . "</td>";
        
        // Columna de estado
        echo "<td>";
        if ($row['devuelto'] == 'Sí') {
            echo "<span style='color:green;'>Devuelto</span>";
        } else if ($row['dias_retraso'] > 0) {
            echo "<span style='color:red;'>Retrasado (" . $row['dias_retraso'] . " días)</span>";
        } else {
            echo "<span style='color:orange;'>En préstamo</span>";
        }
        echo "</td>";
        
        // Columna de acciones
        $id_value = $row['id_prestamo'];
        echo "<td>";
        echo "<a href='?accion=editar&id=$id_value'>Editar</a> | ";
        echo "<a href='?eliminar=1&id=$id_value' onclick='return confirm(\"¿Seguro que quieres eliminar este préstamo?\")'>Eliminar</a>";
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
    <title>Gestión de Préstamos</title>
</head>
<body>
    <h1>Gestión de Préstamos - Biblioteca</h1>
    
    <!-- Botón para nuevo préstamo -->
    <?php if ($accion == 'ver'): ?>
        <div style="margin:20px 0;">
            <a href="?accion=agregar">+ Nuevo Préstamo</a>
        </div>
    <?php endif; ?>
    
    <!-- Mostrar formulario o tabla -->
    <?php if (in_array($accion, ['agregar', 'editar'])): ?>
        <?php
        $data = [];
        if ($accion == 'editar' && $id) {
            $result = $conn->query("SELECT * FROM PRESTAMOS WHERE id_prestamo = $id");
            $data = $result->fetch_assoc();
        }
        mostrar_formulario_prestamo($conn, $data, $id);
        ?>
    <?php else: ?>
        <h2>Lista de Préstamos</h2>
        <?php mostrar_tabla_prestamos($conn, $orden, $direccion); ?>
        
        <!-- Estadísticas -->
        <?php
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total_prestamos,
                SUM(devuelto = 0) as prestamos_activos,
                SUM(devuelto = 0 AND fecha_limite_devolucion < CURDATE()) as prestamos_retrasados
            FROM PRESTAMOS
        ");
        $stat = $stats->fetch_assoc();
        ?>
        
        <div style="margin-top:20px; border:1px solid #ccc; padding:10px;">
            <h3>Estadísticas</h3>
            <p><strong>Total préstamos:</strong> <?= $stat['total_prestamos'] ?></p>
            <p><strong>Préstamos activos:</strong> <?= $stat['prestamos_activos'] ?></p>
            <p><strong>Préstamos retrasados:</strong> <span style="color:red;"><?= $stat['prestamos_retrasados'] ?></span></p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top:20px;">
        <a href="admin.php">← Volver a administración completa</a>
    </div>
    
</body>
</html>
<?php $conn->close(); ?>