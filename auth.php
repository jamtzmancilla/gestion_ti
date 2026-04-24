<?php
// ============================================================
//  includes/auth.php
//  Autenticación, sesión y control de acceso por rol
// ============================================================
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Roles ────────────────────────────────────────────────
define('ROL_ADMIN',  'ADMINISTRADOR');
define('ROL_SUPER',  'SUPERVISOR_TECNICO');
define('ROL_USER',   'USUARIO');

// ════════════════════════════════════════════════════════
//  SESIÓN
// ════════════════════════════════════════════════════════
function authLogin(string $username, string $password): array|false {
    $db = getDB();
    $s  = $db->prepare("SELECT * FROM system_users WHERE username=? AND activo=1 LIMIT 1");
    $s->execute([trim($username)]);
    $su = $s->fetch();
    if (!$su) return false;
    if (!password_verify($password, $su['password_hash'])) return false;
    // Actualizar último acceso
    $db->prepare("UPDATE system_users SET ultimo_acceso=NOW() WHERE id=?")->execute([$su['id']]);
    // Guardar sesión
    $_SESSION['auth'] = [
        'id'             => $su['id'],
        'username'       => $su['username'],
        'nombre'         => $su['nombre_completo'],
        'rol'            => $su['rol'],
        'usuario_id'     => $su['usuario_id'],
        'email'          => $su['email'],
    ];
    auditLog('LOGIN', 'auth', $su['id'], 'Inicio de sesión exitoso');
    return $_SESSION['auth'];
}

function authLogout(): void {
    auditLog('LOGOUT', 'auth', $_SESSION['auth']['id'] ?? null, 'Cierre de sesión');
    session_unset();
    session_destroy();
}

function authCheck(): array {
    if (empty($_SESSION['auth'])) {
        redirect('login.php');
    }
    return $_SESSION['auth'];
}

function authUser(): ?array {
    return $_SESSION['auth'] ?? null;
}

function isAdmin():  bool { return ($_SESSION['auth']['rol'] ?? '') === ROL_ADMIN; }
function isSuper():  bool { return ($_SESSION['auth']['rol'] ?? '') === ROL_SUPER; }
function isNormal(): bool { return ($_SESSION['auth']['rol'] ?? '') === ROL_USER; }
function rolActual(): string { return $_SESSION['auth']['rol'] ?? ''; }
function suserIdActual(): int { return (int)($_SESSION['auth']['id'] ?? 0); }
function usuarioIdActual(): ?int { return ($_SESSION['auth']['usuario_id']) ? (int)$_SESSION['auth']['usuario_id'] : null; }

// Requiere rol específico o redirige
function requireRol(string ...$roles): void {
    $auth = authCheck();
    if (!in_array($auth['rol'], $roles)) {
        flash('danger', 'No tienes permiso para acceder a esa sección.');
        redirect('index.php');
    }
}

// ════════════════════════════════════════════════════════
//  PERMISOS POR MÓDULO
// ════════════════════════════════════════════════════════
function canDo(string $accion): bool {
    $rol = rolActual();
    $permisos = [
        // ── Administrador: acceso total ──
        ROL_ADMIN => [
            'ver_dashboard','ver_equipos','crear_equipo','editar_equipo','eliminar_equipo',
            'ver_usuarios','crear_usuario','editar_usuario','eliminar_usuario',
            'ver_inventario','crear_inventario','editar_inventario','eliminar_inventario',
            'ver_tickets','crear_ticket','editar_ticket','eliminar_ticket',
            'asignar_ticket',           // SOLO ADMIN puede asignar tickets a supervisores
            'ver_mantenimiento','crear_mantenimiento','editar_mantenimiento','eliminar_mantenimiento',
            'aprobar_cambios',          // SOLO ADMIN aprueba cambios del supervisor
            'ver_auditoria',
            'ver_areas','crear_area','editar_area','eliminar_area',
            'ver_system_users','crear_system_user','editar_system_user','eliminar_system_user',
            'exportar',
        ],
        // ── Supervisor Técnico ──
        ROL_SUPER => [
            'ver_dashboard',
            'ver_equipos','editar_equipo',           // puede editar pero queda pendiente
            'ver_inventario','editar_inventario',
            'ver_tickets','editar_ticket',            // edita pero queda pendiente aprobación
            'crear_mantenimiento','ver_mantenimiento','editar_mantenimiento',
            'exportar',
        ],
        // ── Usuario Normal ──
        ROL_USER  => [
            'ver_dashboard',
            'ver_mis_equipos',          // solo sus equipos
            'ver_tickets_propios',
            'crear_ticket_propio',      // puede solicitar ticket al admin
        ],
    ];
    return in_array($accion, $permisos[$rol] ?? []);
}

// Helper para vistas: no redirige, solo retorna bool
function puede(string $accion): bool { return canDo($accion); }

// ════════════════════════════════════════════════════════
//  AUDITORÍA
// ════════════════════════════════════════════════════════
function auditLog(string $accion, string $modulo='', ?int $registroId=null, string $desc=''): void {
    try {
        $suid = (int)($_SESSION['auth']['id'] ?? 0) ?: null;
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        getDB()->prepare(
            "INSERT INTO auditoria(suser_id,accion,modulo,registro_id,descripcion,ip_address) VALUES(?,?,?,?,?,?)"
        )->execute([$suid, $accion, $modulo, $registroId, $desc, $ip]);
    } catch (\Throwable) { /* silenciar para no romper flujo */ }
}

// ════════════════════════════════════════════════════════
//  CAMBIOS PENDIENTES (Supervisor → Admin)
// ════════════════════════════════════════════════════════
function guardarCambioPendiente(string $modulo, int $registroId, string $accion, array $datos, string $desc=''): int {
    $suid = suserIdActual();
    getDB()->prepare(
        "INSERT INTO cambios_pendientes(suser_id,modulo,registro_id,accion,datos_json,descripcion)VALUES(?,?,?,?,?,?)"
    )->execute([$suid, $modulo, $registroId, $accion, json_encode($datos, JSON_UNESCAPED_UNICODE), $desc]);
    return (int)getDB()->lastInsertId();
}

function getCambiosPendientes(string $estado='PENDIENTE'): array {
    $s = getDB()->prepare(
        "SELECT cp.*, su.nombre_completo supervisor_nombre, a.nombre_completo admin_nombre
         FROM cambios_pendientes cp
         LEFT JOIN system_users su ON su.id=cp.suser_id
         LEFT JOIN system_users a  ON a.id=cp.admin_id
         WHERE cp.estado=?
         ORDER BY cp.created_at DESC"
    );
    $s->execute([$estado]);
    return $s->fetchAll();
}

function getCambioPendienteById(int $id): array|false {
    $s = getDB()->prepare("SELECT cp.*, su.nombre_completo supervisor_nombre FROM cambios_pendientes cp LEFT JOIN system_users su ON su.id=cp.suser_id WHERE cp.id=?");
    $s->execute([$id]); return $s->fetch();
}

function resolverCambio(int $id, string $decision, string $nota=''): void {
    $cp = getCambioPendienteById($id);
    if (!$cp || $cp['estado'] !== 'PENDIENTE') return;
    $adminId = suserIdActual();
    getDB()->prepare(
        "UPDATE cambios_pendientes SET estado=?,admin_id=?,admin_nota=?,fecha_resolucion=NOW() WHERE id=?"
    )->execute([$decision, $adminId, $nota, $id]);

    if ($decision === 'APROBADO') {
        $datos = json_decode($cp['datos_json'], true);
        aplicarCambioAprobado($cp['modulo'], (int)$cp['registro_id'], $cp['accion'], $datos);
    }
    auditLog('CAMBIO_'.$decision, $cp['modulo'], $cp['registro_id'], "Cambio #$id ".strtolower($decision));
}

function aplicarCambioAprobado(string $modulo, int $id, string $accion, array $datos): void {
    switch ($modulo) {
        case 'tickets':
            saveTicket($datos, $id); break;
        case 'equipos':
            saveEquipo($datos, $id); break;
        case 'inventario':
            saveInventario($datos, $id); break;
        case 'mantenimientos':
            $checks = $datos['_checks'] ?? [];
            unset($datos['_checks']);
            saveMantenimiento($datos, $checks, $id); break;
    }
}

// ════════════════════════════════════════════════════════
//  SYSTEM USERS (CRUD)
// ════════════════════════════════════════════════════════
function getSystemUsers(): array {
    return getDB()->query(
        "SELECT su.*, u.nombre usuario_vinculado_nombre
         FROM system_users su
         LEFT JOIN usuarios u ON u.id=su.usuario_id
         ORDER BY FIELD(su.rol,'ADMINISTRADOR','SUPERVISOR_TECNICO','USUARIO'), su.nombre_completo"
    )->fetchAll();
}

function getSystemUserById(int $id): array|false {
    $s = getDB()->prepare("SELECT * FROM system_users WHERE id=?");
    $s->execute([$id]); return $s->fetch();
}

function getSupervisores(): array {
    return getDB()->query(
        "SELECT id, nombre_completo, username FROM system_users WHERE rol='SUPERVISOR_TECNICO' AND activo=1 ORDER BY nombre_completo"
    )->fetchAll();
}

function saveSystemUser(array $d, ?int $id=null): int {
    $db = getDB();
    $nombre   = trim($d['nombre_completo'] ?? '');
    $username = trim($d['username'] ?? '');
    $email    = trim($d['email'] ?? '');
    $rol      = $d['rol'] ?? ROL_USER;
    $usuarioId= ($d['usuario_id'] ?? null) ?: null;
    $activo   = (int)($d['activo'] ?? 1);

    if ($id) {
        $sql = "UPDATE system_users SET nombre_completo=?,username=?,email=?,rol=?,usuario_id=?,activo=?";
        $params = [$nombre, $username, $email, $rol, $usuarioId, $activo];
        if (!empty($d['password']) && strlen(trim($d['password'])) >= 6) {
            $sql .= ',password_hash=?';
            $params[] = password_hash(trim($d['password']), PASSWORD_BCRYPT, ['cost'=>12]);
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;
        $db->prepare($sql)->execute($params);
        return $id;
    }
    // Nuevo usuario
    $hash = password_hash(trim($d['password'] ?? 'changeme123'), PASSWORD_BCRYPT, ['cost'=>12]);
    $db->prepare(
        "INSERT INTO system_users(nombre_completo,username,email,rol,usuario_id,activo,password_hash)VALUES(?,?,?,?,?,?,?)"
    )->execute([$nombre, $username, $email, $rol, $usuarioId, $activo, $hash]);
    return (int)$db->lastInsertId();
}

function deleteSystemUser(int $id): void {
    // No eliminar físicamente, solo desactivar
    getDB()->prepare("UPDATE system_users SET activo=0 WHERE id=?")->execute([$id]);
}

// ════════════════════════════════════════════════════════
//  EQUIPOS DEL USUARIO NORMAL (solo los suyos)
// ════════════════════════════════════════════════════════
function getMisEquipos(): array {
    $usuarioId = usuarioIdActual();
    if (!$usuarioId) return [];
    return getEquipos(area:0, tipo:0, estado:'', q:'');
    // Filtrado real:
    $s = getDB()->prepare(
        "SELECT e.*,t.nombre tipo_nombre,t.icono tipo_icono,a.nombre area_nombre
         FROM equipos e
         LEFT JOIN tipos_equipo t ON t.id=e.tipo_id
         LEFT JOIN areas a ON a.id=e.area_id
         WHERE e.usuario_id=? AND e.activo=1
         ORDER BY e.folio"
    );
    $s->execute([$usuarioId]);
    return $s->fetchAll();
}

function getMisTickets(): array {
    $suid = suserIdActual();
    $s = getDB()->prepare(
        "SELECT t.*,e.folio equipo_folio,e.marca equipo_marca,e.modelo equipo_modelo
         FROM tickets t
         LEFT JOIN equipos e ON e.id=t.equipo_id
         WHERE t.solicitante_suser=?
         ORDER BY FIELD(t.prioridad,'URGENTE','ALTA','MEDIA','BAJA'), t.fecha_apertura DESC"
    );
    $s->execute([$suid]);
    return $s->fetchAll();
}

function getMisTicketsComoSupervisor(): array {
    $suid = suserIdActual();
    $s = getDB()->prepare(
        "SELECT t.*,e.folio equipo_folio,e.marca equipo_marca,e.modelo equipo_modelo,
                CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre
         FROM tickets t
         LEFT JOIN equipos e ON e.id=t.equipo_id
         LEFT JOIN usuarios u ON u.id=t.usuario_id
         WHERE t.asignado_a_suser=?
         ORDER BY FIELD(t.prioridad,'URGENTE','ALTA','MEDIA','BAJA'), t.fecha_apertura DESC"
    );
    $s->execute([$suid]);
    return $s->fetchAll();
}

// ════════════════════════════════════════════════════════
//  HELPERS UI
// ════════════════════════════════════════════════════════
function rolBadge(string $rol): string {
    return match($rol) {
        ROL_ADMIN => '<span class="badge" style="background:#1a3a5c;">ADMINISTRADOR</span>',
        ROL_SUPER => '<span class="badge bg-warning text-dark">SUPERVISOR TI</span>',
        ROL_USER  => '<span class="badge bg-secondary">USUARIO</span>',
        default   => '<span class="badge bg-light text-dark">'.h($rol).'</span>',
    };
}

function pendingCount(): int {
    if (!isAdmin()) return 0;
    return (int)getDB()->query("SELECT COUNT(*) FROM cambios_pendientes WHERE estado='PENDIENTE'")->fetchColumn();
}
