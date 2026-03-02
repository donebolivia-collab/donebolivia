<?php
$titulo = "Términos de Uso";
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
        <h1>Términos de Uso</h1>
        <p class="last-updated">Última actualización: <?php echo date('d/m/Y'); ?></p>
    </div>

    <div class="legal-content">
        <h2>1. Aceptación de los Términos</h2>
        <p>Al acceder y utilizar los servicios de Done!, operables a través del sitio web www.donebolivia.com (en adelante, la "Plataforma"), el usuario (en adelante "Usuario") acepta de manera vinculante los presentes Términos de Uso. El acceso a la Plataforma implica la aceptación plena de todas las cláusulas aquí expuestas. Si no está de acuerdo, deberá abstenerse de utilizar el sitio.</p>

        <h2>2. Naturaleza del Servicio</h2>
        <p>Done! es una plataforma tecnológica de anuncios clasificados. Done! no es parte, ni actúa como intermediario, comisionista, agente o representante en las negociaciones o transacciones realizadas entre los Usuarios. Nuestra función se limita exclusivamente a proporcionar el espacio virtual para facilitar el contacto entre interesados. La Plataforma no garantiza la veracidad de los anuncios ni la calidad de los productos.</p>

        <h2>3. Registro de Cuenta</h2>
        <p>Para interactuar en la Plataforma, el Usuario debe crear una cuenta, comprometiéndose a:</p>
        <ul>
            <li>Proporcionar información verdadera, precisa y verificable.</li>
            <li>Mantener la confidencialidad de sus credenciales. Done! no se hace responsable por el uso negligente de las contraseñas por parte del Usuario.</li>
            <li>Notificar cualquier acceso no autorizado de manera inmediata a través de la Plataforma.</li>
        </ul>

        <h2>4. Uso Aceptable y Prohibiciones</h2>
        <p>Queda estrictamente prohibido:</p>
        <ul>
            <li>Publicar contenido falso, engañoso, difamatorio o fraudulento.</li>
            <li>Ofrecer productos o servicios prohibidos por la normativa vigente en Bolivia.</li>
            <li>Utilizar mecanismos automáticos (bots/scraping) sin autorización expresa.</li>
            <li>Realizar cualquier acto que interfiera con la seguridad o la operatividad de la Plataforma.</li>
        </ul>

        <h2>5. Responsabilidad del Usuario y Contenido</h2>
        <p>El Usuario que publica un anuncio es el único responsable por el contenido del mismo. Al publicar, el Usuario garantiza que posee los derechos legales sobre los bienes u objetos ofrecidos y se compromete a mantener indemne a Done! ante cualquier reclamo de terceros derivado de la publicación.</p>

        <h2>6. Exención Total de Responsabilidad por Transacciones</h2>
        <p>Las transacciones se realizan directamente entre los Usuarios. Done! no tiene participación alguna en el perfeccionamiento de la venta, el pago o la entrega. En consecuencia, Done! NO se hace responsable de:</p>
        <ul>
            <li>La existencia, calidad, seguridad, integridad o legalidad de los productos/servicios publicados.</li>
            <li>La capacidad de pago del Comprador ni la veracidad de la oferta del Vendedor.</li>
            <li>El cumplimiento de los acuerdos pactados entre las partes.</li>
            <li>Estafas, fraudes o delitos cometidos por Usuarios aprovechando el espacio de la Plataforma.</li>
            <li>Daños personales o materiales que surjan durante el encuentro físico entre Usuarios para la entrega del bien o servicio.</li>
        </ul>

        <h2>7. Propiedad Intelectual</h2>
        <p>Todo el software, código fuente, diseño, logotipos, la marca Done! y el dominio www.donebolivia.com son propiedad exclusiva. Queda prohibida su reproducción, imitación o uso total o parcial sin consentimiento previo y por escrito.</p>

        <h2>8. Facultades de la Plataforma</h2>
        <p>Done! se reserva el derecho unilateral de:</p>
        <ul>
            <li>Eliminar, suspender o modificar cualquier anuncio que viole estos términos o la moral y buenas costumbres.</li>
            <li>Suspender o cancelar cuentas de forma definitiva sin previo aviso y sin derecho a indemnización.</li>
            <li>Modificar o discontinuar el servicio en cualquier momento.</li>
        </ul>

        <h2>9. Limitación de Responsabilidad Técnica</h2>
        <p>Done! no garantiza el acceso ininterrumpido a la Plataforma. No somos responsables por daños derivados de virus, ataques informáticos, fallos de conexión o cualquier evento de fuerza mayor que afecte la disponibilidad del sitio web.</p>

        <h2>10. Jurisdicción y Ley Aplicable</h2>
        <p>Estos términos se rigen exclusivamente por las leyes del Estado Plurinacional de Bolivia. Para cualquier controversia, las partes se someten a la jurisdicción de los tribunales competentes de Bolivia, renunciando a cualquier otro fuero.</p>

        <h2>11. Contacto</h2>
        <p>Para cualquier duda o reporte de irregularidades, los Usuarios pueden dirigirse a los canales oficiales habilitados en la Plataforma.</p>
    </div>

    <div class="back-link">
        <a href="/">← Volver al inicio</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
