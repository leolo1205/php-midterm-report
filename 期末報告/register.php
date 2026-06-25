<?php
ob_start();
session_start();
if (isset($_SESSION['player_id'])) { header('Location: index.php'); exit; }

require_once 'db.php';
require_once 'lib/session.php';

$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('安全驗證失敗，請重新整理頁面後再試。');
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (mb_strlen($username) < 2 || mb_strlen($username) > 16) {
        $error = '名稱長度需在 2～16 字之間';
    } elseif (preg_match('/[<>"\']/', $username)) {
        $error = '名稱含有不允許的字元';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要 6 個字元';
    } elseif ($password !== $confirm) {
        $error = '兩次輸入的密碼不一致';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param('s', $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = '此名稱已被使用，請換一個';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, hp, max_hp, dmg, def, level, exp, gold, max_floor) VALUES (?, ?, 100, 100, 10, 0, 1, 0, 0, 0)");
            $stmt->bind_param('ss', $username, $hash);
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $pvp = $conn->prepare("INSERT IGNORE INTO pvp_rankings (user_id, rating, wins, losses, streak) VALUES (?, 1000, 0, 0, 0)");
                if ($pvp) {
                    $pvp->bind_param('i', $new_id);
                    $pvp->execute();
                    $pvp->close();
                }
                session_regenerate_id(true);
                $_SESSION['player_id']   = $new_id;
                $_SESSION['player_name'] = $username;
                header('Location: index.php');
                exit;
            } else {
                $error = '註冊失敗，請稍後再試';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>註冊 — 塔城傳說</title>
<link rel="stylesheet" href="assets/style.css">
<style>
/* register 頁面專屬 */
body { display:flex; align-items:center; justify-content:center; }
.bg-glow { position:fixed; pointer-events:none; border-radius:50%; filter:blur(120px); opacity:.15; }
.bg-glow.g1 { width:600px; height:600px; background:#1565c0; top:-100px; left:-200px; }
.bg-glow.g2 { width:500px; height:500px; background:#00695c; bottom:-100px; right:-100px; }
.card { width:420px; background:#111827; border:1px solid #1f2937; border-radius:20px; padding:48px 44px; box-shadow:0 30px 80px rgba(0,0,0,.7); position:relative; z-index:1; }
.card-header { text-align:center; margin-bottom:32px; }
.card-header .icon { font-size:52px; display:block; margin-bottom:12px; }
.card-header h2 { font-size:22px; font-weight:700; color:var(--accent-blue); letter-spacing:2px; }
.card-header p { font-size:12px; color:#4b5563; margin-top:6px; letter-spacing:1px; }
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-size:11px; color:#6b7280; margin-bottom:7px; letter-spacing:1.5px; text-transform:uppercase; }
.form-group input { width:100%; padding:12px 15px; background:#0d111a; border:1px solid #1f2937; border-radius:8px; color:#e5e7eb; font-size:14px; transition:border-color .2s,box-shadow .2s; }
.form-group input:focus { outline:none; border-color:var(--accent-blue); box-shadow:0 0 0 3px rgba(79,195,247,.08); }
.btn { width:100%; padding:13px; border:none; border-radius:8px; background:linear-gradient(135deg,#1565c0,#4fc3f7); color:#fff; font-size:14px; font-weight:700; cursor:pointer; letter-spacing:2px; transition:opacity .2s,transform .1s; margin-top:4px; }
.btn:hover { opacity:.9; transform:translateY(-1px); }
.btn:active { transform:translateY(0); }
.error-msg { padding:10px 13px; border-radius:7px; font-size:12px; margin-bottom:18px; text-align:center; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.3); color:#ef9a9a; }
.rules { background:#0d111a; border:1px solid #1f2937; border-radius:8px; padding:12px 14px; margin-bottom:22px; font-size:12px; color:#6b7280; line-height:1.9; }
.rules b { color:#94a3b8; }
.footer-link { text-align:center; margin-top:24px; font-size:12px; color:#4b5563; }
.footer-link a { color:var(--accent-blue); text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="bg-glow g1"></div>
<div class="bg-glow g2"></div>

<div class="card">
  <div class="card-header">
    <span class="icon">⚔️</span>
    <h2>建立角色</h2>
    <p>TAR GAME · REGISTER</p>
  </div>

  <?php if ($error): ?>
  <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="rules">
    <b>初始屬性</b>：HP 100 · 傷害 10 · 防禦 0 · Lv.1<br>
    名稱：2～16 字 &nbsp;｜&nbsp; 密碼：至少 6 碼<br>
    註冊後自動登入，直接進入遊戲
  </div>

  <form method="POST" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>角色名稱</label>
      <input type="text" name="username"
             placeholder="2～16 字，中英皆可"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             maxlength="16" required autofocus>
    </div>
    <div class="form-group">
      <label>密碼</label>
      <input type="password" name="password" placeholder="至少 6 個字元" required>
    </div>
    <div class="form-group">
      <label>確認密碼</label>
      <input type="password" name="confirm" placeholder="再輸入一次" required>
    </div>
    <button type="submit" class="btn">⚔ 建立角色並開始遊戲</button>
  </form>

  <div class="footer-link">
    已有帳號？<a href="login.php">返回登入</a>
  </div>
</div>
</body>
</html>
