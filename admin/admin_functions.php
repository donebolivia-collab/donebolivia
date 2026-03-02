<?php
// Funciones auxiliares para el panel de administración

// Verificar si el usuario es administrador
function esAdmin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Requerir autenticación de admin
function requiereAdmin() {
    if (!esAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// Obtener datos del admin actual
function getAdminActual() {
    if (!esAdmin()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM administradores WHERE id = ? AND activo = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

// Registrar acción del admin
function registrarAccionAdmin($accion, $tabla_afectada = null, $registro_id = null, $detalles = null) {
    if (!esAdmin()) return;

    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO log_acciones (usuario_id, accion, tabla_afectada, registro_id, detalles, ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $accion,
            $tabla_afectada,
            $registro_id,
            $detalles,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Error registrando acción admin: " . $e->getMessage());
    }
}

// Obtener estadísticas generales
function getEstadisticasGenerales() {
    $db = getDB();

    $stats = [];

    // Total usuarios
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
    $stats['total_usuarios'] = $stmt->fetch()['total'];

    // Usuarios nuevos hoy
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE DATE(fecha_registro) = CURDATE()");
    $stats['usuarios_hoy'] = $stmt->fetch()['total'];

    // Usuarios activos (últimos 30 días)
    $stmt = $db->query("SELECT COUNT(DISTINCT usuario_id) as total FROM visitas WHERE fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['usuarios_activos_30d'] = $stmt->fetch()['total'];

    // Total productos
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos");
    $stats['total_productos'] = $stmt->fetch()['total'];

    // Productos activos
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    $stats['productos_activos'] = $stmt->fetch()['total'];

    // Productos nuevos hoy
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos WHERE DATE(fecha_publicacion) = CURDATE()");
    $stats['productos_hoy'] = $stmt->fetch()['total'];

    // Total visitas hoy
    $stmt = $db->query("SELECT COUNT(*) as total FROM visitas WHERE DATE(fecha_visita) = CURDATE()");
    $stats['visitas_hoy'] = $stmt->fetch()['total'];

    // Total búsquedas
    $stmt = $db->query("SELECT COUNT(*) as total FROM busquedas");
    $stats['total_busquedas'] = $stmt->fetch()['total'];

    // Reportes pendientes
    $stmt = $db->query("SELECT COUNT(*) as total FROM reportes WHERE estado = 'pendiente'");
    $stats['reportes_pendientes'] = $stmt->fetch()['total'];

    return $stats;
}

// Obtener gráfico de registros (últimos 30 días)
function getGraficoRegistros() {
    $db = getDB();
    $stmt = $db->query("
        SELECT DATE(fecha_registro) as fecha, COUNT(*) as total
        FROM usuarios
        WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(fecha_registro)
        ORDER BY fecha ASC
    ");
    return $stmt->fetchAll();
}

// Obtener gráfico de productos (últimos 30 días)
function getGraficoProductos() {
    $db = getDB();
    $stmt = $db->query("
        SELECT DATE(fecha_publicacion) as fecha, COUNT(*) as total
        FROM productos
        WHERE fecha_publicacion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(fecha_publicacion)
        ORDER BY fecha ASC
    ");
    return $stmt->fetchAll();
}

// Obtener gráfico de visitas (últimos 7 días)
function getGraficoVisitas() {
    $db = getDB();
    $stmt = $db->query("
        SELECT DATE(fecha_visita) as fecha, COUNT(*) as total
        FROM visitas
        WHERE fecha_visita >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(fecha_visita)
        ORDER BY fecha ASC
    ");
    return $stmt->fetchAll();
}

// Obtener búsquedas más populares
function getBusquedasPopulares($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT termino, COUNT(*) as veces, SUM(resultados) as total_resultados
        FROM busquedas
        WHERE fecha_busqueda >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY termino
        ORDER BY veces DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Obtener productos más vistos
function getProductosMasVistos($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.id, p.titulo, p.precio, COUNT(v.id) as visitas
        FROM productos p
        LEFT JOIN visitas v ON v.pagina LIKE CONCAT('%producto_id=', p.id, '%')
        WHERE v.fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
        ORDER BY visitas DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Obtener usuarios más activos
function getUsuariosMasActivos($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.email, COUNT(p.id) as publicaciones
        FROM usuarios u
        LEFT JOIN productos p ON p.usuario_id = u.id
        GROUP BY u.id
        ORDER BY publicaciones DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Cambiar estado de usuario
function cambiarEstadoUsuario($usuario_id, $activo) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $result = $stmt->execute([$activo, $usuario_id]);

    if ($result) {
        registrarAccionAdmin(
            $activo ? 'activar_usuario' : 'desactivar_usuario',
            'usuarios',
            $usuario_id
        );
    }

    return $result;
}

// Eliminar usuario
function eliminarUsuario($usuario_id) {
    $db = getDB();

    try {
        $db->beginTransaction();

        // Eliminar productos del usuario
        $stmt = $db->prepare("DELETE FROM productos WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);

        // Eliminar usuario
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);

        $db->commit();

        registrarAccionAdmin('eliminar_usuario', 'usuarios', $usuario_id);
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error eliminando usuario: " . $e->getMessage());
        return false;
    }
}

// Cambiar estado de producto
function cambiarEstadoProducto($producto_id, $activo) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE productos SET activo = ? WHERE id = ?");
    $result = $stmt->execute([$activo, $producto_id]);

    if ($result) {
        registrarAccionAdmin(
            $activo ? 'activar_producto' : 'desactivar_producto',
            'productos',
            $producto_id
        );
    }

    return $result;
}

// Eliminar producto (COMPLETO - Archivos + BD)
function eliminarProducto($producto_id) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // PASO 1: Obtener información del producto para auditoría
        $stmt_info = $db->prepare("SELECT titulo, usuario_id FROM productos WHERE id = ?");
        $stmt_info->execute([$producto_id]);
        $producto_info = $stmt_info->fetch();
        
        if (!$producto_info) {
            throw new Exception("Producto no encontrado");
        }
        
        // PASO 2: Obtener y eliminar imágenes físicas
        $stmt_imgs = $db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE producto_id = ?");
        $stmt_imgs->execute([$producto_id]);
        $imagenes = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);
        
        $imagenes_eliminadas = 0;
        foreach ($imagenes as $imagen) {
            $ruta_completa = UPLOAD_PATH . $imagen;
            if (file_exists($ruta_completa)) {
                if (unlink($ruta_completa)) {
                    $imagenes_eliminadas++;
                } else {
                    error_log("Admin: No se pudo eliminar archivo: " . $ruta_completa);
                }
            }
        }
        
        // PASO 3: Eliminar registros en orden correcto (respetando integridad referencial)
        // 3.1 Eliminar imágenes de BD
        $stmt_del_imgs = $db->prepare("DELETE FROM producto_imagenes WHERE producto_id = ?");
        $stmt_del_imgs->execute([$producto_id]);
        
        // 3.2 Eliminar badges asociados
        $stmt_del_badges = $db->prepare("DELETE FROM producto_badges WHERE producto_id = ?");
        $stmt_del_badges->execute([$producto_id]);
        
        // 3.3 Eliminar reportes asociados
        $stmt_del_reports = $db->prepare("DELETE FROM denuncias WHERE producto_id = ?");
        $stmt_del_reports->execute([$producto_id]);
        
        // 3.4 Eliminar favoritos asociados
        $stmt_del_favs = $db->prepare("DELETE FROM favoritos WHERE producto_id = ?");
        $stmt_del_favs->execute([$producto_id]);
        
        // 3.5 Eliminar registro de visitas asociadas
        $stmt_del_visitas = $db->prepare("DELETE FROM visitas WHERE pagina LIKE ?");
        $stmt_del_visitas->execute(["%producto_id={$producto_id}%"]);
        
        // 3.6 Eliminar el producto final
        $stmt_del_producto = $db->prepare("DELETE FROM productos WHERE id = ?");
        $result = $stmt_del_producto->execute([$producto_id]);
        
        $db->commit();
        
        // Registrar acción administrativa con detalles completos
        registrarAccionAdmin(
            'eliminar_producto_completo', 
            'productos', 
            $producto_id, 
            "Producto: '{$producto_info['titulo']}' | Usuario ID: {$producto_info['usuario_id']} | Imágenes eliminadas: {$imagenes_eliminadas}"
        );
        
        // Auditoría detallada
        error_log("ADMIN: Producto ID {$producto_id} eliminado completamente. Imágenes físicas eliminadas: {$imagenes_eliminadas}");
        
        return $result;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("ADMIN: Error CRÍTICO eliminando producto {$producto_id}: " . $e->getMessage());
        return false;
    }
}

// Obtener configuración
function getConfiguracion($clave) {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->execute([$clave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : null;
}

// Actualizar configuración
function actualizarConfiguracion($clave, $valor) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO configuracion (clave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = ?
    ");
    $result = $stmt->execute([$clave, $valor, $valor]);

    if ($result) {
        registrarAccionAdmin('actualizar_configuracion', 'configuracion', null, "$clave = $valor");
    }

    return $result;
}

// Formatear número
function formatearNumero($numero) {
    return number_format($numero, 0, ',', '.');
}

// Calcular porcentaje de cambio
function calcularCambio($actual, $anterior) {
    if ($anterior == 0) {
        return $actual > 0 ? 100 : 0;
    }
    return (($actual - $anterior) / $anterior) * 100;
}
?>
