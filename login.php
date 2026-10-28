<?php
/**
 * Login username/password + Login with Google (GIS / OAuth).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$co = one('SELECT * FROM company_settings WHERE id=1') ?: ['name' => APP_NAME, 'logo_path' => null];

/* --- Login manual --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    verify_csrf();
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';
    $u = one('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1', [$username, strtolower($username)]);
    if ($u && !empty($u['password_hash']) && password_verify($password, $u['password_hash'])) {
        login_user($u);
        flash('success', 'Selamat datang, ' . ($u['name'] ?: $u['email']));
        redirect('dashboard.php');
    }
    $error = 'Username/email atau password salah.';
}

/* --- Login dengan Google: token credential dari Google Identity Services --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $token = $_POST['credential'];
    $payload = null;
    // Verifikasi JWT ke endpoint tokeninfo Google (aman, server-side)
    if (GOOGLE_CLIENT_ID) {
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $res = curl_exec($ch); curl_close($ch);
        $data = json_decode((string)$res, true);
        if (isset($data['aud']) && $data['aud'] === GOOGLE_CLIENT_ID && isset($data['sub'])) {
            $payload = $data;
        }
    } else {
        /* Mode dev tanpa client-id: decode payload saja (tanpa verifikasi signature).
           Produksi WAJIB mengisi GOOGLE_CLIENT_ID di config. */
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $p = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (is_array($p) && isset($p['sub'], $p['email'])) $payload = $p;
        }
    }

    if (!$payload) {
        $error = 'Verifikasi Google gagal.';
    } else {
        $email = strtolower($payload['email']);
        $u = find_user_by_email($email);
        if (!$u) {
            // auto-provision akun EMPLOYEE baru untuk email google yang terdaftar sebagai karyawan
            $emp = one('SELECT id FROM employees WHERE LOWER(email)=? AND flag="active"', [$email]);
            $sql = 'INSERT INTO users (employee_id, email, google_sub, role) VALUES (?,?,?, "EMPLOYEE")';
            exec_sql($sql, [$emp['id'] ?? null, $email, $payload['sub']]);
            $u = find_user_by_email($email);
            $u['name'] = $payload['name'] ?? $email;
        } elseif (empty($u['google_sub'])) {
            exec_sql('UPDATE users SET google_sub=? WHERE id=?', [$payload['sub'], $u['id']]);
        }
        $u['name'] = $u['name'] ?? ($payload['name'] ?: $email);
        login_user($u);
        audit('LOGIN_GOOGLE', 'Login via Google: ' . $email);
        flash('success', 'Login Google berhasil. Selamat datang!');
        redirect('dashboard.php');
    }
}
$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= e($co['name']) ?></title>
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center mb-3">
      <?php if (!empty($co['logo_path'])): ?>
        <img src="<?= e($co['logo_path']) ?>" style="height:70px" class="mb-2 rounded">
      <?php else: ?>
        <div class="login-logo"><i class="bi bi-buildings"></i></div>
      <?php endif; ?>
      <h1 class="h4 fw-bold mb-0" style="color:var(--primary)"><?= e($co['name']) ?></h1>
      <div class="text-muted-sm">Human Resource Information System</div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="credential" id="g_credential">
      <div class="mb-3">
        <label class="form-label small fw-bold">Username / Email</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
        <input class="form-control" name="username" placeholder="contoh: admin" required></div>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-bold">Password</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input class="form-control" type="password" name="password" placeholder="••••••••" required></div>
      </div>
      <button class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
    </form>

    <div class="divider-text">atau</div>

    <button type="button" class="btn-google" onclick="doGoogle()">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.5 6.1 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.5 6.1 29.5 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.6l6.2 5.2C41.4 35.4 44 30.2 44 24c0-1.3-.1-2.3-.4-3.5z"/></svg>
      Login with Google
    </button>

    <div class="text-center mt-3 small text-muted">
      Demo: <code>admin</code> (HR) · <code>finance</code> (FIN) · <code>joko</code> (Karyawan)<br>Password: <code>password</code>
    </div>
  </div>
</div>
<script>
function doGoogle(){
  if (!window.google || !google.accounts) { alert('Layanan Google belum dimuat. Periksa koneksi internet.'); return; }
  google.accounts.id.initialize({
    client_id: '<?= e(GOOGLE_CLIENT_ID ?: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') ?>',
    callback: function(resp){
      document.getElementById('g_credential').value = resp.credential;
      const f = document.createElement('form');
      f.method='POST';
      f.innerHTML='<input name="credential" value="'+resp.credential+'">';
      document.body.appendChild(f); f.submit();
    }
  });
  google.accounts.id.prompt();
}
</script>
</body>
</html>
