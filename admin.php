<?php
require_once 'db.php';

// ===== PERSONALIZACIONES =====
$column_aliases = [
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

function get_column_alias(string $column) {
    global $column_aliases;
    return isset($column_aliases[$column]) ? $column_aliases[$column] : ucfirst(str_replace('_', ' ', $column));
}

function get_foreign_keys(mysqli $conn, string $table) {
    $fks = [];
    $res = $conn->query("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '{$conn->real_escape_string($table)}'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $fks[$row['COLUMN_NAME']] = [
                'ref_table' => $row['REFERENCED_TABLE_NAME'],
                'ref_column' => $row['REFERENCED_COLUMN_NAME']
            ];
        }
        $res->free();
    }
    return $fks;
}

// ===== PROCESAR FORMULARIOS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    
    // Validar tabla
    $tables_res = $conn->query("SHOW TABLES");
    $valid_tables = [];
    while ($row = $tables_res->fetch_array(MYSQLI_NUM)) {
        $valid_tables[] = $row[0];
    }
    
    if (!in_array($table, $valid_tables)) {
        die("Tabla inválida");
    }
    
    if ($action === 'insert') {
        // INSERT: Agregar nuevo registro
        $columns = [];
        $values = [];
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && $key !== 'table') {
                $columns[] = "`{$conn->real_escape_string($key)}`";
                $values[] = "'{$conn->real_escape_string($value)}'";
            }
        }
        
        $query = "INSERT INTO `{$conn->real_escape_string($table)}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
        
        if ($conn->query($query)) {
            $message = "✓ Registro agregado correctamente";
        } else {
            $message = "✗ Error: " . htmlspecialchars($conn->error);
        }
    }
    
    elseif ($action === 'update') {
        // UPDATE: Editar registro existente
        $id_column = $_POST['id_column'] ?? '';
        $id_value = $_POST['id_value'] ?? '';
        $sets = [];
        
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['action', 'table', 'id_column', 'id_value'])) {
                $sets[] = "`{$conn->real_escape_string($key)}` = '{$conn->real_escape_string($value)}'";
            }
        }
        
        $query = "UPDATE `{$conn->real_escape_string($table)}` SET " . implode(',', $sets) . " WHERE `{$conn->real_escape_string($id_column)}` = '{$conn->real_escape_string($id_value)}'";
        
        if ($conn->query($query)) {
            $message = "✓ Registro actualizado correctamente";
        } else {
            $message = "✗ Error: " . htmlspecialchars($conn->error);
        }
    }
    
    elseif ($action === 'delete') {
        // DELETE: Eliminar registro
        $id_column = $_POST['id_column'] ?? '';
        $id_value = $_POST['id_value'] ?? '';
        
        $query = "DELETE FROM `{$conn->real_escape_string($table)}` WHERE `{$conn->real_escape_string($id_column)}` = '{$conn->real_escape_string($id_value)}'";
        
        if ($conn->query($query)) {
            $message = "✓ Registro eliminado correctamente";
        } else {
            $message = "✗ Error: " . htmlspecialchars($conn->error);
        }
    }
}

// ===== OBTENER TABLAS =====
$tables_res = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_res->fetch_array(MYSQLI_NUM)) {
    $tables[] = $row[0];
}

$selected_table = $_GET['table'] ?? ($tables[0] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrador - <?php echo htmlspecialchars($database); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #333; color: white; padding: 20px 0; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .message { padding: 12px; margin-bottom: 12px; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-selector { margin-bottom: 20px; }
        .table-selector select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        table th { background: #f8f9fa; font-weight: bold; }
        table tr:hover { background: #f8f9fa; }
        button { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; margin-right: 4px; }
        .btn-edit { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-add { background: #28a745; color: white; }
        .btn-submit { background: #007bff; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        button:hover { opacity: 0.9; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .form-actions { display: flex; gap: 8px; justify-content: flex-end; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>📊 Administrador de Biblioteca</h1>
            <a href="../libros.php" style="color: white; margin-top: 10px; display: inline-block;">← Volver a visualización</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, '✓') === 0 ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-selector">
                <form method="get" style="margin-bottom: 20px;">
                    <label for="table">Selecciona tabla:</label>
                    <select name="table" id="table" onchange="this.form.submit()">
                        <?php foreach ($tables as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $selected_table === $t ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_table): ?>
                <h2><?php echo htmlspecialchars($selected_table); ?></h2>
                <button class="btn-add" onclick="openAddModal()">+ Agregar nuevo registro</button>

                <?php
                $res = $conn->query("SELECT * FROM `{$conn->real_escape_string($selected_table)}`");
                $fields = $res->fetch_fields();
                ?>

                <table>
                    <thead>
                        <tr>
                            <?php foreach ($fields as $f): ?>
                                <th><?php echo get_column_alias($f->name); ?></th>
                            <?php endforeach; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM `{$conn->real_escape_string($selected_table)}`");
                        $id_column = $fields[0]->name;
                        
                        while ($row = $res->fetch_assoc()):
                        ?>
                            <tr>
                                <?php foreach ($fields as $f): ?>
                                    <td><?php echo htmlspecialchars((string)($row[$f->name] ?? '')); ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>, '<?php echo htmlspecialchars($id_column); ?>')">✏️ Editar</button>
                                    <button class="btn-delete" onclick="openDeleteModal('<?php echo htmlspecialchars($selected_table); ?>', '<?php echo htmlspecialchars($id_column); ?>', '<?php echo htmlspecialchars($row[$id_column]); ?>')">🗑️ Eliminar</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL: Agregar -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Agregar nuevo registro</h3>
            <form method="post">
                <input type="hidden" name="action" value="insert">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($selected_table); ?>">
                
                <?php
                $res = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($selected_table)}`");
                while ($col = $res->fetch_assoc()):
                    $col_name = $col['Field'];
                    $is_pk = $col['Key'] === 'PRI';
                    
                    if ($is_pk && strpos($col['Extra'], 'auto_increment') !== false) continue;
                ?>
                    <div class="form-group">
                        <label for="<?php echo htmlspecialchars($col_name); ?>"><?php echo get_column_alias($col_name); ?></label>
                        <input type="text" name="<?php echo htmlspecialchars($col_name); ?>" id="<?php echo htmlspecialchars($col_name); ?>" required>
                    </div>
                <?php endwhile; ?>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Editar registro</h3>
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($selected_table); ?>">
                <input type="hidden" name="id_column" id="editIdColumn">
                <input type="hidden" name="id_value" id="editIdValue">
                <div id="editFormFields"></div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Eliminar -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirmar eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar este registro? Esta acción no se puede deshacer.</p>
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="table" id="deleteTable">
                <input type="hidden" name="id_column" id="deleteIdColumn">
                <input type="hidden" name="id_value" id="deleteIdValue">
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancelar</button>
                    <button type="submit" class="btn-delete">Eliminar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(row, idColumn) {
            document.getElementById('editIdColumn').value = idColumn;
            document.getElementById('editIdValue').value = row[idColumn];
            
            let fieldsHtml = '';
            for (let key in row) {
                if (key !== idColumn) {
                    fieldsHtml += `
                        <div class="form-group">
                            <label for="edit_${key}">${key}</label>
                            <input type="text" name="${key}" id="edit_${key}" value="${row[key] || ''}" required>
                        </div>
                    `;
                }
            }
            document.getElementById('editFormFields').innerHTML = fieldsHtml;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openDeleteModal(table, idColumn, idValue) {
            document.getElementById('deleteTable').value = table;
            document.getElementById('deleteIdColumn').value = idColumn;
            document.getElementById('deleteIdValue').value = idValue;
            document.getElementById('deleteModal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>