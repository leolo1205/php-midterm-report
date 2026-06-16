# UI 全站重設計 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 將全站 UI 統一為以 `forge.php` 為基準的設計語言，以共用 CSS 檔案取代各頁面 inline style。

**Architecture:** 建立 `assets/style.css`（設計 token + 共用元件）與 `assets/admin.css`（後台補充），建立前台 `_sidebar.php` include 檔，再逐一更新所有 PHP 頁面移除 `<style>` 區塊並套用新 class。

**Tech Stack:** PHP, HTML/CSS, 無框架（原生 CSS 變數）

**Spec:** `docs/superpowers/specs/2026-06-17-ui-redesign-design.md`

---

## Task 1: 建立 assets/style.css（全站共用設計系統）

**Files:**
- Create: `版本1.6裝備鍛造/assets/style.css`

- [ ] **Step 1: 建立 assets/ 目錄並寫入 style.css**

```css
/* ======================================================
   塔城傳說 — 全站共用設計系統
   以 forge.php 配色為基準
   ====================================================== */

/* ── 設計 TOKEN ── */
:root {
  --bg-base:        #0d0d1a;
  --bg-card:        #16213e;
  --bg-panel:       #1a1a2e;
  --accent:         #ffca28;
  --accent-blue:    #4fc3f7;
  --accent-red:     #ef5350;
  --accent-green:   #66bb6a;
  --text-primary:   #e0e0e0;
  --text-muted:     #94a3b8;
  --text-dim:       #64748b;
  --border:         #2a2a4a;
  --border-hover:   #4fc3f7;
}

/* ── 重置 ── */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Segoe UI', '微軟正黑體', sans-serif;
  background: var(--bg-base);
  color: var(--text-primary);
  min-height: 100vh;
}

/* ── 側邊欄版面（有側邊欄的頁面） ── */
.page-body {
  padding: 20px;
}

/* ── 置中版面（login, register） ── */
.page-center {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

/* ── 側邊欄 ── */
.sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: 220px;
  background: var(--bg-panel);
  border-right: 1px solid var(--border);
  z-index: 50;
  display: flex;
  flex-direction: column;
  box-shadow: 12px 0 35px rgba(0,0,0,.35);
}
.sidebar-logo {
  padding: 26px 22px 20px;
  border-bottom: 1px solid var(--border);
}
.sidebar-logo .logo-icon { font-size: 34px; display: block; margin-bottom: 8px; }
.sidebar-logo h2 { font-size: 17px; color: var(--accent-blue); letter-spacing: 2px; margin: 0; border: none; padding: 0; }
.sidebar-logo p { font-size: 11px; color: var(--text-dim); margin-top: 4px; }
.sidebar-nav { padding: 8px 0; flex: 1; }
.sidebar-section {
  padding: 14px 22px 6px;
  color: var(--text-dim);
  font-size: 11px;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.sidebar-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 12px 22px;
  color: #b0bec5;
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: all .18s ease;
  font-size: 14px;
}
.sidebar-link:hover {
  color: var(--text-primary);
  background: rgba(79,195,247,.07);
  border-left-color: var(--accent-blue);
}
.sidebar-link.active {
  color: var(--accent-blue);
  background: rgba(79,195,247,.12);
  border-left-color: var(--accent-blue);
  font-weight: 700;
}
.sidebar-footer {
  padding: 16px 22px 20px;
  border-top: 1px solid var(--border);
}
.sidebar-player {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.sidebar-player .avatar {
  width: 34px; height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg,#1565c0,#4fc3f7);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
}
.sidebar-player .player-name { color: var(--text-primary); font-size: 13px; font-weight: 700; }
.sidebar-player .player-level { color: var(--text-dim); font-size: 11px; }
.sidebar-logout {
  display: block;
  text-align: center;
  padding: 10px;
  border: 1px solid #b71c1c;
  border-radius: 8px;
  color: var(--accent-red);
  text-decoration: none;
  font-size: 13px;
  transition: all .18s ease;
}
.sidebar-logout:hover { background: rgba(183,28,28,.16); color: #ff8a80; }

/* ── 卡片 ── */
.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 34px 36px;
  box-shadow: 0 12px 28px rgba(0,0,0,.28);
  margin-bottom: 20px;
}
.card-sm {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 20px;
}
.card-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-header h3 { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.card-body { padding: 20px; }

/* ── 按鈕 ── */
.btn-primary {
  padding: 15px 24px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg,#c79100,#ffca28);
  color: #111827;
  font-size: 15px;
  font-weight: 900;
  cursor: pointer;
  letter-spacing: 1px;
  transition: transform .12s, filter .2s;
  display: inline-block;
}
.btn-primary:hover { filter: brightness(1.08); }
.btn-primary:active { transform: scale(.98); }
.btn-primary:disabled { background: #374151; color: #6b7280; cursor: not-allowed; filter: none; }

.btn-outline {
  color: var(--text-muted);
  font-size: 13px;
  text-decoration: none;
  padding: 8px 18px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  transition: all .2s;
  display: inline-block;
}
.btn-outline:hover { border-color: var(--accent-blue); color: var(--accent-blue); }

.btn-danger {
  background: transparent;
  color: var(--accent-red);
  border: 1px solid var(--accent-red);
  font-size: 12px;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all .2s;
}
.btn-danger:hover { background: var(--accent-red); color: #fff; }

/* ── 進度條 ── */
.bar-track {
  height: 16px;
  background: var(--bg-base);
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid var(--border);
}
.bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width .35s ease;
}
.bar-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
  font-size: 12px;
  color: var(--text-dim);
}

/* ── 資訊格子 ── */
.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 28px;
}
.info-card {
  background: var(--bg-base);
  border: 1px solid #111827;
  border-radius: 10px;
  padding: 18px;
  text-align: center;
}
.info-card .value { font-size: 25px; font-weight: 800; margin-bottom: 6px; }
.info-card .label { font-size: 12px; color: var(--text-dim); }

/* ── Toast 通知 ── */
#toast {
  position: fixed;
  right: 28px; bottom: 28px;
  max-width: 460px;
  padding: 14px 18px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.55;
  opacity: 0;
  transform: translateY(12px);
  transition: opacity .25s, transform .25s;
  z-index: 9999;
  pointer-events: none;
  box-shadow: 0 16px 35px rgba(0,0,0,.4);
}
#toast.show { opacity: 1; transform: translateY(0); }
#toast.ok   { background: #12351f; color: #a5d6a7; border: 1px solid #2e7d32; }
#toast.err  { background: #3a1414; color: #ef9a9a; border: 1px solid #b71c1c; }
#toast.info { background: #111827; color: #cbd5e1; border: 1px solid #334155; }

/* ── 訊息框 ── */
.msg-box {
  background: #1a4325;
  color: #a5d6a7;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 10px;
  border: 1px solid #2e7d32;
  line-height: 1.5;
  font-size: 14px;
  min-height: 22px;
}

/* ── 表單元素 ── */
.form-group { margin-bottom: 18px; }
.form-group label {
  display: block;
  font-size: 11px;
  color: var(--text-muted);
  margin-bottom: 7px;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}
.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="email"] {
  width: 100%;
  padding: 12px 15px;
  background: var(--bg-base);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-primary);
  font-size: 14px;
  transition: border-color .2s, box-shadow .2s;
}
.form-group input:focus {
  outline: none;
  border-color: var(--accent-blue);
  box-shadow: 0 0 0 3px rgba(79,195,247,.08);
}
.form-group input::placeholder { color: var(--text-dim); }

/* ── 錯誤/成功提示 ── */
.alert { padding: 10px 13px; border-radius: 7px; font-size: 12px; margin-bottom: 18px; text-align: center; }
.alert-err { background: rgba(239,83,80,.1); border: 1px solid rgba(239,83,80,.3); color: #ef9a9a; }
.alert-ok  { background: rgba(102,187,106,.1); border: 1px solid rgba(102,187,106,.3); color: #a5d6a7; }

/* ── RWD 斷點 ── */
@media (min-width: 761px) {
  .page-body { padding-left: 240px; }
}
@media (max-width: 760px) {
  .page-body { padding: 16px 16px 82px; }
  .sidebar {
    top: auto; right: 0;
    width: 100%; height: 64px;
    border-right: 0;
    border-top: 1px solid var(--border);
    flex-direction: row;
  }
  .sidebar-logo,
  .sidebar-section,
  .sidebar-footer { display: none; }
  .sidebar-nav {
    width: 100%;
    display: flex;
    padding: 0;
    overflow-x: auto;
  }
  .sidebar-link {
    flex: 1 0 auto;
    min-width: 86px;
    justify-content: center;
    flex-direction: column;
    gap: 3px;
    padding: 8px 10px;
    border-left: 0;
    border-top: 3px solid transparent;
    font-size: 12px;
  }
  .sidebar-link:hover,
  .sidebar-link.active {
    border-left: 0;
    border-top-color: var(--accent-blue);
  }
  .info-grid { grid-template-columns: 1fr 1fr; }
}
```

- [ ] **Step 2: 驗證 CSS 檔案存在**

```
assets/style.css 應出現在 版本1.6裝備鍛造/assets/ 目錄下
```

- [ ] **Step 3: Commit**

```bash
git add "版本1.6裝備鍛造/assets/style.css"
git commit -m "feat: add shared design system style.css"
```

---

## Task 2: 建立 assets/admin.css（後台補充樣式）

**Files:**
- Create: `版本1.6裝備鍛造/assets/admin.css`

- [ ] **Step 1: 寫入 admin.css**

```css
/* ======================================================
   塔城傳說 — 後台管理專屬樣式
   需與 style.css 一起引入
   ====================================================== */

/* ── 後台主體結構 ── */
.main {
  margin-left: 220px;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.admin-topbar {
  height: 58px;
  background: var(--bg-card);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 28px;
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 50;
}
.admin-topbar .page-title { font-size: 17px; font-weight: 700; color: var(--text-primary); flex: 1; }
.admin-topbar .breadcrumb { font-size: 12px; color: #8899b0; }
.admin-topbar .breadcrumb span { color: var(--accent-blue); }
.content { padding: 28px; flex: 1; }

/* ── 統計卡 ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 28px;
}
.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 22px 20px;
  transition: border-color .2s;
}
.stat-card:hover { border-color: var(--accent-blue); }
.stat-card .label { font-size: 12px; color: var(--text-muted); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 10px; }
.stat-card .value { font-size: 32px; font-weight: 700; color: var(--text-primary); line-height: 1; }
.stat-card .sub { font-size: 12px; color: #7d8fa3; margin-top: 6px; }
.stat-card.blue .value   { color: var(--accent-blue); }
.stat-card.green .value  { color: var(--accent-green); }
.stat-card.yellow .value { color: var(--accent); }
.stat-card.red .value    { color: var(--accent-red); }

/* ── Section / 資料表包裝 ── */
.section {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
}
.section-header {
  padding: 16px 22px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.section-header h3 { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.section-header .badge {
  font-size: 11px; padding: 3px 10px; border-radius: 20px;
  background: rgba(79,195,247,.1); color: var(--accent-blue);
  border: 1px solid rgba(79,195,247,.3);
}

/* ── 資料表格（.tbl 保留相容性） ── */
.data-table, .tbl {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .tbl th {
  padding: 12px 16px; text-align: left;
  font-size: 11px; color: #8899b0;
  letter-spacing: 1.5px; text-transform: uppercase;
  border-bottom: 1px solid #1a1a2e;
  background: #0f1829;
}
.data-table td, .tbl td {
  padding: 13px 16px; font-size: 14px; color: #ccc;
  border-bottom: 1px solid #1a1a2e;
}
.data-table tr:last-child td,
.tbl tr:last-child td { border-bottom: none; }
.data-table tr:hover td,
.tbl tr:hover td { background: rgba(79,195,247,.03); }
.tbl .rank { font-weight: 700; color: var(--accent); }
.tbl .rank.gold   { color: #ffd700; }
.tbl .rank.silver { color: #c0c0c0; }
.tbl .rank.bronze { color: #cd7f32; }

/* ── 後台小按鈕（覆蓋 style.css 的 .btn-primary 尺寸） ── */
.btn {
  padding: 7px 14px; border-radius: 6px; border: 1px solid;
  font-size: 12px; cursor: pointer; font-weight: 600; transition: all .2s;
  text-decoration: none; display: inline-block;
}
.btn.btn-primary { background: rgba(79,195,247,.1); color: var(--accent-blue); border-color: rgba(79,195,247,.4); }
.btn.btn-primary:hover { background: rgba(79,195,247,.2); }
.btn.btn-danger  { background: rgba(239,83,80,.1);  color: var(--accent-red);   border-color: rgba(239,83,80,.4); }
.btn.btn-danger:hover  { background: rgba(239,83,80,.2); }
.btn.btn-success { background: rgba(102,187,106,.1); color: var(--accent-green); border-color: rgba(102,187,106,.4); }
.btn.btn-success:hover { background: rgba(102,187,106,.2); }
.btn.btn-warning { background: rgba(255,202,40,.1);  color: var(--accent);       border-color: rgba(255,202,40,.4); }
.btn.btn-warning:hover { background: rgba(255,202,40,.2); }

/* ── 標籤 ── */
.tag { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.tag-win    { background: rgba(102,187,106,.15); color: var(--accent-green); }
.tag-lose   { background: rgba(239,83,80,.15);   color: var(--accent-red); }
.tag-escape { background: rgba(255,202,40,.15);   color: var(--accent); }
.tag-banned { background: rgba(239,83,80,.15);   color: var(--accent-red); }
.tag-active { background: rgba(102,187,106,.15); color: var(--accent-green); }

/* ── 搜尋列 ── */
.search-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 16px 22px; border-bottom: 1px solid var(--border);
}
.search-bar input,
.search-bar select {
  padding: 9px 14px;
  background: var(--bg-base);
  border: 1px solid var(--border);
  border-radius: 7px;
  color: var(--text-primary);
  font-size: 13px;
}
.search-bar input { flex: 1; }
.search-bar input:focus,
.search-bar select:focus { outline: none; border-color: var(--accent-blue); }
.search-bar select option { background: var(--bg-card); }

/* ── Tab 列（JS 用 .tab-btn / .tab-content，不改名） ── */
.tabs { display: flex; border-bottom: 1px solid var(--border); padding: 0 22px; }
.tab-btn {
  padding: 14px 22px; font-size: 13px; cursor: pointer;
  border: none; background: none; color: var(--text-muted);
  border-bottom: 2px solid transparent; transition: all .2s; font-weight: 600;
}
.tab-btn.active, .tab-btn:hover { color: var(--accent-blue); }
.tab-btn.active { border-bottom-color: var(--accent-blue); }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── Modal ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.75); z-index: 200;
  align-items: center; justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 12px; padding: 32px; width: 400px;
  box-shadow: 0 20px 60px rgba(0,0,0,.6);
}
.modal h3 { font-size: 17px; color: var(--text-primary); margin-bottom: 12px; }
.modal p { font-size: 14px; color: #888; line-height: 1.6; margin-bottom: 24px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

/* ── 警告框 ── */
.alert-success { background: rgba(102,187,106,.12); border: 1px solid rgba(102,187,106,.3); color: #a5d6a7; }
.alert-danger  { background: rgba(239,83,80,.12);   border: 1px solid rgba(239,83,80,.3);   color: #ef9a9a; }
```

- [ ] **Step 2: Commit**

```bash
git add "版本1.6裝備鍛造/assets/admin.css"
git commit -m "feat: add admin supplemental styles admin.css"
```

---

## Task 3: 建立 _sidebar.php（前台側邊欄 include）

**Files:**
- Create: `版本1.6裝備鍛造/_sidebar.php`

前提：呼叫此 include 的頁面必須先定義 `$user['username']`、`$user['level']`。

- [ ] **Step 1: 建立 _sidebar.php**

```php
<?php
// 前台側邊欄 include
// 需要：$user['username'], $user['level'] 已定義
$_sb_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">⚔️</span>
        <h2>塔城傳說</h2>
        <p>TAR GAME TOWN</p>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">城鎮設施</div>
        <a href="index.php"       class="sidebar-link <?= $_sb_page==='index.php'       ?'active':'' ?>">🏠 <span>主城鎮</span></a>
        <a href="skills_build.php" class="sidebar-link <?= $_sb_page==='skills_build.php'?'active':'' ?>">⚔️ <span>技能樹</span></a>
        <a href="forge.php"       class="sidebar-link <?= $_sb_page==='forge.php'       ?'active':'' ?>">⚒️ <span>裝備鍛造</span></a>
        <a href="arena.php"       class="sidebar-link <?= $_sb_page==='arena.php'       ?'active':'' ?>">🏟️ <span>競技場</span></a>
        <a href="skills.php"      class="sidebar-link <?= $_sb_page==='skills.php'      ?'active':'' ?>">📖 <span>被動技能</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-player">
            <div class="avatar">👤</div>
            <div>
                <div class="player-name"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="player-level">Lv.<?= (int)$user['level'] ?> 冒險者</div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout">⏻ 登出</a>
    </div>
</aside>
```

- [ ] **Step 2: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/_sidebar.php"
```
預期輸出：`No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "版本1.6裝備鍛造/_sidebar.php"
git commit -m "feat: add frontend sidebar include _sidebar.php"
```

---

## Task 4: 更新 admin/_sidebar.php（移除 CSS，改用設計 token class）

**Files:**
- Modify: `版本1.6裝備鍛造/admin/_sidebar.php`

目前 admin/_sidebar.php 把 CSS 和 HTML 混在一起，並且在 class 名稱上不一致。改為只輸出 HTML，CSS 移至 admin.css。

- [ ] **Step 1: 取代 admin/_sidebar.php 內容**

完整取代為以下內容（移除 `<style>` 區塊，class 名稱改用設計系統）：

```php
<?php
$current = basename($_SERVER['PHP_SELF']);
function nav($href, $icon, $label, $current) {
    $active = ($current === $href) ? 'active' : '';
    echo "<a href='$href' class='sidebar-link $active'>$icon <span>$label</span></a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">⚔️</span>
    <h2>後台管理</h2>
    <p>TAR GAME ADMIN</p>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">主選單</div>
    <?php nav('index.php',      '📊', '系統總覽', $current); ?>
    <?php nav('players.php',    '👥', '玩家管理', $current); ?>
    <?php nav('records.php',    '📋', '紀錄查詢', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">遊戲系統</div>
    <?php nav('arena.php',      '🏟️', '競技場管理', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">系統層</div>
    <?php nav('db_layer.php',   '🗄️', '資料庫層', $current); ?>
    <?php nav('api_module.php', '🔌', 'API 模組', $current); ?>
    <div class="sidebar-section" style="margin-top:12px;">遊戲前台</div>
    <a href="../index.php" class="sidebar-link" target="_blank">🏠 <span>主城鎮</span></a>
    <a href="../tower.php" class="sidebar-link" target="_blank">🗼 <span>塔探索</span></a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-player">
      <div class="avatar">👤</div>
      <div>
        <div class="player-name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></div>
        <div class="player-level">超級管理員</div>
      </div>
    </div>
    <a href="logout.php" class="sidebar-logout">⏻ 登出</a>
  </div>
</aside>
<div class="main">
```

- [ ] **Step 2: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/admin/_sidebar.php"
```

- [ ] **Step 3: Commit**

```bash
git add "版本1.6裝備鍛造/admin/_sidebar.php"
git commit -m "refactor: admin sidebar - extract CSS to admin.css, align class names"
```

---

## Task 5: 更新所有後台頁面的 head 結構

**Files:**
- Modify: `版本1.6裝備鍛造/admin/index.php`
- Modify: `版本1.6裝備鍛造/admin/players.php`
- Modify: `版本1.6裝備鍛造/admin/records.php`
- Modify: `版本1.6裝備鍛造/admin/users.php`
- Modify: `版本1.6裝備鍛造/admin/logs.php`
- Modify: `版本1.6裝備鍛造/admin/arena.php`
- Modify: `版本1.6裝備鍛造/admin/db_layer.php`
- Modify: `版本1.6裝備鍛造/admin/api_module.php`

目前 admin 頁面的 `<?php include '_sidebar.php'; ?>` 放在 `<head>` 裡，導致 HTML 結構不正確。改為放在 `<body>` 開頭，並在 `<head>` 加入 stylesheet 連結。

每個後台頁面的修改模式如下。以 `admin/index.php` 示範，其他頁面同理（只有 `<title>` 和頁面內容不同）。

- [ ] **Step 1: 更新 admin/index.php 的 head 結構**

將：
```html
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系統總覽 — 後台管理</title>
<?php include '_sidebar.php'; ?>
```

改為：
```html
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系統總覽 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<?php include '_sidebar.php'; ?>
```

並將 `.topbar` 改名為 `.admin-topbar`（如頁面中有用到）。

- [ ] **Step 2: 對所有其他後台頁面套用相同模式**

對 `players.php`、`records.php`、`users.php`、`logs.php`、`arena.php`、`db_layer.php`、`api_module.php` 做同樣的修改：
1. 在 `<title>` 後新增兩行 `<link>` stylesheet
2. 在 `</head>` 後加 `<body>`
3. 將 `<?php include '_sidebar.php'; ?>` 移至 `<body>` 之後
4. 在頁面最後 `</html>` 前加 `</body>`

- [ ] **Step 3: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/admin/index.php"
php -l "版本1.6裝備鍛造/admin/players.php"
php -l "版本1.6裝備鍛造/admin/records.php"
```

- [ ] **Step 4: Commit**

```bash
git add "版本1.6裝備鍛造/admin/"
git commit -m "refactor: fix admin pages HTML structure, add stylesheet links"
```

---

## Task 6: 更新 forge.php（加側邊欄，移除 style 區塊）

**Files:**
- Modify: `版本1.6裝備鍛造/forge.php`

forge.php 是設計基準頁面，改動最少：移除 `<style>` 區塊，加入 stylesheet 連結，加入側邊欄，把 topbar 改成側邊欄格式，調整 body 的 padding-left。

- [ ] **Step 1: 更新 forge.php 的 head**

將 `<head>` 內的 `<style>...</style>` 整個區塊（第 50-173 行）替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* forge 頁面專屬 */
.forge-container { max-width: 1080px; margin: 0 auto; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.topbar h1 { font-size:24px; color:var(--accent); letter-spacing:2px; }
.topbar-right { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.gold { font-size:16px; color:var(--accent); font-weight:bold; }
.tabs { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
.tab { min-width:170px; padding:14px 28px; border-radius:9px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-muted); cursor:pointer; font-size:15px; font-weight:700; transition:all .2s; text-align:left; }
.tab:hover { border-color:var(--accent-blue); color:var(--text-primary); }
.tab.active { border-color:var(--accent); color:var(--accent); background:rgba(255,202,40,.08); }
.tab .tab-level { font-size:12px; color:var(--text-dim); margin-left:6px; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.forge-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:34px 36px; margin-bottom:20px; box-shadow:0 12px 28px rgba(0,0,0,.28); }
.equip-header { display:flex; align-items:center; gap:18px; margin-bottom:28px; }
.equip-icon { font-size:54px; width:74px; text-align:center; }
.equip-title h2 { font-size:24px; font-weight:800; }
.equip-title p { font-size:14px; color:var(--text-muted); margin-top:6px; line-height:1.7; }
.level-bar { margin:28px 0 24px; }
.bar-mid { font-weight:700; }
.action-row { display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; }
.next-box { background:#101629; border:1px solid #25304f; border-radius:12px; padding:16px 18px; display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap; }
.next-box span { font-size:13px; color:var(--text-muted); }
.next-box b { color:var(--accent); }
.btn-upgrade { min-width:210px; padding:15px 24px; border:none; border-radius:10px; background:linear-gradient(135deg,#c79100,#ffca28); color:#111827; font-size:15px; font-weight:900; cursor:pointer; letter-spacing:1px; transition:transform .12s,filter .2s; }
.btn-upgrade:hover { filter:brightness(1.08); }
.btn-upgrade:active { transform:scale(.98); }
.btn-upgrade:disabled { background:#374151; color:#6b7280; cursor:not-allowed; filter:none; }
.max-note { text-align:center; color:var(--accent); font-size:16px; font-weight:800; margin-top:18px; }
.small-note { font-size:12px; color:var(--text-dim); margin-top:12px; text-align:center; line-height:1.7; }
@media(max-width:760px) {
  .tabs{gap:8px;} .tab{flex:1;min-width:100px;text-align:center;padding:12px 10px;}
  .forge-card{padding:24px 18px;} .equip-icon{font-size:42px;width:52px;}
  .equip-title h2{font-size:21px;} .action-row{grid-template-columns:1fr;}
  .btn-upgrade{width:100%;min-width:0;}
}
</style>
```

- [ ] **Step 2: 在 `<body>` 後加入側邊欄，並把 topbar 套進 page-body**

將 `<body>` 後的第一個 `<div class="topbar">` 改為：

```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="forge-container">

<div class="topbar">
    <h1>⚒️ 裝備鍛造</h1>
    <div class="topbar-right">
        <span class="gold">💰 <span id="gold-display"><?= number_format((int)$user['gold']) ?></span> 金</span>
    </div>
</div>
```

並在 `</body>` 前補：

```html
</div><!-- /forge-container -->
</div><!-- /page-body -->
```

- [ ] **Step 3: 更新 forge.php 的 DB query 加入 level 欄位**

將：
```php
$user = $conn->query("SELECT username, gold FROM users WHERE id=$user_id")->fetch_assoc();
```
改為：
```php
$user = $conn->query("SELECT username, gold, level FROM users WHERE id=$user_id")->fetch_assoc();
```

（_sidebar.php 需要 `$user['level']`）

- [ ] **Step 4: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/forge.php"
```

- [ ] **Step 5: Commit**

```bash
git add "版本1.6裝備鍛造/forge.php"
git commit -m "refactor: forge.php - add sidebar, extract styles to style.css"
```

---

## Task 7: 更新 index.php（移除 style，改用新 class）

**Files:**
- Modify: `版本1.6裝備鍛造/index.php`

index.php 改動最大：原有的 `.town-wall` 側邊欄換成新的 `_sidebar.php`，`<style>` 大區塊移除，panel 改用 `.card` class。

- [ ] **Step 1: 替換 index.php 的 head**

將 `<head>` 內的 `<style>...</style>` 整個區塊替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* index 頁面專屬 */
.index-container { display:flex; gap:15px; flex-wrap:wrap; max-width:850px; width:100%; margin:0 auto; justify-content:center; }
.panel { background:var(--bg-card); padding:15px; border-radius:10px; box-shadow:0 6px 12px rgba(0,0,0,.3); flex:1; min-width:280px; border:1px solid var(--border); display:flex; flex-direction:column; }
h2 { margin-top:0; margin-bottom:10px; font-size:20px; color:#ffffff; border-bottom:2px solid var(--accent-green); padding-bottom:8px; }
h3 { margin-top:0; margin-bottom:10px; font-size:18px; color:#ffffff; border-bottom:2px solid var(--accent-green); padding-bottom:8px; }
.progress-container { width:100%; background-color:#424242; border-radius:6px; margin:4px 0 10px 0; overflow:hidden; height:18px; position:relative; }
.progress-bar-hp { height:100%; background-color:var(--accent-green); transition:width 0.3s ease; }
.progress-text { position:absolute; width:100%; text-align:center; top:0; left:0; font-size:11px; line-height:18px; color:#fff; font-weight:bold; text-shadow:1px 1px 2px #000; }
.resource-bar { display:flex; justify-content:space-between; align-items:center; background:#22222b; padding:8px 12px; border-radius:6px; border:1px solid var(--border); margin-bottom:10px; font-size:14px; }
.stats-container { display:flex; flex-direction:column; gap:6px; margin-bottom:15px; }
.stat-row { display:flex; justify-content:space-between; align-items:center; background:#353542; padding:8px 12px; border-radius:6px; font-size:14px; }
.stat-name { font-weight:bold; color:#ccc; }
.stat-value { font-size:15px; font-weight:bold; margin-left:auto; margin-right:12px; }
.stat-raw { font-size:11px; color:var(--text-dim); margin-left:4px; font-weight:normal; }
.btn-add { background:#ff9800; color:#fff; border:none; border-radius:4px; padding:4px 10px; cursor:pointer; font-weight:bold; font-size:12px; }
.btn-add:hover { background:#fb8c00; }
.btn-train { background-color:var(--accent-green); color:white; margin-bottom:8px; border:none; padding:10px 15px; font-size:14px; border-radius:6px; width:100%; font-weight:bold; cursor:pointer; }
.btn-train:hover { opacity:.8; }
.btn-reset { background-color:transparent; color:var(--accent-red); border:1px solid var(--accent-red); font-size:12px; padding:6px; border-radius:6px; width:100%; cursor:pointer; }
.btn-reset:hover { background-color:var(--accent-red); color:white; }
@keyframes pulse { 0%{transform:scale(1);} 50%{transform:scale(1.02);box-shadow:0 0 10px rgba(255,152,0,.8);} 100%{transform:scale(1);} }
.tower-list { flex-grow:1; overflow-y:auto; max-height:380px; padding-right:8px; margin-top:5px; }
.tower-list::-webkit-scrollbar { width:6px; }
.tower-list::-webkit-scrollbar-thumb { background:#555; border-radius:3px; }
.floor-item { display:block; padding:10px; margin-bottom:8px; border-radius:6px; text-align:center; font-weight:bold; text-decoration:none; color:#fff; transition:transform 0.1s; font-size:14px; }
.floor-item:active { transform:scale(.98); }
.floor-cleared { background-color:#2e7d32; border:1px solid #1b5e20; }
.floor-current { background-color:#f57f17; border:1px solid #bc5100; color:#fff; }
.floor-locked { background-color:#424242; border:1px solid #212121; color:#757575; cursor:not-allowed; }
.opt-btn { padding:9px 6px; border-radius:7px; border:1px solid var(--border); background:var(--bg-base); color:var(--text-muted); font-size:12px; font-weight:600; text-align:center; transition:all .15s; }
.opt-btn.selected { border-color:var(--accent-blue); color:var(--accent-blue); background:rgba(79,195,247,.1); }
.opt-btn:hover { border-color:var(--accent-blue); color:var(--text-primary); }
.mode-card { background:var(--bg-base); border:2px solid var(--border); border-radius:10px; padding:18px 12px; text-align:center; cursor:pointer; transition:all .2s; }
.mode-card:hover { border-color:var(--accent-blue); }
.mode-card.selected { border-color:var(--accent-blue); background:rgba(79,195,247,.08); }
</style>
```

- [ ] **Step 2: 替換 `<body>` 開頭的 `<aside class="town-wall">` 整個區塊**

刪除原本的 `<aside class="town-wall">...</aside>`（第 238-264 行），改為：

```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="index-container">
```

- [ ] **Step 3: 在 `</body>` 前補上閉合 div**

在最後的 `</body>` 前加：

```html
</div><!-- /index-container -->
</div><!-- /page-body -->
```

- [ ] **Step 4: 移除 `@media (min-width: 1200px)` 等 layout media query**

原本 `index.php` 的 RWD body offset（`.container { transform: translateX(-90px) }`）已由 `style.css` 的 `.page-body` 統一管理，刪除這些 media query。

- [ ] **Step 5: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/index.php"
```

- [ ] **Step 6: Commit**

```bash
git add "版本1.6裝備鍛造/index.php"
git commit -m "refactor: index.php - replace town-wall sidebar with _sidebar.php, extract styles"
```

---

## Task 8: 更新 login.php + register.php（無側邊欄，提取共用部分）

**Files:**
- Modify: `版本1.6裝備鍛造/login.php`
- Modify: `版本1.6裝備鍛造/register.php`

這兩頁無側邊欄，以置中卡片佈局呈現。移除 `<style>` 中已由 `style.css` 涵蓋的部分，保留頁面專屬樣式。

- [ ] **Step 1: 更新 login.php head**

將 `<style>` 整個區塊替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* login 頁面專屬 */
body { display:flex; align-items:center; justify-content:center; overflow:hidden; }
.bg-glow { position:fixed; pointer-events:none; border-radius:50%; filter:blur(120px); opacity:.15; }
.bg-glow.g1 { width:600px; height:600px; background:#1565c0; top:-100px; left:-200px; }
.bg-glow.g2 { width:500px; height:500px; background:#4a148c; bottom:-100px; right:-100px; }
.bg-glow.g3 { width:300px; height:300px; background:#006064; top:50%; left:50%; transform:translate(-50%,-50%); }
.login-container { display:flex; width:820px; min-height:520px; background:#111827; border:1px solid #1f2937; border-radius:20px; overflow:hidden; box-shadow:0 30px 80px rgba(0,0,0,.7); position:relative; z-index:1; }
.panel { flex:1; padding:48px 44px; display:flex; flex-direction:column; transition:all .35s ease; }
.panel.player-panel { background:linear-gradient(160deg,#0d1b2a 0%,#111827 100%); border-right:1px solid #1f2937; }
.panel.admin-panel  { background:linear-gradient(160deg,#1a0d2e 0%,#111827 100%); }
.panel-header { text-align:center; margin-bottom:36px; }
.panel-header .icon { font-size:50px; display:block; margin-bottom:12px; }
.panel-header h2 { font-size:20px; font-weight:700; letter-spacing:2px; }
.panel-header p { font-size:12px; color:#4b5563; margin-top:6px; letter-spacing:1px; }
.player-panel .panel-header h2 { color:var(--accent-blue); }
.admin-panel  .panel-header h2 { color:#ce93d8; }
.divider { width:1px; background:linear-gradient(180deg,transparent,#2d3748,transparent); position:relative; flex-shrink:0; }
.divider::after { content:'OR'; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#111827; color:#374151; padding:8px 4px; font-size:11px; letter-spacing:1px; border-radius:4px; }
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-size:11px; color:#6b7280; margin-bottom:7px; letter-spacing:1.5px; text-transform:uppercase; }
.form-group input { width:100%; padding:12px 15px; background:#0d111a; border:1px solid #1f2937; border-radius:8px; color:#e5e7eb; font-size:14px; transition:border-color .2s,box-shadow .2s; }
.form-group input::placeholder { color:#374151; }
.form-group input:focus { outline:none; }
.player-panel .form-group input:focus { border-color:var(--accent-blue); box-shadow:0 0 0 3px rgba(79,195,247,.08); }
.admin-panel  .form-group input:focus { border-color:#ce93d8; box-shadow:0 0 0 3px rgba(206,147,216,.08); }
.btn-submit { width:100%; padding:13px; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; letter-spacing:2px; transition:opacity .2s,transform .1s; margin-top:4px; }
.btn-submit:hover { opacity:.9; transform:translateY(-1px); }
.btn-submit:active { transform:translateY(0); }
.player-panel .btn-submit { background:linear-gradient(135deg,#1565c0,#4fc3f7); color:#fff; }
.admin-panel  .btn-submit { background:linear-gradient(135deg,#4a148c,#ce93d8); color:#fff; }
.error-msg { padding:10px 13px; border-radius:7px; font-size:12px; margin-bottom:18px; text-align:center; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.3); color:#ef9a9a; }
.hint { margin-top:auto; padding-top:24px; text-align:center; font-size:11px; color:#374151; line-height:1.8; }
.hint b { color:#4b5563; }
@media(max-width:640px) {
  .login-container { flex-direction:column; width:95%; min-height:auto; }
  .panel.player-panel { border-right:none; border-bottom:1px solid #1f2937; }
  .divider { display:none; }
}
</style>
```

- [ ] **Step 2: 更新 register.php head**

將 `<style>` 整個區塊替換為：

```html
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
.error-msg { padding:10px 13px; border-radius:7px; font-size:12px; margin-bottom:18px; text-align:center; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.3); color:#ef9a9a; }
.rules { background:#0d111a; border:1px solid #1f2937; border-radius:8px; padding:12px 14px; margin-bottom:22px; font-size:12px; color:#6b7280; line-height:1.9; }
.rules b { color:var(--text-muted); }
.footer-link { text-align:center; margin-top:24px; font-size:12px; color:#4b5563; }
.footer-link a { color:var(--accent-blue); text-decoration:none; font-weight:600; }
</style>
```

- [ ] **Step 3: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/login.php"
php -l "版本1.6裝備鍛造/register.php"
```

- [ ] **Step 4: Commit**

```bash
git add "版本1.6裝備鍛造/login.php" "版本1.6裝備鍛造/register.php"
git commit -m "refactor: login/register - link style.css, keep page-specific styles"
```

---

## Task 9: 更新 arena.php（加側邊欄，移除 style 區塊）

**Files:**
- Modify: `版本1.6裝備鍛造/arena.php`

arena.php 目前有 topbar 但無側邊欄。改為加入 `_sidebar.php`，移除 `<style>` 區塊（保留頁面專屬樣式），DB query 加入 `level` 欄位。

- [ ] **Step 1: 更新 arena.php head**

將 `<style>...</style>` 替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* arena 頁面專屬 */
.arena-wrap { max-width:1000px; margin:0 auto; }
.page-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.page-topbar h1 { font-size:22px; color:var(--accent-red); letter-spacing:2px; }
.grid { display:grid; grid-template-columns:320px 1fr; gap:20px; }
.rating-big { font-size:52px; font-weight:700; color:var(--accent-red); line-height:1; text-align:center; margin:8px 0; }
.rating-rank { text-align:center; font-size:13px; color:var(--text-muted); margin-bottom:16px; }
.stats-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:16px; }
.stat-box { background:var(--bg-base); border:1px solid #1a1a2e; border-radius:8px; padding:10px; text-align:center; }
.stat-box .sv { font-size:20px; font-weight:700; }
.stat-box .sl { font-size:10px; color:#555; margin-top:3px; }
.cd-bar { background:var(--bg-base); border:1px solid var(--border); border-radius:7px; padding:10px 14px; text-align:center; font-size:13px; color:var(--accent); margin-bottom:12px; display:none; }
.rank-table { width:100%; border-collapse:collapse; font-size:13px; }
.rank-table th { padding:10px 14px; text-align:left; font-size:11px; color:#555; border-bottom:1px solid #1a1a2e; background:var(--bg-base); }
.rank-table td { padding:11px 14px; border-bottom:1px solid #1a1a2e; color:#ccc; }
.rank-table tr:last-child td { border-bottom:none; }
.rank-table tr:hover td { background:rgba(79,195,247,.03); }
.rank-num { font-weight:700; width:36px; }
.rank-1{color:#ffd700;} .rank-2{color:#c0c0c0;} .rank-3{color:#cd7f32;}
.opp-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #1a1a2e; }
.opp-row:last-child { border-bottom:none; }
.opp-info { flex:1; }
.opp-name { font-size:14px; font-weight:600; color:var(--text-primary); }
.opp-meta { font-size:11px; color:#555; margin-top:3px; }
.opp-rating { font-size:16px; font-weight:700; color:var(--accent-red); margin:0 16px; }
.btn-challenge { padding:7px 16px; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.4); color:var(--accent-red); border-radius:6px; cursor:pointer; font-size:12px; font-weight:700; transition:all .2s; }
.btn-challenge:hover { background:rgba(239,83,80,.2); }
.btn-challenge:disabled { opacity:.4; cursor:not-allowed; }
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:999; align-items:center; justify-content:center; }
.modal-bg.show { display:flex; }
.modal { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; width:520px; max-width:95vw; max-height:85vh; overflow-y:auto; }
.modal-head { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:var(--bg-card); z-index:1; }
.modal-head h3 { font-size:16px; color:var(--text-primary); }
.modal-close { background:none; border:none; color:#555; font-size:20px; cursor:pointer; padding:4px 8px; }
.modal-close:hover { color:var(--text-primary); }
.modal-body { padding:20px 24px; }
.log-line { padding:7px 12px; border-radius:6px; font-size:13px; margin-bottom:6px; line-height:1.5; }
.log-system { background:#1a1a2e; color:var(--text-muted); }
.log-attack { background:#1a0d0d; color:#ef9a9a; }
.log-crit   { background:#2a1000; color:var(--accent); font-weight:700; }
.log-dodge  { background:#0d1a0d; color:var(--accent-green); }
.log-result { background:rgba(239,83,80,.12); border:1px solid rgba(239,83,80,.3); color:var(--accent-red); font-weight:700; font-size:14px; text-align:center; padding:14px; }
.result-banner { text-align:center; padding:20px 0; margin-bottom:16px; }
.result-banner .big { font-size:28px; font-weight:700; }
.result-banner .sub { font-size:13px; color:var(--text-muted); margin-top:6px; }
.weekly-hint { background:#1a1000; border:1px solid #3a2800; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:12px; color:var(--accent); }
</style>
```

- [ ] **Step 2: 替換 `<body>` 開頭**

將：
```html
<body>

<div class="topbar">
  <h1>🏟️ 競技場</h1>
```

改為：
```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="arena-wrap">

<div class="page-topbar">
  <h1>🏟️ 競技場</h1>
```

並在 `</body>` 前補：
```html
</div><!-- /arena-wrap -->
</div><!-- /page-body -->
```

- [ ] **Step 3: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/arena.php"
```

- [ ] **Step 4: Commit**

```bash
git add "版本1.6裝備鍛造/arena.php"
git commit -m "refactor: arena.php - add sidebar, extract styles to style.css"
```

---

## Task 10: 更新 skills_build.php + skills.php（加側邊欄）

**Files:**
- Modify: `版本1.6裝備鍛造/skills_build.php`
- Modify: `版本1.6裝備鍛造/skills.php`

- [ ] **Step 1: 更新 skills_build.php head**

將 `<style>...</style>` 替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* skills_build 頁面專屬 */
.sb-wrap { max-width:920px; margin:0 auto; }
.page-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.page-topbar h1 { font-size:22px; color:var(--accent); letter-spacing:2px; }
.topbar-right { display:flex; gap:10px; align-items:center; }
.gold { font-size:15px; color:var(--accent); font-weight:bold; }
.triangle-hint { margin-bottom:20px; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:12px 20px; font-size:12px; color:var(--text-muted); text-align:center; }
.triangle-hint b { color:var(--accent); }
.arch-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.arch-card { background:var(--bg-card); border:2px solid var(--border); border-radius:14px; padding:22px 18px; text-align:center; cursor:pointer; transition:all .2s; }
.arch-card:hover { transform:translateY(-2px); }
.arch-card.selected { box-shadow:0 0 0 2px var(--c); border-color:var(--c); }
.arch-icon { font-size:40px; margin-bottom:10px; }
.arch-name { font-size:17px; font-weight:700; margin-bottom:6px; }
.arch-desc { font-size:11px; color:var(--text-muted); line-height:1.6; margin-bottom:10px; }
.arch-beats { font-size:11px; padding:3px 10px; border-radius:10px; display:inline-block; }
.arch-btn { margin-top:14px; width:100%; padding:9px; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:opacity .2s; }
.arch-btn:hover { opacity:.85; }
.arch-btn:disabled { opacity:.4; cursor:not-allowed; }
.build-section { }
.build-header { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.build-header h2 { font-size:18px; font-weight:700; }
.bonus-tags { display:flex; gap:8px; flex-wrap:wrap; }
.bonus-tag { padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700; }
.node-tree { display:flex; flex-direction:column; gap:10px; }
.node-row { display:flex; align-items:center; gap:12px; }
.node-connector { width:3px; height:30px; background:var(--border); margin-left:28px; }
.node-card { flex:1; border:2px solid; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:14px; transition:all .2s; }
.node-card.unlocked { background:rgba(255,255,255,.04); }
.node-card.available { cursor:pointer; }
.node-card.available:hover { transform:translateX(3px); }
.node-card.locked { opacity:.45; filter:grayscale(.6); }
.node-num { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0; }
.node-info { flex:1; }
.node-label { font-size:14px; font-weight:700; margin-bottom:3px; }
.node-desc { font-size:11px; color:var(--text-muted); }
.node-type-badge { font-size:10px; padding:2px 8px; border-radius:6px; font-weight:700; }
.node-action { flex-shrink:0; text-align:right; }
.check-icon { font-size:22px; }
.cost-badge { font-size:12px; color:var(--accent); font-weight:700; white-space:nowrap; }
.btn-unlock { padding:7px 16px; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:all .2s; white-space:nowrap; }
.btn-unlock:hover { opacity:.85; transform:translateY(-1px); }
.btn-unlock:disabled { opacity:.4; cursor:not-allowed; transform:none; }
.change-arch-area { margin:24px auto 0; text-align:center; }
.btn-change { padding:10px 24px; background:transparent; border:1px solid #4b5563; border-radius:8px; color:var(--text-muted); cursor:pointer; font-size:13px; transition:all .2s; }
.btn-change:hover { border-color:var(--accent-red); color:var(--accent-red); }
@media(max-width:640px) { .arch-grid{grid-template-columns:1fr;} }
</style>
```

- [ ] **Step 2: 替換 skills_build.php 的 `<body>` 開頭**

將：
```html
<body>

<div class="topbar">
  <h1>⚔️ 技能樹</h1>
```
改為：
```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="sb-wrap">

<div class="page-topbar">
  <h1>⚔️ 技能樹</h1>
```

並在 `</body>` 前補：
```html
</div><!-- /sb-wrap -->
</div><!-- /page-body -->
```

- [ ] **Step 3: 更新 skills.php（完整重寫 style 區塊）**

skills.php 使用完全不同的舊色調（`#1e1e24`, `#2b2b36`）。將 `<style>` 替換為：

```html
<link rel="stylesheet" href="assets/style.css">
<style>
/* skills 頁面專屬 */
.skills-wrap { max-width:640px; margin:0 auto; }
.skills-wrap h2 { margin-top:0; color:#9c27b0; border-bottom:2px solid #9c27b0; padding-bottom:10px; margin-bottom:20px; }
.skill-card { display:flex; align-items:center; background:#353542; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid var(--accent-green); transition:all 0.3s ease; }
.skill-locked { filter:grayscale(100%); opacity:0.6; border:1px solid #555; }
.skill-icon { font-size:40px; margin-right:20px; background:#22222b; width:60px; height:60px; display:flex; justify-content:center; align-items:center; border-radius:10px; flex-shrink:0; }
.skill-info { flex-grow:1; }
.skill-info h4 { margin:0 0 5px 0; color:#fff; font-size:18px; }
.skill-info p { margin:0 0 10px 0; color:#bbb; font-size:14px; line-height:1.4; }
.progress-wrapper { margin-top:10px; }
.progress-label { font-size:12px; color:#ffeb3b; font-weight:bold; margin-bottom:4px; display:block; }
.progress-container { width:100%; background-color:#22222b; border-radius:4px; overflow:hidden; height:16px; position:relative; border:1px solid #444; }
.progress-bar-crit  { height:100%; background-color:#ff9800; transition:width 0.3s ease; }
.progress-bar-dodge { height:100%; background-color:var(--accent-green); transition:width 0.3s ease; }
.progress-text { position:absolute; width:100%; text-align:center; top:0; left:0; font-size:11px; line-height:16px; color:#fff; font-weight:bold; text-shadow:1px 1px 2px #000; }
.back-btn { display:block; text-align:center; background-color:#555; color:white; text-decoration:none; padding:12px; border-radius:6px; font-weight:bold; margin-top:20px; }
.back-btn:hover { background-color:#666; }
</style>
```

將 `<body>` 後改為：

```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
<div class="skills-wrap">
```

並在 `</body>` 前補：
```html
</div><!-- /skills-wrap -->
</div><!-- /page-body -->
```

skills.php 中的 `$user` 需要 `username` 和 `level`，更新 query：
```php
$user = $conn->query("SELECT username, level FROM users WHERE id=$user_id")->fetch_assoc();
```
（此查詢放在 `if (!isset($_SESSION['player_id']))` 之後）

- [ ] **Step 4: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/skills_build.php"
php -l "版本1.6裝備鍛造/skills.php"
```

- [ ] **Step 5: Commit**

```bash
git add "版本1.6裝備鍛造/skills_build.php" "版本1.6裝備鍛造/skills.php"
git commit -m "refactor: skills pages - add sidebar, align to design system"
```

---

## Task 11: 更新 tower 系列頁面

**Files:**
- Modify: `版本1.6裝備鍛造/tower.php`
- Modify: `版本1.6裝備鍛造/tower_combat.php`
- Modify: `版本1.6裝備鍛造/tower_events.php`
- Modify: `版本1.6裝備鍛造/tower_monsters.php`
- Modify: `版本1.6裝備鍛造/tower_story.php`

tower.php 含側邊欄；combat/events/monsters/story 為沉浸式子頁面，無側邊欄，但需引入 style.css 以統一背景與基礎元素。

- [ ] **Step 1: 找出 tower.php 的 `<style>` 區塊並替換**

在 tower.php 的 `<head>` 裡，移除 `<style>...</style>`，改為：

```html
<link rel="stylesheet" href="assets/style.css">
```

原有 tower.php 的 `<style>` 區塊保留（內含塔探索的節點動畫、戰鬥 log、故事盒等頁面專屬樣式），只需刪除其中與 style.css 重複的部分（`body` background/font、`box-sizing: border-box` reset）。

加入側邊欄（`$user` 已定義，含 `username`, `level`）：
```html
<body>
<?php require '_sidebar.php'; ?>
<div class="page-body">
```

在 `</body>` 前補：
```html
</div><!-- /page-body -->
```

- [ ] **Step 2: 更新 tower_combat.php、tower_events.php、tower_monsters.php、tower_story.php**

這四個檔案為沉浸式頁面（無側邊欄）。在各檔案的 `<head>` 內加入：

```html
<link rel="stylesheet" href="assets/style.css">
```

並移除與 style.css 重複的基礎 CSS（background, font-family, color, box-sizing reset）。頁面專屬樣式保留。

- [ ] **Step 3: PHP 語法檢查**

```bash
php -l "版本1.6裝備鍛造/tower.php"
php -l "版本1.6裝備鍛造/tower_combat.php"
php -l "版本1.6裝備鍛造/tower_events.php"
php -l "版本1.6裝備鍛造/tower_monsters.php"
php -l "版本1.6裝備鍛造/tower_story.php"
```

- [ ] **Step 4: Commit**

```bash
git add "版本1.6裝備鍛造/tower.php" "版本1.6裝備鍛造/tower_combat.php" "版本1.6裝備鍛造/tower_events.php" "版本1.6裝備鍛造/tower_monsters.php" "版本1.6裝備鍛造/tower_story.php"
git commit -m "refactor: tower pages - link style.css, add sidebar to tower.php"
```
