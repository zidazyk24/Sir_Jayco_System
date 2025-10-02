<?php
  // Basic login handling with 3 attempts and 2-minute lockout
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // Prevent caching so Back button reloads the page
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');

  // If user navigates back to the login page (GET), treat it as a logout
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['username'])) {
    unset($_SESSION['username']);
    // Regenerate session ID when privilege level changes
    session_regenerate_id(true);
  }

  // Hardcoded valid credentials
  $VALID_USERNAME = 'admin';
  $VALID_PASSWORD = 'password123';

  // Initialize tracking vars
  if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
  }
  if (!isset($_SESSION['lockout_until'])) {
    $_SESSION['lockout_until'] = 0;
  }

  $error_message = '';
  $lockout_remaining = 0;

  // Check lockout state
  $now = time();
  if ($_SESSION['lockout_until'] > $now) {
    $lockout_remaining = $_SESSION['lockout_until'] - $now;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    // If currently locked out
    if ($_SESSION['lockout_until'] > time()) {
      $lockout_remaining = $_SESSION['lockout_until'] - time();
      $error_message = 'Too many failed attempts. Please wait ' . $lockout_remaining . ' seconds and try again.';
    } else {
      // Not locked out; validate credentials
      if ($username === $VALID_USERNAME && $password === $VALID_PASSWORD) {
        // Success: reset counters and redirect
        // Security: regenerate session ID on successful login
        session_regenerate_id(true);
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lockout_until'] = 0;
        $_SESSION['username'] = $username;
        header('Location: second%20page.php');
        exit;
      } else {
        // Failed attempt
        $_SESSION['login_attempts'] = (int)$_SESSION['login_attempts'] + 1;
        $remaining = 3 - $_SESSION['login_attempts'];
        if ($remaining <= 0) {
          // Lock out for 2 minutes
          $_SESSION['lockout_until'] = time() + 120;
          $_SESSION['login_attempts'] = 0; // reset counter after lockout starts
          $lockout_remaining = 120;
          $error_message = 'Too many failed attempts. You are locked out for 120 seconds.';
        } else {
          $error_message = 'Invalid username or password. Attempts remaining: ' . $remaining . '.';
        }
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sir Jayco System - Login</title>
    <link rel="stylesheet" href="styles.css" />
    <script>
      // Force reload when navigating back/forward from bfcache
      window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
          window.location.reload();
        }
      });
    </script>
</head>
<body>
    <main class="card" role="main" aria-label="Login Form">
        <div class="brand">
            <h1>THE MINI-MARKET</h1>
            <p>Please log in to continue</p>
        </div>

        <!-- Login-only form (no signup link) -->
        <form method="POST" action="">
            <div class="field">
                <label class="label" for="username">Username</label>
                <input class="input" type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';?>" />
            </div>

            <div class="field">
                <label class="label" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password" />
            </div>

            <?php if (!empty($error_message)) : ?>
            <div class="field" role="alert" aria-live="polite" style="color: var(--error); margin-top: 8px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="actions">
                <button type="submit" class="btn-primary">Log In</button>
            </div>
        </form>

        <div class="footer">&copy; THE MINI-MARKET 2025</div>
    </main>
</body>
</html>
