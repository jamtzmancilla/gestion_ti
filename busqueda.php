<?php
require_once 'includes/auth.php';
$pageTitle = 'Búsqueda Global';
$q = trim($_GET['q'] ?? '');
include 'includes/header.php';

$resultados = ['equipos'=>[], 'usuarios'=>[], 'tickets'=>[], 'inventario'=>[]];
$total = 0;

if ($q && strlen($q) >= 2) {
    $db   = getDB();
    $like = '%'.$q.'%';

    // Equipos
    if (puede('ver_equipos')) {
        $s = $db->prepare("SELECT e.*,t.nombre tipo_nombre,t.icono tipo_icono,a.nombre area_nombre,
            CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre
            FROM equipos e
            LEFT JOIN tipos_equipo t ON t.id=e.tipo_id
            LEFT JOIN areas a ON a.id=e.area_id
            LEFT JOIN usuarios u ON u.id=e.usuario_id
            WHERE e.activo=1 AND (e.folio LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.serie LIKE ? OR e.ip LIKE ? OR e.mac_address LIKE ? OR e.ubicacion LIKE ?)
            LIMIT 20");
        $s->execute(array_fill(0, 7, $like));
        $resultados['equipos'] = $s->fetchAll();
    }

    // Usuarios
    if (puede('ver_usuarios')) {
        $s = $db->prepare("SELECT u.*,a.nombre area_nombre FROM usuarios u LEFT JOIN areas a ON a.id=u.area_id
            WHERE u.activo=1 AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ? OR u.usuario_windows LIKE ? OR u.puesto LIKE ?)
            LIMIT 20");
        $s->execute(array_fill(0, 5, $like));
        $resultados['usuarios'] = $s->fetchAll();
    }

    // Tickets
    if (puede('ver_tickets') || puede('ver_tickets_propios')) {
        $s = $db->prepare("SELECT t.*,e.folio equipo_folio,e.marca equipo_marca,
            CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre
            FROM tickets t
            LEFT JOIN equipos e ON e.id=t.equipo_id
            LEFT JOIN usuarios u ON u.id=t.usuario_id
            WHERE t.folio_ticket LIKE ? OR t.descripcion LIKE ? OR t.tecnico LIKE ? OR e.folio LIKE ?
            LIMIT 20");
        $s->execute(array_fill(0, 4, $like));
        $resultados['tickets'] = $s->fetchAll();
    }

    // Inventario
    if (puede('ver_inventario')) {
        $s = $db->prepare("SELECT i.*,e.folio equipo_folio,
            CONCAT(u.nombre,' ',IFNULL(u.apellidos,'')) usuario_nombre,a.nombre area_nombre
            FROM inventario i
            LEFT JOIN equipos e ON e.id=i.equipo_id
            LEFT JOIN usuarios u ON u.id=i.usuario_id
            LEFT JOIN areas a ON a.id=i.area_id
            WHERE i.activo=1 AND (i.tipo LIKE ? OR i.marca LIKE ? OR i.modelo LIKE ? OR i.serie LIKE ?)
            LIMIT 20");
        $s->execute(array_fill(0, 4, $like));
        $resultados['inventario'] = $s->fetchAll();
    }

    $total = array_sum(array_map('count', $resultados));
}

// Función para resaltar coincidencias
function hl(string $texto, string $q): string {
    if (!$q) return h($texto);
    return preg_replace('/('.preg_quote(h($q),'/').')/i', '<mark>$1</mark>', h($texto));
}
?>
<div class="container-fluid px-3 py-3">

  <!-- Barra de búsqueda prominente -->
  <div class="row justify-content-center mb-4">
    <div class="col-lg-8">
      <form method="GET" action="busqueda.php" id="form-search">
        <div class="input-group input-group-lg shadow-sm">
          <span class="input-group-text bg-white border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="search" name="q" id="input-search" class="form-control border-start-0 ps-0"
                 placeholder="Buscar equipos, usuarios, tickets, inventario…"
                 value="<?= h($q) ?>" autofocus autocomplete="off"
                 style="font-size:1rem;">
          <?php if ($q): ?>
          <a href="busqueda.php" class="btn btn-outline-secondary" title="Limpiar">
            <i class="bi bi-x-lg"></i>
          </a>
          <?php endif; ?>
          <button type="submit" class="btn" style="background:#1a3a5c;color:#fff;min-width:100px;">
            Buscar
          </button>
        </div>
        <?php if ($q): ?>
        <div class="text-muted small mt-2 text-center">
          <strong><?= $total ?></strong> resultado(s) para «<strong><?= h($q) ?></strong>»
        </div>
        <?php else: ?>
        <div class="text-muted small mt-2 text-center">Mínimo 2 caracteres para buscar</div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if (!$q): ?>
  <!-- Estado vacío con sugerencias -->
  <div class="row justify-content-center">
    <div class="col-lg-6 text-center py-4 text-muted">
      <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
      <h6>¿Qué estás buscando?</h6>
      <p class="small">Puedes buscar por folio, IP, MAC, nombre, serie, descripción y más.</p>
      <div class="d-flex gap-2 flex-wrap justify-content-center mt-3">
        <?php foreach(['EQ-0001','192.168.','DELL','admin'] as $sug): ?>
        <a href="busqueda.php?q=<?= urlencode($sug) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-clock-history me-1"></i><?= h($sug) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php elseif ($total === 0): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-emoji-frown fs-1 d-block mb-2 opacity-25"></i>
    <h6>Sin resultados para «<?= h($q) ?>»</h6>
    <p class="small">Intenta con otros términos: folio, IP, MAC, nombre de usuario…</p>
  </div>

  <?php else: ?>

  <!-- Navegación por secciones -->
  <ul class="nav nav-pills mb-3 gap-1" id="searchTabs">
    <?php $secciones = [
      'equipos'   => ['bi-pc-display',  'Equipos'],
      'usuarios'  => ['bi-people',      'Usuarios'],
      'tickets'   => ['bi-ticket',      'Tickets'],
      'inventario'=> ['bi-boxes',       'Inventario'],
    ]; $first = true;
    foreach ($secciones as $k => [$icon, $label]): if (empty($resultados[$k])) continue; ?>
    <li class="nav-item">
      <a class="nav-link <?= $first?'active':'' ?>" data-bs-toggle="pill" href="#sec-<?= $k ?>">
        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        <span class="badge bg-secondary ms-1"><?= count($resultados[$k]) ?></span>
      </a>
    </li>
    <?php $first = false; endforeach; ?>
  </ul>

  <div class="tab-content">

    <!-- ── EQUIPOS ── -->
    <?php if (!empty($resultados['equipos'])): $first = empty(array_filter(array_slice($resultados,0,0))) ?>
    <div class="tab-pane fade show active" id="sec-equipos">
      <div class="card">
        <div class="card-header-ti"><i class="bi bi-pc-display me-2"></i>Equipos (<?= count($resultados['equipos']) ?>)</div>
        <div class="card-body p-0">
          <div class="table-responsive">
          <table class="table table-ti mb-0">
            <thead><tr><th>Folio</th><th>Tipo</th><th>Marca / Modelo</th><th>IP</th><th>MAC</th><th>Área</th><th>Usuario</th><th>Estado</th><th>Acc.</th></tr></thead>
            <tbody>
            <?php foreach ($resultados['equipos'] as $e):
              $cls = ['ACTIVO'=>'badge-activo','MANTENIMIENTO'=>'badge-mantenimiento','BAJA'=>'badge-baja','INACTIVO'=>'badge-inactivo'][$e['estado']]??'bg-secondary';
            ?>
            <tr style="cursor:pointer" onclick="location.href='equipos.php?action=view&id=<?= $e['id'] ?>'">
              <td><code class="text-primary"><?= hl($e['folio'],$q) ?></code></td>
              <td class="small"><i class="bi <?= h($e['tipo_icono']??'bi-pc-display') ?> me-1"></i><?= h($e['tipo_nombre']??'') ?></td>
              <td><strong><?= hl($e['marca'],$q) ?></strong> <span class="text-muted"><?= hl($e['modelo']??'',$q) ?></span></td>
              <td><code><?= hl($e['ip']??'',$q) ?></code></td>
              <td><code class="small"><?= hl($e['mac_address']??'',$q) ?></code></td>
              <td class="small"><?= h($e['area_nombre']??'') ?></td>
              <td class="small"><?= h(trim($e['usuario_nombre']??'')) ?></td>
              <td><span class="badge <?= $cls ?>"><?= h($e['estado']) ?></span></td>
              <td><a href="equipos.php?action=view&id=<?= $e['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── USUARIOS ── -->
    <?php if (!empty($resultados['usuarios'])): ?>
    <div class="tab-pane fade <?= empty($resultados['equipos'])?'show active':'' ?>" id="sec-usuarios">
      <div class="card">
        <div class="card-header-ti"><i class="bi bi-people me-2"></i>Usuarios (<?= count($resultados['usuarios']) ?>)</div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Nombre</th><th>Puesto</th><th>Área</th><th>Email</th><th>Usuario Win.</th><th>Acc.</th></tr></thead>
            <tbody>
            <?php foreach ($resultados['usuarios'] as $u): ?>
            <tr style="cursor:pointer" onclick="location.href='usuarios.php?action=view&id=<?= $u['id'] ?>'">
              <td><strong><?= hl($u['nombre'].' '.($u['apellidos']??''),$q) ?></strong></td>
              <td class="small"><?= hl($u['puesto']??'',$q) ?></td>
              <td class="small"><?= h($u['area_nombre']??'') ?></td>
              <td class="small"><?= hl($u['email']??'',$q) ?></td>
              <td><code class="small"><?= hl($u['usuario_windows']??'',$q) ?></code></td>
              <td><a href="usuarios.php?action=view&id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── TICKETS ── -->
    <?php if (!empty($resultados['tickets'])): ?>
    <div class="tab-pane fade <?= (empty($resultados['equipos'])&&empty($resultados['usuarios']))?'show active':'' ?>" id="sec-tickets">
      <div class="card">
        <div class="card-header-ti"><i class="bi bi-ticket me-2"></i>Tickets (<?= count($resultados['tickets']) ?>)</div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Folio</th><th>Equipo</th><th>Descripción</th><th>Técnico</th><th>Prioridad</th><th>Estado</th><th>Acc.</th></tr></thead>
            <tbody>
            <?php foreach ($resultados['tickets'] as $t):
              $prioC=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
              $estC=['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
            ?>
            <tr style="cursor:pointer" onclick="location.href='tickets.php?action=view&id=<?= $t['id'] ?>'">
              <td><code class="text-primary"><?= hl($t['folio_ticket'],$q) ?></code></td>
              <td class="small"><?= h($t['equipo_folio'].' '.($t['equipo_marca']??'')) ?></td>
              <td class="small text-muted"><?= hl(mb_substr($t['descripcion']??'',0,60),$q) ?></td>
              <td class="small"><?= hl($t['tecnico']??'',$q) ?></td>
              <td><span class="badge <?= $prioC ?>"><?= h($t['prioridad']) ?></span></td>
              <td><span class="badge <?= $estC ?>"><?= h(str_replace('_',' ',$t['estado'])) ?></span></td>
              <td><a href="tickets.php?action=view&id=<?= $t['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── INVENTARIO ── -->
    <?php if (!empty($resultados['inventario'])): ?>
    <div class="tab-pane fade" id="sec-inventario">
      <div class="card">
        <div class="card-header-ti"><i class="bi bi-boxes me-2"></i>Inventario (<?= count($resultados['inventario']) ?>)</div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Tipo</th><th>Marca / Modelo</th><th>Serie</th><th>Equipo</th><th>Usuario</th><th>Estado</th><th>Acc.</th></tr></thead>
            <tbody>
            <?php foreach ($resultados['inventario'] as $i):
              $ic=['BUENO'=>'badge-bueno','REGULAR'=>'badge-regular','MALO'=>'badge-malo','BAJA'=>'badge-baja'][$i['estado']]??'bg-secondary';
            ?>
            <tr>
              <td><strong><?= hl($i['tipo'],$q) ?></strong></td>
              <td><?= hl($i['marca']??'',$q) ?> <span class="text-muted"><?= hl($i['modelo']??'',$q) ?></span></td>
              <td><code class="small"><?= hl($i['serie']??'',$q) ?></code></td>
              <td><code class="small"><?= h($i['equipo_folio']??'—') ?></code></td>
              <td class="small"><?= h(trim($i['usuario_nombre']??'')) ?></td>
              <td><span class="badge <?= $ic ?>"><?= h($i['estado']) ?></span></td>
              <td><a href="inventario.php?action=edit&id=<?= $i['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /tab-content -->
  <?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>
