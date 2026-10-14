<?php
$ch = curl_init('http://localhost:3000/api/init');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 30,
]);
$resp = curl_exec($ch);
$err  = curl_errno($ch) ? curl_error($ch) : null;
curl_close($ch);

$result = $err ? null : json_decode($resp, true);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Init Blockchain</title>
<style>
  body{font-family:monospace;background:#0a0e1a;color:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#1a2235;border:1px solid #1e2d45;border-radius:16px;padding:2rem;max-width:500px;width:100%;text-align:center}
  h2{margin-bottom:1rem}
  pre{background:rgba(0,0,0,.3);padding:1rem;border-radius:8px;text-align:left;font-size:.85rem;overflow:auto}
  .ok{color:#10b981} .err{color:#ef4444}
  a{color:#06b6d4;margin-top:1.5rem;display:inline-block}
</style>
</head>
<body>
<div class="box">
  <h2>⛓ Init Blockchain</h2>
  <?php if ($err): ?>
    <p class="err">⚠️ API tidak tersambung: <?= htmlspecialchars($err) ?></p>
  <?php elseif ($result['success'] ?? false): ?>
    <p class="ok">✅ <?= htmlspecialchars($result['message']) ?></p>
    <p style="color:#64748b;font-size:.85rem;margin-top:.5rem">User dan data awal berhasil dibuat di blockchain.</p>
  <?php else: ?>
    <p class="err">❌ Gagal: <?= htmlspecialchars($result['message'] ?? $resp) ?></p>
  <?php endif; ?>
  <pre><?= htmlspecialchars($resp ?: $err) ?></pre>
  <a href="/blockchain-wfh-monitoring/client-php/login.php">→ Ke halaman Login</a>
</div>
</body>
</html>
