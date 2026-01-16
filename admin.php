<?php
require_once 'db.php';

$vista      = $_GET['vista'] ?? 'todas';
$sort_by    = $_GET['sort_by'] ?? null;
$sort_order = $_GET['sort_order'] ?? 'ASC';
$sort_tabla = $_GET['sort_tabla'] ?? null;
$accion     = $_GET['accion'] ?? null;
$id         = $_GET['id'] ?? null;

// Definición de vistas simplificada (solo columnas reales de la tabla)
$vistas = [
    'categorias' => [
        'titulo'  => 'Categorías',
        'tabla'   => 'CATEGORIAS_LIBROS',
        'fields'  => ['id_categoria','codigo_categoria','descripcion'],
        'headers' => ['Código','Descripción'], // IDs ocultos
        'fk'      => []
    ],
    'libros' => [
        'titulo'  => 'Libros',
        'tabla'   => 'LIBROS',
        'fields'  => ['id_libro','titulo','autor','editorial','isbn','id_categoria'],
        'headers' => ['Título','Autor','Editorial','ISBN','Categoría'], // ID oculto
        'fk'      => ['id_categoria' => 'CATEGORIAS_LIBROS']
    ],
    'ejemplares' => [
        'titulo'  => 'Ejemplares',
        'tabla'   => 'EJEMPLARES',
        'fields'  => ['id_ejemplar','id_libro','estado_fisico'],
        'headers' => ['Libro','Estado físico'], // ID oculto
        'fk'      => ['id_libro' => 'LIBROS'],
        'select'  => ['estado_fisico' => ['Nuevo', 'Buen estado', 'Estado regular', 'Dañado']]
    ],
    'alumnos' => [
        'titulo'  => 'Alumnos',
        'tabla'   => 'ALUMNOS',
        'fields'  => ['id_alumno','nombre','apellidos','dni','email','curso'],
        'headers' => ['Nombre','Apellidos','DNI','Email','Curso'], // ID oculto
        'fk'      => []
    ],
    'prestamos' => [
        'titulo'  => 'Préstamos',
        'tabla'   => 'PRESTAMOS',
        'fields'  => ['id_prestamo','id_ejemplar','id_alumno','fecha_prestamo','fecha_limite_devolucion','fecha_devolucion_real','devuelto'],
        'headers' => ['Ejemplar','Alumno','Fecha préstamo','Fecha límite','Fecha devolución','Devuelto'], // ID oculto
        'fk'      => ['id_ejemplar'=>'EJEMPLARES','id_alumno'=>'ALUMNOS']
    ]
];

// URL de ordenación
function get_sort_url($field, $tabla_key, $vista, $sort_by, $sort_order) {
    $anchor = '#' . str_replace(' ', '_', $tabla_key);
    if ($sort_by === $field) {
        return $sort_order==='ASC'
            ? "?vista={$vista}&sort_by={$field}&sort_order=DESC&sort_tabla={$tabla_key}{$anchor}"
            : "?vista={$vista}{$anchor}";
    }
    return "?vista={$vista}&sort_by={$field}&sort_order=ASC&sort_tabla={$tabla_key}{$anchor}";
}

// Mostrar tabla
function mostrar_tabla($conn,$v,$sort_by,$sort_order,$sort_tabla,$vista) {
    $tabla = $v['tabla'];
    $fields = $v['fields'];
    $headers = $v['headers'];

    $sql = "SELECT ".implode(',',$fields)." FROM {$tabla}";
    
    // Si es libros, hacer JOIN con categorías para mostrar nombre
    if($tabla === 'LIBROS') {
        $sql = "SELECT l.id_libro, l.titulo, l.autor, l.editorial, l.isbn, c.descripcion as categoria_nombre
                FROM LIBROS l
                LEFT JOIN CATEGORIAS_LIBROS c ON l.id_categoria = c.id_categoria";
        $display_fields = ['titulo','autor','editorial','isbn','categoria_nombre'];
    } 
    // Si es ejemplares, hacer JOIN con libros para mostrar nombre
    else if($tabla === 'EJEMPLARES') {
        $sql = "SELECT e.id_ejemplar, l.titulo as libro_nombre, e.estado_fisico
                FROM EJEMPLARES e
                LEFT JOIN LIBROS l ON e.id_libro = l.id_libro";
        $display_fields = ['libro_nombre','estado_fisico'];
    }
    else {
        $display_fields = array_slice($fields, 1); // Saltar ID
    }
    
    if ($sort_by && in_array($sort_by, $display_fields)) {
        $sql .= " ORDER BY {$sort_by} {$sort_order}";
    }
    
    $res = $conn->query($sql);
    if(!$res || $res->num_rows==0) return;

    echo "<a href='admin.php?vista={$vista}&accion=agregar'>Añadir nuevo</a><br><br>";

    echo "<table border='1'><tr>";
    foreach($headers as $i=>$h){
        $field = $display_fields[$i];
        $flecha = ($sort_by===$field) ? ($sort_order==='ASC' ? ' ↑':' ↓'):'';
        echo "<th><a href='".get_sort_url($field,$tabla,$vista,$sort_by,$sort_order)."' style='text-decoration:none;color:black;'>{$h}{$flecha}</a></th>";
    }
    echo "<th>Acciones</th></tr>";

    while($row=$res->fetch_assoc()){
        echo "<tr>";
        foreach($display_fields as $f){
            echo "<td>{$row[$f]}</td>";
        }
        $id = $row['id_libro'] ?? $row['id_ejemplar'] ?? $row[$fields[0]]; // Obtener ID correcto
        echo "<td>
                <a href='admin.php?vista={$vista}&accion=editar&id={$id}'>Editar</a> |
                <a href='admin.php?vista={$vista}&accion=eliminar&id={$id}' onclick='return confirm(\"¿Seguro que quieres eliminar?\")'>Eliminar</a>
              </td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

// Manejo acciones
if($accion && $vista && isset($vistas[$vista])){
    $v = $vistas[$vista];
    $tabla = $v['tabla'];

    if($accion==='eliminar' && $id){
        $clave = $v['fields'][0];
        $conn->query("DELETE FROM {$tabla} WHERE {$clave}='{$id}'");
        header("Location: admin.php?vista={$vista}");
        exit;
    }

    if($accion==='editar' || $accion==='agregar'){
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $campos = [];
            foreach($v['fields'] as $f){
                if($f===$v['fields'][0]) continue; // nunca se edita/ingresa ID
                $campos[$f] = $_POST[$f] ?? '';
            }
            if($accion==='editar'){
                $sets = [];
                foreach($campos as $k=>$val) $sets[]="$k='$val'";
                $conn->query("UPDATE {$tabla} SET ".implode(',',$sets)." WHERE {$v['fields'][0]}='{$id}'");
            } else {
                $cols = implode(',',array_keys($campos));
                $vals = implode("','",array_values($campos));
                $conn->query("INSERT INTO {$tabla} ($cols) VALUES ('$vals')");
            }
            header("Location: admin.php?vista={$vista}");
            exit;
        }

        $data = ($accion==='editar') ? $conn->query("SELECT * FROM {$tabla} WHERE {$v['fields'][0]}='{$id}'")->fetch_assoc() : [];
        echo "<h2>".ucfirst($accion)." {$v['titulo']}</h2>";
        echo "<form method='post'>";

        foreach($v['fields'] as $f){
            if($f===$v['fields'][0]) continue; // nunca mostrar ID

            // Crear etiqueta legible
            $label = str_replace('id_', '', $f);
            $label = ucfirst(str_replace('_', ' ', $label));

            // Si es select (opciones predefinidas)
            if(isset($v['select'][$f])){
                echo "<label>{$label}: <select name='$f'>";
                foreach($v['select'][$f] as $option){
                    $selected = ($data[$f]??'')==$option?' selected':'';
                    echo "<option value='{$option}'{$selected}>{$option}</option>";
                }
                echo "</select></label><br>";
            }
            // Si es FK, mostrar select con opciones de otra tabla
            else if(isset($v['fk'][$f])){
                $fkTabla = $v['fk'][$f];
                $res_fk = $conn->query("SELECT * FROM {$fkTabla}");
                echo "<label>{$label}: <select name='$f'>";
                
                while($r_fk=$res_fk->fetch_assoc()){
                    $pk = $r_fk[array_keys($r_fk)[0]];
                    $display = $r_fk[array_keys($r_fk)[1]] ?? $pk;
                    $selected = ($data[$f]??'')==$pk?' selected':'';
                    echo "<option value='{$pk}'{$selected}>{$display}</option>";
                }
                echo "</select></label><br>";
            }
            // Si es texto normal
            else {
                $val = $data[$f] ?? '';
                echo "<label>{$label}: <input type='text' name='$f' value='$val'></label><br>";
            }
        }

        echo "<input type='submit' value='Guardar'>";
        echo "</form><br>";
    }
}

// Select para elegir tabla concreta
echo "<form method='get'>Mostrar tabla: <select name='vista' onchange='this.form.submit()'>";
echo "<option value='todas'".($vista==='todas'?' selected':'').">Todas</option>";
foreach($vistas as $key=>$v){
    $selected = $vista===$key?' selected':'';
    echo "<option value='$key'$selected>{$v['titulo']}</option>";
}
echo "</select></form><br>";

// Mostrar tablas
if($vista==='todas'){
    foreach($vistas as $key=>$v) mostrar_tabla($conn,$v,$sort_by,$sort_order,$sort_tabla,$vista);
}else{
    mostrar_tabla($conn,$vistas[$vista],$sort_by,$sort_order,$sort_tabla,$vista);
}

$conn->close();
?>
