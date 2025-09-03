<?php
// api.php - minimal JSON API for the LIS
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function json_input() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return $data ?: [];
}

function ok($data) {
  echo json_encode(['ok' => true, 'data' => $data]);
  exit;
}

function fail($message, $code=400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message]);
  exit;
}

// --- Helpers ---
function todayRange() {
  $start = (new DateTime('today'))->format('Y-m-d 00:00:00');
  $end   = (new DateTime('tomorrow'))->format('Y-m-d 00:00:00');
  return [$start, $end];
}

switch ($action) {

  case 'stats':
    [$start,$end] = todayRange();

    $sqlTestsToday = "SELECT COUNT(*) c FROM order_items WHERE created_at >= ? AND created_at < ?";
    $stmt = $pdo->prepare($sqlTestsToday);
    $stmt->execute([$start,$end]);
    $testsToday = (int)$stmt->fetch()['c'];

    $sqlPending = "SELECT COUNT(*) c FROM order_items WHERE status <> 'released'";
    $pending = (int)$pdo->query($sqlPending)->fetch()['c'];

    $sqlCompleted = "SELECT COUNT(*) c FROM order_items WHERE status='released' AND completed_at >= ? AND completed_at < ?";
    $stmt = $pdo->prepare($sqlCompleted);
    $stmt->execute([$start,$end]);
    $completedToday = (int)$stmt->fetch()['c'];

    $sqlRevenue = "SELECT COALESCE(SUM(t.price),0) rev
                   FROM order_items oi
                   JOIN tests_catalog t ON t.id=oi.test_id
                   WHERE oi.status='released' AND oi.completed_at >= ? AND oi.completed_at < ?";
    $stmt = $pdo->prepare($sqlRevenue);
    $stmt->execute([$start,$end]);
    $revenue = (float)$stmt->fetch()['rev'];

    ok([
      'tests_today' => $testsToday,
      'pending' => $pending,
      'completed_today' => $completedToday,
      'revenue_today' => $revenue
    ]);
    break;

  // --- Patients ---
  case 'patients.list':
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY id DESC");
    ok($stmt->fetchAll());
    break;

  case 'patients.create':
    $data = json_input();
    $mrn = trim($data['mrn'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $sex = $data['sex'] ?? 'O';
    $dob = $data['dob'] ?? null;
    $phone = trim($data['phone'] ?? '');
    if ($full_name === '') fail("Full name is required");
    $stmt = $pdo->prepare("INSERT INTO patients (mrn, full_name, sex, dob, phone) VALUES (?,?,?,?,?)");
    $stmt->execute([$mrn, $full_name, $sex, $dob, $phone]);
    ok(['id' => $pdo->lastInsertId()]);
    break;

  // --- Tests Catalog ---
  case 'tests.list':
    $stmt = $pdo->query("SELECT * FROM tests_catalog ORDER BY name ASC");
    ok($stmt->fetchAll());
    break;

  case 'tests.create':
    $data = json_input();
    $code = trim($data['code'] ?? '');
    $name = trim($data['name'] ?? '');
    $unit = trim($data['unit'] ?? '');
    $price = (float)($data['price'] ?? 0);
    $ref_low = is_numeric($data['ref_low'] ?? null) ? (float)$data['ref_low'] : null;
    $ref_high = is_numeric($data['ref_high'] ?? null) ? (float)$data['ref_high'] : null;
    if ($name === '') fail("Test name is required");
    $stmt = $pdo->prepare("INSERT INTO tests_catalog (code, name, unit, price, ref_low, ref_high) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$code,$name,$unit,$price,$ref_low,$ref_high]);
    ok(['id' => $pdo->lastInsertId()]);
    break;

  // --- Orders ---
  case 'orders.create':
    $data = json_input();
    $patient_id = (int)($data['patient_id'] ?? 0);
    $tests = $data['tests'] ?? [];
    if ($patient_id <= 0 || empty($tests)) fail("patient_id and tests[] required");
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO orders (patient_id) VALUES (?)");
      $stmt->execute([$patient_id]);
      $order_id = $pdo->lastInsertId();
      $stmt = $pdo->prepare("INSERT INTO order_items (order_id, test_id) VALUES (?,?)");
      foreach ($tests as $t) {
        $stmt->execute([$order_id, (int)$t]);
      }
      $pdo->commit();
      ok(['order_id' => $order_id]);
    } catch (Exception $e) {
      $pdo->rollBack();
      fail($e->getMessage(), 500);
    }
    break;

  case 'orders.list':
    $stmt = $pdo->query("SELECT o.id, o.ordered_at, p.full_name, p.mrn 
                         FROM orders o JOIN patients p ON p.id=o.patient_id ORDER BY o.id DESC");
    ok($stmt->fetchAll());
    break;

  case 'orders.by_patient':
    $pid = (int)($_GET['patient_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, ordered_at FROM orders WHERE patient_id=? ORDER BY id DESC");
    $stmt->execute([$pid]);
    ok($stmt->fetchAll());
    break;

  case 'order.items':
    $order_id = (int)($_GET['order_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT oi.*, t.name as test_name, t.unit, t.ref_low, t.ref_high
                           FROM order_items oi 
                           JOIN tests_catalog t ON t.id=oi.test_id
                           WHERE oi.order_id=? ORDER BY oi.id ASC");
    $stmt->execute([$order_id]);
    ok($stmt->fetchAll());
    break;

  case 'order_items.update_status':
    $data = json_input();
    $id = (int)($data['id'] ?? 0);
    $status = $data['status'] ?? 'registered';
    $allowed = ['registered','collected','in_lab','result_entered','released'];
    if (!in_array($status, $allowed)) fail("Invalid status");
    $stmt = $pdo->prepare("UPDATE order_items SET status=? WHERE id=?");
    $stmt->execute([$status,$id]);
    ok(true);
    break;

  // --- Results ---
  case 'results.add':
    $data = json_input();
    $item_id = (int)($data['order_item_id'] ?? 0);
    $value = trim($data['value'] ?? '');
    if ($item_id <= 0) fail("order_item_id required");

    $stmt = $pdo->prepare("SELECT oi.*, t.name, t.unit, t.ref_low, t.ref_high 
                           FROM order_items oi JOIN tests_catalog t ON t.id=oi.test_id 
                           WHERE oi.id=?");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch();
    if (!$row) fail("Order item not found", 404);

    $is_abnormal = 0;
    $num = floatval($value);
    $hasNumeric = is_numeric($value) && ($row['ref_low'] !== null || $row['ref_high'] !== null);
    if ($hasNumeric) {
      if ($row['ref_low'] !== null && $num < floatval($row['ref_low'])) $is_abnormal = 1;
      if ($row['ref_high'] !== null && $num > floatval($row['ref_high'])) $is_abnormal = 1;
    }

    $stmt = $pdo->prepare("UPDATE order_items SET result_value=?, result_unit=?, is_abnormal=?, status='released', completed_at=NOW() WHERE id=?");
    $stmt->execute([$value, $row['unit'], $is_abnormal, $item_id]);

    ok(['is_abnormal' => (bool)$is_abnormal]);
    break;

  case 'results.search':
    $q = "%".trim($_GET['q'] ?? '')."%";
    $sql = "SELECT oi.id as item_id, oi.result_value, oi.status, oi.is_abnormal, 
                   t.name as test_name, t.unit, 
                   o.id as order_id, p.full_name, p.mrn
            FROM order_items oi
            JOIN tests_catalog t ON t.id=oi.test_id
            JOIN orders o ON o.id=oi.order_id
            JOIN patients p ON p.id=o.patient_id
            WHERE p.full_name LIKE ? OR p.mrn LIKE ? OR t.name LIKE ?
            ORDER BY oi.id DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q,$q,$q]);
    ok($stmt->fetchAll());
    break;

  // --- Workflow ---
  case 'workflow.board':
    $rows = $pdo->query("SELECT oi.id, oi.status, t.name AS test_name, p.full_name
                         FROM order_items oi
                         JOIN tests_catalog t ON t.id=oi.test_id
                         JOIN orders o ON o.id=oi.order_id
                         JOIN patients p ON p.id=o.patient_id
                         ORDER BY oi.id DESC LIMIT 200")->fetchAll();
    $buckets = ['registered'=>[], 'collected'=>[], 'in_lab'=>[], 'result_entered'=>[], 'released'=>[]];
    foreach ($rows as $r) { $buckets[$r['status']][] = $r; }
    ok($buckets);
    break;

  default:
    fail("Unknown action '$action'", 404);
}
?>
