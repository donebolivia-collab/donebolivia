<?php
/**
 * DONE! - Crear Tienda
 * Versión Final Definitiva: Estructura y VALIDACIÓN IDÉNTICA a Register.php
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar que esté logueado
if (!estaLogueado()) {
    header('Location: /auth/login.php?redirect=' . urlencode('/mi/crear_tienda.php'));
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$error = '';
$success = '';

// Verificar si ya tiene tienda
$tienda_existente = null;
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nombre, slug, logo FROM tiendas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $tienda_existente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si tiene tienda Y NO viene de la feria (ni por GET ni por POST), redirigir al editor
    $is_feria_context = (isset($_GET['feria_sector']) && isset($_GET['feria_city'])) || 
                        (isset($_POST['feria_sector']) && isset($_POST['feria_city']));

    if ($tienda_existente && !$is_feria_context) {
        header('Location: /mi/tienda_editor.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error verificando tienda: " . $e->getMessage());
}

// Capturar contexto de Feria (GET o POST)
$feria_sector = $_REQUEST['feria_sector'] ?? '';
$feria_city   = $_REQUEST['feria_city'] ?? '';
$feria_pos    = $_REQUEST['feria_pos'] ?? ''; // Nueva variable: Posición Exacta

// ---------------------------------------------------------
// LÓGICA DE PROCESAMIENTO (CONTROLADOR)
// ---------------------------------------------------------

// 1. CONFIRMACIÓN DE MUDANZA (Escenario 1)
if (isset($_POST['confirmar_mudanza']) && $tienda_existente) {
    
    // Forzamos la captura de datos del POST
    $sector_final = $_POST['feria_sector'] ?? $feria_sector;
    $city_final   = $_POST['feria_city'] ?? $feria_city;
    $pos_final    = $_POST['feria_pos'] ?? $feria_pos;

    if ($sector_final && $city_final && is_numeric($pos_final)) {
        try {
            // INSERTAR EN FERIA_POSICIONES (Mapeo directo Symbaloo)
            // Primero verificamos si hay una tienda asignada a este usuario y sector/ciudad
            // pero como la estructura nueva permite una sola posicion por id...
            
            // Buscar ID de sector basado en slug (Priorizando activos)
            $stmtSec = $db->prepare("SELECT id FROM feria_sectores WHERE slug = ? AND activo = 1 ORDER BY id DESC LIMIT 1");
            $stmtSec->execute([$sector_final]);
            $secData = $stmtSec->fetch(PDO::FETCH_ASSOC);
            
            if (!$secData) throw new Exception("Sector no válido");
            $sectorId = $secData['id'];

            // CALCULAR BLOQUE Y SLOT (Igual que en Creación)
            $idx = intval($pos_final);
            $orden_bloque = floor($idx / 12) + 1;
            $slot_numero = ($idx % 12) + 1;
            
            // Buscar ID real del bloque
            $stmtB = $db->prepare("SELECT id FROM feria_bloques WHERE sector_id = ? AND orden = ? LIMIT 1");
            $stmtB->execute([$sectorId, $orden_bloque]);
            $bloque = $stmtB->fetch(PDO::FETCH_ASSOC);
            
            $bloque_id = null;
            if ($bloque) {
                $bloque_id = $bloque['id'];
            } else {
                // Auto-crear si no existe (Safety Net)
                $nombres = [1=>'Bloque Tiwanaku', 2=>'Bloque Illimani', 3=>'Bloque Sajama', 4=>'Bloque Uyuni', 5=>'Bloque Madidi', 6=>'Bloque Titicaca'];
                $nombreBloque = $nombres[$orden_bloque] ?? "Bloque $orden_bloque";
                $insB = $db->prepare("INSERT INTO feria_bloques (sector_id, nombre, orden) VALUES (?, ?, ?)");
                $insB->execute([$sectorId, $nombreBloque, $orden_bloque]);
                $bloque_id = $db->lastInsertId();
            }

            // INSERTAR O ACTUALIZAR
            // Usamos ON DUPLICATE KEY UPDATE para el índice único (sector_id, ciudad, posicion_index)
            // Primero verificamos si hay algún conflicto con el ID único
            
            // Intento de borrado previo para evitar conflictos extraños con claves
            $db->prepare("DELETE FROM feria_posiciones WHERE sector_id = ? AND ciudad = ? AND posicion_index = ?")->execute([$sectorId, $city_final, $pos_final]);

            // LÓGICA DE UNICIDAD (UX): Una tienda solo puede estar en UN lugar a la vez.
            // Borramos cualquier posición previa de este usuario en CUALQUIER sector/ciudad.
            $db->prepare("DELETE FROM feria_posiciones WHERE usuario_id = ?")->execute([$usuario_id]);

            // ---------------------------------------------------------
            // ARQUITECTURA DETERMINISTA (LOGO)
            // ---------------------------------------------------------
            // Ya no usamos el logo de la BD si es posible, construimos el nombre estándar.
            // Pero por compatibilidad legacy, si la columna logo tiene algo, lo usamos, 
            // sino construimos el default.
            $logo_final = $tienda_existente['logo'];
            if (empty($logo_final)) {
                // Si está vacío, asumimos el estándar si existe el archivo, o null
                $standard_logo = "logo_tienda_" . $tienda_existente['id'] . ".png";
                if (file_exists(__DIR__ . '/../uploads/logos/' . $standard_logo)) {
                    $logo_final = $standard_logo;
                }
            }

            $stmt = $db->prepare("
                INSERT INTO feria_posiciones (sector_id, ciudad, posicion_index, bloque_id, slot_numero, tienda_nombre, tienda_url, tienda_logo, estado, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ocupado', ?) 
            ");
            
            $resultado = $stmt->execute([
                $sectorId, 
                $city_final, 
                $pos_final,
                $bloque_id,
                $slot_numero, 
                $tienda_existente['nombre'],
                $tienda_existente['slug'],
                $logo_final,
                $usuario_id
            ]);

            if ($resultado) {
                // Redirigir ÉXITO
                header('Location: /feria.php?dept=' . urlencode($city_final) . '&success=assigned');
                exit;
            } else {
                $error = "Error: No se pudo actualizar la base de datos.";
            }
            
        } catch (Exception $e) {
            $error = "Error al asignar tienda: " . $e->getMessage();
        }
    } else {
        $error = "Faltan datos de sector, ciudad o posición.";
    }
}

// 2. CREACIÓN DE TIENDA (Escenario 2)
if (!$tienda_existente && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirmar_mudanza'])) {
    // ... (lógica de creación existente)
    $nombre = trim($_POST['nombre'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    
    // Validaciones básicas (el JS hace el trabajo pesado visual)
    if (empty($nombre) || empty($slug) || empty($whatsapp)) {
        $error = 'Todos los campos son obligatorios.';
    } else {
        try {
            $db = getDB(); // Asegurar conexión
            
            // INTENTO DE AUTO-MIGRACIÓN (Añadir columnas si no existen)
            // Esto es un hack para producción rápida sin acceso a shell
            try {
                $db->exec("ALTER TABLE tiendas ADD COLUMN categoria VARCHAR(50) DEFAULT NULL");
                $db->exec("ALTER TABLE tiendas ADD COLUMN ciudad VARCHAR(50) DEFAULT NULL");
            } catch (Exception $e) {
                // Ignorar error si ya existen
            }

            $stmt = $db->prepare("SELECT id FROM tiendas WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $error = 'Esa URL ya está en uso. Por favor elige otra.';
            } else {
                // INSERT con Categoría y Ciudad
                $stmt = $db->prepare("
                    INSERT INTO tiendas (usuario_id, nombre, slug, descripcion, whatsapp, estado, fecha_creacion, categoria, ciudad)
                    VALUES (?, ?, ?, '', ?, 'activo', NOW(), ?, ?)
                ");
                $stmt->execute([$usuario_id, $nombre, $slug, $whatsapp, $feria_sector, $feria_city]);
                
                // -----------------------------------------------------
                // NUEVA LÓGICA PRO: ASIGNACIÓN AUTOMÁTICA DE PUESTO
                // -----------------------------------------------------
                $nueva_tienda_id = $db->lastInsertId();
                
                if ($feria_sector && $feria_city && is_numeric($feria_pos)) {
                    try {
                        // REINGENIERÍA V2: Asignación Relacional
                        // Buscar ID de sector basado en slug (Priorizando activos)
                        $stmtSec = $db->prepare("SELECT id FROM feria_sectores WHERE slug = ? AND activo = 1 ORDER BY id DESC LIMIT 1");
                        $stmtSec->execute([$feria_sector]);
                        $secData = $stmtSec->fetch(PDO::FETCH_ASSOC);
                        
                        if ($secData) {
                            $sectorId = $secData['id'];

                            // Calcular Bloque y Slot
                            $idx = intval($feria_pos);
                            $bloque_id = null;
                            $slot_numero = null;

                            // Traducir Índice 0-71 a Bloque/Slot
                            // Bloque 1 (Index 0-11), Bloque 2 (Index 12-23)...
                            $orden_bloque = floor($idx / 12) + 1;
                            $slot_numero = ($idx % 12) + 1;
                            
                            // Buscar ID real del bloque
                            $stmtB = $db->prepare("SELECT id FROM feria_bloques WHERE sector_id = ? AND orden = ? LIMIT 1");
                            $stmtB->execute([$sectorId, $orden_bloque]);
                            $bloque = $stmtB->fetch(PDO::FETCH_ASSOC);
                            
                            if ($bloque) {
                                $bloque_id = $bloque['id'];
                            } else {
                                // Auto-crear si no existe (Safety Net)
                                $nombres = [1=>'Bloque Tiwanaku', 2=>'Bloque Illimani', 3=>'Bloque Sajama', 4=>'Bloque Uyuni', 5=>'Bloque Madidi', 6=>'Bloque Titicaca'];
                                $nombreBloque = $nombres[$orden_bloque] ?? "Bloque $orden_bloque";
                                $insB = $db->prepare("INSERT INTO feria_bloques (sector_id, nombre, orden) VALUES (?, ?, ?)");
                                $insB->execute([$sectorId, $nombreBloque, $orden_bloque]);
                                $bloque_id = $db->lastInsertId();
                            }
                            
                            // Intento de borrado previo (copiado de la lógica de mudanza para consistencia)
                            $db->prepare("DELETE FROM feria_posiciones WHERE sector_id = ? AND ciudad = ? AND posicion_index = ?")->execute([$sectorId, $feria_city, $feria_pos]);

                            // INSERTAR con DATOS RELACIONALES
                            // Nota: tienda_logo es NULL inicialmente porque la tienda es nueva y no tiene logo aún.
                            // Cuando suba el logo en el editor, el sistema estandarizado lo nombrará logo_tienda_{ID}.png
                            // y actualizará esta tabla.
                            $stmtFeria = $db->prepare("
                                INSERT INTO feria_posiciones (sector_id, ciudad, posicion_index, bloque_id, slot_numero, tienda_nombre, tienda_url, tienda_logo, estado, usuario_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'ocupado', ?) 
                            ");
                            $stmtFeria->execute([$sectorId, $feria_city, $feria_pos, $bloque_id, $slot_numero, $nombre, $slug, $usuario_id]);
                            
                            // Si se asignó puesto, redirigir directo a la feria
                            header('Location: /feria.php?dept=' . urlencode($feria_city) . '&success=created_and_assigned');
                            exit;
                        }
                    } catch (Exception $e) {
                        // Si falla la asignación, solo logueamos
                        error_log("Error asignando puesto automático: " . $e->getMessage());
                    }
                }

                // Notificación a Telegram
                require_once __DIR__ . '/../config/telegram_config.php';
                $mensaje = "🏪 <b>Nueva Tienda Creada</b>\n\n" .
                           "🛒 <b>Nombre:</b> " . $nombre . "\n" .
                           "🔗 <b>Slug:</b> /tienda/" . $slug . "\n" .
                           "📱 <b>WhatsApp:</b> " . $whatsapp . "\n" .
                           "👤 <b>Usuario ID:</b> " . $usuario_id;
                enviarNotificacionTelegram($mensaje);
                header('Location: /mi/tienda_editor.php?success=created');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error creando tienda: " . $e->getMessage());
            $error = 'Error al crear la tienda. Por favor intenta nuevamente.';
        }
    }
}

$titulo = "Crea tu Tienda Virtual";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Estructura CLONADA de register.php */

/* Contenedor GRIS que envuelve todo */
.register-wrap {
    max-width: 750px;
    margin: 40px auto;
    padding: 30px;
    background: #f5f5f5;
    border-radius: 8px;
}

.register-title { 
    text-align: center; 
    font-size: 28px; 
    font-weight: 700; 
    margin-bottom: 30px; 
    color: #2c3e50; 
}

/* Tarjetas BLANCAS dentro del contenedor gris */
.register-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.register-card h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #2c3e50;
    border-bottom: 2px solid #f3f4f6;
    padding-bottom: 12px;
}

.field-group { margin-bottom: 0; }

.input-text {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    height: 48px;
    box-sizing: border-box;
    background: white;
    transition: all 0.3s ease;
    color: #333;
}

.input-text:focus {
    outline: none;
    border-color: #ff6b1a;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
    background: #fffbf8;
}

/* MENSAJES DE ERROR VISUALES (Clonados de Register.php) */
.section-error {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: 12px;
    padding: 12px 14px;
    background: #fef2f2;
    border-radius: 6px;
    border-left: 3px solid #dc2626;
    display: none; /* Oculto por defecto */
}

/* Alerta de error global del servidor */
.alert-error {
    background: #fef2f2;
    color: #dc2626;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Botones IGUALES (Grid 50/50) */
.form-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
}

.btn-cancel {
    background: white;
    color: #666;
    border: 2px solid #d0d0d0;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 50px;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-cancel:hover {
    border-color: #999;
    color: #333;
}

.btn-submit {
    background: #ff6b1a;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}
.btn-submit:hover {
    background: #e85e00;
}

/* Prefijo URL estilo Register */
.url-container {
    display: flex;
    align-items: center;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    background: white;
    overflow: hidden;
    transition: all 0.3s ease;
}
.url-container:focus-within {
    border-color: #ff6b1a;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
}
.url-prefix {
    background: #f8fafc;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    padding: 0 16px;
    height: 48px;
    display: flex;
    align-items: center;
    border-right: 1px solid #e2e8f0;
    white-space: nowrap;
}
.url-input {
    flex: 1;
    border: none;
    padding: 0 16px;
    height: 46px;
    font-size: 15px;
    font-family: inherit;
    outline: none;
    color: #333;
}
.url-helper {
    font-size: 13px;
    color: #64748b;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .register-wrap { padding: 20px 16px; margin: 20px auto; }
    .register-card { padding: 24px 20px; }
    .form-buttons { grid-template-columns: 1fr; }
    .url-prefix { padding: 0 12px; font-size: 13px; }
}
</style>

<!-- CONTENEDOR GRIS PRINCIPAL -->
<div class="register-wrap">
    
    <?php if ($feria_sector && $feria_city): 
        // Traductor simple
        $dept_names = ['LPZ'=>'La Paz', 'ALT'=>'El Alto', 'SCZ'=>'Santa Cruz', 'CBA'=>'Cochabamba', 'ORU'=>'Oruro', 'PTS'=>'Potosí', 'TJA'=>'Tarija', 'CHQ'=>'Chuquisaca', 'BEN'=>'Beni', 'PND'=>'Pando'];
        $sector_names = ['tech'=>'Tecnología', 'fashion'=>'Ropa', 'home'=>'Hogar', 'auto'=>'Vehículos', 'food'=>'Comidas', 'toys'=>'Juguetes', 'tools'=>'Herramientas', 'electro'=>'Electrodomésticos', 'realestate'=>'Inmuebles'];
        
        $city_nice = $dept_names[$feria_city] ?? $feria_city;
        $sector_nice = $sector_names[$feria_sector] ?? $feria_sector;
    ?>

        <!-- ESCENARIO 1: CONFIRMACIÓN DE MUDANZA (Solo si tiene tienda) -->
        <?php if ($tienda_existente): ?>
            <h1 class="register-title">Asigna tu Puesto en la Feria</h1>
            
            <div class="register-card text-center py-5">
                <h2 class="mb-4 border-0 pb-0">¡Hola! Ya tienes una tienda lista</h2>
                
                <div class="d-flex justify-content-center align-items-center gap-4 mb-4 flex-wrap">
                    <!-- Tu Tienda -->
                    <div class="p-3 border rounded bg-white shadow-sm d-flex flex-column align-items-center" style="min-width: 160px;">
                        <?php 
                        // LÓGICA DE VISUALIZACIÓN DE LOGO
                        // Intentamos usar el archivo estándar si la BD no tiene nada o tiene algo viejo
                        $logo_mostrar = $tienda_existente['logo'];
                        $logo_estandar_path = '/uploads/logos/logo_tienda_' . $tienda_existente['id'] . '.png';
                        // Nota: No podemos verificar file_exists de una URL relativa web fácilmente desde aquí sin __DIR__, 
                        // pero visualmente el navegador lo resolverá.
                        
                        if (empty($logo_mostrar)) {
                             // Si está vacío, probamos el estándar (asumiendo que existe, si no el navegador mostrará icono roto, 
                             // por eso ponemos onerror)
                             $logo_mostrar = 'logo_tienda_' . $tienda_existente['id'] . '.png';
                        }
                        ?>

                        <?php if (!empty($logo_mostrar)): ?>
                            <img src="/uploads/logos/<?php echo htmlspecialchars($logo_mostrar); ?>" 
                                 alt="Logo" 
                                 class="rounded-circle mb-2" 
                                 style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #eee;"
                                 onerror="this.onerror=null; this.src=''; this.parentNode.innerHTML='<div class=\'rounded-circle bg-light d-flex align-items-center justify-content-center mb-2\' style=\'width: 60px; height: 60px;\'><i class=\'fas fa-store text-muted fs-3\'></i></div>';">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px;">
                                <i class="fas fa-store text-muted fs-3"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fw-bold text-dark mb-0 text-center lh-sm"><?php echo htmlspecialchars($tienda_existente['nombre']); ?></div>
                        <small class="text-muted mt-1" style="font-size: 0.75rem;">Tu Tienda Actual</small>
                    </div>

                    <!-- Flecha -->
                    <i class="fas fa-arrow-right text-primary fs-3"></i>

                    <!-- Destino -->
                    <div class="p-3 border rounded bg-light shadow-sm" style="min-width: 150px;">
                        <div class="fw-bold text-primary mb-1">Sector <?php echo $sector_nice; ?></div>
                        <small class="text-muted"><?php echo $city_nice; ?></small>
                    </div>
                </div>

                <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                    Al confirmar, tu tienda aparecerá visible en este puesto de la feria inmediatamente.
                </p>

                <form method="POST">
                    <input type="hidden" name="feria_sector" value="<?php echo htmlspecialchars($feria_sector); ?>">
                    <input type="hidden" name="feria_city" value="<?php echo htmlspecialchars($feria_city); ?>">
                    <input type="hidden" name="feria_pos" value="<?php echo htmlspecialchars($feria_pos); ?>">
                    <input type="hidden" name="confirmar_mudanza" value="1">
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="/feria.php" class="btn btn-outline-secondary px-4 py-2 text-decoration-none rounded">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-bold rounded shadow-sm text-white border-0" style="background: #ff6b1a;">
                            ✅ Confirmar y Ocupar Puesto
                        </button>
                    </div>
                </form>
            </div>
        
        <!-- ESCENARIO 2: FORMULARIO DE CREACIÓN (Si NO tiene tienda) -->
        <?php else: ?>
            <h1 class="register-title">Reserva tu Puesto en <?php echo $sector_nice; ?></h1>
            
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="fas fa-store-alt fs-4 me-3 text-primary"></i>
                <div>
                    <small class="text-uppercase fw-bold text-primary opacity-75">Ubicación Seleccionada</small>
                    <div class="fw-bold text-dark">Sector <?php echo $sector_nice; ?> - <?php echo $city_nice; ?></div>
                </div>
            </div>
    <?php endif; ?>

    <?php else: ?>
        <!-- Sin contexto de feria (Creación normal) -->
        <h1 class="register-title">Crea tu Tienda Virtual</h1>
    <?php endif; ?>

    <!-- MOSTRAR FORMULARIO SOLO SI NO TIENE TIENDA -->
    <?php if (!$tienda_existente): ?>
    
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="createStoreForm" novalidate>
            <input type="hidden" name="feria_sector" value="<?php echo htmlspecialchars($feria_sector); ?>">
            <input type="hidden" name="feria_city" value="<?php echo htmlspecialchars($feria_city); ?>">
            
            <!-- TARJETA 1: DATOS DE LA TIENDA -->
            <div class="register-card">
                <h2>Datos de la Tienda</h2>
                <div class="field-group">
                    <input type="text" 
                           name="nombre" 
                           id="nombre"
                           placeholder="Nombre de tu Tienda (Ej: Tecnología Bolivia)" 
                           class="input-text"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                </div>
                <!-- Mensaje de error oculto -->
                <div class="section-error" id="error-nombre"></div>
            </div>

            <!-- TARJETA 2: URL -->
            <div class="register-card">
                <h2>Dirección Web (Link)</h2>
                <div class="field-group">
                    <div class="url-container">
                        <span class="url-prefix">donebolivia.com/tienda/</span>
                        <input type="text" 
                               name="slug" 
                               id="slug"
                               placeholder="mi-tienda" 
                               class="url-input"
                               value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>">
                    </div>
                    <div class="url-helper">
                        Esta será tu dirección única para compartir en redes sociales.
                    </div>
                </div>
                <!-- Mensaje de error oculto -->
                <div class="section-error" id="error-slug"></div>
            </div>

            <!-- TARJETA 3: CONTACTO -->
            <div class="register-card">
                <h2>Contacto Directo</h2>
                <div class="field-group">
                    <input type="tel" 
                           name="whatsapp" 
                           id="whatsapp"
                           placeholder="Número de WhatsApp para ventas (Ej: 70123456)" 
                           class="input-text"
                           value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>"
                           maxlength="8">
                </div>
                <!-- Mensaje de error oculto -->
                <div class="section-error" id="error-whatsapp"></div>
            </div>

            <!-- BOTONES -->
            <div class="form-buttons">
                <a href="/" class="btn-cancel">Cancelar</a>
                <button type="submit" class="btn-submit">
                    <?php echo ($feria_sector) ? 'Crear y Ocupar Puesto' : 'Crear Tienda'; ?>
                </button>
            </div>

        </form>
    <?php endif; ?>

</div>

<script>
(function() {
    const form = document.getElementById('createStoreForm');
    const nombreInput = document.getElementById('nombre');
    const slugInput = document.getElementById('slug');
    const whatsappInput = document.getElementById('whatsapp');

    // Funciones de utilidad (Clonadas de Register.php)
    function clearErrors() {
        document.querySelectorAll('.section-error').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    function showError(id, message) {
        const errorEl = document.getElementById(id);
        errorEl.innerHTML = `❌ ${message}`; // Icono de X
        errorEl.style.display = 'block';
    }

    // Auto-generación de slug
    nombreInput.addEventListener('input', function(e) {
        // Solo autocompletar si el usuario no ha editado el slug manualmente
        if (!slugInput.value || slugInput.dataset.manual !== 'true') {
            const normalized = e.target.value
                .toLowerCase()
                .replace(/[áàäâ]/g, 'a')
                .replace(/[éèëê]/g, 'e')
                .replace(/[íìïî]/g, 'i')
                .replace(/[óòöô]/g, 'o')
                .replace(/[úùüû]/g, 'u')
                .replace(/ñ/g, 'n')
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            slugInput.value = normalized;
        }
    });

    slugInput.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });

    // Validación al enviar
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        clearErrors();
        let hayErrores = false;

        // Validar Nombre
        if (!nombreInput.value.trim()) {
            showError('error-nombre', 'Debes ingresar el nombre de tu tienda');
            hayErrores = true;
        } else if (nombreInput.value.length < 3) {
            showError('error-nombre', 'El nombre debe tener al menos 3 caracteres');
            hayErrores = true;
        }

        // Validar URL
        if (!slugInput.value.trim()) {
            showError('error-slug', 'Debes ingresar una dirección web para tu tienda');
            hayErrores = true;
        } else if (!/^[a-z0-9\-]+$/.test(slugInput.value)) {
            showError('error-slug', 'La URL solo puede contener letras minúsculas, números y guiones');
            hayErrores = true;
        }

        // Validar WhatsApp
        if (!whatsappInput.value.trim()) {
            showError('error-whatsapp', 'Debes ingresar tu número de WhatsApp');
            hayErrores = true;
        } else if (!/^[0-9]{8}$/.test(whatsappInput.value)) {
            showError('error-whatsapp', 'El número debe tener 8 dígitos válidos');
            hayErrores = true;
        }

        // Si todo está bien, enviar
        if (!hayErrores) {
            form.submit();
        } else {
            // Scroll al primer error
            const primerError = document.querySelector('.section-error[style*="display: block"]');
            if (primerError) {
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });

    // Restricción de entrada para WhatsApp (Solo números)
    whatsappInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>