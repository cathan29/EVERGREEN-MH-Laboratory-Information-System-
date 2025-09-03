<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Patients • Evergreen LIS";

// Create
$err = $ok = null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $first   = trim($_POST['first_name']??'');
  $middle  = trim($_POST['middle_name']??'');
  $last    = trim($_POST['last_name']??'');
  $suffix  = trim($_POST['suffix']??'');
  $dob     = $_POST['dob'] ?? null;
  $sex     = $_POST['sex'] ?? 'M';
  $phone   = trim($_POST['phone']??'');
  $email   = trim($_POST['email']??'');            // optional; allow any text
  $address = trim($_POST['address']??'');
  $notes   = trim($_POST['notes']??'');

  if(!$first || !$last){
    $err = "First and Last name are required.";
  } else {
    // Save blanks as NULL (not empty strings)
    $middleVal  = ($middle !== '') ? $middle : null;
    $suffixVal  = ($suffix !== '') ? $suffix : null;
    $dobVal     = ($dob    !== '') ? $dob    : null;
    $phoneVal   = ($phone  !== '') ? $phone  : null;
    $emailVal   = ($email  !== '') ? $email  : null;
    $addressVal = ($address!== '') ? $address: null;
    $notesVal   = ($notes  !== '') ? $notes  : null;

    $stmt = $pdo->prepare("
      INSERT INTO patients (
        first_name, middle_name, last_name, suffix,
        dob, sex, phone, email, address, notes, created_at
      ) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
    ");
    $stmt->execute([
      $first, $middleVal, $last, $suffixVal,
      $dobVal, $sex, $phoneVal, $emailVal, $addressVal, $notesVal
    ]);
    $ok = "Patient saved.";
  }
}

// List
$patients = $pdo->query("
  SELECT id, first_name, middle_name, last_name, suffix, dob, sex, phone, email
  FROM patients ORDER BY id DESC
")->fetchAll();

include __DIR__.'/partials/header.php';
?>
  <div class="card">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Register Patient</div>
    <?php if($err): ?><div class="alert warn"><?= h($err) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-row three">
        <div><label>First Name</label><input class="input" name="first_name" required placeholder="e.g., Juan"></div>
        <div><label>Middle Name</label><input class="input" name="middle_name" placeholder="e.g., Santos"></div>
        <div><label>Last Name</label><input class="input" name="last_name" required placeholder="e.g., Dela Cruz"></div>
      </div>
      <div class="form-row three">
        <div><label>Suffix</label><input class="input" name="suffix" placeholder="e.g., Jr., III"></div>
        <div><label>DOB</label><input class="input" type="date" name="dob"></div>
        <div>
          <label>Sex</label>
          <select name="sex" class="input">
            <option value="M">Male</option>
            <option value="F">Female</option>
          </select>
        </div>
      </div>
      <div class="form-row two">
        <div><label>Phone</label><input class="input" name="phone" placeholder="09xxxxxxxxx"></div>
        <!-- Changed from type="email" to type="text" so any value (or blank) is allowed -->
        <div><label>Email (optional)</label><input class="input" type="text" name="email" placeholder="you@domain.com or leave blank"></div>
      </div>
      <div class="form-row one">
        <div><label>Address</label><input class="input" name="address" placeholder="Street, City, Province"></div>
      </div>
      <div class="form-row one">
        <div><label>Notes</label><textarea class="input" name="notes" placeholder="Medical history / remarks" rows="3"></textarea></div>
      </div>
      <div class="actions"><button class="btn" type="submit">Save</button></div>
    </form>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="caption" style="font-weight:700;margin-bottom:8px">Patient List</div>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>Name</th><th>DOB</th><th>Sex</th><th>Phone</th><th>Email</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($patients as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= h(format_name_row($p)) ?></td>
            <td><?= h($p['dob']) ?></td>
            <td><?= h($p['sex']) ?></td>
            <td><?= h($p['phone']) ?></td>
            <td><?= h($p['email']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php include __DIR__.'/partials/footer.php'; ?>
