<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

$error = "";

$subj_stmt = $conn->prepare("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$subj_stmt->execute();
$all_subjects = $subj_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);
    $email       = trim($_POST['email']);
    $role        = $_POST['role'];
    $password    = $_POST['password'];
    $confirm_pw  = $_POST['confirm_password'];

    $year_level = ($role == 'student') ? (int)$_POST['year_level'] : null;

    $selected_subjects = ($role == 'tutor' && isset($_POST['subjects'])) ? $_POST['subjects'] : [];
    $other_subject     = ($role == 'tutor') ? trim($_POST['other_subject'] ?? '') : '';

    //Validation 
    if (empty($first_name) || empty($middle_name) || empty($last_name)) {
        $error = "First, middle, and last name are required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else if ($role == 'student' && empty($year_level)) {
        $error = "Please select a year level.";
    } else if ($role == 'tutor' && empty($selected_subjects) && empty($other_subject)) {
        $error = "Please select at least one subject you teach.";
    } else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else if ($password != $confirm_pw) {
        $error = "Passwords do not match.";
    } else {

        // Check duplicate email
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $error = "An account with that email already exists.";
        } else {
            $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
            $full_name = $first_name . " " . $last_name;

            $stmt = $conn->prepare("
            INSERT INTO users (first_name, middle_name, last_name, email, password, role, year_level, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$first_name, $middle_name, $last_name, $email, $hashed_pw, $role, $year_level]);
            $new_user_id = $conn->lastInsertId();

            if ($role == 'tutor') {

                if ($other_subject != '') {
                    $dup = $conn->prepare("SELECT subject_id FROM subjects WHERE LOWER(subject_name) = LOWER(?)");
                    $dup->execute([$other_subject]);
                    $existing = $dup->fetch();

                    if ($existing) {
                        $selected_subjects[] = $existing['subject_id'];
                    } else {
                        $clean_name = ucwords(strtolower($other_subject));
                        $ins = $conn->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
                        $ins->execute([$clean_name]);
                        $selected_subjects[] = $conn->lastInsertId();
                    }
                }
                $link = $conn->prepare("INSERT IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
                foreach ($selected_subjects as $subject_id) {
                    $link->execute([$new_user_id, (int)$subject_id]);
                }
            }

            //Send verification email
            require_once '../classes/PHPMailer/src/PHPMailer.php';
            require_once '../classes/PHPMailer/src/SMTP.php';
            require_once '../classes/PHPMailer/src/Exception.php';

            $verify_token = bin2hex(random_bytes(32));

            $upd = $conn->prepare("UPDATE users SET verify_token = ? WHERE user_id = ?");
            $upd->execute([$verify_token, $new_user_id]);

            $verify_link = "http://localhost/PEER_TUTORING_TRACKER/account/verify.php?token=" . $verify_token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'peerturoring@gmail.com'; 
                $mail->Password   = 'pcdslgglcturvuwj';   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('your_email@gmail.com', 'Peer Tutoring Tracker');
                $mail->addAddress($email, $full_name);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Email — Peer Tutoring Tracker';
                $mail->Body    = "
                    <p>Hi <strong>{$full_name}</strong>, welcome to Peer Tutoring Tracker!</p>
                    <p>Please click the button below to verify your email address:</p>
                    <p style='margin:24px 0;'>
                        <a href='{$verify_link}' style='background:#2A9D8F;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:500;'>
                            Verify My Email
                        </a>
                    </p>
                    <p>If the button doesn't work, copy this link into your browser:<br>{$verify_link}</p>
                    <p>— Peer Tutoring Tracker</p>
                ";
                $mail->AltBody = "Verify your email here: $verify_link";
                $mail->send();
            } catch (Exception $e) {
            }

            header("Location: ../account/verify_pending.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/register.css"/>
</head>
<body>

  <aside class="panel-left" style="background-image: url('../assets/images/image.jpeg');">
    <div class="brand">
      <h1>Peer Tutoring<br>Tracker</h1>
      <p>Join our community of students and tutors. Learn smarter, teach better.</p>
      <div class="stats-row">
        <div class="stat-chip">
          <span class="stat-val">20+</span>
          <span class="stat-label">Subjects</span>
        </div>
        <div class="stat-chip">
          <span class="stat-val">100%</span>
          <span class="stat-label">Free</span>
        </div>
        <div class="stat-chip">
          <span class="stat-val">24/7</span>
          <span class="stat-label">Access</span>
        </div>
      </div>
    </div>

    <div class="features">
      <div class="feature">
        </div>
      </div>
    </div>

    <div class="panel-left-footer">© <?php echo date('Y'); ?> Peer Tutoring Tracker. All rights reserved.</div>
  </aside>

  <main class="panel-right">
    <div class="form-card">

      <div class="form-header">
        <h2>Create your account</h2>
        <p>Already have an account? <a href="login.php">Login here</a></p>
      </div>

      <?php if ($error != "") { ?>
        <div class="alert error show"><?php echo $error; ?></div>
      <?php } ?>

      <div class="role-selector">
        <div class="role-option">
          <input type="radio" name="role_display" id="role-student" value="student"
            <?php echo (!isset($_POST['role']) || $_POST['role'] == 'student') ? 'checked' : ''; ?>>
          <label for="role-student">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            <span>Student</span>
          </label>
        </div>
        <div class="role-option">
          <input type="radio" name="role_display" id="role-tutor" value="tutor"
            <?php echo (isset($_POST['role']) && $_POST['role'] == 'tutor') ? 'checked' : ''; ?>>
          <label for="role-tutor">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><line x1="18" y1="13" x2="18" y2="19"/><line x1="15" y1="16" x2="21" y2="16"/></svg>
            <span>Tutor</span>
          </label>
        </div>
      </div>

      <form action="register.php" method="POST" id="registerForm">
        <input type="hidden" name="role" id="hiddenRole"
          value="<?php echo (isset($_POST['role']) && $_POST['role'] == 'tutor') ? 'tutor' : 'student'; ?>">

        <div class="field-row" style="grid-template-columns: 1fr 1fr 1fr;">
          <div class="field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" placeholder="Juan"
              value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            <div class="error-msg" id="err-first_name">Required.</div>
          </div>
          <div class="field">
            <label for="middle_name">Middle Name</label>
            <input type="text" id="middle_name" name="middle_name" placeholder="Santos"
              value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
            <div class="error-msg" id="err-middle_name">Required.</div>
          </div>
          <div class="field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Dela Cruz"
              value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            <div class="error-msg" id="err-last_name">Required.</div>
          </div>
        </div>

        <!-- Email -->
        <div class="field">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="you@email.com"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          <div class="error-msg" id="err-email">Enter a valid email.</div>
        </div>

        <!-- Year Level — students only -->
        <div class="field" id="yearLevelField"
          style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'tutor') ? 'display:none;' : ''; ?>">
          <label for="year_level">Year Level</label>
          <select id="year_level" name="year_level">
            <option value="" disabled selected>Select year level</option>
            <option value="1" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '1') ? 'selected' : ''; ?>>Year 1</option>
            <option value="2" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '2') ? 'selected' : ''; ?>>Year 2</option>
            <option value="3" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '3') ? 'selected' : ''; ?>>Year 3</option>
            <option value="4" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '4') ? 'selected' : ''; ?>>Year 4</option>
          </select>
          <div class="error-msg" id="err-year_level">Please select your year level.</div>
        </div>

        <!-- Subjects — tutors only -->
        <div class="field" id="subjectsField"
          style="<?php echo (!isset($_POST['role']) || $_POST['role'] == 'student') ? 'display:none;' : ''; ?>">
          <label>Subjects You Teach</label>
          <div class="subject-grid">
            <?php foreach ($all_subjects as $subj) {
              $checked = (isset($_POST['subjects']) && in_array($subj['subject_id'], $_POST['subjects'])) ? 'checked' : '';
            ?>
              <label class="subject-option">
                <input type="checkbox" name="subjects[]" value="<?php echo $subj['subject_id']; ?>" <?php echo $checked; ?>>
                <span><?php echo htmlspecialchars($subj['subject_name']); ?></span>
              </label>
            <?php } ?>
          </div>

          <!-- Other subject -->
          <div class="other-subject-wrap" id="otherWrap" style="display:none;">
            <input type="text" id="other_subject" name="other_subject"
              placeholder="Enter subject name..."
              value="<?php echo isset($_POST['other_subject']) ? htmlspecialchars($_POST['other_subject']) : ''; ?>">
            <div class="other-subject-hint" id="otherHint"></div>
          </div>

          <!-- Other checkbox -->
          <label class="subject-option other-toggle" id="otherToggle">
            <input type="checkbox" id="otherCheck" onchange="toggleOther(this)">
            <span>Other</span>
          </label>

          <div class="error-msg" id="err-subjects">Please select at least one subject.</div>
        </div>

        <!-- Password -->
        <div class="field">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="Min. 8 characters">
            <button type="button" class="pw-toggle" id="togglePw">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="pw-strength">
            <div class="pw-strength-bar"><div class="pw-strength-fill" id="strengthFill"></div></div>
            <div class="pw-strength-label" id="strengthLabel">Enter a password</div>
          </div>
          <div class="error-msg" id="err-password">Min. 8 characters required.</div>
        </div>

        <!-- Confirm password -->
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password">
            <button type="button" class="pw-toggle" id="toggleCpw">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="error-msg" id="err-confirm_password">Passwords do not match.</div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnText">Create Account</span>
          <div class="spinner" id="spinner"></div>
        </button>

        <div class="divider">Your information is kept private and secure.</div>
      </form>
    </div>
  </main>

  <script>
    // Role toggle — show/hide year level and subjects
    document.querySelectorAll('input[name="role_display"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        document.getElementById('hiddenRole').value = this.value;

        if (this.value == 'tutor') {
          document.getElementById('yearLevelField').style.display = 'none';
          document.getElementById('year_level').value = '';
          document.getElementById('subjectsField').style.display = 'block';
        } else {
          document.getElementById('yearLevelField').style.display = 'block';
          document.getElementById('subjectsField').style.display = 'none';
        }
      });
    });

    function toggleOther(cb) {
      var wrap = document.getElementById('otherWrap');
      if (cb.checked) {
        wrap.style.display = 'block';
        document.getElementById('other_subject').focus();
      } else {
        wrap.style.display = 'none';
        document.getElementById('other_subject').value = '';
        document.getElementById('otherHint').textContent = '';
      }
    }

    var otherInput = document.getElementById('other_subject');
    if (otherInput) {
      otherInput.addEventListener('input', function() {
        var val = this.value.trim();
        var hint = document.getElementById('otherHint');
        if (val.length < 2) { hint.textContent = ''; hint.className = 'other-subject-hint'; return; }

        var existing = <?php echo json_encode(array_map(function($s) {
          return strtolower($s['subject_name']);
        }, $all_subjects)); ?>;

        if (existing.indexOf(val.toLowerCase()) !== -1) {
          hint.textContent = 'This subject already exists in our list — please select it above instead.';
          hint.className = 'other-subject-hint warn';
        } else {
          hint.textContent = '"' + val + '" will be added as a new subject.';
          hint.className = 'other-subject-hint ok';
        }
      });
    }

    function makeToggle(btnId, inputId) {
      var btn = document.getElementById(btnId);
      var inp = document.getElementById(inputId);
      btn.addEventListener('click', function() {
        if (inp.type === 'password') {
          inp.type = 'text';
          btn.querySelector('svg').innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
          inp.type = 'password';
          btn.querySelector('svg').innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
      });
    }
    makeToggle('togglePw', 'password');
    makeToggle('toggleCpw', 'confirm_password');

    // Password strength
    document.getElementById('password').addEventListener('input', function() {
      var pw = this.value;
      var score = 0;
      var fill  = document.getElementById('strengthFill');
      var label = document.getElementById('strengthLabel');

      if (pw.length >= 8)          score++;
      if (/[A-Z]/.test(pw))        score++;
      if (/[0-9]/.test(pw))        score++;
      if (/[^A-Za-z0-9]/.test(pw)) score++;

      if (pw.length == 0) {
        fill.style.width = '0%'; fill.style.background = 'transparent';
        label.textContent = 'Enter a password'; label.style.color = 'var(--muted)';
      } else if (score == 1) {
        fill.style.width = '25%'; fill.style.background = '#E24B4A';
        label.textContent = 'Weak'; label.style.color = 'var(--danger)';
      } else if (score == 2) {
        fill.style.width = '50%'; fill.style.background = '#E9C46A';
        label.textContent = 'Fair'; label.style.color = '#B8860B';
      } else if (score == 3) {
        fill.style.width = '75%'; fill.style.background = '#2A9D8F';
        label.textContent = 'Good'; label.style.color = 'var(--teal)';
      } else {
        fill.style.width = '100%'; fill.style.background = '#1A3A5C';
        label.textContent = 'Strong'; label.style.color = 'var(--navy)';
      }
    });

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      var hasError = false;
      var role = document.getElementById('hiddenRole').value;

      // Name fields
      var nameFields = ['first_name', 'middle_name', 'last_name'];
      for (var i = 0; i < nameFields.length; i++) {
        var f = document.getElementById(nameFields[i]);
        var err = document.getElementById('err-' + nameFields[i]);
        if (f.value.trim() == '') {
          f.classList.add('error'); err.style.display = 'block'; hasError = true;
        } else {
          f.classList.remove('error'); err.style.display = 'none';
        }
      }

      // Email
      var email = document.getElementById('email');
      var emailErr = document.getElementById('err-email');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        email.classList.add('error'); emailErr.style.display = 'block'; hasError = true;
      } else {
        email.classList.remove('error'); emailErr.style.display = 'none';
      }

      // Year level (students only)
      if (role == 'student') {
        var yr = document.getElementById('year_level');
        var yrErr = document.getElementById('err-year_level');
        if (!yr.value) {
          yrErr.style.display = 'block'; hasError = true;
        } else {
          yrErr.style.display = 'none';
        }
      }

      // Subjects (tutors only)
      if (role == 'tutor') {
        var checked   = document.querySelectorAll('input[name="subjects[]"]:checked').length;
        var otherVal  = document.getElementById('other_subject').value.trim();
        var subjErr   = document.getElementById('err-subjects');
        if (checked == 0 && otherVal == '') {
          subjErr.style.display = 'block'; hasError = true;
        } else {
          subjErr.style.display = 'none';
        }
      }

      // Password
      var pw = document.getElementById('password');
      var pwErr = document.getElementById('err-password');
      if (pw.value.length < 8) {
        pw.classList.add('error'); pwErr.style.display = 'block'; hasError = true;
      } else {
        pw.classList.remove('error'); pwErr.style.display = 'none';
      }

      // Confirm password
      var cpw = document.getElementById('confirm_password');
      var cpwErr = document.getElementById('err-confirm_password');
      if (cpw.value != pw.value) {
        cpw.classList.add('error'); cpwErr.style.display = 'block'; hasError = true;
      } else {
        cpw.classList.remove('error'); cpwErr.style.display = 'none';
      }

      if (hasError) { e.preventDefault(); return; }

      document.getElementById('btnText').style.display = 'none';
      document.getElementById('spinner').style.display = 'block';
      document.getElementById('submitBtn').disabled = true;
    });
  </script>
</body>
</html>
