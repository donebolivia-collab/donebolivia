<?php
$titulo = "Política de Privacidad";
require_once '../includes/header.php';
?>

<style>
.legal-container {
    max-width: 900px;
    margin: 30px auto;
    padding: 0 20px;
}

.legal-header {
    text-align: center;
    margin-bottom: 40px;
}

.legal-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
}

.legal-header .last-updated {
    color: #6c757d;
    font-size: 14px;
}

.legal-content {
    background: white;
    border: 2px solid #2c3e50;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.legal-content h2 {
    font-size: 22px;
    font-weight: 700;
    color: #2c3e50;
    margin-top: 30px;
    margin-bottom: 15px;
}

.legal-content h2:first-child {
    margin-top: 0;
}

.legal-content p {
    font-size: 15px;
    line-height: 1.7;
    color: #495057;
    margin-bottom: 15px;
    text-align: justify;
}

.legal-content ul {
    margin-left: 20px;
    margin-bottom: 15px;
}

.legal-content li {
    font-size: 15px;
    line-height: 1.7;
    color: #495057;
    margin-bottom: 8px;
}

.back-link {
    text-align: center;
    margin-top: 30px;
}

.back-link a {
    color: #ff6b1a;
    font-weight: 600;
    text-decoration: none;
    font-size: 16px;
}

.back-link a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .legal-content {
        padding: 24px 20px;
    }

    .legal-header h1 {
        font-size: 26px;
    }
}
</style>

<div class="legal-container">
    <div class="legal-header">
        <h1>Política de Privacidad</h1>
        <p class="last-updated">Última actualización: <?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="legal-content">
        <h2>1. Introducción</h2>
        <p>En Done!, operable a través de www.donebolivia.com (en adelante, la "Plataforma"), la privacidad de nuestros Usuarios es fundamental. Esta Política explica de manera transparente qué datos recolectamos realmente y cómo los protegemos. Al usar la Plataforma, usted acepta estas prácticas.</p>

        <h2>2. Información que Recopilamos</h2>
        <p>Limitamos la recolección de datos a lo estrictamente necesario para el funcionamiento del servicio:</p>
        <ul>
            <li><strong>Información de Registro:</strong> Nombre completo, fecha de nacimiento, correo electrónico, número de teléfono y contraseña (encriptada mediante hashing).</li>
            <li><strong>Información de Publicaciones:</strong> Todo contenido cargado voluntariamente por el Usuario (títulos, descripciones, precios y fotografías).</li>
            <li><strong>Información Técnica Básica:</strong> Al acceder a la Plataforma, nuestros servidores pueden registrar datos técnicos estándar como la dirección IP y el tipo de navegador (User Agent). Estos datos se utilizan exclusivamente para fines de seguridad, prevención de ataques y estadísticas generales de tráfico.</li>
        </ul>

        <h2>3. Finalidad del Tratamiento de Datos</h2>
        <p>Utilizamos su información para:</p>
        <ul>
            <li>Gestionar su cuenta y permitirle publicar anuncios.</li>
            <li>Facilitar que otros usuarios lo contacten para concretar ventas.</li>
            <li>Mantener la seguridad de la Plataforma y prevenir fraudes.</li>
            <li>Cumplir con requerimientos legales de las autoridades bolivianas.</li>
        </ul>

        <h2>4. Divulgación de la Información</h2>
        <p>Done! no vende, alquila ni comercializa sus datos personales. Su información se comparte únicamente en los siguientes casos:</p>
        <ul>
            <li><strong>Visibilidad Pública:</strong> Al publicar un anuncio, su nombre y número de teléfono serán visibles para cualquier visitante de la Plataforma para facilitar el contacto comercial.</li>
            <li><strong>Requerimientos Legales:</strong> Cuando exista una orden judicial o solicitud de autoridad competente en el Estado Plurinacional de Bolivia.</li>
            <li><strong>Proveedores Técnicos:</strong> Con servicios de alojamiento (hosting) que mantienen la infraestructura de la Plataforma bajo estándares de confidencialidad.</li>
        </ul>

        <h2>5. Seguridad de los Datos</h2>
        <p>Implementamos medidas técnicas para proteger su información:</p>
        <ul>
            <li>Uso de protocolos de transferencia segura (HTTPS).</li>
            <li>Encriptación de contraseñas para que nadie, ni siquiera el equipo de Done!, pueda verlas.</li>
            <li>Respaldos periódicos de la base de datos.</li>
        </ul>
        <p><strong>Aviso:</strong> El Usuario entiende que ninguna plataforma en internet es 100% invulnerable. Done! actúa con diligencia, pero no se hace responsable por interceptaciones ilegales o violaciones de seguridad fuera de nuestro control técnico.</p>

        <h2>6. Sus Derechos (Acceso y Eliminación)</h2>
        <p>Usted tiene control sobre sus datos. Puede:</p>
        <ul>
            <li>Corregir su información desde su perfil de usuario.</li>
            <li>Eliminar su cuenta y sus datos en cualquier momento. Una vez eliminada la cuenta, sus anuncios dejarán de ser visibles de forma inmediata.</li>
        </ul>

        <h2>7. Menores de Edad</h2>
        <p>Done! está diseñado para mayores de 18 años. No recolectamos datos de menores de edad intencionalmente. Si detectamos una cuenta de un menor, será eliminada de inmediato.</p>

        <h2>8. Enlaces a Terceros</h2>
        <p>No nos hacemos responsables por las prácticas de privacidad de sitios externos que puedan aparecer enlazados en los anuncios de los usuarios.</p>

        <h2>9. Cambios en esta Política</h2>
        <p>Nos reservamos el derecho de modificar este documento. Cualquier cambio será publicado en www.donebolivia.com. El uso continuo del sitio implica la aceptación de los nuevos términos.</p>

        <h2>10. Contacto</h2>
        <p>Para dudas sobre su privacidad, puede contactarnos a través de los canales oficiales habilitados en la Plataforma.</p>
    </div>

    <div class="back-link">
        <a href="/">← Volver al inicio</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
