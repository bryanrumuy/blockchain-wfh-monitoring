<?php
session_start();
if (!isset($_SESSION['role'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit; }
$success = '';
$error = '';

// Ambil total aktivitas untuk auto-ID
$chCount = curl_init('http://localhost:3000/api/activities');
curl_setopt($chCount, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chCount, CURLOPT_TIMEOUT, 3);
$countResp = curl_exec($chCount);
curl_close($chCount);
$countData = json_decode($countResp, true);
$total = (int)($countData['total'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_encode([
        'id'           => trim($_POST['id'] ?? ''),
        'employeeName' => trim($_POST['employeeName'] ?? ''),
        'department'   => trim($_POST['department'] ?? ''),
        'activity'     => trim($_POST['activity'] ?? ''),
        'duration'     => (int)($_POST['duration'] ?? 0),
        'activityType' => trim($_POST['activityType'] ?? 'Productive'),
        'notes'        => trim($_POST['notes'] ?? ''),
        'date'         => trim($_POST['date'] ?? date('Y-m-d')),
    ]);

    $ch = curl_init('http://localhost:3000/api/activities');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($resp, true);
    if ($httpCode === 201 && ($result['success'] ?? false)) {
        $success = "✅ Aktivitas <strong>" . htmlspecialchars($_POST['id']) . "</strong> berhasil dicatat ke blockchain!";
    } else {
        $error = "❌ Gagal: " . htmlspecialchars($result['error'] ?? 'Unknown error');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catat Aktivitas — WFH Blockchain</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0a0e1a; --surface:#111827; --card:#1a2235; --border:#1e2d45;
    --cyan:#06b6d4; --purple:#8b5cf6; --green:#10b981; --red:#ef4444;
    --text:#f1f5f9; --muted:#64748b;
  }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
  nav {
    background:rgba(17,24,39,.95); backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    padding:0 2rem; height:64px;
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:100;
  }
  .nav-brand { display:flex; align-items:center; gap:.75rem; font-weight:700; font-size:1.1rem; }
  .nav-brand .icon {
    width:36px; height:36px;
    background:linear-gradient(135deg,var(--cyan),var(--purple));
    border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem;
  }
  .nav-links { display:flex; gap:.5rem; }
  .nav-links a {
    padding:.4rem 1rem; border-radius:8px; color:var(--muted);
    text-decoration:none; font-size:.875rem; font-weight:500; transition:all .2s;
  }
  .nav-links a:hover, .nav-links a.active { background:rgba(6,182,212,.1); color:var(--cyan); }
  .back-btn {
    display:inline-flex; align-items:center; gap:.4rem;
    color:var(--muted); text-decoration:none; font-size:.875rem;
    transition:color .2s;
  }
  .back-btn:hover { color:var(--cyan); }
  main { padding:2rem; max-width:720px; margin:0 auto; }
  .page-title {
    text-align:center; padding:2.5rem 0 2rem;
  }
  .page-title h1 {
    font-size:2rem; font-weight:800;
    background:linear-gradient(135deg,var(--cyan),var(--purple));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent;
    margin-bottom:.5rem;
  }
  .page-title p { color:var(--muted); font-size:.9rem; }
  .alert {
    padding:1rem 1.25rem; border-radius:12px; margin-bottom:1.5rem;
    font-size:.9rem; border:1px solid;
  }
  .alert-success { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.3); color:#6ee7b7; }
  .alert-error   { background:rgba(239,68,68,.1);  border-color:rgba(239,68,68,.3);  color:#fca5a5; }
  .form-card {
    background:var(--card); border:1px solid var(--border);
    border-radius:20px; overflow:hidden;
  }
  .form-card-head {
    padding:1.25rem 1.75rem; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:.6rem;
    font-weight:600; font-size:1rem;
  }
  .form-body { padding:1.75rem; }
  .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .form-group { display:flex; flex-direction:column; gap:.4rem; }
  .form-group.full { grid-column:1/-1; }
  label { font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
  input, select, textarea {
    background:rgba(255,255,255,.05);
    border:1px solid var(--border);
    border-radius:10px;
    padding:.65rem 1rem;
    color:var(--text);
    font-family:'Inter',sans-serif;
    font-size:.9rem;
    transition:border-color .2s, background .2s;
    width:100%;
  }
  input:focus, select:focus, textarea:focus {
    outline:none;
    border-color:var(--cyan);
    background:rgba(6,182,212,.05);
  }
  input::placeholder, textarea::placeholder { color:var(--muted); }
  select option { background:#1a2235; }
  textarea { resize:vertical; min-height:80px; }
  .btn-submit {
    width:100%; margin-top:1.5rem;
    background:linear-gradient(135deg,var(--cyan),var(--purple));
    color:white; border:none; border-radius:12px;
    padding:.85rem; font-size:1rem; font-weight:700;
    cursor:pointer; transition:opacity .2s, transform .1s;
  }
  .btn-submit:hover { opacity:.9; transform:translateY(-1px); }
  .btn-submit:active { transform:translateY(0); }
  .info-tip {
    display:flex; align-items:flex-start; gap:.6rem;
    background:rgba(6,182,212,.07); border:1px solid rgba(6,182,212,.2);
    border-radius:10px; padding:.75rem 1rem; margin-top:1.5rem;
    font-size:.82rem; color:var(--muted);
  }
  .info-tip .ti { color:var(--cyan); font-size:1rem; flex-shrink:0; margin-top:.05rem; }
  footer {
    text-align:center; padding:2rem; color:var(--muted);
    font-size:.8rem; border-top:1px solid var(--border); margin-top:2rem;
  }
  @media(max-width:600px) { .form-grid { grid-template-columns:1fr; } }

  /* TIMER */
  .timer-wrap { display:flex; flex-direction:column; gap:.5rem; }
  .timer-display {
    background:rgba(0,0,0,.3);
    border:1px solid var(--border);
    border-radius:10px;
    padding:.5rem 1rem;
    font-family:monospace;
    font-size:1.2rem;
    font-weight:700;
    color:var(--cyan);
    text-align:center;
    letter-spacing:.1em;
  }
  .timer-display.running { color:var(--green); border-color:rgba(16,185,129,.4); animation:blink 1s infinite; }
  @keyframes blink { 50%{opacity:.7} }
  .timer-btns { display:flex; gap:.5rem; }
  .btn-timer {
    flex:1; padding:.5rem; border-radius:8px; border:none;
    font-size:.8rem; font-weight:700; cursor:pointer; transition:all .2s;
  }
  .btn-start { background:rgba(16,185,129,.2); color:var(--green); border:1px solid rgba(16,185,129,.3); }
  .btn-start:hover { background:rgba(16,185,129,.35); }
  .btn-stop  { background:rgba(239,68,68,.2); color:var(--red); border:1px solid rgba(239,68,68,.3); }
  .btn-stop:hover  { background:rgba(239,68,68,.35); }
  .btn-reset { background:rgba(100,116,139,.15); color:var(--muted); border:1px solid var(--border); }
  .btn-reset:hover { background:rgba(100,116,139,.3); }

  /* TOAST NOTIFICATION */
  .toast {
    position:fixed; top:80px; right:1.5rem; z-index:999;
    background:#1a2235;
    border:1px solid rgba(16,185,129,.4);
    border-left:4px solid var(--green);
    border-radius:12px;
    padding:1rem 1.25rem;
    min-width:300px; max-width:400px;
    display:flex; align-items:flex-start; gap:.75rem;
    box-shadow:0 8px 32px rgba(0,0,0,.4);
    animation:slideIn .3s ease;
    cursor:pointer;
  }
  .toast.error {
    border-color:rgba(239,68,68,.4);
    border-left-color:var(--red);
  }
  .toast-icon { font-size:1.4rem; flex-shrink:0; }
  .toast-body { flex:1; }
  .toast-title { font-weight:700; font-size:.9rem; margin-bottom:.2rem; color:var(--green); }
  .toast.error .toast-title { color:var(--red); }
  .toast-msg { font-size:.82rem; color:var(--muted); }
  .toast-close {
    color:var(--muted); font-size:1rem; background:none; border:none;
    cursor:pointer; padding:0; line-height:1; flex-shrink:0;
    transition:color .2s;
  }
  .toast-close:hover { color:var(--text); }
  .toast-progress {
    position:absolute; bottom:0; left:0;
    height:3px; border-radius:0 0 12px 12px;
    background:var(--green);
    animation:progress 4s linear forwards;
  }
  .toast.error .toast-progress { background:var(--red); }
  @keyframes slideIn {
    from { opacity:0; transform:translateX(120%); }
    to   { opacity:1; transform:translateX(0); }
  }
  @keyframes slideOut {
    from { opacity:1; transform:translateX(0); }
    to   { opacity:0; transform:translateX(120%); }
  }
  @keyframes progress {
    from { width:100%; }
    to   { width:0%; }
  }
</style>
</head>
<body>
<nav>
  <div class="nav-brand">
    <div class="icon">⛓️</div>
    <span>WFH Blockchain</span>
  </div>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="tambah.php" class="active">+ Catat Aktivitas</a>
  </div>
  <a href="index.php" class="back-btn">← Kembali</a>
</nav>

<main>
  <div class="page-title">
    <h1>⛏ Catat Aktivitas</h1>
    <p>Tambah aktivitas WFH baru ke blockchain Hyperledger Fabric</p>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error"><?= $error ?></div>
  <?php endif; ?>

  <div class="form-card">
    <div class="form-card-head">📝 Form Pencatatan Aktivitas</div>
    <div class="form-body">
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label>ID Aktivitas</label>
            <input type="text" name="id" placeholder="act7" required
                   value="act<?= $total + 1 ?>"
                   pattern="[a-zA-Z0-9_-]+">
          </div>
          <div class="form-group">
            <label>Nama Karyawan</label>
            <input type="text" name="employeeName"
                   value="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>"
                   readonly
                   style="background:rgba(255,255,255,.03);color:var(--muted);cursor:not-allowed;"
                   title="Nama otomatis dari akun login">
          </div>
          <div class="form-group">
            <label>Departemen</label>
            <select name="department">
              <option value="Engineering">Engineering</option>
              <option value="Marketing">Marketing</option>
              <option value="Finance">Finance</option>
              <option value="Design">Design</option>
              <option value="HR">HR</option>
              <option value="Operations">Operations</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tipe Aktivitas</label>
            <select name="activityType">
              <option value="Productive">💻 Productive</option>
              <option value="Meeting">🎙️ Meeting</option>
              <option value="Training">📚 Training</option>
            </select>
          </div>
          <div class="form-group full">
            <label>Aktivitas / Tugas</label>
            <input type="text" name="activity" placeholder="Coding - Feature Authentication" required>
          </div>
          <div class="form-group">
            <label>Durasi (menit)</label>
            <div class="timer-wrap">
              <div class="timer-display" id="timerDisplay">00:00:00</div>
              <div class="timer-btns">
                <button type="button" class="btn-timer btn-start" id="btnMulai" onclick="startTimer()">▶ Mulai</button>
                <button type="button" class="btn-timer btn-stop"  id="btnStop"  onclick="stopTimer()" disabled>■ Selesai</button>
                <button type="button" class="btn-timer btn-reset" id="btnReset" onclick="resetTimer()">↺ Reset</button>
              </div>
              <input type="number" name="duration" id="durasiInput" min="1" max="600"
                     placeholder="atau isi manual" required style="text-align:center">
            </div>
          </div>
          <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group full">
            <label>Catatan</label>
            <textarea name="notes" placeholder="Deskripsi singkat tentang aktivitas yang dikerjakan..."></textarea>
          </div>
        </div>
        <button type="submit" class="btn-submit">⛏ Submit ke Blockchain</button>
      </form>
      <script>
        // ===== STOPWATCH TIMER =====
        let timerInterval = null;
        let startTime = null;
        let elapsed = 0; // dalam detik

        function pad(n){ return String(n).padStart(2,'0'); }

        function updateDisplay(sec) {
          const h = Math.floor(sec/3600);
          const m = Math.floor((sec%3600)/60);
          const s = sec%60;
          document.getElementById('timerDisplay').textContent = pad(h)+':'+pad(m)+':'+pad(s);
        }

        function startTimer() {
          if (timerInterval) return;
          startTime = Date.now() - elapsed * 1000;
          timerInterval = setInterval(() => {
            elapsed = Math.floor((Date.now() - startTime) / 1000);
            updateDisplay(elapsed);
          }, 1000);
          document.getElementById('timerDisplay').classList.add('running');
          document.getElementById('btnMulai').disabled = true;
          document.getElementById('btnStop').disabled  = false;
        }

        function stopTimer() {
          if (!timerInterval) return;
          clearInterval(timerInterval);
          timerInterval = null;
          document.getElementById('timerDisplay').classList.remove('running');

          const menit = Math.max(1, Math.floor(elapsed / 60));

          document.getElementById('durasiInput').value = menit;
          document.getElementById('btnMulai').disabled = false;
          document.getElementById('btnStop').disabled  = true;
        }

        function resetTimer() {
          clearInterval(timerInterval);
          timerInterval = null;
          elapsed = 0;
          updateDisplay(0);
          document.getElementById('timerDisplay').classList.remove('running');
          document.getElementById('durasiInput').value = '';
          document.getElementById('btnMulai').disabled = false;
          document.getElementById('btnStop').disabled  = true;
        }
      </script>
      <div class="info-tip">
        <span class="ti">ℹ</span>
        <span>Data akan di-submit ke <strong>Hyperledger Fabric</strong> via REST API dan dicatat secara permanen di blockchain. Pastikan <code>node src/server.js</code> sedang berjalan di WSL.</span>
      </div>
    </div>
  </div>
</main>
<footer>⛓️ WFH Activity Monitoring — Hyperledger Fabric | <?= date('d M Y') ?></footer>

<?php if (isset($_GET['sukses'])): ?>
<div class="toast" id="toast" onclick="dismissToast()" style="position:fixed">
  <div style="position:relative">
    <div class="toast-progress"></div>
  </div>
  <div class="toast-icon">✅</div>
  <div class="toast-body">
    <div class="toast-title">Berhasil dicatat ke Blockchain!</div>
    <div class="toast-msg">ID <strong style="color:var(--cyan)"><?= htmlspecialchars($_GET['sukses']) ?></strong> tersimpan permanen di Hyperledger Fabric</div>
  </div>
  <button class="toast-close" onclick="dismissToast()">✕</button>
</div>
<script>
  function dismissToast() {
    const t = document.getElementById('toast');
    if (t) { t.style.animation = 'slideOut .3s ease forwards'; setTimeout(()=>t.remove(), 300); }
  }
  setTimeout(dismissToast, 4000);
</script>
<?php endif; ?>

<?php if ($error): ?>
<div class="toast error" id="toastErr" onclick="dismissToastErr()" style="position:fixed;border-left-color:var(--red)">
  <div style="position:relative">
    <div class="toast-progress"></div>
  </div>
  <div class="toast-icon">❌</div>
  <div class="toast-body">
    <div class="toast-title" style="color:var(--red)">Gagal Submit!</div>
    <div class="toast-msg"><?= htmlspecialchars(strip_tags($error)) ?></div>
  </div>
  <button class="toast-close" onclick="dismissToastErr()">✕</button>
</div>
<script>
  function dismissToastErr() {
    const t = document.getElementById('toastErr');
    if (t) { t.style.animation = 'slideOut .3s ease forwards'; setTimeout(()=>t.remove(), 300); }
  }
  setTimeout(dismissToastErr, 6000);
</script>
<?php endif; ?>

</body>
</html>
