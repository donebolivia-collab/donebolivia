<?php
// includes/util_safe_redirect.php
// Robust redirect that works even if headers were already sent.
if (!function_exists('safe_redirect')) {
  function safe_redirect(string $url, int $status=303): void {
    if (!headers_sent()) {
      header('Location: ' . $url, true, $status);
      exit;
    }
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><meta charset="utf-8">';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $u . '"></noscript>';
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    exit;
  }
}