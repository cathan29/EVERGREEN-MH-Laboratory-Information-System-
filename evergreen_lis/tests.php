<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Tests • Evergreen LIS";

$err = $ok = null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $code = trim($_POST['code']??'');
  $name = trim($_POST['name']??'');
  $dept = trim($_POST['department']??'');
  $price = (float)($_POST['price']??0);
  $tat = (int)($_POST['tat_hours']??0);
  if(!$code || !$name) $err="Code and Name are required.";
  else{
    $stmt=$pdo->prepare("INSERT INTO tests(code,name,department,price,tat_hours,active) VALUES(?,?,?,?,?,1)");
    try{
      $stmt->execute([$code,$name,$dept,$price,$tat]);
      $ok="Test saved.";
    }catch(PDOException $e){
      $err="Could not save test. Possibly duplicate code.";
    }
  }
}

$tests = $pdo->query("SELECT id, code, name, department, price, tat_hours FROM tests WHERE active=1 ORDER BY name")->fetchAll();
include __DIR__.'/partials/header.php';
?>
  <div class="card">
    <div class="caption" style="font-weight:700;margin-bottom:8px">New Test</div>
    <?php if($err): ?><div class="alert warn"><?= h($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-row three">
        <div><label>Code</label><input class="input" name="code" placeholder="e.g., CBC"></div>
        <div><label>Name</label><input class="input" name="name" placeholder="Complete Blood Count"></div>
        <div><label>Department</label>
          <select name="department" class="input">
            <option>Hematology</option><option>Chemistry</option><option>Microscopy</option>
            <option>Serology</option><option>Immunology</option><option>Other</option>
          </select>
        </div>
      </div>
      <div class="form-row three">
        <div><label>Price</label><input class="input" type="number" step="0.01" name="price" placeholder="0.00"></div>
        <div><label>TAT (hours)</label><input class="input" type="number" name="tat_hours" placeholder="e.g., 24"></div>
        <div style="display:flex;align-items:end"><button class="btn" type="submit">Save</button></div>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Test Catalog</div>
    <table class="table">
      <thead><tr><th>Code</th><th>Name</th><th>Dept</th><th>Price</th><th>TAT (h)</th></tr></thead>
      <tbody>
        <?php foreach($tests as $t): ?>
          <tr>
            <td><?= h($t['code']) ?></td>
            <td><?= h($t['name']) ?></td>
            <td><?= h($t['department']) ?></td>
            <td><?= money($t['price']) ?></td>
            <td><?= (int)$t['tat_hours'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php include __DIR__.'/partials/footer.php'; ?>
