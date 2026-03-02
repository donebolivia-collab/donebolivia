<?php
$titulo = "¡Actualizado!";
require_once '../includes/functions.php';

if (!estaLogueado()) {
    redireccionar('../auth/login.php');
}

// Verificar que haya datos de edición
if (!isset($_SESSION['producto_editado'])) {
    redireccionar('/');
}

$producto = $_SESSION['producto_editado'];
$producto_id = $producto['id'];
$titulo_producto = $producto['titulo'];

// Limpiar sesión después de leer
unset($_SESSION['producto_editado']);

require_once '../includes/header.php';
?>

<style>
body { background: #f5f5f5; }
.success-container { max-width: 600px; margin: 80px auto; padding: 0 20px; }
.success-card { background: white; border-radius: 16px; padding: 48px 40px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.08); animation: slideUp 0.4s ease-out; }
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; animation: scaleIn 0.5s ease-out 0.2s backwards; }
@keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
.success-icon svg { width: 40px; height: 40px; color: white; }
.success-title { font-size: 28px; font-weight: 700; color: #1a1a1a; margin: 0 0 12px 0; }
.success-subtitle { font-size: 16px; color: #666; margin: 0 0 32px 0; line-height: 1.5; }
.product-preview { background: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 32px; text-align: left; border: 1px solid #e5e7eb; }
.product-preview-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; font-weight: 500; }
.product-preview-title { font-size: 18px; font-weight: 600; color: #1f2937; margin: 0; }
.action-buttons { display: grid; gap: 12px; }
.btn-primary { background: #ff6b1a; color: white; padding: 16px 32px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; display: block; transition: all 0.3s; border: none; }
.btn-primary:hover { background: #e85e00; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 107, 26, 0.3); color: white; }
.btn-secondary { background: white; color: #374151; padding: 14px 32px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; display: block; transition: all 0.2s; border: 2px solid #e5e7eb; }
.btn-secondary:hover { border-color: #d1d5db; background: #f9fafb; color: #1f2937; }
.success-features { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.feature-item { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f0fdf4; border-radius: 8px; }
.feature-item:nth-child(2) { background: #e7f8ee; }
.feature-icon { width: 24px; height: 24px; color: #16a34a; flex-shrink: 0; }
.feature-item:nth-child(2) .feature-icon { color: #25D366; }
.feature-text { font-size: 14px; color: #166534; font-weight: 500; }
.feature-item:nth-child(2) .feature-text { color: #075e54; }
@media (max-width: 768px) {
    .success-container { margin: 40px auto; }
    .success-card { padding: 32px 24px; }
    .success-title { font-size: 24px; }
    .success-features { grid-template-columns: 1fr; gap: 10px; }
}
</style>

<div class="success-container">
    <div class="success-card">
        <div class="success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="success-title">¡Anuncio Actualizado!</h1>
        <p class="success-subtitle">Los cambios ya están publicados</p>

        <div class="product-preview">
            <div class="product-preview-label">Tu anuncio:</div>
            <h2 class="product-preview-title"><?php echo htmlspecialchars($titulo_producto); ?></h2>
        </div>

        <div class="success-features">
            <div class="feature-item">
                <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span class="feature-text">Visible al público</span>
            </div>
            <div class="feature-item">
                <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                <span class="feature-text">Recibirás mensajes por WhatsApp</span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="view_product.php?id=<?php echo $producto_id; ?>" class="btn-primary">
                Ver mi Anuncio
            </a>
            <a href="/mi/publicaciones.php" class="btn-secondary">
                Ir a Mis Anuncios
            </a>
        </div>
    </div>
</div>

<script>
// Auto-redireccionar después de 30 segundos
setTimeout(function() {
    window.location.href = 'view_product.php?id=<?php echo $producto_id; ?>';
}, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>
