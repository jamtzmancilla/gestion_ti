<?php
/**
 * install.php — Ejecutar UNA VEZ para configurar el sistema
 * Eliminar o proteger este archivo después de la instalación
 *
 * Acceso: http://localhost/gestion_ti/install.php
 */

// ── Verificar si ya está instalado ───────────────────────
$lockFile = __DIR__.'/install.lock';
if (file_exists($lockFile)) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#666;">
    <h2 style="color:#dc3545;">⚠ Instalación ya completada</h2>
    <p>El sistema ya fue instalado. Este archivo fue bloqueado por seguridad.</p>
    <p>Si necesitas reinstalar, elimina el archivo <code>install.lock</code></p>
    <a href="login.php" style="background:#1a3a5c;color:#fff;padding:8px 20px;border-radius:5px;text-decoration:none;">Ir al Login</a>
    </div>');
}

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ── Paso 2: Instalar ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = trim($_POST['db_host']  ?? 'localhost');
    $dbname = trim($_POST['db_name']  ?? 'gestion_ti');
    $user   = trim($_POST['db_user']  ?? 'root');
    $pass   = trim($_POST['db_pass']  ?? '');
    $appName= trim($_POST['app_name'] ?? 'Gestión TI');

    // 1. Probar conexión sin BD
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $errors[] = 'No se pudo conectar a MySQL: '.$e->getMessage();
    }

    if (empty($errors)) {
        // 2. Crear BD y ejecutar schema
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");

            // Leer y ejecutar schema.sql
            $schemaSql = file_get_contents(__DIR__.'/schema.sql');
            // Quitar CREATE DATABASE y USE del schema (ya ejecutado)
            $schemaSql = preg_replace('/CREATE DATABASE.*?;/si', '', $schemaSql);
            $schemaSql = preg_replace('/USE.*?;/si', '', $schemaSql);
            // Ejecutar por sentencias
            foreach (array_filter(array_map('trim', explode(';', $schemaSql))) as $stmt) {
                if ($stmt) try { $pdo->exec($stmt); } catch (PDOException $e2) { /* ignorar duplicados */ }
            }

            // 3. Ejecutar auth_schema.sql
            $authSql = file_get_contents(__DIR__.'/auth_schema.sql');
            $authSql = preg_replace('/USE.*?;/si', '', $authSql);
            foreach (array_filter(array_map('trim', explode(';', $authSql))) as $stmt) {
                if ($stmt) try { $pdo->exec($stmt); } catch (PDOException $e2) { /* ignorar duplicados */ }
            }

            // 4. Crear/actualizar cuentas con hashes reales
            $adminPass = trim($_POST['admin_pass'] ?? 'admin123');
            $superPass = trim($_POST['super_pass'] ?? 'super123');
            $userPass  = trim($_POST['user_pass']  ?? 'usuario123');

            $hashAdmin = password_hash($adminPass, PASSWORD_BCRYPT, ['cost'=>12]);
            $hashSuper = password_hash($superPass, PASSWORD_BCRYPT, ['cost'=>12]);
            $hashUser  = password_hash($userPass,  PASSWORD_BCRYPT, ['cost'=>12]);

            $adminName = trim($_POST['admin_name'] ?? 'Administrador del Sistema');
            $adminEmail= trim($_POST['admin_email']?? 'admin@empresa.com');

            // Limpiar cuentas demo e insertar reales
            $pdo->exec("DELETE FROM system_users WHERE username IN ('admin','supervisor','usuario1')");
            $pdo->prepare("INSERT INTO system_users(username,password_hash,nombre_completo,email,rol) VALUES(?,?,?,?,?)")
                ->execute(['admin', $hashAdmin, $adminName, $adminEmail, 'ADMINISTRADOR']);
            $pdo->prepare("INSERT INTO system_users(username,password_hash,nombre_completo,email,rol) VALUES(?,?,?,?,?)")
                ->execute(['supervisor', $hashSuper, 'Supervisor Técnico TI', '', 'SUPERVISOR_TECNICO']);
            $pdo->prepare("INSERT INTO system_users(username,password_hash,nombre_completo,email,rol) VALUES(?,?,?,?,?)")
                ->execute(['usuario1', $hashUser, 'Usuario Demo', '', 'USUARIO']);

            // 5. Actualizar db.php con configuración real
            $dbContent = "<?php\ndefine('DB_HOST',    '$host');\ndefine('DB_NAME',    '$dbname');\ndefine('DB_USER',    '$user');\ndefine('DB_PASS',    '".addslashes($pass)."');\ndefine('DB_CHARSET', 'utf8mb4');\ndefine('APP_NAME',   '".addslashes($appName)."');\ndefine('APP_VERSION','1.0');\n\nfunction getDB(): PDO {\n    static \$pdo = null;\n    if (\$pdo === null) {\n        \$dsn = \"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME.\";charset=\".DB_CHARSET;\n        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n            PDO::ATTR_EMULATE_PREPARES   => false,\n        ]);\n    }\n    return \$pdo;\n}\n";
            file_put_contents(__DIR__.'/includes/db.php', $dbContent);

            // 6. Crear lock file
            file_put_contents($lockFile, date('Y-m-d H:i:s').' — Instalado por '.$_SERVER['REMOTE_ADDR']);

            $success[] = 'Base de datos configurada correctamente.';
            $success[] = 'Cuentas de sistema creadas.';
            $success[] = 'Configuración guardada en includes/db.php';
            $step = 3; // Éxito

        } catch (PDOException $e) {
            $errors[] = 'Error al instalar: '.$e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalación — Sistema de Gestión TI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* { font-family:'Inter',sans-serif; }
body { background:linear-gradient(135deg,#0f1e2d,#1a3a5c); min-height:100vh; padding:30px 16px; }
.install-card { max-width:640px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.4); overflow:hidden; }
.install-header { background:linear-gradient(135deg,#1a3a5c,#2563a8); color:#fff; padding:28px 32px; }
.install-header h4 { margin:0; font-weight:700; font-size:1.2rem; }
.install-body { padding:28px 32px; }
.step-badge { background:rgba(255,255,255,.2); border-radius:20px; padding:3px 12px; font-size:.75rem; }
.form-label { font-size:.82rem; font-weight:600; color:#444; }
.section-title { font-size:.75rem; text-transform:uppercase; letter-spacing:1px; color:#1a3a5c; font-weight:700; border-bottom:2px solid #e9ecef; padding-bottom:4px; margin-bottom:14px; }
</style>
</head>
<body>
<div class="install-card">
  <div class="install-header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <i class="bi bi-hdd-network-fill me-2 fs-4"></i>
        <h4 class="d-inline">Sistema de Gestión TI</h4>
      </div>
      <span class="step-badge">
        <?= $step < 3 ? 'Instalación' : '✓ Completado' ?>
      </span>
    </div>
  </div>
  <div class="install-body">

  <?php if ($step < 3): ?>
  <!-- ── Formulario de instalación ── -->
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="install.php">
    <!-- BD -->
    <div class="section-title mb-2 mt-1">🗄 Configuración de Base de Datos</div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label">Host MySQL</label>
        <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Nombre de la Base de Datos</label>
        <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? 'gestion_ti') ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Usuario MySQL</label>
        <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Contraseña MySQL <span class="text-muted small">(puede estar vacía)</span></label>
        <input type="password" name="db_pass" class="form-control" value="">
      </div>
    </div>

    <!-- App -->
    <div class="section-title mb-2">⚙️ Configuración de la Aplicación</div>
    <div class="row g-3 mb-4">
      <div class="col-12">
        <label class="form-label">Nombre del Sistema</label>
        <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($_POST['app_name'] ?? 'Gestión TI') ?>">
      </div>
    </div>

    <!-- Cuentas -->
    <div class="section-title mb-2">👤 Cuentas del Sistema</div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label">Nombre del Administrador</label>
        <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador del Sistema') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email del Administrador</label>
        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label"><span class="badge" style="background:#1a3a5c;font-size:.65rem;">ADMIN</span> admin — Contraseña</label>
        <input type="text" name="admin_pass" class="form-control" value="admin123" required minlength="6">
      </div>
      <div class="col-md-4">
        <label class="form-label"><span class="badge bg-warning text-dark" style="font-size:.65rem;">SUPERVISOR</span> supervisor — Contraseña</label>
        <input type="text" name="super_pass" class="form-control" value="super123" required minlength="6">
      </div>
      <div class="col-md-4">
        <label class="form-label"><span class="badge bg-secondary" style="font-size:.65rem;">USUARIO</span> usuario1 — Contraseña</label>
        <input type="text" name="user_pass" class="form-control" value="usuario123" required minlength="6">
      </div>
    </div>

    <div class="d-grid">
      <button type="submit" id="btn-install" class="btn btn-lg" style="background:#1a3a5c;color:#fff;font-weight:600;">
        <i class="bi bi-play-circle me-2"></i>Instalar Sistema
      </button>
    </div>
  </form>
  <script>
  document.querySelector('form').addEventListener('submit', function(e) {
    // Mostrar loading solo si no hay errores HTML5
    if (this.checkValidity()) {
      document.getElementById('btn-install').disabled = true;
      Swal.fire({
        title: 'Instalando…',
        html: 'Creando base de datos y configurando el sistema.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });
    }
  });
  </script>

  <?php else: ?>
  <!-- ── Éxito ── -->
  <div class="text-center py-2">
    <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem;"></i>
    <h4 class="mt-3 mb-1">¡Instalación completada!</h4>
    <p class="text-muted small mb-3">El sistema está listo para usarse.</p>
  </div>
  <?php foreach ($success as $s): ?>
  <div class="alert alert-success py-2 small"><i class="bi bi-check me-1"></i><?= htmlspecialchars($s) ?></div>
  <?php endforeach; ?>
  <div class="alert alert-warning py-2 small mt-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Importante:</strong> El archivo <code>install.php</code> ha sido bloqueado automáticamente. No es necesario eliminarlo manualmente.
  </div>
  <div class="d-grid mt-3">
    <a href="login.php" class="btn btn-lg" style="background:#1a3a5c;color:#fff;font-weight:600;">
      <i class="bi bi-box-arrow-in-right me-2"></i>Ir al Login
    </a>
  </div>
  <?php endif; ?>

  </div>
</div>
</body>
</html>
