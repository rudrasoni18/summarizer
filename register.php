<?php
/**
 * DocuMind — register.php (Registration Page)
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $role             = trim($_POST['role'] ?? 'Student');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $validRoles = ['Student', 'Researcher', 'Professional'];
    if (!in_array($role, $validRoles, true)) {
        $role = 'Student';
    }

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are mandatory.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must contain at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation password do not match.';
    } else {
        try {
            // Check for existing duplicate email
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address is already registered.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $insertStmt->execute([$full_name, $email, $password_hash, $role]);

                $newUserId = (int)$pdo->lastInsertId();

                // Auto-login into session
                $_SESSION['user_id']    = $newUserId;
                $_SESSION['user_name']  = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role']  = $role;

                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('[DocuMind Registration Error] ' . $e->getMessage());
            $error = 'Could not complete registration due to a database exception.';
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/navbar.php';
?>

<div class="dm-page-wrap py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8 col-sm-11">
      <div class="uiverse-card p-4 p-md-5">
        
        <div class="mb-4 text-center">
          <span class="badge-dm brand mb-2">Registration</span>
          <h1 class="h3 fw-bold mb-1">Join DocuMind</h1>
          <p class="text-muted-dm small">Configure your academic intelligence profile to begin.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert-dm danger mb-4" role="alert">
            <i data-feather="alert-circle" class="flex-shrink-0" style="width:16px;height:16px;"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="register.php" novalidate>
          <div class="mb-3">
            <label for="full_name" class="form-label small fw-semibold text-primary">Full Name</label>
            <input 
              type="text" 
              name="full_name" 
              id="full_name" 
              class="form-control bg-surface-dm border-dm text-primary" 
              placeholder="e.g. Alex Henderson" 
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
              required
            >
            <div class="text-danger small mt-1 d-none" id="nameError"></div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label small fw-semibold text-primary">Email Address</label>
            <input 
              type="email" 
              name="email" 
              id="email" 
              class="form-control bg-surface-dm border-dm text-primary" 
              placeholder="alex@university.edu" 
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
            >
            <div class="text-danger small mt-1 d-none" id="emailError"></div>
          </div>

          <div class="mb-3">
            <label for="role" class="form-label small fw-semibold text-primary">Academic Role</label>
            <select name="role" id="role" class="form-select bg-surface-dm border-dm text-primary">
              <option value="Student" <?= (($_POST['role'] ?? '') === 'Student') ? 'selected' : '' ?>>Student</option>
              <option value="Researcher" <?= (($_POST['role'] ?? '') === 'Researcher') ? 'selected' : '' ?>>Researcher</option>
              <option value="Professional" <?= (($_POST['role'] ?? '') === 'Professional') ? 'selected' : '' ?>>Professional</option>
            </select>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="password" class="form-label small fw-semibold text-primary">Password</label>
              <input 
                type="password" 
                name="password" 
                id="password" 
                class="form-control bg-surface-dm border-dm text-primary" 
                placeholder="Min 6 characters" 
                required
              >
              <div class="text-danger small mt-1 d-none" id="passError"></div>
            </div>

            <div class="col-md-6">
              <label for="confirm_password" class="form-label small fw-semibold text-primary">Confirm Password</label>
              <input 
                type="password" 
                name="confirm_password" 
                id="confirm_password" 
                class="form-control bg-surface-dm border-dm text-primary" 
                placeholder="Repeat password" 
                required
              >
              <div class="text-danger small mt-1 d-none" id="confirmPassError"></div>
            </div>
          </div>

          <button type="submit" class="btn-animated btn-primary-anim w-100 py-2 mb-3">
            <i data-feather="user-plus" style="width:15px;height:15px;"></i>
            <span>Create Account & Launch</span>
          </button>
        </form>

        <div class="text-center pt-3 border-top border-dm">
          <span class="text-muted-dm small">Already registered?</span>
          <a href="index.php" class="small fw-semibold ms-1">Log in here</a>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('registerForm');
  const nameInput = document.getElementById('full_name');
  const emailInput = document.getElementById('email');
  const passInput = document.getElementById('password');
  const confirmInput = document.getElementById('confirm_password');

  const nameError = document.getElementById('nameError');
  const emailError = document.getElementById('emailError');
  const passError = document.getElementById('passError');
  const confirmPassError = document.getElementById('confirmPassError');

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  form.addEventListener('submit', function (event) {
    let isValid = true;

    // Reset validations
    [nameError, emailError, passError, confirmPassError].forEach(el => el.classList.add('d-none'));
    [nameInput, emailInput, passInput, confirmInput].forEach(el => el.classList.remove('is-invalid'));

    if (!nameInput.value.trim()) {
      nameError.textContent = 'Full name is required.';
      nameError.classList.remove('d-none');
      nameInput.classList.add('is-invalid');
      isValid = false;
    }

    const emailVal = emailInput.value.trim();
    if (!emailVal) {
      emailError.textContent = 'Email address is required.';
      emailError.classList.remove('d-none');
      emailInput.classList.add('is-invalid');
      isValid = false;
    } else if (!emailRegex.test(emailVal)) {
      emailError.textContent = 'Valid email syntax required.';
      emailError.classList.remove('d-none');
      emailInput.classList.add('is-invalid');
      isValid = false;
    }

    const passVal = passInput.value;
    if (passVal.length < 6) {
      passError.textContent = 'Password must have at least 6 characters.';
      passError.classList.remove('d-none');
      passInput.classList.add('is-invalid');
      isValid = false;
    }

    if (confirmInput.value !== passVal) {
      confirmPassError.textContent = 'Passwords do not match.';
      confirmPassError.classList.remove('d-none');
      confirmInput.classList.add('is-invalid');
      isValid = false;
    }

    if (!isValid) {
      event.preventDefault();
    }
  });
});
</script>
</body>
</html>