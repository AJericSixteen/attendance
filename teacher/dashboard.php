<?php
require __DIR__ . '/../includes/auth.php';
require_login('teacher');
require __DIR__ . '/../config/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$stmt = $pdo->prepare('
    SELECT s.id, s.subject_code, s.subject_name, s.section, s.created_at,
           COUNT(CASE WHEN DATE(a.scanned_at) = CURDATE() THEN 1 END) AS scan_count
    FROM subjects s
    LEFT JOIN attendance a ON a.subject_id = s.id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
');
$stmt->execute([$_SESSION['user_id']]);
$subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Dashboard</title>
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
    <h1><i class="bi bi-mortarboard-fill"></i> Attendance System &mdash; Teacher</h1>
    <div class="user-info">
      <span class="role-badge">Teacher</span>
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
        <h2>My Subjects</h2>
        <p>Click a subject to open the QR scanner and take attendance.</p>
      </div>
      <button type="button" class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#newSubjectModal">
        + New Subject
      </button>
    </div>

    <?php if (empty($subjects)): ?>
      <div class="empty-state">
        <h3>No subjects yet</h3>
        <p>Click "New Subject" to create your first one.</p>
      </div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($subjects as $s): ?>
          <button type="button" class="entity-card subject-card" data-subject-id="<?= $s['id'] ?>">
            <div class="card-banner"><?= htmlspecialchars($s['subject_code']) ?></div>
            <div class="card-body">
              <div class="card-title"><?= htmlspecialchars($s['subject_name']) ?></div>
              <div class="card-section">Section: <?= htmlspecialchars($s['section']) ?></div>
              <div class="card-stat" data-scan-stat><?= (int) $s['scan_count'] ?> Present Today</div>
              <div class="card-desc">Created <?= date('M j, Y', strtotime($s['created_at'])) ?></div>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- New Subject Modal -->
  <div class="modal fade" id="newSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="actions/create_subject.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title">New Subject</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="subject_code">Subject Code</label>
              <input type="text" class="form-control" id="subject_code" name="subject_code" placeholder="e.g. IT101" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="subject_name">Subject Name</label>
              <input type="text" class="form-control" id="subject_name" name="subject_name" placeholder="e.g. Introduction to Programming" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="section">Section</label>
              <input type="text" class="form-control" id="section" name="section" placeholder="e.g. BSIT-3A" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary-gradient">Save &amp; Continue</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Attendance QR Scanner Modal -->
  <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="attendanceModalTitle">Attendance</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-5">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small">Camera</span>
                <button type="button" class="camera-toggle-btn" id="cameraToggleBtn" aria-label="Turn camera on">
                  <i class="bi bi-camera-video-off"></i>
                </button>
              </div>
              <div class="camera-frame" id="cameraFrame">
                <video id="cameraVideo" autoplay playsinline muted></video>
                <p class="camera-hint d-none" id="cameraError">Camera access is required. Please allow camera permission on your PC or phone.</p>
                <p class="camera-hint d-none" id="cameraOffMessage">Camera is off.</p>
              </div>
              <canvas id="cameraCanvas" class="d-none"></canvas>
              <div id="scanStatus" class="scan-status scan-status--info">Point a student's QR code at the camera.</div>
            </div>
            <div class="col-md-7">
              <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                <table class="table table-sm table-striped align-middle mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Student Number</th>
                      <th>Surname</th>
                    </tr>
                  </thead>
                  <tbody id="attendanceTableBody">
                    <tr><td colspan="3" class="text-muted">Loading...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="col-12 d-none" id="manualEntrySection">
              <div class="manual-entry-box">
                <div class="row g-2 align-items-end">
                  <div class="col-sm-5">
                    <label class="form-label" for="manualStudentNumber">Student Number</label>
                    <input type="text" class="form-control" id="manualStudentNumber" placeholder="06-2526-001648" inputmode="numeric" maxlength="14">
                  </div>
                  <div class="col-sm-5">
                    <label class="form-label" for="manualSurname">Surname</label>
                    <input type="text" class="form-control text-uppercase" id="manualSurname" placeholder="e.g. SERRANO">
                  </div>
                  <div class="col-sm-2">
                    <button type="button" class="btn btn-primary-gradient w-100" id="manualSaveBtn">Add</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-primary me-auto" id="manualEntryBtn">+ Manual Entry</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <a class="btn btn-success" id="exportBtn" href="#" target="_blank">Export to Excel</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
  <script src="../assets/js/theme.js"></script>
  <script src="../assets/js/teacher.js"></script>
</body>
</html>
