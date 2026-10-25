<?php
/**
 * DocuMind — index.php (Authentication / Login Page)
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password credentials.';
            }
        } catch (PDOException $e) {
            error_log('[DocuMind Login Error] ' . $e->getMessage());
            $error = 'Authentication failed due to a system issue. Please try again.';
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/navbar.php';
?>

<div class="dm-page-wrap py-5">
  <div class="row g-4 align-items-stretch justify-content-center">
    
    <!-- Feature Presentation Column -->
    <div class="col-lg-6 col-md-10">
      <div class="uiverse-card h-100 d-flex flex-column justify-content-between p-4 p-md-5">
        <div>
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge-dm brand">OEP Project Edition</span>
            <span class="badge-dm">PHP 8 & MySQL</span>
          </div>
          <h1 class="h2 fw-bold mb-3 tracking-tight">Structured Document Intelligence for Academia.</h1>
          <p class="text-muted-dm mb-4">
            DocuMind transforms lengthy syllabus modules, research papers, and raw notes into clear executive briefs and exam-oriented question sets.
          </p>

          <div class="d-flex flex-column gap-3 my-4">
            <div class="p-3 bg-surface-dm border-dm radius-md d-flex align-items-start gap-3">
              <span class="badge-dm brand mt-1">01</span>
              <div>
                <strong class="d-block text-primary">Dual-Mode Extraction</strong>
                <small class="text-muted-dm">Seamlessly switch between native PDF file parsing and raw text direct pasting.</small>
              </div>
            </div>

            <div class="p-3 bg-surface-dm border-dm radius-md d-flex align-items-start gap-3">
              <span class="badge-dm brand mt-1">02</span>
              <div>
                <strong class="d-block text-primary">IMP Question Generator</strong>
                <small class="text-muted-dm">Extract short, analytical, and long examination questions directly from source context.</small>
              </div>
            </div>

            <div class="p-3 bg-surface-dm border-dm radius-md d-flex align-items-start gap-3">
              <span class="badge-dm brand mt-1">03</span>
              <div>
                <strong class="d-block text-primary">Personal Cloud Records</strong>
                <small class="text-muted-dm">Inspect, filter, update, or export your saved synthesis archives anytime.</small>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-3 border-top border-dm d-flex justify-content-between align-items-center">
          <small class="text-faint-dm">DocuMind Core · Minimalist Workspace</small>
          <span class="badge-dm success">System Operational</span>
        </div>
      </div>
    </div>

    <!-- Login Form Column -->
    <div class="col-lg-5 col-md-10">
      <div class="uiverse-card h-100 p-4 p-md-5 d-flex flex-column justify-content-center">
        <div class="mb-4">
          <h2 class="h3 fw-bold mb-1">Account Sign In</h2>
          <p class="text-muted-dm small">Access your workspace summaries and study modules.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert-dm danger mb-4" role="alert">
            <i data-feather="alert-circle" class="flex-shrink-0" style="width:16px;height:16px;"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="index.php" novalidate>
          <div class="mb-3">
            <label for="email" class="form-label small fw-semibold text-primary">Institutional Email</label>
            <input 
              type="email" 
              name="email" 
              id="email" 
              class="form-control bg-surface-dm border-dm text-primary" 
              placeholder="student@university.edu" 
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required
            >
            <div class="text-danger small mt-1 d-none" id="emailError"></div>
          </div>

          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label for="password" class="form-label small fw-semibold text-primary mb-0">Password</label>
            </div>
            <input 
              type="password" 
              name="password" 
              id="password" 
              class="form-control bg-surface-dm border-dm text-primary" 
              placeholder="••••••••" 
              required
            >
            <div class="text-danger small mt-1 d-none" id="passwordError"></div>
          </div>

          <button type="submit" class="btn-animated btn-primary-anim w-100 py-2 mb-3">
            <i data-feather="log-in" style="width:15px;height:15px;"></i>
            <span>Sign In to Workspace</span>
          </button>
        </form>

        <div class="text-center pt-3 border-top border-dm mt-2">
          <span class="text-muted-dm small">New student or faculty?</span>
          <a href="register.php" class="small fw-semibold ms-1">Create an account</a>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  form.addEventListener('submit', function (event) {
    let isValid = true;

    // Reset errors
    emailError.classList.add('d-none');
    passwordError.classList.add('d-none');
    emailInput.classList.remove('is-invalid');
    passwordInput.classList.remove('is-invalid');

    const emailVal = emailInput.value.trim();
    const passVal = passwordInput.value;

    if (!emailVal) {
      emailError.textContent = 'Please enter your email address.';
      emailError.classList.remove('d-none');
      emailInput.classList.add('is-invalid');
      isValid = false;
    } else if (!emailRegex.test(emailVal)) {
      emailError.textContent = 'Enter a syntactically valid email.';
      emailError.classList.remove('d-none');
      emailInput.classList.add('is-invalid');
      isValid = false;
    }

    if (!passVal) {
      passwordError.textContent = 'Please enter your password.';
      passwordError.classList.remove('d-none');
      passwordInput.classList.add('is-invalid');
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