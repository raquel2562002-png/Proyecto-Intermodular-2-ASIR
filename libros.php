<?php
require_once 'db.php';

/**
 * GESTOR PRINCIPAL DE TABLAS
 * Esta clase se encarga de mostrar todas las tablas de la biblioteca
 * con funcionalidades de búsqueda y ordenamiento
 */
class TableManager {
    private $conn;  // Conexión a la base de datos
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
 
    /**
     * MUESTRA LA TABLA DE LIBROS con información de ejemplares
     * - Muestra cada libro con su título, autor, editorial, etc.
     * - Cuenta cuántos ejemplares tiene cada libro
     * - Cuenta cuántos ejemplares están disponibles
     */
    public function displayLibros($search = '', $orderBy = '', $orderDir = 'ASC') {
        // Consulta SQL que junta LIBROS con EJEMPLARES
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
                  GROUP BY l.id_libro";  // Agrupa por libro para contar ejemplares
        
        // AÑADIR BÚSQUEDA si el usuario escribió algo
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);  // Protege contra SQL injection
            $query .= " HAVING titulo LIKE '%{$searchEscaped}%' OR autor LIKE '%{$searchEscaped}%' OR editorial LIKE '%{$searchEscaped}%' OR isbn LIKE '%{$searchEscaped}%' OR categoria LIKE '%{$searchEscaped}%'";
        }
        
        // AÑADIR ORDENAMIENTO si el usuario eligió una columna válida
        $allowedColumns = ['titulo', 'autor', 'editorial', 'isbn', 'categoria', 'total_ejemplares', 'ejemplares_disponibles'];
        if (!empty($orderBy) && in_array($orderBy, $allowedColumns)) {
            $safeOrderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY {$orderBy} {$safeOrderDir}";
        }
        
        // CONFIGURACIÓN DE COLUMNAS: clave interna => texto para mostrar
        $headers = [
            'titulo' => 'Título',
            'autor' => 'Autor', 
            'editorial' => 'Editorial',
            'isbn' => 'ISBN',
            'categoria' => 'Categoría',
            'total_ejemplares' => 'Total Ejemplares',
            'ejemplares_disponibles' => 'Ejemplares Disponibles'
        ];
        
        // MOSTRAR LA TABLA usando la función reutilizable
        $this->displayTable('LIBROS', $query, $headers, $orderBy, $orderDir);
    }
    
    /**
     * MUESTRA LA TABLA DE EJEMPLARES
     * - Muestra cada ejemplar físico con su código de inventario
     * - Indica si está disponible o prestado
     * - Muestra el estado físico y ubicación
     */
    public function displayEjemplares($search = '', $orderBy = '', $orderDir = 'ASC') {
        $query = "SELECT 
                    e.codigo_inventario, 
                    l.titulo as libro,
                    e.disponible, 
                    e.estado_fisico, 
                    e.ubicacion 
                  FROM EJEMPLARES e 
                  JOIN LIBROS l ON e.id_libro = l.id_libro";  // Junta con LIBROS para saber qué libro es
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " WHERE e.codigo_inventario LIKE '%{$searchEscaped}%' OR l.titulo LIKE '%{$searchEscaped}%' OR e.estado_fisico LIKE '%{$searchEscaped}%' OR e.ubicacion LIKE '%{$searchEscaped}%'";
        }
        
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
    
    /**
     * MUESTRA LA TABLA DE ALUMNOS
     * - Información personal de cada alumno
     * - Calcula si tiene préstamos activos
     * - Calcula si tiene préstamos retrasados
     */
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
    
    /**
     * MUESTRA LA TABLA DE PRÉSTAMOS
     * - Muestra qué libro tiene prestado cada alumno
     * - Fechas de préstamo y devolución
     * - Indica si está devuelto o retrasado
     */
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
                  JOIN ALUMNOS a ON p.id_alumno = a.id_alumno";  // Junta 4 tablas!
        
        if (!empty($search)) {
            $searchEscaped = $this->conn->real_escape_string($search);
            $query .= " WHERE l.titulo LIKE '%{$searchEscaped}%' OR a.nombre LIKE '%{$searchEscaped}%' OR a.apellidos LIKE '%{$searchEscaped}%'";
        }
        
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
    
    /**
     * FUNCIÓN REUTILIZABLE PARA MOSTRAR CUALQUIER TABLA
     * Esta es la función más importante - hace todo el trabajo pesado:
     * - Ejecuta la consulta SQL
     * - Crea la tabla HTML con encabezados clickeables para ordenar
     * - Muestra los datos formateados
     */
    private function displayTable($tableName, $query, $headers, $currentOrderBy = '', $currentOrderDir = 'ASC') {
        // EJECUTAR LA CONSULTA SQL
        $res = $this->conn->query($query);
        
        if (!$res) {
            echo "<p>Error: " . htmlspecialchars($this->conn->error) . "</p>";
            return;
        }
        
        // MOSTRAR TÍTULO DE LA TABLA
        echo '<h3>Tabla: ' . htmlspecialchars($tableName) . '</h3>';
        echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:18px;width:100%;">';
        
        // CREAR ENCABEZADOS DE LA TABLA (clickeables para ordenar)
        echo '<thead><tr>';
        foreach ($headers as $columnKey => $headerText) {
            // Calcular la dirección del ordenamiento (si clickean otra vez, cambia)
            $nextDir = 'ASC';
            if ($currentOrderBy === $columnKey && $currentOrderDir === 'ASC') {
                $nextDir = 'DESC';
            }
            
            // Mostrar flecha ↑ o ↓ si esta columna está ordenada
            $arrow = '';
            if ($currentOrderBy === $columnKey) {
                $arrow = $currentOrderDir === 'ASC' ? ' ↑' : ' ↓';
            }
            
            // Crear URL con los parámetros para ordenar por esta columna
            $params = $_GET;  // Mantener todos los parámetros actuales
            $params['orderBy'] = $columnKey;
            $params['orderDir'] = $nextDir;
            $url = '?' . http_build_query($params);
            
            // Mostrar el encabezado como enlace clickeable
            echo '<th><a href="' . htmlspecialchars($url) . '" style="text-decoration:none;color:inherit;">' . 
                 htmlspecialchars($headerText) . $arrow . '</a></th>';
        }
        echo '</tr></thead>';
        
        // MOSTRAR FILAS DE DATOS
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
        
        // Si no hay datos, mostrar mensaje
        if ($rowCount === 0) {
            echo '<tr><td colspan="' . count($headers) . '">No hay filas</td></tr>';
        }
        
        echo '</tbody></table>';
        $res->free();  // Liberar memoria
    }
    
    /**
     * FORMATEA VALORES PARA MOSTRARLOS MEJOR
     * - Convierte TRUE/FALSE en Sí/No
     * - Mantiene números como están
     */
    private function formatValue($value, $columnName = '') {
        if (is_numeric($value) && !in_array($columnName, ['disponible', 'devuelto'])) {
            return $value;
        }
        
        if ($value === true || $value == 1) return 'Sí';
        if ($value === false || $value == 0) return 'No';
        
        return $value;
    }
}

// LISTA DE TABLAS DISPONIBLES para el selector
$tables = ['LIBROS', 'EJEMPLARES', 'ALUMNOS', 'PRESTAMOS'];

// OBTENER PARÁMETROS DE LA URL (lo que el usuario eligió)
$selectedTable = $_GET['table'] ?? '';  // Tabla seleccionada (o vacío para todas)
$search = $_GET['search'] ?? '';        // Texto de búsqueda
$orderBy = $_GET['orderBy'] ?? '';      // Columna para ordenar
$orderDir = $_GET['orderDir'] ?? 'ASC'; // Dirección del orden (ASC o DESC)

// Validar que la tabla seleccionada sea válida
if ($selectedTable && !in_array($selectedTable, $tables, true)) {
    $selectedTable = '';
}

// CREAR EL GESTOR DE TABLAS
$tableManager = new TableManager($conn);
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Base de datos: <?php echo htmlspecialchars($database); ?></title>
</head>
<body>
  <div class="header">
        <div class="container">
            <h1>Biblioteca</h1>
            <a href="index.html">Ir a página inicial</a>
            <a href="admin.php">Ir a administración</a>
            
        </div>
    </div>
  
  <!-- FORMULARIO DE BÚSQUEDA Y SELECCIÓN -->
  <form method="get">
    <!-- SELECTOR DE TABLA - cambia automáticamente cuando seleccionas -->
    <select name="table" onchange="this.form.submit()">
      <option value="">-- Todas las tablas --</option>
      <?php foreach ($tables as $t): ?>
        <option value="<?php echo htmlspecialchars($t); ?>" <?= $t === $selectedTable ? 'selected' : '' ?>>
          <?php echo htmlspecialchars($t); ?>
        </option>
      <?php endforeach; ?>
    </select>
    
    <!-- CAMPO DE BÚSQUEDA -->
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar...">
    <button type="submit">Buscar</button>
    
    <!-- BOTÓN PARA LIMPIAR BÚSQUEDA (solo aparece cuando hay búsqueda) -->
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
  // MOSTRAR TABLAS SEGÚN LO QUE EL USUARIO SELECCIONÓ
  if ($selectedTable) {
      // Mostrar solo UNA tabla específica
      switch ($selectedTable) {
          case 'LIBROS': $tableManager->displayLibros($search, $orderBy, $orderDir); break;
          case 'EJEMPLARES': $tableManager->displayEjemplares($search, $orderBy, $orderDir); break;
          case 'ALUMNOS': $tableManager->displayAlumnos($search, $orderBy, $orderDir); break;
          case 'PRESTAMOS': $tableManager->displayPrestamos($search, $orderBy, $orderDir); break;
      }
  } else {
      // Mostrar TODAS las tablas
      $tableManager->displayLibros($search, $orderBy, $orderDir);
      $tableManager->displayEjemplares($search, $orderBy, $orderDir);
      $tableManager->displayAlumnos($search, $orderBy, $orderDir);
      $tableManager->displayPrestamos($search, $orderBy, $orderDir);
  }
  
  // CERRAR CONEXIÓN A LA BASE DE DATOS
  $conn->close();
  ?>
</body>
</html>