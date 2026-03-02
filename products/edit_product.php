<?php
// VERSIÓN ACTUALIZADA - 2025-12-31 22:15 - CON REDIRECT
$titulo = "EDITAR ANUNCIO";
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!estaLogueado()) {
    redireccionar('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redireccionar('/mi/publicaciones.php');
}

$producto_id = (int)$_GET['id'];

// Verificar que el producto existe y pertenece al usuario
$db = getDB();
$stmt = $db->prepare("SELECT * FROM productos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$producto_id, $_SESSION['usuario_id']]);
$producto = $stmt->fetch();

if (!$producto) {
    redireccionar('/mi/publicaciones.php?msg=forbidden');
}

$mensaje = '';
$error = '';

// PROCESAR FORMULARIO ANTES DE CARGAR HEADER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/ubicaciones_bolivia.php';
    $titulo_producto = limpiarEntrada($_POST['titulo']);
    $descripcion = limpiarEntrada($_POST['descripcion']);
    $precio = (float)$_POST['precio'];
    $categoria_id = (int)$_POST['categoria_id'];
    $subcategoria_id = (int)$_POST['subcategoria_id'];
    $departamento_codigo = $_POST['departamento'] ?? '';
    $municipio_codigo = $_POST['municipio'] ?? '';
    $estado = limpiarEntrada($_POST['estado']);

    // Opciones de envío
    $envio_gratis = isset($_POST['envio_gratis']) ? 1 : 0;
    $envio_rapido = isset($_POST['envio_rapido']) ? 1 : 0;
    $categoria_tienda = !empty($_POST['categoria_tienda']) ? limpiarEntrada($_POST['categoria_tienda']) : null;

    if (empty($titulo_producto) || empty($descripcion) || $precio <= 0 ||
        empty($categoria_id) || empty($subcategoria_id) || empty($departamento_codigo) || empty($municipio_codigo)) {
        $error = 'Completa todos los campos';
    } elseif (strlen($titulo_producto) < 10) {
        $error = 'Título muy corto (mínimo 10 caracteres)';
    } elseif (strlen($descripcion) < 20) {
        $error = 'Descripción muy corta (mínimo 20 caracteres)';
    } else {
        try {
            // Obtener nombres de departamento y municipio
            $departamentos = obtenerDepartamentosBolivia();
            $todosMunicipios = obtenerTodosMunicipiosConCodigos();

            $departamento_nombre = $departamentos[$departamento_codigo] ?? '';
            $municipioInfo = $todosMunicipios[$municipio_codigo] ?? null;
            $municipio_nombre = $municipioInfo ? $municipioInfo['nombre'] : '';

            if (empty($departamento_nombre) || empty($municipio_nombre)) {
                $error = 'Ubicación no válida';
            } else {
                // Verificar si la columna categoria_tienda existe
                $columna_existe = false;
                try {
                    $check = $db->query("SHOW COLUMNS FROM productos LIKE 'categoria_tienda'");
                    $columna_existe = $check->rowCount() > 0;
                } catch (Exception $e) {
                    $columna_existe = false;
                }

                // Preparar UPDATE según si la columna existe o no
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
                            municipio_nombre = ?,
                            envio_gratis = ?
                        WHERE id = ? AND usuario_id = ?
                    ");

                    $stmt->execute([
                        $categoria_id,
                        $subcategoria_id,
                        $categoria_tienda,
                        $titulo_producto,
                        $descripcion,
                        $precio,
                        $estado,
                        $departamento_codigo,
                        $municipio_codigo,
                        $departamento_nombre,
                        $municipio_nombre,
                        $envio_gratis,
                        $producto_id,
                        $_SESSION['usuario_id']
                    ]);
                } else {
                    // Versión SIN categoria_tienda (para usuarios que no han actualizado)
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
                            municipio_nombre = ?,
                            envio_gratis = ?
                        WHERE id = ? AND usuario_id = ?
                    ");

                    $stmt->execute([
                        $categoria_id,
                        $subcategoria_id,
                        $titulo_producto,
                        $descripcion,
                        $precio,
                        $estado,
                        $departamento_codigo,
                        $municipio_codigo,
                        $departamento_nombre,
                        $municipio_nombre,
                        $envio_gratis,
                        $producto_id,
                        $_SESSION['usuario_id']
                    ]);
                }

                // Manejar nuevas imágenes si se subieron
                if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
                    for ($i = 0; $i < count($_FILES['imagenes']['name']); $i++) {
                        if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                            $archivo = [
                                'name' => $_FILES['imagenes']['name'][$i],
                                'type' => $_FILES['imagenes']['type'][$i],
                                'tmp_name' => $_FILES['imagenes']['tmp_name'][$i],
                                'error' => $_FILES['imagenes']['error'][$i],
                                'size' => $_FILES['imagenes']['size'][$i]
                            ];
                            
                            $resultado = subirImagen($archivo, 'productos');
                            
                            if (isset($resultado['success'])) {
                                // Obtener el orden máximo actual
                                $stmtOrden = $db->prepare("SELECT COALESCE(MAX(orden), -1) + 1 as siguiente_orden FROM producto_imagenes WHERE producto_id = ?");
                                $stmtOrden->execute([$producto_id]);
                                $ordenRow = $stmtOrden->fetch();
                                $siguiente_orden = $ordenRow['siguiente_orden'];
                                
                                $stmt = $db->prepare("
                                    INSERT INTO producto_imagenes (producto_id, nombre_archivo, es_principal, orden) 
                                    VALUES (?, ?, 0, ?)
                                ");
                                $stmt->execute([
                                    $producto_id,
                                    $resultado['archivo'],
                                    $siguiente_orden
                                ]);
                            }
                        }
                    }
                }

                // Guardar datos para página de confirmación
                $_SESSION['producto_editado'] = [
                    'id' => $producto_id,
                    'titulo' => $titulo_producto,
                    'tiempo' => time()
                ];

                // Redirigir a página de éxito
                header('Location: edicion_exitosa.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// CARGAR HEADER DESPUÉS DE PROCESAR POST
require_once '../includes/header.php';
require_once '../config/ubicaciones_bolivia.php';

// Cargar imágenes del producto
$stmt = $db->prepare("SELECT * FROM producto_imagenes WHERE producto_id = ? ORDER BY es_principal DESC, orden ASC");
$stmt->execute([$producto_id]);
$imagenes = $stmt->fetchAll();

$categorias = obtenerCategorias();
$departamentos = obtenerDepartamentosBolivia();
$usuario = obtenerUsuarioActual();

// Obtener tienda del usuario y sus secciones de menú
$tienda_usuario = null;
$secciones_menu = [];
try {
    $stmt = $db->prepare("SELECT id, nombre, menu_items FROM tiendas WHERE usuario_id = ? AND estado = 'activo' LIMIT 1");
    $stmt->execute([$_SESSION['usuario_id']]);
    $tienda_usuario = $stmt->fetch();

    if ($tienda_usuario && !empty($tienda_usuario['menu_items'])) {
        $menu_items = json_decode($tienda_usuario['menu_items'], true);
        if (is_array($menu_items)) {
            foreach ($menu_items as $item) {
                if (!empty($item['label'])) {
                    $secciones_menu[] = $item['label'];
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error obteniendo tienda: " . $e->getMessage());
}
?>

<style>
body { background: #f5f5f5; min-height: 100vh; }
.publish-wrap { max-width: 750px; margin: 0 auto; padding: 40px 20px; }
.publish-title { text-align: center; font-size: 28px; font-weight: 700; margin-bottom: 30px; color: #2c3e50; }
.publish-card { background: white; border-radius: 12px; padding: 32px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); border: 1px solid #e5e7eb; transition: all 0.3s ease; }
.publish-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12); }
.publish-card h2 { font-size: 18px; font-weight: 700; margin: 0 0 20px 0; color: #2c3e50; border-bottom: 2px solid #f3f4f6; padding-bottom: 12px; }
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; margin-bottom: 12px; }
.photo-add { aspect-ratio: 1; border: 2px dashed #ccc; border-radius: 8px; background: #fafafa; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; color: #666; font-size: 13px; transition: all 0.2s; }
.photo-add:hover { border-color: #ff6b1a; background: #fff8f5; color: #ff6b1a; }
.photo-add svg { width: 28px; height: 28px; }
.photo-item { aspect-ratio: 1; border-radius: 8px; overflow: hidden; position: relative; border: 2px solid #e5e7eb; }
.photo-item img { width: 100%; height: 100%; object-fit: cover; }
.photo-remove { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; background: rgba(0,0,0,0.7); border: none; border-radius: 50%; color: white; cursor: pointer; font-size: 16px; }
.photo-remove:hover { background: #dc2626; }
.photo-badge { position: absolute; bottom: 6px; left: 6px; background: #22c55e; color: white; padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.input-text, .input-select { width: 100%; padding: 14px 16px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 15px; font-family: inherit; margin-bottom: 0; height: 48px; box-sizing: border-box; background: white; transition: all 0.3s ease; }
.input-text:focus, .input-select:focus { outline: none; border-color: #ff6b1a; box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1); background: #fffbf8; }
.input-text:hover, .input-select:hover { border-color: #9ca3af; }
textarea.input-text { resize: vertical; min-height: 110px; height: auto; margin-bottom: 0; }
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start; }
.input-prefix-wrap { position: relative; margin-bottom: 0; }
.input-prefix { position: absolute; left: 16px; top: 12px; font-weight: 700; color: #2c3e50; font-size: 15px; pointer-events: none; z-index: 1; line-height: 24px; }
.input-prefix-wrap input { padding-left: 50px; margin-bottom: 0; }
.price-literal { font-size: 13px; color: #64748b; margin-top: 8px; margin-bottom: 0; min-height: 18px; font-style: italic; font-weight: 500; }
.form-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 24px; }
.form-buttons .btn-publish { padding: 0 !important; background: #ff6b1a !important; color: white !important; border: none !important; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; height: 50px; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
.form-buttons .btn-publish:hover { background: #e85e00 !important; }
.form-buttons .btn-publish:active { transform: scale(0.98); }
.form-buttons .btn-cancel { padding: 0; background: white; color: #666; border: 2px solid #d0d0d0; border-radius: 8px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; display: flex; align-items: center; justify-content: center; height: 50px; box-sizing: border-box; }
.form-buttons .btn-cancel:hover { border-color: #999; color: #333; }
.alert-error { background: #fef2f2; color: #dc2626; padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca; }
.alert-success { background: #f0fdf4; color: #16a34a; padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0; }
.photo-existing { position: relative; }
.photo-delete-btn { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; background: rgba(220,38,38,0.9); border: none; border-radius: 50%; color: white; cursor: pointer; font-size: 14px; z-index: 10; }
.photo-delete-btn:hover { background: #dc2626; }
@media (max-width: 768px) {
    body { padding: 20px 0; }
    .publish-wrap { padding: 0 16px; }
    .publish-card { padding: 24px 20px; }
    .publish-title { font-size: 24px; margin-bottom: 24px; }
    .photo-grid { grid-template-columns: repeat(3, 1fr); }
    .input-row { grid-template-columns: 1fr; }
    .form-buttons { grid-template-columns: 1fr; }
}
</style>

<div class="publish-wrap">
    <h1 class="publish-title"><?php echo $titulo; ?></h1>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert-success"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="publishForm">
        
        <div class="publish-card">
            <h2>Fotos</h2>
            <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*" style="display:none">
            <div class="photo-grid" id="photoGrid">
                <?php foreach ($imagenes as $img): ?>
                <div class="photo-item photo-existing" data-img-id="<?php echo $img['id']; ?>">
                    <img src="/uploads/<?php echo htmlspecialchars($img['nombre_archivo']); ?>" alt="Foto">
                    <?php if ($img['es_principal']): ?>
                    <div class="photo-badge">PRINCIPAL</div>
                    <?php endif; ?>
                    <button type="button" class="photo-delete-btn" onclick="deleteExistingPhoto(<?php echo $img['id']; ?>, <?php echo $producto_id; ?>)">×</button>
                </div>
                <?php endforeach; ?>
                
                <div class="photo-add" id="addPhotoBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Agregar</span>
                </div>
            </div>
            <p style="font-size: 13px; color: #999; margin: 0;">Máximo 5 fotos • La primera será la principal</p>
        </div>

        <div class="publish-card">
            <h2>Descripción</h2>
            <input type="text" name="titulo" required placeholder="Título del producto" class="input-text" style="margin-bottom: 12px" value="<?php echo htmlspecialchars($producto['titulo']); ?>">
            <textarea name="descripcion" required placeholder="Describe tu producto" class="input-text"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
        </div>

        <div class="publish-card">
            <h2>Categoría</h2>
            <div class="input-row">
                <select name="categoria_id" id="categoriaSelect" required class="input-select">
                    <option value="">Seleccionar</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($producto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="subcategoria_id" id="subcategoriaSelect" required class="input-select">
                    <option value="">Primero elige categoría</option>
                </select>
            </div>
        </div>

        <div class="publish-card">
            <h2>Precio y Estado</h2>
            <div class="input-row">
                <div class="input-prefix-wrap">
                    <span class="input-prefix">Bs</span>
                    <input type="text" name="precio" id="precioInput" required placeholder="1000" inputmode="numeric" pattern="[0-9]+" class="input-text" value="<?php echo round($producto['precio']); ?>">
                </div>
                <select name="estado" required class="input-select">
                    <option value="">Estado</option>
                    <option value="nuevo" <?php echo ($producto['estado'] == 'nuevo') ? 'selected' : ''; ?>>Nuevo</option>
                    <option value="como_nuevo" <?php echo ($producto['estado'] == 'como_nuevo') ? 'selected' : ''; ?>>Como nuevo</option>
                    <option value="buen_estado" <?php echo ($producto['estado'] == 'buen_estado') ? 'selected' : ''; ?>>Buen estado</option>
                    <option value="aceptable" <?php echo ($producto['estado'] == 'aceptable') ? 'selected' : ''; ?>>Estado aceptable</option>
                </select>
            </div>
            <p class="price-literal" id="precioLiteral"></p>
        </div>

        <div class="publish-card">
            <h2>Opciones de Entrega</h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <label class="checkbox-wrapper">
                    <input type="checkbox" name="envio_gratis" value="1" <?php echo (!empty($producto['envio_gratis'])) ? 'checked' : ''; ?>>
                    <span class="checkbox-label">
                        <i class="fas fa-truck" style="color: #22c55e;"></i>
                        Ofrezco Envío Gratis
                    </span>
                </label>
            </div>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">Marca esta opción solo si puedes cumplirla.</p>
        </div>

        <?php if ($tienda_usuario && count($secciones_menu) > 0): ?>
        <!-- SECCIÓN: SECCIÓN DE TU TIENDA -->
        <div class="publish-card">
            <h2>Sección de tu Tienda</h2>
            <select name="categoria_tienda" class="input-select">
                <option value="">Mostrar en "Inicio" solamente</option>
                <?php foreach ($secciones_menu as $seccion): ?>
                <option value="<?php echo strtolower(htmlspecialchars($seccion)); ?>"
                    <?php echo (strtolower($producto['categoria_tienda'] ?? '') === strtolower($seccion)) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($seccion); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">
                <i class="fas fa-info-circle" style="color: #ff6b1a;"></i>
                Elige en qué sección de tu tienda <strong><?php echo htmlspecialchars($tienda_usuario['nombre']); ?></strong> quieres que aparezca este producto.
            </p>
        </div>
        <?php endif; ?>

        <div class="publish-card">
            <h2>Ubicación</h2>
            <div class="input-row">
                <select name="departamento" id="departamentoSelect" required class="input-select">
                    <option value="">Departamento</option>
                    <?php foreach ($departamentos as $codigo => $nombre): ?>
                    <option value="<?php echo $codigo; ?>" <?php echo ($producto['departamento_codigo'] == $codigo) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($nombre); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="municipio" id="municipioSelect" required class="input-select">
                    <option value="">Cargando...</option>
                </select>
            </div>
        </div>

        <div class="form-buttons">
            <a href="/mi/publicaciones.php" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-publish">GUARDAR CAMBIOS</button>
        </div>
    </form>
</div>

<script>
const productoSubcategoriaId = <?php echo $producto['subcategoria_id']; ?>;
const productoMunicipioId = '<?php echo $producto['municipio_codigo'] ?? ''; ?>';

// Función para eliminar foto existente
function deleteExistingPhoto(imgId, prodId) {
    if (!confirm('¿Eliminar esta foto?')) return;
    
    fetch('/api/delete_product_image.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: imgId, producto_id: prodId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-img-id="${imgId}"]`).remove();
        } else {
            alert('Error al eliminar foto');
        }
    })
    .catch(() => alert('Error al eliminar foto'));
}

(function() {
    const MAX = 5;
    let files = [];
    const input = document.getElementById('imagenes');
    const grid = document.getElementById('photoGrid');
    const addBtn = document.getElementById('addPhotoBtn');
    
    addBtn.addEventListener('click', () => input.click());
    
    input.addEventListener('change', (e) => {
        const newFiles = Array.from(e.target.files);
        const existingPhotos = document.querySelectorAll('.photo-existing').length;
        const newPhotoItems = document.querySelectorAll('.photo-item:not(.photo-existing)').length;
        const totalCurrent = existingPhotos + newPhotoItems;
        const available = MAX - totalCurrent;
        
        if (newFiles.length > available) {
            alert(`Solo puedes agregar ${available} foto(s) más. Máximo ${MAX} fotos en total.`);
            return;
        }
        
        files = files.concat(newFiles);
        render();
    });
    
    function render() {
        // Limpiar fotos nuevas (no existentes)
        document.querySelectorAll('.photo-item:not(.photo-existing)').forEach(el => el.remove());
        
        files.forEach((f, i) => {
            const url = URL.createObjectURL(f);
            const div = document.createElement('div');
            div.className = 'photo-item';
            div.innerHTML = `
                <img src="${url}" alt="">
                <button type="button" class="photo-remove" onclick="removeNewPhoto(${i})">×</button>
            `;
            grid.insertBefore(div, addBtn);
        });
        
        const existingPhotos = document.querySelectorAll('.photo-existing').length;
        const newPhotos = files.length;
        const total = existingPhotos + newPhotos;
        
        if (total >= MAX) {
            addBtn.style.display = 'none';
        } else {
            addBtn.style.display = 'flex';
        }
    }
    
    window.removeNewPhoto = function(i) {
        files.splice(i, 1);
        render();
    };
})();

// Precio en literal
(function() {
    const precioInput = document.getElementById('precioInput');
    const precioLiteral = document.getElementById('precioLiteral');
    
    if (precioInput && precioLiteral) {
        function numeroAPalabras(num) {
            const unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
            const decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
            const especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
            
            if (num === 0) return 'cero';
            if (num < 10) return unidades[num];
            if (num >= 10 && num < 20) return especiales[num - 10];
            if (num >= 20 && num < 100) {
                const d = Math.floor(num / 10);
                const u = num % 10;
                return decenas[d] + (u > 0 ? ' y ' + unidades[u] : '');
            }
            if (num >= 100 && num < 1000) {
                const c = Math.floor(num / 100);
                const resto = num % 100;
                const centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
                if (num === 100) return 'cien';
                return centenas[c] + (resto > 0 ? ' ' + numeroAPalabras(resto) : '');
            }
            if (num >= 1000 && num < 1000000) {
                const m = Math.floor(num / 1000);
                const resto = num % 1000;
                const mil = m === 1 ? 'mil' : numeroAPalabras(m) + ' mil';
                return mil + (resto > 0 ? ' ' + numeroAPalabras(resto) : '');
            }
            return num.toString();
        }
        
        // Bloquear caracteres no numéricos (solo permite dígitos)
        precioInput.addEventListener('input', function() {
            // Eliminar cualquier carácter que no sea dígito
            this.value = this.value.replace(/[^0-9]/g, '');

            const valor = parseInt(this.value);
            if (!isNaN(valor) && valor > 0) {
                let texto = numeroAPalabras(valor) + ' boliviano' + (valor !== 1 ? 's' : '');
                precioLiteral.textContent = texto;
            } else {
                precioLiteral.textContent = '';
            }
        });

        // Prevenir entrada de decimales con el teclado
        precioInput.addEventListener('keypress', function(e) {
            // Permitir solo números (códigos 48-57)
            if (e.charCode < 48 || e.charCode > 57) {
                e.preventDefault();
            }
        });
        
        // Disparar evento para mostrar precio actual
        if (precioInput.value) {
            precioInput.dispatchEvent(new Event('input'));
        }
    }
})();

// Cargar municipios cuando se selecciona departamento
const departamentoSelect = document.getElementById('departamentoSelect');
const municipioSelect = document.getElementById('municipioSelect');

if (departamentoSelect && municipioSelect) {
    departamentoSelect.addEventListener('change', function() {
        const departamento = this.value;
        
        if (!departamento) {
            municipioSelect.innerHTML = '<option value="">Primero elige departamento</option>';
            municipioSelect.disabled = true;
            return;
        }
        
        municipioSelect.disabled = false;
        municipioSelect.innerHTML = '<option value="">Cargando...</option>';
        
        fetch('/api/get_municipios.php?departamento=' + encodeURIComponent(departamento))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    municipioSelect.innerHTML = '<option value="">Selecciona municipio</option>';
                    
                    data.data.forEach(municipio => {
                        const option = document.createElement('option');
                        option.value = municipio.codigo;
                        option.textContent = municipio.nombre;
                        if (municipio.codigo === productoMunicipioId) {
                            option.selected = true;
                        }
                        municipioSelect.appendChild(option);
                    });
                } else {
                    municipioSelect.innerHTML = '<option value="">No hay municipios</option>';
                }
            })
            .catch(error => {
                console.error('Error al cargar municipios:', error);
                municipioSelect.innerHTML = '<option value="">Error al cargar</option>';
            });
    });
    
    // Cargar municipios del departamento actual
    if (departamentoSelect.value) {
        departamentoSelect.dispatchEvent(new Event('change'));
    }
}

// Cargar subcategorías
const categoriaSelect = document.getElementById('categoriaSelect');
const subcategoriaSelect = document.getElementById('subcategoriaSelect');

if (categoriaSelect && subcategoriaSelect) {
    categoriaSelect.addEventListener('change', function() {
        const catId = this.value;
        
        subcategoriaSelect.innerHTML = '<option value="">Cargando...</option>';
        subcategoriaSelect.disabled = true;
        
        if (!catId) {
            subcategoriaSelect.innerHTML = '<option value="">Primero elige categoría</option>';
            return;
        }
        
        fetch('/api/subcategorias.php?categoria_id=' + catId)
            .then(r => r.json())
            .then(data => {
                subcategoriaSelect.innerHTML = '<option value="">Seleccionar</option>';
                
                const subcats = data.data || data.subcategorias || [];
                
                if (data.success && subcats.length > 0) {
                    subcats.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.nombre;
                        if (s.id == productoSubcategoriaId) {
                            opt.selected = true;
                        }
                        subcategoriaSelect.appendChild(opt);
                    });
                    subcategoriaSelect.disabled = false;
                } else {
                    subcategoriaSelect.innerHTML = '<option value="">Sin subcategorías</option>';
                    subcategoriaSelect.disabled = false;
                }
            })
            .catch(err => {
                console.error('Error cargando subcategorías:', err);
                subcategoriaSelect.innerHTML = '<option value="">Error</option>';
            });
    });
    
    // Cargar subcategorías de la categoría actual
    if (categoriaSelect.value) {
        categoriaSelect.dispatchEvent(new Event('change'));
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
