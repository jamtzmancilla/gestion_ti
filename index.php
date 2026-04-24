<?php
require_once 'includes/auth.php';
$pageTitle = 'Dashboard';

// Usuario normal → redirigir a su vista de equipos
if (isNormal()) { redirect('equipos.php'); }

include 'includes/header.php';

// ── Supervisor: mostrar su panel personalizado ────────────
if (isSuper()):
  $misTickets   = getMisTicketsComoSupervisor();
  $abiertos     = array_filter($misTickets, fn($t)=>$t['estado']==='ABIERTO');
  $enProceso    = array_filter($misTickets, fn($t)=>$t['estado']==='EN_PROCESO');
  $misMants     = getMantenimientos();
  $pendingChgs  = getDB()->prepare("SELECT COUNT(*) FROM cambios_pendientes WHERE suser_id=? AND estado='PENDIENTE'");
  $pendingChgs->execute([suserIdActual()]); $pendingChgs = (int)$pendingChgs->fetchColumn();
?>
<div class="container-fluid px-3 py-3">

  <div class="d-flex align-items-center gap-3 mb-3">
    <div class="avatar-circle" style="width:44px;height:44px;font-size:1.2rem;background:#1a3a5c;">
      <?= strtoupper(substr($auth['nombre'],0,1)) ?>
    </div>
    <div>
      <h5 class="mb-0">Bienvenido, <?= h($auth['nombre']) ?></h5>
      <span class="badge bg-warning text-dark">SUPERVISOR TÉCNICO</span>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card bg-ti-red h-100">
      <div class="stat-val"><?= count($abiertos) ?></div>
      <div class="stat-lbl">Tickets Abiertos</div>
      <i class="bi bi-ticket stat-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card bg-ti-orange h-100">
      <div class="stat-val"><?= count($enProceso) ?></div>
      <div class="stat-lbl">En Proceso</div>
      <i class="bi bi-tools stat-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card bg-ti-blue h-100">
      <div class="stat-val"><?= count($misMants) ?></div>
      <div class="stat-lbl">Mantenimientos</div>
      <i class="bi bi-clipboard-check stat-icon"></i>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card bg-ti-purple h-100">
      <div class="stat-val"><?= $pendingChgs ?></div>
      <div class="stat-lbl">Cambios Pendientes</div>
      <i class="bi bi-hourglass stat-icon"></i>
    </div></div>
  </div>

  <?php if ($pendingChgs > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-hourglass-split fs-5"></i>
    <div>Tienes <strong><?= $pendingChgs ?></strong> cambio(s) en espera de aprobación del Administrador.</div>
  </div>
  <?php endif; ?>

  <!-- Mis Tickets asignados -->
  <div class="card mb-3">
    <div class="card-header-ti d-flex justify-content-between align-items-center">
      <span><i class="bi bi-ticket me-2"></i>Mis Tickets Asignados</span>
      <a href="tickets.php" class="btn btn-sm btn-outline-light py-0">Ver todos</a>
    </div>
    <div class="card-body p-0">
      <table class="table table-ti mb-0">
        <thead><tr><th>Folio</th><th>Equipo</th><th>Descripción</th><th>Prioridad</th><th>Estado</th><th>Apertura</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($misTickets,0,10) as $t):
          $prioC=['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
          $estC =['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
        ?>
        <tr>
          <td><code class="text-primary"><?=h($t['folio_ticket'])?></code></td>
          <td class="small"><?=h($t['equipo_folio'].' '.($t['equipo_marca']??''))?></td>
          <td class="small text-muted"><?=h(mb_substr($t['descripcion']??'',0,50))?></td>
          <td><span class="badge <?=$prioC?>"><?=h($t['prioridad'])?></span></td>
          <td><span class="badge <?=$estC?>"><?=h(str_replace('_',' ',$t['estado']))?></span></td>
          <td class="small"><?=h(substr($t['fecha_apertura']??'',0,10))?></td>
          <td>
            <a href="tickets.php?action=view&id=<?=$t['id']?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($misTickets)): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin tickets asignados</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Accesos rápidos -->
  <div class="card">
    <div class="card-header-ti"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</div>
    <div class="card-body d-flex flex-wrap gap-2 py-3">
      <a href="mantenimiento.php?action=new" class="btn btn-outline-warning btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Nuevo Mantenimiento</a>
      <a href="equipos.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-pc-display me-1"></i>Ver Equipos</a>
      <a href="inventario.php" class="btn btn-outline-success btn-sm"><i class="bi bi-boxes me-1"></i>Ver Inventario</a>
      <a href="exports/export_equipos_pdf.php" target="_blank" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-file-earmark-pdf me-1"></i>Reporte PDF</a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; return;
endif; // end isSuper

$s = getDashboardStats();
$tickets  = getTickets(estado:'ABIERTO') + getTickets(estado:'EN_PROCESO');
$ticketsR = array_slice(getTickets(), 0, 8);
?>
<div class="container-fluid px-3 py-3">

  <!-- KPI Row -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-blue h-100">
        <div class="stat-val"><?= $s['equipos_total'] ?></div>
        <div class="stat-lbl">Equipos Total</div>
        <i class="bi bi-pc-display stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-green h-100">
        <div class="stat-val"><?= $s['equipos_activos'] ?></div>
        <div class="stat-lbl">Equipos Activos</div>
        <i class="bi bi-check-circle stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-orange h-100">
        <div class="stat-val"><?= $s['equipos_mant'] ?></div>
        <div class="stat-lbl">En Mantenimiento</div>
        <i class="bi bi-tools stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-purple h-100">
        <div class="stat-val"><?= $s['usuarios_total'] ?></div>
        <div class="stat-lbl">Usuarios</div>
        <i class="bi bi-people stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-red h-100">
        <div class="stat-val"><?= (int)($s['tickets_stats']['ABIERTO']??0) + (int)($s['tickets_stats']['EN_PROCESO']??0) ?></div>
        <div class="stat-lbl">Tickets Activos</div>
        <i class="bi bi-ticket stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-card bg-ti-teal h-100">
        <div class="stat-val"><?= $s['ip_libres'] ?></div>
        <div class="stat-lbl">IPs Libres</div>
        <i class="bi bi-diagram-3 stat-icon"></i>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Resumen por tipo equipo -->
    <div class="col-md-5 col-xl-4">
      <div class="card h-100">
        <div class="card-header-ti d-flex justify-content-between align-items-center">
          <span><i class="bi bi-bar-chart me-2"></i>Equipos por Tipo</span>
          <a href="equipos.php" class="btn btn-sm btn-outline-light py-0">Ver todos</a>
        </div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Tipo</th><th class="text-center">Total</th><th class="text-center">Activos</th><th class="text-center">Bajas</th></tr></thead>
            <tbody>
            <?php foreach ($s['resumen_equipos'] as $r): ?>
            <tr>
              <td><strong><?= h($r['tipo']) ?></strong></td>
              <td class="text-center"><span class="badge bg-primary"><?= $r['total'] ?></span></td>
              <td class="text-center"><span class="badge bg-success"><?= $r['activos'] ?></span></td>
              <td class="text-center"><span class="badge bg-danger"><?= $r['bajas'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Estadísticas tickets -->
    <div class="col-md-3 col-xl-2">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-ticket me-2"></i>Tickets</div>
        <div class="card-body">
          <?php
          $tst = $s['tickets_stats'];
          $states = ['ABIERTO'=>['danger','Abiertos'],'EN_PROCESO'=>['warning','En Proceso'],'RESUELTO'=>['success','Resueltos'],'CANCELADO'=>['secondary','Cancelados']];
          foreach ($states as $k=>[$cls,$lbl]): ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small"><?= $lbl ?></span>
            <span class="badge bg-<?= $cls ?> text-<?= $cls==='warning'?'dark':'white' ?>"><?= $tst[$k]??0 ?></span>
          </div>
          <?php endforeach; ?>
          <hr>
          <a href="tickets.php?action=new" class="btn btn-sm w-100" style="background:#1a3a5c;color:#fff;">
            <i class="bi bi-plus me-1"></i>Nuevo Ticket
          </a>
        </div>
      </div>
    </div>

    <!-- Últimos tickets -->
    <div class="col-md-12 col-xl-6">
      <div class="card h-100">
        <div class="card-header-ti d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clock-history me-2"></i>Tickets Recientes</span>
          <a href="tickets.php" class="btn btn-sm btn-outline-light py-0">Ver todos</a>
        </div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Folio</th><th>Equipo</th><th>Tipo</th><th>Prioridad</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($ticketsR as $t):
              $prioClass = ['URGENTE'=>'badge-urgente','ALTA'=>'badge-alta','MEDIA'=>'badge-media','BAJA'=>'badge-baja-p'][$t['prioridad']]??'bg-secondary';
              $estClass  = ['ABIERTO'=>'badge-abierto','EN_PROCESO'=>'badge-en_proceso','RESUELTO'=>'badge-resuelto','CANCELADO'=>'badge-cancelado'][$t['estado']]??'bg-secondary';
            ?>
            <tr style="cursor:pointer" onclick="location.href='tickets.php?action=view&id=<?= $t['id'] ?>'">
              <td><code><?= h($t['folio_ticket']) ?></code></td>
              <td><?= h($t['equipo_folio'].' '.($t['equipo_marca']??'')) ?></td>
              <td class="small"><?= h(str_replace('_',' ',$t['tipo_servicio'])) ?></td>
              <td><span class="badge <?= $prioClass ?>"><?= h($t['prioridad']) ?></span></td>
              <td><span class="badge <?= $estClass ?>"><?= h(str_replace('_',' ',$t['estado'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($ticketsR)): ?><tr><td colspan="5" class="text-center text-muted py-3">Sin tickets</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick access -->
  <div class="row g-3 mt-1">
    <div class="col-12">
      <div class="card">
        <div class="card-header-ti"><i class="bi bi-lightning me-2"></i>Acceso Rápido</div>
        <div class="card-body d-flex flex-wrap gap-2 py-3">
          <a href="equipos.php?action=new"      class="btn btn-outline-primary btn-sm"><i class="bi bi-plus me-1"></i>Nuevo Equipo</a>
          <a href="usuarios.php?action=new"     class="btn btn-outline-purple btn-sm" style="border-color:#6f42c1;color:#6f42c1;"><i class="bi bi-person-plus me-1"></i>Nuevo Usuario</a>
          <a href="inventario.php?action=new"   class="btn btn-outline-success btn-sm"><i class="bi bi-plus me-1"></i>Nuevo Inventario</a>
          <a href="tickets.php?action=new"      class="btn btn-outline-danger btn-sm"><i class="bi bi-plus me-1"></i>Nuevo Ticket</a>
          <a href="mantenimiento.php?action=new" class="btn btn-outline-warning btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Nuevo Mantenimiento</a>
          <a href="reportes.php"                 class="btn btn-outline-info btn-sm"><i class="bi bi-bar-chart-line me-1"></i>Ver Reportes</a>
          <a href="exports/export_equipos_pdf.php" target="_blank" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-file-earmark-pdf me-1"></i>Reporte PDF</a>
          <a href="exports/export_equipos_xls.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Reporte Excel</a>
        </div>
      </div>
    </div>
  </div>

</div>
<?php include 'includes/footer.php'; ?>
