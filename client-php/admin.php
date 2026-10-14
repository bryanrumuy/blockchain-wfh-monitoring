<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit;
}

$ch = curl_init('http://localhost:3000/api/chain');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$resp   = curl_exec($ch);
$curlErr = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

$chainData  = $curlErr ? null : json_decode($resp, true);
$activities = $chainData['chain'] ?? [];  // sudah terurut by BlockIndex
$total      = count($activities);
$totalMenit = array_sum(array_column($activities, 'Duration'));
$employees  = array_unique(array_column($activities, 'EmployeeName'));
$depts      = array_unique(array_column($activities, 'Department'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — WFH Blockchain</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#0a0e1a;--card:#1a2235;--border:#1e2d45;--cyan:#06b6d4;--purple:#8b5cf6;--green:#10b981;--yellow:#f59e0b;--red:#ef4444;--text:#f1f5f9;--muted:#64748b}
  body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
  nav{background:rgba(17,24,39,.95);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:0 2rem;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
  .nav-brand{display:flex;align-items:center;gap:.75rem;font-weight:700;font-size:1.1rem}
  .nav-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--purple),var(--cyan));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
  .nav-right{display:flex;align-items:center;gap:1rem}
  .badge-admin{background:rgba(139,92,246,.2);color:var(--purple);border:1px solid rgba(139,92,246,.3);padding:.3rem .75rem;border-radius:999px;font-size:.75rem;font-weight:700}
  .btn-logout{background:rgba(239,68,68,.15);color:var(--red);border:1px solid rgba(239,68,68,.3);padding:.35rem .9rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;transition:background .2s}
  .btn-logout:hover{background:rgba(239,68,68,.3)}
  main{padding:2rem;max-width:1400px;margin:0 auto}
  .page-header{margin-bottom:2rem}
  .page-header h1{font-size:1.75rem;font-weight:800}
  .page-header p{color:var(--muted);font-size:.875rem;margin-top:.25rem}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
  .stat-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1rem}
  .stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
  .si-blue{background:rgba(6,182,212,.15)}.si-green{background:rgba(16,185,129,.15)}.si-purple{background:rgba(139,92,246,.15)}.si-yellow{background:rgba(245,158,11,.15)}
  .stat-val{font-size:1.75rem;font-weight:800}.stat-lbl{font-size:.8rem;color:var(--muted);margin-top:.1rem}
  .section-title{font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
  .section-title::after{content:'';flex:1;height:1px;background:var(--border)}

  /* VALIDATE SECTION */
  .validate-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.5rem;margin-bottom:2rem}
  .validate-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
  .validate-header h2{font-size:1rem;font-weight:600}
  .btn-validate{background:linear-gradient(135deg,var(--cyan),var(--purple));color:white;border:none;padding:.6rem 1.5rem;border-radius:10px;font-size:.875rem;font-weight:700;cursor:pointer;transition:opacity .2s;display:flex;align-items:center;gap:.5rem}
  .btn-validate:hover{opacity:.85}
  .btn-validate:disabled{opacity:.5;cursor:not-allowed}
  #validateResult{margin-top:1rem;display:none}
  .valid-ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;border-radius:10px;padding:1rem 1.25rem}
  .valid-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;border-radius:10px;padding:1rem 1.25rem}
  .err-item{background:rgba(239,68,68,.08);border-radius:8px;padding:.6rem .9rem;margin-top:.5rem;font-size:.82rem;font-family:monospace}
  .validate-info{font-size:.82rem;color:var(--muted)}

  /* BLOCKCHAIN CHAIN VISUALIZATION */
  .chain-scroll{overflow-x:auto;padding-bottom:1rem}
  .chain-wrap{display:flex;align-items:center;gap:0;min-width:max-content;padding:.5rem 0}
  .block-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1rem;width:220px;flex-shrink:0;transition:border-color .2s,transform .2s;position:relative}
  .block-card:hover{border-color:var(--cyan);transform:translateY(-3px)}
  .block-card.genesis{border-color:rgba(139,92,246,.4)}
  .block-num{font-size:.7rem;font-weight:700;color:var(--purple);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem}
  .block-id{font-family:monospace;font-size:.78rem;color:var(--cyan);background:rgba(6,182,212,.1);padding:.15rem .5rem;border-radius:5px;display:inline-block;margin-bottom:.6rem}
  .block-hash-label{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-top:.5rem;margin-bottom:.15rem}
  .block-hash{font-family:monospace;font-size:.7rem;color:var(--green);word-break:break-all;line-height:1.4}
  .block-prevhash{font-family:monospace;font-size:.7rem;color:var(--muted);word-break:break-all;line-height:1.4}
  .block-data{font-size:.78rem;color:var(--text);margin-top:.5rem;border-top:1px solid var(--border);padding-top:.5rem}
  .block-data strong{display:block;color:var(--cyan);font-size:.75rem;margin-bottom:.1rem}
  .block-nonce{margin-top:.4rem;font-size:.7rem;color:var(--muted);font-family:monospace}
  .pow-badge{display:inline-block;background:rgba(16,185,129,.15);color:var(--green);border:1px solid rgba(16,185,129,.3);border-radius:5px;padding:.1rem .4rem;font-size:.65rem;font-weight:700;margin-top:.3rem}
  .chain-arrow{color:var(--muted);font-size:1.5rem;padding:0 .25rem;flex-shrink:0;align-self:center}

  /* TABLE */
  .table-wrap{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:2rem}
  .table-head{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
  .table-head h2{font-size:1rem;font-weight:600}
  .readonly-badge{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2);padding:.25rem .75rem;border-radius:999px;font-size:.72rem;font-weight:600}
  table{width:100%;border-collapse:collapse}
  thead th{background:rgba(255,255,255,.03);padding:.75rem 1rem;text-align:left;font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border)}
  tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
  tbody tr:last-child{border-bottom:none}
  tbody tr:hover{background:rgba(255,255,255,.03)}
  td{padding:.75rem 1rem;font-size:.82rem;vertical-align:middle}
  .id-badge{font-family:monospace;background:rgba(6,182,212,.1);color:var(--cyan);padding:.2rem .5rem;border-radius:6px;font-size:.75rem}
  .block-idx{font-family:monospace;background:rgba(139,92,246,.1);color:var(--purple);padding:.2rem .5rem;border-radius:6px;font-size:.75rem;font-weight:700}
  .hash-mono{font-family:monospace;font-size:.7rem;color:var(--green)}
  .prevhash-mono{font-family:monospace;font-size:.7rem;color:var(--muted)}
  .dept-badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600;border:1px solid}
  .dept-Engineering{color:#06b6d4;border-color:rgba(6,182,212,.3);background:rgba(6,182,212,.1)}
  .dept-Marketing{color:#f59e0b;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.1)}
  .dept-Finance{color:#10b981;border-color:rgba(16,185,129,.3);background:rgba(16,185,129,.1)}
  .dept-Design{color:#8b5cf6;border-color:rgba(139,92,246,.3);background:rgba(139,92,246,.1)}
  .dept-default{color:#64748b;border-color:rgba(100,116,139,.3);background:rgba(100,116,139,.1)}
  .type-Productive{background:rgba(16,185,129,.15);color:var(--green);padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600}
  .type-Meeting{background:rgba(245,158,11,.15);color:var(--yellow);padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600}
  .type-Training{background:rgba(139,92,246,.15);color:var(--purple);padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600}

  /* PER KARYAWAN */
  .emp-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:2rem}
  .emp-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.25rem 1.5rem}
  .emp-name{font-weight:700;font-size:1rem;margin-bottom:.25rem}
  .emp-dept{font-size:.8rem;color:var(--muted);margin-bottom:.75rem}
  .emp-stat{display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.4rem}
  .emp-stat span:last-child{color:var(--cyan);font-weight:600}
  .emp-bar-bg{width:100%;height:5px;background:rgba(255,255,255,.07);border-radius:4px;margin-top:.5rem}
  .emp-bar{height:5px;border-radius:4px;background:linear-gradient(90deg,var(--cyan),var(--purple))}

  .alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:1rem 1.25rem;border-radius:12px;margin-bottom:1.5rem;text-align:center}
  footer{text-align:center;padding:2rem;color:var(--muted);font-size:.8rem;border-top:1px solid var(--border)}
  @media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">
    <div class="nav-icon">⛓️</div>
    <span>WFH Blockchain</span>
  </div>
  <div class="nav-right">
    <span style="font-size:.85rem;color:var(--muted)">👤 <?= htmlspecialchars($_SESSION['name']) ?></span>
    <span class="badge-admin">🔐 ADMIN</span>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>

<main>
  <div class="page-header">
    <h1>🛡 Admin Dashboard</h1>
    <p>Monitor semua aktivitas WFH karyawan dari Hyperledger Fabric Blockchain</p>
  </div>

  <?php if ($curlErr): ?>
  <div class="alert-err">⚠️ API tidak tersambung. Pastikan <code>node src/server.js</code> berjalan di WSL.</div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats">
    <div class="stat-card"><div class="stat-icon si-blue">⛓</div><div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total Block</div></div></div>
    <div class="stat-card"><div class="stat-icon si-green">⏱</div><div><div class="stat-val"><?= round($totalMenit/60,1) ?>j</div><div class="stat-lbl">Total Jam Kerja</div></div></div>
    <div class="stat-card"><div class="stat-icon si-purple">👥</div><div><div class="stat-val"><?= count($employees) ?></div><div class="stat-lbl">Karyawan Aktif</div></div></div>
    <div class="stat-card"><div class="stat-icon si-yellow">🏢</div><div><div class="stat-val"><?= count($depts) ?></div><div class="stat-lbl">Departemen</div></div></div>
  </div>

  <!-- VALIDASI BLOCKCHAIN -->
  <p class="section-title">🔍 Validasi Blockchain</p>
  <div class="validate-card">
    <div class="validate-header">
      <div>
        <h2>Integritas Blockchain</h2>
        <p class="validate-info">Klik tombol untuk re-compute SHA-256 semua block dan verifikasi chain</p>
      </div>
      <button class="btn-validate" id="btnValidate" onclick="validateChain()">
        🔍 Validasi Sekarang
      </button>
    </div>
    <div id="validateResult"></div>
  </div>

  <!-- VISUALISASI CHAIN -->
  <?php if (!empty($activities)): ?>
  <p class="section-title">⛓ Visualisasi Blockchain Chain</p>
  <div class="table-wrap" style="overflow:visible;background:transparent;border:none;padding:0;margin-bottom:2rem">
    <div class="chain-scroll">
      <div class="chain-wrap">
        <?php foreach ($activities as $i => $a):
          $hash    = $a['Hash']         ?? '';
          $prevH   = $a['PreviousHash'] ?? '0000...';
          $bIdx    = $a['BlockIndex']   ?? ($i+1);
          $nonce   = $a['Nonce']        ?? '-';
          $powOk   = strlen($hash) > 0 && substr($hash, 0, 3) === '000';
          $isFirst = ($i === 0);
        ?>
        <?php if ($i > 0): ?>
          <div class="chain-arrow">→</div>
        <?php endif; ?>
        <div class="block-card <?= $isFirst ? 'genesis' : '' ?>">
          <div class="block-num"><?= $isFirst ? '🏁 Genesis Block' : "Block #$bIdx" ?></div>
          <div class="block-id"><?= htmlspecialchars($a['ID'] ?? '-') ?></div>

          <div class="block-hash-label">Hash</div>
          <div class="block-hash"><?= htmlspecialchars(substr($hash, 0, 20) . '...') ?></div>

          <div class="block-hash-label">Prev Hash</div>
          <div class="block-prevhash"><?= htmlspecialchars(substr($prevH, 0, 20) . '...') ?></div>

          <div class="block-data">
            <strong><?= htmlspecialchars($a['EmployeeName'] ?? '-') ?></strong>
            <?= htmlspecialchars(substr($a['Activity'] ?? '-', 0, 30)) ?>
          </div>
          <div class="block-nonce">Nonce: <?= $nonce ?></div>
          <?php if ($powOk): ?>
          <div class="pow-badge">⛏ PoW Valid (000...)</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- PER KARYAWAN -->
  <?php if (!empty($employees)): ?>
  <p class="section-title">👥 Ringkasan Per Karyawan</p>
  <div class="emp-cards">
    <?php
    $empData = [];
    foreach ($activities as $a) {
        $name = $a['EmployeeName'] ?? 'Unknown';
        if (!isset($empData[$name])) $empData[$name] = ['dept'=>$a['Department']??'','count'=>0,'menit'=>0];
        $empData[$name]['count']++;
        $empData[$name]['menit'] += (int)($a['Duration']??0);
    }
    $maxMenit = max(array_column($empData,'menit')) ?: 1;
    foreach ($empData as $name => $d):
        $pct = round($d['menit']/$maxMenit*100);
        $dept = $d['dept'];
        $deptClass = 'dept-'.(in_array($dept,['Engineering','Marketing','Finance','Design'])?$dept:'default');
    ?>
    <div class="emp-card">
      <div class="emp-name"><?= htmlspecialchars($name) ?></div>
      <div class="emp-dept"><span class="dept-badge <?= $deptClass ?>"><?= htmlspecialchars($dept) ?></span></div>
      <div class="emp-stat"><span>Total Aktivitas</span><span><?= $d['count'] ?> block</span></div>
      <div class="emp-stat"><span>Total Jam Kerja</span><span><?= round($d['menit']/60,1) ?> jam</span></div>
      <div class="emp-bar-bg"><div class="emp-bar" style="width:<?= $pct ?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- TABLE SEMUA DATA -->
  <p class="section-title">📦 Semua Data Blockchain</p>
  <div class="table-wrap">
    <div class="table-head">
      <h2>Aktivitas WFH <span style="color:var(--muted);font-weight:400;font-size:.85rem">(<?= $total ?> block)</span></h2>
      <span class="readonly-badge">🔒 Read Only</span>
    </div>
    <?php if (!empty($activities)): ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>ID</th>
          <th>Karyawan</th>
          <th>Dept</th>
          <th>Aktivitas</th>
          <th>Durasi</th>
          <th>Hash (SHA-256)</th>
          <th>Prev Hash</th>
          <th>Nonce</th>
          <th>Tanggal</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($activities as $a):
        $dept = $a['Department']??'default';
        $deptClass='dept-'.(in_array($dept,['Engineering','Marketing','Finance','Design'])?$dept:'default');
        $type=$a['ActivityType']??'Productive';
        $typeClass='type-'.(in_array($type,['Productive','Meeting','Training'])?$type:'Productive');
        $hash  = $a['Hash']         ?? '';
        $prevH = $a['PreviousHash'] ?? '';
        $bIdx  = $a['BlockIndex']   ?? '-';
        $nonce = $a['Nonce']        ?? '-';
      ?>
        <tr>
          <td><span class="block-idx">#<?= $bIdx ?></span></td>
          <td><span class="id-badge"><?= htmlspecialchars($a['ID']??'-') ?></span></td>
          <td><strong><?= htmlspecialchars($a['EmployeeName']??'-') ?></strong></td>
          <td><span class="dept-badge <?= $deptClass ?>"><?= htmlspecialchars($dept) ?></span></td>
          <td><?= htmlspecialchars($a['Activity']??'-') ?></td>
          <td><strong><?= $a['Duration']??0 ?>m</strong></td>
          <td><span class="hash-mono" title="<?= htmlspecialchars($hash) ?>"><?= htmlspecialchars(substr($hash,0,16)) ?>...</span></td>
          <td><span class="prevhash-mono" title="<?= htmlspecialchars($prevH) ?>"><?= htmlspecialchars(substr($prevH,0,16)) ?>...</span></td>
          <td style="font-family:monospace;font-size:.75rem;color:var(--muted)"><?= $nonce ?></td>
          <td style="color:var(--muted);font-size:.78rem;font-family:monospace"><?= htmlspecialchars($a['Date']??'-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div style="padding:3rem;text-align:center;color:var(--muted)">Belum ada data aktivitas.</div>
    <?php endif; ?>
  </div>
</main>

<footer>⛓️ WFH Blockchain Monitor — Hyperledger Fabric | Admin: <?= htmlspecialchars($_SESSION['name']) ?> | <?= date('d M Y, H:i') ?></footer>

<script>
async function validateChain() {
    const btn = document.getElementById('btnValidate');
    const box = document.getElementById('validateResult');

    btn.disabled = true;
    btn.innerHTML = '⏳ Memvalidasi...';
    box.style.display = 'none';

    try {
        const resp = await fetch('http://localhost:3000/api/validate');
        const data = await resp.json();

        box.style.display = 'block';

        if (data.valid) {
            box.innerHTML = `
                <div class="valid-ok">
                    ✅ <strong>Blockchain VALID!</strong><br>
                    <span style="font-size:.85rem">${data.message}</span>
                    <br><span style="font-size:.78rem;opacity:.8">Total: ${data.totalBlocks} block terverifikasi. Semua SHA-256 hash cocok dan chain tidak terputus.</span>
                </div>`;
        } else {
            let errHtml = `<div class="valid-err">
                ❌ <strong>Blockchain TIDAK VALID!</strong><br>
                <span style="font-size:.85rem">${data.message}</span>`;
            if (data.errors && data.errors.length > 0) {
                data.errors.forEach(e => {
                    errHtml += `<div class="err-item">Block #${e.blockIndex} (${e.id}): ${e.message}</div>`;
                });
            }
            errHtml += '</div>';
            box.innerHTML = errHtml;
        }
    } catch (err) {
        box.style.display = 'block';
        box.innerHTML = `<div class="valid-err">⚠️ Gagal menghubungi API. Pastikan server.js berjalan di WSL.</div>`;
    }

    btn.disabled = false;
    btn.innerHTML = '🔍 Validasi Sekarang';
}
</script>
</body>
</html>
