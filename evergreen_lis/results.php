<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Results • Evergreen LIS";

$filters = [
  'q'          => trim($_GET['q'] ?? ''),
  'date_from'  => $_GET['date_from'] ?? '',
  'date_to'    => $_GET['date_to'] ?? '',
  'status'     => $_GET['status'] ?? '',
  'patient_id' => isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0,
];

$params = [];
$where  = build_where($filters, $params);

// Sorting
$sort = $_GET['sort'] ?? 'oi.id';
$dir  = strtolower($_GET['dir'] ?? 'desc');
$allowedSort = ['oi.id','o.id','t.name','t.code','t.department','p.id','p.last_name','o.ordering_doctor','oi.updated_at','oi.status'];
if(!in_array($sort,$allowedSort)) $sort='oi.id';
$dir = $dir === 'asc' ? 'asc' : 'desc';

$sql = "SELECT
          oi.id,
          o.id AS order_id,
          t.name AS test, t.code, t.department AS test_dept,
          p.id AS patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
          o.ordering_doctor,
          oi.result_value, oi.status, oi.updated_at
        FROM order_items oi
        JOIN orders   o ON o.id = oi.order_id
        JOIN tests    t ON t.id = oi.test_id
        JOIN patients p ON p.id = o.patient_id
        $where
        ORDER BY $sort $dir
        LIMIT 500";

$rows = [];
$summary = ['items'=>0,'patients'=>0];
$err = null;

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  $sumSql = "SELECT COUNT(*) AS items, COUNT(DISTINCT o.patient_id) AS patients
             FROM order_items oi JOIN orders o ON o.id=oi.order_id
             JOIN tests t ON t.id=oi.test_id
             JOIN patients p ON p.id=o.patient_id
             $where";
  $sum = $pdo->prepare($sumSql);
  $sum->execute($params);
  $summary = $sum->fetch() ?: $summary;

} catch(PDOException $e){
  $err = "Database error: ".$e->getMessage();
}

include __DIR__.'/partials/header.php';
?>
  <div class="card">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Find / Download Results</div>
    <?php if($err): ?><div class="alert warn"><?= h($err) ?></div><?php endif; ?>
    <form class="form-row three" method="get">
      <div>
        <label>Search</label>
        <input class="input" type="text" name="q" value="<?= h($filters['q']) ?>"
               placeholder="Search (ID#, order/item/patient)">
      </div>
      <div>
        <label>Date From</label>
        <input class="input" type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
      </div>
      <div>
        <label>Date To</label>
        <input class="input" type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
      </div>
      <div>
        <label>Status</label>
        <select name="status" class="input">
          <option value="">(any)</option>
          <option value="pending_collection" <?= $filters['status']=='pending_collection'?'selected':'' ?>>Pending</option>
          <option value="in_processing" <?= $filters['status']=='in_processing'?'selected':'' ?>>In Processing</option>
          <option value="completed" <?= $filters['status']=='completed'?'selected':'' ?>>Completed</option>
        </select>
      </div>
      <div>
        <label>Patient (by name)</label>
        <select name="patient_id" class="input">
          <option value="">(any)</option>
          <?php
          $patients = $pdo->query("SELECT id, first_name, middle_name, last_name, suffix
                                   FROM patients ORDER BY last_name, first_name")->fetchAll();
          foreach($patients as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $filters['patient_id']==$p['id']?'selected':'' ?>>
              <?= h(format_name_row($p).' (ID#'.$p['id'].')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn" type="submit">Search</button>
        <a class="btn ghost" href="results.php">Reset</a>
        <a class="btn ghost" href="export_results.php?<?= http_build_query($_GET) ?>">Export CSV</a>
      </div>
    </form>
    <div class="meta" style="margin-top:8px">
      Showing <strong><?= (int)$summary['items'] ?></strong> items for <strong><?= (int)$summary['patients'] ?></strong> unique patients.
      Tip: Click a table header to sort. Export with <span class="kbd">Ctrl/Cmd+P</span>.
    </div>
  </div>

  <div class="card" style="margin-top:16px">
    <table class="table">
      <thead>
        <tr>
          <th data-sort="oi.id">Item #</th>
          <th data-sort="o.id">Order #</th>
          <th data-sort="t.code">Test</th>
          <th data-sort="t.department">Test Dept</th>
          <th data-sort="p.id">Patient ID</th>
          <th data-sort="p.last_name">Patient Name</th>
          <th data-sort="o.ordering_doctor">Doctor</th>
          <th>Result</th>
          <th data-sort="oi.status">Status</th>
          <th data-sort="oi.updated_at">Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td>#<?= (int)$r['order_id'] ?></td>
            <td><?= h($r['code'].' — '.$r['test']) ?></td>
            <td><?= h($r['test_dept']) ?></td>
            <td><?= (int)$r['patient_id'] ?></td>
            <td><?= h(format_name_row($r)) ?></td>
            <td><?= h($r['ordering_doctor']) ?></td>
            <td><?= h($r['result_value']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td><?= h($r['updated_at']) ?></td>
            <td class="actions">
              <a href="enter_result.php?item_id=<?= (int)$r['id'] ?>">Enter / Edit</a>
              <a href="report.php?item_id=<?= (int)$r['id'] ?>">Open Report</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?>
          <tr><td colspan="11" class="meta">No results found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php include __DIR__.'/partials/footer.php'; ?>
