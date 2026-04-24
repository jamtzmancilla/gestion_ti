<?php
require_once 'includes/auth.php';
requireRol(ROL_ADMIN, ROL_SUPER);
$pageTitle = 'Reportes y Estadísticas';
include 'includes/header.php';

$db = getDB();

// ── Datos para gráficas ───────────────────────────────────

// Equipos por tipo
$equiposPorTipo = $db->query(
    "SELECT t.nombre, COUNT(e.id) total, SUM(e.estado='ACTIVO') activos,
     SUM(e.estado='MANTENIMIENTO') mant, SUM(e.estado='BAJA') bajas
     FROM equipos e JOIN tipos_equipo t ON t.id=e.tipo_id
     WHERE e.activo=1 GROUP BY t.id ORDER BY total DESC"
)->fetchAll();

// Equipos por área
$equiposPorArea = $db->query(
    "SELECT a.nombre, COUNT(e.id) total
     FROM equipos e JOIN areas a ON a.id=e.area_id
     WHERE e.activo=1 GROUP BY a.id ORDER BY total DESC LIMIT 10"
)->fetchAll();

// Tickets por mes (últimos 6 meses)
$ticketsPorMes = $db->query(
    "SELECT DATE_FORMAT(fecha_apertura,'%Y-%m') mes,
            COUNT(*) total,
            SUM(estado='RESUELTO') resueltos,
            SUM(estado='ABIERTO') abiertos
     FROM tickets
     WHERE fecha_apertura >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes ASC"
)->fetchAll();

// Mantenimientos por mes (últimos 6 meses)
$mantsPorMes = $db->query(
    "SELECT DATE_FORMAT(fecha,'%Y-%m') mes, COUNT(*) total,
            SUM(tipo='PREVENTIVO') preventivos, SUM(tipo='CORRECTIVO') correctivos
     FROM mantenimientos
     WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes ASC"
)->fetchAll();

// Estado tickets
$ticketsEstado = $db->query(
    "SELECT estado, COUNT(*) total FROM tickets GROUP BY estado"
)->fetchAll();

// Inventario por estado
$invEstado = $db->query(
    "SELECT estado, SUM(cantidad) total FROM inventario WHERE activo=1 GROUP BY estado"
)->fetchAll();

// Top 5 equipos con más tickets
$topEquipos = $db->query(
    "SELECT e.folio, e.marca, e.modelo, COUNT(t.id) total_tickets
     FROM tickets t JOIN equipos e ON e.id=t.equipo_id
     GROUP BY e.id ORDER BY total_tickets DESC LIMIT 5"
)->fetchAll();

// Tickets por prioridad
$ticketsPrio = $db->query(
    "SELECT prioridad, COUNT(*) total FROM tickets GROUP BY prioridad ORDER BY FIELD(prioridad,'URGENTE','ALTA','MEDIA','BAJA')"
)->fetchAll();

// Totales generales
$stats = [
    'equipos'      => $db->query("SELECT COUNT(*) FROM equipos WHERE activo=1")->fetchColumn(),
    'usuarios'     => $db->query("SELECT COUNT(*) FROM usuarios WHERE activo=1")->fetchColumn(),
    'tickets'      => $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'resueltos'    => $db->query("SELECT COUNT(*) FROM tickets WHERE estado='RESUELTO'")->fetchColumn(),
    'mants'        => $db->query("SELECT COUNT(*) FROM mantenimientos")->fetchColumn(),
    'inventario'   => $db->query("SELECT COALESCE(SUM(cantidad),0) FROM inventario WHERE activo=1")->fetchColumn(),
];
$stats['tasa_resolucion'] = $stats['tickets'] > 0
    ? round(($stats['resueltos'] / $stats['tickets']) * 100, 1) : 0;

// Helpers JSON para Chart.js
$jEquiposTipo  = json_encode(array_column($equiposPorTipo,'nombre'));
$jEquiposTot   = json_encode(array_map('intval', array_column($equiposPorTipo,'total')));
$jEquiposAct   = json_encode(array_map('intval', array_column($equiposPorTipo,'activos')));

$jAreaNom      = json_encode(array_column($equiposPorArea,'nombre'));
$jAreaTot      = json_encode(array_map('intval', array_column($equiposPorArea,'total')));

$jMeses        = json_encode(array_column($ticketsPorMes,'mes'));
$jTktTot       = json_encode(array_map('intval', array_column($ticketsPorMes,'total')));
$jTktRes       = json_encode(array_map('intval', array_column($ticketsPorMes,'resueltos')));

$jMantMes      = json_encode(array_column($mantsPorMes,'mes'));
$jMantPrev     = json_encode(array_map('intval', array_column($mantsPorMes,'preventivos')));
$jMantCorr     = json_encode(array_map('intval', array_column($mantsPorMes,'correctivos')));

$jTktEstLbl    = json_encode(array_column($ticketsEstado,'estado'));
$jTktEstTot    = json_encode(array_map('intval', array_column($ticketsEstado,'total')));

$jInvLbl       = json_encode(array_column($invEstado,'estado'));
$jInvTot       = json_encode(array_map('intval', array_column($invEstado,'total')));

$jPrioLbl      = json_encode(array_column($ticketsPrio,'prioridad'));
$jPrioTot      = json_encode(array_map('intval', array_column($ticketsPrio,'total')));
?>
<div class="container-fluid px-3 py-3">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Reportes y Estadísticas</h5>
    <div class="d-flex gap-2">
      <a href="exports/export_equipos_pdf.php" target="_blank" class="btn btn-sm btn-outline-danger no-print">
        <i class="bi bi-file-earmark-pdf me-1"></i>PDF Equipos
      </a>
      <a href="exports/export_equipos_xls.php" class="btn btn-sm btn-outline-success no-print">
        <i class="bi bi-file-earmark-excel me-1"></i>Excel Equipos
      </a>
      <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print">
        <i class="bi bi-printer me-1"></i>Imprimir
      </button>
    </div>
  </div>

  <!-- KPI Resumen -->
  <div class="row g-3 mb-4">
    <?php $kpis = [
      ['Equipos Registrados',  $stats['equipos'],           'bg-ti-blue',   'bi-pc-display'],
      ['Usuarios',             $stats['usuarios'],           'bg-ti-purple', 'bi-people'],
      ['Total Tickets',        $stats['tickets'],            'bg-ti-red',    'bi-ticket'],
      ['Tasa de Resolución',   $stats['tasa_resolucion'].'%','bg-ti-green',  'bi-check-circle'],
      ['Mantenimientos',       $stats['mants'],              'bg-ti-orange', 'bi-clipboard-check'],
      ['Ítems Inventario',     $stats['inventario'],         'bg-ti-teal',   'bi-boxes'],
    ]; foreach ($kpis as [$lbl,$val,$cls,$ico]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card <?= $cls ?> h-100">
        <div class="stat-val"><?= $val ?></div>
        <div class="stat-lbl"><?= $lbl ?></div>
        <i class="bi <?= $ico ?> stat-icon"></i>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Fila 1: Equipos por tipo + Equipos por área -->
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-bar-chart me-2"></i>Equipos por Tipo</div>
        <div class="card-body"><canvas id="chartTipos" height="220"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-building me-2"></i>Equipos por Área (Top 10)</div>
        <div class="card-body"><canvas id="chartAreas" height="220"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Fila 2: Tickets por mes + Mantenimientos por mes -->
  <div class="row g-3 mb-3">
    <div class="col-md-7">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-graph-up me-2"></i>Tickets por Mes (últimos 6 meses)</div>
        <div class="card-body"><canvas id="chartTicketsMes" height="200"></canvas></div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-clipboard-check me-2"></i>Mantenimientos por Mes</div>
        <div class="card-body"><canvas id="chartMantMes" height="200"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Fila 3: Estado tickets + Inventario + Prioridades -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-pie-chart me-2"></i>Estado de Tickets</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="chartTktEstado" style="max-height:220px;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-boxes me-2"></i>Inventario por Estado</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="chartInvEstado" style="max-height:220px;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-exclamation-triangle me-2"></i>Tickets por Prioridad</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="chartPrioridad" style="max-height:220px;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Fila 4: Top equipos con más tickets + tabla detalle equipos -->
  <div class="row g-3">
    <div class="col-md-5">
      <div class="card h-100">
        <div class="card-header-ti"><i class="bi bi-trophy me-2"></i>Top Equipos con más Tickets</div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>#</th><th>Folio</th><th>Equipo</th><th class="text-center">Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($topEquipos as $i => $e): ?>
            <tr>
              <td><span class="badge bg-<?= ['danger','warning','info','secondary','secondary'][$i] ?>"><?= $i+1 ?></span></td>
              <td><code><?= h($e['folio']) ?></code></td>
              <td><?= h($e['marca'].' '.($e['modelo']??'')) ?></td>
              <td class="text-center"><span class="badge bg-primary"><?= $e['total_tickets'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($topEquipos)): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin datos</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card h-100">
        <div class="card-header-ti d-flex justify-content-between align-items-center">
          <span><i class="bi bi-table me-2"></i>Resumen de Equipos por Tipo</span>
        </div>
        <div class="card-body p-0">
          <table class="table table-ti mb-0">
            <thead><tr><th>Tipo</th><th class="text-center">Total</th><th class="text-center">Activos</th><th class="text-center">En Mant.</th><th class="text-center">Bajas</th><th class="text-center">%</th></tr></thead>
            <tbody>
            <?php foreach ($equiposPorTipo as $r):
              $pct = $stats['equipos'] > 0 ? round($r['total']/$stats['equipos']*100,1) : 0;
            ?>
            <tr>
              <td><strong><?= h($r['nombre']) ?></strong></td>
              <td class="text-center"><span class="badge bg-primary"><?= $r['total'] ?></span></td>
              <td class="text-center"><span class="badge bg-success"><?= $r['activos'] ?></span></td>
              <td class="text-center"><span class="badge bg-warning text-dark"><?= $r['mant'] ?></span></td>
              <td class="text-center"><span class="badge bg-danger"><?= $r['bajas'] ?></span></td>
              <td class="text-center">
                <div class="progress" style="height:6px;min-width:60px;">
                  <div class="progress-bar" style="width:<?= $pct ?>%;background:#1a3a5c;"></div>
                </div>
                <small class="text-muted"><?= $pct ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const C = {
  primary:'#1a3a5c', accent:'#0d6efd', success:'#198754', danger:'#dc3545',
  warning:'#ffc107', info:'#0dcaf0', purple:'#6f42c1', teal:'#20c997',
  orange:'#fd7e14', gray:'#6c757d',
};
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = '#666';

// Paleta de colores
const palette = [C.primary,C.accent,C.success,C.danger,C.warning,C.purple,C.teal,C.orange,C.gray,'#e83e8c'];

// ── Equipos por Tipo (barras agrupadas) ──
new Chart(document.getElementById('chartTipos'), {
  type: 'bar',
  data: {
    labels: <?= $jEquiposTipo ?>,
    datasets: [
      { label:'Total',   data: <?= $jEquiposTot ?>, backgroundColor: C.primary + 'cc', borderRadius:4 },
      { label:'Activos', data: <?= $jEquiposAct ?>, backgroundColor: C.success + 'cc', borderRadius:4 },
    ]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } }
});

// ── Equipos por Área (barras horizontales) ──
new Chart(document.getElementById('chartAreas'), {
  type: 'bar',
  data: {
    labels: <?= $jAreaNom ?>,
    datasets: [{ label:'Equipos', data: <?= $jAreaTot ?>, backgroundColor: palette, borderRadius:4 }]
  },
  options: {
    indexAxis: 'y', responsive:true,
    plugins:{ legend:{ display:false } },
    scales:{ x:{ beginAtZero:true, ticks:{ stepSize:1 } } }
  }
});

// ── Tickets por mes (línea) ──
new Chart(document.getElementById('chartTicketsMes'), {
  type: 'line',
  data: {
    labels: <?= $jMeses ?>,
    datasets: [
      { label:'Total',     data: <?= $jTktTot ?>, borderColor: C.danger,  backgroundColor: C.danger+'22',  tension:.4, fill:true, pointRadius:4 },
      { label:'Resueltos', data: <?= $jTktRes ?>, borderColor: C.success, backgroundColor: C.success+'22', tension:.4, fill:true, pointRadius:4 },
    ]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }
});

// ── Mantenimientos por mes (barras) ──
new Chart(document.getElementById('chartMantMes'), {
  type: 'bar',
  data: {
    labels: <?= $jMantMes ?>,
    datasets: [
      { label:'Preventivo',  data: <?= $jMantPrev ?>, backgroundColor: C.info+'cc',    borderRadius:4 },
      { label:'Correctivo',  data: <?= $jMantCorr ?>, backgroundColor: C.warning+'cc', borderRadius:4 },
    ]
  },
  options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } } }
});

// ── Estado de Tickets (dona) ──
new Chart(document.getElementById('chartTktEstado'), {
  type: 'doughnut',
  data: {
    labels: <?= $jTktEstLbl ?>,
    datasets:[{ data: <?= $jTktEstTot ?>, backgroundColor:[C.danger,C.warning,C.success,C.gray], borderWidth:2 }]
  },
  options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12 } } } }
});

// ── Inventario por estado (dona) ──
new Chart(document.getElementById('chartInvEstado'), {
  type: 'doughnut',
  data: {
    labels: <?= $jInvLbl ?>,
    datasets:[{ data: <?= $jInvTot ?>, backgroundColor:[C.success,C.warning,C.danger,C.gray], borderWidth:2 }]
  },
  options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12 } } } }
});

// ── Prioridades (polar area) ──
new Chart(document.getElementById('chartPrioridad'), {
  type: 'polarArea',
  data: {
    labels: <?= $jPrioLbl ?>,
    datasets:[{ data: <?= $jPrioTot ?>, backgroundColor:[C.danger+'cc',C.warning+'cc',C.info+'cc',C.gray+'cc'], borderWidth:1 }]
  },
  options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12 } } } }
});
</script>
<?php include 'includes/footer.php'; ?>
