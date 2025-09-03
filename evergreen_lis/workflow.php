<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Workflow • Evergreen LIS";

$ok = $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
  $patient_id = (int)($_POST['patient_id'] ?? 0);

  // Fallbacks: parse input if hidden wasn't set
  if (!$patient_id) {
    $ps = trim($_POST['patient_search'] ?? '');
    if ($ps !== '') {
      if (preg_match('/ID#(\d+)/', $ps, $m))       $patient_id = (int)$m[1];
      elseif (ctype_digit($ps))                    $patient_id = (int)$ps;
      else {
        $parts = array_map('trim', explode(',', $ps));
        if (count($parts) === 2) {
          [$last, $firstRest] = $parts;
          // allow "First M. Suffix" in right-hand side – match on first token only for safety
          $first = trim(explode(' ', $firstRest)[0]);
          $stmt = $pdo->prepare("SELECT id FROM patients WHERE last_name=? AND first_name=? LIMIT 2");
          $stmt->execute([$last, $first]);
          $rows = $stmt->fetchAll();
          if (count($rows) === 1) $patient_id = (int)$rows[0]['id'];
        }
      }
    }
  }

  $test_ids = array_values(array_filter(array_map('intval', (array)($_POST['test_ids'] ?? []))));
  $ordering_doctor = trim($_POST['ordering_doctor'] ?? '');

  if (!$patient_id || count($test_ids) === 0) {
    $err = "Select patient and at least one test.";
  } else {
    $pdo->beginTransaction();
    try{
      $insOrder = $pdo->prepare("INSERT INTO orders(patient_id, ordering_doctor, order_date, status, created_at)
                                 VALUES(?, ?, NOW(), 'pending_collection', NOW())");
      $insOrder->execute([$patient_id, $ordering_doctor ?: null]);
      $order_id = (int)$pdo->lastInsertId();

      $insItem = $pdo->prepare("INSERT INTO order_items(order_id,test_id,status,updated_at) VALUES(?,?,'pending_collection',NOW())");
      foreach($test_ids as $tid){ $insItem->execute([$order_id,$tid]); }
      $pdo->commit();
      $ok = "Order #$order_id created.";
    }catch(Exception $e){
      $pdo->rollBack(); $err = "Could not create order.";
    }
  }
}

// lists
$pending = $pdo->query("SELECT oi.id, t.name as test, p.first_name, p.middle_name, p.last_name, p.suffix, oi.updated_at
  FROM order_items oi
  JOIN orders o ON o.id=oi.order_id
  JOIN tests t ON t.id=oi.test_id
  JOIN patients p ON p.id=o.patient_id
  WHERE oi.status='pending_collection'
  ORDER BY oi.updated_at DESC")->fetchAll();

$processing = $pdo->query("SELECT oi.id, t.name as test, p.first_name, p.middle_name, p.last_name, p.suffix, oi.updated_at
  FROM order_items oi
  JOIN orders o ON o.id=oi.order_id
  JOIN tests t ON t.id=oi.test_id
  JOIN patients p ON p.id=o.patient_id
  WHERE oi.status='in_processing'
  ORDER BY oi.updated_at DESC")->fetchAll();

$completed = $pdo->query("SELECT oi.id, t.name as test, p.first_name, p.middle_name, p.last_name, p.suffix, oi.updated_at
  FROM order_items oi
  JOIN orders o ON o.id=oi.order_id
  JOIN tests t ON t.id=oi.test_id
  JOIN patients p ON p.id=o.patient_id
  WHERE oi.status='completed'
  ORDER BY oi.completed_at DESC
  LIMIT 10")->fetchAll();

$patients = $pdo->query("SELECT id, first_name, middle_name, last_name, suffix
  FROM patients
  ORDER BY last_name, first_name")->fetchAll();

$tests = $pdo->query("SELECT id, code, name, department
  FROM tests
  WHERE active=1
  ORDER BY name")->fetchAll();

include __DIR__.'/partials/header.php';
?>
  <div class="card">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Create New Order</div>
    <?php if($err): ?><div class="alert warn"><?= h($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><?php endif; ?>
    <form method="post" id="createOrderForm">
      <div class="form-row two">
        <div>
          <label>Patient</label>
          <input
            class="input"
            id="patientSearch"
            name="patient_search"
            list="patientsList"
            placeholder="Type to search name or ID..."
            autocomplete="off">
          <datalist id="patientsList">
            <?php foreach($patients as $p): ?>
              <option value="<?= h(format_name_row($p)) ?> (ID#<?= (int)$p['id'] ?>)" data-id="<?= (int)$p['id'] ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input type="hidden" name="patient_id" id="patientId">
          <div class="small meta">Pick a suggestion; the ID fills automatically.</div>
        </div>
        <div>
          <label>Tests</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach($tests as $t): ?>
              <label class="badge" title="<?= h($t['department']) ?>"><input type="checkbox" name="test_ids[]" value="<?= (int)$t['id'] ?>"> <?= h($t['code'].' — '.$t['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <div class="small meta">Hover to see the test department.</div>
        </div>
      </div>

      <div class="form-row one">
        <div>
          <label>Requesting Doctor</label>
          <input class="input" name="ordering_doctor" placeholder="e.g., Dr. Juan Dela Cruz">
        </div>
      </div>

      <div class="actions">
        <button class="btn" name="create_order" value="1">Create Order</button>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Pending Collection</div>
    <?php if(!$pending): ?><div class="meta">No items pending.</div><?php else: ?>
    <table class="table"><thead><tr><th>Item #</th><th>Test</th><th>Patient</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($pending as $row): ?>
        <tr>
          <td>#<?= (int)$row['id'] ?></td>
          <td><?= h($row['test']) ?></td>
          <td><?= h(format_name_row($row)) ?></td>
          <td><?= h($row['updated_at']) ?></td>
          <td class="actions">
            <a href="enter_result.php?item_id=<?= (int)$row['id'] ?>">Enter Result</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="caption" style="font-weight:700;margin-bottom:8px">In Processing</div>
    <?php if(!$processing): ?><div class="meta">No items processing.</div><?php else: ?>
    <table class="table"><thead><tr><th>Item #</th><th>Test</th><th>Patient</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($processing as $row): ?>
        <tr>
          <td>#<?= (int)$row['id'] ?></td>
          <td><?= h($row['test']) ?></td>
          <td><?= h(format_name_row($row)) ?></td>
          <td><?= h($row['updated_at']) ?></td>
          <td class="actions">
            <a href="enter_result.php?item_id=<?= (int)$row['id'] ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Recently Completed</div>
    <?php if(!$completed): ?><div class="meta">No items completed.</div><?php else: ?>
    <table class="table"><thead><tr><th>Item #</th><th>Test</th><th>Patient</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($completed as $row): ?>
        <tr>
          <td>#<?= (int)$row['id'] ?></td>
          <td><?= h($row['test']) ?></td>
          <td><?= h(format_name_row($row)) ?></td>
          <td><?= h($row['updated_at']) ?></td>
          <td class="actions">
            <a href="report.php?item_id=<?= (int)$row['id'] ?>">View</a>
            <a href="enter_result.php?item_id=<?= (int)$row['id'] ?>&reopen=1">Reopen</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>

  <!-- Sync hidden patient_id before submit and while typing/choosing -->
  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const form   = document.getElementById('createOrderForm');
    const input  = document.getElementById('patientSearch');
    const hidden = document.getElementById('patientId');
    const list   = document.getElementById('patientsList');

    function syncHiddenExact() {
      const val = input.value;
      const opt = Array.from(list.options).find(o => o.value === val);
      hidden.value = opt ? (opt.getAttribute('data-id') || '') : '';
    }

    input.addEventListener('change', syncHiddenExact);
    input.addEventListener('input', () => {
      const val = input.value;
      const exact = Array.from(list.options).some(o => o.value === val);
      if (!exact) hidden.value = '';
    });

    form.addEventListener('submit', function(){
      if (!hidden.value) {
        syncHiddenExact();
      }
      if (!hidden.value) {
        const ps = input.value.trim();
        const m = ps.match(/ID#(\d+)/);
        if (m) hidden.value = m[1];
        else if (/^\d+$/.test(ps)) hidden.value = ps;
      }
    });
  });
  </script>
<?php include __DIR__.'/partials/footer.php'; ?>
