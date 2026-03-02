<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/auth.php'; // Agregado: Seguridad

requiereAdmin();

$admin = getAdminActual();
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo auth_csrf_token(); ?>"> <!-- Token CSRF -->
    <title><?php echo $titulo ?? 'Admin'; ?> - Done!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 para alertas modernas y consistentes -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
    <script>
        // Token CSRF Global para todas las peticiones AJAX del Admin
        // Si auth_csrf_token no existe, generamos un token dummy
        const CSRF_TOKEN = "<?php echo function_exists('auth_csrf_token') ? auth_csrf_token() : ''; ?>";
    </script>
    <style>
        :root {
            --primary: #ff6b1a;
            --dark: #2c3e50;
            --sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--dark);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            color: var(--primary);
            font-size: 32px;
            font-weight: 800;
            margin: 0;
        }

        .sidebar-header p {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            margin: 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            padding: 0 20px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary);
        }

        .nav-link.active {
            background: rgba(255,107,26,0.1);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 16px;
        }

        .nav-link .badge {
            margin-left: auto;
            background: #dc3545;
            font-size: 11px;
        }

        /* Main content */
        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .admin-topbar {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .admin-topbar h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .admin-user-info {
            line-height: 1.2;
        }

        .admin-user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .admin-user-role {
            font-size: 12px;
            color: #6c757d;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        .admin-content {
            padding: 32px;
        }

        /* Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: 100%;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .stat-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-card-title {
            font-size: 13px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-card-change {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-card-change.positive {
            color: #28a745;
        }

        .stat-card-change.negative {
            color: #dc3545;
        }

        /* Tables */
        .admin-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .admin-table table {
            margin: 0;
        }

        .admin-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px;
        }

        .admin-table td {
            padding: 16px;
            vertical-align: middle;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 0;
                transform: translateX(-100%);
            }

            .admin-main {
                margin-left: 0;
            }

            .admin-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3>Done!</h3>
            <p>Panel de Control</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="/admin/dashboard.php" class="nav-link <?php echo $pagina_actual === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/analytics.php" class="nav-link <?php echo $pagina_actual === 'analytics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Analíticas</span>
                </a>
                <a href="/admin/traffic.php" class="nav-link <?php echo $pagina_actual === 'traffic.php' ? 'active' : ''; ?>">
                    <i class="fas fa-globe"></i>
                    <span>Tráfico en Vivo</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Gestión</div>
                <a href="/admin/users.php" class="nav-link <?php echo $pagina_actual === 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Usuarios</span>
                </a>
                <a href="/admin/products.php" class="nav-link <?php echo $pagina_actual === 'products.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Productos</span>
                </a>
                <a href="/admin/categories.php" class="nav-link <?php echo $pagina_actual === 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Categorías</span>
                </a>
                <a href="/admin/stores.php" class="nav-link <?php echo $pagina_actual === 'stores.php' ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i>
                    <span>Tiendas</span>
                </a>
                <a href="/admin/badges.php" class="nav-link <?php echo $pagina_actual === 'badges.php' ? 'active' : ''; ?>">
                    <i class="fas fa-certificate"></i>
                    <span>Insignias</span>
                </a>
                <a href="/admin/feria.php" class="nav-link <?php echo $pagina_actual === 'feria.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Feria Virtual</span>
                </a>
                <a href="/admin/reports.php" class="nav-link <?php echo $pagina_actual === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-flag"></i>
                    <span>Reportes Prod.</span>
                    <?php
                    $reportes_pendientes = getEstadisticasGenerales()['reportes_pendientes'] ?? 0;
                    if ($reportes_pendientes > 0):
                    ?>
                        <span class="badge"><?php echo $reportes_pendientes; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin/store_reports.php" class="nav-link <?php echo $pagina_actual === 'store_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Reportes Tiendas</span>
                    <?php
                    // Obtener conteo de reportes de tiendas pendientes
                    try {
                        $db_count = getDB();
                        $stmt_count = $db_count->query("SELECT COUNT(*) FROM denuncias_tiendas WHERE estado = 'pendiente'");
                        $reportes_tiendas_pendientes = $stmt_count->fetchColumn();
                        if ($reportes_tiendas_pendientes > 0):
                        ?>
                            <span class="badge"><?php echo $reportes_tiendas_pendientes; ?></span>
                        <?php 
                        endif;
                    } catch (Exception $e) {
                        // Ignorar error si la tabla no existe aún
                    }
                    ?>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Sistema</div>
                <a href="/admin/settings.php" class="nav-link <?php echo $pagina_actual === 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
                <a href="/admin/logs.php" class="nav-link <?php echo $pagina_actual === 'logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Logs</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Acciones</div>
                <a href="/index.php" class="nav-link" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Ver sitio</span>
                </a>
                <a href="/admin/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-topbar">
            <h1><?php echo $titulo ?? 'Dashboard'; ?></h1>
            <div class="admin-user">
                <div class="admin-user-avatar">
                    <?php echo strtoupper(substr($admin['nombre'], 0, 1)); ?>
                </div>
                <div class="admin-user-info">
                    <div class="admin-user-name"><?php echo htmlspecialchars($admin['nombre']); ?></div>
                    <div class="admin-user-role"><?php echo ucfirst($admin['nivel']); ?></div>
                </div>
            </div>
        </div>

        <div class="admin-content">