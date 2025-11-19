<?php
require_once 'db.php';

class TableManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function displayLibros($search = '', $orderBy = '', $orderDir = 'ASC') {
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
        
        // Solo ordenar si la columna pertenece a esta tabla
        $allowedColumns = ['titulo', 'autor', 'editorial', 'isbn', 'categoria', 'total_ejemplares', 'ejemplares_disponibles'];
        if (!empty($orderBy) && in_array($orderBy, $allowedColumns)) {
            $safeOrderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$orderBy} {$safeOrderDir}";
        }
        
        $headers = [
            'titulo' => 'Título',
            'autor' => 'Autor', 
            'editorial' => 'Editorial',
            'isbn' => 'ISBN',
            'categoria' => 'Categoría',
            'total_ejemplares' => 'Total Ejemplares',
            'ejemplares_disponibles' => 'Ejemplares Disponibles'
        ];
        
        $this->displayTable('LIBROS', $query, $headers, $orderBy, $orderDir);
    }
    
    public function displayEjemplares($search = '', $orderBy = '', $orderDir = 'ASC') {
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
        
        // Solo ordenar si la columna pertenece a esta tabla
        $allowedColumns = ['codigo_inventario', 'libro', 'disponible', 'estado_fisico', 'ubicacion'];
        if (!empty($orderBy) && in_array($orderBy, $allowedColumns)) {
            $safeOrderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$orderBy} {$safeOrderDir}";
        }
        
        $headers = [
            'codigo_inventario' => 'Código Inventario',
            'libro' => 'Libro',
            'disponible' => 'Disponible', 
            'estado_fisico' => 'Estado Físico',
            'ubicacion' => 'Ubicación'
        ];
        
        $this->displayTable('EJEMPLARES', $query, $headers, $orderBy, $orderDir);
    }
    
    public function displayAlumnos($search = '', $orderBy = '', $orderDir = 'ASC') {
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
        
        // Solo ordenar si la columna pertenece a esta tabla
        $allowedColumns = ['nombre', 'apellidos', 'dni', 'email', 'curso', 'fecha_registro', 'prestamo_activo', 'prestamo_retrasado'];
        if (!empty($orderBy) && in_array($orderBy, $allowedColumns)) {
            $safeOrderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$orderBy} {$safeOrderDir}";
        }
        
        $headers = [
            'nombre' => 'Nombre',
            'apellidos' => 'Apellidos',
            'dni' => 'DNI',
            'email' => 'Email',
            'curso' => 'Curso',
            'fecha_registro' => 'Fecha Registro',
            'prestamo_activo' => 'Préstamo Activo',
            'prestamo_retrasado' => 'Préstamo Retrasado'
        ];
        
        $this->displayTable('ALUMNOS', $query, $headers, $orderBy, $orderDir);
    }
    
    public function displayPrestamos($search = '', $orderBy = '', $orderDir = 'ASC') {
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
        
        // Solo ordenar si la columna pertenece a esta tabla
        $allowedColumns = ['libro', 'alumno_nombre', 'alumno_apellidos', 'fecha_prestamo', 'fecha_devolucion', 'devuelto', 'retrasado'];
        if (!empty($orderBy) && in_array($orderBy, $allowedColumns)) {
            $safeOrderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$orderBy} {$safeOrderDir}";
        }
        
        $headers = [
            'libro' => 'Libro',
            'alumno_nombre' => 'Alumno Nombre',
            'alumno_apellidos' => 'Alumno Apellidos',
            'fecha_prestamo' => 'Fecha Préstamo',
            'fecha_devolucion' => 'Fecha Devolución',
            'devuelto' => 'Devuelto',
            'retrasado' => 'Retrasado'
        ];
        
        $this->displayTable('PRÉSTAMOS', $query, $headers, $orderBy, $orderDir);
    }
    
    private function displayTable($tableName, $query, $headers, $currentOrderBy = '', $currentOrderDir = 'ASC') {
        $res = $this->conn->query($query);
        
        if (!$res) {
            echo "<p>Error: " . htmlspecialchars($this->conn->error) . "</p>";
            return;
        }
        
        echo '<h3>Tabla: ' . htmlspecialchars($tableName) . '</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;width:100%;">';
        
        // Cabecera con enlaces de ordenamiento
        echo '<thead><tr>';
        foreach ($headers as $columnKey => $headerText) {
            $nextDir = 'ASC';
            if ($currentOrderBy === $columnKey && $currentOrderDir === 'ASC') {
                $nextDir = 'DESC';
            }
            
            $arrow = '';
            if ($currentOrderBy === $columnKey) {
                $arrow = $currentOrderDir === 'ASC' ? ' ↑' : ' ↓';
            }
            
            // Pasar siempre los parámetros de ordenamiento
            $params = $_GET;
            $params['orderBy'] = $columnKey;
            $params['orderDir'] = $nextDir;
            $url = '?' . http_build_query($params);
            
            echo '<th><a href="' . htmlspecialchars($url) . '" style="text-decoration:none;color:inherit;">' . 
                 htmlspecialchars($headerText) . $arrow . '</a></th>';
        }
        echo '</tr></thead>';
        
        // Filas
        echo '<tbody>';
        $rowCount = 0;
        
        while ($row = $res->fetch_assoc()) {
            $rowCount++;
            echo '<tr>';
            foreach ($headers as $columnKey => $headerText) {
                $value = $row[$columnKey] ?? '';
                $formattedValue = $this->formatValue($value, $columnKey);
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
        if (is_numeric($value) && !in_array($columnName, ['disponible', 'devuelto'])) {
            return $value;
        }
        
        if ($value === true || $value == 1) return 'Sí';
        if ($value === false || $value == 0) return 'No';
        
        return $value;
    }
}

$tables = ['LIBROS', 'EJEMPLARES', 'ALUMNOS', 'PRESTAMOS'];

$selectedTable = $_GET['table'] ?? '';
if ($selectedTable && !in_array($selectedTable, $tables, true)) {
    $selectedTable = '';
}

$search = $_GET['search'] ?? '';
$orderBy = $_GET['orderBy'] ?? '';
$orderDir = $_GET['orderDir'] ?? 'ASC';

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
      <?php
      $clearUrl = '?' . http_build_query(array_filter([
          'table' => $selectedTable ?: null
      ]));
      ?>
      <a href="<?php echo htmlspecialchars($clearUrl); ?>">Limpiar</a>
    <?php endif; ?>
  </form>

  <?php
  if ($selectedTable) {
      switch ($selectedTable) {
          case 'LIBROS': $tableManager->displayLibros($search, $orderBy, $orderDir); break;
          case 'EJEMPLARES': $tableManager->displayEjemplares($search, $orderBy, $orderDir); break;
          case 'ALUMNOS': $tableManager->displayAlumnos($search, $orderBy, $orderDir); break;
          case 'PRESTAMOS': $tableManager->displayPrestamos($search, $orderBy, $orderDir); break;
      }
  } else {
      // Cuando se muestran todas las tablas, pasar orderBy/orderDir a cada una
      $tableManager->displayLibros($search, $orderBy, $orderDir);
      $tableManager->displayEjemplares($search, $orderBy, $orderDir);
      $tableManager->displayAlumnos($search, $orderBy, $orderDir);
      $tableManager->displayPrestamos($search, $orderBy, $orderDir);
  }
  
  $conn->close();
  ?>
</body>
</html>