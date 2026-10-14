<?php
session_start();
if (!isset($_SESSION['role'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit; }
$apiUrl = "http://localhost:3000/api/activities";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$curlError = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

$data = $curlError ? null : json_decode($response, true);
$allActivities = $data['data'] ?? [];

// Filter: user hanya lihat aktivitas milik sendiri
$myName = $_SESSION['name'] ?? '';
$activities = array_values(array_filter($allActivities, fn($a) => ($a['EmployeeName'] ?? '') === $myName));
$total = count($activities);

// Hitung statistik (berdasarkan data milik user)
$totalMenit = array_sum(array_column($activities, 'Duration'));
$depts = array_unique(array_column($activities, 'Department'));
$employees = array_unique(array_column($activities, 'EmployeeName'));
$tipeCount = array_count_values(array_column($activities, 'ActivityType'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WFH Blockchain Monitor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0a0e1a;
    --surface: #111827;
    --card: #1a2235;
    --border: #1e2d45;
    --cyan: #06b6d4;
    --purple: #8b5cf6;
    --green: #10b981;
    --yellow: #f59e0b;
    --red: #ef4444;
    --text: #f1f5f9;
    --muted: #64748b;
    --accent: #0ea5e9;
  }
  body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
  }
  /* NAVBAR */
  nav {
    background: rgba(17,24,39,0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .nav-brand {
    display: flex;
    align-items: center;
    gap: .75rem;
    font-weight: 700;
    font-size: 1.1rem;
  }
  .nav-brand .icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--cyan), var(--purple));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
  }
  .nav-links { display: flex; gap: .5rem; }
  .nav-links a {
    padding: .4rem 1rem;
    border-radius: 8px;
    color: var(--muted);
    text-decoration: none;
    font-size: .875rem;
    font-weight: 500;
    transition: all .2s;
  }
  .nav-links a:hover, .nav-links a.active {
    background: rgba(6,182,212,.1);
    color: var(--cyan);
  }
  .badge-live {
    display: flex; align-items: center; gap: .4rem;
    background: rgba(16,185,129,.1);
    border: 1px solid rgba(16,185,129,.3);
    color: var(--green);
    padding: .3rem .75rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 600;
  }
  .badge-live .dot {
    width: 6px; height: 6px;
    background: var(--green);
    border-radius: 50%;
    animation: pulse 1.5s infinite;
  }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

  /* MAIN */
  main { padding: 2rem; max-width: 1280px; margin: 0 auto; }

  /* HERO */
  .hero {
    text-align: center;
    padding: 3rem 0 2rem;
  }
  .hero h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--cyan), var(--purple));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: .75rem;
  }
  .hero p { color: var(--muted); font-size: 1rem; }

  /* ALERT ERROR */
  .alert-error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: #fca5a5;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
  }

  /* STATS GRID */
  .stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 2rem; }
  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform .2s, border-color .2s;
  }
  .stat-card:hover { transform: translateY(-2px); border-color: var(--cyan); }
  .stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
  }
  .stat-icon.blue  { background: rgba(6,182,212,.15); }
  .stat-icon.green { background: rgba(16,185,129,.15); }
  .stat-icon.purple{ background: rgba(139,92,246,.15); }
  .stat-icon.yellow{ background: rgba(245,158,11,.15); }
  .stat-val { font-size: 1.75rem; font-weight: 800; }
  .stat-lbl { font-size: .8rem; color: var(--muted); margin-top: .1rem; }

  /* SECTION */
  .section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }
  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* TABLE */
  .table-wrap {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 2rem;
  }
  .table-head {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .table-head h2 { font-size: 1rem; font-weight: 600; }
  .btn-add {
    background: linear-gradient(135deg, var(--cyan), var(--purple));
    color: white;
    border: none;
    padding: .5rem 1.25rem;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: opacity .2s;
  }
  .btn-add:hover { opacity: .85; }
  table { width: 100%; border-collapse: collapse; }
  thead th {
    background: rgba(255,255,255,.03);
    padding: .75rem 1.5rem;
    text-align: left;
    font-size: .75rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid var(--border);
  }
  tbody tr {
    border-bottom: 1px solid rgba(255,255,255,.04);
    transition: background .15s;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(255,255,255,.03); }
  td { padding: .875rem 1.5rem; font-size: .875rem; vertical-align: middle; }
  .id-badge {
    font-family: monospace;
    background: rgba(6,182,212,.1);
    color: var(--cyan);
    padding: .2rem .6rem;
    border-radius: 6px;
    font-size: .8rem;
  }
  .emp-name { font-weight: 600; }
  .dept-badge {
    display: inline-block;
    padding: .2rem .65rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
    border: 1px solid;
  }
  .dept-Engineering { color:#06b6d4; border-color:rgba(6,182,212,.3); background:rgba(6,182,212,.1); }
  .dept-Marketing   { color:#f59e0b; border-color:rgba(245,158,11,.3); background:rgba(245,158,11,.1); }
  .dept-Finance     { color:#10b981; border-color:rgba(16,185,129,.3); background:rgba(16,185,129,.1); }
  .dept-Design      { color:#8b5cf6; border-color:rgba(139,92,246,.3); background:rgba(139,92,246,.1); }
  .dept-default     { color:#64748b; border-color:rgba(100,116,139,.3); background:rgba(100,116,139,.1); }
  .type-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .7rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
  }
  .type-Productive { background:rgba(16,185,129,.15); color:var(--green); }
  .type-Meeting    { background:rgba(245,158,11,.15);  color:var(--yellow); }
  .type-Training   { background:rgba(139,92,246,.15);  color:var(--purple); }
  .dur-bar-wrap { display: flex; align-items: center; gap: .5rem; min-width: 120px; }
  .dur-bar-bg { flex:1; background: rgba(255,255,255,.07); border-radius: 4px; height: 6px; }
  .dur-bar { height: 6px; border-radius: 4px; background: linear-gradient(90deg, var(--cyan), var(--purple)); }
  .dur-text { font-weight: 600; font-size: .82rem; white-space: nowrap; }
  .hash-preview {
    font-family: monospace;
    font-size: .72rem;
    color: var(--muted);
  }
  td.notes { color: var(--muted); font-size: .8rem; max-width: 200px; }

  /* BOTTOM GRID */
  .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
  .info-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
  }
  .info-card-head {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    font-size: .875rem;
    font-weight: 600;
    display: flex; align-items: center; gap: .5rem;
  }
  .info-card-body { padding: 1.25rem 1.5rem; }
  .api-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .6rem .75rem;
    border-radius: 8px;
    margin-bottom: .4rem;
    background: rgba(255,255,255,.03);
    font-size: .82rem;
  }
  .method {
    padding: .15rem .5rem;
    border-radius: 5px;
    font-size: .7rem;
    font-weight: 700;
    min-width: 40px;
    text-align: center;
  }
  .method.get    { background:rgba(16,185,129,.2); color:var(--green); }
  .method.post   { background:rgba(6,182,212,.2);  color:var(--cyan); }
  .method.put    { background:rgba(245,158,11,.2);  color:var(--yellow); }
  .method.delete { background:rgba(239,68,68,.2);  color:var(--red); }
  .api-path { font-family: monospace; color: var(--text); }
  .api-desc { color: var(--muted); margin-left: auto; font-size: .75rem; }

  .arch-box {
    background: rgba(0,0,0,.3);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    font-family: monospace;
    font-size: .8rem;
    color: var(--muted);
    line-height: 1.9;
  }
  .arch-box .hl { color: var(--cyan); font-weight: 700; }
  .arch-box .hl2 { color: var(--green); }
  .arch-box .hl3 { color: var(--purple); }

  /* FOOTER */
  footer {
    text-align: center;
    padding: 2rem;
    color: var(--muted);
    font-size: .8rem;
    border-top: 1px solid var(--border);
    margin-top: 2rem;
  }

  @media (max-width: 900px) {
    .stats { grid-template-columns: repeat(2,1fr); }
    .bottom-grid { grid-template-columns: 1fr; }
    nav { padding: 0 1rem; }
    main { padding: 1rem; }
    .hero h1 { font-size: 1.8rem; }
  }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <div class="nav-brand">
    <div class="icon">⛓️</div>
    <span>WFH Blockchain</span>
  </div>
  <div class="nav-links">
    <a href="index.php" class="active">Dashboard</a>
    <a href="tambah.php">+ Catat Aktivitas</a>
  </div>
  <div style="display:flex;align-items:center;gap:.75rem">
    <span style="font-size:.85rem;color:var(--muted)">👤 <?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
    <?php if (!$curlError): ?>
    <div class="badge-live"><div class="dot"></div> API Connected</div>
    <?php else: ?>
    <div class="badge-live" style="color:#ef4444;border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.1)">⚠ API Offline</div>
    <?php endif; ?>
    <a href="logout.php" style="background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);padding:.35rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none">Logout</a>
  </div>
</nav>

<main>
  <!-- HERO -->
  <div class="hero">
    <h1>⛓ WFH Activity Monitor</h1>
    <p>Sistem Monitoring Aktivitas Kerja Berbasis Hyperledger Fabric Blockchain</p>
  </div>

  <?php if ($curlError): ?>
  <div class="alert-error">
    ⚠️ <strong>API tidak dapat dihubungi.</strong> Pastikan <code>node src/server.js</code> berjalan di WSL.<br>
    Error: <?= htmlspecialchars($curlError) ?>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon blue">📋</div>
      <div>
        <div class="stat-val"><?= $total ?></div>
        <div class="stat-lbl">Aktivitas Saya</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">⏱</div>
      <div>
        <div class="stat-val"><?= round($totalMenit/60,1) ?>j</div>
        <div class="stat-lbl">Total Jam Kerja</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple">🏢</div>
      <div>
        <div class="stat-val"><?= count($depts) ?></div>
        <div class="stat-lbl">Departemen</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon yellow">📊</div>
      <div>
        <div class="stat-val"><?= count($tipeCount) ?></div>
        <div class="stat-lbl">Tipe Aktivitas</div>
      </div>
    </div>
  </div>

  <!-- TABLE -->
  <p class="section-title">📦 Data Blockchain</p>
  <div class="table-wrap">
    <div class="table-head">
      <h2>Aktivitas Saya — <span style="color:var(--cyan);font-weight:600"><?= htmlspecialchars($myName) ?></span> <span style="color:var(--muted);font-weight:400;font-size:.85rem">(<?= $total ?> record)</span></h2>
      <a href="tambah.php" class="btn-add">+ Catat Baru</a>
    </div>
    <?php if (!empty($activities)): ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>ID</th>
          <th>Departemen</th>
          <th>Aktivitas</th>
          <th>Durasi</th>
          <th>Tipe</th>
          <th>Hash (SHA-256)</th>
          <th>Prev Hash</th>
          <th>Tanggal</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $maxDur = max(array_column($activities, 'Duration')) ?: 1;
      foreach ($activities as $a):
        $dept = $a['Department'] ?? 'default';
        $deptClass = 'dept-' . (in_array($dept,['Engineering','Marketing','Finance','Design']) ? $dept : 'default');
        $type = $a['ActivityType'] ?? 'Productive';
        $typeClass = 'type-' . (in_array($type,['Productive','Meeting','Training']) ? $type : 'Productive');
        $dur = (int)($a['Duration'] ?? 0);
        $pct = round($dur / $maxDur * 100);
        $typeIcon = ['Productive'=>'💻','Meeting'=>'🎙️','Training'=>'📚'][$type] ?? '📌';
        $hash  = $a['Hash']         ?? '';
        $prevH = $a['PreviousHash'] ?? '';
        $bIdx  = $a['BlockIndex']   ?? '-';
      ?>
        <tr>
          <td><span style="font-family:monospace;background:rgba(139,92,246,.1);color:#8b5cf6;padding:.2rem .5rem;border-radius:6px;font-size:.75rem;font-weight:700">#<?= $bIdx ?></span></td>
          <td><span class="id-badge"><?= htmlspecialchars($a['ID'] ?? '-') ?></span></td>
          <td><span class="dept-badge <?= $deptClass ?>"><?= htmlspecialchars($dept) ?></span></td>
          <td><?= htmlspecialchars($a['Activity'] ?? '-') ?></td>
          <td>
            <div class="dur-bar-wrap">
              <div class="dur-bar-bg"><div class="dur-bar" style="width:<?= $pct ?>%"></div></div>
              <span class="dur-text"><?= $dur ?>m</span>
            </div>
          </td>
          <td><span class="type-badge <?= $typeClass ?>"><?= $typeIcon ?> <?= htmlspecialchars($type) ?></span></td>
          <td><span class="hash-preview" title="<?= htmlspecialchars($hash) ?>"><?= $hash ? htmlspecialchars(substr($hash,0,14)).'...' : '-' ?></span></td>
          <td><span class="hash-preview" title="<?= htmlspecialchars($prevH) ?>"><?= $prevH ? htmlspecialchars(substr($prevH,0,14)).'...' : '-' ?></span></td>
          <td><span class="hash-preview"><?= htmlspecialchars($a['Date'] ?? '-') ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div style="padding:3rem;text-align:center;color:var(--muted)">
      Belum ada data aktivitas dalam blockchain.
    </div>
    <?php endif; ?>
  </div>

  <!-- BOTTOM GRID -->
  <div class="bottom-grid">
    <!-- API Endpoints -->
    <div class="info-card">
      <div class="info-card-head">🔌 API Endpoints (localhost:3000)</div>
      <div class="info-card-body">
        <?php
        $eps = [
          ['GET','get','/api/activities','Semua aktivitas'],
          ['GET','get','/api/activities/:id','Detail aktivitas'],
          ['POST','post','/api/activities','Catat aktivitas baru'],
          ['PUT','put','/api/activities/:id','Update aktivitas'],
          ['DELETE','delete','/api/activities/:id','Hapus aktivitas'],
          ['GET','get','/api/employee/:name','Per karyawan'],
          ['GET','get','/api/history/:id','Riwayat blockchain'],
          ['POST','post','/api/init','Init ledger'],
        ];
        foreach ($eps as $ep): ?>
        <div class="api-row">
          <span class="method <?= $ep[1] ?>"><?= $ep[0] ?></span>
          <span class="api-path"><?= $ep[2] ?></span>
          <span class="api-desc"><?= $ep[3] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Arsitektur -->
    <div class="info-card">
      <div class="info-card-head">🏗 Arsitektur Sistem</div>
      <div class="info-card-body">
        <div class="arch-box">
<span class="hl">[ PHP Client ]</span> ← Anda di sini<br>
  index.php / tambah.php<br>
         ↓ HTTP cURL<br>
<span class="hl2">[ Node.js REST API ]</span><br>
  localhost:3000/api/...<br>
         ↓ gRPC<br>
<span class="hl3">[ Hyperledger Fabric ]</span><br>
  Chaincode: wfh (JavaScript)<br>
         ↓<br>
  peer0.org1 ←→ peer0.org2<br>
  Channel: mychannel
        </div>
        <div style="margin-top:1rem;display:flex;flex-direction:column;gap:.5rem">
          <?php
          $infos = [
            ['🔗','Channel','mychannel'],
            ['📦','Chaincode','wfh (JavaScript)'],
            ['🔐','Hash','SHA-256 + Merkle Tree'],
            ['⛏','Consensus','Raft Ordering'],
          ];
          foreach ($infos as $info): ?>
          <div style="display:flex;align-items:center;gap:.75rem;font-size:.83rem">
            <span><?= $info[0] ?></span>
            <span style="color:var(--muted)"><?= $info[1] ?>:</span>
            <span style="color:var(--cyan);font-weight:600"><?= $info[2] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  ⛓️ WFH Activity Monitoring — Berbasis Hyperledger Fabric &nbsp;|&nbsp;
  Mata Kuliah: STNCS &nbsp;|&nbsp;
  <?= date('d M Y, H:i') ?> WIB
</footer>
</body>
</html>
