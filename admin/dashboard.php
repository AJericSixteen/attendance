<?php
require __DIR__ . '/../includes/auth.php';
require_login('admin');
require __DIR__ . '/../config/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$teachers = $pdo->query('SELECT id, full_name, username, email, is_active, created_at FROM users WHERE role = "teacher" ORDER BY created_at DESC')->fetchAll();
$reopenTeacherId = $_SESSION['reopen_teacher_id'] ?? null;
unset($_SESSION['reopen_teacher_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('attendance-theme');
        var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', theme);
      } catch (e) {}
    })();
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/theme.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

  <header class="topbar">
    <h1><i class="bi bi-mortarboard-fill"></i> Attendance System &mdash; Admin</h1>
    <div class="user-info">
      <span class="role-badge">Admin</span>
      <span><?= htmlspecialchars($_SESSION['full_name']) ?></span>
      <button type="button" class="theme-toggle theme-toggle--on-gradient" data-theme-toggle aria-label="Toggle dark mode">
        <i class="bi bi-moon-stars-fill"></i>
        <i class="bi bi-sun-fill"></i>
      </button>
      <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
  </header>

  <?php if ($flash): ?>
    <div class="alert-flash">
      <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    </div>
  <?php endif; ?>

  <main class="page-content">
    <div class="content-header">
      <div>
        <h2>Teacher Accounts</h2>
        <p>Create and manage teacher accounts. Admins can only create teacher accounts.</p>
      </div>
      <button type="button" class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        + New Teacher
      </button>
    </div>

    <?php if (empty($teachers)): ?>
      <div class="empty-state">
        <h3>No teacher accounts yet</h3>
        <p>Click "New Teacher" to create the first one.</p>
      </div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($teachers as $t): ?>
          <button type="button" class="entity-card teacher-card<?= $t['is_active'] ? '' : ' entity-card--inactive' ?>"
                  data-teacher-id="<?= $t['id'] ?>"
                  data-full-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                  data-username="<?= htmlspecialchars($t['username'], ENT_QUOTES) ?>"
                  data-email="<?= htmlspecialchars($t['email'], ENT_QUOTES) ?>"
                  data-active="<?= $t['is_active'] ? '1' : '0' ?>">
            <div class="card-banner"><?= strtoupper(substr($t['full_name'], 0, 1)) ?></div>
            <div class="card-body">
              <span class="status-badge <?= $t['is_active'] ? 'status-badge--active' : 'status-badge--inactive' ?>">
                <?= $t['is_active'] ? 'Active' : 'Deactivated' ?>
              </span>
              <div class="card-title"><?= htmlspecialchars($t['full_name']) ?></div>
              <div class="card-username">@<?= htmlspecialchars($t['username']) ?></div>
              <div class="card-desc">
                <?= htmlspecialchars($t['email']) ?><br>
                Added <?= date('M j, Y', strtotime($t['created_at'])) ?>
              </div>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Add Teacher Modal -->
  <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="actions/create_teacher.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title">New Teacher Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="full_name">Full Name</label>
              <input type="text" class="form-control" id="full_name" name="full_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <input type="password" class="form-control" id="password" name="password" minlength="6" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary-gradient">Create Teacher</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Manage Teacher Modal -->
  <div class="modal fade" id="manageTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="manageTeacherName">Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-1" id="manageTeacherUsername"></p>
          <p class="mb-2" id="manageTeacherEmail"></p>
          <span class="status-badge" id="manageTeacherStatusBadge"></span>

          <hr>

          <h6>Change Password</h6>
          <form action="actions/change_teacher_password.php" method="POST" class="mb-4">
            <input type="hidden" name="teacher_id" id="changePasswordTeacherId">
            <div class="mb-2">
              <label class="form-label" for="new_password">New Password</label>
              <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
            </div>
            <div class="mb-2">
              <label class="form-label" for="confirm_password">Confirm Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary-gradient btn-sm">Update Password</button>
          </form>

          <hr>

          <h6>Account Status</h6>
          <div id="activateSection" class="d-none">
            <form action="actions/toggle_teacher_status.php" method="POST">
              <input type="hidden" name="teacher_id" id="activateTeacherId">
              <input type="hidden" name="new_status" value="activate">
              <button type="submit" class="btn btn-success btn-sm">Activate Account</button>
            </form>
          </div>
          <div id="deactivateSection">
            <button type="button" class="btn btn-outline-danger btn-sm" id="showDeactivateFormBtn">Deactivate Account</button>
            <form action="actions/toggle_teacher_status.php" method="POST" id="deactivateForm" class="d-none mt-3">
              <input type="hidden" name="teacher_id" id="deactivateTeacherId">
              <input type="hidden" name="new_status" value="deactivate">
              <div class="mb-2">
                <label class="form-label" for="admin_password">Enter your admin password to confirm</label>
                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
              </div>
              <button type="submit" class="btn btn-danger btn-sm">Confirm Deactivation</button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelDeactivateBtn">Cancel</button>
            </form>
          </div>

          <div id="deleteZone" class="d-none">
            <hr>
            <h6 class="text-danger">Danger Zone</h6>
            <button type="button" class="btn btn-danger btn-sm" id="showDeleteFormBtn">Delete Account</button>
            <form action="actions/delete_teacher.php" method="POST" id="deleteForm" class="d-none mt-3">
              <input type="hidden" name="teacher_id" id="deleteTeacherId">
              <div class="alert alert-danger py-2 small">
                This will permanently delete this teacher and <strong>all subjects and attendance records</strong> under them. This cannot be undone.
              </div>
              <div class="mb-2">
                <label class="form-label" for="delete_admin_password">Enter your admin password to confirm</label>
                <input type="password" class="form-control" id="delete_admin_password" name="admin_password" required>
              </div>
              <button type="submit" class="btn btn-danger btn-sm">Permanently Delete</button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelDeleteBtn">Cancel</button>
            </form>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/theme.js"></script>
  <script>
    window.REOPEN_TEACHER_ID = <?= $reopenTeacherId !== null ? (int) $reopenTeacherId : 'null' ?>;
  </script>
  <script src="../assets/js/admin.js"></script>
  <?php if (!empty($_SESSION['reopen_modal'])): unset($_SESSION['reopen_modal']); ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      new bootstrap.Modal(document.getElementById('addTeacherModal')).show();
    });
  </script>
  <?php endif; ?>
</body>
</html>
