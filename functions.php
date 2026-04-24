<?php
require_once __DIR__.'/db.php';

// ── Generic ───────────────────────────────────────────────
function h($v): string { return htmlspecialchars((string)($v??''), ENT_QUOTES,'UTF-8'); }
function redirect(string $u): never { header("Location: $u"); exit; }
function jsonOut($d, int $c=200): never { http_response_code($c); header('Content-Type:application/json;charset=utf-8'); echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }

// ── Flash messages (session) ──────────────────────────────
function flash(string $type, string $msg): void { $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function getFlash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }

// ── Folio auto ────────────────────────────────────────────
function nextFolioEquipo(): string {
    $n = getDB()->query("SELECT COUNT(*)+1 AS n FROM equipos")->fetchColumn();
    return 'EQ-'.str_pad($n,4,'0',STR_PAD_LEFT);
}
function nextFolioTicket(): string {
    $n = getDB()->query("SELECT COUNT(*)+1 AS n FROM tickets")->fetchColumn();
    return 'TKT-'.date('Y').'-'.str_pad($n,4,'0',STR_PAD_LEFT);
}

// ════════════════════════════════════════════════════════
//  ÁREAS
// ════════════════════════════════════════════════════════
function getAreas(): array { return getDB()->query("SELECT * FROM areas WHERE activo=1 ORDER BY nombre")->fetchAll(); }
function getAreaById(int $id) { $s=getDB()->prepare("SELECT * FROM areas WHERE id=?"); $s->execute([$id]); return $s->fetch(); }
function saveArea(array $d, ?int $id=null): void {
    $db=getDB();
    if($id){ $db->prepare("UPDATE areas SET nombre=?,descripcion=? WHERE id=?")->execute([$d['nombre'],$d['descripcion'],$id]); }
    else    { $db->prepare("INSERT INTO areas(nombre,descripcion)VALUES(?,?)")->execute([$d['nombre'],$d['descripcion']]); }
}
function deleteArea(int $id): void { getDB()->prepare("DELETE FROM areas WHERE id=?")->execute([$id]); }

// ════════════════════════════════════════════════════════
//  USUARIOS
// ════════════════════════════════════════════════════════
function getUsuarios(string $q=''): array {
    $db=getDB(); $like='%'.$q.'%';
    $sql = "SELECT u.*, a.nombre area_nombre, p.nombre puesto_nombre, p.nivel puesto_nivel
            FROM usuarios u
            LEFT JOIN areas a ON a.id=u.area_id
            LEFT JOIN cat_puestos p ON p.id=u.puesto_id
            WHERE u.activo=1";
    if($q){
        $sql .= " AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ?
                       OR u.usuario_windows LIKE ? OR u.extension_ip LIKE ?
                       OR u.cubículo LIKE ? OR u.oficina LIKE ?)";
        $s=$db->prepare($sql." ORDER BY u.nombre");
        $s->execute([$like,$like,$like,$like,$like,$like,$like]);
    } else {
        $s=$db->query($sql." ORDER BY a.nombre, u.nombre");
    }
    return $s->fetchAll();
}

function getUsuarioById(int $id) {
    $s=getDB()->prepare(
        "SELECT u.*, a.nombre area_nombre, p.nombre puesto_nombre, p.nivel puesto_nivel
         FROM usuarios u
         LEFT JOIN areas a ON a.id=u.area_id
         LEFT JOIN cat_puestos p ON p.id=u.puesto_id
         WHERE u.id=?"
    );
    $s->execute([$id]); return $s->fetch();
}

function saveUsuario(array $d, ?int $id=null): int {
    $db=getDB();
    $f=['area_id','puesto_id','nombre','apellidos','email','telefono','puesto',
        'usuario_windows','contrasena_ref','cubículo','oficina','extension_ip','telefono_directo'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null, $f);
    if($id){
        $set=implode(',',array_map(fn($k)=>"$k=?",$f));
        $db->prepare("UPDATE usuarios SET $set WHERE id=?")->execute([...$v,$id]);
        return $id;
    }
    $cols=implode(',',$f);
    $ph=implode(',',array_fill(0,count($f),'?'));
    $db->prepare("INSERT INTO usuarios($cols) VALUES($ph)")->execute($v);
    return (int)$db->lastInsertId();
}

function deleteUsuario(int $id): void { getDB()->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$id]); }

// ════════════════════════════════════════════════════════
//  CATÁLOGO DE PUESTOS
// ════════════════════════════════════════════════════════
function getPuestos(): array {
    return getDB()->query(
        "SELECT * FROM cat_puestos WHERE activo=1
         ORDER BY FIELD(nivel,'DIRECTIVO','SUBDIRECTIVO','JEFATURA','COORDINACION','OPERATIVO','APOYO'), nombre"
    )->fetchAll();
}
function getPuestoById(int $id) {
    $s=getDB()->prepare("SELECT * FROM cat_puestos WHERE id=?");
    $s->execute([$id]); return $s->fetch();
}
function savePuesto(array $d, ?int $id=null): int {
    $db=getDB();
    if($id){
        $db->prepare("UPDATE cat_puestos SET nombre=?,descripcion=?,nivel=? WHERE id=?")
           ->execute([trim($d['nombre']),trim($d['descripcion']??''),$d['nivel'],$id]);
        return $id;
    }
    $db->prepare("INSERT INTO cat_puestos(nombre,descripcion,nivel)VALUES(?,?,?)")
       ->execute([trim($d['nombre']),trim($d['descripcion']??''),$d['nivel']??'OPERATIVO']);
    return (int)$db->lastInsertId();
}
function deletePuesto(int $id): void {
    getDB()->prepare("UPDATE cat_puestos SET activo=0 WHERE id=?")->execute([$id]);
}

// ════════════════════════════════════════════════════════
//  DIRECTORIO TELEFÓNICO IP
// ════════════════════════════════════════════════════════
function getDirectorio(string $q='', int $areaId=0): array {
    $db=getDB(); $where=['d.activo=1']; $params=[];
    if($areaId){ $where[]='d.area_id=?'; $params[]=$areaId; }
    if($q){
        $like='%'.$q.'%';
        $where[]="(d.extension LIKE ? OR d.nombre_completo LIKE ? OR d.puesto_cargo LIKE ?
                   OR d.telefono_directo LIKE ? OR d.ubicacion LIKE ? OR a.nombre LIKE ?)";
        array_push($params,$like,$like,$like,$like,$like,$like);
    }
    $w=implode(' AND ',$where);
    $s=$db->prepare(
        "SELECT d.*, a.nombre area_nombre, u.nombre usuario_nombre, u.apellidos usuario_apellidos
         FROM directorio d
         JOIN areas a ON a.id=d.area_id
         LEFT JOIN usuarios u ON u.id=d.usuario_id
         WHERE $w ORDER BY a.nombre, d.orden, d.id"
    );
    $s->execute($params); return $s->fetchAll();
}

function getDirectorioById(int $id) {
    $s=getDB()->prepare("SELECT d.*, a.nombre area_nombre FROM directorio d JOIN areas a ON a.id=d.area_id WHERE d.id=?");
    $s->execute([$id]); return $s->fetch();
}

function getDirectorioAgrupado(string $q='', int $areaId=0): array {
    $rows = getDirectorio($q, $areaId);
    $grouped = [];
    foreach($rows as $r){
        $grouped[$r['area_nombre']][] = $r;
    }
    return $grouped;
}

function saveDirectorio(array $d, ?int $id=null): int {
    $db=getDB();
    $f=['area_id','usuario_id','extension','nombre_completo','puesto_cargo',
        'telefono_directo','ubicacion','notas','color_area','orden'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null,$f);
    if($id){
        $set=implode(',',array_map(fn($k)=>"$k=?",$f));
        $db->prepare("UPDATE directorio SET $set WHERE id=?")->execute([...$v,$id]);
        return $id;
    }
    $cols=implode(',',$f); $ph=implode(',',array_fill(0,count($f),'?'));
    $db->prepare("INSERT INTO directorio($cols)VALUES($ph)")->execute($v);
    return (int)$db->lastInsertId();
}

function deleteDirectorio(int $id): void {
    getDB()->prepare("UPDATE directorio SET activo=0 WHERE id=?")->execute([$id]);
}

// ════════════════════════════════════════════════════════
//  TIPOS EQUIPO
// ════════════════════════════════════════════════════════
function getTiposEquipo(): array { return getDB()->query("SELECT * FROM tipos_equipo ORDER BY nombre")->fetchAll(); }

// ════════════════════════════════════════════════════════
//  EQUIPOS
// ════════════════════════════════════════════════════════
function getEquipos(string $q='', string $estado='', int $tipo=0, int $area=0): array {
    $db=getDB(); $where=['e.activo=1']; $params=[];
    if($q){ $like='%'.$q.'%'; $where[]="(e.folio LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.serie LIKE ? OR e.ip LIKE ? OR e.mac_address LIKE ? OR u.nombre LIKE ?)"; $params=array_merge($params,array_fill(0,7,$like)); }
    if($estado){ $where[]="e.estado=?"; $params[]=$estado; }
    if($tipo)  { $where[]="e.tipo_id=?"; $params[]=$tipo; }
    if($area)  { $where[]="e.area_id=?"; $params[]=$area; }
    $w=implode(' AND ',$where);
    $s=$db->prepare("SELECT e.*,t.nombre tipo_nombre,t.icono tipo_icono,a.nombre area_nombre,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id LEFT JOIN areas a ON a.id=e.area_id LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE $w ORDER BY e.folio");
    $s->execute($params); return $s->fetchAll();
}
function getEquipoById(int $id) {
    $s=getDB()->prepare("SELECT e.*,t.nombre tipo_nombre,a.nombre area_nombre,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id LEFT JOIN areas a ON a.id=e.area_id LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE e.id=?");
    $s->execute([$id]); return $s->fetch();
}
function saveEquipo(array $d, ?int $id=null): int {
    $db=getDB();
    $f=['tipo_id','area_id','usuario_id','folio','marca','modelo','serie','descripcion','ip','mac_address','nodo','conexion_medio','ubicacion','estado','fecha_adquisicion'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null,$f);
    if($id){ $set=implode(',',array_map(fn($k)=>"$k=?",$f)); $db->prepare("UPDATE equipos SET $set WHERE id=?")->execute([...$v,$id]); return $id; }
    $db->prepare("INSERT INTO equipos(".implode(',',$f).")VALUES(".implode(',',array_fill(0,count($f),'?')).")")->execute($v);
    return (int)$db->lastInsertId();
}
function deleteEquipo(int $id): void { getDB()->prepare("UPDATE equipos SET activo=0 WHERE id=?")->execute([$id]); }
function getResumenEquipos(): array {
    return getDB()->query("SELECT t.nombre tipo, COUNT(e.id) total, SUM(e.estado='ACTIVO') activos, SUM(e.estado='MANTENIMIENTO') mantenimiento, SUM(e.estado='BAJA') bajas FROM equipos e JOIN tipos_equipo t ON t.id=e.tipo_id WHERE e.activo=1 GROUP BY t.id ORDER BY total DESC")->fetchAll();
}
function getIpLibres(): int {
    $used=getDB()->query("SELECT COUNT(DISTINCT ip) FROM equipos WHERE ip IS NOT NULL AND ip!='' AND activo=1")->fetchColumn();
    return max(0, 254 - (int)$used);
}

// ════════════════════════════════════════════════════════
//  INVENTARIO
// ════════════════════════════════════════════════════════
function getInventario(string $q='', int $equipo=0, int $usuario=0): array {
    $db=getDB(); $where=['i.activo=1']; $params=[];
    if($q){ $like='%'.$q.'%'; $where[]="(i.tipo LIKE ? OR i.marca LIKE ? OR i.modelo LIKE ? OR i.serie LIKE ?)"; $params=array_merge($params,array_fill(0,4,$like)); }
    if($equipo) { $where[]="i.equipo_id=?"; $params[]=$equipo; }
    if($usuario){ $where[]="i.usuario_id=?"; $params[]=$usuario; }
    $w=implode(' AND ',$where);
    $s=$db->prepare("SELECT i.*,e.folio equipo_folio,e.marca equipo_marca,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre,a.nombre area_nombre FROM inventario i LEFT JOIN equipos e ON e.id=i.equipo_id LEFT JOIN usuarios u ON u.id=i.usuario_id LEFT JOIN areas a ON a.id=i.area_id WHERE $w ORDER BY i.tipo,i.marca");
    $s->execute($params); return $s->fetchAll();
}
function getInventarioById(int $id) { $s=getDB()->prepare("SELECT * FROM inventario WHERE id=?"); $s->execute([$id]); return $s->fetch(); }
function saveInventario(array $d, ?int $id=null): int {
    $db=getDB();
    $f=['equipo_id','usuario_id','area_id','tipo','marca','modelo','serie','cantidad','estado','notas'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null,$f);
    if($id){ $set=implode(',',array_map(fn($k)=>"$k=?",$f)); $db->prepare("UPDATE inventario SET $set WHERE id=?")->execute([...$v,$id]); return $id; }
    $db->prepare("INSERT INTO inventario(".implode(',',$f).")VALUES(".implode(',',array_fill(0,count($f),'?')).")")->execute($v);
    return (int)$db->lastInsertId();
}
function deleteInventario(int $id): void { getDB()->prepare("UPDATE inventario SET activo=0 WHERE id=?")->execute([$id]); }

// ════════════════════════════════════════════════════════
//  TICKETS
// ════════════════════════════════════════════════════════
function getTickets(string $q='', string $estado='', string $prioridad=''): array {
    $db=getDB(); $where=['1=1']; $params=[];
    if($q){ $like='%'.$q.'%'; $where[]="(t.folio_ticket LIKE ? OR e.folio LIKE ? OR e.marca LIKE ? OR CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) LIKE ? OR t.descripcion LIKE ? OR t.tecnico LIKE ?)"; $params=array_merge($params,array_fill(0,6,$like)); }
    if($estado)   { $where[]="t.estado=?";    $params[]=$estado; }
    if($prioridad){ $where[]="t.prioridad=?"; $params[]=$prioridad; }
    $w=implode(' AND ',$where);
    $s=$db->prepare("SELECT t.*,e.folio equipo_folio,e.marca equipo_marca,e.modelo equipo_modelo,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre FROM tickets t LEFT JOIN equipos e ON e.id=t.equipo_id LEFT JOIN usuarios u ON u.id=t.usuario_id WHERE $w ORDER BY FIELD(t.prioridad,'URGENTE','ALTA','MEDIA','BAJA'), t.fecha_apertura DESC");
    $s->execute($params); return $s->fetchAll();
}
function getTicketById(int $id) {
    $s=getDB()->prepare("SELECT t.*,e.folio equipo_folio,e.marca equipo_marca,e.modelo equipo_modelo,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre FROM tickets t LEFT JOIN equipos e ON e.id=t.equipo_id LEFT JOIN usuarios u ON u.id=t.usuario_id WHERE t.id=?");
    $s->execute([$id]); return $s->fetch();
}
// En functions.php - Línea aproximada 184
function saveTicket(array $d, ?int $id=null): int {
    $db=getDB();
    // Se añade 'asignado_a_suser' a la lista de campos permitidos
    $f=['equipo_id','usuario_id','tipo_servicio','prioridad','estado','descripcion','tecnico','fecha_cierre','asignado_a_suser'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null,$f);
    
    if($id){ 
        $set=implode(',',array_map(fn($k)=>"$k=?",$f)); 
        $db->prepare("UPDATE tickets SET $set WHERE id=?")->execute([...$v,$id]); 
        return $id; 
    }
    
    $folio=nextFolioTicket();
    // Se actualiza la consulta INSERT para incluir el nuevo campo
    $db->prepare("INSERT INTO tickets(folio_ticket,".implode(',',$f).")VALUES(?,".implode(',',array_fill(0,count($f),'?')).")")->execute([$folio,...$v]);
    return (int)$db->lastInsertId();
}

function deleteTicket(int $id): void { getDB()->prepare("DELETE FROM tickets WHERE id=?")->execute([$id]); }
function getEstadisticasTickets(): array {
    return getDB()->query("SELECT estado,COUNT(*) total FROM tickets GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
}
// ════════════════════════════════════════════════════════
//  TICKETS (ACTUALIZADO)
// ════════════════════════════════════════════════════════

/**
 * Obtiene los tickets asignados específicamente al técnico actual
 */
function getMisTicketsComoSupervisor(): array {
    $db = getDB();
    $suserId = suserIdActual(); // ID de la sesión de system_users
    
    $sql = "SELECT t.*, e.folio equipo_folio, e.marca equipo_marca, e.modelo equipo_modelo,
            CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre 
            FROM tickets t 
            LEFT JOIN equipos e ON e.id = t.equipo_id 
            LEFT JOIN usuarios u ON u.id = t.usuario_id 
            WHERE t.asignado_a_suser = ? 
            ORDER BY FIELD(t.prioridad,'URGENTE','ALTA','MEDIA','BAJA'), t.fecha_apertura DESC";
            
    $s = $db->prepare($sql);
    $s->execute([$suserId]);
    return $s->fetchAll();
}

/**
 * Guarda o actualiza un ticket incluyendo el vínculo con el supervisor
 */
function saveTicket(array $d, ?int $id=null): int {
    $db = getDB();
    // Campos que deben coincidir con tu DB
    $f = ['equipo_id','usuario_id','tipo_servicio','prioridad','estado','descripcion','tecnico','fecha_cierre','asignado_a_suser'];
    
    $v = array_map(fn($k) => ($d[$k] ?? null) ? trim($d[$k]) : null, $f);
    
    if($id) {
        $set = implode(',', array_map(fn($k) => "$k=?", $f));
        $db->prepare("UPDATE tickets SET $set WHERE id=?")->execute([...$v, $id]);
        return $id;
    }
    
    $folio = nextFolioTicket();
    $cols = implode(',', array_merge(['folio_ticket'], $f));
    $ph = implode(',', array_fill(0, count($f) + 1, '?'));
    
    $db->prepare("INSERT INTO tickets($cols) VALUES($ph)")->execute([$folio, ...$v]);
    return (int)$db->lastInsertId();
}

// ════════════════════════════════════════════════════════
//  MANTENIMIENTOS
// ════════════════════════════════════════════════════════
function getMantenimientos(int $equipo=0): array {
    $db=getDB(); $w=$equipo?"WHERE m.equipo_id=$equipo":"";
    return $db->query("SELECT m.*,e.folio equipo_folio,e.marca,e.modelo,CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre FROM mantenimientos m LEFT JOIN equipos e ON e.id=m.equipo_id LEFT JOIN usuarios u ON u.id=m.usuario_id $w ORDER BY m.fecha DESC")->fetchAll();
}
function getMantenimientoById(int $id) {
    $s=getDB()->prepare("SELECT m.*,e.folio equipo_folio,e.marca,e.modelo FROM mantenimientos m LEFT JOIN equipos e ON e.id=m.equipo_id WHERE m.id=?");
    $s->execute([$id]); return $s->fetch();
}
function saveMantenimiento(array $d, array $checks, ?int $id=null): int {
    $db=getDB();
    $f=['ticket_id','equipo_id','usuario_id','tecnico','tipo','fecha','observaciones'];
    $v=array_map(fn($k)=>($d[$k]??null)?trim($d[$k]):null,$f);
    if($id){
        $set=implode(',',array_map(fn($k)=>"$k=?",$f));
        $db->prepare("UPDATE mantenimientos SET $set WHERE id=?")->execute([...$v,$id]);
        $db->prepare("DELETE FROM mantenimiento_checks WHERE mantenimiento_id=?")->execute([$id]);
    } else {
        $db->prepare("INSERT INTO mantenimientos(".implode(',',$f).")VALUES(".implode(',',array_fill(0,count($f),'?')).")")->execute($v);
        $id=(int)$db->lastInsertId();
    }
    foreach($checks as $c){
        $db->prepare("INSERT INTO mantenimiento_checks(mantenimiento_id,categoria,item,resultado,notas)VALUES(?,?,?,?,?)")->execute([$id,$c['categoria'],$c['item'],$c['resultado'],$c['notas']??'']);
    }
    return $id;
}
function getChecks(int $mId): array {
    $s=getDB()->prepare("SELECT * FROM mantenimiento_checks WHERE mantenimiento_id=? ORDER BY categoria,id");
    $s->execute([$mId]); return $s->fetchAll();
}

// ────────────────────────────────────────────────────────
//  Template de checklist de mantenimiento
// ────────────────────────────────────────────────────────
function getChecklistTemplate(): array {
    return [
        'LIMPIEZA FÍSICA' => [
            'Limpieza exterior del equipo (carcasa/chasis)',
            'Limpieza de teclado y mouse',
            'Limpieza de monitor/pantalla',
            'Limpieza interna (CPU/ventiladores/slots)',
            'Limpieza de lector óptico/DVD',
            'Estado físico del cable de corriente',
            'Estado físico del cable de red/datos',
            'Revisión de puertos USB y conectores',
            'Revisión de estado del hardware (golpes/daños físicos)',
            'Ventiladores en buen estado y funcionando',
            'Memoria RAM en buen estado (sin corrosión)',
            'Disco duro/SSD sin daños físicos',
        ],
        'LIMPIEZA LÓGICA' => [
            'Actualización de Windows / SO',
            'Drivers actualizados',
            'Punto de recuperación creado',
            'Eliminación de programas innecesarios',
            'CCleaner / limpieza de temporales',
            'Temporales del sistema eliminados',
            'Programa de optimización ejecutado',
            'Espacio en disco suficiente (>15%)',
            'Windows activo/licenciado',
            'Office 2013/365 activo',
            'Antivirus actualizado (SKE/SE)',
            'Escaneo de virus realizado',
            'DigiScan ejecutado',
            'AAMY/Anydesk instalado y configurado',
            'Bitlocker/cifrado estado revisado',
        ],
        'HARDWARE / PERIFÉRICOS' => [
            'Monitor encendiendo correctamente',
            'Teclado funcionando',
            'CPU interno funcionando',
            'Ventiladores correcto funcionamiento',
            'USB/FDD no bloqueado',
            'Cables en buen estado',
            'Memoria RAM detectada correctamente',
            'Periféricos detectados por el SO',
            'Red/Ethernet funcionando',
            'Sonido funcionando',
            'Cámara web funcionando (si aplica)',
        ],
        'SEGURIDAD / RED' => [
            'IP asignada correctamente según inventario',
            'MAC registrada en base de datos',
            'Pertenece al nodo/switch correcto',
            'Firewall activo',
            'Acceso remoto verificado',
            'Usuario de Windows configurado correctamente',
            'Contraseña de usuario vigente',
            'Restricciones de USB configuradas',
        ],
    ];
}

// ════════════════════════════════════════════════════════
//  ESTADÍSTICAS DASHBOARD
// ════════════════════════════════════════════════════════
function getDashboardStats(): array {
    $db=getDB();
    return [
        'equipos_total'       => $db->query("SELECT COUNT(*) FROM equipos WHERE activo=1")->fetchColumn(),
        'equipos_activos'     => $db->query("SELECT COUNT(*) FROM equipos WHERE activo=1 AND estado='ACTIVO'")->fetchColumn(),
        'equipos_mant'        => $db->query("SELECT COUNT(*) FROM equipos WHERE activo=1 AND estado='MANTENIMIENTO'")->fetchColumn(),
        'usuarios_total'      => $db->query("SELECT COUNT(*) FROM usuarios WHERE activo=1")->fetchColumn(),
        'tickets_abiertos'    => $db->query("SELECT COUNT(*) FROM tickets WHERE estado='ABIERTO'")->fetchColumn(),
        'tickets_proceso'     => $db->query("SELECT COUNT(*) FROM tickets WHERE estado='EN_PROCESO'")->fetchColumn(),
        'inventario_total'    => $db->query("SELECT COALESCE(SUM(cantidad),0) FROM inventario WHERE activo=1")->fetchColumn(),
        'mantenimientos_mes'  => $db->query("SELECT COUNT(*) FROM mantenimientos WHERE MONTH(fecha)=MONTH(NOW()) AND YEAR(fecha)=YEAR(NOW())")->fetchColumn(),
        'ip_libres'           => getIpLibres(),
        'resumen_equipos'     => getResumenEquipos(),
        'tickets_stats'       => getEstadisticasTickets(),
    ];
}
// --- AGREGAR ESTA FUNCIÓN AL FINAL DE functions.php ---
function getMisTicketsComoSupervisor(): array {
    $db = getDB();
    $suserId = suserIdActual(); // Obtiene el ID del técnico logueado
    $s = $db->prepare("SELECT t.*, e.folio equipo_folio, e.marca equipo_marca, 
                       CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre 
                       FROM tickets t 
                       LEFT JOIN equipos e ON e.id = t.equipo_id 
                       LEFT JOIN usuarios u ON u.id = t.usuario_id 
                       WHERE t.asignado_a_suser = ? 
                       ORDER BY FIELD(t.prioridad,'URGENTE','ALTA','MEDIA','BAJA'), t.fecha_apertura DESC");
    $s->execute([$suserId]);
    return $s->fetchAll();
}

