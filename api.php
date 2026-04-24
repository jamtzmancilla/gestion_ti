<?php
require_once 'includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'next_folio_equipo':
        echo json_encode(['folio' => nextFolioEquipo()]);
        break;

    case 'next_folio_ticket':
        echo json_encode(['folio' => nextFolioTicket()]);
        break;

    case 'equipos_by_area':
        $areaId = (int)($_GET['area_id'] ?? 0);
        $equipos = $areaId ? getEquipos(area: $areaId) : getEquipos();
        echo json_encode($equipos);
        break;

    case 'usuarios_by_area':
        $areaId = (int)($_GET['area_id'] ?? 0);
        $db = getDB();
        if ($areaId) {
            $s = $db->prepare("SELECT id, CONCAT(nombre,' ',IFNULL(apellidos,'')) AS nombre_completo FROM usuarios WHERE area_id=? AND activo=1 ORDER BY nombre");
            $s->execute([$areaId]);
        } else {
            $s = $db->query("SELECT id, CONCAT(nombre,' ',IFNULL(apellidos,'')) AS nombre_completo FROM usuarios WHERE activo=1 ORDER BY nombre");
        }
        echo json_encode($s->fetchAll());
        break;

    case 'equipo_info':
        $eqId = (int)($_GET['equipo_id'] ?? 0);
        $eq = $eqId ? getEquipoById($eqId) : null;
        echo json_encode($eq ?: new stdClass());
        break;

    case 'dashboard_stats':
        echo json_encode(getDashboardStats());
        break;

    case 'search_ip':
        $ip = trim($_GET['ip'] ?? '');
        if (!$ip) { echo json_encode([]); break; }
        $s = getDB()->prepare("SELECT e.*, t.nombre tipo_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id WHERE e.ip LIKE ? AND e.activo=1 LIMIT 10");
        $s->execute(['%'.$ip.'%']);
        echo json_encode($s->fetchAll());
        break;

    case 'search_mac':
        $mac = trim($_GET['mac'] ?? '');
        if (!$mac) { echo json_encode([]); break; }
        $s = getDB()->prepare("SELECT e.*, t.nombre tipo_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id WHERE e.mac_address LIKE ? AND e.activo=1 LIMIT 10");
        $s->execute(['%'.$mac.'%']);
        echo json_encode($s->fetchAll());
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}
