<?php
// includes/safe_redirect.php
function safe_redirect($url = '/', $status = 302) {
  while (ob_get_level()) { ob_end_clean(); }
  if (!headers_sent()) {
    header('Location: ' . ($url ?: '/'), true, $status);
    exit;
  }
  echo '<!doctype html><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url ?: '/', ENT_QUOTES, 'UTF-8') . '">';
  echo '<script>location.replace(' . json_encode($url ?: '/') . ');</script>';
  exit;
}
