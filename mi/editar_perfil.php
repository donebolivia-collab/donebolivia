<?php
$titulo = "Editar perfil";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ubicaciones_bolivia.php';

if (!estaLogueado()) { 
    redireccionar('/auth/login.php?redirect=' . urlencode('/mi/editar_perfil.php')); 
}

$usr = obtenerUsuarioActual();
$db = getDB();

// Obtener departamentos
$departamentos = obtenerDepartamentosBolivia();

// Procesar el formulario
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $departamento_codigo = $_POST['departamento'] ?? '';
    $municipio_codigo = $_POST['municipio'] ?? '';
    
    // Validaciones
    if (empty($nombre)) {
        $error = 'El nombre es obligatorio';
    } elseif (empty($email)) {
        $error = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } elseif (empty($departamento_codigo) || empty($municipio_codigo)) {
        $error = 'Debes seleccionar tu departamento y municipio';
    } else {
        // Verificar si el email ya existe (excepto el del usuario actual)
        $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $_SESSION['usuario_id']]);
        
        if ($stmt_check->fetch()) {
            $error = 'Este email ya está registrado por otro usuario';
        } else {
            // Obtener nombres de ubicación
            $todosMunicipios = obtenerTodosMunicipiosConCodigos();
            $municipioInfo = $todosMunicipios[$municipio_codigo] ?? null;
            
            if (!$municipioInfo) {
                $error = 'Municipio no válido';
            } else {
                $departamento_nombre = $departamentos[$departamento_codigo] ?? '';
                $municipio_nombre = $municipioInfo['nombre'];
                
                // Actualizar datos
                $stmt_update = $db->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, email = ?, telefono = ?, 
                        departamento_codigo = ?, municipio_codigo = ?,
                        departamento_nombre = ?, municipio_nombre = ?
                    WHERE id = ?
                ");
                
                if ($stmt_update->execute([
                    $nombre, $email, $telefono, 
                    $departamento_codigo, $municipio_codigo,
                    $departamento_nombre, $municipio_nombre,
                    $_SESSION['usuario_id']
                ])) {
                    $mensaje = 'Perfil actualizado correctamente';
                    // Recargar datos del usuario
                    $usr = obtenerUsuarioActual();
                } else {
                    $error = 'Error al actualizar el perfil';
                }
            }
        }
    }
}

// URL de la foto de perfil
$foto_perfil_url = !empty($usr['foto_perfil']) 
    ? '/uploads/perfiles/' . $usr['foto_perfil'] 
    : null;
?>

<style>
/* Estilos para el formulario de edición */
.edit-profile-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.edit-profile-card {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.edit-profile-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
}

/* Foto de perfil con opción de cambio */
.edit-profile-photo-wrapper {
    position: relative;
    cursor: pointer;
}

.edit-profile-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6a00 0%, #ff8533 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    font-size: 32px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.edit-profile-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}

.edit-profile-photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.edit-profile-photo-wrapper:hover .edit-profile-photo-overlay {
    opacity: 1;
}

.edit-profile-photo-overlay i {
    color: #fff;
    font-size: 24px;
}

.photo-upload-input {
    display: none;
}

.photo-upload-info {
    font-size: 0.75rem;
    color: #666;
    margin-top: 0.5rem;
    text-align: center;
}

.edit-profile-title h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: #222;
}

.edit-profile-title p {
    margin: 0.25rem 0 0 0;
    color: #666;
    font-size: 0.95rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #ff6a00;
    box-shadow: 0 0 0 3px rgba(255, 106, 0, 0.1);
}

.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s;
    background: #fff;
    cursor: pointer;
}

.form-select:focus {
    outline: none;
    border-color: #ff6a00;
    box-shadow: 0 0 0 3px rgba(255, 106, 0, 0.1);
}

.form-select:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
    opacity: 0.6;
}

.location-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 2px solid #e9ecef;
}

.location-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #ff6a00;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid #f0f0f0;
}

.btn-save {
    background: #ff6a00;
    border: none;
    color: #fff;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-save:hover {
    background: #e85e00;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 106, 0, 0.3);
}

.btn-cancel {
    background: #f0f0f0;
    border: none;
    color: #333;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-cancel:hover {
    background: #e0e0e0;
    color: #000;
}

.form-help {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.25rem;
}

/* Modal de éxito */
.success-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.success-modal {
    background: white;
    border-radius: 16px;
    padding: 2.5rem;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease;
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
    animation: scaleIn 0.5s ease 0.2s both;
}

.success-modal h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 1rem 0;
}

.success-modal p {
    color: #666;
    font-size: 1rem;
    margin: 0 0 2rem 0;
    line-height: 1.6;
}

.success-modal-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-modal {
    padding: 0.85rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
}

.btn-modal-primary {
    background: #ff6a00;
    color: white;
}

.btn-modal-primary:hover {
    background: #e85e00;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 106, 0, 0.3);
}

.btn-modal-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-modal-secondary:hover {
    background: #e0e0e0;
    color: #000;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@media (max-width: 768px) {
    .edit-profile-card {
        padding: 1.5rem;
    }

    .edit-profile-header {
        flex-direction: column;
        text-align: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn-save, .btn-cancel {
        width: 100%;
        justify-content: center;
    }

    .location-section {
        padding: 1rem;
    }

    .success-modal {
        padding: 2rem 1.5rem;
    }

    .success-modal-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="edit-profile-container">
    <div class="edit-profile-card">
        <div class="edit-profile-header">
            <div class="edit-profile-photo-wrapper" onclick="document.getElementById('photoUpload').click()">
                <div class="edit-profile-icon">
                    <?php if ($foto_perfil_url): ?>
                        <img src="<?php echo htmlspecialchars($foto_perfil_url); ?>" alt="Foto de perfil" id="profileImage">
                    <?php else: ?>
                        <span id="profileInitial"><?php echo htmlspecialchars(mb_substr($usr['nombre'] ?? 'U', 0, 1, 'UTF-8')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="edit-profile-photo-overlay">
                    <i class="fas fa-camera"></i>
                </div>
                <input type="file" 
                       id="photoUpload" 
                       class="photo-upload-input" 
                       accept="image/jpeg,image/jpg,image/png">
            </div>
            <div class="edit-profile-title">
                <h1>Editar perfil</h1>
                <p>Actualiza tu información personal</p>
                <div class="photo-upload-info">
                    <i class="fas fa-info-circle"></i> Haz clic en la foto para cambiarla (JPG/PNG, máx 2MB)
                </div>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success" style="display:none;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="nombre">
                    <i class="fas fa-user"></i> Nombre completo *
                </label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="nombre" 
                    name="nombre" 
                    value="<?php echo htmlspecialchars($usr['nombre'] ?? ''); ?>"
                    required
                    placeholder="Ingresa tu nombre completo">
                <div class="form-help">Este nombre será visible en tus publicaciones</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="fas fa-envelope"></i> Correo electrónico *
                </label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($usr['email'] ?? ''); ?>"
                    required
                    placeholder="tu@email.com">
                <div class="form-help">Usaremos este email para contactarte</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="telefono">
                    <i class="fas fa-phone"></i> Teléfono
                    <?php if (!empty($usr['telefono_verificado']) && $usr['telefono_verificado'] == 1): ?>
                        <span style="color: #25D366; font-size: 0.9rem;">
                            <i class="fas fa-check-circle"></i> Verificado
                        </span>
                    <?php endif; ?>
                </label>
                <input 
                    type="tel" 
                    class="form-control" 
                    id="telefono" 
                    name="telefono" 
                    value="<?php echo htmlspecialchars($usr['telefono'] ?? ''); ?>"
                    placeholder="Ej: 70123456"
                    <?php echo (!empty($usr['telefono_verificado']) && $usr['telefono_verificado'] == 1) ? 'readonly' : ''; ?>>
                <div class="form-help">
                    <?php if (!empty($usr['telefono_verificado']) && $usr['telefono_verificado'] == 1): ?>
                        Tu teléfono está verificado. Contacta soporte para cambiarlo.
                    <?php else: ?>
                        Los compradores podrán contactarte por este número
                    <?php endif; ?>
                </div>
            </div>

            <!-- NUEVO SISTEMA DE UBICACIÓN -->
            <div class="location-section">
                <h5 class="location-title">
                    <i class="fas fa-map-marker-alt"></i> Ubicación
                </h5>
                
                <div class="form-group">
                    <label class="form-label" for="departamento">
                        Departamento *
                    </label>
                    <select class="form-select" id="departamento" name="departamento" required>
                        <option value="">Selecciona tu departamento</option>
                        <?php foreach ($departamentos as $codigo => $nombre): ?>
                        <option value="<?php echo $codigo; ?>"
                                <?php echo (($usr['departamento_codigo'] ?? '') === $codigo) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nombre); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="municipio">
                        Municipio *
                    </label>
                    <select class="form-select" id="municipio" name="municipio" required disabled>
                        <option value="">Primero selecciona un departamento</option>
                    </select>
                    <div class="form-help">
                        <i class="fas fa-info-circle"></i>
                        Selecciona primero tu departamento para ver los municipios disponibles
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i>
                    Guardar cambios
                </button>
                <a href="/mi/perfil.php" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Modal de éxito -->
<?php if ($mensaje): ?>
<div class="success-modal-overlay" id="successModal">
    <div class="success-modal">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>¡Perfil actualizado!</h2>
        <p>Tus cambios se han guardado correctamente.<br>Ahora puedes continuar usando tu cuenta.</p>
        <div class="success-modal-buttons">
            <a href="/index.php" class="btn-modal btn-modal-secondary">
                <i class="fas fa-home"></i>
                Ir al inicio
            </a>
            <a href="/mi/perfil.php" class="btn-modal btn-modal-primary">
                <i class="fas fa-user"></i>
                Ver mi perfil
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Cerrar modal al hacer clic fuera de él
<?php if ($mensaje): ?>
document.getElementById('successModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
<?php endif; ?>

// Cargar municipios cuando se selecciona un departamento
document.addEventListener('DOMContentLoaded', function() {
    const departamentoSelect = document.getElementById('departamento');
    const municipioSelect = document.getElementById('municipio');
    const municipioActual = '<?php echo $usr['municipio_codigo'] ?? ''; ?>';
    
    // Cargar municipios al cambiar departamento
    departamentoSelect.addEventListener('change', function() {
        const departamento = this.value;
        
        if (!departamento) {
            municipioSelect.innerHTML = '<option value="">Primero selecciona un departamento</option>';
            municipioSelect.disabled = true;
            return;
        }
        
        // Habilitar select
        municipioSelect.disabled = false;
        municipioSelect.innerHTML = '<option value="">Cargando municipios...</option>';
        
        // Hacer petición para obtener municipios
        fetch('/api/get_municipios.php?departamento=' + encodeURIComponent(departamento))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    municipioSelect.innerHTML = '<option value="">Selecciona tu municipio</option>';
                    
                    data.data.forEach(municipio => {
                        const option = document.createElement('option');
                        option.value = municipio.codigo;
                        option.textContent = municipio.nombre;
                        
                        // Seleccionar el municipio actual si coincide
                        if (municipio.codigo === municipioActual) {
                            option.selected = true;
                        }
                        
                        municipioSelect.appendChild(option);
                    });
                } else {
                    municipioSelect.innerHTML = '<option value="">No hay municipios disponibles</option>';
                }
            })
            .catch(error => {
                console.error('Error al cargar municipios:', error);
                municipioSelect.innerHTML = '<option value="">Error al cargar municipios</option>';
            });
    });
    
    // Cargar municipios iniciales si hay departamento seleccionado
    if (departamentoSelect.value) {
        departamentoSelect.dispatchEvent(new Event('change'));
    }
});

// Subir foto de perfil
document.getElementById('photoUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    
    if (!file) return;
    
    // Validar tipo
    if (!file.type.match('image/(jpeg|jpg|png)')) {
        alert('Solo se permiten imágenes JPG y PNG');
        return;
    }
    
    // Validar tamaño (2MB)
    if (file.size > 2 * 1024 * 1024) {
        alert('La imagen no debe superar 2MB');
        return;
    }
    
    // Mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const profileIcon = document.querySelector('.edit-profile-icon');
        const existingImg = profileIcon.querySelector('img');
        const existingInitial = profileIcon.querySelector('span');
        
        if (existingImg) {
            existingImg.src = e.target.result;
        } else {
            if (existingInitial) {
                existingInitial.remove();
            }
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Foto de perfil';
            img.id = 'profileImage';
            profileIcon.appendChild(img);
        }
    };
    reader.readAsDataURL(file);
    
    // Subir archivo
    const formData = new FormData();
    formData.append('foto_perfil', file);
    
    fetch('/api/upload_profile_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            const alert = document.createElement('div');
            alert.className = 'alert alert-success';
            alert.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            document.querySelector('.edit-profile-card').insertBefore(alert, document.querySelector('form'));
            
            // Remover alerta después de 3 segundos
            setTimeout(() => alert.remove(), 3000);
            const perfilUrl = (data.foto_url || '').replace(/\\/g, '/');
            const finalUrl = perfilUrl ? perfilUrl + '?t=' + Date.now() : null;
            if (finalUrl) {
                // 1) Actualizar el círculo (preview local ya se mostró, ahora la URL real del servidor)
                const profileIcon = document.querySelector('.edit-profile-icon');
                let existingImg = profileIcon.querySelector('img');
                const existingInitial = profileIcon.querySelector('span');
                if (!existingImg) {
                    existingImg = document.createElement('img');
                    existingImg.alt = 'Foto de perfil';
                    existingImg.id = 'profileImage';
                    if (existingInitial) existingInitial.remove();
                    profileIcon.appendChild(existingImg);
                }
                existingImg.src = finalUrl;

                // 2) Actualizar la imagen del botón del usuario en el header
                const userBtn = document.querySelector('.user-menu-trigger');
                if (userBtn) {
                    let btnImg = userBtn.querySelector('img');
                    const btnIcon = userBtn.querySelector('i.fas.fa-user-circle');
                    if (!btnImg) {
                        btnImg = document.createElement('img');
                        btnImg.alt = 'Foto de perfil';
                        btnImg.style.width = '24px';
                        btnImg.style.height = '24px';
                        btnImg.style.borderRadius = '50%';
                        btnImg.style.objectFit = 'cover';
                        if (btnIcon) btnIcon.replaceWith(btnImg);
                        else userBtn.insertBefore(btnImg, userBtn.firstChild);
                    }
                    btnImg.src = finalUrl;
                }

                // 3) Actualizar la imagen del avatar dentro del dropdown del header
                const dropdownHeader = document.querySelector('#userDropdownMenu .dropdown-header');
                if (dropdownHeader) {
                    let ddImg = dropdownHeader.querySelector('img.user-avatar-img');
                    const ddIcon = dropdownHeader.querySelector('i.user-avatar');
                    if (!ddImg) {
                        ddImg = document.createElement('img');
                        ddImg.className = 'user-avatar-img';
                        ddImg.alt = 'Foto de perfil';
                        if (ddIcon) ddIcon.replaceWith(ddImg);
                        else dropdownHeader.insertBefore(ddImg, dropdownHeader.firstChild);
                    }
                    ddImg.src = finalUrl;
                }
                setTimeout(() => { try { location.reload(); } catch(e) {} }, 500);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al subir la foto');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>