<?php
require_once 'includes/auth.php';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST'){ $pid=(int)($_POST['id']??0)?:null; $saved=saveInventario($_POST,$pid); flash('success',$pid?'Ítem actualizado.':'Ítem agregado.'); redirect('inventario.php'); }
if ($action==='delete'&&$id){ deleteInventario($id); flash('warning','Ítem eliminado.'); redirect('inventario.php'); }
$pageTitle='Inventario'; include 'includes/header.php';
$equipos=getEquipos(); $usuarios=getUsuarios(); $areas=getAreas();
$q=trim($_GET['q']??''); $eqF=(int)($_GET['equipo']??0); $usrF=(int)($_GET['usuario']??0);
?>
<div class="container-fluid px-3 py-3">
<?php if($action==='list'): ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-boxes me-2"></i>Inventario de Periféricos y Accesorios</h5>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print"><i class="bi bi-printer me-1"></i>Imprimir</button>
    <a href="exports/export_inventario_xls.php" class="btn btn-sm btn-outline-success no-print"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <a href="inventario.php?action=new" class="btn btn-sm no-print" style="background:#1a3a5c;color:#fff;"><i class="bi bi-plus me-1"></i>Agregar Ítem</a>
  </div>
</div>
<!-- Filtros -->
<div class="card mb-3"><div class="card-body py-2"><form id="form-search" method="GET" class="row g-2 align-items-end">
  <div class="col-sm-3"><input type="search" name="q" id="input-search" class="form-control form-control-sm" placeholder="Tipo, marca, serie…" value="<?=h($q)?>"></div>
  <div class="col-sm-3"><select name="equipo" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">— Equipo —</option><?php foreach($equipos as $e): ?><option value="<?=$e['id']?>" <?=$eqF==(int)$e['id']?'selected':''?>><?=h($e['folio'].' '.$e['marca'])?></option><?php endforeach; ?></select></div>
  <div class="col-sm-3"><select name="usuario" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">— Usuario —</option><?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=$usrF==(int)$u['id']?'selected':''?>><?=h($u['nombre'].' '.($u['apellidos']??''))?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button><?php if($q||$eqF||$usrF): ?><a href="inventario.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?></div>
</form></div></div>

<?php $inv=getInventario($q,$eqF,$usrF); ?>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-ti mb-0">
<thead><tr><th>#</th><th>Tipo</th><th>Marca / Modelo</th><th>Serie</th><th>Equipo Asignado</th><th>Usuario</th><th>Área</th><th>Cant.</th><th>Estado</th><th>Notas</th><th class="no-print">Acc.</th></tr></thead>
<tbody>
<?php foreach($inv as $i):
  $cls=['BUENO'=>'badge-bueno','REGULAR'=>'badge-regular','MALO'=>'badge-malo','BAJA'=>'badge-baja'][$i['estado']]??'bg-secondary';
?>
<tr>
  <td><?=$i['id']?></td>
  <td><strong><?=h($i['tipo'])?></strong></td>
  <td><?=h($i['marca'].' '.($i['modelo']??''))?></td>
  <td><code class="small"><?=h($i['serie']??'')?></code></td>
  <td><code class="small"><?=h($i['equipo_folio']??'—')?></code></td>
  <td class="small"><?=h(trim($i['usuario_nombre']??''))?></td>
  <td class="small"><?=h($i['area_nombre']??'')?></td>
  <td class="text-center"><?=$i['cantidad']?></td>
  <td><span class="badge <?=$cls?>"><?=h($i['estado'])?></span></td>
  <td class="small text-muted"><?=h(mb_substr($i['notas']??'',0,40))?></td>
  <td class="no-print">
    <a href="inventario.php?action=edit&id=<?=$i['id']?>" class="btn btn-xs btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
    <button onclick="confirmDelete('inventario.php?action=delete&id=<?=$i['id']?>','<?=addslashes($i['tipo'])?>')" class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
  </td>
</tr>
<?php endforeach; if(empty($inv)): ?><tr><td colspan="11" class="text-center text-muted py-4">Sin ítems en inventario</td></tr><?php endif; ?>
</tbody></table></div></div></div>

<?php elseif(in_array($action,['new','edit'])): ?>
<?php $inv=$action==='edit'&&$id?getInventarioById($id):[]; $isEdit=!empty($inv); $eqPre=(int)($_GET['equipo_id']??$inv['equipo_id']??0); ?>
<div class="row justify-content-center"><div class="col-xl-7"><div class="card">
  <div class="card-header-ti"><i class="bi bi-<?=$isEdit?'pencil':'plus-circle'?> me-2"></i><?=$isEdit?'Editar':'Agregar'?> Ítem de Inventario</div>
  <div class="card-body">
    <form method="POST" action="inventario.php">
      <?php if($isEdit): ?><input type="hidden" name="id" value="<?=$inv['id']?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Tipo *</label>
          <input type="text" name="tipo" class="form-control" list="tipos-inv" value="<?=h($inv['tipo']??'')?>" required placeholder="MONITOR, TECLADO, MOUSE…">
          <datalist id="tipos-inv"><?php foreach(['MONITOR','TECLADO','MOUSE','HEADSET','WEBCAM','UPS/NO-BREAK','IMPRESORA','ESCÁNER','HUB/SWITCH','LECTOR CÓDIGO','DIADEMA','MEMORIA USB','DISCO EXTERNO'] as $tp): ?><option><?=$tp?></option><?php endforeach; ?></datalist>
        </div>
        <div class="col-md-6"><label class="form-label">Marca</label><input type="text" name="marca" class="form-control" value="<?=h($inv['marca']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Modelo</label><input type="text" name="modelo" class="form-control" value="<?=h($inv['modelo']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">No. Serie</label><input type="text" name="serie" class="form-control" value="<?=h($inv['serie']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Equipo Asociado</label><select name="equipo_id" class="form-select"><option value="">— Ninguno —</option><?php foreach($equipos as $e): ?><option value="<?=$e['id']?>" <?=(int)($inv['equipo_id']??$eqPre)==(int)$e['id']?'selected':''?>><?=h($e['folio'].' — '.$e['marca'].' '.($e['modelo']??''))?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Usuario Asignado</label><select name="usuario_id" class="form-select"><option value="">— Ninguno —</option><?php foreach($usuarios as $u): ?><option value="<?=$u['id']?>" <?=(int)($inv['usuario_id']??0)==(int)$u['id']?'selected':''?>><?=h($u['nombre'].' '.($u['apellidos']??''))?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Área</label><select name="area_id" class="form-select"><option value="">— Ninguna —</option><?php foreach($areas as $a): ?><option value="<?=$a['id']?>" <?=(int)($inv['area_id']??0)==(int)$a['id']?'selected':''?>><?=h($a['nombre'])?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Cantidad</label><input type="number" name="cantidad" class="form-control" min="1" value="<?=h($inv['cantidad']??1)?>"></div>
        <div class="col-md-4"><label class="form-label">Estado</label><select name="estado" class="form-select"><?php foreach(['BUENO','REGULAR','MALO','BAJA'] as $es): ?><option <?=($inv['estado']??'BUENO')===$es?'selected':''?>><?=$es?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="2"><?=h($inv['notas']??'')?></textarea></div>
      </div>
      <div class="d-flex gap-2 mt-4"><button type="submit" class="btn" style="background:#1a3a5c;color:#fff;"><i class="bi bi-check-lg me-1"></i>Guardar</button><a href="inventario.php" class="btn btn-outline-secondary">Cancelar</a></div>
    </form>
  </div>
</div></div></div>
<?php endif; ?>
</div><?php include 'includes/footer.php'; ?>
