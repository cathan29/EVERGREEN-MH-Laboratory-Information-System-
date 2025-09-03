<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/lib/functions.php';
$PAGE_TITLE = "Dashboard • Evergreen LIS";
$cs = counts($pdo);
include __DIR__.'/partials/header.php';
?>
  <div class="card hero">
    <h1>Welcome back MASTER SERGS!</h1>
    <p>View and manage your lab operations efficiently.</p>
  </div>

  <div class="grid cols-4" style="margin-top:16px">
    <div class="card kpi"><div class="label">Tests Today</div><div class="value"><?= (int)$cs['testsToday'] ?></div></div>
    <div class="card kpi"><div class="label">Pending Results</div><div class="value"><?= (int)$cs['pending'] ?></div></div>
    <div class="card kpi"><div class="label">Completed Tests</div><div class="value"><?= (int)$cs['completed'] ?></div></div>
    <div class="card kpi"><div class="label">Revenue Today</div><div class="value"><?= money($cs['revenueToday']) ?></div></div>
  </div>

  <div class="grid cols-2" style="margin-top:16px">
    <div class="card">
      <div class="caption">Quick Actions</div>
      <div class="actions">
        <a class="btn" href="patients.php">Register Patient</a>
        <a class="btn secondary" href="tests.php">Test Catalog</a>
        <a class="btn secondary" href="workflow.php">Workflow</a>
        <a class="btn ghost" href="results.php">Find Results</a>
      </div>
    </div>
  </div>
<?php include __DIR__.'/partials/footer.php'; ?>
