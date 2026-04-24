<?php
require_once 'includes/auth.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';

// ── POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isNormal()) { flash('danger','Sin permiso.'); redirect('equipos.php'); }

    $pid = (int)($_POST['id'] ?? 0) ?: null;

    if (isSuper() && $pid) {
        // Supervisor: guardar como cambio pendiente de aprobación
        $cpId = guardarCambioPendiente('equipos', $pid, 'UPDATE', $_POST, 'Edición de equipo '.$_POST['folio']);
        auditLog('EDITAR_EQUIPO_PENDIENTE','equipos',$pid,'Cambio pendiente #'.$cpId);
        flash('warning','Cambios enviados para aprobación del Administrador (Cambio #'.$cpId.').');
        redirect('equipos.php?action=view&id='.$pid);
    }

    // Admin: guardar directo
    $saved = saveEquipo($_POST, $pid);
    auditLog($pid?'EDITAR_EQUIPO':'CREAR_EQUIPO','equipos',$saved,'Folio: '.($_POST['folio']??''));
    flash('success', $pid ? 'Equipo actualizado.' : 'Equipo creado: '.$_POST['folio'].'.');
    redirect('equipos.php?action=view&id='.$saved);
}

// ── DELETE ────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    requireRol(ROL_ADMIN);
    deleteEquipo($id);
    auditLog('ELIMINAR_EQUIPO','equipos',$id);
    flash('warning', 'Equipo dado de baja.');
    redirect('equipos.php');
}

// ── DATA ──────────────────────────────────────────────────
$q      = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? '';
$tipo   = (int)($_GET['tipo'] ?? 0);
$area   = (int)($_GET['area'] ?? 0);

$pageTitle = 'Equipos';
include 'includes/header.php';

$areas    = getAreas();
$tipos    = getTiposEquipo();
$usuarios = getUsuarios();

// ── USUARIO NORMAL: solo ve sus equipos ──────────────────
if (isNormal()):
    $usuarioId = usuarioIdActual();
    $misEquipos = [];
    if ($usuarioId) {
        $s = getDB()->prepare("SELECT e.*,t.nombre tipo_nombre,t.icono tipo_icono,a.nombre area_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id LEFT JOIN areas a ON a.id=e.area_id WHERE e.usuario_id=? AND e.activo=1 ORDER BY e.folio");
        $s->execute([$usuarioId]);
        $misEquipos = $s->fetchAll();
    }
?>
<div class="container-fluid px-3 py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-pc-display me-2"></i>Mis Equipos Asignados</h5>
    <a href="tickets.php?action=new" class="btn btn-sm btn-danger">
      <i class="bi bi-ticket me-1"></i>Solicitar Soporte
    </a>
  </div>

  <?php if (empty($misEquipos)): ?>
  <div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-pc-display fs-1 d-block mb-2 opacity-25"></i>
    No tienes equipos asignados. Contacta al Administrador TI.
  </div></div>
  <?php else: ?>
  <div class="row g-3">
  <?php foreach ($misEquipos as $e):
    $cls = ['ACTIVO'=>'badge-activo','MANTENIMIENTO'=>'badge-mantenimiento','BAJA'=>'badge-baja','INACTIVO'=>'badge-inactivo'][$e['estado']]??'bg-secondary';
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="my-equipo-card card h-100">
      <div class="my-equipo-header d-flex justify-content-between align-items-start">
        <div>
          <i class="bi <?= h($e['tipo_icono']??'bi-pc-display') ?> fs-4 mb-1 d-block"></i>
          <strong><?= h($e['tipo_nombre']??'') ?></strong>
        </div>
        <span class="badge <?= $cls ?>"><?= h($e['estado']) ?></span>
      </div>
      <div class="card-body">
        <h6 class="mb-1"><?= h($e['folio']) ?> — <?= h($e['marca'].' '.($e['modelo']??'')) ?></h6>
        <table class="table table-sm mb-0">
          <?php foreach ([['Área', $e['area_nombre']],['IP','<code>'.h($e['ip']??'').'</code>'],['Ubicación',$e['ubicacion']],['Serie',$e['serie']]] as [$k,$v]): if(!$v) continue; ?>
          <tr><th class="small text-muted" style="width:40%"><?=$k?></th><td class="small"><?=$v?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2">
        <a href="equipos.php?action=view&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
          <i class="bi bi-eye me-1"></i>Ver Detalles
        </a>
        <a href="tickets.php?action=new&equipo_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger flex-fill">
          <i class="bi bi-ticket me-1"></i>Solicitar
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; return;
endif; // end isNormal
?>
<div class="container-fluid px-3 py-3">

<?php if ($action === 'list'): ?>
<!-- ══ LISTA ══ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-pc-display me-2"></i>Equipos</h5>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir</button>
    <a href="exports/export_equipos_pdf.php<?= $q?"?q=".urlencode($q):'' ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <a href="exports/export_equipos_xls.php<?= $q?"?q=".urlencode($q):'' ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <?php if (puede('crear_equipo')): ?>
    <a href="equipos.php?action=new" class="btn btn-sm" style="background:#1a3a5c;color:#fff;"><i class="bi bi-plus me-1"></i>Nuevo Equipo</a>
    <?php endif; ?>
  </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form id="form-search" method="GET" action="equipos.php" class="row g-2 align-items-end">
      <div class="col-sm-3">
        <input type="search" name="q" id="input-search" class="form-control form-control-sm"
               placeholder="Folio, IP, MAC, marca, usuario…" value="<?= h($q) ?>">
      </div>
      <div class="col-sm-2">
        <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Tipo —</option>
          <?php foreach($tipos as $t): ?><option value="<?=$t['id']?>" <?=$tipo==(int)$t['id']?'selected':''?>><?=h($t['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <select name="area" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Área —</option>
          <?php foreach($areas as $a): ?><option value="<?=$a['id']?>" <?=$area==(int)$a['id']?'selected':''?>><?=h($a['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Estado —</option>
          <?php foreach(['ACTIVO','INACTIVO','MANTENIMIENTO','BAJA'] as $e): ?><option <?=$estado===$e?'selected':''?>><?=$e?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
        <?php if($q||$estado||$tipo||$area): ?><a href="equipos.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php $equipos = getEquipos($q, $estado, $tipo, $area); ?>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-ti mb-0">
      <thead><tr>
        <th>Folio</th><th>Tipo</th><th>Marca/Modelo</th><th>IP</th><th>MAC</th>
        <th>Nodo</th><th>Área</th><th>Usuario</th><th>Estado</th><th class="no-print">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach($equipos as $e):
        $cls = ['ACTIVO'=>'badge-activo','MANTENIMIENTO'=>'badge-mantenimiento','BAJA'=>'badge-baja','INACTIVO'=>'badge-inactivo'][$e['estado']]??'bg-secondary';
      ?>
      <tr style="cursor:pointer" onclick="location.href='equipos.php?action=view&id=<?=$e['id']?>'">
        <td><code class="text-primary"><?=h($e['folio'])?></code></td>
        <td><i class="bi <?=h($e['tipo_icono']??'bi-pc-display')?> me-1"></i><?=h($e['tipo_nombre']??'')?></td>
        <td><strong><?=h($e['marca'])?></strong><br><span class="text-muted small"><?=h($e['modelo']??'')?></span></td>
        <td><code><?=h($e['ip']??'')?></code></td>
        <td><code class="small"><?=h($e['mac_address']??'')?></code></td>
        <td><?=h($e['nodo']??'')?></td>
        <td class="small"><?=h($e['area_nombre']??'')?></td>
        <td class="small"><?=h(trim($e['usuario_nombre']??''))?></td>
        <td><span class="badge <?=$cls?>"><?=h($e['estado'])?></span></td>
        <td class="no-print" onclick="event.stopPropagation()">
          <a href="equipos.php?action=edit&id=<?=$e['id']?>" class="btn btn-xs btn-outline-primary me-1" title="Editar"><i class="bi bi-pencil"></i></a>
          <?php if (puede('crear_mantenimiento')): ?>
          <a href="mantenimiento.php?action=new&equipo_id=<?=$e['id']?>" class="btn btn-xs btn-outline-warning me-1" title="Mantenimiento"><i class="bi bi-tools"></i></a>
          <?php endif; ?>
          <?php if (puede('eliminar_equipo')): ?>
          <button onclick="confirmDelete('equipos.php?action=delete&id=<?=$e['id']?>','<?=addslashes($e['folio'])?>')" class="btn btn-xs btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($equipos)): ?><tr><td colspan="10" class="text-center text-muted py-4">Sin equipos registrados</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php elseif (in_array($action,['new','edit'])): ?>
<!-- ══ FORM ══ -->
<?php
$eq = $action==='edit' && $id ? getEquipoById($id) : [];
$isEdit = !empty($eq);
?>
<div class="row justify-content-center">
<div class="col-xl-9">
  <div class="card">
    <div class="card-header-ti">
      <i class="bi bi-<?=$isEdit?'pencil':'plus-circle'?> me-2"></i>
      <?=$isEdit?'Editar Equipo — '.h($eq['folio']):'Nuevo Equipo'?>
    </div>
    <div class="card-body">
      <form method="POST" action="equipos.php">
        <?php if($isEdit): ?><input type="hidden" name="id" value="<?=$eq['id']?>"><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Folio *</label>
            <div class="input-group">
              <input type="text" name="folio" id="folio" class="form-control" value="<?=h($eq['folio']??nextFolioEquipo())?>" required>
              <button type="button" id="btn-gen-folio" class="btn btn-outline-secondary" title="Regenerar"><i class="bi bi-arrow-repeat"></i></button>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo de Equipo</label>
            <select name="tipo_id" class="form-select">
              <option value="">— Seleccionar —</option>
              <?php foreach($tipos as $t): ?><option value="<?=$t['id']?>" <?=(int)($eq['tipo_id']??0)==(int)$t['id']?'selected':''?>><?=h($t['nombre'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <?php foreach(['ACTIVO','INACTIVO','MANTENIMIENTO','BAJA'] as $e2): ?><option <?=($eq['estado']??'ACTIVO')===$e2?'selected':''?>><?=$e2?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha Adquisición</label>
            <input type="date" name="fecha_adquisicion" class="form-control" value="<?=h($eq['fecha_adquisicion']??'')?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Marca *</label>
            <input type="text" name="marca" class="form-control" value="<?=h($eq['marca']??'')?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Modelo</label>
            <input type="text" name="modelo" class="form-control" value="<?=h($eq['modelo']??'')?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">No. Serie</label>
            <input type="text" name="serie" class="form-control" value="<?=h($eq['serie']??'')?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Dirección IP</label>
            <input type="text" name="ip" class="form-control" value="<?=h($eq['ip']??'')?>" placeholder="192.168.1.x">
          </div>
          <div class="col-md-3">
            <label class="form-label">MAC Address</label>
            <input type="text" name="mac_address" class="form-control" value="<?=h($eq['mac_address']??'')?>" placeholder="AA:BB:CC:DD:EE:FF">
          </div>
          <div class="col-md-3">
            <label class="form-label">Nodo</label>
            <input type="text" name="nodo" class="form-control" value="<?=h($eq['nodo']??'')?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Conexión por Medio de</label>
            <select name="conexion_medio" class="form-select">
              <?php foreach(['CABLE','WIFI','FIBRA','SERIAL','USB'] as $cm): ?><option <?=($eq['conexion_medio']??'')===$cm?'selected':''?>><?=$cm?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Área</label>
            <select name="area_id" class="form-select">
              <option value="">— Sin área —</option>
              <?php foreach($areas as $a): ?><option value="<?=$a['id']?>" <?=(int)($eq['area_id']??0)==(int)$a['id']?'selected':''?>><?=h($a['nombre'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Usuario Asignado</label>
            <select name="usuario_id" class="form-select">
              <option value="">— Sin usuario —</option>
              <?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=(int)($eq['usuario_id']??0)==(int)$u['id']?'selected':''?>><?=h($u['nombre'].' '.($u['apellidos']??''))?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ubicación Física</label>
            <input type="text" name="ubicacion" class="form-control" value="<?=h($eq['ubicacion']??'')?>">
          </div>
          <div class="col-12">
            <label class="form-label">Descripción / Notas</label>
            <textarea name="descripcion" class="form-control" rows="2"><?=h($eq['descripcion']??'')?></textarea>
          </div>
        </div>
        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn" style="background:#1a3a5c;color:#fff;"><i class="bi bi-check-lg me-1"></i><?=$isEdit?'Guardar Cambios':'Crear Equipo'?></button>
          <a href="<?=$isEdit?'equipos.php?action=view&id='.$id:'equipos.php'?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>
</div>

<?php elseif ($action === 'view' && $id): ?>
<!-- ══ DETALLE ══ -->
<?php
$eq  = getEquipoById($id);
$inv = getInventario(equipo:$id);
$mants = getMantenimientos(equipo:$id);
$tks = getTickets(); // filter below
$ticketsEq = array_filter($tks, fn($t)=>$t['equipo_id']==$id);
if (!$eq) { flash('danger','Equipo no encontrado.'); redirect('equipos.php'); }
$cls = ['ACTIVO'=>'badge-activo','MANTENIMIENTO'=>'badge-mantenimiento','BAJA'=>'badge-baja','INACTIVO'=>'badge-inactivo'][$eq['estado']]??'bg-secondary';
?>
<div class="equipo-header d-flex justify-content-between align-items-start flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-pc-display me-2"></i><?=h($eq['folio'])?> — <?=h($eq['marca'].' '.($eq['modelo']??''))?></h4>
    <span class="badge <?=$cls?> me-2"><?=h($eq['estado'])?></span>
    <span class="badge bg-secondary"><?=h($eq['tipo_nombre']??'')?></span>
  </div>
  <div class="d-flex gap-2 flex-wrap no-print">
    <a href="equipos.php?action=edit&id=<?=$id?>" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="mantenimiento.php?action=new&equipo_id=<?=$id?>" class="btn btn-sm btn-warning"><i class="bi bi-tools me-1"></i>Mantenimiento</a>
    <a href="tickets.php?action=new&equipo_id=<?=$id?>" class="btn btn-sm btn-danger"><i class="bi bi-ticket me-1"></i>Ticket</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-light"><i class="bi bi-printer me-1"></i>Imprimir</button>
    <a href="exports/export_equipos_pdf.php?id=<?=$id?>" target="_blank" class="btn btn-sm btn-outline-light"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
  </div>
</div>

<div class="row g-3">
  <!-- Datos principales -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header-ti"><i class="bi bi-info-circle me-2"></i>Información del Equipo</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <?php $rows=[['Folio',$eq['folio']],['Tipo',$eq['tipo_nombre']],['Marca',$eq['marca']],['Modelo',$eq['modelo']],['No. Serie',$eq['serie']],['Dirección IP','<code>'.h($eq['ip']).'</code>'],['MAC Address','<code>'.h($eq['mac_address']).'</code>'],['Nodo',$eq['nodo']],['Conexión',$eq['conexion_medio']],['Ubicación',$eq['ubicacion']],['Área',$eq['area_nombre']],['Usuario',h($eq['usuario_nombre'])],['Estado','<span class="badge '.$cls.'">'.h($eq['estado']).'</span>'],['F. Adquisición',$eq['fecha_adquisicion']]];
          foreach($rows as [$k,$v]): if(!$v) continue; ?>
          <tr><th class="text-muted small" style="width:38%"><?=$k?></th><td><?=$v?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>
  <!-- Inventario periféricos -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header-ti d-flex justify-content-between">
        <span><i class="bi bi-boxes me-2"></i>Periféricos / Inventario</span>
        <a href="inventario.php?action=new&equipo_id=<?=$id?>" class="btn btn-sm btn-outline-light py-0 no-print">+ Agregar</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Tipo</th><th>Marca/Modelo</th><th>Serie</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach($inv as $i):
            $ic=['BUENO'=>'badge-bueno','REGULAR'=>'badge-regular','MALO'=>'badge-malo','BAJA'=>'badge-baja'][$i['estado']]??'bg-secondary';
          ?>
          <tr>
            <td><?=h($i['tipo'])?></td>
            <td><?=h($i['marca'].' '.($i['modelo']??''))?></td>
            <td><code class="small"><?=h($i['serie']??'')?></code></td>
            <td><span class="badge <?=$ic?>"><?=h($i['estado'])?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($inv)): ?><tr><td colspan="4" class="text-center text-muted py-2 small">Sin periféricos</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- Historial mantenimientos -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header-ti d-flex justify-content-between">
        <span><i class="bi bi-clipboard-check me-2"></i>Mantenimientos</span>
        <a href="mantenimiento.php?action=new&equipo_id=<?=$id?>" class="btn btn-sm btn-outline-light py-0 no-print">+ Nuevo</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Fecha</th><th>Tipo</th><th>Técnico</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php foreach($mants as $m): ?>
          <tr>
            <td><?=h($m['fecha'])?></td>
            <td><span class="badge bg-info text-dark"><?=h($m['tipo'])?></span></td>
            <td class="small"><?=h($m['tecnico']??'')?></td>
            <td class="no-print"><a href="mantenimiento.php?action=view&id=<?=$m['id']?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($mants)): ?><tr><td colspan="4" class="text-center text-muted py-2 small">Sin mantenimientos</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- Tickets del equipo -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header-ti d-flex justify-content-between">
        <span><i class="bi bi-ticket me-2"></i>Tickets</span>
        <a href="tickets.php?action=new&equipo_id=<?=$id?>" class="btn btn-sm btn-outline-light py-0 no-print">+ Nuevo</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Folio</th><th>Tipo</th><th>Prioridad</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach($ticketsEq as $t):
            $prioC=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
            $estC=['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
          ?>
          <tr><td><code><?=h($t['folio_ticket'])?></code></td><td class="small"><?=h(str_replace('_',' ',$t['tipo_servicio']))?></td><td><span class="badge <?=$prioC?>"><?=h($t['prioridad'])?></span></td><td><span class="badge <?=$estC?>"><?=h(str_replace('_',' ',$t['estado']))?></span></td></tr>
          <?php endforeach; ?>
          <?php if(empty($ticketsEq)): ?><tr><td colspan="4" class="text-center text-muted py-2 small">Sin tickets</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
