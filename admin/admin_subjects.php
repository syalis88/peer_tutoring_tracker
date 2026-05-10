<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../account/login.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];
$success = "";
$error   = "";

//Add subject 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add') {
    $name = trim($_POST['subject_name']);
    if (empty($name)) {
        $error = "Subject name is required.";
    } else {
        $dup = $conn->prepare("SELECT subject_id FROM subjects WHERE LOWER(subject_name) = LOWER(?)");
        $dup->execute([$name]);
        if ($dup->rowCount() > 0) {
            $error = "That subject already exists.";
        } else {
            $clean = ucwords(strtolower($name));
            $stmt  = $conn->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
            $stmt->execute([$clean]);
            $success = "Subject \"$clean\" added.";
        }
    }
}

//Edit subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'edit') {
    $sid  = (int)$_POST['subject_id'];
    $name = trim($_POST['subject_name']);
    if (empty($name)) {
        $error = "Subject name cannot be empty.";
    } else {
        $dup = $conn->prepare("SELECT subject_id FROM subjects WHERE LOWER(subject_name) = LOWER(?) AND subject_id != ?");
        $dup->execute([$name, $sid]);
        if ($dup->rowCount() > 0) {
            $error = "A subject with that name already exists.";
        } else {
            $clean = ucwords(strtolower($name));
            $stmt  = $conn->prepare("UPDATE subjects SET subject_name = ? WHERE subject_id = ?");
            $stmt->execute([$clean, $sid]);
            $success = "Subject updated.";
        }
    }
}

//Delete subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'delete') {
    $sid = (int)$_POST['subject_id'];
    $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->execute([$sid]);
    $success = "Subject deleted.";
}

//Fetch all subjects with tutor count
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where  = $search != '' ? "WHERE LOWER(s.subject_name) LIKE LOWER(?)" : "";
$params = $search != '' ? ['%' . $search . '%'] : [];

$stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name,
           COUNT(DISTINCT ts.tutor_id) as tutor_count
    FROM subjects s
    LEFT JOIN tutor_subjects ts ON ts.subject_id = s.subject_id
    $where
    GROUP BY s.subject_id, s.subject_name
    ORDER BY s.subject_name ASC
");
$stmt->execute($params);
$subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Subjects — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/admin.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class=""></div>
      <span>Peer Tutoring Tracker Admin</span>
    </div>
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
      <a href="admin_users.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
      <a href="admin_sessions.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Sessions</span></a>
      <a href="admin_subjects.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Subjects</span></a>
      <a href="admin_feedback.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
      <a href="admin_reports.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Reports</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
          <span class="user-role">Administrator</span>
        </div>
      </div>
      <a href="#" onclick="document.getElementById('logoutModal').classList.add('open'); return false;" class="logout-btn" title="Sign out">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="page-title">Subjects</h1>
        <p class="page-sub">Manage the list of available subjects.</p>
      </div>
      <button type="button" class="btn-primary" onclick="document.getElementById('addModal').classList.add('open')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Subject
      </button>
    </div>

    <div class="page-content">
    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <!-- Search -->
    <form method="GET" action="admin_subjects.php">
      <div class="filter-bar">
        <input type="text" name="search" placeholder="Search subjects..."
          value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="action-btn">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <?php if ($search != '') { ?><a href="admin_subjects.php" class="action-btn">Clear</a><?php } ?>
        <div style="flex:1;"></div>
        <span style="font-size:13px; color:var(--muted);"><?php echo count($subjects); ?> subject<?php echo count($subjects) != 1 ? 's' : ''; ?></span>
      </div>
    </form>

    <!-- Subjects grid -->
    <div class="subjects-grid">
      <?php foreach ($subjects as $subj) { ?>
        <div class="subject-card">
          <div class="subject-card-info">
            <div class="subject-name"><?php echo htmlspecialchars($subj['subject_name']); ?></div>
            <div class="subject-meta"><?php echo $subj['tutor_count']; ?> tutor<?php echo $subj['tutor_count'] != 1 ? 's' : ''; ?></div>
          </div>
          <div class="subject-card-actions">
            <button type="button" class="action-btn"
              onclick="openEdit(<?php echo $subj['subject_id']; ?>, '<?php echo htmlspecialchars($subj['subject_name'], ENT_QUOTES); ?>')">
              Edit
            </button>
            <button type="button" class="action-btn danger"
              onclick="openDeleteSubj(<?php echo $subj['subject_id']; ?>, '<?php echo htmlspecialchars($subj['subject_name'], ENT_QUOTES); ?>')">
              Delete
            </button>
          </div>
        </div>
      <?php } ?>

      <?php if (count($subjects) == 0) { ?>
        <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--muted); font-size:14px;">
          No subjects found.
        </div>
      <?php } ?>
    </div>
    </div>
  </main>

  <!-- Add modal -->
  <div class="modal-overlay" id="addModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Add Subject</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form action="admin_subjects.php" method="POST">
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label>Subject Name</label>
          <input type="text" name="subject_name" placeholder="e.g. Trigonometry" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm">Add Subject</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit modal -->
  <div class="modal-overlay" id="editModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Edit Subject</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form action="admin_subjects.php" method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="subject_id" id="editSubjectId">
        <div class="field">
          <label>Subject Name</label>
          <input type="text" name="subject_name" id="editSubjectName" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete modal -->
  <div class="modal-overlay" id="deleteSubjModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Delete Subject</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('deleteSubjModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Delete <strong id="deleteSubjName"></strong>? This will remove it from all tutor profiles.
      </p>
      <form action="admin_subjects.php" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="subject_id" id="deleteSubjId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('deleteSubjModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm danger">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEdit(id, name) {
      document.getElementById('editSubjectId').value   = id;
      document.getElementById('editSubjectName').value = name;
      document.getElementById('editModal').classList.add('open');
    }
    function openDeleteSubj(id, name) {
      document.getElementById('deleteSubjId').value      = id;
      document.getElementById('deleteSubjName').textContent = name;
      document.getElementById('deleteSubjModal').classList.add('open');
    }
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
    });

    document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
  </script>
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Sign Out</h2>
      <button type="button" class="modal-close" onclick="document.getElementById('logoutModal').classList.remove('open')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
      Are you sure you want to sign out?
    </p>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancel</button>
      <a href="../account/logout.php" class="btn-confirm danger">Yes, Sign Out</a>
    </div>
  </div>
</div>
</body>
</html>