<?php
/**
 * DocuMind — profile.php (Account Settings & User CRUD)
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// Handle DELETE Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    try {
        $delStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delStmt->execute([$userId]);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: index.php?msg=account_deleted');
        exit;
    } catch (PDOException $e) {
        error_log('[DocuMind Profile Delete Error] ' . $e->getMessage());
        $error = 'Unable to delete account at this time.';
    }
}

// Handle UPDATE Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $role         = trim($_POST['role'] ?? 'Student');
    $new_password = $_POST['new_password'] ?? '';

    $validRoles = ['Student', 'Researcher', 'Professional'];
    if (!in_array($role, $validRoles, true)) {
        $role = 'Student';
    }

    if (empty($full_name)) {
        $error = 'Full Name cannot be empty.';
    } else {
        try {
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, role = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$full_name, $role, $password_hash, $userId]);
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_role'] = $role;
                    $success = 'Profile and password updated successfully.';
                }
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, role = ? WHERE id = ?');
                $stmt->execute([$full_name, $role, $userId]);
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_role'] = $role;
                $success = 'Profile updated successfully.';
            }
        } catch (PDOException $e) {
            error_log('[DocuMind Profile Update Error] ' . $e->getMessage());
            $error = 'Error updating profile details.';
        }
    }
}

// Fetch current user details
try {
    $userStmt = $pdo->prepare('SELECT full_name, email, role, created_at FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch();

    // User Statistics
    $stat1Stmt = $pdo->prepare('SELECT COUNT(*) AS total_summaries FROM pdf_history WHERE user_id = ?');
    $stat1Stmt->execute([$userId]);
    $totalSummaries = (int)($stat1Stmt->fetch()['total_summaries'] ?? 0);

    $stat2Stmt = $pdo->prepare('SELECT COUNT(*) AS total_questions FROM pdf_history WHERE user_id = ? AND imp_questions IS NOT NULL AND imp_questions != ""');
    $stat2Stmt->execute([$userId]);
    $totalQuestions = (int)($stat2Stmt->fetch()['total_questions'] ?? 0);

} catch (PDOException $e) {
    error_log('[DocuMind Profile Fetch Error] ' . $e->getMessage());
    $currentUser = [
        'full_name'  => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['user_role'] ?? 'Student',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $totalSummaries = 0;
    $totalQuestions = 0;
}

$pageTitle = 'Account Profile';
require_once __DIR__ . '/navbar.php';
?>

<div class="dm-page-wrap py-4">
  <div class="row g-4">
    
    <!-- Profile & Metrics Overview -->
    <div class="col-lg-4">
      <div class="uiverse-card mb-4 text-center p-4">
        <div class="dm-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:1.5rem;">
          <?= strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <h2 class="h5 fw-bold mb-1 text-primary"><?= htmlspecialchars($currentUser['full_name']) ?></h2>
        <p class="text-muted-dm small mb-2"><?= htmlspecialchars($currentUser['email']) ?></p>
        <span class="badge-dm brand mb-3"><?= htmlspecialchars($currentUser['role']) ?></span>

        <div class="divider-dm my-3"></div>

        <div class="text-start small text-muted-dm">
          <div class="d-flex justify-content-between mb-2">
            <span>Account Status</span>
            <span class="text-success fw-semibold">Active</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>Registered On</span>
            <span class="text-primary"><?= date('M d, Y', strtotime($currentUser['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <!-- Activity Metrics -->
      <div class="uiverse-card p-4">
        <h3 class="h6 fw-bold mb-3 text-primary d-flex align-items-center gap-2">
          <i data-feather="bar-chart-2" style="width:16px;height:16px;"></i>
          <span>Usage Statistics</span>
        </h3>
        
        <div class="d-flex flex-column gap-3">
          <div class="p-3 bg-surface-dm border-dm radius-md d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block small text-muted-dm">Total Summaries</span>
              <strong class="h4 mb-0 text-primary"><?= $totalSummaries ?></strong>
            </div>
            <i data-feather="file-text" class="text-faint-dm"></i>
          </div>

          <div class="p-3 bg-surface-dm border-dm radius-md d-flex justify-content-between align-items-center">
            <div>
              <span class="d-block small text-muted-dm">Question Sets</span>
              <strong class="h4 mb-0 text-primary"><?= $totalQuestions ?></strong>
            </div>
            <i data-feather="help-circle" class="text-faint-dm"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Profile and Danger Zone -->
    <div class="col-lg-8">
      
      <!-- Notifications -->
      <?php if (!empty($success)): ?>
        <div class="alert-dm success mb-4" role="alert">
          <i data-feather="check-circle" class="flex-shrink-0" style="width:16px;height:16px;"></i>
          <span><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert-dm danger mb-4" role="alert">
          <i data-feather="alert-circle" class="flex-shrink-0" style="width:16px;height:16px;"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Update Profile Card -->
      <div class="uiverse-card p-4 p-md-5 mb-4">
        <div class="mb-4">
          <h2 class="h4 fw-bold mb-1 text-primary">Account Settings</h2>
          <p class="text-muted-dm small">Update personal details or renew your authentication key.</p>
        </div>

        <form method="POST" action="profile.php" id="updateProfileForm" novalidate>
          <input type="hidden" name="action" value="update_profile">

          <div class="mb-3">
            <label for="full_name" class="form-label small fw-semibold text-primary">Full Name</label>
            <input 
              type="text" 
              name="full_name" 
              id="full_name" 
              class="form-control bg-surface-dm border-dm text-primary" 
              value="<?= htmlspecialchars($currentUser['full_name']) ?>" 
              required
            >
          </div>

          <div class="mb-3">
            <label for="email_static" class="form-label small fw-semibold text-primary">Email Address</label>
            <input 
              type="email" 
              id="email_static" 
              class="form-control bg-surface-dm border-dm text-muted-dm" 
              value="<?= htmlspecialchars($currentUser['email']) ?>" 
              disabled
            >
            <small class="text-faint-dm">Primary identifier cannot be altered.</small>
          </div>

          <div class="mb-4">
            <label for="role" class="form-label small fw-semibold text-primary">Role</label>
            <select name="role" id="role" class="form-select bg-surface-dm border-dm text-primary">
              <option value="Student" <?= ($currentUser['role'] === 'Student') ? 'selected' : '' ?>>Student</option>
              <option value="Researcher" <?= ($currentUser['role'] === 'Researcher') ? 'selected' : '' ?>>Researcher</option>
              <option value="Professional" <?= ($currentUser['role'] === 'Professional') ? 'selected' : '' ?>>Professional</option>
            </select>
          </div>

          <div class="divider-dm"></div>

          <div class="mb-4">
            <label for="new_password" class="form-label small fw-semibold text-primary">Change Password (Optional)</label>
            <input 
              type="password" 
              name="new_password" 
              id="new_password" 
              class="form-control bg-surface-dm border-dm text-primary" 
              placeholder="Leave blank to maintain current password"
            >
            <small class="text-faint-dm">Minimum 6 characters if you wish to reset.</small>
          </div>

          <button type="submit" class="btn-animated btn-primary-anim px-4">
            <i data-feather="save" style="width:14px;height:14px;"></i>
            <span>Save Profile Modifications</span>
          </button>
        </form>
      </div>

      <!-- Danger Zone: Delete Account -->
      <div class="uiverse-card border-danger p-4" style="border-color: rgba(220, 38, 38, 0.3) !important;">
        <h3 class="h6 fw-bold text-danger mb-2">Danger Zone</h3>
        <p class="text-muted-dm small mb-3">
          Terminating your account will permanently purge your user profile and all associated PDF history summaries from the database.
        </p>

        <form method="POST" action="profile.php" onsubmit="return confirm('Warning: This will permanently delete your account and all associated documents. Proceed?');">
          <input type="hidden" name="action" value="delete_account">
          <button type="submit" class="btn-animated btn-danger-anim">
            <i data-feather="trash-2" style="width:14px;height:14px;"></i>
            <span>Permanently Delete Account</span>
          </button>
        </form>
      </div>

    </div>

  </div>
</div>
</body>
</html>