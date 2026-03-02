<?php
// Version: 2024-12-26 - OPTIMIZADO PROFESIONAL
// CAMBIOS: Orden lógico (Categoría primero) + Títulos limpios + Color #2c3e50 + Botones profesionales
$titulo = "Publica tu anuncio";
require_once '../includes/functions.php';

if (!estaLogueado()) {
    redireccionar('../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

require_once '../includes/header.php';
require_once '../config/ubicaciones_bolivia.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Este bloque ahora se manejará con JavaScript y una llamada a la API.
    // La lógica de PHP para el procesamiento del formulario ha sido eliminada
    // para centralizar la creación de productos a través de la API.
}

$categorias = obtenerCategorias();
$departamentos = obtenerDepartamentosBolivia();
$usuario = obtenerUsuarioActual();

// --- LÓGICA INTELIGENTE DE UBICACIÓN ---
// Si el usuario ya tiene ubicación guardada, usarla por defecto
$user_dept = $usuario['departamento_codigo'] ?? '';
$user_mun = $usuario['municipio_codigo'] ?? '';
$user_dept_nombre = $usuario['departamento_nombre'] ?? '';
$user_mun_nombre = $usuario['municipio_nombre'] ?? '';

// Si tiene ubicación completa, ocultamos los selectores
$ubicacion_fija = (!empty($user_dept) && !empty($user_mun));

// Obtener tienda del usuario y sus secciones de menú
$tienda_usuario = null;
$secciones_menu = [];
try {
    $db = getDB();
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
/* Contenido de la página - Diseño profesional alineado con view_product.php */
.publish-wrap {
    max-width: 750px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 8px;
}
.publish-title { text-align: center; font-size: 28px; font-weight: 700; margin-bottom: 30px; color: #2c3e50; }

/* Tarjetas con estilo profesional idéntico a view_product.php */
.publish-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}
.publish-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Títulos con estilo profesional idéntico a view_product.php */
.publish-card h2 { font-size: 18px; font-weight: 700; margin: 0 0 20px 0; color: #2c3e50; border-bottom: 2px solid #f3f4f6; padding-bottom: 12px; }

/* Centrar cuadro de agregar fotos */
.photo-grid-center { display: flex !important; justify-content: center !important; margin-bottom: 12px !important; width: 100% !important; }
.photo-grid { display: flex !important; justify-content: center !important; gap: 12px !important; flex-wrap: wrap !important; max-width: 600px !important; margin: 0 auto !important; }

/* Mensajes de error mejorados */
.section-error {
    color: #dc2626;
    font-size: 14px;
    font-weight: 500;
    margin-top: 12px;
    padding: 12px 16px;
    background: linear-gradient(to right, #fef2f2, #fff);
    border-left: 4px solid #dc2626;
    border-radius: 6px;
    display: none;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
}

/* Botón agregar foto mejorado */
.photo-add {
    width: 110px !important;
    aspect-ratio: 1;
    border: 2px dashed #ff6b1a;
    border-radius: 10px;
    background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #ff6b1a;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.photo-add:hover {
    border-color: #ff6b1a;
    background: linear-gradient(135deg, #fff8f5 0%, #ffffff 100%);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(255,107,26,0.2);
}
.photo-add svg { width: 30px; height: 30px; }

/* Preview de fotos mejorado */
.photo-item {
    aspect-ratio: 1;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    border: 2px solid #2c3e50;
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
    transition: all 0.3s ease;
}
.photo-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.18);
}
.photo-item img { width: 100%; height: 100%; object-fit: cover; }
.photo-remove {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 28px;
    height: 28px;
    background: rgba(0,0,0,0.75);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}
.photo-remove:hover {
    background: #dc2626;
    transform: scale(1.1);
}
.photo-badge {
    position: absolute;
    bottom: 8px;
    left: 8px;
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

/* Inputs con estilo profesional */
.input-text, .input-select {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    margin-bottom: 0;
    height: 48px;
    box-sizing: border-box;
    background: white;
    transition: all 0.3s ease;
}
.input-text:focus, .input-select:focus {
    outline: none;
    border-color: #ff6b1a;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
    background: #fffbf8;
}
.input-text:hover, .input-select:hover {
    border-color: #9ca3af;
}
textarea.input-text { resize: vertical; min-height: 110px; height: auto; margin-bottom: 0; }

.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start; }
.input-prefix-wrap { position: relative; margin-bottom: 0; }
.input-prefix { position: absolute; left: 16px; top: 12px; font-weight: 700; color: #2c3e50; font-size: 15px; pointer-events: none; z-index: 1; line-height: 24px; }
.input-prefix-wrap input { padding-left: 50px; margin-bottom: 0; }
.price-literal { font-size: 13px; color: #64748b; margin-top: 8px; margin-bottom: 0; min-height: 18px; font-style: italic; font-weight: 500; }

/* ===== BOTONES - IGUAL QUE REGISTER ===== */
.form-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
}

/* Cancelar - Borde gris como register */
.btn-cancel {
    padding: 0;
    background: white;
    color: #666;
    border: 2px solid #d0d0d0;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 50px;
    box-sizing: border-box;
    cursor: pointer;
}

.btn-cancel:hover {
    border-color: #999;
    color: #333;
}

.btn-cancel:active {
    transform: scale(0.98);
}

/* Publicar - Destacado sin borde */
.btn-publish {
    background: #ff6b1a !important;
    color: white !important;
    border: none !important;
    border-radius: 8px;
    padding: 0 !important;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    height: 50px;
    box-sizing: border-box;
}

.btn-publish:hover {
    background: #e85e00 !important;
}

.btn-publish:active {
    transform: scale(0.98);
}

.alert-error {
    background: linear-gradient(to right, #fef2f2, #fff);
    color: #dc2626;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 2px solid #fecaca;
    border-left: 4px solid #dc2626;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
}

@media (max-width: 768px) {
    .publish-wrap { padding: 0 16px; }
    .publish-card { padding: 24px 20px; }
    .publish-title { font-size: 24px; margin-bottom: 24px; }
    .photo-grid { grid-template-columns: repeat(3, 1fr); }
    .input-row { grid-template-columns: 1fr; }

    /* Botones stack en móvil */
    .form-buttons {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .btn-cancel {
        width: 100%;
        height: 48px;
        order: 2;
    }

    .btn-publish {
        width: 100%;
        height: 54px;
        order: 1;
        font-size: 16px;
    }
}
</style>

<div class="publish-wrap">
    <h1 class="publish-title">Publica tu anuncio</h1>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="publishForm">

        <!-- SECCIÓN 1: CATEGORÍA -->
        <div class="publish-card">
            <h2>Categoría</h2>
            <div class="input-row">
                <select name="categoria_id" id="categoriaSelect" class="input-select">
                    <option value="">Seleccionar</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="subcategoria_id" id="subcategoriaSelect" class="input-select" disabled>
                    <option value="">Primero elige categoría</option>
                </select>
            </div>
            <div class="section-error" id="error-categoria"></div>
        </div>

        <!-- SECCIÓN 2: DESCRIPCIÓN -->
        <div class="publish-card">
            <h2>Descripción</h2>
            <div style="margin-bottom: 12px;">
                <input type="text" name="titulo" placeholder="Título del producto (mínimo 10 caracteres)" class="input-text" style="text-transform: uppercase;">
            </div>
            <textarea name="descripcion" placeholder="Describe tu producto (mínimo 20 caracteres)" class="input-text"></textarea>
            <div class="section-error" id="error-descripcion"></div>
        </div>

        <!-- SECCIÓN 3: FOTOGRAFÍAS -->
        <div class="publish-card">
            <h2>Fotografías</h2>
            <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*" style="display:none">
            <div class="photo-grid-center">
                <div class="photo-grid" id="photoGrid">
                    <button type="button" class="photo-add" onclick="document.getElementById('imagenes').click()">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Agregar</span>
                    </button>
                </div>
            </div>
            <p style="font-size: 13px; color: #333; margin: 8px 0 0 0; font-weight: 600;">Máximo 5 fotos • La primera será la principal</p>
            <div class="section-error" id="error-fotos"></div>
        </div>

        <!-- SECCIÓN 4: PRECIO -->
        <div class="publish-card">
            <h2>Precio</h2>
            <div class="input-row">
                <div>
                    <div class="input-prefix-wrap">
                        <span class="input-prefix">Bs</span>
                        <input type="text" name="precio" id="precioInput" placeholder="1000" inputmode="numeric" pattern="[0-9]+" class="input-text">
                    </div>
                    <div class="price-literal" id="precioLiteral"></div>
                </div>
                <select name="estado" class="input-select">
                    <option value="">Estado</option>
                    <option value="nuevo">Nuevo</option>
                    <option value="como_nuevo">Como nuevo</option>
                    <option value="buen_estado">Buen estado</option>
                    <option value="aceptable">Estado aceptable</option>
                </select>
            </div>
            <div class="section-error" id="error-precio"></div>
        </div>

        <!-- SECCIÓN: OPCIONES DE ENTREGA -->
        <div class="publish-card">
            <h2>Opciones de Entrega</h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <label class="checkbox-wrapper">
                    <input type="checkbox" name="badges[]" value="envio_gratis">
                    <span class="checkbox-label">
                        <i class="fas fa-truck" style="color: #22c55e;"></i>
                        Ofrezco Envío Gratis
                    </span>
                </label>
                <label class="checkbox-wrapper">
                    <input type="checkbox" name="badges[]" value="oferta">
                    <span class="checkbox-label">
                        <i class="fas fa-tag" style="color: #f59e0b;"></i>
                        Marcar como Oferta
                    </span>
                </label>
                <label class="checkbox-wrapper">
                    <input type="checkbox" name="badges[]" value="nuevo">
                    <span class="checkbox-label">
                        <i class="fas fa-star" style="color: #3b82f6;"></i>
                        Marcar como Novedad
                    </span>
                </label>
            </div>
            <p style="font-size: 13px; color: #666; margin-top: 8px;">Selecciona las insignias que apliquen a tu producto.</p>
        </div>

        <?php if ($tienda_usuario && count($secciones_menu) > 0): ?>
        <!-- SECCIÓN: SECCIÓN DE TU TIENDA -->
        <div class="publish-card">
            <h2>Sección de tu Tienda</h2>
            <select name="categoria_tienda" class="input-select">
                <option value="">Mostrar en "Inicio" solamente</option>
                <?php foreach ($secciones_menu as $seccion): ?>
                <option value="<?php echo strtolower(htmlspecialchars($seccion)); ?>">
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

        <!-- SECCIÓN 5: UBICACIÓN -->
        <div class="publish-card">
            <h2>Ubicación</h2>
            
            <?php if ($ubicacion_fija): ?>
                <!-- UBICACIÓN FIJA DEL USUARIO (OCULTA SELECTORES) -->
                <div style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="background: #e0f2fe; padding: 10px; border-radius: 50%;">
                        <i class="fas fa-map-marker-alt" style="color: #0ea5e9; font-size: 20px;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: #334155; font-size: 15px;">
                            <?php echo htmlspecialchars($user_dept_nombre . ' - ' . $user_mun_nombre); ?>
                        </div>
                        <div style="font-size: 13px; color: #64748b;">Ubicación de tu perfil</div>
                    </div>
                    <!-- Inputs ocultos para enviar los datos -->
                    <input type="hidden" name="departamento" value="<?php echo htmlspecialchars($user_dept); ?>">
                    <input type="hidden" name="municipio" value="<?php echo htmlspecialchars($user_mun); ?>">
                </div>
            <?php else: ?>
                <!-- SELECTORES NORMALES (SOLO SI NO TIENE UBICACIÓN) -->
                <div class="input-row">
                    <select name="departamento" id="departamentoSelect" class="input-select">
                        <option value="">Departamento</option>
                        <?php foreach ($departamentos as $codigo => $nombre): ?>
                        <option value="<?php echo $codigo; ?>">
                            <?php echo htmlspecialchars($nombre); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="municipio" id="municipioSelect" class="input-select" disabled>
                        <option value="">Primero elige departamento</option>
                    </select>
                </div>
                <div class="section-error" id="error-ubicacion"></div>
            <?php endif; ?>
        </div>

        <div class="form-buttons">
            <a href="/" class="btn-cancel">Cancelar</a>
            <button type="submit" class="btn-publish">Publicar Anuncio</button>
        </div>
    </form>
</div>

<script>
(function() {
    const MAX = 5;
    let files = [];
    const input = document.getElementById('imagenes');
    const grid = document.getElementById('photoGrid');

    input.addEventListener('change', function() {
        Array.from(this.files).forEach(f => {
            if (files.length >= MAX) return;
            if (!f.type.match(/^image\/(jpeg|jpg|png|webp)$/i)) return;
            if (f.size > 5 * 1024 * 1024) return;
            files.push(f);
        });
        render();
    });

    function render() {
        const btn = grid.querySelector('.photo-add');
        grid.innerHTML = '';
        
        files.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'photo-item';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'photo-remove';
            rm.innerHTML = '×';
            rm.onclick = () => { files.splice(i, 1); render(); };
            div.appendChild(img);
            div.appendChild(rm);
            if (i === 0) {
                const badge = document.createElement('div');
                badge.className = 'photo-badge';
                badge.textContent = 'PRINCIPAL';
                div.appendChild(badge);
            }
            grid.appendChild(div);
        });
        
        if (files.length < MAX) grid.appendChild(btn);
        
        try {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (e) {}
    }

    // Convertir número a palabras
    function numeroAPalabras(num) {
        if (!num || num <= 0) return '';
        
        const unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        const decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        const especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        const centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        
        if (num === 100) return 'cien';
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
            return centenas[c] + (resto > 0 ? ' ' + numeroAPalabras(resto) : '');
        }
        if (num >= 1000 && num < 1000000) {
            const m = Math.floor(num / 1000);
            const resto = num % 1000;
            let millar = m === 1 ? 'mil' : numeroAPalabras(m) + ' mil';
            return millar + (resto > 0 ? ' ' + numeroAPalabras(resto) : '');
        }
        if (num >= 1000000) {
            const mill = Math.floor(num / 1000000);
            const resto = num % 1000000;
            let millones = mill === 1 ? 'un millón' : numeroAPalabras(mill) + ' millones';
            return millones + (resto > 0 ? ' ' + numeroAPalabras(resto) : '');
        }
        return '';
    }
    
    // Actualizar precio en literal
    const precioInput = document.getElementById('precioInput');
    const precioLiteral = document.getElementById('precioLiteral');
    
    if (precioInput && precioLiteral) {
        // Bloquear caracteres no numéricos (solo permite dígitos)
        precioInput.addEventListener('input', function() {
            // Eliminar cualquier carácter que no sea dígito
            this.value = this.value.replace(/[^0-9]/g, '');

            const valor = parseInt(this.value);
            if (!isNaN(valor) && valor > 0) {
                let texto = numeroAPalabras(valor).charAt(0).toUpperCase() + numeroAPalabras(valor).slice(1);
                texto += ' boliviano' + (valor !== 1 ? 's' : '');
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
    }

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
                    
                    // La API devuelve data.data, no data.subcategorias
                    const subcats = data.data || data.subcategorias || [];
                    
                    if (data.success && subcats.length > 0) {
                        subcats.forEach(s => {
                            const opt = document.createElement('option');
                            opt.value = s.id;
                            opt.textContent = s.nombre;
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
                    subcategoriaSelect.innerHTML = '<option value="">Error al cargar</option>';
                });
        });
    }

    // VALIDACIÓN PROFESIONAL POR SECCIÓN - SIN MENSAJES REPETITIVOS
    const form = document.getElementById('publishForm');
    const submitButton = form.querySelector('.btn-publish');
    const originalButtonText = submitButton.innerHTML;

    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevenir el envío tradicional SIEMPRE

        // Limpiar errores previos
        document.querySelectorAll('.section-error').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
        const globalError = document.querySelector('.alert-error');
        if (globalError) globalError.style.display = 'none';

        let hasErrors = false;

        // Validar Fotos
        if (files.length === 0) {
            showError('error-fotos', 'Debes agregar al menos una foto.');
            hasErrors = true;
        }

        // Validar Descripción
        const titulo = document.querySelector('[name="titulo"]').value.trim();
        const descripcion = document.querySelector('[name="descripcion"]').value.trim();
        if (!titulo || titulo.length < 10) {
            showError('error-descripcion', 'El título debe tener al menos 10 caracteres.');
            hasErrors = true;
        } else if (!descripcion || descripcion.length < 20) {
            showError('error-descripcion', 'La descripción debe tener al menos 20 caracteres.');
            hasErrors = true;
        }

        // Validar Categoría
        const categoria = document.querySelector('[name="categoria_id"]').value;
        const subcategoriaSelect = document.querySelector('[name="subcategoria_id"]');
        const subcategoria = subcategoriaSelect.value;
        if (!categoria) {
            showError('error-categoria', 'Debes seleccionar una categoría.');
            hasErrors = true;
        // Solo validar subcategoría si hay opciones para elegir (más de 1, p.ej. "Seleccionar" + opciones)
        } else if (subcategoriaSelect.options.length > 1 && !subcategoria) {
            showError('error-categoria', 'Debes seleccionar una subcategoría.');
            hasErrors = true;
        }

        // Validar Precio
        const precio = document.querySelector('[name="precio"]').value;
        const estado = document.querySelector('[name="estado"]').value;
        if (!precio || parseFloat(precio) <= 0) {
            showError('error-precio', 'Debes ingresar un precio válido.');
            hasErrors = true;
        } else if (!estado) {
            showError('error-precio', 'Debes seleccionar el estado del producto.');
            hasErrors = true;
        }

        // Validar Ubicación (si los selectores son visibles)
        const departamentoSelect = document.getElementById('departamentoSelect');
        if (departamentoSelect) { // Si no hay ubicación fija
            const departamento = departamentoSelect.value;
            const municipio = document.getElementById('municipioSelect').value;
            if (!departamento) {
                showError('error-ubicacion', 'Debes seleccionar un departamento.');
                hasErrors = true;
            } else if (!municipio) {
                showError('error-ubicacion', 'Debes seleccionar un municipio.');
                hasErrors = true;
            }
        }

        if (hasErrors) {
            const firstError = document.querySelector('.section-error[style*="block"]');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return; // Detener si hay errores de validación
        }

        // --- INICIO DE ENVÍO ASÍNCRONO ---

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';

        const formData = new FormData(form);
        
        // Asegurarnos que las imágenes gestionadas por JS se añadan correctamente
        const imageInput = document.getElementById('imagenes');
        formData.delete('imagenes[]'); // Limpiar por si acaso
        files.forEach(file => {
            formData.append('imagenes[]', file, file.name);
        });

        fetch('/api/crear_producto_completo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir a la página de éxito como lo hacía el PHP
                window.location.href = 'publicacion_exitosa.php';
            } else {
                // Mostrar error devuelto por la API
                const errorContainer = document.querySelector('.alert-error');
                if (errorContainer) {
                    errorContainer.textContent = data.message || 'Ocurrió un error inesperado.';
                    errorContainer.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        })
        .catch(error => {
            console.error('Error en la petición fetch:', error);
            const errorContainer = document.querySelector('.alert-error');
            if (errorContainer) {
                errorContainer.textContent = 'Error de conexión. Por favor, intenta de nuevo.';
                errorContainer.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        });
    });

    function showError(id, message) {
        const errorEl = document.getElementById(id);
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

})();
</script>

<?php require_once '../includes/footer.php'; ?>
