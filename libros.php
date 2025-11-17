<?php
// Conexión a la base de datos
$servername = "localhost";  // El servidor MySQL en XAMPP es 'localhost'
$username = "root";         // El nombre de usuario por defecto es 'root'
$password = "root";             // La contraseña por defecto está vacía
$dbname = "biblioteca";     // El nombre de la base de datos que creaste

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Consultar los libros
$sql = "SELECT id_libro, titulo, autor, editorial, isbn, categoria FROM LIBROS";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cátalogo de Libros</title>
</head>
<body>
    <h1>Catálogo de Libros</h1>

    <?php if ($result->num_rows > 0): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Editorial</th>
                    <th>ISBN</th>
                    <th>Categoría</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Mostrar los libros
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id_libro'] . "</td>";
                    echo "<td>" . $row['titulo'] . "</td>";
                    echo "<td>" . $row['autor'] . "</td>";
                    echo "<td>" . $row['editorial'] . "</td>";
                    echo "<td>" . $row['isbn'] . "</td>";
                    echo "<td>" . $row['categoria'] . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No se encontraron libros.</p>
    <?php endif; ?>

    <?php $conn->close(); // Cerrar la conexión ?>
</body>
</html>
