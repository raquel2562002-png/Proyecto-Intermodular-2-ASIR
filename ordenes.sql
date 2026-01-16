-- ==========================================
-- CREAR BASE DE DATOS
-- ==========================================
CREATE DATABASE IF NOT EXISTS biblioteca;
USE biblioteca;

-- ==========================================
-- TABLA: CATEGORIAS_LIBROS
-- ==========================================
CREATE TABLE CATEGORIAS_LIBROS (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    codigo_categoria VARCHAR(10) UNIQUE NOT NULL,
    descripcion VARCHAR(255) NOT NULL
);

-- ==========================================
-- TABLA: LIBROS
-- ==========================================
CREATE TABLE LIBROS (
    id_libro INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    autor VARCHAR(255) NOT NULL,
    editorial VARCHAR(255),
    isbn VARCHAR(20) UNIQUE,
    id_categoria INT,
    CONSTRAINT fk_libro_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES CATEGORIAS_LIBROS(id_categoria)
        ON DELETE RESTRICT
);

-- ==========================================
-- TABLA: EJEMPLARES
-- ==========================================
CREATE TABLE EJEMPLARES (
    id_ejemplar INT AUTO_INCREMENT PRIMARY KEY,
    id_libro INT NOT NULL,
    estado_fisico ENUM('Usar', 'Sustituir', 'Perdido') DEFAULT 'Usar',
    CONSTRAINT fk_ejemplar_libro
        FOREIGN KEY (id_libro)
        REFERENCES LIBROS(id_libro)
        ON DELETE CASCADE
);

-- ==========================================
-- TABLA: ALUMNOS
-- ==========================================
CREATE TABLE ALUMNOS (
    id_alumno INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    dni VARCHAR(15) UNIQUE NOT NULL,
    email VARCHAR(150),
    curso VARCHAR(100)
);

-- ==========================================
-- TABLA: PRESTAMOS
-- ==========================================
CREATE TABLE PRESTAMOS (
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_ejemplar INT NOT NULL,
    id_alumno INT NOT NULL,
    fecha_prestamo DATE NOT NULL,
    fecha_limite_devolucion DATE NOT NULL,
    fecha_devolucion_real DATE NULL,
    devuelto BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_prestamo_ejemplar
        FOREIGN KEY (id_ejemplar)
        REFERENCES EJEMPLARES(id_ejemplar)
        ON DELETE RESTRICT,
    CONSTRAINT fk_prestamo_alumno
        FOREIGN KEY (id_alumno)
        REFERENCES ALUMNOS(id_alumno)
        ON DELETE RESTRICT
);

-- ==========================================
-- VACIAR BASE DE DATOS COMPLETAMENTE
-- ==========================================
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE PRESTAMOS;
TRUNCATE TABLE EJEMPLARES;
TRUNCATE TABLE LIBROS;
TRUNCATE TABLE CATEGORIAS_LIBROS;
TRUNCATE TABLE ALUMNOS;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- INSERTAR CATEGORÍAS DE LIBROS
-- ==========================================
INSERT INTO CATEGORIAS_LIBROS (codigo_categoria, descripcion) VALUES
('0', 'Vacío'),
('10', 'Filosofía General'),
('53', 'Física'),
('54', 'Química'),
('55', 'Geología'),
('57', 'Biología General'),
('159', 'Psicología'),
('804', 'Lengua y lingüística'),
('807', 'Lenguas clásicas'),
('82', 'Historia de la Literatura Española'),
('820', 'Literatura inglesa'),
('830', 'Literatura alemana'),
('840', 'Literatura francesa'),
('844', 'Literatura checa'),
('849', 'Literatura valenciana'),
('850', 'Literatura italiana'),
('860', 'Literatura española'),
('869', 'Literatura portuguesa'),
('871', 'Literatura latina'),
('875', 'Literatura griega'),
('882', 'Literatura Rusa'),
('929', 'Biografía'),
('938', 'Historia de la Antigua Grecia');

-- ==========================================
-- INSERTAR LIBROS
-- ==========================================
INSERT INTO LIBROS (titulo, autor, editorial, isbn, id_categoria) VALUES
('Cien años de soledad', 'Gabriel García Márquez', 'Editorial Sudamericana', '978-3-16-148410-0', 16), -- 860: Literatura española
('1984', 'George Orwell', 'Editorial Planeta', '978-0-452-28423-4', 10), -- 820: Literatura inglesa
('Don Quijote de la Mancha', 'Miguel de Cervantes', 'Editorial Espasa Calpe', '978-84-670-3272-2', 16), -- 860: Literatura española
('El amor en los tiempos del cólera', 'Gabriel García Márquez', 'Editorial Oveja Negra', '978-84-663-2181-5', 16), -- 860: Literatura española
('Fahrenheit 451', 'Ray Bradbury', 'Editorial Minotauro', '978-84-450-7691-2', 12), -- 840: Literatura francesa
('Un mundo feliz', 'Aldous Huxley', 'Editorial Debolsillo', '978-84-9759-319-9', 10), -- 820: Literatura inglesa
('Rebelión en la granja', 'George Orwell', 'Editorial Destino', '978-84-233-2328-4', 10), -- 820: Literatura inglesa
('El principito', 'Antoine de Saint-Exupéry', 'Editorial Salamandra', '978-84-9838-471-2', 12), -- 840: Literatura francesa
('La sombra del viento', 'Carlos Ruiz Zafón', 'Editorial Planeta', '978-84-08-05239-2', 16), -- 860: Literatura española
('Harry Potter y la piedra filosofal', 'J.K. Rowling', 'Editorial Salamandra', '978-84-9838-475-0', 10); -- 820: Literatura inglesa

-- ==========================================
-- INSERTAR EJEMPLARES (IDs del 1 al 20)
-- ==========================================
INSERT INTO EJEMPLARES (id_libro, estado_fisico) VALUES
-- Libro 1: Cien años de soledad (3 ejemplares)
(1, 'Usar'),
(1, 'Usar'),
(1, 'Usar'),

-- Libro 2: 1984 (2 ejemplares)
(2, 'Usar'),
(2, 'Sustituir'),

-- Libro 3: Don Quijote (3 ejemplares)
(3, 'Usar'),
(3, 'Usar'),
(3, 'Usar'),

-- Libro 4: El amor en los tiempos del cólera (2 ejemplares)
(4, 'Usar'),
(4, 'Usar'),

-- Libro 5: Fahrenheit 451 (2 ejemplares)
(5, 'Usar'),
(5, 'Usar'),

-- Libro 6: Un mundo feliz (2 ejemplares)
(6, 'Usar'),
(6, 'Sustituir'),

-- Libro 7: Rebelión en la granja (1 ejemplar)
(7, 'Usar'),

-- Libro 8: El principito (3 ejemplares)
(8, 'Usar'),
(8, 'Usar'),
(8, 'Usar'),

-- Libro 9: La sombra del viento (1 ejemplar)
(9, 'Usar'),

-- Libro 10: Harry Potter (2 ejemplares)
(10, 'Usar'),
(10, 'Usar');

-- ==========================================
-- INSERTAR ALUMNOS (IDs del 1 al 10)
-- ==========================================
INSERT INTO ALUMNOS (nombre, apellidos, dni, email, curso) VALUES
('Juan', 'Pérez López', '12345678A', 'juanperez@email.com', 'Ingeniería Informática'),
('Ana', 'González Martínez', '87654321B', 'anagonzalez@email.com', 'Medicina'),
('Luis', 'Ramírez Sánchez', '11223344C', 'luisramirez@email.com', 'Derecho'),
('María', 'López García', '23456789D', 'marialopez@email.com', 'Ingeniería Informática'),
('Carlos', 'Martínez Ruiz', '34567890E', 'carlosmartinez@email.com', 'Medicina'),
('Elena', 'Sánchez Pérez', '45678901F', 'elenasanchez@email.com', 'Derecho'),
('David', 'Gómez Fernández', '56789012G', 'davidgomez@email.com', 'Arquitectura'),
('Laura', 'Rodríguez Martín', '67890123H', 'laurarodriguez@email.com', 'Psicología'),
('Pablo', 'Hernández Díaz', '78901234I', 'pablohernandez@email.com', 'Administración de Empresas'),
('Sofía', 'Jiménez Moreno', '89012345J', 'sofiajimenez@email.com', 'Biología');

-- ==========================================
-- INSERTAR PRÉSTAMOS (IDs del 1 al 15)
-- ==========================================
INSERT INTO PRESTAMOS (id_ejemplar, id_alumno, fecha_prestamo, fecha_limite_devolucion, fecha_devolucion_real, devuelto) VALUES
-- Préstamos activos (no devueltos, en fecha)
(3, 1, '2025-10-20', '2025-11-03', NULL, FALSE),   -- Juan Pérez tiene Cien años de soledad
(4, 2, '2025-10-18', '2025-11-01', NULL, FALSE),   -- Ana González tiene 1984
(9, 3, '2025-10-15', '2025-10-29', NULL, FALSE),   -- Luis Ramírez tiene El amor en los tiempos...
(13, 4, '2025-10-22', '2025-11-05', NULL, FALSE),  -- María López tiene Un mundo feliz
(17, 5, '2025-10-19', '2025-11-02', NULL, FALSE),  -- Carlos Martínez tiene El principito

-- Préstamos retrasados (no devueltos, fecha límite pasada)
(6, 6, '2025-10-01', '2025-10-15', NULL, FALSE),   -- Elena Sánchez con 1984 retrasado
(11, 7, '2025-10-05', '2025-10-19', NULL, FALSE),  -- David Gómez con Un mundo feliz retrasado
(18, 8, '2025-10-03', '2025-10-17', NULL, FALSE),  -- Laura Rodríguez con El principito retrasado

-- Préstamos devueltos a tiempo
(1, 9, '2025-10-10', '2025-10-24', '2025-10-22', TRUE),    -- Pablo Hernández devolvió Cien años
(7, 10, '2025-10-12', '2025-10-26', '2025-10-24', TRUE),   -- Sofía Jiménez devolvió Don Quijote
(10, 1, '2025-10-08', '2025-10-22', '2025-10-20', TRUE),   -- Juan Pérez devolvió Fahrenheit 451
(14, 2, '2025-10-14', '2025-10-28', '2025-10-25', TRUE),   -- Ana González devolvió Rebelión en la granja
(19, 3, '2025-10-11', '2025-10-25', '2025-10-23', TRUE),   -- Luis Ramírez devolvió La sombra del viento

-- Préstamos devueltos con retraso (pero ya devueltos)
(2, 4, '2025-09-20', '2025-10-04', '2025-10-10', TRUE),    -- María López devolvió Cien años con retraso
(5, 5, '2025-09-25', '2025-10-09', '2025-10-12', TRUE);    -- Carlos Martínez devolvió 1984 con retraso

