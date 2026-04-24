<?php
require_once 'includes/auth.php';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($_POST['id'] ?? 0) ?: null;
    // Build checks array from POST
    $checks = [];
    $template = getChecklistTemplate();
    foreach ($template as $cat => $items) {
        foreach ($items as $idx => $item) {
            $key = 'check_'.md5($cat.'_'.$idx);
            $checks[] = [
                'categoria' => $cat,
                'item'      => $item,
                'resultado' => $_POST[$key.'_resultado'] ?? 'NO_APLICA',
                'notas'     => $_POST[$key.'_notas'] ?? '',
            ];
        }
    }

    // Supervisor: guarda como cambio pendiente si edita existente
    if (isSuper() && $pid) {
        $datosConChecks = array_merge($_POST, ['_checks' => $checks]);
        $cpId = guardarCambioPendiente('mantenimientos', $pid, 'UPDATE', $datosConChecks, 'Edición mantenimiento #'.$pid.' por supervisor');
        auditLog('EDITAR_MANT_PENDIENTE','mantenimientos',$pid,'Cambio pendiente #'.$cpId);
        flash('warning', 'Cambios enviados para aprobación del Administrador (#'.$cpId.').');
        redirect('mantenimiento.php?action=view&id='.$pid);
    }

    // Admin o supervisor creando nuevo
    $_POST['realizado_por_suser'] = suserIdActual();
    $saved = saveMantenimiento($_POST, $checks, $pid);
    auditLog($pid?'EDITAR_MANT':'CREAR_MANT','mantenimientos',$saved);
    flash('success', 'Mantenimiento guardado correctamente.');
    redirect('mantenimiento.php?action=view&id='.$saved);
}

if ($action === 'delete' && $id) {
    requireRol(ROL_ADMIN);
    getDB()->prepare("DELETE FROM mantenimientos WHERE id=?")->execute([$id]);
    auditLog('ELIMINAR_MANT', 'mantenimientos', $id);
    flash('warning', 'Mantenimiento eliminado.');
    redirect('mantenimiento.php');
}

$pageTitle = 'Mantenimiento';
include 'includes/header.php';

$equipos  = getEquipos();
$usuarios = getUsuarios();
$tickets  = getTickets();
$template = getChecklistTemplate();
?>
<div class="container-fluid px-3 py-3">

<?php if ($action === 'list'): ?>
<!-- ══ LISTA ══ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Mantenimientos</h5>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print"><i class="bi bi-printer me-1"></i>Imprimir</button>
    <a href="mantenimiento.php?action=new" class="btn btn-sm" style="background:#1a3a5c;color:#fff;"><i class="bi bi-plus me-1"></i>Nuevo Mantenimiento</a>
  </div>
</div>
<?php $mants = getMantenimientos(); ?>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-ti mb-0">
      <thead><tr><th>#</th><th>Equipo</th><th>Fecha</th><th>Tipo</th><th>Técnico</th><th>Observaciones</th><th class="no-print">Acciones</th></tr></thead>
      <tbody>
      <?php foreach($mants as $m): ?>
      <tr>
        <td><?=$m['id']?></td>
        <td><strong><?=h($m['equipo_folio'])?></strong><br><span class="text-muted small"><?=h($m['marca'].' '.($m['modelo']??''))?></span></td>
        <td><?=h($m['fecha'])?></td>
        <td><span class="badge bg-info text-dark"><?=h($m['tipo'])?></span></td>
        <td class="small"><?=h($m['tecnico']??'')?></td>
        <td class="small text-muted"><?=h(mb_substr($m['observaciones']??'',0,60))?></td>
        <td class="no-print">
          <a href="mantenimiento.php?action=view&id=<?=$m['id']?>" class="btn btn-xs btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
          <a href="mantenimiento.php?action=edit&id=<?=$m['id']?>" class="btn btn-xs btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
          <button onclick="confirmDelete('mantenimiento.php?action=delete&id=<?=$m['id']?>','Mantenimiento #<?=$m['id']?>')" class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($mants)): ?><tr><td colspan="7" class="text-center text-muted py-4">Sin registros de mantenimiento</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif (in_array($action,['new','edit'])): ?>
<!-- ══ FORM MANTENIMIENTO ══ -->
<?php
$m    = $action==='edit' && $id ? getMantenimientoById($id) : [];
$savedChecks = $id && $action==='edit' ? getChecks($id) : [];
$savedCheckMap = [];
foreach ($savedChecks as $sc) {
    $savedCheckMap[$sc['categoria'].'|'.$sc['item']] = $sc;
}
$equipoIdPre = (int)($_GET['equipo_id'] ?? $m['equipo_id'] ?? 0);
$isEdit = !empty($m);
?>
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <h5 class="mb-0"><i class="bi bi-clipboard-plus me-2"></i><?=$isEdit?'Editar':'Nueva'?> Orden de Mantenimiento</h5>
  <?php if (isSuper() && $isEdit): ?>
  <span class="sup-pending"><i class="bi bi-hourglass me-1"></i>Tus cambios requerirán aprobación del Administrador</span>
  <?php endif; ?>
</div>

<form method="POST" action="mantenimiento.php">
<?php if($isEdit): ?><input type="hidden" name="id" value="<?=$m['id']?>"><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header-ti"><i class="bi bi-info-circle me-2"></i>Datos Generales</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label">Equipo *</label>
          <select name="equipo_id" class="form-select" required>
            <option value="">— Seleccionar equipo —</option>
            <?php foreach($equipos as $e): ?><option value="<?=$e['id']?>" <?=(int)($m['equipo_id']??$equipoIdPre)==(int)$e['id']?'selected':''?>><?=h($e['folio'].' — '.$e['marca'].' '.($e['modelo']??''))?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Usuario del Equipo</label>
          <select name="usuario_id" class="form-select">
            <option value="">— Seleccionar —</option>
            <?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=(int)($m['usuario_id']??0)==(int)$u['id']?'selected':''?>><?=h($u['nombre'].' '.($u['apellidos']??''))?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Ticket Relacionado</label>
          <select name="ticket_id" class="form-select">
            <option value="">— Ninguno —</option>
            <?php foreach($tickets as $t): ?><option value="<?=$t['id']?>" <?=(int)($m['ticket_id']??0)==(int)$t['id']?'selected':''?>><?=h($t['folio_ticket'].' — '.$t['descripcion'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-select">
            <?php foreach(['PREVENTIVO','CORRECTIVO'] as $tp): ?><option <?=($m['tipo']??'PREVENTIVO')===$tp?'selected':''?>><?=$tp?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Fecha *</label>
          <input type="date" name="fecha" class="form-control" value="<?=h($m['fecha']??date('Y-m-d'))?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Técnico Responsable</label>
          <input type="text" name="tecnico" class="form-control" value="<?=h($m['tecnico']??'')?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Observaciones Generales</label>
          <textarea name="observaciones" class="form-control" rows="3"><?=h($m['observaciones']??'')?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header-ti d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-list-check me-2"></i>Checklist de Mantenimiento</span>
        <div class="d-flex gap-2 no-print">
          <button type="button" id="btn-mark-all-ok-global" class="btn btn-sm btn-success py-0">✓ Todo OK</button>
          <button type="button" id="btn-mark-all-na" class="btn btn-sm btn-outline-light py-0">N/A Todo</button>
        </div>
      </div>
      <div class="card-body p-2" style="max-height:600px;overflow-y:auto;">
        <?php foreach ($template as $cat => $items): ?>
        <div class="check-categoria d-flex justify-content-between align-items-center mb-1">
          <span><?=h($cat)?></span>
          <button type="button" class="btn btn-xs btn-light py-0 no-print btn-mark-all-ok" data-cat="<?=h($cat)?>">✓ Marcar todos OK</button>
        </div>
        <?php foreach ($items as $idx => $item):
          $key = 'check_'.md5($cat.'_'.$idx);
          $saved = $savedCheckMap[$cat.'|'.$item] ?? null;
          $resSaved = $saved['resultado'] ?? 'NO_APLICA';
          $notesSaved = $saved['notas'] ?? '';
        ?>
        <div class="check-row mb-1">
          <div class="item-label"><?=h($item)?></div>
          <div class="check-result">
            <select name="<?=$key?>_resultado" class="form-select form-select-sm check-sel" data-cat="<?=h($cat)?>">
              <?php foreach(['OK'=>'✓ OK','REQUIERE_ATENCION'=>'⚠ Requiere Atención','MAL'=>'✗ Mal','NO_APLICA'=>'— N/A'] as $rv=>$rl): ?>
              <option value="<?=$rv?>" <?=$resSaved===$rv?'selected':''?>><?=$rl?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="check-notes">
            <input type="text" name="<?=$key?>_notas" class="form-control form-control-sm" placeholder="Nota…" value="<?=h($notesSaved)?>">
          </div>
        </div>
        <?php endforeach; ?>
        <hr class="my-2">
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 no-print">
  <button type="submit" class="btn" style="background:#1a3a5c;color:#fff;"><i class="bi bi-check-lg me-1"></i>Guardar Mantenimiento</button>
  <a href="mantenimiento.php" class="btn btn-outline-secondary">Cancelar</a>
</div>
</form>

<?php elseif ($action === 'view' && $id): ?>
<!-- ══ DETALLE MANTENIMIENTO ══ -->
<?php
$m = getMantenimientoById($id);
if (!$m) { flash('danger','No encontrado.'); redirect('mantenimiento.php'); }
$checks = getChecks($id);
$checksByCat = [];
foreach ($checks as $c) { $checksByCat[$c['categoria']][] = $c; }
$clsTipo = $m['tipo']==='PREVENTIVO' ? 'bg-info text-dark' : 'bg-warning text-dark';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Orden de Mantenimiento #<?=$m['id']?></h5>
    <span class="badge <?=$clsTipo?>"><?=h($m['tipo'])?></span>
    <span class="badge bg-secondary ms-1"><?=h($m['fecha'])?></span>
  </div>
  <div class="d-flex gap-2 no-print">
    <a href="mantenimiento.php?action=edit&id=<?=$id?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="exports/export_mantenimiento_pdf.php?id=<?=$id?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir</button>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header-ti"><i class="bi bi-info-circle me-2"></i>Datos Generales</div>
      <div class="card-body">
        <?php $info=[['Equipo',$m['equipo_folio'].' — '.h($m['marca'].' '.($m['modelo']??''))],['Técnico',$m['tecnico']],['Fecha',$m['fecha']],['Tipo',$m['tipo']],['Usuario',$m['usuario_nombre']]]; ?>
        <table class="table table-sm mb-2">
          <?php foreach($info as [$k,$v]): if(!$v) continue; ?><tr><th class="small text-muted"><?=$k?></th><td class="small"><?=$v?></td></tr><?php endforeach; ?>
        </table>
        <?php if($m['observaciones']): ?>
        <div class="alert alert-light py-2 small"><strong>Observaciones:</strong><br><?=h($m['observaciones'])?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <?php foreach ($checksByCat as $cat => $items): ?>
    <div class="card mb-3">
      <div class="check-categoria" style="border-radius:8px 8px 0 0;"><?=h($cat)?></div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Elemento</th><th style="width:160px">Resultado</th><th>Notas</th></tr></thead>
          <tbody>
          <?php foreach($items as $c):
            $rclass=['OK'=>'text-success','MAL'=>'text-danger','REQUIERE_ATENCION'=>'text-warning','NO_APLICA'=>'text-muted'][$c['resultado']]??'text-muted';
            $ricon=['OK'=>'bi-check-circle-fill','MAL'=>'bi-x-circle-fill','REQUIERE_ATENCION'=>'bi-exclamation-triangle-fill','NO_APLICA'=>'bi-dash-circle'][$c['resultado']]??'bi-dash';
          ?>
          <tr>
            <td class="small"><?=h($c['item'])?></td>
            <td><span class="<?=$rclass?>"><i class="bi <?=$ricon?> me-1"></i><?=h(str_replace('_',' ',$c['resultado']))?></span></td>
            <td class="small text-muted"><?=h($c['notas']??'')?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Firma -->
<div class="row g-3 mt-2 no-print-hidden">
  <div class="col-md-4 offset-md-2 text-center">
    <div style="border-top:2px solid #333;padding-top:8px;margin-top:40px;">
      <strong class="small">TÉCNICO RESPONSABLE</strong><br>
      <span class="text-muted small"><?=h($m['tecnico']??'___________________________')?></span>
    </div>
  </div>
  <div class="col-md-4 text-center">
    <div style="border-top:2px solid #333;padding-top:8px;margin-top:40px;">
      <strong class="small">USUARIO / RESPONSABLE DEL EQUIPO</strong><br>
      <span class="text-muted small"><?=h($m['usuario_nombre']??'___________________________')?></span>
    </div>
  </div>
</div>

<?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
