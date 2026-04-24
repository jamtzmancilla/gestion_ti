<?php
require_once 'includes/auth.php';

$action  = $_GET['action'] ?? 'list';
$id      = (int)($_GET['id'] ?? 0);

// ── POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($_POST['id']??0) ?: null;

    // Usuario normal: solo puede crear solicitudes propias
    if (isNormal()) {
        if ($pid) { flash('danger','Sin permiso para editar tickets.'); redirect('tickets.php'); }
        // Crear ticket pendiente de asignación
        $_POST['estado']            = 'ABIERTO';
        $_POST['solicitante_suser'] = suserIdActual();
        $saved = saveTicket($_POST, null);
        auditLog('CREAR_TICKET','tickets',$saved,'Solicitado por usuario normal');
        flash('success', 'Solicitud enviada. El Administrador la revisará y asignará.');
        redirect('tickets.php');
    }

    // Supervisor: edita pero queda pendiente de aprobación
    if (isSuper() && $pid) {
        $cpId = guardarCambioPendiente('tickets', $pid, 'UPDATE', $_POST, 'Actualización ticket #'.$pid.' por supervisor');
        auditLog('EDITAR_TICKET_PENDIENTE','tickets',$pid,'Cambio pendiente #'.$cpId);
        flash('warning', 'Cambios enviados para aprobación del Administrador (#'.$cpId.').');
        redirect('tickets.php?action=view&id='.$pid);
    }

    // Admin: todo directo
    if ($_POST['estado']==='RESUELTO' && empty($_POST['fecha_cierre']))
        $_POST['fecha_cierre'] = date('Y-m-d H:i:s');
    $saved = saveTicket($_POST, $pid);
    auditLog($pid?'EDITAR_TICKET':'CREAR_TICKET','tickets',$saved);
    flash('success', $pid?'Ticket actualizado.':'Ticket creado.');
    redirect('tickets.php?action=view&id='.$saved);
}

if ($action==='delete' && $id) {
    requireRol(ROL_ADMIN);
    deleteTicket($id);
    auditLog('ELIMINAR_TICKET','tickets',$id);
    flash('warning','Ticket eliminado.');
    redirect('tickets.php');
}

// ── ASIGNAR (solo admin) ──────────────────────────────────
if ($action === 'asignar' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRol(ROL_ADMIN);
    $superId   = (int)($_POST['supervisor_id'] ?? 0);
    $superInfo = $superId ? getSystemUserById($superId) : null;
    if ($superInfo) {
        getDB()->prepare(
            "UPDATE tickets SET asignado_a_suser=?, asignado_por=?, tecnico=?, estado='EN_PROCESO' WHERE id=?"
        )->execute([$superId, suserIdActual(), $superInfo['nombre_completo'], $id]);
        auditLog('ASIGNAR_TICKET','tickets',$id,'Asignado a: '.$superInfo['nombre_completo']);
        flash('success', 'Ticket asignado a '.$superInfo['nombre_completo'].'.');
    }
    redirect('tickets.php?action=view&id='.$id);
}

$pageTitle = 'Tickets / Soporte';
include 'includes/header.php';

$equipos    = getEquipos();
$usuarios   = getUsuarios();
$supervisores = getSupervisores();
$q          = trim($_GET['q']??'');
$estado     = $_GET['estado']??'';
$prioridad  = $_GET['prioridad']??'';
?>
<div class="container-fluid px-3 py-3">

<?php if ($action==='list'): ?>
<!-- ══ LISTA ══ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0">
    <i class="bi bi-ticket me-2"></i>
    <?= isNormal() ? 'Mis Solicitudes de Soporte' : (isSuper() ? 'Tickets Asignados' : 'Tickets de Soporte') ?>
  </h5>
  <div class="d-flex gap-2">
    <?php if (puede('exportar')): ?>
    <a href="exports/export_tickets_xls.php" class="btn btn-sm btn-outline-success no-print"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <?php endif; ?>
    <?php if (puede('crear_ticket') || puede('crear_ticket_propio')): ?>
    <a href="tickets.php?action=new" class="btn btn-sm btn-danger">
      <i class="bi bi-plus me-1"></i><?= isNormal() ? 'Nueva Solicitud' : 'Nuevo Ticket' ?>
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!isNormal()): // Admin y supervisor ven filtros ?>
<div class="card mb-3"><div class="card-body py-2">
  <form id="form-search" method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="action" value="list">
    <div class="col-sm-3"><input type="search" name="q" id="input-search" class="form-control form-control-sm" placeholder="Folio, equipo, técnico…" value="<?=h($q)?>"></div>
    <div class="col-sm-2">
      <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Estado —</option>
        <?php foreach(['ABIERTO','EN_PROCESO','RESUELTO','CANCELADO'] as $e): ?><option <?=$estado===$e?'selected':''?>><?=$e?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-2">
      <select name="prioridad" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Prioridad —</option>
        <?php foreach(['URGENTE','ALTA','MEDIA','BAJA'] as $p): ?><option <?=$prioridad===$p?'selected':''?>><?=$p?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
      <?php if($q||$estado||$prioridad): ?><a href="tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
    </div>
  </form>
</div></div>
<?php endif; ?>

<?php
// Obtener tickets según rol
if (isNormal())    $tickets = getMisTickets();
elseif (isSuper()) $tickets = getMisTicketsComoSupervisor();
else               $tickets = getTickets($q, $estado, $prioridad);
?>

<?php if (isNormal() && empty($tickets)): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
  <i class="bi bi-ticket fs-1 d-block mb-2 opacity-25"></i>
  No tienes solicitudes activas.
  <br><a href="tickets.php?action=new" class="btn btn-sm btn-danger mt-3"><i class="bi bi-plus me-1"></i>Nueva Solicitud</a>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-ti mb-0">
  <thead><tr>
    <th>Folio</th><th>Equipo</th>
    <?php if (!isNormal()): ?><th>Usuario</th><?php endif; ?>
    <th>Tipo</th><th>Descripción</th><th>Técnico</th><th>Prioridad</th><th>Estado</th><th>Apertura</th>
    <th class="no-print">Acciones</th>
  </tr></thead>
  <tbody>
  <?php foreach($tickets as $t):
    $prioC=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
    $estC =['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
  ?>
  <tr style="cursor:pointer" onclick="location.href='tickets.php?action=view&id=<?=$t['id']?>'">
    <td><code class="text-primary"><?=h($t['folio_ticket'])?></code></td>
    <td class="small"><?=h($t['equipo_folio'].' '.($t['equipo_marca']??''))?></td>
    <?php if (!isNormal()): ?><td class="small"><?=h(trim($t['usuario_nombre']??''))?></td><?php endif; ?>
    <td class="small"><?=h(str_replace('_',' ',$t['tipo_servicio']))?></td>
    <td class="small text-muted"><?=h(mb_substr($t['descripcion']??'',0,50))?></td>
    <td class="small"><?=h($t['tecnico']??'')?></td>
    <td><span class="badge <?=$prioC?>"><?=h($t['prioridad'])?></span></td>
    <td><span class="badge <?=$estC?>"><?=h(str_replace('_',' ',$t['estado']))?></span></td>
    <td class="small"><?=h(substr($t['fecha_apertura']??'',0,10))?></td>
    <td class="no-print" onclick="event.stopPropagation()">
      <a href="tickets.php?action=view&id=<?=$t['id']?>" class="btn btn-xs btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
      <?php if (puede('editar_ticket') || isAdmin()): ?>
      <a href="tickets.php?action=edit&id=<?=$t['id']?>" class="btn btn-xs btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
      <?php endif; ?>
      <?php if (puede('eliminar_ticket')): ?>
      <button onclick="confirmDelete('tickets.php?action=delete&id=<?=$t['id']?>','<?=addslashes($t['folio_ticket'])?>')" class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($tickets)): ?><tr><td colspan="10" class="text-center text-muted py-4">Sin tickets</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div></div>
<?php endif; ?>

<?php elseif(in_array($action,['new','edit'])): ?>
<!-- ══ FORM ══ -->
<?php
$t = $action==='edit' && $id ? getTicketById($id) : [];
$isEdit = !empty($t);
$equipoIdPre = (int)($_GET['equipo_id']??$t['equipo_id']??0);

// Usuario normal: solo puede crear, no editar
if (isNormal() && $isEdit) { flash('danger','Sin permiso.'); redirect('tickets.php'); }
// Supervisor no puede crear nuevos tickets, solo editar los asignados
if (isSuper() && !$isEdit) { flash('danger','Solo el Administrador crea tickets.'); redirect('tickets.php'); }

// Para usuario normal: solo sus equipos
if (isNormal()) {
    $usuarioId = usuarioIdActual();
    if ($usuarioId) {
        $s = getDB()->prepare("SELECT e.*,t.nombre tipo_nombre FROM equipos e LEFT JOIN tipos_equipo t ON t.id=e.tipo_id WHERE e.usuario_id=? AND e.activo=1");
        $s->execute([$usuarioId]);
        $equipos = $s->fetchAll();
    } else { $equipos = []; }
}
?>
<div class="row justify-content-center">
<div class="col-xl-8">
  <div class="card">
    <div class="card-header-ti">
      <i class="bi bi-ticket me-2"></i>
      <?php if (isNormal()): ?>
        Nueva Solicitud de Soporte
      <?php elseif ($isEdit): ?>
        Editar Ticket — <?=h($t['folio_ticket'])?>
        <?php if (isSuper()): ?>
        <span class="badge bg-warning text-dark ms-2 small">Los cambios requieren aprobación del Administrador</span>
        <?php endif; ?>
      <?php else: ?>
        Nuevo Ticket
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST" action="tickets.php">
        <?php if($isEdit): ?><input type="hidden" name="id" value="<?=$t['id']?>"><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-<?= isNormal() ? '12' : '6' ?>">
            <label class="form-label">Equipo *</label>
            <select name="equipo_id" class="form-select" required>
              <option value="">— Seleccionar equipo —</option>
              <?php foreach($equipos as $e): ?>
              <option value="<?=$e['id']?>" <?=(int)($t['equipo_id']??$equipoIdPre)==(int)$e['id']?'selected':''?>>
                <?=h($e['folio'].' — '.($e['tipo_nombre']??'').' '.($e['marca']??'').' '.($e['modelo']??''))?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!isNormal()): ?>
          <div class="col-md-6">
            <label class="form-label">Usuario Solicitante</label>
            <select name="usuario_id" class="form-select">
              <option value="">— Seleccionar —</option>
              <?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=(int)($t['usuario_id']??0)==(int)$u['id']?'selected':''?>><?=h($u['nombre'].' '.($u['apellidos']??''))?></option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="col-md-<?= isNormal()?'12':'4' ?>">
            <label class="form-label">Tipo de Servicio</label>
            <select name="tipo_servicio" class="form-select" <?= isNormal()?'':'readonly' ?>>
              <?php foreach(['MANTENIMIENTO_PREVENTIVO','MANTENIMIENTO_CORRECTIVO','SOPORTE','INSTALACION','OTRO'] as $ts): ?>
              <option value="<?=$ts?>" <?=($t['tipo_servicio']??'SOPORTE')===$ts?'selected':''?>><?=h(str_replace('_',' ',$ts))?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-select">
              <?php foreach(['URGENTE','ALTA','MEDIA','BAJA'] as $p): ?><option value="<?=$p?>" <?=($t['prioridad']??'MEDIA')===$p?'selected':''?>><?=$p?></option><?php endforeach; ?>
            </select>
          </div>

<?php if (isAdmin()): ?>
<div class="col-md-6">
  <label class="form-label">Asignar a Supervisor Técnico *</label>
  <select name="asignado_a_suser" class="form-select" required>
    <option value="">— Seleccionar Técnico —</option>
    <?php foreach($supervisores as $sup): ?>
      <option value="<?=$sup['id']?>" <?=((int)($t['asignado_a_suser']??0)==(int)$sup['id'])?'selected':''?>>
        <?=h($sup['nombre_completo'])?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<input type="hidden" name="tecnico" id="hidden_tecnico" value="<?=h($t['tecnico']??'')?>">

<script>
// Sincroniza el nombre del técnico automáticamente
document.querySelector('select[name="asignado_a_suser"]').addEventListener('change', function() {
    document.getElementById('hidden_tecnico').value = this.options[this.selectedIndex].text;
});
</script>
<?php endif; ?>

          <div class="col-12">
            <label class="form-label">
              <?= isNormal() ? 'Descripción del problema *' : 'Descripción del Problema *' ?>
            </label>
            <textarea name="descripcion" class="form-control" rows="4" required><?=h($t['descripcion']??'')?></textarea>
          </div>
        </div>
        <?php if (isNormal()): ?>
        <div class="alert alert-info mt-3 py-2 small">
          <i class="bi bi-info-circle me-1"></i>
          Tu solicitud será revisada por el Administrador TI, quien la asignará al Supervisor Técnico correspondiente.
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn" style="background:#1a3a5c;color:#fff;">
            <i class="bi bi-check-lg me-1"></i>
            <?= isNormal() ? 'Enviar Solicitud' : ($isEdit ? 'Guardar' : 'Crear Ticket') ?>
          </button>
          <a href="tickets.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div></div>

<?php elseif($action==='view' && $id): ?>
<!-- ══ VISTA ══ -->
<?php
$t = getTicketById($id);
if(!$t){ flash('danger','No encontrado.'); redirect('tickets.php'); }
// Usuario normal: solo sus propios tickets
if (isNormal() && (int)$t['solicitante_suser'] !== suserIdActual()) {
    flash('danger','Sin permiso para ver este ticket.'); redirect('tickets.php');
}
$prioC=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
$estC =['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-1"><code><?=h($t['folio_ticket'])?></code></h5>
    <span class="badge <?=$prioC?> me-1"><?=h($t['prioridad'])?></span>
    <span class="badge <?=$estC?>"><?=h(str_replace('_',' ',$t['estado']))?></span>
  </div>
  <div class="d-flex gap-2 no-print flex-wrap">
    <?php if ((puede('editar_ticket') || isAdmin())): ?>
    <a href="tickets.php?action=edit&id=<?=$id?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <?php endif; ?>
    <?php if (isAdmin() && $t['estado']==='ABIERTO'): ?>
    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalAsignar">
      <i class="bi bi-person-check me-1"></i>Asignar a Supervisor
    </button>
    <?php endif; ?>
    <a href="mantenimiento.php?action=new&equipo_id=<?=$t['equipo_id']?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-tools me-1"></i>Generar Mantenimiento</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir</button>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header-ti"><i class="bi bi-info-circle me-2"></i>Detalles del Ticket</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <?php
          $assignedSu = $t['asignado_a_suser'] ? getSystemUserById((int)$t['asignado_a_suser']) : null;
          $assignedBy = $t['asignado_por'] ? getSystemUserById((int)$t['asignado_por']) : null;
          $info=[
            ['Folio',$t['folio_ticket']],
            ['Equipo',h($t['equipo_folio'].' — '.($t['equipo_marca']??'').' '.($t['equipo_modelo']??''))],
            ['Usuario',trim($t['usuario_nombre']??'')],
            ['Tipo',str_replace('_',' ',$t['tipo_servicio'])],
            ['Prioridad',$t['prioridad']],
            ['Estado',str_replace('_',' ',$t['estado'])],
            ['Técnico asignado',h($t['tecnico']??'Sin asignar')],
            ['Asignado a supervisor', $assignedSu ? h($assignedSu['nombre_completo']) : '—'],
            ['Asignado por', $assignedBy ? h($assignedBy['nombre_completo']) : '—'],
            ['Apertura',substr($t['fecha_apertura']??'',0,16)],
            ['Cierre',substr($t['fecha_cierre']??'',0,16)],
          ];
          foreach($info as [$k,$v]): if(!$v||$v==='—') continue; ?>
          <tr><th class="small text-muted" style="width:38%"><?=$k?></th><td class="small"><?=$v?></td></tr>
          <?php endforeach; ?>
        </table>
        <hr>
        <div class="small"><strong>Descripción:</strong><p class="text-muted mt-1"><?=h($t['descripcion']??'')?></p></div>
      </div>
    </div>
  </div>

  <?php if (isSuper()): ?>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header-ti"><i class="bi bi-tools me-2"></i>Actualizar Estado</div>
      <div class="card-body">
        <div class="sup-pending mb-3">
          <i class="bi bi-info-circle me-1"></i>
          Como Supervisor Técnico, tus cambios serán enviados al Administrador para aprobación antes de aplicarse.
        </div>
        <form method="POST" action="tickets.php">
          <input type="hidden" name="id" value="<?=$id?>">
          <input type="hidden" name="equipo_id" value="<?=$t['equipo_id']?>">
          <input type="hidden" name="usuario_id" value="<?=$t['usuario_id']?>">
          <input type="hidden" name="tipo_servicio" value="<?=$t['tipo_servicio']?>">
          <input type="hidden" name="prioridad" value="<?=$t['prioridad']?>">
          <input type="hidden" name="descripcion" value="<?=h($t['descripcion']??'')?>">
          <div class="mb-3">
            <label class="form-label">Nuevo Estado</label>
            <select name="estado" class="form-select">
              <?php foreach(['EN_PROCESO','RESUELTO'] as $es): ?><option value="<?=$es?>" <?=$t['estado']===$es?'selected':''?>><?=h(str_replace('_',' ',$es))?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Observaciones del Técnico</label>
            <textarea name="tecnico_notas" class="form-control" rows="3" placeholder="Describe las acciones realizadas…"></textarea>
          </div>
          <button type="submit" class="btn btn-warning w-100">
            <i class="bi bi-send me-1"></i>Enviar para Aprobación
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal asignar (solo admin) -->
<?php if (isAdmin()): ?>
<div class="modal fade" id="modalAsignar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header modal-header-ti">
        <h6 class="modal-title"><i class="bi bi-person-check me-2"></i>Asignar Ticket a Supervisor Técnico</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="tickets.php?action=asignar&id=<?=$id?>">
        <div class="modal-body">
          <p class="text-muted small">Selecciona el Supervisor Técnico al que deseas asignar este ticket.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Supervisor Técnico *</label>
            <select name="supervisor_id" class="form-select" required>
              <option value="">— Seleccionar supervisor —</option>
              <?php foreach ($supervisores as $sup): ?>
              <option value="<?=$sup['id']?>" <?=(int)($t['asignado_a_suser']??0)==(int)$sup['id']?'selected':''?>>
                <?=h($sup['nombre_completo'])?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="alert alert-warning py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            El ticket cambiará a estado <strong>EN PROCESO</strong> y se notificará al supervisor seleccionado.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-person-check me-1"></i>Asignar Ticket</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
