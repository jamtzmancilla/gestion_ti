<?php
require_once 'includes/auth.php';
requireRol(ROL_ADMIN, ROL_SUPER);

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)($_POST['id'] ?? 0) ?: null;
    $saved = saveUsuario($_POST, $pid);
    auditLog($pid ? 'EDITAR_USUARIO' : 'CREAR_USUARIO', 'usuarios', $saved);
    flash('success', $pid ? 'Usuario actualizado.' : 'Usuario creado.');
    redirect('usuarios.php?action=view&id='.$saved);
}
if ($action === 'delete' && $id) {
    requireRol(ROL_ADMIN);
    deleteUsuario($id);
    auditLog('DESACTIVAR_USUARIO','usuarios',$id);
    flash('warning','Usuario desactivado.');
    redirect('usuarios.php');
}

$pageTitle = 'Usuarios';
include 'includes/header.php';

$areas   = getAreas();
$puestos = getPuestos();
$q       = trim($_GET['q'] ?? '');
$areaFil = (int)($_GET['area'] ?? 0);
?>
<style>
.avatar-circle-lg { width:58px;height:58px;border-radius:50%;background:rgba(255,255,255,.22);
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.3rem;
  color:#fff;border:3px solid rgba(255,255,255,.4);flex-shrink:0;letter-spacing:-1px; }
.section-divider { border-bottom:2px solid #e9ecef;margin-bottom:4px;padding-bottom:4px; }
.section-label { font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1a3a5c; }
.ext-ip-badge { background:#c0392b;color:#fff;font-weight:700;border-radius:5px;padding:3px 10px;font-size:.82rem; }
</style>

<div class="container-fluid px-3 py-3">

<?php if ($action === 'list'): ?>
<!-- ══ LISTA ══ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-people me-2"></i>Usuarios del Sistema</h5>
  <div class="d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer me-1"></i>Imprimir
    </button>
    <a href="directorio.php" class="btn btn-sm btn-outline-info no-print">
      <i class="bi bi-telephone-fill me-1"></i>Directorio IP
    </a>
    <?php if (puede('crear_usuario')): ?>
    <a href="usuarios.php?action=new" class="btn btn-sm no-print" style="background:#1a3a5c;color:#fff;">
      <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filtros -->
<div class="card mb-3"><div class="card-body py-2">
  <form id="form-search" method="GET" action="usuarios.php" class="row g-2 align-items-end">
    <div class="col-sm-4">
      <input type="search" name="q" id="input-search" class="form-control form-control-sm"
             placeholder="Nombre, ext. IP, email, cubículo…" value="<?= h($q) ?>">
    </div>
    <div class="col-sm-3">
      <select name="area" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Todas las áreas —</option>
        <?php foreach ($areas as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $areaFil==(int)$a['id']?'selected':'' ?>><?= h($a['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
      <?php if ($q||$areaFil): ?><a href="usuarios.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
    </div>
  </form>
</div></div>

<?php
$db = getDB(); $like='%'.$q.'%';
$sql = "SELECT u.*, a.nombre area_nombre, p.nombre puesto_nombre, p.nivel puesto_nivel,
        (SELECT COUNT(*) FROM equipos e WHERE e.usuario_id=u.id AND e.activo=1) n_equipos
        FROM usuarios u
        LEFT JOIN areas a ON a.id=u.area_id
        LEFT JOIN cat_puestos p ON p.id=u.puesto_id
        WHERE u.activo=1";
$params=[];
if($q){
    $sql.=" AND (u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ? OR u.usuario_windows LIKE ?
                 OR u.extension_ip LIKE ? OR u.cubículo LIKE ? OR u.oficina LIKE ? OR u.puesto LIKE ?)";
    $params=array_fill(0,8,$like);
}
if($areaFil){$sql.=" AND u.area_id=?";$params[]=$areaFil;}
$sql.=" ORDER BY a.nombre, u.nombre";
$st=$db->prepare($sql);$st->execute($params);$users=$st->fetchAll();
?>

<div class="card">
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-ti mb-0">
  <thead><tr>
    <th>Nombre</th><th>Puesto</th><th>Área</th>
    <th>Ext. IP</th><th>Cubículo / Oficina</th>
    <th>Tel. Directo</th><th>Email</th>
    <th class="text-center">Equipos</th><th class="no-print">Acc.</th>
  </tr></thead>
  <tbody>
  <?php foreach ($users as $u):
    $nb=['DIRECTIVO'=>'bg-danger','SUBDIRECTIVO'=>'bg-warning text-dark','JEFATURA'=>'bg-primary',
         'COORDINACION'=>'bg-info text-dark','OPERATIVO'=>'bg-success','APOYO'=>'bg-secondary'][$u['puesto_nivel']??'']??'bg-secondary';
  ?>
  <tr style="cursor:pointer" onclick="location.href='usuarios.php?action=view&id=<?=$u['id']?>'">
    <td>
      <strong><?=h($u['nombre'].' '.($u['apellidos']??''))?></strong>
      <?php if($u['usuario_windows']): ?><br><code class="text-muted" style="font-size:.68rem;"><?=h($u['usuario_windows'])?></code><?php endif; ?>
    </td>
    <td>
      <?php if($u['puesto_nombre']): ?><span class="badge <?=$nb?>" style="font-size:.65rem;"><?=h($u['puesto_nombre'])?></span>
      <?php elseif($u['puesto']): ?><span class="small text-muted"><?=h($u['puesto'])?></span><?php endif; ?>
    </td>
    <td class="small"><?=h($u['area_nombre']??'')?></td>
    <td><?php if($u['extension_ip']): ?><span class="ext-ip-badge"><?=h($u['extension_ip'])?></span><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
    <td class="small">
      <?php $ubi=array_filter([$u['cubículo']?'<i class="bi bi-grid-1x2 me-1 text-muted"></i>'.h($u['cubículo']):'', $u['oficina']?'<i class="bi bi-building me-1 text-muted"></i>'.h($u['oficina']):'']); echo implode(' · ',$ubi)?:'<span class="text-muted">—</span>'; ?>
    </td>
    <td class="small text-primary"><?=h($u['telefono_directo']??$u['telefono']??'')?></td>
    <td class="small"><?=h($u['email']??'')?></td>
    <td class="text-center"><span class="badge bg-primary"><?=$u['n_equipos']?></span></td>
    <td class="no-print" onclick="event.stopPropagation()">
      <a href="usuarios.php?action=edit&id=<?=$u['id']?>" class="btn btn-xs btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
      <?php if(puede('eliminar_usuario')): ?>
      <button onclick="confirmDelete('usuarios.php?action=delete&id=<?=$u['id']?>','<?=addslashes($u['nombre'])?>')" class="btn btn-xs btn-outline-danger"><i class="bi bi-person-x"></i></button>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($users)): ?><tr><td colspan="9" class="text-center text-muted py-4">Sin usuarios registrados</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div></div>
<div class="text-muted small mt-2 px-1"><?=count($users)?> usuario(s)</div>

<?php elseif(in_array($action,['new','edit'])): ?>
<!-- ══ FORM ══ -->
<?php $u=$action==='edit'&&$id?getUsuarioById($id):[]; $isEdit=!empty($u); ?>
<div class="row justify-content-center">
<div class="col-xl-9">
<div class="card">
  <div class="card-header-ti">
    <i class="bi bi-person<?=$isEdit?'-gear':'-plus'?> me-2"></i>
    <?=$isEdit?'Editar Usuario — '.h($u['nombre'].' '.($u['apellidos']??'')):'Nuevo Usuario'?>
  </div>
  <div class="card-body">
    <form method="POST" action="usuarios.php">
      <?php if($isEdit): ?><input type="hidden" name="id" value="<?=$u['id']?>"><?php endif; ?>

      <!-- Datos personales -->
      <p class="section-label"><i class="bi bi-person me-1"></i>Datos Personales</p>
      <div class="row g-3 mb-4">
        <div class="col-md-5"><label class="form-label">Nombre(s) <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control" value="<?=h($u['nombre']??'')?>" required></div>
        <div class="col-md-5"><label class="form-label">Apellidos</label>
          <input type="text" name="apellidos" class="form-control" value="<?=h($u['apellidos']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?=h($u['email']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control" value="<?=h($u['telefono']??'')?>"></div>
        <div class="col-md-4"><label class="form-label">Teléfono Directo</label>
          <input type="text" name="telefono_directo" class="form-control" value="<?=h($u['telefono_directo']??'')?>" placeholder="Ej. 2288423661"></div>
      </div>

      <!-- Puesto y ubicación -->
      <p class="section-label"><i class="bi bi-building me-1"></i>Puesto y Ubicación</p>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">Área / Departamento</label>
          <select name="area_id" class="form-select">
            <option value="">— Sin área —</option>
            <?php foreach($areas as $a): ?>
            <option value="<?=$a['id']?>" <?=(int)($u['area_id']??0)==(int)$a['id']?'selected':''?>><?=h($a['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Puesto (Catálogo)
            <a href="puestos.php" target="_blank" class="btn btn-xs btn-outline-secondary ms-1" title="Gestionar catálogo"><i class="bi bi-gear"></i></a>
          </label>
          <select name="puesto_id" class="form-select">
            <option value="">— Sin puesto del catálogo —</option>
            <?php
            $nivelIcons=['DIRECTIVO'=>'🔴 ','SUBDIRECTIVO'=>'🟠 ','JEFATURA'=>'🔵 ','COORDINACION'=>'🟢 ','OPERATIVO'=>'⚪ ','APOYO'=>'⚫ '];
            foreach($puestos as $p): ?>
            <option value="<?=$p['id']?>" <?=(int)($u['puesto_id']??0)==(int)$p['id']?'selected':''?>>
              <?=($nivelIcons[$p['nivel']]??'')?><?=h($p['nombre'])?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Puesto (Texto libre)</label>
          <input type="text" name="puesto" class="form-control" value="<?=h($u['puesto']??'')?>" placeholder="Ej. ENCARGADA DPI">
        </div>
        <div class="col-md-3">
          <label class="form-label"><i class="bi bi-grid-1x2 me-1"></i>Cubículo</label>
          <input type="text" name="cubículo" class="form-control" value="<?=h($u['cubículo']??'')?>" placeholder="Ej. CUB 3 ENTORNO">
        </div>
        <div class="col-md-3">
          <label class="form-label"><i class="bi bi-building me-1"></i>Oficina</label>
          <input type="text" name="oficina" class="form-control" value="<?=h($u['oficina']??'')?>" placeholder="Ej. OFNA. RECURSOS HUMANOS">
        </div>
      </div>

      <!-- Extensión IP -->
      <p class="section-label"><i class="bi bi-telephone me-1"></i>Extensión Telefónica IP</p>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label"><span class="ext-ip-badge">IP</span>&nbsp;Extensión IP</label>
          <div class="input-group">
            <span class="input-group-text" style="background:#c0392b;border-color:#c0392b;"><i class="bi bi-telephone-fill text-white"></i></span>
            <input type="text" name="extension_ip" class="form-control" value="<?=h($u['extension_ip']??'')?>" placeholder="Ej. 19050" maxlength="20">
          </div>
          <div class="form-text">Número de extensión del teléfono IP</div>
        </div>
        <div class="col-md-8">
          <label class="form-label">Nota</label>
          <input type="text" class="form-control" readonly
                 value="La extensión se vinculará automáticamente al Directorio Telefónico IP"
                 style="background:#f8f9fa;color:#6c757d;">
        </div>
      </div>

      <!-- Acceso al sistema -->
      <p class="section-label"><i class="bi bi-shield-lock me-1"></i>Acceso al Sistema</p>
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label">Usuario Windows</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-windows"></i></span>
            <input type="text" name="usuario_windows" class="form-control" value="<?=h($u['usuario_windows']??'')?>" placeholder="Ej. hsantillano">
          </div>
        </div>
        <div class="col-md-5">
          <label class="form-label">Contraseña (referencia)</label>
          <input type="text" name="contrasena_ref" class="form-control" value="<?=h($u['contrasena_ref']??'')?>" placeholder="⚠ No usar en producción">
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn" style="background:#1a3a5c;color:#fff;">
          <i class="bi bi-check-lg me-1"></i><?=$isEdit?'Guardar Cambios':'Crear Usuario'?>
        </button>
        <a href="<?=$isEdit?'usuarios.php?action=view&id='.$id:'usuarios.php'?>" class="btn btn-outline-secondary">Cancelar</a>
        <?php if($isEdit&&$u['extension_ip']): ?>
        <a href="directorio.php?buscar=<?=urlencode($u['extension_ip']??'')?>" class="btn btn-outline-info ms-auto no-print">
          <i class="bi bi-telephone-fill me-1"></i>Ver en Directorio IP
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div></div></div>

<?php elseif($action==='view'&&$id): ?>
<!-- ══ DETALLE ══ -->
<?php
$u=getUsuarioById($id);
if(!$u){flash('danger','No encontrado.');redirect('usuarios.php');}
$equiposU=array_filter(getEquipos(),fn($e)=>$e['usuario_id']==$id);
$invU=getInventario(usuario:$id);
$ticketsU=array_filter(getTickets(),fn($t)=>$t['usuario_id']==$id);
$nb=['DIRECTIVO'=>'bg-danger','SUBDIRECTIVO'=>'bg-warning text-dark','JEFATURA'=>'bg-primary',
     'COORDINACION'=>'bg-info text-dark','OPERATIVO'=>'bg-success','APOYO'=>'bg-secondary'][$u['puesto_nivel']??'']??'bg-secondary';
?>

<!-- Header perfil -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3 p-3 rounded"
     style="background:linear-gradient(135deg,#1a3a5c,#2563a8);color:#fff;">
  <div class="d-flex align-items-center gap-3">
    <div class="avatar-circle-lg"><?=strtoupper(substr($u['nombre'],0,1).substr($u['apellidos']??'',0,1))?></div>
    <div>
      <h4 class="mb-1"><?=h($u['nombre'].' '.($u['apellidos']??''))?></h4>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if($u['puesto_nombre']): ?><span class="badge <?=$nb?>"><?=h($u['puesto_nombre'])?></span><?php elseif($u['puesto']): ?><span class="badge bg-secondary"><?=h($u['puesto'])?></span><?php endif; ?>
        <?php if($u['area_nombre']): ?><span class="badge bg-light text-dark"><?=h($u['area_nombre'])?></span><?php endif; ?>
        <?php if($u['extension_ip']): ?><span class="badge" style="background:#e8ff47;color:#000;font-weight:700;"><i class="bi bi-telephone-fill me-1"></i>Ext. <?=h($u['extension_ip'])?></span><?php endif; ?>
        <?php if($u['cubículo']||$u['oficina']): ?><span class="badge bg-light text-dark"><i class="bi bi-geo-alt me-1"></i><?=h($u['cubículo']?:$u['oficina'])?></span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap no-print">
    <a href="usuarios.php?action=edit&id=<?=$id?>" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Editar</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-light"><i class="bi bi-printer me-1"></i>Imprimir</button>
    <a href="tickets.php?action=new" class="btn btn-sm btn-danger"><i class="bi bi-ticket me-1"></i>Nuevo Ticket</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <!-- Datos personales -->
    <div class="card mb-3">
      <div class="card-header-ti"><i class="bi bi-person me-2"></i>Información Personal</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <?php $rows=[
            ['Nombre',h($u['nombre'].' '.($u['apellidos']??''))],
            ['Puesto (catálogo)',$u['puesto_nombre']?'<span class="badge '.$nb.'">'.h($u['puesto_nombre']).'</span>':null],
            ['Puesto',h($u['puesto']??'')],
            ['Área',h($u['area_nombre']??'')],
            ['Cubículo',$u['cubículo']?'<i class="bi bi-grid-1x2 me-1 text-muted"></i>'.h($u['cubículo']):null],
            ['Oficina',$u['oficina']?'<i class="bi bi-building me-1 text-muted"></i>'.h($u['oficina']):null],
          ];
          foreach($rows as [$k,$v]): if(!$v) continue; ?>
          <tr><th class="small text-muted ps-3" style="width:40%"><?=$k?></th><td class="small pe-3"><?=$v?></td></tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <!-- Contacto / Extensión IP -->
    <div class="card mb-3">
      <div class="card-header-ti"><i class="bi bi-telephone me-2"></i>Contacto y Extensión IP</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <?php $cr=[
            ['Extensión IP',$u['extension_ip']?'<span class="ext-ip-badge">'.h($u['extension_ip']).'</span>':null],
            ['Tel. Directo',$u['telefono_directo']?'<a href="tel:'.h($u['telefono_directo']).'">'.h($u['telefono_directo']).'</a>':null],
            ['Tel. Alternativo',$u['telefono']?h($u['telefono']):null],
            ['Email',$u['email']?'<a href="mailto:'.h($u['email']).'">'.h($u['email']).'</a>':null],
          ];
          foreach($cr as [$k,$v]): if(!$v) continue; ?>
          <tr><th class="small text-muted ps-3" style="width:42%"><?=$k?></th><td class="small pe-3"><?=$v?></td></tr>
          <?php endforeach; ?>
        </table>
        <?php if($u['extension_ip']): ?>
        <div class="px-3 pb-2 mt-1">
          <a href="directorio.php?buscar=<?=urlencode($u['extension_ip']??'')?>" class="btn btn-sm btn-outline-primary w-100 no-print">
            <i class="bi bi-telephone-fill me-1"></i>Ver en Directorio IP
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- Acceso -->
    <div class="card">
      <div class="card-header-ti"><i class="bi bi-shield-lock me-2"></i>Acceso al Sistema</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tr><th class="small text-muted ps-3">Usuario Windows</th><td class="small pe-3"><code><?=h($u['usuario_windows']??'—')?></code></td></tr>
          <?php if(isAdmin()):
            $sc=$db->prepare("SELECT username,rol FROM system_users WHERE usuario_id=? LIMIT 1");
            $sc->execute([$id]); $sc=$sc->fetch(); ?>
          <tr><th class="small text-muted ps-3">Cuenta Sistema</th><td class="small pe-3"><?=$sc?'<code>'.h($sc['username']).'</code> '.rolBadge($sc['rol']):'<span class="text-muted">Sin cuenta</span>'?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <!-- Equipos -->
    <div class="card mb-3">
      <div class="card-header-ti d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pc-display me-2"></i>Equipos Asignados (<?=count($equiposU)?>)</span>
        <?php if(puede('crear_equipo')): ?>
        <a href="equipos.php?action=new" class="btn btn-sm btn-outline-light py-0 no-print"><i class="bi bi-plus me-1"></i>Asignar</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-ti mb-0">
          <thead><tr><th>Folio</th><th>Tipo</th><th>Marca / Modelo</th><th>IP</th><th>MAC</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach($equiposU as $e):
            $cls=['ACTIVO'=>'badge-activo','MANTENIMIENTO'=>'badge-mantenimiento','BAJA'=>'badge-baja','INACTIVO'=>'badge-inactivo'][$e['estado']]??'bg-secondary';
          ?>
          <tr style="cursor:pointer" onclick="location.href='equipos.php?action=view&id=<?=$e['id']?>'">
            <td><code class="text-primary"><?=h($e['folio'])?></code></td>
            <td class="small"><i class="bi <?=h($e['tipo_icono']??'bi-pc-display')?> me-1"></i><?=h($e['tipo_nombre']??'')?></td>
            <td><strong><?=h($e['marca'])?></strong> <span class="text-muted small"><?=h($e['modelo']??'')?></span></td>
            <td><code class="small"><?=h($e['ip']??'')?></code></td>
            <td><code style="font-size:.67rem;"><?=h($e['mac_address']??'')?></code></td>
            <td><span class="badge <?=$cls?>"><?=h($e['estado'])?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($equiposU)): ?><tr><td colspan="6" class="text-center text-muted py-3 small">Sin equipos asignados</td></tr><?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- Periféricos -->
    <?php if(!empty($invU)): ?>
    <div class="card mb-3">
      <div class="card-header-ti"><i class="bi bi-boxes me-2"></i>Periféricos / Inventario (<?=count($invU)?>)</div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Tipo</th><th>Marca/Modelo</th><th>Serie</th><th>Equipo</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach($invU as $i): $ic=['BUENO'=>'badge-bueno','REGULAR'=>'badge-regular','MALO'=>'badge-malo','BAJA'=>'badge-baja'][$i['estado']]??'bg-secondary'; ?>
          <tr><td><?=h($i['tipo'])?></td><td><?=h($i['marca'].' '.($i['modelo']??''))?></td><td><code class="small"><?=h($i['serie']??'')?></code></td><td><code class="small"><?=h($i['equipo_folio']??'—')?></code></td><td><span class="badge <?=$ic?>"><?=h($i['estado'])?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tickets -->
    <div class="card">
      <div class="card-header-ti d-flex justify-content-between align-items-center">
        <span><i class="bi bi-ticket me-2"></i>Tickets (<?=count($ticketsU)?>)</span>
        <a href="tickets.php?action=new" class="btn btn-sm btn-outline-light py-0 no-print"><i class="bi bi-plus me-1"></i>Nuevo</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-ti mb-0">
          <thead><tr><th>Folio</th><th>Tipo</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Fecha</th></tr></thead>
          <tbody>
          <?php foreach(array_slice($ticketsU,0,10) as $t):
            $pc=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
            $ec=['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
          ?>
          <tr style="cursor:pointer" onclick="location.href='tickets.php?action=view&id=<?=$t['id']?>'">
            <td><code class="text-primary small"><?=h($t['folio_ticket'])?></code></td>
            <td class="small"><?=h(str_replace('_',' ',$t['tipo_servicio']))?></td>
            <td class="small text-muted"><?=h(mb_substr($t['descripcion']??'',0,45))?></td>
            <td><span class="badge <?=$pc?>"><?=h($t['prioridad'])?></span></td>
            <td><span class="badge <?=$ec?>"><?=h(str_replace('_',' ',$t['estado']))?></span></td>
            <td class="small"><?=h(substr($t['fecha_apertura']??'',0,10))?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($ticketsU)): ?><tr><td colspan="6" class="text-center text-muted py-3 small">Sin tickets</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
