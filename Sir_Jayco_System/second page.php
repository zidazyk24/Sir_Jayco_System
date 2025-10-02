<?php
  // Start session to optionally greet by username if set previously
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  // Prevent caching so Back button reloads the page
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');
  // Security: Only allow access if logged in
  $loggedIn = !empty($_SESSION['username']);
  if (!$loggedIn) {
    // Not logged in: show message and stop further output
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Access Denied</title>
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
      <main class="card" role="alert" aria-label="Access Restricted">
        <div class="brand">
          <h1>ACCESS RESTRICTED</h1>
          <p>You're not allowed to proceed. Please log in first.</p>
        </div>
        <div class="actions">
          <a href="index.php" style="text-decoration:none">
            <button class="btn-primary" type="button">Go to Login</button>
          </a>
        </div>
        <div class="footer">&copy; THE MINI-MARKET 2025</div>
      </main>
    </body>
    </html>
    <?php
    exit;
  }
  $name = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sir Jayco System - Actions</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="second page.css" />
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
  <main class="card" role="main" aria-label="Action Selection">
    <div class="brand welcome">
      <h1>WELCOME, <?php echo htmlspecialchars($name); ?>!</h1>
      <p>What is the action for today?</p>
    </div>

    <div class="actions-grid" role="navigation" aria-label="Primary Actions">
      <a href="inventory.php" style="text-decoration:none">
        <button class="btn-secondary" type="button" aria-label="Go to Inventory">INVENTORY</button>
      </a>
      <a href="sales.php" style="text-decoration:none">
        <button class="btn-secondary" type="button" aria-label="Go to Sales">SALES</button>
      </a>
      <a href="profit.php" style="text-decoration:none">
        <button class="btn-secondary" type="button" aria-label="Go to Profit Calculator">SEE PROFIT</button>
      </a>
      <a href="logout.php" style="text-decoration:none">
        <button class="btn-secondary" type="button" aria-label="Log out of your account">LOG OUT</button>
      </a>
    </div>

    <div class="footer">&copy; THE MINI-MARKET 2025</div>
  </main>
</body>
</html>
