<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php'; // Agregado: Cargar funciones de seguridad

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // NUEVO: Protección CSRF
    if (!auth_csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad: Sesión inválida. Por favor recarga la página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email y contraseña son obligatorios';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM administradores WHERE email = ? AND activo = 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_nombre'] = $admin['nombre'];
                    $_SESSION['admin_nivel'] = $admin['nivel'];

                    // Actualizar último acceso
                    $stmt = $db->prepare("UPDATE administradores SET ultimo_acceso = NOW() WHERE id = ?");
                    $stmt->execute([$admin['id']]);

                    header('Location: /admin/dashboard.php');
                    exit;
                } else {
                    sleep(2); // Rate limiting básico contra fuerza bruta
                    $error = 'Credenciales incorrectas';
                }
            } catch (Exception $e) {
                $error = 'Error del sistema. Inténtalo más tarde.';
                error_log("Error en login admin: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Done!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b1a;
            --dark: #2c3e50;
            --light: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }

        .admin-login-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .admin-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .admin-logo h1 {
            color: var(--primary);
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .admin-logo p {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
        }

        .btn-admin-login {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .btn-admin-login:hover {
            background: #e55d0f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 26, 0.3);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .back-home {
            text-align: center;
            margin-top: 20px;
        }

        .back-home a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .back-home a:hover {
            opacity: 1;
        }

        .admin-shield {
            text-align: center;
            margin-bottom: 20px;
        }

        .admin-shield i {
            font-size: 64px;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-shield">
                <i class="fas fa-shield-halved"></i>
            </div>

            <div class="admin-logo">
                <h1>Done!</h1>
                <p>Panel de Administración</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Token CSRF Oculto -->
                <input type="hidden" name="csrf_token" value="<?php echo auth_csrf_token(); ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email
                    </label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Contraseña
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-admin-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                </button>
            </form>
        </div>

        <div class="back-home">
            <a href="/index.php">
                <i class="fas fa-arrow-left me-2"></i>Volver al sitio
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>