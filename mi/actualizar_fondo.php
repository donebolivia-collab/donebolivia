<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Verificar autenticación
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$db = getDbConnection();

// Verificar que se subió un archivo
if (!isset($_FILES['fondo_perfil']) || $_FILES['fondo_perfil']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se subió ningún archivo']);
    exit;
}

// Obtener el usuario actual
$stmt = $db->prepare("SELECT fondo_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

// Subir la nueva imagen de fondo
$resultado = subirImagen($_FILES['fondo_perfil'], 'portadas');

if ($resultado['success']) {
    // Eliminar fondo anterior si existe
    if ($usuario['fondo_perfil'] && file_exists('../uploads/portadas/' . $usuario['fondo_perfil'])) {
        unlink('../uploads/portadas/' . $usuario['fondo_perfil']);
    }
    
    // Actualizar base de datos
    try {
        $stmt = $db->prepare("UPDATE usuarios SET fondo_perfil = ? WHERE id = ?");
        $stmt->execute([$resultado['archivo'], $usuario_id]);
        
        echo json_encode(['success' => true, 'message' => 'Fondo actualizado correctamente']);
    } catch (PDOException $e) {
        // Si falla la BD, eliminar la imagen subida
        if (file_exists('../uploads/portadas/' . $resultado['archivo'])) {
            unlink('../uploads/portadas/' . $resultado['archivo']);
        }
        echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos']);
    }
} else {
    echo json_encode(['success' => false, 'error' => $resultado['error']]);
}
?>