<?php
/**
 * DONE! - Landing Page Business
 * Versión final: Texto definitivo aprobado por el usuario (CORREGIDO ALINEACIÓN)
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Si ya tiene tienda, redirigir directo al editor
if (estaLogueado()) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM tiendas WHERE usuario_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['usuario_id']]);
    if ($stmt->fetch()) {
        header('Location: /mi/tienda_editor.php');
        exit;
    }
}

$titulo = "Done! Business";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .business-landing {
        max-width: 800px;
        margin: 0 auto;
        padding: 100px 20px;
        text-align: center;
        min-height: 60vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center; /* CENTRADO TOTAL HORIZONTAL */
    }

    .business-title {
        font-size: 42px;
        font-weight: 800;
        line-height: 1.2;
        color: #2c3e50;
        margin-bottom: 24px;
        font-style: italic;
    }

    .business-text {
        font-size: 18px;
        color: #1f2937; /* Gris casi negro para mejor lectura */
        line-height: 1.6;
        margin-bottom: 40px;
        max-width: 750px;
        margin-left: auto;
        margin-right: auto;
        font-style: italic;
    }

    .business-cta {
        display: inline-block;
        background: #ff6b1a;
        color: white;
        padding: 18px 48px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s;
        box-shadow: 0 4px 6px rgba(255, 107, 26, 0.2);
    }

    .business-cta:hover {
        background: #e85e00;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px rgba(255, 107, 26, 0.3);
    }

    .promo-tag {
        display: inline-block;
        background: #e0f2f1;
        color: #00897b;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-top: 24px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    @media (max-width: 768px) {
        .business-title { font-size: 32px; }
    }
</style>

<div class="business-landing">
    <!-- Badge Eliminado -->
    <h1 class="business-title">
        Tu propia sucursal digital
    </h1>
    <p class="business-text">
        Crea tu propia página web dentro de Done! para mostrar tus productos y servicios en un solo lugar. Proyecta una imagen profesional, comparte información clave de tu negocio y genera confianza en tus clientes.
    </p>
    
    <div>
        <a href="/mi/crear_tienda.php" class="business-cta">
            Crear mi Tienda Oficial
        </a>
    </div>

    <div class="promo-tag">
        Sin costo por lanzamiento
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>