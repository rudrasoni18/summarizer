<?php
/**
 * DocuMind — dashboard.php
 * ChatGPT-Style Dual-Mode PDF & Text Workspace
 */
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Workspace';

// Pre-load history list
$histStmt = $pdo->prepare(
    'SELECT id, title, pdf_name, category,
            (imp_questions IS NOT NULL AND imp_questions != "") AS has_questions,
            created_at
     FROM pdf_history
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$histStmt->execute([$_SESSION['user_id']]);
$historyItems = $histStmt->fetchAll();

// Pre-load a specific item if ?id= is set
$preloadItem = null;
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $plStmt = $pdo->prepare(
        'SELECT id, title, pdf_name, category, summary, imp_questions, created_at
         FROM pdf_history WHERE id = ? AND user_id = ?'
    );
    $plStmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $preloadItem = $plStmt->fetch() ?: null;
}

require_once __DIR__ . '/navbar.php';
?>

<!-- ── CDN Scripts ── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
/* ════════════════════════════════════════════════════════════
   Dashboard Layout
   ════════════════════════════════════════════════════════════ */
.dash-root {
  display: grid;
  grid-template-columns: 260px 1fr 1fr;
  gap: 0;
  height: calc(100vh - 60px);
  overflow: hidden;
  background: var(--bg-canvas);
}

/* ── Sidebar ── */
.dash-sidebar {
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border-base);
  background: var(--bg-surface);
  overflow: hidden;
}
.sidebar-header {
  padding: 1rem;
  border-bottom: 1px solid var(--border-base);
  flex-shrink: 0;
}
.sidebar-search {
  margin-top: .65rem;
  position: relative;
}
.sidebar-search input {
  width: 100%;
  padding: .45rem .75rem .45rem 2rem;
  border-radius: 7px;
  border: 1px solid var(--border-base);
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-size: .8rem;
  font-family: 'Inter', sans-serif;
  outline: none;
  transition: border-color .15s;
}
.sidebar-search input:focus { border-color: var(--brand); }
.sidebar-search svg {
  position: absolute;
  left: .6rem;
  top: 50%;
  transform: translateY(-50%);
  width: 12px;
  height: 12px;
  stroke: var(--text-tertiary);
  pointer-events: none;
}
.sidebar-list {
  flex: 1;
  overflow-y: auto;
  padding: .5rem .5rem;
}
.sidebar-empty {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--text-tertiary);
  font-size: .8rem;
}
.history-item {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  padding: .65rem .75rem;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background .15s, border-color .15s;
  margin-bottom: .25rem;
  position: relative;
}
.history-item:hover {
  background: var(--bg-elevated);
  border-color: var(--border-base);
}
.history-item.active {
  background: var(--accent-subtle);
  border-color: var(--border-strong);
}
.history-item-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
  background: var(--bg-sunken);
  border: 1px solid var(--border-base);
}
.history-item-body { flex: 1; min-width: 0; }
.history-item-title {
  font-size: .78rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}
.history-item-meta {
  display: flex;
  align-items: center;
  gap: .3rem;
  margin-top: .2rem;
  flex-wrap: wrap;
}
.history-item-cat {
  font-size: .65rem;
  font-weight: 600;
  padding: .1rem .4rem;
  border-radius: 4px;
  background: var(--bg-sunken);
  color: var(--text-tertiary);
  border: 1px solid var(--border-subtle);
  text-transform: uppercase;
  letter-spacing: .03em;
}
.history-item-date {
  font-size: .65rem;
  color: var(--text-tertiary);
}
.history-item-qs {
  font-size: .6rem;
  padding: .1rem .35rem;
  border-radius: 4px;
  background: var(--brand-subtle);
  color: var(--brand);
  font-weight: 600;
}

/* ── Center Input Panel ── */
.dash-input {
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border-base);
  background: var(--bg-canvas);
  overflow: hidden;
}
.panel-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-base);
  display: flex;
  align-items: center;
  gap: .6rem;
  flex-shrink: 0;
}
.panel-header h2 {
  font-size: .9rem;
  font-weight: 700;
  letter-spacing: -.02em;
  color: var(--text-primary);
}
.panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.25rem;
}

/* Fields */
.field-group {
  margin-bottom: 1rem;
}
.field-label {
  display: block;
  font-size: .75rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: .4rem;
  letter-spacing: .01em;
  text-transform: uppercase;
}
.field-input {
  width: 100%;
  padding: .55rem .75rem;
  border: 1px solid var(--border-base);
  border-radius: 8px;
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-family: 'Inter', sans-serif;
  font-size: .875rem;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.field-input:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(14,165,233,.1);
}
.field-input::placeholder { color: var(--text-tertiary); }
select.field-input { cursor: pointer; }

/* Mode Toggle */
.mode-toggle-wrap {
  margin-bottom: 1rem;
}
.mode-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--bg-sunken);
  border: 1px solid var(--border-base);
  border-radius: 10px;
  padding: 3px;
  gap: 2px;
  position: relative;
}
.mode-btn {
  position: relative;
  z-index: 1;
  padding: .5rem 1rem;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-family: 'Inter', sans-serif;
  font-size: .82rem;
  font-weight: 500;
  color: var(--text-secondary);
  cursor: pointer;
  transition: color .2s, background .2s, box-shadow .2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  outline: none;
  -webkit-user-select: none;
  user-select: none;
}
.mode-btn.active {
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-weight: 600;
  box-shadow: var(--shadow-sm), 0 0 0 1px var(--border-base);
}
.mode-btn.active[data-mode="pdf"]  { color: var(--brand); }
.mode-btn.active[data-mode="text"] { color: var(--success); }
.mode-btn:hover:not(.active) { color: var(--text-primary); background: var(--bg-elevated); }

/* Drop Zone */
.drop-zone {
  border: 2px dashed var(--border-base);
  border-radius: 10px;
  padding: 2rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: var(--bg-surface);
  position: relative;
}
.drop-zone:hover,
.drop-zone.drag-over {
  border-color: var(--brand);
  background: var(--brand-subtle);
}
.drop-zone input[type="file"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.drop-zone-icon { font-size: 1.8rem; margin-bottom: .5rem; display: block; }
.drop-zone-label {
  font-size: .82rem;
  color: var(--text-secondary);
  font-weight: 500;
}
.drop-zone-sub {
  font-size: .72rem;
  color: var(--text-tertiary);
  margin-top: .25rem;
}

/* PDF Preview */
.pdf-info-bar {
  display: none;
  align-items: center;
  gap: .6rem;
  margin-top: .75rem;
  padding: .6rem .85rem;
  border-radius: 8px;
  background: var(--bg-sunken);
  border: 1px solid var(--border-base);
}
.pdf-info-bar.visible { display: flex; }
.pdf-info-name {
  flex: 1;
  font-size: .8rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pdf-info-pages {
  font-size: .72rem;
  color: var(--text-tertiary);
  white-space: nowrap;
}

/* Progress Bar */
.progress-wrap {
  display: none;
  margin-top: .75rem;
}
.progress-wrap.visible { display: block; }
.progress-label {
  font-size: .72rem;
  color: var(--text-secondary);
  margin-bottom: .35rem;
  font-weight: 500;
}
.progress-bar-track {
  height: 4px;
  background: var(--bg-sunken);
  border-radius: 99px;
  overflow: hidden;
  border: 1px solid var(--border-base);
}
.progress-bar-fill {
  height: 100%;
  background: var(--brand);
  border-radius: 99px;
  width: 0%;
  transition: width .2s ease;
}

/* Textarea */
.text-area-wrap { position: relative; }
.paste-area {
  width: 100%;
  min-height: 200px;
  padding: .75rem;
  border: 1px solid var(--border-base);
  border-radius: 8px;
  background: var(--bg-elevated);
  color: var(--text-primary);
  font-family: 'Inter', monospace;
  font-size: .85rem;
  line-height: 1.6;
  resize: vertical;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.paste-area:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(14,165,233,.1);
}
.paste-area::placeholder { color: var(--text-tertiary); }
.word-counter {
  text-align: right;
  font-size: .68rem;
  color: var(--text-tertiary);
  margin-top: .3rem;
  font-family: 'Inter', sans-serif;
}

/* Submit area */
.submit-area {
  flex-shrink: 0;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-base);
  background: var(--bg-surface);
}
.btn-submit {
  width: 100%;
  padding: .7rem;
  font-size: .875rem;
  font-weight: 700;
  letter-spacing: -.01em;
}

/* Spinner inline */
.btn-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Right Output Panel ── */
.dash-output {
  display: flex;
  flex-direction: column;
  background: var(--bg-canvas);
  overflow: hidden;
}
.output-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-base);
  flex-shrink: 0;
}
.output-doc-title {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: -.025em;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.output-doc-meta {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-top: .3rem;
  flex-wrap: wrap;
}
.output-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem 1.25rem;
}

/* Empty state */
.output-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  padding: 2rem;
}
.output-empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: .5; }
.output-empty h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: .5rem;
}
.output-empty p {
  font-size: .85rem;
  color: var(--text-tertiary);
  max-width: 280px;
  line-height: 1.5;
}

/* Printable area */
#printable-area {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
}
#printable-area h1,
#printable-area h2,
#printable-area h3,
#printable-area h4 {
  color: var(--text-primary);
  line-height: 1.3;
  margin-top: 1.25rem;
  margin-bottom: .5rem;
}
#printable-area h1 { font-size: 1.35rem; }
#printable-area h2 { font-size: 1.1rem; border-bottom: 1px solid var(--border-base); padding-bottom: .4rem; }
#printable-area h3 { font-size: .95rem; }
#printable-area p  { color: var(--text-secondary); font-size: .875rem; margin-bottom: .75rem; line-height: 1.7; }
#printable-area ul,
#printable-area ol { padding-left: 1.4rem; margin-bottom: .75rem; }
#printable-area li { color: var(--text-secondary); font-size: .875rem; margin-bottom: .3rem; line-height: 1.6; }
#printable-area strong { color: var(--text-primary); font-weight: 600; }
#printable-area em { font-style: italic; }
#printable-area code {
  font-family: monospace;
  font-size: .82rem;
  background: var(--bg-sunken);
  border: 1px solid var(--border-base);
  border-radius: 4px;
  padding: .1rem .35rem;
}
#printable-area blockquote {
  border-left: 3px solid var(--brand);
  padding-left: .85rem;
  margin: .75rem 0;
  color: var(--text-secondary);
  font-style: italic;
}
#printable-area hr {
  border: none;
  border-top: 1px solid var(--border-base);
  margin: 1.25rem 0;
}

/* IMP Questions section */
.qs-section {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 2px dashed var(--border-base);
}
.qs-section-head {
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--brand);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: .4rem;
}

/* Action bar */
.action-bar {
  flex-shrink: 0;
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-base);
  background: var(--bg-surface);
  display: flex;
  gap: .65rem;
  align-items: center;
  flex-wrap: wrap;
}
.action-bar.hidden { display: none; }

/* Skeleton loading */
.output-skeleton { padding: 0; }
.skel-line {
  height: 14px;
  border-radius: 4px;
  margin-bottom: .6rem;
}
.skel-h2 { height: 20px; width: 55%; margin-bottom: .8rem; }

/* Responsive */
@media (max-width: 1100px) {
  .dash-root { grid-template-columns: 220px 1fr 1fr; }
}
@media (max-width: 820px) {
  .dash-root {
    grid-template-columns: 1fr;
    grid-template-rows: auto;
    height: auto;
    overflow: visible;
  }
  .dash-sidebar { height: auto; max-height: 300px; border-right: none; border-bottom: 1px solid var(--border-base); }
  .dash-input   { border-right: none; border-bottom: 1px solid var(--border-base); }
  .dash-output  { min-height: 500px; }
}

/* Error toast */
.toast-error {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 9999;
  background: var(--danger);
  color: #fff;
  padding: .75rem 1.1rem;
  border-radius: 8px;
  font-size: .83rem;
  font-weight: 600;
  box-shadow: var(--shadow-lg);
  display: flex;
  align-items: center;
  gap: .5rem;
  transform: translateY(120%);
  transition: transform .3s cubic-bezier(.4,0,.2,1);
  max-width: 340px;
}
.toast-error.show { transform: translateY(0); }
</style>

<!-- ════ MAIN LAYOUT ════ -->
<div class="dash-root">

  <!-- ── COL 1: Sidebar ── -->
  <aside class="dash-sidebar" aria-label="History sidebar">
    <div class="sidebar-header">
      <button class="btn-animated btn-primary-anim" id="btnNewSession" style="width:100%;justify-content:center;">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Summary Session
      </button>
      <div class="sidebar-search" aria-label="Filter history">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="search" id="historySearch" placeholder="Filter history…" autocomplete="off" aria-label="Filter history">
      </div>
    </div>

    <div class="sidebar-list" role="list" id="historyList">
      <?php if (empty($historyItems)): ?>
        <div class="sidebar-empty" id="sidebarEmpty">
          <div style="font-size:1.6rem;margin-bottom:.4rem;">📂</div>
          <div>No summaries yet.</div>
          <div style="margin-top:.2rem;color:var(--text-tertiary);font-size:.7rem;">Generate one to see it here.</div>
        </div>
      <?php else: ?>
        <?php foreach ($historyItems as $item):
          $isPdf     = ($item['pdf_name'] !== 'Direct Text Input');
          $icon      = $isPdf ? '📄' : '📝';
          $dateStr   = date('d M', strtotime($item['created_at']));
          $catShort  = mb_strtoupper(mb_substr($item['category'], 0, 3));
          $titleEsc  = htmlspecialchars($item['title'], ENT_QUOTES);
          $catEsc    = htmlspecialchars($item['category'], ENT_QUOTES);
        ?>
        <div class="history-item<?= $preloadItem && $preloadItem['id'] == $item['id'] ? ' active' : '' ?>"
             role="listitem"
             data-id="<?= (int)$item['id'] ?>"
             data-title="<?= $titleEsc ?>"
             onclick="loadHistoryItem(<?= (int)$item['id'] ?>, this)"
             title="<?= $titleEsc ?> — <?= $catEsc ?>">
          <div class="history-item-icon" aria-hidden="true"><?= $icon ?></div>
          <div class="history-item-body">
            <div class="history-item-title"><?= $titleEsc ?></div>
            <div class="history-item-meta">
              <span class="history-item-cat"><?= $catShort ?></span>
              <span class="history-item-date"><?= $dateStr ?></span>
              <?php if ($item['has_questions']): ?>
                <span class="history-item-qs">✓ IMP Qs</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- ── COL 2: Input Panel ── -->
  <section class="dash-input" aria-label="Input panel">
    <div class="panel-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
           stroke="var(--brand)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
      <h2>Input</h2>
    </div>

    <div class="panel-body">

      <!-- Title -->
      <div class="field-group">
        <label class="field-label" for="inputTitle">Title <span style="color:var(--text-tertiary);font-weight:400;text-transform:none;">(auto-filled if blank)</span></label>
        <input type="text" class="field-input" id="inputTitle" placeholder="My Document Title…" maxlength="255" autocomplete="off">
      </div>

      <!-- Category -->
      <div class="field-group">
        <label class="field-label" for="inputCategory">Category</label>
        <select class="field-input" id="inputCategory">
          <option value="Study">📚 Study</option>
          <option value="Non-Study / General">🌐 Non-Study / General</option>
          <option value="Business">💼 Business</option>
          <option value="Research">🔬 Research</option>
        </select>
      </div>

      <!-- Mode Toggle -->
      <div class="mode-toggle-wrap">
        <div class="mode-toggle" role="group" aria-label="Input mode">
          <button class="mode-btn active" data-mode="pdf" id="modePdf" onclick="switchMode('pdf')" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            Upload PDF
          </button>
          <button class="mode-btn" data-mode="text" id="modeText" onclick="switchMode('text')" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="17" y1="10" x2="3" y2="10"/>
              <line x1="21" y1="6" x2="3" y2="6"/>
              <line x1="21" y1="14" x2="3" y2="14"/>
              <line x1="17" y1="18" x2="3" y2="18"/>
            </svg>
            Paste Text
          </button>
        </div>
      </div>

      <!-- Mode A: PDF Upload -->
      <div id="panePdf">
        <div class="drop-zone" id="dropZone"
             role="button" aria-label="Click or drag a PDF here"
             ondragover="onDragOver(event)" ondragleave="onDragLeave(event)" ondrop="onDrop(event)">
          <input type="file" id="pdfFileInput" accept="application/pdf" aria-label="Select PDF file"
                 onchange="handleFileSelect(this.files[0])">
          <span class="drop-zone-icon" aria-hidden="true">📎</span>
          <div class="drop-zone-label">Drag & drop PDF here or <strong>click to browse</strong></div>
          <div class="drop-zone-sub">Any size — text is extracted locally in the browser</div>
        </div>

        <div class="pdf-info-bar" id="pdfInfoBar" aria-live="polite">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
               stroke="var(--brand)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <span class="pdf-info-name" id="pdfInfoName">—</span>
          <span class="pdf-info-pages" id="pdfInfoPages"></span>
        </div>

        <div class="progress-wrap" id="progressWrap" aria-live="polite">
          <div class="progress-label" id="progressLabel">Extracting page 0 / 0…</div>
          <div class="progress-bar-track">
            <div class="progress-bar-fill" id="progressFill"></div>
          </div>
        </div>
      </div>

      <!-- Mode B: Paste Text -->
      <div id="paneText" style="display:none;">
        <div class="text-area-wrap">
          <textarea class="paste-area" id="pasteTextarea"
                    placeholder="Paste your notes, article, study material, or any text here…"
                    rows="10" oninput="updateWordCounter()"
                    aria-label="Text input area"></textarea>
        </div>
        <div class="word-counter" id="wordCounter" aria-live="polite">0 words · 0 chars</div>
      </div>

    </div><!-- /.panel-body -->

    <div class="submit-area">
      <button class="btn-animated btn-primary-anim btn-submit" id="btnGenerate" onclick="handleGenerate()" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
        Generate Summary
      </button>
    </div>
  </section>

  <!-- ── COL 3: Output Panel ── -->
  <section class="dash-output" aria-label="Output panel">
    <div class="output-header">
      <div class="output-doc-title" id="outputTitle">Ready</div>
      <div class="output-doc-meta" id="outputMeta">
        <span class="badge-dm" style="color:var(--text-tertiary);">No document loaded</span>
      </div>
    </div>

    <div class="output-body" id="outputBody">
      <div class="output-empty" id="outputEmpty">
        <div class="output-empty-icon" aria-hidden="true">✦</div>
        <h3>Your summary will appear here</h3>
        <p>Upload a PDF or paste text in the Input panel, then click <strong>Generate Summary</strong>.</p>
      </div>
      <div id="printable-area" style="display:none;"></div>
    </div>

    <div class="action-bar hidden" id="actionBar">
      <button class="btn-animated btn-outline-anim" id="btnDownloadPdf" onclick="downloadSummaryPdf()" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Print / Download PDF
      </button>
      <button class="btn-animated btn-brand-anim" id="btnGenQuestions" onclick="handleGenerateQuestions()" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Generate IMP Questions
      </button>
    </div>
  </section>

</div><!-- /.dash-root -->

<!-- Error toast -->
<div class="toast-error" id="toastError" role="alert" aria-live="assertive">
  <span>⚠</span>
  <span id="toastMsg"></span>
</div>

<script>
/* ════════════════════════════════════════════════════════════
   Dashboard JS
   ════════════════════════════════════════════════════════════ */
'use strict';

// ── pdf.js worker ──────────────────────────────────────────
if (typeof pdfjsLib !== 'undefined') {
  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

// ── State ──────────────────────────────────────────────────
let currentMode       = 'pdf';   // 'pdf' | 'text'
let extractedText     = '';
let currentPdfName    = '';
let currentHistoryId  = null;
let currentTitle      = '';
let isExtracting      = false;

// ── Preloaded item (server-side) ───────────────────────────
<?php if ($preloadItem): ?>
(function () {
  const item = <?= json_encode([
    'id'           => $preloadItem['id'],
    'title'        => $preloadItem['title'],
    'pdf_name'     => $preloadItem['pdf_name'],
    'category'     => $preloadItem['category'],
    'summary'      => $preloadItem['summary'],
    'imp_questions'=> $preloadItem['imp_questions'],
  ]) ?>;
  document.addEventListener('DOMContentLoaded', function () {
    renderOutput(item.title, item.category, item.pdf_name, item.summary, item.imp_questions, item.id);
  });
})();
<?php endif; ?>

// ── Safe JSON parse (handles HTML/timeout responses from server) ──
async function safeJson(res) {
  const raw = await res.text();
  try {
    return JSON.parse(raw);
  } catch (e) {
    if (res.status === 401) throw new Error('Session expired. Please log in again.');
    throw new Error('Server returned an unexpected response (HTTP ' + res.status + '). It may have timed out — please try again.');
  }
}

// ── Mode Toggle ────────────────────────────────────────────
function switchMode(mode) {
  currentMode = mode;
  document.getElementById('panePdf').style.display   = mode === 'pdf'  ? 'block' : 'none';
  document.getElementById('paneText').style.display  = mode === 'text' ? 'block' : 'none';
  document.getElementById('modePdf').classList.toggle('active', mode === 'pdf');
  document.getElementById('modeText').classList.toggle('active', mode === 'text');
  if (mode === 'text') extractedText = '';
}

// ── Word Counter ───────────────────────────────────────────
function updateWordCounter() {
  const txt = document.getElementById('pasteTextarea').value;
  const words = txt.trim() === '' ? 0 : txt.trim().split(/\s+/).length;
  document.getElementById('wordCounter').textContent =
    words.toLocaleString() + ' words · ' + txt.length.toLocaleString() + ' chars';
}

// ── Drag & Drop ────────────────────────────────────────────
function onDragOver(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.add('drag-over');
}
function onDragLeave(e) {
  document.getElementById('dropZone').classList.remove('drag-over');
}
function onDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file && file.type === 'application/pdf') handleFileSelect(file);
  else showToast('Please drop a valid PDF file.');
}

// ── PDF Extraction via pdf.js ──────────────────────────────
function handleFileSelect(file) {
  if (!file) return;
  if (file.type !== 'application/pdf') { showToast('Only PDF files are accepted.'); return; }
  if (isExtracting) return;
  isExtracting   = true;
  currentPdfName = file.name;
  extractedText  = '';

  // Auto-fill title from filename
  if (!document.getElementById('inputTitle').value.trim()) {
    document.getElementById('inputTitle').value =
      file.name.replace(/\.pdf$/i, '').replace(/[-_]/g, ' ').trim();
  }

  showPdfInfo(file.name, '…');
  showProgress(true, 'Reading file…', 0);

  const reader = new FileReader();
  reader.onload = function (e) {
    const typedArr = new Uint8Array(e.target.result);
    pdfjsLib.getDocument({ data: typedArr }).promise.then(function (pdf) {
      const totalPages = pdf.numPages;
      showPdfInfo(file.name, totalPages + ' page' + (totalPages > 1 ? 's' : ''));

      let pagesProcessed = 0;
      const pagePromises = [];

      for (let i = 1; i <= totalPages; i++) {
        pagePromises.push(
          pdf.getPage(i).then(function (page) {
            return page.getTextContent().then(function (tc) {
              const txt = tc.items.map(function (s) { return s.str; }).join(' ');
              pagesProcessed++;
              const pct = Math.round((pagesProcessed / totalPages) * 100);
              showProgress(true, 'Extracting page ' + pagesProcessed + ' / ' + totalPages + '…', pct);
              return { page: page.pageNumber, text: txt };
            });
          })
        );
      }

      Promise.all(pagePromises).then(function (results) {
        results.sort(function (a, b) { return a.page - b.page; });
        extractedText = results.map(function (r) { return r.text; }).join('\n\n');
        showProgress(false, '', 100);
        isExtracting = false;
      }).catch(function (err) {
        showToast('Error extracting pages: ' + err.message);
        showProgress(false, '', 0);
        isExtracting = false;
      });

    }).catch(function (err) {
      showToast('Could not parse PDF: ' + err.message);
      showProgress(false, '', 0);
      isExtracting = false;
    });
  };
  reader.readAsArrayBuffer(file);
}

function showPdfInfo(name, pages) {
  document.getElementById('pdfInfoBar').classList.add('visible');
  document.getElementById('pdfInfoName').textContent  = name;
  document.getElementById('pdfInfoPages').textContent = pages;
}
function showProgress(visible, label, pct) {
  const wrap = document.getElementById('progressWrap');
  if (!visible) { wrap.classList.remove('visible'); return; }
  wrap.classList.add('visible');
  document.getElementById('progressLabel').textContent = label;
  document.getElementById('progressFill').style.width  = pct + '%';
}

// ── Generate Summary ───────────────────────────────────────
async function handleGenerate() {
  const title    = document.getElementById('inputTitle').value.trim();
  const category = document.getElementById('inputCategory').value;
  let   text     = '';
  let   pdfName  = 'Direct Text Input';

  if (currentMode === 'pdf') {
    if (isExtracting) { showToast('PDF is still being extracted, please wait…'); return; }
    if (!extractedText.trim()) { showToast('No PDF text found. Please upload a valid PDF first.'); return; }
    text    = extractedText;
    pdfName = currentPdfName || 'Uploaded PDF';
  } else {
    text = document.getElementById('pasteTextarea').value.trim();
    if (!text) { showToast('Please paste some text before generating.'); return; }
    if (!title) {
      const firstLine = text.split('\n')[0].slice(0, 80).trim();
      document.getElementById('inputTitle').value = firstLine || 'Untitled';
    }
  }

  const finalTitle = document.getElementById('inputTitle').value.trim() || 'Untitled Document';
  setGenerateLoading(true);
  showSkeletonOutput();

  try {
    const res  = await fetch('api_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action        : 'summarize',
        title         : finalTitle,
        category      : category,
        pdf_name      : pdfName,
        extracted_text: text,
      })
    });
    const data = await safeJson(res);
    if (!data.success) throw new Error(data.error || 'Unknown error from server.');

    renderOutput(data.title, data.category, data.pdf_name, data.summary, null, data.history_id);
    addSidebarItem(data.history_id, data.title, data.category, pdfName);

  } catch (err) {
    showToast('Summary failed: ' + err.message);
    showEmptyOutput();
  } finally {
    setGenerateLoading(false);
  }
}

function setGenerateLoading(on) {
  const btn = document.getElementById('btnGenerate');
  if (on) {
    btn.disabled = true;
    btn.innerHTML = '<span class="btn-spinner"></span> Generating…';
  } else {
    btn.disabled = false;
    btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
    </svg> Generate Summary`;
  }
}

// ── Render Output ──────────────────────────────────────────
function renderOutput(title, category, pdfName, summary, impQuestions, histId) {
  currentHistoryId = histId;
  currentTitle     = title;

  document.getElementById('outputTitle').textContent = title;
  document.getElementById('outputMeta').innerHTML =
    `<span class="badge-dm brand">${escHtml(category)}</span>
     <span class="badge-dm">${escHtml(pdfName !== 'Direct Text Input' ? '📄 ' + pdfName : '📝 Direct Text')}</span>`;

  const pa = document.getElementById('printable-area');
  let html = `<h1 style="margin-top:0;">${escHtml(title)}</h1>`;
  html    += `<p style="font-size:.75rem;color:var(--text-tertiary);margin-bottom:1rem;">
                ${escHtml(category)} · ${escHtml(pdfName)} · ${new Date().toLocaleDateString()}
              </p>`;
  html    += `<div id="summary-content">${marked.parse(summary || '')}</div>`;

  if (impQuestions) {
    html += `<div class="qs-section">
      <div class="qs-section-head">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Important Questions
      </div>
      <div id="questions-content">${marked.parse(impQuestions)}</div>
    </div>`;
  }

  pa.innerHTML = html;
  pa.style.display = 'block';
  document.getElementById('outputEmpty').style.display = 'none';
  document.getElementById('actionBar').classList.remove('hidden');

  // Update generate questions button based on whether qs already exist
  const qBtn = document.getElementById('btnGenQuestions');
  if (impQuestions) {
    qBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/></svg> Regenerate IMP Qs`;
  } else {
    qBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg> Generate IMP Questions`;
  }
}

function showSkeletonOutput() {
  document.getElementById('outputEmpty').style.display = 'none';
  const pa = document.getElementById('printable-area');
  pa.style.display = 'block';
  pa.innerHTML = `<div class="output-skeleton">
    <div class="skeleton skel-h2" style="margin-bottom:1.2rem;"></div>
    ${[80,65,90,70,55,85,60,75,88,50].map(w =>
      `<div class="skeleton skel-line" style="width:${w}%;"></div>`
    ).join('')}
    <div class="skeleton skel-h2" style="margin-top:1.5rem;width:40%;"></div>
    ${[75,60,80,55].map(w =>
      `<div class="skeleton skel-line" style="width:${w}%;"></div>`
    ).join('')}
  </div>`;
}

function showEmptyOutput() {
  document.getElementById('outputEmpty').style.display = 'flex';
  document.getElementById('printable-area').style.display = 'none';
  document.getElementById('actionBar').classList.add('hidden');
}

// ── Load History Item ──────────────────────────────────────
async function loadHistoryItem(id, el) {
  document.querySelectorAll('.history-item').forEach(function (e) {
    e.classList.remove('active');
  });
  if (el) el.classList.add('active');

  showSkeletonOutput();
  document.getElementById('actionBar').classList.remove('hidden');

  try {
    const res  = await fetch('api_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'get_history', id: id })
    });
    const data = await safeJson(res);
    if (!data.success) throw new Error(data.error || 'Could not load record.');
    const r = data.record;
    renderOutput(r.title, r.category, r.pdf_name, r.summary, r.imp_questions, r.id);
  } catch (err) {
    showToast('Load failed: ' + err.message);
    showEmptyOutput();
  }
}

// ── Generate IMP Questions ─────────────────────────────────
async function handleGenerateQuestions() {
  if (!currentHistoryId) { showToast('No document loaded.'); return; }

  const qBtn = document.getElementById('btnGenQuestions');
  qBtn.disabled = true;
  qBtn.innerHTML = '<span class="btn-spinner" style="border-top-color:#fff;"></span> Generating…';

  try {
    const res  = await fetch('api_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'generate_questions', history_id: currentHistoryId })
    });
    const data = await safeJson(res);
    if (!data.success) throw new Error(data.error || 'Failed to generate questions.');

    // Append / replace questions inside #printable-area
    let qsDiv = document.getElementById('questions-content');
    const pa  = document.getElementById('printable-area');

    if (!qsDiv) {
      const section = document.createElement('div');
      section.className = 'qs-section';
      section.innerHTML = `<div class="qs-section-head">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Important Questions
      </div>
      <div id="questions-content"></div>`;
      pa.appendChild(section);
      qsDiv = document.getElementById('questions-content');
    }

    qsDiv.innerHTML = marked.parse(data.imp_questions || '');
    qsDiv.closest('.qs-section').scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Update sidebar badge
    updateSidebarQsBadge(currentHistoryId);

    qBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/></svg> Regenerate IMP Qs`;

  } catch (err) {
    showToast('Questions failed: ' + err.message);
    qBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg> Generate IMP Questions`;
  } finally {
    qBtn.disabled = false;
  }
}

// ── Download PDF via html2pdf.js ───────────────────────────
function downloadSummaryPdf() {
  const el = document.getElementById('printable-area');
  if (!el || el.style.display === 'none') { showToast('Nothing to download yet.'); return; }
  const safeTitle = (currentTitle || 'Summary').replace(/[^a-z0-9_\-\s]/gi, '').trim().replace(/\s+/g, '_');
  const opt = {
    margin      : [12, 12, 12, 12],
    filename    : safeTitle + '_Summary.pdf',
    image       : { type: 'jpeg', quality: 0.97 },
    html2canvas : { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
    jsPDF       : { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak   : { mode: ['avoid-all', 'css', 'legacy'] },
  };
  // Force light background for PDF
  const cloned = el.cloneNode(true);
  cloned.style.background = '#ffffff';
  cloned.style.color      = '#09090b';
  cloned.querySelectorAll('h1,h2,h3,h4,p,li,strong').forEach(function (n) {
    n.style.color = n.tagName.match(/^H[1-4]$/) ? '#09090b' : '#374151';
  });
  html2pdf().set(opt).from(cloned).save();
}

// ── New Session ────────────────────────────────────────────
document.getElementById('btnNewSession').addEventListener('click', function () {
  // Reset inputs
  document.getElementById('inputTitle').value    = '';
  document.getElementById('inputCategory').value = 'Study';
  document.getElementById('pasteTextarea').value  = '';
  document.getElementById('pdfFileInput').value   = '';
  updateWordCounter();
  extractedText    = '';
  currentPdfName   = '';
  currentHistoryId = null;
  currentTitle     = '';
  document.getElementById('pdfInfoBar').classList.remove('visible');
  showProgress(false, '', 0);
  showEmptyOutput();
  switchMode('pdf');
  document.querySelectorAll('.history-item').forEach(function (e) { e.classList.remove('active'); });
});

// ── Sidebar Search / Filter ────────────────────────────────
document.getElementById('historySearch').addEventListener('input', function () {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('.history-item').forEach(function (item) {
    const text = item.textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
});

// ── Sidebar helpers ────────────────────────────────────────
function addSidebarItem(id, title, category, pdfName) {
  const isPdf   = (pdfName !== 'Direct Text Input');
  const icon    = isPdf ? '📄' : '📝';
  const catShort = category.toUpperCase().slice(0, 3);
  const today   = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
  const el      = document.createElement('div');
  el.className  = 'history-item active';
  el.setAttribute('role', 'listitem');
  el.setAttribute('data-id', id);
  el.setAttribute('data-title', title);
  el.setAttribute('title', title + ' — ' + category);
  el.onclick    = function () { loadHistoryItem(id, el); };
  el.innerHTML  = `
    <div class="history-item-icon">${icon}</div>
    <div class="history-item-body">
      <div class="history-item-title">${escHtml(title)}</div>
      <div class="history-item-meta">
        <span class="history-item-cat">${escHtml(catShort)}</span>
        <span class="history-item-date">${today}</span>
      </div>
    </div>`;

  document.querySelectorAll('.history-item').forEach(function (e) { e.classList.remove('active'); });
  const list = document.getElementById('historyList');
  // Remove empty state if present
  const empty = document.getElementById('sidebarEmpty');
  if (empty) empty.remove();
  list.prepend(el);
}

function updateSidebarQsBadge(id) {
  const item = document.querySelector(`.history-item[data-id="${id}"] .history-item-meta`);
  if (!item) return;
  if (!item.querySelector('.history-item-qs')) {
    const badge = document.createElement('span');
    badge.className = 'history-item-qs';
    badge.textContent = '✓ IMP Qs';
    item.appendChild(badge);
  }
}

// ── Toast ──────────────────────────────────────────────────
let _toastTimer = null;
function showToast(msg) {
  const t = document.getElementById('toastError');
  document.getElementById('toastMsg').textContent = msg;
  t.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(function () { t.classList.remove('show'); }, 4500);
}

// ── Escape HTML ────────────────────────────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str || '')));
  return d.innerHTML;
}

// ── DOMContentLoaded: init feather ────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  if (typeof feather !== 'undefined') feather.replace({ 'stroke-width': 2, width: 14, height: 14 });
});
</script>

</body>
</html>
