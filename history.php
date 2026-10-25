<?php
/**
 * DocuMind — history.php
 * Full Database CRUD & Live Search Manager (OEP Guideline #3 & #4)
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$uid     = (int) $_SESSION['user_id'];
$msg     = '';
$msgType = '';

// ── DELETE single record ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_record') {
    $rid = (int) ($_POST['record_id'] ?? 0);
    if ($rid > 0) {
        $st = $pdo->prepare('DELETE FROM pdf_history WHERE id = ? AND user_id = ?');
        $st->execute([$rid, $uid]);
        $msg     = $st->rowCount() ? 'Record deleted successfully.' : 'Record not found.';
        $msgType = $st->rowCount() ? 'success' : 'danger';
    }
}

// ── DELETE all records ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_all') {
    $st = $pdo->prepare('DELETE FROM pdf_history WHERE user_id = ?');
    $st->execute([$uid]);
    $msg     = 'All history cleared.';
    $msgType = 'success';
}

// ── UPDATE record ─────────────────────────────────────────────────────────────
$updateErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_record') {
    $rid        = (int)   ($_POST['record_id'] ?? 0);
    $newTitle   = trim(   $_POST['title']      ?? '');
    $newCat     = trim(   $_POST['category']   ?? '');
    $newNotes   = trim(   $_POST['user_notes'] ?? '');

    $validCats = ['Study', 'Non-Study / General', 'Business', 'Research'];

    // Server-side validation
    if ($rid <= 0)                              $updateErrors[] = 'Invalid record.';
    if (strlen($newTitle) < 3)                  $updateErrors[] = 'Title must be at least 3 characters.';
    if (strlen($newTitle) > 255)                $updateErrors[] = 'Title too long (max 255).';
    if (!in_array($newCat, $validCats, true))   $updateErrors[] = 'Invalid category selected.';
    if (strlen($newNotes) > 5000)               $updateErrors[] = 'Notes too long (max 5000 chars).';

    if (empty($updateErrors)) {
        $st = $pdo->prepare('UPDATE pdf_history SET title = ?, category = ?, user_notes = ? WHERE id = ? AND user_id = ?');
        $st->execute([$newTitle, $newCat, $newNotes ?: null, $rid, $uid]);
        $msg     = $st->rowCount() ? 'Record updated successfully.' : 'No changes made or record not found.';
        $msgType = 'success';
    } else {
        $msg     = implode(' ', $updateErrors);
        $msgType = 'danger';
    }
}

// ── SEARCH & FILTER ───────────────────────────────────────────────────────────
$q   = trim($_GET['q']   ?? '');
$cat = trim($_GET['cat'] ?? '');

$validCats = ['Study', 'Non-Study / General', 'Business', 'Research'];

$sql  = 'SELECT id, title, pdf_name, category, user_notes,
                (imp_questions IS NOT NULL AND imp_questions != \'\') AS has_questions,
                created_at
         FROM pdf_history
         WHERE user_id = :uid';
$params = [':uid' => $uid];

if ($q !== '') {
    $sql .= ' AND (title LIKE :q OR pdf_name LIKE :q OR summary LIKE :q OR user_notes LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($cat !== '' && in_array($cat, $validCats, true)) {
    $sql .= ' AND category = :cat';
    $params[':cat'] = $cat;
}

$sql .= ' ORDER BY created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$records = $st->fetchAll();

$pageTitle = 'My Directory';
require_once __DIR__ . '/navbar.php';
?>

<style>
  /* ── History page extras ────────────────────────────────────────── */
  .page-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.75rem;
  }
  .page-hero h1 {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -.03em;
    margin: 0;
  }
  .page-hero p { margin: .2rem 0 0; font-size: .875rem; }

  /* Search bar */
  .search-row {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
    align-items: center;
  }
  .dm-input {
    flex: 1 1 260px;
    height: 38px;
    padding: 0 .85rem;
    background: var(--bg-elevated);
    border: 1px solid var(--border-base);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    color: var(--text-primary);
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease;
  }
  .dm-input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px var(--accent-ring);
  }
  .dm-select {
    height: 38px;
    padding: 0 .85rem;
    background: var(--bg-elevated);
    border: 1px solid var(--border-base);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    color: var(--text-primary);
    outline: none;
    cursor: pointer;
    transition: border-color .2s ease;
  }
  .dm-select:focus { border-color: var(--brand); }

  /* Table */
  .hist-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .hist-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
  }
  .hist-table thead th {
    background: var(--bg-sunken);
    color: var(--text-tertiary);
    font-weight: 600;
    font-size: .72rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: .65rem .9rem;
    border-bottom: 1px solid var(--border-base);
    white-space: nowrap;
  }
  .hist-table tbody tr {
    border-bottom: 1px solid var(--border-subtle);
    transition: background .15s ease;
  }
  .hist-table tbody tr:last-child { border-bottom: none; }
  .hist-table tbody tr:hover { background: var(--bg-sunken); }
  .hist-table td {
    padding: .75rem .9rem;
    vertical-align: middle;
    color: var(--text-primary);
  }
  .hist-table td.muted { color: var(--text-secondary); font-size: .78rem; }

  /* Source badge */
  .src-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .18rem .55rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .02em;
  }
  .src-badge.pdf  { background: var(--brand-subtle); color: var(--brand); }
  .src-badge.text { background: #f0fdf4; color: var(--success); }
  [data-theme="dark"] .src-badge.text { background: #052e16; }

  /* Category badge */
  .cat-badge {
    display: inline-block;
    padding: .18rem .6rem;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 600;
    background: var(--accent-subtle);
    color: var(--text-secondary);
    border: 1px solid var(--border-base);
    white-space: nowrap;
  }

  /* IMP badge */
  .imp-badge-yes { background: #f0fdf4; color: var(--success); border-color: transparent; }
  [data-theme="dark"] .imp-badge-yes { background: #052e16; }
  .imp-badge-no  { background: var(--bg-sunken); color: var(--text-tertiary); }

  /* Notes preview */
  .notes-prev {
    max-width: 180px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    color: var(--text-secondary);
    font-size: .78rem;
    font-style: italic;
  }

  /* Action buttons in table */
  .tbl-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .65rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border-base);
    background: transparent;
    color: var(--text-primary);
    text-decoration: none;
    transition: background .18s ease, border-color .18s ease, transform .1s ease, color .18s ease;
    white-space: nowrap;
  }
  .tbl-btn:hover { background: var(--bg-sunken); border-color: var(--border-strong); transform: translateY(-1px); }
  .tbl-btn:active { transform: translateY(0); }
  .tbl-btn.brand-btn { background: var(--brand); color: #fff; border-color: var(--brand); }
  .tbl-btn.brand-btn:hover { background: var(--brand-hover); border-color: var(--brand-hover); }
  .tbl-btn.del-btn   { color: var(--danger); }
  .tbl-btn.del-btn:hover { background: #fef2f2; border-color: #fecaca; }
  [data-theme="dark"] .tbl-btn.del-btn:hover { background: #2d1515; border-color: #7f1d1d; }

  .btn-grp { display: flex; gap: .35rem; flex-wrap: nowrap; }

  /* Empty state */
  .empty-state {
    text-align: center;
    padding: 3.5rem 1rem;
    color: var(--text-tertiary);
  }
  .empty-state svg { opacity: .3; margin-bottom: .75rem; }
  .empty-state p { font-size: .9rem; margin: 0; }

  /* Modal overrides */
  .dm-modal .modal-content {
    background: var(--bg-elevated);
    border: 1px solid var(--border-base);
    border-radius: 14px;
    box-shadow: var(--shadow-lg);
    color: var(--text-primary);
  }
  .dm-modal .modal-header {
    border-bottom: 1px solid var(--border-base);
    padding: 1.1rem 1.4rem;
  }
  .dm-modal .modal-footer {
    border-top: 1px solid var(--border-base);
    padding: .9rem 1.4rem;
  }
  .dm-modal .modal-title { font-weight: 700; font-size: 1rem; }
  .dm-modal .btn-close { filter: var(--text-primary) == '#fafafa' ? invert(1) : none; }
  [data-theme="dark"] .dm-modal .btn-close { filter: invert(1); }

  .form-label-dm {
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: .35rem;
    display: block;
    letter-spacing: .02em;
  }
  .form-ctrl-dm {
    width: 100%;
    padding: .55rem .85rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    color: var(--text-primary);
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease;
  }
  .form-ctrl-dm:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px var(--accent-ring);
  }
  .form-ctrl-dm.is-invalid { border-color: var(--danger); }
  textarea.form-ctrl-dm { resize: vertical; min-height: 90px; }

  .err-text { font-size: .75rem; color: var(--danger); margin-top: .3rem; display: none; }
  .err-text.show { display: block; }

  /* Stats bar */
  .stats-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
  }
  .stat-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .3rem .8rem;
    background: var(--bg-sunken);
    border: 1px solid var(--border-base);
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 600;
    color: var(--text-secondary);
  }
  .stat-chip span { color: var(--text-primary); }
</style>

<main class="dm-page-wrap">

  <!-- Page Header -->
  <div class="page-hero">
    <div>
      <h1>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-.2em;margin-right:.4rem;" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        My Directory
      </h1>
      <p class="text-muted-dm">Search, edit, and manage your PDF & text analysis history.</p>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
      <form method="post" id="clearAllForm" style="margin:0;">
        <input type="hidden" name="action" value="clear_all">
        <button type="button" class="btn-animated btn-danger-anim" onclick="confirmClearAll()" style="font-size:.8rem;padding:.4rem 1rem;">
          <i data-feather="trash-2" style="width:13px;height:13px;" aria-hidden="true"></i> Clear All History
        </button>
      </form>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="alert-dm <?= $msgType === 'success' ? 'success' : 'danger' ?> mb-3" role="alert">
    <i data-feather="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:15px;height:15px;flex-shrink:0;" aria-hidden="true"></i>
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-chip"><i data-feather="layers" style="width:12px;height:12px;" aria-hidden="true"></i> Total Records: <span><?= count($records) ?></span></div>
    <?php if ($q !== ''): ?>
    <div class="stat-chip"><i data-feather="search" style="width:12px;height:12px;" aria-hidden="true"></i> Query: <span>"<?= htmlspecialchars($q) ?>"</span></div>
    <?php endif; ?>
    <?php if ($cat !== ''): ?>
    <div class="stat-chip"><i data-feather="tag" style="width:12px;height:12px;" aria-hidden="true"></i> Filter: <span><?= htmlspecialchars($cat) ?></span></div>
    <?php endif; ?>
  </div>

  <!-- Search & Filter Form -->
  <form method="get" id="searchForm">
    <div class="search-row">
      <input type="text" class="dm-input" name="q" id="searchQ" value="<?= htmlspecialchars($q) ?>"
             placeholder="Search by title, filename, summary, or notes…" autocomplete="off">
      <select class="dm-select" name="cat" id="catFilter" onchange="document.getElementById('searchForm').submit()">
        <option value="">All Categories</option>
        <?php foreach (['Study','Non-Study / General','Business','Research'] as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-animated btn-primary-anim" style="font-size:.82rem;padding:.42rem 1.1rem;">
        <i data-feather="search" style="width:13px;height:13px;" aria-hidden="true"></i> Search
      </button>
      <?php if ($q !== '' || $cat !== ''): ?>
      <a href="history.php" class="btn-animated btn-outline-anim" style="font-size:.82rem;padding:.42rem 1rem;">
        <i data-feather="x" style="width:13px;height:13px;" aria-hidden="true"></i> Clear
      </a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Records Table Card -->
  <div class="uiverse-card" style="padding:0;overflow:hidden;">
    <?php if (empty($records)): ?>
    <div class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <p><?= ($q !== '' || $cat !== '') ? 'No records match your search criteria.' : 'No records yet. Go to the Workspace to analyze a PDF or text.' ?></p>
    </div>
    <?php else: ?>
    <div class="hist-table-wrap">
      <table class="hist-table" role="table" aria-label="History records">
        <thead>
          <tr>
            <th style="width:70px;">ID / Date</th>
            <th>Custom Title</th>
            <th style="width:140px;">Source</th>
            <th style="width:120px;">Category</th>
            <th style="width:90px;">IMP Q's</th>
            <th style="width:180px;">Personal Notes</th>
            <th style="width:230px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row): ?>
          <tr>
            <!-- ID / Date -->
            <td>
              <div style="font-weight:700;font-size:.78rem;color:var(--text-tertiary);">#<?= $row['id'] ?></div>
              <div style="font-size:.7rem;color:var(--text-tertiary);margin-top:.1rem;">
                <?= date('d M y', strtotime($row['created_at'])) ?>
              </div>
            </td>

            <!-- Custom Title -->
            <td>
              <div style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['title']) ?>">
                <?= htmlspecialchars($row['title']) ?>
              </div>
            </td>

            <!-- Source -->
            <td>
              <?php $isPdf = stripos($row['pdf_name'], '.pdf') !== false || $row['pdf_name'] !== 'Direct Text Input'; ?>
              <?php if ($row['pdf_name'] === 'Direct Text Input'): ?>
              <span class="src-badge text">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                Direct Text
              </span>
              <?php else: ?>
              <span class="src-badge pdf">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF
              </span>
              <div style="font-size:.68rem;color:var(--text-tertiary);margin-top:.2rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['pdf_name']) ?>">
                <?= htmlspecialchars($row['pdf_name']) ?>
              </div>
              <?php endif; ?>
            </td>

            <!-- Category -->
            <td><span class="cat-badge"><?= htmlspecialchars($row['category']) ?></span></td>

            <!-- IMP Q's -->
            <td>
              <?php if ($row['has_questions']): ?>
              <span class="cat-badge imp-badge-yes">✓ Yes</span>
              <?php else: ?>
              <span class="cat-badge imp-badge-no">— No</span>
              <?php endif; ?>
            </td>

            <!-- Notes -->
            <td>
              <div class="notes-prev"><?= $row['user_notes'] ? htmlspecialchars($row['user_notes']) : '<span style="color:var(--text-tertiary);font-style:normal;">—</span>' ?></div>
            </td>

            <!-- Actions -->
            <td>
              <div class="btn-grp">
                <a href="dashboard.php?id=<?= $row['id'] ?>" class="tbl-btn brand-btn" title="Open in Workspace">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                  Open
                </a>
                <button type="button" class="tbl-btn"
                        onclick="openEditModal(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['category']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['user_notes'] ?? ''), ENT_QUOTES) ?>)"
                        title="Edit / Add Notes">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
                <button type="button" class="tbl-btn del-btn"
                        onclick="confirmDelete(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['title']), ENT_QUOTES) ?>)"
                        title="Delete record">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  Del
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <p class="text-faint-dm" style="font-size:.72rem;margin-top:.85rem;text-align:right;">
    <?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?> shown
    <?= ($q !== '' || $cat !== '') ? '· Filtered view' : '· All records' ?>
  </p>

</main>

<!-- ── Edit / Add Notes Modal ──────────────────────────────────────────────── -->
<div class="modal fade dm-modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-.2em;margin-right:.4rem;" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit Record
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" id="editForm" novalidate>
        <input type="hidden" name="action" value="update_record">
        <input type="hidden" name="record_id" id="editRecordId">
        <div class="modal-body" style="padding:1.4rem;display:flex;flex-direction:column;gap:1rem;">

          <!-- Title -->
          <div>
            <label class="form-label-dm" for="editTitle">Custom Title <span style="color:var(--danger)">*</span></label>
            <input type="text" class="form-ctrl-dm" id="editTitle" name="title" maxlength="255" required placeholder="Enter a descriptive title…">
            <div class="err-text" id="titleErr">Title must be at least 3 characters.</div>
          </div>

          <!-- Category -->
          <div>
            <label class="form-label-dm" for="editCategory">Category <span style="color:var(--danger)">*</span></label>
            <select class="form-ctrl-dm" id="editCategory" name="category" required>
              <option value="Study">Study</option>
              <option value="Non-Study / General">Non-Study / General</option>
              <option value="Business">Business</option>
              <option value="Research">Research</option>
            </select>
          </div>

          <!-- Notes -->
          <div>
            <label class="form-label-dm" for="editNotes">Personal Notes</label>
            <textarea class="form-ctrl-dm" id="editNotes" name="user_notes" maxlength="5000" placeholder="Add personal notes, tags, or reminders…"></textarea>
            <div style="font-size:.7rem;color:var(--text-tertiary);margin-top:.25rem;text-align:right;">
              <span id="notesCount">0</span> / 5000
            </div>
          </div>

        </div>
        <div class="modal-footer" style="gap:.6rem;">
          <button type="button" class="btn-animated btn-outline-anim" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-animated btn-primary-anim" id="editSubmitBtn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Delete confirmation form (hidden) ─────────────────────────────────── -->
<form method="post" id="deleteForm" style="display:none;">
  <input type="hidden" name="action" value="delete_record">
  <input type="hidden" name="record_id" id="deleteRecordId">
</form>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
  'use strict';

  // ── Live search debounce ──────────────────────────────────────────
  const searchInput = document.getElementById('searchQ');
  let debTimer;
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(debTimer);
      debTimer = setTimeout(() => document.getElementById('searchForm').submit(), 600);
    });
  }

  // ── Edit modal ────────────────────────────────────────────────────
  const editModal    = new bootstrap.Modal(document.getElementById('editModal'));
  const notesArea    = document.getElementById('editNotes');
  const notesCounter = document.getElementById('notesCount');

  if (notesArea && notesCounter) {
    notesArea.addEventListener('input', function () {
      notesCounter.textContent = this.value.length;
    });
  }

  window.openEditModal = function (id, title, category, notes) {
    document.getElementById('editRecordId').value  = id;
    document.getElementById('editTitle').value     = title;
    document.getElementById('editCategory').value  = category;
    document.getElementById('editNotes').value     = notes || '';
    if (notesCounter) notesCounter.textContent     = (notes || '').length;

    // Reset validation state
    document.getElementById('titleErr').classList.remove('show');
    document.getElementById('editTitle').classList.remove('is-invalid');

    editModal.show();
  };

  // Client-side validation before submit
  document.getElementById('editForm').addEventListener('submit', function (e) {
    let valid = true;
    const title = document.getElementById('editTitle');
    const titleErr = document.getElementById('titleErr');

    if (!title.value.trim() || title.value.trim().length < 3) {
      title.classList.add('is-invalid');
      titleErr.classList.add('show');
      valid = false;
    } else {
      title.classList.remove('is-invalid');
      titleErr.classList.remove('show');
    }

    if (!valid) e.preventDefault();
  });

  // ── Delete confirmation ───────────────────────────────────────────
  window.confirmDelete = function (id, title) {
    if (confirm('Delete record #' + id + '?\n"' + title + '"\n\nThis action cannot be undone.')) {
      document.getElementById('deleteRecordId').value = id;
      document.getElementById('deleteForm').submit();
    }
  };

  // ── Clear all confirmation ────────────────────────────────────────
  window.confirmClearAll = function () {
    if (confirm('Clear ALL history?\n\nThis will permanently delete every record for your account. This cannot be undone.')) {
      document.getElementById('clearAllForm').submit();
    }
  };

  // Re-replace feather icons after dynamic content
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof feather !== 'undefined') feather.replace({ 'stroke-width': 2, width: 14, height: 14 });
  });
})();
</script>

</body>
</html>
