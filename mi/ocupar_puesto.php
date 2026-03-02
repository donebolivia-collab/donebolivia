<?php
/**
 * DONE! - Ocupar Puesto en Feria
 * Lógica para asignar una tienda existente a un puesto libre.
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar login
if (!estaLogueado()) {
    // Redirigir al login y luego volver aquí
    $current_url = $_SERVER['REQUEST_URI'];
    header('Location: /auth/login.php?redirect=' . urlencode($current_url));
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$feria_sector = $_GET['feria_sector'] ?? '';
$feria_city = $_GET['feria_city'] ?? '';

// Validar parámetros
if (empty($feria_sector) || empty($feria_city)) {
    die("Error: Faltan parámetros de ubicación.");
}

$db = getDB();

// 1. Obtener tiendas del usuario
$stmt = $db->prepare("SELECT id, nombre, slug, categoria, ciudad FROM tiendas WHERE usuario_id = ? AND estado = 'activo'");
$stmt->execute([$usuario_id]);
$tiendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no tiene tiendas, redirigir al creador
if (empty($tiendas)) {
    $redirect_url = "/mi/crear_tienda.php?feria_sector=" . urlencode($feria_sector) . "&feria_city=" . urlencode($feria_city);
    header("Location: " . $redirect_url);
    exit;
}

// 2. Procesar la selección (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tienda_id = $_POST['tienda_id'] ?? '';
    
    if ($tienda_id === 'new') {
        // Opción: Crear Nueva
        $redirect_url = "/mi/crear_tienda.php?feria_sector=" . urlencode($feria_sector) . "&feria_city=" . urlencode($feria_city);
        header("Location: " . $redirect_url);
        exit;
    } elseif (!empty($tienda_id)) {
        // Opción: Asignar Tienda Existente
        // Verificar propiedad
        $stmt = $db->prepare("SELECT id FROM tiendas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$tienda_id, $usuario_id]);
        if ($stmt->fetch()) {
            // Actualizar ubicación
            // Intentar crear columnas si no existen (Hack rápido)
            try {
                $db->exec("ALTER TABLE tiendas ADD COLUMN categoria VARCHAR(50) DEFAULT NULL");
                $db->exec("ALTER TABLE tiendas ADD COLUMN ciudad VARCHAR(50) DEFAULT NULL");
            } catch (Exception $e) {}

            $update = $db->prepare("UPDATE tiendas SET categoria = ?, ciudad = ? WHERE id = ?");
            $update->execute([$feria_sector, $feria_city, $tienda_id]);
            
            // Redirigir a la feria
            header("Location: /feria.php?dept=" . urlencode($feria_city) . "&success=assigned");
            exit;
        }
    }
}

// 3. Renderizar UI de Selección
$titulo = "Ocupar Puesto - Done! Feria";
require_once __DIR__ . '/../includes/header.php';

// Traductor visual
$dept_names = ['LPZ'=>'La Paz', 'ALT'=>'El Alto', 'SCZ'=>'Santa Cruz', 'CBA'=>'Cochabamba', 'ORU'=>'Oruro', 'PTS'=>'Potosí', 'TJA'=>'Tarija', 'CHQ'=>'Chuquisaca', 'BEN'=>'Beni', 'PND'=>'Pando'];
$sector_names = ['tech'=>'Tecnología', 'fashion'=>'Ropa', 'home'=>'Hogar', 'auto'=>'Vehículos', 'food'=>'Comidas', 'toys'=>'Juguetes', 'tools'=>'Herramientas', 'electro'=>'Electrodomésticos', 'realestate'=>'Inmuebles'];
$city_nice = $dept_names[$feria_city] ?? $feria_city;
$sector_nice = $sector_names[$feria_sector] ?? $feria_sector;
?>

<div style="max-width: 600px; margin: 60px auto; padding: 20px;">
    
    <div class="text-center mb-5">
        <h1 class="fw-bold mb-2">¡Encontraste un Puesto Libre!</h1>
        <p class="text-muted">¿Qué tienda quieres colocar aquí?</p>
        
        <div class="alert alert-warning d-inline-block border-0 shadow-sm mt-3 px-4 py-2">
            <i class="fas fa-map-marker-alt me-2"></i>
            <strong><?php echo $sector_nice; ?></strong> en <strong><?php echo $city_nice; ?></strong>
        </div>
    </div>

    <form method="POST" action="">
        <div class="list-group shadow-sm mb-4">
            
            <!-- Opción: Crear Nueva -->
            <label class="list-group-item list-group-item-action p-4 d-flex align-items-center gap-3 bg-light">
                <input class="form-check-input flex-shrink-0" type="radio" name="tienda_id" value="new" style="transform: scale(1.5);">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div class="bg-white rounded-circle d-flex align-items-center justify-content-center border" style="width: 50px; height: 50px;">
                        <i class="fas fa-plus text-primary fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-primary">Crear una Nueva Tienda</h5>
                        <small class="text-muted">Empieza desde cero para este puesto</small>
                    </div>
                </div>
            </label>

            <!-- Lista de Tiendas Existentes -->
            <?php foreach ($tiendas as $t): ?>
                <label class="list-group-item list-group-item-action p-4 d-flex align-items-center gap-3">
                    <input class="form-check-input flex-shrink-0" type="radio" name="tienda_id" value="<?php echo $t['id']; ?>" style="transform: scale(1.5);">
                    <div class="d-flex align-items-center gap-3 w-100">
                        <!-- Avatar Simulado -->
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center border" style="width: 50px; height: 50px; font-weight: bold; color: #555;">
                            <?php echo strtoupper(substr($t['nombre'], 0, 1)); ?>
                        </div>
                        
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($t['nombre']); ?></h5>
                            <small class="text-muted d-block">
                                <?php if ($t['ciudad']): ?>
                                    <i class="fas fa-map-pin me-1"></i> Actualmente en: <?php echo $dept_names[$t['ciudad']] ?? $t['ciudad']; ?>
                                <?php else: ?>
                                    <i class="fas fa-globe me-1"></i> Sin ubicación asignada
                                <?php endif; ?>
                            </small>
                        </div>

                        <span class="badge bg-secondary rounded-pill">Mover aquí</span>
                    </div>
                </label>
            <?php endforeach; ?>

        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg py-3 fw-bold" style="background: #ff6b35; border: none;">
                Confirmar Ubicación
            </button>
            <a href="/feria.php" class="btn btn-outline-secondary py-2">Cancelar</a>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>