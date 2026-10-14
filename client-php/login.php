<?php
session_start();
if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error      = '';
$apiOffline = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Coba login via blockchain (REST API)
    $payload = json_encode(['username' => $username, 'password' => $password]);
    $ch = curl_init('http://localhost:3000/api/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ]);
    $resp    = curl_exec($ch);
    $curlErr = curl_errno($ch);
    curl_close($ch);

    if (!$curlErr && $resp) {
        // API online — cek hasil dari blockchain
        $result = json_decode($resp, true);
        if ($result['success'] ?? false) {
            $user = $result['user'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name']     = $user['name'];
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        } else {
            $error = $result['message'] ?? 'Username atau password salah!';
        }
    } else {
        // API offline — fallback ke hardcoded
        $apiOffline = true;
        $accounts = [
            'admin'    => ['password' => 'admin123', 'role' => 'admin', 'name' => 'Administrator'],
            'employee1'    => ['password' => 'user123',  'role' => 'user',  'name' => 'Employee 1'],
            'employee2'     => ['password' => 'user123',  'role' => 'user',  'name' => 'Employee 2'],
            'employee3'  => ['password' => 'user123',  'role' => 'user',  'name' => 'Employee 3'],
            'employee4'   => ['password' => 'user123',  'role' => 'user',  'name' => 'Employee 4'],
            'employee5' => ['password' => 'user123',  'role' => 'user',  'name' => 'Employee 5'],
        ];
        if (isset($accounts[$username]) && $accounts[$username]['password'] === $password) {
            $_SESSION['role']     = $accounts[$username]['role'];
            $_SESSION['username'] = $username;
            $_SESSION['name']     = $accounts[$username]['name'];
            header('Location: ' . ($accounts[$username]['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        } else {
            $error = 'Username atau password salah! (Mode Offline)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — WFH Blockchain Monitor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#0a0e1a;--card:#1a2235;--border:#1e2d45;--cyan:#06b6d4;--purple:#8b5cf6;--green:#10b981;--red:#ef4444;--text:#f1f5f9;--muted:#64748b}
  body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
  .bg-glow{position:fixed;top:-200px;left:50%;transform:translateX(-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(6,182,212,.12) 0%,transparent 70%);pointer-events:none}
  .card{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2.5rem;width:100%;max-width:420px;position:relative}
  .logo{text-align:center;margin-bottom:2rem}
  .logo-icon{width:64px;height:64px;background:linear-gradient(135deg,var(--cyan),var(--purple));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1rem}
  .logo h1{font-size:1.5rem;font-weight:800;background:linear-gradient(135deg,var(--cyan),var(--purple));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
  .logo p{color:var(--muted);font-size:.85rem;margin-top:.3rem}
  .form-group{margin-bottom:1rem}
  label{display:block;font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem}
  input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;padding:.7rem 1rem;color:var(--text);font-family:'Inter',sans-serif;font-size:.9rem;transition:border-color .2s}
  input:focus{outline:none;border-color:var(--cyan);background:rgba(6,182,212,.05)}
  input::placeholder{color:var(--muted)}
  .btn-login{width:100%;margin-top:1.5rem;background:linear-gradient(135deg,var(--cyan),var(--purple));color:white;border:none;border-radius:12px;padding:.85rem;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s}
  .btn-login:hover{opacity:.9}
  .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;margin-bottom:1rem;text-align:center}
  .alert-warning{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fcd34d;border-radius:10px;padding:.75rem 1rem;font-size:.82rem;margin-bottom:1rem;text-align:center}
  .blockchain-badge{display:flex;align-items:center;justify-content:center;gap:.4rem;background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:8px;padding:.5rem;margin-bottom:1.5rem;font-size:.78rem;color:var(--cyan)}
  .demo-accounts{margin-top:1.5rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;padding:1rem}
  .demo-title{font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem}
  .demo-row{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.82rem;cursor:pointer;transition:color .2s}
  .demo-row:last-child{border-bottom:none}
  .demo-row:hover{color:var(--cyan)}
  .demo-badge{padding:.15rem .5rem;border-radius:5px;font-size:.7rem;font-weight:700}
  .badge-admin{background:rgba(139,92,246,.2);color:var(--purple)}
  .badge-user{background:rgba(6,182,212,.2);color:var(--cyan)}
  .demo-cred{color:var(--muted);font-family:monospace;font-size:.78rem}
</style>
</head>
<body>
<div class="bg-glow"></div>
<div class="card">
  <div class="logo">
    <div class="logo-icon">⛓️</div>
    <h1>WFH Blockchain</h1>
    <p>Sistem Monitoring Aktivitas Hyperledger Fabric</p>
  </div>

  <div class="blockchain-badge">
    🔐 Login diverifikasi via Hyperledger Fabric Blockchain
  </div>

  <?php if ($apiOffline): ?>
  <div class="alert-warning">⚠️ API Offline — Mode fallback aktif. Jalankan <code>node src/server.js</code> di WSL.</div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" id="usr" placeholder="admin / employee1 / employee2 ..." required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" id="pwd" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login">🔐 Login via Blockchain</button>
  </form>

  <div class="demo-accounts">
    <div class="demo-title">Akun Demo (klik untuk isi otomatis)</div>
    <div class="demo-row" onclick="fillLogin('admin','admin123')">
      <span>admin <span class="demo-badge badge-admin">ADMIN</span></span>
      <span class="demo-cred">admin123</span>
    </div>
    <div class="demo-row" onclick="fillLogin('employee1','user123')">
      <span>employee1 <span class="demo-badge badge-user">USER</span></span>
      <span class="demo-cred">user123</span>
    </div>
    <div class="demo-row" onclick="fillLogin('employee2','user123')">
      <span>employee2 <span class="demo-badge badge-user">USER</span></span>
      <span class="demo-cred">user123</span>
    </div>
    <div class="demo-row" onclick="fillLogin('employee3','user123')">
      <span>employee3 <span class="demo-badge badge-user">USER</span></span>
      <span class="demo-cred">user123</span>
    </div>
    <div class="demo-row" onclick="fillLogin('employee4','user123')">
      <span>employee4 <span class="demo-badge badge-user">USER</span></span>
      <span class="demo-cred">user123</span>
    </div>
    <div class="demo-row" onclick="fillLogin('employee5','user123')">
      <span>employee5 <span class="demo-badge badge-user">USER</span></span>
      <span class="demo-cred">user123</span>
    </div>
  </div>
</div>
<script>
function fillLogin(u, p) {
  document.getElementById('usr').value = u;
  document.getElementById('pwd').value = p;
}
</script>
</body>
</html>
