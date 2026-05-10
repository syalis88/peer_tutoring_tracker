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

//Delete user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'delete') {
    $uid = (int)$_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
    $stmt->execute([$uid]);
    $success = "User deleted successfully.";
}

//Suspend / Unsuspend
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'suspend') {
    $uid = (int)$_POST['user_id'];
    $check = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $check->execute([$uid]);
    $row = $check->fetch();
    if ($row) {
        if ($row['status'] === 'suspended') {
            $new_status = 'active';
            $success    = "User unsuspended.";
        } else {
            $new_status = 'suspended';
            $success    = "User suspended.";
        }
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->execute([$new_status, $uid]);
    }
}

//Filters
$filter_role   = isset($_GET['role'])   ? $_GET['role']         : 'all';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$per_page     = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

$where  = "WHERE role != 'admin'";
$params = [];

if ($filter_role != 'all') {
    $where .= " AND role = ?";
    $params[] = $filter_role;
}
if ($filter_search != '') {
    $where .= " AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ?)";
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users $where");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetch()['total'];
$total_pages = ceil($total_rows / $per_page);

$stmt = $conn->prepare("
    SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as name,
           u.status, u.email, u.role, u.year_level, u.created_at,
           COUNT(DISTINCT s.session_id) as session_count
    FROM users u
    LEFT JOIN sessions s ON (s.student_id = u.user_id OR s.tutor_id = u.user_id)
    $where
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Users — Admin</title>
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
      <a href="admin_users.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
      <a href="admin_sessions.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Sessions</span></a>
      <a href="admin_subjects.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Subjects</span></a>
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
        <h1 class="page-title">Users</h1>
        <p class="page-sub">Manage student feedback.</p>
      </div>
    </div>

    <div class="page-content">

      <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
      <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

      <form method="GET" action="admin_users.php" id="filterForm">
        <div class="filter-bar">
          <input type="text" name="search" placeholder="Search by name or email..."
            value="<?php echo htmlspecialchars($filter_search); ?>">
          <select name="role" onchange="document.getElementById('filterForm').submit()">
            <option value="all"     <?php echo $filter_role == 'all'     ? 'selected' : ''; ?>>All Roles</option>
            <option value="student" <?php echo $filter_role == 'student' ? 'selected' : ''; ?>>Students</option>
            <option value="tutor"   <?php echo $filter_role == 'tutor'   ? 'selected' : ''; ?>>Tutors</option>
          </select>
          <button type="submit" class="action-btn">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search
          </button>
          <?php if ($filter_search != '' || $filter_role != 'all') { ?>
            <a href="admin_users.php" class="action-btn">Clear</a>
          <?php } ?>
          <div style="flex:1;"></div>
          <span style="font-size:13px; color:var(--muted);"><?php echo $total_rows; ?> user<?php echo $total_rows != 1 ? 's' : ''; ?></span>
        </div>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Sessions</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($users) == 0) { ?>
              <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--muted); font-size:14px;">No users found.</td></tr>
            <?php } else { ?>
              <?php foreach ($users as $u) {
                $suspended    = $u['status'] === 'suspended';
                $display_name = $u['name'];
              ?>
                <tr class="<?php echo $suspended ? 'suspended-row' : ''; ?>">
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar-sm"><?php echo strtoupper(substr($display_name, 0, 1)); ?></div>
                      <div>
                        <div class="td-name"><?php echo htmlspecialchars($display_name); ?></div>
                        <?php if ($suspended) { ?><div class="suspended-tag">Suspended</div><?php } ?>
                      </div>
                    </div>
                  </td>
                  <td><div class="td-meta"><?php echo htmlspecialchars($u['email']); ?></div></td>
                  <td>
                    <?php if ($u['role'] == 'tutor') { ?>
                      <span class="badge badge-gold">Tutor</span>
                    <?php } else { ?>
                      <span class="badge badge-teal">Student</span>
                    <?php } ?>
                  </td>
                  <td><?php echo $u['session_count']; ?></td>
                  <td><div class="td-meta"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></div></td>
                  <td>
                    <div class="actions-cell">
                      <?php if ($suspended) { ?>
                        <button type="button" class="action-btn success"
                          onclick="openUnsuspend(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($display_name, ENT_QUOTES); ?>')">
                          Unsuspend
                        </button>
                      <?php } else { ?>
                        <button type="button" class="action-btn warning"
                          onclick="openSuspend(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($display_name, ENT_QUOTES); ?>')">
                          Suspend
                        </button>
                      <?php } ?>
                      <button type="button" class="action-btn danger"
                        onclick="openDelete(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($display_name, ENT_QUOTES); ?>')">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              <?php } ?>
            <?php } ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1) { ?>
          <div class="pagination">
            <span>Showing <?php echo min($offset + 1, $total_rows); ?>–<?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?></span>
            <div class="page-btns">
              <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($filter_role); ?>&search=<?php echo urlencode($filter_search); ?>"
                  class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>

    </div>
  </main>

  <!-- Suspend confirm modal -->
  <div class="modal-overlay" id="suspendModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Suspend User</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('suspendModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Suspend <strong id="suspendUserName"></strong>? They will not be able to log in until unsuspended. You can undo this at any time.
      </p>
      <form action="admin_users.php" method="POST">
        <input type="hidden" name="action" value="suspend">
        <input type="hidden" name="user_id" id="suspendUserId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('suspendModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm danger">Yes, Suspend</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Unsuspend confirm modal -->
  <div class="modal-overlay" id="unsuspendModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Unsuspend User</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('unsuspendModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Restore access for <strong id="unsuspendUserName"></strong>? They will be able to log in again immediately.
      </p>
      <form action="admin_users.php" method="POST">
        <input type="hidden" name="action" value="suspend">
        <input type="hidden" name="user_id" id="unsuspendUserId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('unsuspendModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm">Yes, Unsuspend</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete confirm modal -->
  <div class="modal-overlay" id="deleteModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Delete User</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('deleteModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Permanently delete <strong id="deleteUserName"></strong>? This will also delete all their sessions and feedback. This cannot be undone.
      </p>
      <form action="admin_users.php" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm danger">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openSuspend(id, name) {
      document.getElementById('suspendUserId').value = id;
      document.getElementById('suspendUserName').textContent = name;
      document.getElementById('suspendModal').classList.add('open');
    }
    function openUnsuspend(id, name) {
      document.getElementById('unsuspendUserId').value = id;
      document.getElementById('unsuspendUserName').textContent = name;
      document.getElementById('unsuspendModal').classList.add('open');
    }
    function openDelete(id, name) {
      document.getElementById('deleteUserId').value = id;
      document.getElementById('deleteUserName').textContent = name;
      document.getElementById('deleteModal').classList.add('open');
    }
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
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