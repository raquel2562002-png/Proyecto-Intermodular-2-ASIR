<?php
require_once 'db.php'; // Importar conexión

// ===== PERSONALIZACIONES: NOMBRES DE TABLAS =====
// Array que mapea nombres de tablas reales con nombres más legibles
// Ejemplo: en la BD se llama "EJEMPLARES" pero queremos mostrar "Ejemplares"
$table_aliases = [
    'LIBROS'      => 'Libros',
    'EJEMPLARES'  => 'Ejemplares',
    'ALUMNOS'     => 'Alumnos',
    'PRESTAMOS'   => 'Préstamos',
];

// ===== PERSONALIZACIONES: NOMBRES DE COLUMNAS =====
// Array que mapea nombres de columnas reales con nombres más legibles
// Ejemplo: "id_libro" se muestra como "ID Libro"
$column_aliases = [
    'id_libro'          => 'ID Libro',
    'id_ejemplar'       => 'ID Ejemplar',
    'id_alumno'         => 'ID Alumno',
    'id_prestamo'       => 'ID Préstamo',
    'titulo'            => 'Título',
    'autor'             => 'Autor',
    'editorial'         => 'Editorial',
    'isbn'              => 'ISBN',
    'categoria'         => 'Categoría',
    'codigo_inventario' => 'Código Inventario',
    'disponible'        => 'Disponible',
    'estado_fisico'     => 'Estado Físico',
    'ubicacion'         => 'Ubicación',
    'nombre'            => 'Nombre',
    'apellidos'         => 'Apellidos',
    'dni'               => 'DNI',
    'email'             => 'Email',
    'curso'             => 'Curso',
    'fecha_registro'    => 'Fecha Registro',
    'antecedentes'      => 'Antecedentes',
    'fecha_prestamo'    => 'Fecha Préstamo',
    'fecha_devolucion'  => 'Fecha Devolución',
    'estado'            => 'Estado',
];

// ===== FUNCIONES AUXILIARES =====
// Función para obtener el nombre legible de una tabla
// Si existe en $table_aliases, lo devuelve; si no, devuelve el nombre real
function get_table_alias(string $table) {
    global $table_aliases;
    return isset($table_aliases[$table]) ? $table_aliases[$table] : $table;
}

// Función para obtener el nombre legible de una columna
// Si existe en $column_aliases, lo devuelve; si no, lo crea automáticamente
// Ejemplo: "id_libro" → "Id Libro"
function get_column_alias(string $column) {
    global $column_aliases;
    return isset($column_aliases[$column]) ? $column_aliases[$column] : ucfirst(str_replace('_', ' ', $column));
}

// Función para obtener las claves foráneas (FK) de una tabla
// Las FK son referencias a otras tablas
// Ejemplo: EJEMPLARES tiene id_libro que apunta a LIBROS
function get_foreign_keys(mysqli $conn, string $table) {
    $fks = []; // Array para guardar las FK encontradas
    
    // Consulta a INFORMATION_SCHEMA para obtener info de la BD
    $res = $conn->query("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '{$conn->real_escape_string($table)}'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if ($res) {
        // Recorrer cada FK encontrada
        while ($row = $res->fetch_assoc()) {
            $fks[$row['COLUMN_NAME']] = [
                'ref_table' => $row['REFERENCED_TABLE_NAME'],   // Tabla que referencia
                'ref_column' => $row['REFERENCED_COLUMN_NAME']  // Columna de esa tabla
            ];
        }
        $res->free();
    }
    return $fks;
}

// Función para obtener la columna más legible de una tabla
// Prioritiza: titulo > nombre > descripcion > codigo_inventario
// Si no encuentra ninguna, usa la primera columna que no sea ID
function get_readable_column(mysqli $conn, string $table) {
    $readable_cols = ['titulo', 'nombre', 'descripcion', 'codigo_inventario'];
    
    // Obtener todas las columnas de la tabla
    $res = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($table)}`");
    if ($res) {
        $columns = [];
        while ($col = $res->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        $res->free();
        
        // Buscar si existe alguna columna preferida
        foreach ($readable_cols as $col) {
            if (in_array($col, $columns)) {
                return $col;
            }
        }
        
        // Si no, buscar la primera que no sea ID
        foreach ($columns as $col) {
            if (strpos($col, 'id') === false) {
                return $col;
            }
        }
        
        // Si todo falla, devolver la primera columna
        return $columns[0] ?? null;
    }
    return null;
}

// Función para construir una consulta SQL con JOINs automáticos
// Esto permite mostrar nombres en lugar de números de ID
// Ejemplo: en EJEMPLARES, en lugar de "id_libro: 1" muestra "id_libro_display: El Quijote"
function build_query_with_joins(mysqli $conn, string $table) {
    $fks = get_foreign_keys($conn, $table); // Obtener las FK
    
    $query = "SELECT t.*"; // SELECT todo de la tabla principal (t)
    $from = " FROM `{$conn->real_escape_string($table)}` t"; // FROM tabla principal
    $join_idx = 0; // Contador para crear alias únicos
    
    // Por cada FK, crear un JOIN
    foreach ($fks as $fk_col => $fk_info) {
        $ref_table = $fk_info['ref_table'];      // Tabla que referencia
        $ref_column = $fk_info['ref_column'];    // Columna de esa tabla
        $readable_col = get_readable_column($conn, $ref_table); // Columna legible
        
        if ($readable_col) {
            $alias = "j{$join_idx}"; // Alias: j0, j1, j2...
            // Agregar a SELECT el valor legible de la tabla relacionada
            $query .= ", {$alias}.{$readable_col} AS {$fk_col}_display";
            // Agregar JOIN a la otra tabla
            $from .= " LEFT JOIN `{$conn->real_escape_string($ref_table)}` {$alias} ON t.{$fk_col} = {$alias}.{$ref_column}";
            $join_idx++;
        }
    }
    
    return $query . $from; // Devolver la consulta completa
}

// Función para mostrar una tabla en HTML
// Hace JOINs automáticos para mostrar datos legibles
function dump_table(mysqli $conn, string $table) {
    // Construir la consulta con JOINs
    $query = build_query_with_joins($conn, $table);
    $res = $conn->query($query); // Ejecutar la consulta
    
    // Si hay error, mostrarlo
    if (!$res) {
        echo "<p>Error al consultar la tabla " . htmlspecialchars($table) . ": " . htmlspecialchars($conn->error) . "</p>";
        return;
    }

    // Obtener las columnas de la tabla
    $fields = $res->fetch_fields();
    if (empty($fields)) {
        echo "<p>Sin columnas en " . htmlspecialchars($table) . ".</p>";
        $res->free();
        return;
    }

    // Mostrar título de la tabla (con nombre legible)
    echo '<h3>Tabla: ' . htmlspecialchars(get_table_alias($table)) . '</h3>';
    
    // Iniciar tabla HTML
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;width:100%;">';
    
    // Crear la cabecera con nombres legibles
    echo '<thead><tr>';
    foreach ($fields as $f) {
        $header = get_column_alias($f->name); // Obtener nombre legible
        
        // Si es columna _display (viene del JOIN), ponerle "(Nombre)"
        if (strpos($f->name, '_display') !== false) {
            $fk_col = str_replace('_display', '', $f->name);
            $header = get_column_alias($fk_col) . ' (Nombre)';
        }
        echo '<th>' . $header . '</th>';
    }
    echo '</tr></thead>';
    
    // Mostrar los datos de la tabla
    echo '<tbody>';
    $rowCount = 0; // Contador de filas
    
    while ($row = $res->fetch_assoc()) { // Recorrer cada fila
        $rowCount++;
        echo '<tr>';
        foreach ($fields as $f) {
            // Obtener el valor de la celda
            $val = isset($row[$f->name]) ? $row[$f->name] : '';
            // Mostrar el valor escapado (seguridad contra XSS)
            echo '<td>' . htmlspecialchars((string)$val) . '</td>';
        }
        echo '</tr>';
    }
    
    // Si no hay filas, mostrar mensaje
    if ($rowCount === 0) {
        echo '<tr><td colspan="' . count($fields) . '">No hay filas en esta tabla.</td></tr>';
    }
    echo '</tbody></table>';
    $res->free(); // Liberar memoria
}

// ===== LÓGICA: OBTENER TABLAS DE LA BD =====
// Ejecutar consulta para obtener lista de todas las tablas
$tables_res = $conn->query("SHOW TABLES");
if (!$tables_res) {
    die("<p>Error al listar tablas: " . htmlspecialchars($conn->error) . "</p>");
}

// Guardar los nombres de las tablas en un array
$tables = [];
while ($trow = $tables_res->fetch_array(MYSQLI_NUM)) {
    $tables[] = $trow[0]; // $trow[0] es el nombre de la tabla
}

// ===== LÓGICA: VALIDAR SELECCIÓN DEL USUARIO =====
// Obtener la tabla seleccionada del formulario (si existe)
$selected_table = isset($_GET['table']) ? $_GET['table'] : '';

// Validar que la tabla seleccionada existe en la BD (seguridad)
if ($selected_table !== '' && !in_array($selected_table, $tables, true)) {
    $selected_table = ''; // Si no existe, ignorar selección
}
?>
<!-- ===== HTML: PRESENTACIÓN EN NAVEGADOR ===== -->
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Contenido DB - <?php echo htmlspecialchars($database); ?></title>
  <!-- <link rel="stylesheet" href="styles.css"> Cargar estilos CSS -->
</head>
<body>
  <!-- CABECERA -->
  <div class="header">
    <div class="container">
      <div class="brand">
        <div class="title">Base de datos: <?php echo htmlspecialchars($database); ?></div>
      </div>
    </div>
  </div>

  <!-- CONTENIDO PRINCIPAL -->
  <div class="container">
    <div class="card">
      <h2>Volcado de tablas</h2>

      <!-- FORMULARIO: Selector de tabla -->
      <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="margin-bottom:12px;">
        <label for="table_select" style="font-weight:600;display:block;margin-bottom:6px;">Selecciona tabla a visualizar</label>
        <!-- Select que se envía automáticamente al cambiar (onchange) -->
        <select id="table_select" name="table" style="padding:8px;border-radius:6px;border:1px solid #d1c4b8;" onchange="this.form.submit()">
          <option value="">-- Todas las tablas --</option>
          <!-- Crear opción para cada tabla de la BD -->
          <?php foreach ($tables as $t): ?>
            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($t === $selected_table) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($t); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <!-- MOSTRAR TABLAS -->
      <?php
      // Si el usuario seleccionó una tabla, mostrar solo esa
      if ($selected_table !== '') {
          dump_table($conn, $selected_table);
      } else {
          // Si no seleccionó nada, mostrar todas las tablas
          foreach ($tables as $table) {
              dump_table($conn, $table);
          }
      }

      // Si no hay tablas, mostrar mensaje
      if (empty($tables)) {
          echo '<p>No hay tablas en la base de datos.</p>';
      }
      ?>
    </div> <!-- Fin de card -->

    <!-- PIE DE PÁGINA -->
    <div class="footer">
      Proyecto Intermodular – ASIR
    </div>
  </div> <!-- Fin de container -->

<?php
// Cerrar la conexión con la base de datos
$conn->close();
?>
</body>
</html>