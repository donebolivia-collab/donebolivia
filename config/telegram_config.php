<?php
// Configuración de Telegram para Notificaciones de Admin
define('TELEGRAM_BOT_TOKEN', '7763991993:AAEiKdvXgSqm6EjE4wKXVjGbzvag2pDzmrQ');
define('TELEGRAM_CHAT_ID', '8393998474');

/**
 * Envía una notificación a Telegram (Versión Robusta con Fallback IP)
 * @param string $mensaje El texto a enviar
 * @return bool true si se envió correctamente, false si falló
 */
function enviarNotificacionTelegram($mensaje) {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        return false;
    }

    $token = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_CHAT_ID;
    
    // Lista de IPs conocidas de Telegram (por si falla el DNS)
    $telegramIPs = ['149.154.167.220', '149.154.167.197', '91.108.56.100'];
    
    // Datos comunes
    $data = [
        'chat_id' => $chatId,
        'text' => $mensaje,
        'parse_mode' => 'HTML'
    ];

    // Intento 1: Usando dominio oficial (Lo ideal)
    if (intentarEnvio("https://api.telegram.org/bot{$token}/sendMessage", $data)) {
        return true;
    }

    // Intento 2: Usando IPs directas (Fallback si falla DNS)
    foreach ($telegramIPs as $ip) {
        // Nota: Al usar IP, debemos pasar el Host en el header para que el servidor sepa qué sitio queremos
        if (intentarEnvio("https://{$ip}/bot{$token}/sendMessage", $data, true)) {
            return true;
        }
    }

    return false;
}

function intentarEnvio($url, $data, $usarIpDirecta = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout muy corto
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Importante para evitar problemas de certificados
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($usarIpDirecta) {
        // Truco: Forzar el Host header
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: api.telegram.org']);
    }
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}
?>