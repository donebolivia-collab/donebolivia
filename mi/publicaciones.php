<?php
$titulo = "Mis publicaciones";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (!estaLogueado()) { redireccionar('/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); }
$usr = obtenerUsuarioActual();
$db = getDB();
// Productos del usuario con imagen principal
$stmt = $db->prepare("
    SELECT p.*, 
      (SELECT nombre_archivo FROM producto_imagenes 
         WHERE producto_id = p.id 
         ORDER BY es_principal DESC, orden ASC LIMIT 1) AS img
    FROM productos p
    WHERE p.usuario_id = ? AND p.activo = 1 AND p.estado != 'eliminado'
    ORDER BY p.id DESC
    LIMIT 200
");
$stmt->execute([$_SESSION['usuario_id']]);
$mis = $stmt->fetchAll();
// CSRF token
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

?>
<div class="container my-4">
  <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?php
      $msgType = 'danger';
      $msgText = 'Error desconocido al procesar la publicación.';
      switch ($_GET['msg']) {
        case 'csrf': $msgText = 'Error de seguridad. Por favor, inténtalo de nuevo.'; break;
        case 'badid': $msgText = 'ID de publicación inválido.'; break;
        case 'forbidden': $msgText = 'No tienes permiso para eliminar esta publicación.'; break;
        case 'deleted': $msgType = 'success'; $msgText = 'Publicación eliminada correctamente.'; break;
        case 'fail': $msgText = 'No se pudo eliminar la publicación. Inténtalo de nuevo.'; break;
      }
      echo $msgType;
    ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msgText) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <h2 class="mb-3">Mis publicaciones</h2>
  <?php if (!$mis): ?>
    <div class="alert alert-info">Aún no tienes publicaciones. <a class="btn btn-sm btn-success ms-2" href="/products/add_product.php">Publicar</a></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>Imagen</th><th>Título</th><th>Precio</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach($mis as $r): 
          $id=(int)$r['id']; $title=$r['titulo']; $price=$r['precio']; $estado=$r['estado']; $fecha=$r['fecha_publicacion'] ?? $r['created_at'] ?? '';
          $img=$r['img']; $href='/products/view_product.php?id='.$id;
          $imgUrl = $img ? '/uploads/'.$img : 'https://via.placeholder.com/84x84?text=%20';
        ?>
        <tr>
          <td style="width:92px">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($title) ?>" style="width:84px;height:84px;object-fit:cover;border-radius:8px">
          </td>
          <td style="max-width: 250px;">
            <a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none d-block text-truncate" title="<?= htmlspecialchars($title) ?>">
              <?= htmlspecialchars($title) ?>
            </a>
            <?php if (!empty($r['envio_gratis'])): ?>
                <div style="margin-top: 4px; display: flex; gap: 4px;">
                                    </div>
            <?php endif; ?>
          </td>
          <td>Bs. <?= number_format((float)$price, 2, '.', ',') ?></td>
          <td><?= htmlspecialchars($estado) ?></td>
          <td><?= htmlspecialchars($fecha) ?></td>
          <td style="white-space: nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($href) ?>">Ver</a>
            <a class="btn btn-sm btn-outline-secondary" href="/products/edit_product.php?id=<?= $id ?>">Editar</a>
            <?php $shareText = rawurlencode(($title?:'') . " - " . (string)$price . " en Cambalache: " . $href); $waWeb = "https://api.whatsapp.com/send?text={$shareText}"; $waApp = "whatsapp://send?text={$shareText}"; ?>
            <a class="btn btn-sm btn-outline-success" href="#" onclick="return shareWhatsApp('<?= $waApp ?>', '<?= $waWeb ?>');">Compartir</a>
            <form method="post" action="/mi/eliminar_publicacion.php" style="display:inline-block" onsubmit="return confirm('¿Eliminar esta publicación?');">
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<script>
function shareWhatsApp(appUrl, webUrl){
  var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  if (isMobile){ window.location.href = appUrl; setTimeout(function(){ window.open(webUrl, '_blank'); }, 400); }
  else { window.open(webUrl, '_blank'); }
  return false;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
