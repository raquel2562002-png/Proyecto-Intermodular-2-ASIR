<?php
require_once 'db.php';

class TableManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function displayLibros($search = '') {
        $query = "SELECT 
                    l.titulo, 
                    l.autor, 
                    l.editorial, 
                    l.isbn, 
                    l.categoria,
                    COUNT(e.id_ejemplar) as total_ejemplares,
                    SUM(CASE WHEN e.disponible = TRUE THEN 1 ELSE 0 END) as ejemplares_disponibles
                  FROM LIBROS l
                  LEFT JOIN EJEMPLARES e ON l.id_libro = e.id_libro
                  GROUP BY l.id_libro";
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " HAVING titulo LIKE '%{$searchEscaped}%' OR autor LIKE '%{$searchEscaped}%' OR editorial LIKE '%{$searchEscaped}%' OR isbn LIKE '%{$searchEscaped}%' OR categoria LIKE '%{$searchEscaped}%'";
        }
        
        $this->displayTable('LIBROS', $query, ['Título', 'Autor', 'Editorial', 'ISBN', 'Categoría', 'Total Ejemplares', 'Ejemplares Disponibles']);
    }
    
    public function displayEjemplares($search = '') {
        $query = "SELECT 
                    e.codigo_inventario, 
                    l.titulo as libro,
                    e.disponible, 
                    e.estado_fisico, 
                    e.ubicacion 
                  FROM EJEMPLARES e 
                  JOIN LIBROS l ON e.id_libro = l.id_libro";
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " WHERE e.codigo_inventario LIKE '%{$searchEscaped}%' OR l.titulo LIKE '%{$searchEscaped}%' OR e.estado_fisico LIKE '%{$searchEscaped}%' OR e.ubicacion LIKE '%{$searchEscaped}%'";
        }
        
        $this->displayTable('EJEMPLARES', $query, ['Código Inventario', 'Libro', 'Disponible', 'Estado Físico', 'Ubicación']);
    }
    
    public function displayAlumnos($search = '') {
        $query = "SELECT 
                    a.nombre, 
                    a.apellidos, 
                    a.dni, 
                    a.email, 
                    a.curso, 
                    a.fecha_registro,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM PRESTAMOS p 
                            WHERE p.id_alumno = a.id_alumno 
                            AND p.devuelto = FALSE
                        ) THEN 'Sí' 
                        ELSE 'No' 
                    END as prestamo_activo,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM PRESTAMOS p 
                            WHERE p.id_alumno = a.id_alumno 
                            AND p.devuelto = FALSE 
                            AND p.fecha_devolucion < CURDATE()
                        ) THEN 'Sí' 
                        ELSE 'No' 
                    END as prestamo_retrasado
                  FROM ALUMNOS a";
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " WHERE a.nombre LIKE '%{$searchEscaped}%' OR a.apellidos LIKE '%{$searchEscaped}%' OR a.dni LIKE '%{$searchEscaped}%' OR a.email LIKE '%{$searchEscaped}%' OR a.curso LIKE '%{$searchEscaped}%'";
        }
        
        $this->displayTable('ALUMNOS', $query, ['Nombre', 'Apellidos', 'DNI', 'Email', 'Curso', 'Fecha Registro', 'Préstamo Activo', 'Préstamo Retrasado']);
    }
    
    public function displayPrestamos($search = '') {
        $query = "SELECT 
                    l.titulo as libro,
                    a.nombre as alumno_nombre,
                    a.apellidos as alumno_apellidos,
                    p.fecha_prestamo,
                    p.fecha_devolucion,
                    p.devuelto,
                    CASE 
                        WHEN p.devuelto = FALSE AND p.fecha_devolucion < CURDATE() THEN 'Sí'
                        ELSE 'No'
                    END as retrasado
                  FROM PRESTAMOS p
                  JOIN EJEMPLARES e ON p.id_ejemplar = e.id_ejemplar
                  JOIN LIBROS l ON e.id_libro = l.id_libro
                  JOIN ALUMNOS a ON p.id_alumno = a.id_alumno";
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " WHERE l.titulo LIKE '%{$searchEscaped}%' OR a.nombre LIKE '%{$searchEscaped}%' OR a.apellidos LIKE '%{$searchEscaped}%'";
        }
        
        $this->displayTable('PRÉSTAMOS', $query, ['Libro', 'Alumno Nombre', 'Alumno Apellidos', 'Fecha Préstamo', 'Fecha Devolución', 'Devuelto', 'Retrasado']);
    }
    
    private function displayTable($tableName, $query, $headers) {
        $res = $this->conn->query($query);
        
        if (!$res) {
            echo "<p>Error: " . htmlspecialchars($this->conn->error) . "</p>";
            return;
        }
        
        echo '<h3>Tabla: ' . htmlspecialchars($tableName) . '</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;width:100%;">';
        
        // Cabecera
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr></thead>';
        
        // Filas
        echo '<tbody>';
        $rowCount = 0;
        
        while ($row = $res->fetch_assoc()) {
            $rowCount++;
            echo '<tr>';
            foreach ($row as $key => $value) {
                // Solo formatear como Sí/No las columnas booleanas, dejar números como números
                $formattedValue = $this->formatValue($value, $key);
                echo '<td>' . htmlspecialchars($formattedValue) . '</td>';
            }
            echo '</tr>';
        }
        
        if ($rowCount === 0) {
            echo '<tr><td colspan="' . count($headers) . '">No hay filas</td></tr>';
        }
        
        echo '</tbody></table>';
        $res->free();
    }
    
    private function formatValue($value, $columnName = '') {
        // Si es numérico y no es una columna booleana específica, dejarlo como número
        if (is_numeric($value) && !in_array($columnName, ['disponible', 'devuelto'])) {
            return $value;
        }
        
        // Solo convertir a Sí/No las columnas booleanas
        if ($value === true || $value == 1) return 'Sí';
        if ($value === false || $value == 0) return 'No';
        
        return $value;
    }
}

// Tablas conocidas
$tables = ['LIBROS', 'EJEMPLARES', 'ALUMNOS', 'PRESTAMOS'];

$selectedTable = $_GET['table'] ?? '';
if ($selectedTable && !in_array($selectedTable, $tables, true)) {
    $selectedTable = '';
}

$search = $_GET['search'] ?? '';

$tableManager = new TableManager($conn);
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Base de datos: <?php echo htmlspecialchars($database); ?></title>
</head>
<body>
  <h1>Base de datos: <?php echo htmlspecialchars($database); ?></h1>
  
  <form method="get">
    <select name="table" onchange="this.form.submit()">
      <option value="">-- Todas las tablas --</option>
      <?php foreach ($tables as $t): ?>
        <option value="<?php echo htmlspecialchars($t); ?>" <?= $t === $selectedTable ? 'selected' : '' ?>>
          <?php echo htmlspecialchars($t); ?>
        </option>
      <?php endforeach; ?>
    </select>
    
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar...">
    <button type="submit">Buscar</button>
    
    <?php if (!empty($search)): ?>
      <a href="?<?php echo $selectedTable ? 'table=' . htmlspecialchars($selectedTable) : ''; ?>">Limpiar</a>
    <?php endif; ?>
  </form>

  <?php
  if ($selectedTable) {
      switch ($selectedTable) {
          case 'LIBROS': $tableManager->displayLibros($search); break;
          case 'EJEMPLARES': $tableManager->displayEjemplares($search); break;
          case 'ALUMNOS': $tableManager->displayAlumnos($search); break;
          case 'PRESTAMOS': $tableManager->displayPrestamos($search); break;
      }
  } else {
      $tableManager->displayLibros($search);
      $tableManager->displayEjemplares($search);
      $tableManager->displayAlumnos($search);
      $tableManager->displayPrestamos($search);
  }
  
  $conn->close();
  ?>
</body>
</html>