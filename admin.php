<?php
require_once 'db.php';

// ===== CONFIGURACIÓN ESPECÍFICA PARA BIBLIOTECA =====
$column_aliases = [
    'id_libro' => 'ID Libro',
    'titulo' => 'Título',
    'autor' => 'Autor',
    'editorial' => 'Editorial', 
    'isbn' => 'ISBN',
    'categoria' => 'Categoría',
    'id_ejemplar' => 'ID Ejemplar',
    'codigo_inventario' => 'Código Inventario',
    'disponible' => 'Disponible',
    'estado_fisico' => 'Estado Físico',
    'ubicacion' => 'Ubicación',
    'id_alumno' => 'ID Alumno',
    'nombre' => 'Nombre',
    'apellidos' => 'Apellidos',
    'dni' => 'DNI',
    'email' => 'Email',
    'curso' => 'Curso',
    'fecha_registro' => 'Fecha Registro',
    'id_prestamo' => 'ID Préstamo',
    'fecha_prestamo' => 'Fecha Préstamo',
    'fecha_devolucion' => 'Fecha Devolución',
    'devuelto' => 'Devuelto'
];

// Configuración específica por tabla
$table_config = [
    'LIBROS' => [
        'id_field' => 'id_libro',
        'display_field' => 'titulo',
        'fields' => [
            'titulo' => ['type' => 'text', 'placeholder' => 'Ej: Cien años de soledad', 'required' => true],
            'autor' => ['type' => 'text', 'placeholder' => 'Ej: Gabriel García Márquez', 'required' => true],
            'editorial' => ['type' => 'text', 'placeholder' => 'Ej: Editorial Sudamericana'],
            'isbn' => ['type' => 'text', 'placeholder' => 'Ej: 978-3-16-148410-0'],
            'categoria' => ['type' => 'text', 'placeholder' => 'Ej: Ficción, Ciencia Ficción...']
        ]
    ],
    'EJEMPLARES' => [
        'id_field' => 'id_ejemplar', 
        'display_field' => 'codigo_inventario',
        'fields' => [
            'id_libro' => [
                'type' => 'select', 
                'options_query' => "SELECT id_libro, titulo FROM LIBROS ORDER BY titulo",
                'required' => true
            ],
            'codigo_inventario' => ['type' => 'text', 'placeholder' => 'Ej: LIB-001-01', 'required' => true],
            'disponible' => [
                'type' => 'select', 
                'options' => ['1' => 'Sí', '0' => 'No'],
                'default' => '1'
            ],
            'estado_fisico' => [
                'type' => 'select', 
                'options' => ['Nuevo' => 'Nuevo', 'Bueno' => 'Bueno', 'Dañado' => 'Dañado', 'Perdido' => 'Perdido'],
                'default' => 'Bueno'
            ],
            'ubicacion' => ['type' => 'text', 'placeholder' => 'Ej: Estante A1']
        ]
    ],
    'ALUMNOS' => [
        'id_field' => 'id_alumno',
        'display_field' => 'nombre',
        'fields' => [
            'nombre' => ['type' => 'text', 'placeholder' => 'Ej: Juan', 'required' => true],
            'apellidos' => ['type' => 'text', 'placeholder' => 'Ej: Pérez López', 'required' => true],
            'dni' => ['type' => 'text', 'placeholder' => 'Ej: 12345678A', 'pattern' => '[0-9]{8}[A-Z]', 'required' => true],
            'email' => ['type' => 'email', 'placeholder' => 'Ej: juan@email.com'],
            'curso' => ['type' => 'text', 'placeholder' => 'Ej: Ingeniería Informática'],
            'fecha_registro' => ['type' => 'date', 'placeholder' => 'AAAA-MM-DD']
        ]
    ],
    'PRESTAMOS' => [
        'id_field' => 'id_prestamo',
        'display_field' => 'id_prestamo',
        'fields' => [
            'id_ejemplar' => [
                'type' => 'select',
                'options_query' => "SELECT e.id_ejemplar, CONCAT(l.titulo, ' - ', e.codigo_inventario) as display 
                                  FROM EJEMPLARES e 
                                  JOIN LIBROS l ON e.id_libro = l.id_libro 
                                  WHERE e.disponible = 1 
                                  ORDER BY l.titulo",
                'required' => true
            ],
            'id_alumno' => [
                'type' => 'select',
                'options_query' => "SELECT id_alumno, CONCAT(nombre, ' ', apellidos) as display FROM ALUMNOS ORDER BY nombre",
                'required' => true
            ],
            'fecha_prestamo' => ['type' => 'date', 'placeholder' => 'AAAA-MM-DD', 'required' => true],
            'fecha_devolucion' => ['type' => 'date', 'placeholder' => 'AAAA-MM-DD', 'required' => true],
            'devuelto' => [
                'type' => 'select',
                'options' => ['0' => 'No', '1' => 'Sí'],
                'default' => '0'
            ]
        ]
    ]
];

// ===== FUNCIONES ESPECÍFICAS =====
function get_column_alias(string $column) {
    global $column_aliases;
    return $column_aliases[$column] ?? ucfirst(str_replace('_', ' ', $column));
}

function get_field_options($conn, $options_query) {
    $options = [];
    $res = $conn->query($options_query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // Obtener la primera columna como valor y la segunda como etiqueta
            $keys = array_keys($row);
            $value = $row[$keys[0]];
            $label = isset($row[$keys[1]]) ? $row[$keys[1]] : $value;
            $options[$value] = $label;
        }
        $res->free();
    }
    return $options;
}

function validate_unique_field($conn, $table, $field, $value, $exclude_id = null) {
    $query = "SELECT COUNT(*) as count FROM $table WHERE $field = '{$conn->real_escape_string($value)}'";
    if ($exclude_id) {
        $id_field = $table_config[$table]['id_field'] ?? 'id';
        $query .= " AND $id_field != '{$conn->real_escape_string($exclude_id)}'";
    }
    
    $res = $conn->query($query);
    $count = $res->fetch_assoc()['count'];
    return $count == 0;
}

// ===== PROCESAR FORMULARIOS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';
    
    if (!isset($table_config[$table])) {
        die("Tabla no configurada");
    }
    
    $config = $table_config[$table];
    $errors = [];
    
    // Validaciones específicas por tabla
    foreach ($config['fields'] as $field_name => $field_config) {
        $value = $_POST[$field_name] ?? '';
        
        if (($field_config['required'] ?? false) && empty($value)) {
            $errors[] = "El campo " . get_column_alias($field_name) . " es requerido";
        }
        
        // Validar unicidad para campos únicos
        if ($field_name === 'isbn' && !empty($value)) {
            if (!validate_unique_field($conn, $table, $field_name, $value, $_POST['id_value'] ?? null)) {
                $errors[] = "El ISBN ya existe en la base de datos";
            }
        }
        
        if ($field_name === 'dni' && !empty($value)) {
            if (!preg_match('/^[0-9]{8}[A-Z]$/i', $value)) {
                $errors[] = "El DNI debe tener 8 números y 1 letra";
            }
            if (!validate_unique_field($conn, $table, $field_name, $value, $_POST['id_value'] ?? null)) {
                $errors[] = "El DNI ya existe en la base de datos";
            }
        }
        
        if ($field_name === 'codigo_inventario' && !empty($value)) {
            if (!validate_unique_field($conn, $table, $field_name, $value, $_POST['id_value'] ?? null)) {
                $errors[] = "El código de inventario ya existe";
            }
        }
    }
    
    if (empty($errors)) {
        if ($action === 'insert') {
            $columns = [];
            $values = [];
            
            foreach ($config['fields'] as $field_name => $field_config) {
                $value = $_POST[$field_name] ?? ($field_config['default'] ?? '');
                $columns[] = "`{$conn->real_escape_string($field_name)}`";
                $values[] = "'{$conn->real_escape_string($value)}'";
            }
            
            $query = "INSERT INTO `$table` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
            
            if ($conn->query($query)) {
                $message = "✓ Registro agregado correctamente";
                
                // Actualizar disponibilidad del ejemplar si es un préstamo
                if ($table === 'PRESTAMOS' && isset($_POST['id_ejemplar'])) {
                    $conn->query("UPDATE EJEMPLARES SET disponible = 0 WHERE id_ejemplar = '{$conn->real_escape_string($_POST['id_ejemplar'])}'");
                }
            } else {
                $message = "✗ Error: " . htmlspecialchars($conn->error);
            }
        }
        
        elseif ($action === 'update') {
            $id_column = $_POST['id_column'] ?? '';
            $id_value = $_POST['id_value'] ?? '';
            $sets = [];
            
            // Guardar estado anterior para préstamos
            $old_devuelto = null;
            $old_id_ejemplar = null;
            if ($table === 'PRESTAMOS') {
                $res = $conn->query("SELECT devuelto, id_ejemplar FROM PRESTAMOS WHERE id_prestamo = '{$conn->real_escape_string($id_value)}'");
                $old_data = $res->fetch_assoc();
                $old_devuelto = $old_data['devuelto'];
                $old_id_ejemplar = $old_data['id_ejemplar'];
            }
            
            foreach ($config['fields'] as $field_name => $field_config) {
                $value = $_POST[$field_name] ?? ($field_config['default'] ?? '');
                $sets[] = "`{$conn->real_escape_string($field_name)}` = '{$conn->real_escape_string($value)}'";
            }
            
            $query = "UPDATE `$table` SET " . implode(',', $sets) . " WHERE `$id_column` = '$id_value'";
            
            if ($conn->query($query)) {
                $message = "✓ Registro actualizado correctamente";
                
                // Manejar cambios en préstamos
                if ($table === 'PRESTAMOS') {
                    $new_devuelto = $_POST['devuelto'] ?? '0';
                    $new_id_ejemplar = $_POST['id_ejemplar'] ?? $old_id_ejemplar;
                    
                    // Si cambió el ejemplar, actualizar disponibilidades
                    if ($new_id_ejemplar != $old_id_ejemplar) {
                        $conn->query("UPDATE EJEMPLARES SET disponible = 1 WHERE id_ejemplar = '$old_id_ejemplar'");
                        $conn->query("UPDATE EJEMPLARES SET disponible = 0 WHERE id_ejemplar = '$new_id_ejemplar'");
                    }
                    // Si se marcó como devuelto, liberar ejemplar
                    elseif ($new_devuelto == '1' && $old_devuelto == '0') {
                        $conn->query("UPDATE EJEMPLARES SET disponible = 1 WHERE id_ejemplar = '$new_id_ejemplar'");
                    }
                    // Si se desmarcó devuelto, reservar ejemplar
                    elseif ($new_devuelto == '0' && $old_devuelto == '1') {
                        $conn->query("UPDATE EJEMPLARES SET disponible = 0 WHERE id_ejemplar = '$new_id_ejemplar'");
                    }
                }
            } else {
                $message = "✗ Error: " . htmlspecialchars($conn->error);
            }
        }
        
        elseif ($action === 'delete') {
            $id_column = $_POST['id_column'] ?? '';
            $id_value = $_POST['id_value'] ?? '';
            
            // Manejar eliminación de préstamos
            if ($table === 'PRESTAMOS') {
                $res = $conn->query("SELECT id_ejemplar, devuelto FROM PRESTAMOS WHERE id_prestamo = '$id_value'");
                $prestamo = $res->fetch_assoc();
                if ($prestamo && $prestamo['devuelto'] == '0') {
                    $conn->query("UPDATE EJEMPLARES SET disponible = 1 WHERE id_ejemplar = '{$prestamo['id_ejemplar']}'");
                }
            }
            
            $query = "DELETE FROM `$table` WHERE `$id_column` = '$id_value'";
            
            if ($conn->query($query)) {
                $message = "✓ Registro eliminado correctamente";
            } else {
                $message = "✗ Error: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $message = "✗ Errores: " . implode(', ', $errors);
    }
}

// ===== OBTENER TABLAS =====
$tables = array_keys($table_config);
$selected_table = $_GET['table'] ?? ($tables[0] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrador - <?php echo htmlspecialchars($database); ?></title>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Administrador de Biblioteca</h1>
            <a href="index.html">Ir a página inicial</a>
            <a href="libros.php">Ir al listado de libros</a>
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
                <form method="get" class="selector-form">
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

            <?php if ($selected_table && isset($table_config[$selected_table])): ?>
                <?php $config = $table_config[$selected_table]; ?>
                <h2><?php echo htmlspecialchars($selected_table); ?></h2>
                <button class="btn-add" onclick="openAddModal()">+ Agregar nuevo registro</button>

                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($config['fields'] as $field_name => $field_config): ?>
                                <th><?php echo get_column_alias($field_name); ?></th>
                            <?php endforeach; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM `$selected_table`";
                        if ($selected_table === 'EJEMPLARES') {
                            $query .= " ORDER BY codigo_inventario";
                        } elseif ($selected_table === 'LIBROS') {
                            $query .= " ORDER BY titulo";
                        } elseif ($selected_table === 'ALUMNOS') {
                            $query .= " ORDER BY apellidos, nombre";
                        } elseif ($selected_table === 'PRESTAMOS') {
                            $query .= " ORDER BY fecha_prestamo DESC";
                        }
                        
                        $res = $conn->query($query);
                        $id_column = $config['id_field'];
                        
                        while ($row = $res->fetch_assoc()):
                        ?>
                            <tr>
                                <?php foreach ($config['fields'] as $field_name => $field_config): ?>
                                    <td>
                                        <?php if (isset($field_config['options_query'])): ?>
                                            <?php 
                                            $options = get_field_options($conn, $field_config['options_query']);
                                            echo htmlspecialchars($options[$row[$field_name]] ?? $row[$field_name]);
                                            ?>
                                        <?php elseif (isset($field_config['options'])): ?>
                                            <?php echo htmlspecialchars($field_config['options'][$row[$field_name]] ?? $row[$field_name]); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars((string)($row[$field_name] ?? '')); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="actions">
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
            <h3>Agregar nuevo registro - <?php echo htmlspecialchars($selected_table); ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="insert">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($selected_table); ?>">
                
                <?php if (isset($table_config[$selected_table])): ?>
                    <?php foreach ($table_config[$selected_table]['fields'] as $field_name => $field_config): ?>
                        <div class="form-group">
                            <label for="<?php echo htmlspecialchars($field_name); ?>">
                                <?php echo get_column_alias($field_name); ?>
                                <?php if ($field_config['required'] ?? false): ?><span style="color:red">*</span><?php endif; ?>
                            </label>
                            
                            <?php if (isset($field_config['options_query'])): ?>
                                <?php $options = get_field_options($conn, $field_config['options_query']); ?>
                                <select name="<?php echo htmlspecialchars($field_name); ?>" id="<?php echo htmlspecialchars($field_name); ?>" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($options as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (isset($field_config['options'])): ?>
                                <select name="<?php echo htmlspecialchars($field_name); ?>" id="<?php echo htmlspecialchars($field_name); ?>" <?php if ($field_config['required'] ?? false): ?>required<?php endif; ?>>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($field_config['options'] as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($field_config['default'] ?? '') == $value ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars($field_config['type']); ?>" 
                                       name="<?php echo htmlspecialchars($field_name); ?>" 
                                       id="<?php echo htmlspecialchars($field_name); ?>" 
                                       placeholder="<?php echo htmlspecialchars($field_config['placeholder'] ?? ''); ?>"
                                       <?php if (isset($field_config['pattern'])): ?>pattern="<?php echo htmlspecialchars($field_config['pattern']); ?>"<?php endif; ?>
                                       <?php if ($field_config['required'] ?? false): ?>required<?php endif; ?>
                                       value="<?php echo htmlspecialchars($field_config['default'] ?? ''); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

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
            <h3>Editar registro - <?php echo htmlspecialchars($selected_table); ?></h3>
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
            <h3>Confirmar eliminación</h3>
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
            
            // Configuración específica para cada tabla
            const tableConfig = {
                'LIBROS': {
                    'titulo': {type: 'text'},
                    'autor': {type: 'text'},
                    'editorial': {type: 'text'},
                    'isbn': {type: 'text'},
                    'categoria': {type: 'text'}
                },
                'EJEMPLARES': {
                    'id_libro': {type: 'select', query: 'libros'},
                    'codigo_inventario': {type: 'text'},
                    'disponible': {type: 'select', options: {'1': 'Sí', '0': 'No'}},
                    'estado_fisico': {type: 'select', options: {'Nuevo': 'Nuevo', 'Bueno': 'Bueno', 'Dañado': 'Dañado', 'Perdido': 'Perdido'}},
                    'ubicacion': {type: 'text'}
                },
                'ALUMNOS': {
                    'nombre': {type: 'text'},
                    'apellidos': {type: 'text'},
                    'dni': {type: 'text'},
                    'email': {type: 'email'},
                    'curso': {type: 'text'},
                    'fecha_registro': {type: 'date'}
                },
                'PRESTAMOS': {
                    'id_ejemplar': {type: 'select', query: 'ejemplares_disponibles'},
                    'id_alumno': {type: 'select', query: 'alumnos'},
                    'fecha_prestamo': {type: 'date'},
                    'fecha_devolucion': {type: 'date'},
                    'devuelto': {type: 'select', options: {'0': 'No', '1': 'Sí'}}
                }
            };
            
            const currentTable = '<?php echo $selected_table; ?>';
            const config = tableConfig[currentTable] || {};
            
            for (let key in row) {
                if (key !== idColumn && config[key]) {
                    const fieldConfig = config[key];
                    
                    if (fieldConfig.type === 'select') {
                        let optionsHtml = '';
                        if (fieldConfig.options) {
                            for (let optValue in fieldConfig.options) {
                                optionsHtml += `<option value="${optValue}" ${row[key] == optValue ? 'selected' : ''}>${fieldConfig.options[optValue]}</option>`;
                            }
                        }
                        fieldsHtml += `
                            <div class="form-group">
                                <label for="edit_${key}">${key}</label>
                                <select name="${key}" id="edit_${key}" required>${optionsHtml}</select>
                            </div>
                        `;
                    } else {
                        fieldsHtml += `
                            <div class="form-group">
                                <label for="edit_${key}">${key}</label>
                                <input type="${fieldConfig.type}" name="${key}" id="edit_${key}" value="${row[key] || ''}" required>
                            </div>
                        `;
                    }
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
