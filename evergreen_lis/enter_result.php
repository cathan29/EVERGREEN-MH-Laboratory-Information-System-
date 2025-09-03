<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Enter Result • Evergreen LIS";
$item_id = (int)($_GET['item_id'] ?? 0);
if(!$item_id){ header('Location: results.php'); exit; }

// Reopen if requested
if(isset($_GET['reopen'])){
  $pdo->prepare("UPDATE order_items SET status='in_processing', result_value=NULL, result_unit=NULL, abnormal_flag=0 WHERE id=?")->execute([$item_id]);
}

$sql = "SELECT oi.*,
               t.name as test_name, t.code, t.price, t.department as test_dept,
               o.id as order_id, o.order_date, o.ordering_doctor,
               p.first_name, p.middle_name, p.last_name, p.suffix, p.dob, p.sex
        FROM order_items oi
        JOIN orders o ON o.id=oi.order_id
        JOIN patients p ON p.id=o.patient_id
        JOIN tests t ON t.id=oi.test_id
        WHERE oi.id=?";
$stmt = $pdo->prepare($sql); $stmt->execute([$item_id]); $item = $stmt->fetch();
if(!$item){ echo "Item not found"; exit; }

$err = $ok=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $result = trim($_POST['result_value'] ?? '');
  $unit   = trim($_POST['result_unit'] ?? '');
  $ref    = trim($_POST['reference_range'] ?? '');
  $status = $_POST['status'] ?? 'in_processing';
  $abn = detect_abnormal($result, $ref);
  $sqlU = "UPDATE order_items SET result_value=?, result_unit=?, reference_range=?, abnormal_flag=?, status=?, updated_at=NOW(), completed_at=CASE WHEN ?='completed' THEN NOW() ELSE completed_at END WHERE id=?";
  $pdo->prepare($sqlU)->execute([$result,$unit,$ref,$abn,$status,$status,$item_id]);
  $ok="Saved.";
  $stmt = $pdo->prepare($sql); $stmt->execute([$item_id]); $item = $stmt->fetch();
}

include __DIR__.'/partials/header.php';
?>
  <div class="card">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Enter / Edit Result — Item #<?= (int)$item['id'] ?></div>
    <?php if($err): ?><div class="alert warn"><?= h($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><?php endif; ?>
    <div class="grid cols-2">
      <div>
        <div class="meta"><strong>Order:</strong> #<?= (int)$item['order_id'] ?> · <strong>Test:</strong> <?= h($item['code'].' — '.$item['test_name']) ?></div>
        <div class="meta"><strong>Test Dept:</strong> <?= h($item['test_dept']) ?></div>
        <div class="meta"><strong>Patient:</strong> <?= h(format_name_row($item)) ?> (<?= h($item['sex']) ?>) · <strong>DOB:</strong> <?= h($item['dob']) ?></div>
        <div class="meta"><strong>Doctor:</strong> <?= h($item['ordering_doctor']) ?></div>
      </div>
      <div class="meta" style="text-align:right">
        Status: <?= status_badge($item['status']) ?>
      </div>
    </div>
    <hr class="sep">
    <form method="post">
      <div class="form-row three">
        <div><label>Result Value</label><input class="input" name="result_value" value="<?= h($item['result_value']) ?>" placeholder="e.g., 126"></div>
        <div><label>Unit</label><input class="input" name="result_unit" value="<?= h($item['result_unit']) ?>" placeholder="e.g., mg/dL"></div>
        <div><label>Reference Range</label><input class="input" name="reference_range" value="<?= h($item['reference_range']) ?>" placeholder="e.g., 70-110 mg/dL"></div>
      </div>
      <div class="form-row two">
        <div>
          <label>Mark as</label>
          <select class="input" name="status">
            <option value="in_processing" <?= $item['status']=='in_processing'?'selected':'' ?>>In Processing</option>
            <option value="completed" <?= $item['status']=='completed'?'selected':'' ?>>Completed</option>
          </select>
        </div>
      </div>
      <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn ghost" href="report.php?item_id=<?= (int)$item['id'] ?>">Open Report</a>
      </div>
    </form>
  </div>
<?php include __DIR__.'/partials/footer.php'; ?>
