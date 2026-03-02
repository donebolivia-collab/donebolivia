<?php
// includes/util_flash.php
// Minimal flash messages. Safe to include multiple times.
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function set_flash(string $type, string $message): void {
  $_SESSION['__flash'] = ['type'=>$type, 'message'=>$message];
}

function get_flash(): ?array {
  if (!empty($_SESSION['__flash'])) {
    $f = $_SESSION['__flash'];
    unset($_SESSION['__flash']);
    return $f;
  }
  return null;
}