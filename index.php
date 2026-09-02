<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'teacher/dashboard.php'));
    exit;
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('attendance-theme');
        var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', theme);
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/theme.css">
  <link rel="stylesheet" href="css/login.css">
</head>
<body>

  <button type="button" class="theme-toggle login-theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
    <i class="bi bi-moon-stars-fill"></i>
    <i class="bi bi-sun-fill"></i>
  </button>

  <div class="background">
    <div class="shape shape-one"></div>
    <div class="shape shape-two"></div>
  </div>

  <main class="login-wrapper">
    <section class="login-card">
      <div class="login-header">
        <div class="logo">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h1>Welcome Back</h1>
        <p>Sign in to continue to your account</p>
      </div>

      <?php if ($error): ?>
        <div class="login-alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="login-form" action="login.php" method="POST">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <div class="input-wrapper">
            <i class="bi bi-person-fill input-icon"></i>
            <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <i class="bi bi-lock-fill input-icon"></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <button type="button" class="toggle-password" aria-label="Show password">Show</button>
          </div>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
      </form>
    </section>
  </main>

  <script src="assets/js/theme.js"></script>
  <script>
    const toggleBtn = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');

    toggleBtn.addEventListener('click', () => {
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      toggleBtn.textContent = isHidden ? 'Hide' : 'Show';
    });
  </script>

</body>
</html>
