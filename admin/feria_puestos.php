<?php
$titulo = "Gestionar Puestos";
require_once 'header.php';

// Validar ID de sector
$sector_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Validar Ciudad (Por defecto LPZ)
$ciudad = isset($_GET['ciudad']) ? $_GET['ciudad'] : 'LPZ';
$ciudades = [
    'LPZ' => 'La Paz', 'ALT' => 'El Alto', 'SCZ' => 'Santa Cruz', 
    'CBA' => 'Cochabamba', 'ORU' => 'Oruro', 'PTS' => 'Potosí', 
    'TJA' => 'Tarija', 'CHQ' => 'Chuquisaca', 'BEN' => 'Beni', 'PND' => 'Pando'
];

if ($sector_id <= 0) {
    echo "<script>location.href='feria.php';</script>";
    exit;
}

$db = getDB();

// 1. Obtener datos del sector
$stmtSec = $db->prepare("SELECT * FROM feria_sectores WHERE id = ?");
$stmtSec->execute([$sector_id]);
$sector = $stmtSec->fetch(PDO::FETCH_ASSOC);

if (!$sector) {
    echo "<div class='alert alert-danger'>Sector no encontrado</div>";
    require_once 'footer.php';
    exit;
}

// 2. Obtener BLOQUES del sector (Dinámico)
$stmtBloques = $db->prepare("SELECT * FROM feria_bloques WHERE sector_id = ? ORDER BY orden ASC");
$stmtBloques->execute([$sector_id]);
$bloques = $stmtBloques->fetchAll(PDO::FETCH_ASSOC);

// Si no hay bloques, crear default (Migración al vuelo)
if (empty($bloques)) {
    // Insertar bloques por defecto si no existen
    $default_blocks = ['Tiwanaku', 'Illimani', 'Sajama', 'Uyuni', 'Madidi', 'Titicaca'];
    $stmtInsert = $db->prepare("INSERT INTO feria_bloques (sector_id, nombre, orden, capacidad) VALUES (?, ?, ?, 12)");
    foreach ($default_blocks as $idx => $name) {
        $stmtInsert->execute([$sector_id, $name, $idx + 1]);
    }
    // Recargar
    $stmtBloques->execute([$sector_id]);
    $bloques = $stmtBloques->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Obtener Puestos Ocupados
// REFACTORIZACIÓN: Usar JOIN con tabla tiendas para obtener datos reales
$sql = "SELECT p.*, 
               t.nombre as tienda_nombre, 
               t.logo as tienda_logo,
               t.slug as tienda_url
        FROM feria_posiciones p
        LEFT JOIN tiendas t ON p.usuario_id = t.usuario_id
        WHERE p.sector_id = ? AND p.ciudad = ?";
$stmtPos = $db->prepare($sql);
$stmtPos->execute([$sector_id, $ciudad]);
$ocupados_raw = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

// Mapear: [bloque_id][slot_numero] => data
$mapa_puestos = [];
foreach ($ocupados_raw as $pos) {
    // Ya no necesitamos lógica de reemplazo manual porque el SQL ya trae los datos correctos
    // en tienda_nombre y tienda_logo gracias al alias del JOIN

    
    // Lógica de compatibilidad V1 -> V2
    $bid = intval($pos['bloque_id']);
    $snum = intval($pos['slot_numero']);
    
    // Si falta bloque_id, intentar deducir por index (Legacy)
    if ($bid === 0 && isset($pos['posicion_index'])) {
        $idx = intval($pos['posicion_index']);
        $order = floor($idx / 12) + 1;
        $snum = ($idx % 12) + 1;
        // Buscar ID de bloque por orden
        foreach ($bloques as $b) {
            if ($b['orden'] == $order) {
                $bid = $b['id'];
                break;
            }
        }
    }
    
    if ($bid > 0 && $snum > 0) {
        $mapa_puestos[$bid][$snum] = $pos;
    }
}
?>

<!-- Estilos Admin Específicos -->
<style>
    /* Admin Container */
    .admin-feria-container {
        background: #f4f6f8;
        padding: 20px;
        border-radius: 12px;
    }
    
    /* Bloque Admin */
    .admin-block {
        background: white;
        border: 1px solid #dfe3e8;
        border-radius: 12px;
        margin-bottom: 30px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .admin-block-header {
        background: #fff;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .admin-block-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* Grid de Slots */
    .admin-slots-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* 4 por fila (4x3 = 12) */
        gap: 8px; /* Gap reducido para mayor compacidad */
        padding: 12px;
        background: #fafafa;
    }
    
    /* Slot Individual */
    .admin-slot {
        aspect-ratio: 1; /* Cuadrado perfecto */
        background: white;
        border: 2px dashed #e0e0e0;
        border-radius: 8px; /* Bordes menos redondeados para look más pro */
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        overflow: hidden;
    }
    
    .admin-slot:hover {
        border-color: #0d6efd;
        background: #f8fbff;
        transform: translateY(-2px); /* Efecto sutil de elevación */
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    
    /* Slot Ocupado */
    .admin-slot.occupied {
        border: 2px solid #28a745; /* Verde éxito */
        background: #fff;
        cursor: grab;
        padding: 0; /* Sin padding para que la imagen toque los bordes */
    }
    
    .admin-slot.occupied:active {
        cursor: grabbing;
    }
    
    .slot-number {
        position: absolute;
        top: 6px;
        left: 8px;
        font-size: 0.8rem;
        color: #fff;
        font-weight: bold;
        z-index: 10;
        text-shadow: 0 1px 2px rgba(0,0,0,0.8); /* Sombra para que se lea sobre imágenes */
        background: rgba(0,0,0,0.3);
        padding: 1px 6px;
        border-radius: 4px;
    }
    
    .slot-content {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .store-mini-logo {
        width: 100%;
        height: 100%;
        object-fit: cover; /* Llenar todo el espacio */
        display: block;
    }
    
    /* Nombre superpuesto al estilo Spotify/Netflix */
    .store-mini-name {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(0,0,0,0.7);
        color: white;
        font-size: 0.75rem;
        padding: 4px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Icono Más (+) para agregar */
    .add-icon {
        font-size: 2rem;
        color: #ccc;
        transition: color 0.2s;
    }
    
    .admin-slot:hover .add-icon {
        color: #007bff;
    }
    
    /* Estilos para Drag & Drop Nativo */
    .admin-slot.dragging {
        opacity: 0.5;
        border: 2px dashed #007bff;
    }
    .admin-slot.drag-over {
        background: #e2e6ea;
        border: 2px solid #28a745;
        transform: scale(1.05);
    }
    
    /* Sortable Ghost (elemento siendo arrastrado) */
    .sortable-ghost {
        opacity: 0.4;
        background: #c8ebfb;
    }
    
    /* Controles de Slot (Hover) */
    .slot-controls {
        position: absolute;
        top: 2px;
        right: 2px;
        display: none;
        gap: 2px;
    }
    
    .admin-slot:hover .slot-controls {
        display: flex;
    }
    
    .btn-xs {
        padding: 1px 4px;
        font-size: 0.65rem;
        line-height: 1.2;
        border-radius: 3px;
    }

</style>

<!-- Librería SortableJS para Drag & Drop -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="feria.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fas fa-arrow-left"></i> Volver</a>
        <h1 class="h3"><i class="fas fa-th text-primary"></i> Gestión Visual: <?php echo htmlspecialchars($sector['titulo']); ?></h1>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt text-danger"></i></span>
            <select class="form-select border-start-0" onchange="location.href='?id=<?php echo $sector_id; ?>&ciudad='+this.value" style="font-weight:600;">
                <?php foreach ($ciudades as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $code === $ciudad ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button class="btn btn-success text-nowrap" onclick="crearBloque()">
            <i class="fas fa-plus"></i> Nuevo Bloque
        </button>
    </div>
</div>

<div class="alert alert-light border shadow-sm py-2 mb-4">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-info-circle text-info"></i> 
        <span>Arrastra las tiendas para moverlas. Usa los botones <i class="fas fa-pen text-secondary"></i> para editar.</span>
        <span class="ms-auto badge bg-warning text-dark">Ciudad: <?php echo $ciudades[$ciudad]; ?></span>
    </div>
</div>

<div class="admin-feria-container">
    <div class="row g-4">
    <?php foreach ($bloques as $bloque): 
        $bid = $bloque['id'];
        $capacidad = max(intval($bloque['capacidad']), 12); // Mínimo 12
    ?>
        <div class="col-12 col-xl-6"> <!-- 2 Bloques por fila en pantallas grandes -->
            <div class="admin-block" id="bloque-<?php echo $bid; ?>" data-id="<?php echo $bid; ?>">
                <!-- Header del Bloque -->
                <div class="admin-block-header">
                    <div class="admin-block-title">
                        <i class="fas fa-cube text-muted"></i>
                        <span id="titulo-bloque-<?php echo $bid; ?>"><?php echo htmlspecialchars($bloque['nombre']); ?></span>
                        <button class="btn btn-link btn-sm text-secondary p-0 ms-2" onclick="editarBloque(<?php echo $bid; ?>, '<?php echo htmlspecialchars(addslashes($bloque['nombre'])); ?>')">
                            <i class="fas fa-pen fa-xs"></i>
                        </button>
                    </div>
                    <div>
                        <button class="btn btn-outline-danger btn-sm" onclick="eliminarBloque(<?php echo $bid; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Grid de Slots (Sortable) -->
                <div class="admin-slots-grid sortable-list" data-block-id="<?php echo $bid; ?>">
                    <?php for ($i = 1; $i <= $capacidad; $i++): 
                        $posData = isset($mapa_puestos[$bid][$i]) ? $mapa_puestos[$bid][$i] : null;
                        $isOccupied = !empty($posData);
                    ?>
                        <div class="admin-slot <?php echo $isOccupied ? 'occupied' : 'empty-clickable'; ?>" 
                             data-slot-num="<?php echo $i; ?>"
                             data-pos-id="<?php echo $isOccupied ? $posData['id'] : ''; ?>"
                             <?php if (!$isOccupied): ?>onclick="asignarTienda(<?php echo $bid; ?>, <?php echo $i; ?>)"<?php endif; ?>>
                            
                            <span class="slot-number"><?php echo $i; ?></span>
                            
                            <?php if ($isOccupied): ?>
                                <div class="slot-controls">
                                    <button class="btn btn-danger btn-xs" onclick="liberarPuesto(event, <?php echo $posData['id']; ?>)" title="Liberar puesto">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <div class="slot-content">
                                    <?php 
                                    $logo = !empty($posData['tienda_logo']) ? $posData['tienda_logo'] : '';
                                    
                                    // 1. Limpieza básica de parámetros URL
                                    if (strpos($logo, '?') !== false) $logo = explode('?', $logo)[0];
                                    
                                    // 2. Normalización Inteligente de Rutas
                                    if ($logo) {
                                        // Si solo es el nombre del archivo (ej: "logo_123.png"), agregar ruta completa
                                        if (strpos($logo, '/') === false) {
                                            $logo = '/uploads/logos/' . $logo;
                                        }
                                        // Si es ruta relativa sin slash inicial (ej: "uploads/logos/..."), agregar slash
                                        elseif (strpos($logo, 'uploads/') === 0) {
                                            $logo = '/' . $logo;
                                        }
                                    }
    
                                    // 3. Validación Física (Server-Side)
                                    $showImage = false;
                                    if ($logo && $logo !== '/assets/img/default-store.png') {
                                        $localPath = $_SERVER['DOCUMENT_ROOT'] . $logo;
                                        // Compatibilidad Windows/Linux
                                        $localPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localPath);
                                        
                                        if (file_exists($localPath)) {
                                            $showImage = true;
                                        }
                                    }
                                    ?>
    
                                    <?php if ($showImage): ?>
                                    <img src="<?php echo htmlspecialchars($logo); ?>?v=<?php echo time(); ?>" 
                                         class="store-mini-logo" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <i class="fas fa-store" style="display:none; font-size: 40px; color: #e5e5e5;"></i>
                                <?php else: ?>
                                    <!-- Si no existe la imagen física, mostrar icono FontAwesome como en feria.php -->
                                    <i class="fas fa-store" style="font-size: 40px; color: #e5e5e5;"></i>
                                <?php endif; ?>
    
                                    <div class="store-mini-name"><?php echo htmlspecialchars($posData['tienda_nombre']); ?></div>
                                </div>
                            <?php else: ?>
                                 <div class="text-muted opacity-25" style="pointer-events: none;">
                                     <i class="fas fa-plus add-icon"></i>
                                 </div>
                             <?php endif; ?>
                             
                         </div>
                     <?php endfor; ?>
                 </div>
             </div>
         </div>
     <?php endforeach; ?>
     </div>
 </div>

 <!-- Scripts de Interacción -->
<script>
    // --- DEFINIR FUNCIONES GLOBALES PRIMERO ---
    // (Para asegurar que estén disponibles antes de que cualquier HTML las llame)
    
    const API_URL = '/admin/ajax/feria_posiciones_actions.php';
    const CURRENT_SECTOR = <?php echo $sector_id; ?>;
    const CURRENT_CIUDAD = '<?php echo $ciudad; ?>';

    window.crearBloque = function() {
        const nombre = prompt("Nombre del nuevo bloque:");
        if (!nombre) return;
        fetch(API_URL, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'create_block', sector_id: CURRENT_SECTOR, nombre: nombre })
        }).then(() => location.reload());
    };

    window.editarBloque = function(id, actual) {
        const nuevo = prompt("Editar nombre del bloque:", actual);
        if (!nuevo || nuevo === actual) return;
        fetch(API_URL, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_block', id: id, nombre: nuevo })
        }).then(() => location.reload());
    };

    window.eliminarBloque = function(id) {
        if (!confirm("¿Eliminar este bloque? Se liberarán todas las tiendas asignadas a él.")) return;
        fetch(API_URL, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_block', id: id })
        }).then(() => location.reload());
    };

    window.liberarPuesto = function(e, id) {
        e.stopPropagation();
        if (!confirm("¿Quitar esta tienda del puesto?")) return;
        fetch(API_URL, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'free', id: id })
        }).then(() => location.reload());
    };

    // Flag para evitar doble ejecución (Debounce manual)
    let isAssigning = false;

    window.asignarTienda = function(bloqueId, slotNum) {
         if (isAssigning) return; // Si ya se está ejecutando, salir
         isAssigning = true;
 
         // Pequeño timeout para resetear el flag si el usuario cancela el prompt rápido
         setTimeout(() => { isAssigning = false; }, 5000); 
 
         const input = prompt("ASIGNAR TIENDA:\n\nIngresa el LINK o el SLUG de la tienda.\nEjemplo: 'tingo'");
         
         // Si el usuario cancela (null) o deja vacío
         if (input === null || input.trim() === '') {
             isAssigning = false; // Resetear flag inmediatamente
             return;
         }
         
         let slug = input.trim();
         if (slug.includes('/tienda/')) slug = slug.split('/tienda/')[1].split('/')[0].split('?')[0];
         
         // Buscar nombre del bloque visualmente
         const bloqueElement = document.getElementById('titulo-bloque-' + bloqueId);
         const bloqueTitulo = bloqueElement ? bloqueElement.innerText : 'Bloque ' + bloqueId;
 
         // Confirmar visualmente (UNA SOLA VEZ)
         if (!confirm(`¿Asignar '${slug}' al Bloque '${bloqueTitulo}' (Puesto ${slotNum})?`)) {
             isAssigning = false; // Resetear flag si cancela la confirmación
             return;
         }
         
         document.body.style.cursor = 'wait';
         
         fetch(API_URL, {
             method: 'POST', headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({ action: 'assign_store', slug: slug, target_block: bloqueId, target_slot: slotNum, ciudad: CURRENT_CIUDAD })
         }).then(r => r.json()).then(data => {
             if (data.success) location.reload();
             else {
                 alert('Error: ' + data.message);
                 isAssigning = false; // Resetear flag si hay error
             }
         }).catch(e => {
             alert('Error de conexión');
             isAssigning = false; // Resetear flag si hay error de red
         }).finally(() => {
             document.body.style.cursor = 'default';
             // isAssigning = false; // YA NO NECESARIO AQUÍ si se recarga la página, pero por seguridad
         });
     };

    window.confirmarMovimiento = function(posId, bloqueId, slotNum) {
        document.body.style.cursor = 'wait';
        fetch(API_URL, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'move_v2', id: posId, target_block: bloqueId, target_slot: slotNum, ciudad: CURRENT_CIUDAD })
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else { alert('Error: ' + data.message); location.reload(); }
        }).catch(e => { console.error(e); location.reload(); }).finally(() => document.body.style.cursor = 'default');
    };

    // --- LOGICA DOM LOADED ---
    document.addEventListener('DOMContentLoaded', function() {
        // Listener Global para Clics en Slots Vacíos
        const container = document.querySelector('.admin-feria-container');
        if (container) {
            container.addEventListener('click', function(e) {
                // Buscar si el clic fue en un slot (o dentro de él)
                const slot = e.target.closest('.admin-slot');
                
                // Si no es un slot, o es un slot ocupado, ignorar.
                if (!slot || slot.classList.contains('occupied')) return;
                
                // Prevenir conflictos
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation(); // MATAR CUALQUIER OTRO LISTENER FANTASMA
                
                // Obtener datos
                const grid = slot.closest('.admin-slots-grid');
                if (!grid) return;
                
                const bid = grid.dataset.blockId;
                const snum = slot.dataset.slotNum;
                
                // Solo llamar si no está en proceso
                if (typeof isAssigning !== 'undefined' && !isAssigning) {
                    window.asignarTienda(bid, snum);
                } else if (typeof isAssigning === 'undefined') {
                     // Fallback si la variable no está en scope por alguna razón
                     window.asignarTienda(bid, snum);
                }
            });
        }

        // Drag & Drop
        const draggables = document.querySelectorAll('.admin-slot.occupied');
        const slots = document.querySelectorAll('.admin-slot'); 
        
        draggables.forEach(d => {
            d.setAttribute('draggable', 'true');
            d.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', d.dataset.posId);
                e.dataTransfer.effectAllowed = 'move';
                d.classList.add('dragging');
            });
            d.addEventListener('dragend', () => {
                d.classList.remove('dragging');
                document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            });
        });
        
        slots.forEach(slot => {
            slot.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (!slot.classList.contains('occupied')) {
                    slot.classList.add('drag-over');
                    e.dataTransfer.dropEffect = 'move';
                } else {
                    e.dataTransfer.dropEffect = 'none';
                }
            });
            slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
            slot.addEventListener('drop', (e) => {
                e.preventDefault(); slot.classList.remove('drag-over');
                if (slot.classList.contains('occupied')) return;
                
                const posId = e.dataTransfer.getData('text/plain');
                if (posId) window.confirmarMovimiento(posId, slot.closest('.admin-slots-grid').dataset.blockId, slot.dataset.slotNum);
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>
