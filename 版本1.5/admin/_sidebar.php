<?php
$current = basename($_SERVER['PHP_SELF']);
function nav($href, $icon, $label, $current) {
    $active = ($current === $href) ? 'active' : '';
    echo "<a href='$href' class='nav-item $active'>$icon <span>$label</span></a>";
}
?>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  min-height:100vh;display:flex;
  background:#0d0d1a;
  font-family:'Segoe UI','微軟正黑體',sans-serif;
  color:#e0e0e0;
}
/* ── SIDEBAR ── */
.sidebar{
  width:220px;min-height:100vh;
  background:#1a1a2e;
  border-right:1px solid #2a2a4a;
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;z-index:100;
}
.sidebar-logo{
  padding:24px 20px 20px;
  border-bottom:1px solid #2a2a4a;
}
.sidebar-logo .logo-icon{font-size:28px;display:block;margin-bottom:6px;}
.sidebar-logo h2{font-size:16px;color:#4fc3f7;letter-spacing:2px;font-weight:700;}
.sidebar-logo p{font-size:11px;color:#7d8fa3;margin-top:2px;}
.sidebar-nav{flex:1;padding:16px 0;}
.nav-section{
  padding:8px 20px 4px;
  font-size:10px;color:#7d8fa3;letter-spacing:2px;text-transform:uppercase;
}
.nav-item{
  display:flex;align-items:center;gap:10px;
  padding:11px 20px;
  color:#b0bec5;text-decoration:none;
  font-size:14px;transition:all .2s;
  border-left:3px solid transparent;
}
.nav-item:hover{color:#e0e0e0;background:rgba(79,195,247,.06);border-left-color:#4fc3f7;}
.nav-item.active{color:#4fc3f7;background:rgba(79,195,247,.1);border-left-color:#4fc3f7;font-weight:600;}
.sidebar-footer{
  padding:16px 20px;
  border-top:1px solid #2a2a4a;
  font-size:12px;
}
.admin-badge{
  display:flex;align-items:center;gap:10px;margin-bottom:14px;
}
.admin-badge .avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,#1565c0,#4fc3f7);
  display:flex;align-items:center;justify-content:center;
  font-size:16px;
}
.admin-badge .info span{display:block;}
.admin-badge .info .name{font-size:13px;color:#e0e0e0;font-weight:600;}
.admin-badge .info .role{font-size:11px;color:#7d8fa3;}
.btn-logout{
  display:block;width:100%;padding:9px;
  background:rgba(239,83,80,.1);border:1px solid rgba(239,83,80,.3);
  color:#ef9a9a;border-radius:7px;text-decoration:none;
  font-size:13px;text-align:center;transition:all .2s;
}
.btn-logout:hover{background:rgba(239,83,80,.2);color:#ef5350;}

/* ── MAIN ── */
.main{
  margin-left:220px;flex:1;
  display:flex;flex-direction:column;min-height:100vh;
}
.topbar{
  height:58px;
  background:#16213e;
  border-bottom:1px solid #2a2a4a;
  display:flex;align-items:center;
  padding:0 28px;
  gap:12px;
  position:sticky;top:0;z-index:50;
}
.topbar .page-title{font-size:17px;font-weight:700;color:#e0e0e0;flex:1;}
.topbar .breadcrumb{font-size:12px;color:#8899b0;}
.topbar .breadcrumb span{color:#4fc3f7;}
.content{padding:28px;flex:1;}

/* ── CARDS ── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px;}
.stat-card{
  background:#16213e;border:1px solid #2a2a4a;border-radius:12px;
  padding:22px 20px;transition:border-color .2s;
}
.stat-card:hover{border-color:#4fc3f7;}
.stat-card .label{font-size:12px;color:#94a3b8;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;}
.stat-card .value{font-size:32px;font-weight:700;color:#e0e0e0;line-height:1;}
.stat-card .sub{font-size:12px;color:#7d8fa3;margin-top:6px;}
.stat-card.blue .value{color:#4fc3f7;}
.stat-card.green .value{color:#66bb6a;}
.stat-card.yellow .value{color:#ffca28;}
.stat-card.red .value{color:#ef5350;}

/* ── TABLE ── */
.section{background:#16213e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;margin-bottom:24px;}
.section-header{
  padding:16px 22px;border-bottom:1px solid #2a2a4a;
  display:flex;align-items:center;justify-content:space-between;
}
.section-header h3{font-size:15px;font-weight:700;color:#e0e0e0;}
.section-header .badge{
  font-size:11px;padding:3px 10px;border-radius:20px;
  background:rgba(79,195,247,.1);color:#4fc3f7;border:1px solid rgba(79,195,247,.3);
}
.tbl{width:100%;border-collapse:collapse;}
.tbl th{
  padding:12px 16px;text-align:left;
  font-size:11px;color:#8899b0;letter-spacing:1.5px;text-transform:uppercase;
  border-bottom:1px solid #1a1a2e;background:#0f1829;
}
.tbl td{
  padding:13px 16px;font-size:14px;color:#ccc;
  border-bottom:1px solid #1a1a2e;
}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(79,195,247,.03);}
.tbl .rank{font-weight:700;color:#ffca28;}
.tbl .rank.gold{color:#ffd700;}
.tbl .rank.silver{color:#c0c0c0;}
.tbl .rank.bronze{color:#cd7f32;}

/* ── BUTTONS ── */
.btn{
  padding:7px 14px;border-radius:6px;border:1px solid;
  font-size:12px;cursor:pointer;font-weight:600;transition:all .2s;
}
.btn-primary{background:rgba(79,195,247,.1);color:#4fc3f7;border-color:rgba(79,195,247,.4);}
.btn-primary:hover{background:rgba(79,195,247,.2);}
.btn-danger{background:rgba(239,83,80,.1);color:#ef5350;border-color:rgba(239,83,80,.4);}
.btn-danger:hover{background:rgba(239,83,80,.2);}
.btn-success{background:rgba(102,187,106,.1);color:#66bb6a;border-color:rgba(102,187,106,.4);}
.btn-success:hover{background:rgba(102,187,106,.2);}
.btn-warning{background:rgba(255,202,40,.1);color:#ffca28;border-color:rgba(255,202,40,.4);}
.btn-warning:hover{background:rgba(255,202,40,.2);}

/* ── TAGS ── */
.tag{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;}
.tag-win{background:rgba(102,187,106,.15);color:#66bb6a;}
.tag-lose{background:rgba(239,83,80,.15);color:#ef5350;}
.tag-escape{background:rgba(255,202,40,.15);color:#ffca28;}
.tag-banned{background:rgba(239,83,80,.15);color:#ef5350;}
.tag-active{background:rgba(102,187,106,.15);color:#66bb6a;}

/* ── SEARCH ── */
.search-bar{
  display:flex;align-items:center;gap:12px;
  padding:16px 22px;border-bottom:1px solid #2a2a4a;
}
.search-bar input, .search-bar select{
  padding:9px 14px;background:#0d0d1a;
  border:1px solid #2a2a4a;border-radius:7px;
  color:#e0e0e0;font-size:13px;
}
.search-bar input{flex:1;}
.search-bar input:focus, .search-bar select:focus{outline:none;border-color:#4fc3f7;}
.search-bar select option{background:#16213e;}

/* ── TABS ── */
.tabs{display:flex;border-bottom:1px solid #2a2a4a;padding:0 22px;}
.tab-btn{
  padding:14px 22px;font-size:13px;cursor:pointer;
  border:none;background:none;color:#94a3b8;
  border-bottom:2px solid transparent;transition:all .2s;font-weight:600;
}
.tab-btn.active, .tab-btn:hover{color:#4fc3f7;}
.tab-btn.active{border-bottom-color:#4fc3f7;}
.tab-content{display:none;}
.tab-content.active{display:block;}

/* ── MODAL ── */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.75);z-index:200;
  align-items:center;justify-content:center;
}
.modal-overlay.show{display:flex;}
.modal{
  background:#16213e;border:1px solid #2a2a4a;
  border-radius:12px;padding:32px;width:400px;
  box-shadow:0 20px 60px rgba(0,0,0,.6);
}
.modal h3{font-size:17px;color:#e0e0e0;margin-bottom:12px;}
.modal p{font-size:14px;color:#888;line-height:1.6;margin-bottom:24px;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;}

/* ── ALERT ── */
.alert{padding:12px 18px;border-radius:8px;font-size:13px;margin-bottom:20px;}
.alert-success{background:rgba(102,187,106,.12);border:1px solid rgba(102,187,106,.3);color:#a5d6a7;}
.alert-danger{background:rgba(239,83,80,.12);border:1px solid rgba(239,83,80,.3);color:#ef9a9a;}
</style>

<div class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">⚔️</span>
    <h2>後台管理</h2>
    <p>TAR GAME ADMIN</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">主選單</div>
    <?php nav('index.php','📊','系統總覽',$current); ?>
    <?php nav('players.php','👥','玩家管理',$current); ?>
    <?php nav('records.php','📋','紀錄查詢',$current); ?>
    <div class="nav-section" style="margin-top:12px;">系統層</div>
    <?php nav('db_layer.php','🗄️','資料庫層',$current); ?>
    <?php nav('api_module.php','🔌','API 模組',$current); ?>
    <div class="nav-section" style="margin-top:12px;">遊戲前台</div>
    <a href="../index.php" class="nav-item" target="_blank">🏠 <span>主城鎮</span></a>
    <a href="../tower.php" class="nav-item" target="_blank">🗼 <span>塔探索</span></a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-badge">
      <div class="avatar">👤</div>
      <div class="info">
        <span class="name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></span>
        <span class="role">超級管理員</span>
      </div>
    </div>
    <a href="logout.php" class="btn-logout">⏻ 登出</a>
  </div>
</div>
<div class="main">
