<?php
/**
 * API: Crear producto completo con imágenes (desde editor visual)
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
    $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
    $subcategoria_id = isset($_POST['subcategoria_id']) ? (int)$_POST['subcategoria_id'] : 0;
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0;
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
    // Normalizar estado (ELIMINADO el str_replace prematuro que causaba confusión)

    $departamento_codigo = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
    $municipio_codigo = isset($_POST['municipio']) ? trim($_POST['municipio']) : '';
    $categoria_tienda = isset($_POST['categoria_tienda']) ? trim($_POST['categoria_tienda']) : null;
    $badges = isset($_POST['badges']) ? json_decode($_POST['badges'], true) : [];
    if (!is_array($badges)) {
        $badges = [];
    }

    // Validaciones
    if ($categoria_id <= 0) {
        throw new Exception('Categoría inválida');
    }

    if ($subcategoria_id <= 0) {
        throw new Exception('Subcategoría inválida');
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

    // Validación actualizada simplificada y robusta
    $mapa_estados = [
        'nuevo' => 'Nuevo',
        'como nuevo' => 'Como Nuevo',
        'buen estado' => 'Buen Estado',
        'aceptable' => 'Aceptable'
        // Soporte legacy
        ,'como_nuevo' => 'Como Nuevo'
        ,'buen_estado' => 'Buen Estado'
    ];
    
    $estado_key = strtolower(trim($estado));
    
    if (!array_key_exists($estado_key, $mapa_estados)) {
        // Fallback permisivo
        if(empty($estado)) {
             throw new Exception('DEBUG: El estado está vacío.');
        }
    } else {
        // Asignar el valor correcto formateado para la BD
        $estado = $mapa_estados[$estado_key];
    }

    if (empty($departamento_codigo) || empty($municipio_codigo)) {
        throw new Exception('Ubicación incompleta');
    }

    // Validar imágenes
    if (!isset($_FILES['imagenes']) || empty($_FILES['imagenes']['name'][0])) {
        throw new Exception('Debes subir al menos 1 imagen');
    }

    $num_imagenes = count($_FILES['imagenes']['name']);
    if ($num_imagenes > 5) {
        throw new Exception('Máximo 5 imágenes permitidas');
    }

    // NUEVO: Detectar si es edición o creación para limpiar imágenes anteriores
    $es_edicion = false;
    $imagenes_anteriores = [];
    
    // Verificar si viene de un formulario de edición (campo oculto producto_id)
    $producto_id_edicion = isset($_POST['producto_id_edicion']) ? (int)$_POST['producto_id_edicion'] : 0;
    
    if ($producto_id_edicion > 0) {
        // ES EDICIÓN - Obtener imágenes existentes para eliminarlas
        $stmt_check = $db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
        $stmt_check->execute([$producto_id_edicion, $usuario_id]);
        if ($stmt_check->fetch()) {
            $es_edicion = true;
            
            // Obtener imágenes existentes
            $stmt_imgs_ant = $db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE producto_id = ?");
            $stmt_imgs_ant->execute([$producto_id_edicion]);
            $imagenes_anteriores = $stmt_imgs_ant->fetchAll(PDO::FETCH_COLUMN);
        }
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
    $db->beginTransaction();

    $usuario_id = $_SESSION['usuario_id'];

    // Verificar si la columna categoria_tienda existe
    $columna_existe = false;
    try {
        $check = $db->query("SHOW COLUMNS FROM productos LIKE 'categoria_tienda'");
        $columna_existe = $check->rowCount() > 0;
    } catch (Exception $e) {
        $columna_existe = false;
    }

    // Insertar producto
    if ($columna_existe) {
        $stmt = $db->prepare("
            INSERT INTO productos (
                usuario_id, categoria_id, subcategoria_id, categoria_tienda,
                titulo, descripcion, precio, estado,
                departamento_codigo, municipio_codigo,
                departamento_nombre, municipio_nombre,
                activo, fecha_publicacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $usuario_id,
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
            $municipio_nombre
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO productos (
                usuario_id, categoria_id, subcategoria_id,
                titulo, descripcion, precio, estado,
                departamento_codigo, municipio_codigo,
                departamento_nombre, municipio_nombre,
                activo, fecha_publicacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $usuario_id,
            $categoria_id,
            $subcategoria_id,
            $titulo,
            $descripcion,
            $precio,
            $estado,
            $departamento_codigo,
            $municipio_codigo,
            $departamento_nombre,
            $municipio_nombre
        ]);
    }

    $producto_id = $db->lastInsertId();

    // Insertar badges
    if (!empty($badges)) {
        $stmt_badge = $db->prepare("INSERT INTO producto_badges (producto_id, badge_id) VALUES (?, ?)");
        foreach ($badges as $badge_key) {
            $stmt_badge->execute([$producto_id, $badge_key]);
        }
    }

    // Subir imágenes
    $imagenes_subidas = 0;
    $es_principal = true;

    for ($i = 0; $i < $num_imagenes; $i++) {
        if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
            $archivo = [
                'name' => $_FILES['imagenes']['name'][$i],
                'type' => $_FILES['imagenes']['type'][$i],
                'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                'error' => $_FILES['imagenes']['error'][$i],
                'size' => $_FILES['imagenes']['size'][$i]
            ];

            // Validar tamaño
            if ($archivo['size'] > 5 * 1024 * 1024) {
                throw new Exception('Imagen muy pesada: ' . $archivo['name'] . ' (máx 5MB)');
            }

            $resultado = subirImagen($archivo, 'productos', null, $producto_id);

            if (isset($resultado['success'])) {
                $stmt = $db->prepare("
                    INSERT INTO producto_imagenes (producto_id, nombre_archivo, es_principal, orden)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $producto_id,
                    $resultado['archivo'],
                    $es_principal ? 1 : 0,
                    $i
                ]);

                $imagenes_subidas++;
                $es_principal = false;
            }
        }
    }

    if ($imagenes_subidas === 0) {
        throw new Exception('No se pudo subir ninguna imagen');
    }

    // NUEVO: Eliminar imágenes antiguas si es edición
    if ($es_edicion && !empty($imagenes_anteriores)) {
        $imagenes_eliminadas_old = 0;
        foreach ($imagenes_anteriores as $img_antigua) {
            $ruta_antigua = __DIR__ . '/../uploads/' . $img_antigua;
            if (file_exists($ruta_antigua)) {
                if (unlink($ruta_antigua)) {
                    $imagenes_eliminadas_old++;
                } else {
                    error_log("Edición: No se pudo eliminar imagen antigua: " . $ruta_antigua);
                }
            }
        }
        
        // Eliminar registros de imágenes antiguas de BD
        $stmt_del_imgs_ant = $db->prepare("DELETE FROM producto_imagenes WHERE producto_id = ?");
        $stmt_del_imgs_ant->execute([$producto_id_edicion]);
        
        // CORRECCIÓN: Mantener el producto_id correcto para las nuevas imágenes
        // No sobreescribir $producto_id con $producto_id_edicion
        
        // Actualizar datos del producto existente
        $stmt_update = $db->prepare("
            UPDATE productos SET 
                categoria_id = ?, subcategoria_id = ?, categoria_tienda = ?,
                titulo = ?, descripcion = ?, precio = ?, estado = ?,
                departamento_codigo = ?, municipio_codigo = ?,
                departamento_nombre = ?, municipio_nombre = ?,
                fecha_actualizacion = NOW()
            WHERE id = ? AND usuario_id = ?
        ");
        
        $stmt_update->execute([
            $categoria_id, $subcategoria_id, $categoria_tienda,
            $titulo, $descripcion, $precio, $estado,
            $departamento_codigo, $municipio_codigo,
            $departamento_nombre, $municipio_nombre,
            $producto_id, $usuario_id
        ]);
        
        // Eliminar badges antiguos
        $stmt_del_badges_ant = $db->prepare("DELETE FROM producto_badges WHERE producto_id = ?");
        $stmt_del_badges_ant->execute([$producto_id]);
        
        error_log("Edición producto {$producto_id}: Imágenes antiguas eliminadas: {$imagenes_eliminadas_old}");
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => $es_edicion ? 'Producto actualizado correctamente' : 'Producto creado correctamente',
        'producto_id' => $producto_id,
        'imagenes_subidas' => $imagenes_subidas,
        'es_edicion' => $es_edicion,
        'imagenes_eliminadas' => $es_edicion ? ($imagenes_eliminadas_old ?? 0) : 0
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Error en crear_producto_completo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
