<?php
if(!isset($PAGE_TITLE)) $PAGE_TITLE = 'EvergreenMH LIS';
require_once __DIR__.'/../lib/functions.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($PAGE_TITLE) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/app.js"></script>
  </head>
  <style>
   .icon {
  width: 30px;
  height: 30px;
  object-fit: contain;
  display: block;
  } 
  </style>
  <body>
    <div class="layout">
      <aside class="sidebar">
        <div class="brand">
          <img src="assets/logo.png" alt="Evergreen logo">
          <div class="title">
            <div>EvergreenMH</div>
            <div class="small">Laboratory IS</div>
          </div>
        </div>
        <nav class="nav">
          <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>">
            <img src="assets\dashboard.png" class="icon" alt=""> <span>Dashboard</span>
          </a>
          <a href="patients.php" class="<?= basename($_SERVER['PHP_SELF'])==='patients.php'?'active':'' ?>">
            <img src="assets\patient.png" class="icon" alt=""> <span>Patients</span>
          </a>
          <a href="tests.php" class="<?= basename($_SERVER['PHP_SELF'])==='tests.php'?'active':'' ?>">
            <img src="assets\test-tube.png" class="icon" alt=""> <span>Tests</span>
          </a>
          <a href="workflow.php" class="<?= basename($_SERVER['PHP_SELF'])==='workflow.php'?'active':'' ?>">
            <img src="assets\workflow.png" class="icon" alt=""> <span>Workflow</span>
        </a>
          <a href="results.php" class="<?= basename($_SERVER['PHP_SELF'])==='results.php'?'active':'' ?>">
            <img src="assets\results.png" class="icon" alt=""> <span>Results</span>
          </a>
          <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF'])==='settings.php'?'active':'' ?>">⚙️ Burat ni Mark</a>
        </nav>
      </aside>
      <main>
        <figure class="ui-watermark"><img src="assets/logo.png" alt=""></figure>
        </figure>

        <div class="topbar">
          <div class="searchbar" style="flex:1;max-width:720px">
            <form method="get" action="results.php" style="display:flex;gap:8px;flex:1">
              <input class="input" type="text" name="q" placeholder="Search patients, tests, orders..." value="<?= isset($_GET['q']) ? h($_GET['q']) : '' ?>">
              <button class="btn" type="submit">Search</button>
            </form>
          </div>
          <div class="meta">Evergreen Medical Hospital</div>
        </div>
        <div class="container">
