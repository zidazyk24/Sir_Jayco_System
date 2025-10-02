<?php
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  require_once __DIR__ . '/marketdb.php';

  // Require login
  if (empty($_SESSION['username'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Access Denied</title>
      <link rel="stylesheet" href="styles.css" />
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
    <?php exit; }

  // Flash
  $message = '';
  if (!empty($_SESSION['sales_flash'])) { $message = $_SESSION['sales_flash']; unset($_SESSION['sales_flash']); }


  // Aggregate totals
  $totals = db_query('SELECT COALESCE(SUM(total_items),0) AS items, COALESCE(SUM(total_amount),0) AS revenue FROM sales')->fetch();
  $grand_items = (int)$totals['items'];
  $grand_amount = (float)$totals['revenue'];

  // Detect optional columns paid_amount and change_amount
  $has_paid = false;
  $has_change = false;
  try {
    $col = db_query("SHOW COLUMNS FROM sales LIKE 'paid_amount'")->fetch();
    if ($col) { $has_paid = true; }
  } catch (Throwable $e) {}
  try {
    $col = db_query("SHOW COLUMNS FROM sales LIKE 'change_amount'")->fetch();
    if ($col) { $has_change = true; }
  } catch (Throwable $e) {}

  // Build SELECT based on available columns
  if ($has_paid && $has_change) {
    $sales_rows = db_query('SELECT id, sale_no, username, total_items, total_amount, paid_amount, change_amount, created_at FROM sales ORDER BY id DESC')->fetchAll();
  } elseif ($has_paid && !$has_change) {
    $sales_rows = db_query('SELECT id, sale_no, username, total_items, total_amount, paid_amount, NULL AS change_amount, created_at FROM sales ORDER BY id DESC')->fetchAll();
  } elseif (!$has_paid && $has_change) {
    $sales_rows = db_query('SELECT id, sale_no, username, total_items, total_amount, NULL AS paid_amount, change_amount, created_at FROM sales ORDER BY id DESC')->fetchAll();
  } else {
    $sales_rows = db_query('SELECT id, sale_no, username, total_items, total_amount, created_at FROM sales ORDER BY id DESC')->fetchAll();
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="sales.css" />
</head>
<body>
  <main class="page" role="main" aria-label="Sales">
    <div class="topbar" role="navigation" aria-label="Quick Navigation">
      <a class="nav-btn" href="inventory.php">Inventory</a>
      <a class="nav-btn" href="sales.php" aria-current="page">Sales</a>
      <a class="nav-btn" href="profit.php" aria-label="Go to Profit Calculator">Profit</a>
      <a class="nav-btn" href="logout.php" aria-label="Log out" style="margin-left:auto">Log out</a>
    </div>

    <div class="brand">
      <h1>Sales</h1>
      <p>Review all completed checkouts.</p>
    </div>

    <section class="summary" aria-label="Totals">
      <div class="summary-item">
        <div class="summary-label">Total Products Sold</div>
        <div class="summary-value"><?php echo number_format($grand_items); ?></div>
      </div>
      <div class="summary-item">
        <div class="summary-label">Total Revenue</div>
        <div class="summary-value">₱<?php echo number_format($grand_amount, 2); ?></div>
      </div>
    </section>

    <?php if (!empty($message)): ?>
      <div class="alert" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="panel sales-list">
      <h2 class="section-title">Sales Records</h2>
      <div class="table-wrapper">
        <table class="table" role="table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Cashier</th>
              <th>Items</th>
              <th>Total (₱)</th>
              <?php if ($has_paid): ?>
                <th>Paid (₱)</th>
              <?php endif; ?>
              <?php if ($has_change): ?>
                <th>Change (₱)</th>
              <?php endif; ?>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($sales_rows)): ?>
              <?php
                $colspan = 5 + ($has_paid ? 1 : 0) + ($has_change ? 1 : 0);
              ?>
              <tr><td colspan="<?php echo (int)$colspan; ?>" style="text-align:center; color: var(--muted);">No sales yet</td></tr>
            <?php else: ?>
              <?php foreach ($sales_rows as $sale): ?>
                <tr>
                  <td><?php echo htmlspecialchars($sale['created_at']); ?></td>
                  <td><?php echo htmlspecialchars($sale['username'] ?? ''); ?></td>
                  <td><?php echo number_format((int)$sale['total_items']); ?></td>
                  <td><?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                  <?php if ($has_paid): ?>
                    <td><?php echo number_format((float)($sale['paid_amount'] ?? 0), 2); ?></td>
                  <?php endif; ?>
                  <?php if ($has_change): ?>
                    <td><?php echo number_format((float)($sale['change_amount'] ?? 0), 2); ?></td>
                  <?php endif; ?>
                  <td>
                    <details>
                      <summary>View</summary>
                      <ul>
                        <?php
                          $items = db_query('SELECT product_name, quantity, unit_price FROM sale_items WHERE sale_id = ? ORDER BY id', [(int)$sale['id']])->fetchAll();
                          foreach ($items as $it):
                        ?>
                          <li><?php echo htmlspecialchars($it['product_name']); ?> — x<?php echo (int)$it['quantity']; ?> @ ₱<?php echo number_format((float)$it['unit_price'], 2); ?></li>
                        <?php endforeach; ?>
                        <?php if ($has_paid || $has_change): ?>
                          <li><strong>Total:</strong> ₱<?php echo number_format((float)$sale['total_amount'], 2); ?></li>
                          <?php if ($has_paid): ?><li><strong>Paid:</strong> ₱<?php echo number_format((float)($sale['paid_amount'] ?? 0), 2); ?></li><?php endif; ?>
                          <?php if ($has_change): ?><li><strong>Change:</strong> ₱<?php echo number_format((float)($sale['change_amount'] ?? 0), 2); ?></li><?php endif; ?>
                        <?php endif; ?>
                      </ul>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div class="footer">&copy; THE MINI-MARKET 2025</div>
  </main>
</body>
</html>
