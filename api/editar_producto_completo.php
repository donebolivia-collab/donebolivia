<?php
/**
 * API: Editar producto completo (todos los campos e imágenes)
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ubicaciones_bolivia.php';

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    // Validar datos POST
    $producto_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
    $subcategoria_id = isset($_POST['subcategoria_id']) ? (int)$_POST['subcategoria_id'] : 0;
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0;
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
    $departamento_codigo = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
    $municipio_codigo = isset($_POST['municipio']) ? trim($_POST['municipio']) : '';
    $categoria_tienda = isset($_POST['categoria_tienda']) ? trim($_POST['categoria_tienda']) : null;
    $badges = isset($_POST['badges']) ? json_decode($_POST['badges'], true) : [];
    if (!is_array($badges)) {
        $badges = [];
    }
    
    // Imágenes a eliminar (JSON array de IDs)
    $imagenes_eliminar = isset($_POST['imagenes_eliminar']) ? json_decode($_POST['imagenes_eliminar'], true) : [];
    
    // DEBUG: Mostrar imágenes a eliminar
    echo json_encode([
        'debug' => 'Imágenes a eliminar recibidas',
        'imagenes_eliminar' => $imagenes_eliminar,
        'producto_id' => $producto_id
    ]);
    exit;

    // Validaciones
    if ($producto_id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    if ($categoria_id <= 0 || $subcategoria_id <= 0) {
        throw new Exception('Categoría o subcategoría inválida');
    }

    if (empty($titulo) || strlen($titulo) < 10) {
        throw new Exception('El título debe tener al menos 10 caracteres');
    }

    if (empty($descripcion) || strlen($descripcion) < 20) {
        throw new Exception('La descripción debe tener al menos 20 caracteres');
    }

    if ($precio <= 0) {
        throw new Exception('El precio debe ser mayor a 0');
    }

    // Normalización de Estado Simplificada y Robusta
    $mapa_estados = [
        'nuevo' => 'Nuevo',
        'como nuevo' => 'Como Nuevo',
        'buen estado' => 'Buen Estado',
        'aceptable' => 'Aceptable'
        // Soporte legacy por si acaso
        ,'como_nuevo' => 'Como Nuevo'
        ,'buen_estado' => 'Buen Estado'
    ];

    $estado_key = strtolower(trim($estado)); // "Buen Estado" -> "buen estado"
    
    if (!array_key_exists($estado_key, $mapa_estados)) {
        // Fallback: Si no está en el mapa, intentamos pasar el valor original
        // Esto permite que si la DB acepta el valor, pase.
        // Solo lanzamos error si está vacío.
        if(empty($estado)) {
             throw new Exception('DEBUG: El estado está vacío.');
        }
        // Dejamos pasar el valor original capitalizado o como venga
        // $estado = $estado; 
    } else {
        // Asignar el valor correcto formateado para la BD
        $estado = $mapa_estados[$estado_key];
    }

    if (empty($departamento_codigo) || empty($municipio_codigo)) {
        throw new Exception('Ubicación incompleta');
    }

    // Obtener nombres de ubicación
    $departamentos = obtenerDepartamentosBolivia();
    $todosMunicipios = obtenerTodosMunicipiosConCodigos();

    $departamento_nombre = $departamentos[$departamento_codigo] ?? '';
    $municipioInfo = $todosMunicipios[$municipio_codigo] ?? null;
    $municipio_nombre = $municipioInfo ? $municipioInfo['nombre'] : '';

    if (empty($departamento_nombre) || empty($municipio_nombre)) {
        throw new Exception('Ubicación no válida');
    }

    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar propiedad del producto
    $stmt_check = $db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
    $stmt_check->execute([$producto_id, $usuario_id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Producto no encontrado o no tienes permiso');
    }

    $db->beginTransaction();

    // 1. Actualizar datos del producto
    // Verificar si la columna categoria_tienda existe
    $columna_existe = false;
    try {
        $check = $db->query("SHOW COLUMNS FROM productos LIKE 'categoria_tienda'");
        $columna_existe = $check->rowCount() > 0;
    } catch (Exception $e) {
        $columna_existe = false;
    }

    if ($columna_existe) {
        $stmt = $db->prepare("
            UPDATE productos SET
                categoria_id = ?,
                subcategoria_id = ?,
                categoria_tienda = ?,
                titulo = ?,
                descripcion = ?,
                precio = ?,
                estado = ?,
                departamento_codigo = ?,
                municipio_codigo = ?,
                departamento_nombre = ?,
                municipio_nombre = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([
            $categoria_id,
            $subcategoria_id,
            $categoria_tienda,
            $titulo,
            $descripcion,
            $precio,
            $estado,
            $departamento_codigo,
            $municipio_codigo,
            $departamento_nombre,
            $municipio_nombre,
            $producto_id,
            $usuario_id
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE productos SET
                categoria_id = ?,
                subcategoria_id = ?,
                titulo = ?,
                descripcion = ?,
                precio = ?,
                estado = ?,
                departamento_codigo = ?,
                municipio_codigo = ?,
                departamento_nombre = ?,
                municipio_nombre = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([
            $categoria_id,
            $subcategoria_id,
            $titulo,
            $descripcion,
            $precio,
            $estado,
            $departamento_codigo,
            $municipio_codigo,
            $departamento_nombre,
            $municipio_nombre,
            $producto_id,
            $usuario_id
        ]);
    }

    // 2. Actualizar badges
    $stmt_del_badges = $db->prepare("DELETE FROM producto_badges WHERE producto_id = ?");
    $stmt_del_badges->execute([$producto_id]);

    if (!empty($badges)) {
        $stmt_ins_badge = $db->prepare("INSERT INTO producto_badges (producto_id, badge_id) VALUES (?, ?)");
        foreach ($badges as $badge_key) {
            $stmt_ins_badge->execute([$producto_id, $badge_key]);
        }
    }

    // 3. Eliminar imágenes marcadas
    if (!empty($imagenes_eliminar) && is_array($imagenes_eliminar)) {
        foreach ($imagenes_eliminar as $img_id) {
            // Obtener nombre de archivo para borrar del disco
            $stmt_get_img = $db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE id = ? AND producto_id = ?");
            $stmt_get_img->execute([$img_id, $producto_id]);
            $img_data = $stmt_get_img->fetch();

            if ($img_data) {
                // Borrar registro DB
                $stmt_del = $db->prepare("DELETE FROM producto_imagenes WHERE id = ?");
                $stmt_del->execute([$img_id]);

                // Borrar archivo físico
                $ruta_archivo = UPLOAD_PATH . $img_data['nombre_archivo'];
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
        }
    }

    // 4. Subir nuevas imágenes
    if (isset($_FILES['imagenes_nuevas']) && !empty($_FILES['imagenes_nuevas']['name'][0])) {
        $num_nuevas = count($_FILES['imagenes_nuevas']['name']);
        
        // SIMPLIFICACIÓN: Permitir hasta 5 imágenes nuevas sin límite complejo
        if ($num_nuevas > 5) {
            throw new Exception("No puedes subir más de 5 imágenes a la vez");
        }

        for ($i = 0; $i < $num_nuevas; $i++) {
            if ($_FILES['imagenes_nuevas']['error'][$i] === UPLOAD_ERR_OK) {
                $archivo = [
                    'name' => $_FILES['imagenes_nuevas']['name'][$i],
                    'type' => $_FILES['imagenes_nuevas']['type'][$i],
                    'tmp_name' => $_FILES['imagenes_nuevas']['tmp_name'][$i],
                    'error' => $_FILES['imagenes_nuevas']['error'][$i],
                    'size' => $_FILES['imagenes_nuevas']['size'][$i]
                ];

                $resultado = subirImagen($archivo, 'productos', null, $producto_id, false);

                if (isset($resultado['success'])) {
                    $hash_archivo = hash_file('sha256', $archivo['tmp_name']);
                    
                    $stmt_ins = $db->prepare("
                        INSERT INTO producto_imagenes (producto_id, nombre_archivo, es_principal, orden, hash_archivo)
                        VALUES (?, ?, 0, 99, ?)
                    ");
                    
                    $execute_result = $stmt_ins->execute([
                        $producto_id,
                        $resultado['archivo'],
                        $hash_archivo
                    ]);
                    
                    if (!$execute_result) {
                        throw new Exception('Error al insertar imagen: ' . implode(' - ', $stmt_ins->errorInfo()));
                    }
                }
            }
        }
    }

    // 5. Reasignar imagen principal si es necesario
    // Verificar si hay alguna imagen principal
    $stmt_check_main = $db->prepare("SELECT id FROM producto_imagenes WHERE producto_id = ? AND es_principal = 1");
    $stmt_check_main->execute([$producto_id]);
    
    if (!$stmt_check_main->fetch()) {
        // No hay principal (se borró o no había), asignar a la primera (menor ID o orden)
        $stmt_set_main = $db->prepare("
            UPDATE producto_imagenes 
            SET es_principal = 1 
            WHERE producto_id = ? 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt_set_main->execute([$producto_id]);
    }
    
    // Verificar que quede al menos una imagen
    $stmt_final_count = $db->prepare("SELECT COUNT(*) FROM producto_imagenes WHERE producto_id = ?");
    $stmt_final_count->execute([$producto_id]);
    if ($stmt_final_count->fetchColumn() == 0) {
        throw new Exception("El producto debe tener al menos una imagen");
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Producto actualizado correctamente'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en editar_producto_completo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
