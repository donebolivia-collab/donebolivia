<?php
// includes/auth.php - helpers de autenticación (independiente del resto)
require_once __DIR__ . '/../config/database.php';

function auth_session_boot(){
  if (session_status() !== PHP_SESSION_ACTIVE) {
      // Configuración de cookies seguras antes de iniciar sesión
      if (ini_get('session.use_cookies')) {
          $p = session_get_cookie_params();
          // Intentar forzar cookies seguras
          session_set_cookie_params([
              'lifetime' => $p['lifetime'],
              'path' => $p['path'],
              'domain' => $p['domain'],
              'secure' => isset($_SERVER['HTTPS']), // Solo Secure si hay HTTPS
              'httponly' => true, // Protección contra XSS en cookies
              'samesite' => 'Lax' // Protección básica CSRF
          ]);
      }
      session_start();
  }
}
function auth_db(){
  return getDB();
}
function auth_csrf_token(){
  auth_session_boot();
  if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } // Aumentado a 32 bytes para más entropía
  return $_SESSION['csrf'];
}
function auth_csrf_check($t){
  auth_session_boot();
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? '');
}
function auth_current_user(){
  auth_session_boot();
  if (empty($_SESSION['uid'])) return null;
  $pdo = auth_db();
  $st = $pdo->prepare('SELECT id,name,email FROM users WHERE id=?');
  $st->execute([$_SESSION['uid']]);
  return $st->fetch() ?: null;
}
function auth_require_login(){
  if (!auth_current_user()){
    header('Location: /auth/login.php');
    exit;
  }
}
function auth_login($uid){
  auth_session_boot();
  $_SESSION['uid'] = (int)$uid;
  session_regenerate_id(true); // Previene Session Fixation
}
function auth_logout(){
  auth_session_boot();
  $_SESSION = [];
  if (ini_get('session.use_cookies')){
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
