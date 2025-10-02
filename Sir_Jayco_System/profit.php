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

  // Flash message (validation etc.)
  $message = '';
  // Load flash (PRG) if present
  if (!empty($_SESSION['profit_flash'])) {
    $message = $_SESSION['profit_flash'];
    unset($_SESSION['profit_flash']);
  }

  // Get total revenue from completed sales
  $totals = db_query('SELECT COALESCE(SUM(total_amount),0) AS revenue FROM sales')->fetch();
  $revenue = (float)($totals['revenue'] ?? 0);

  // Defaults for form values
  $tax = '';
  $rent = '';
  $electricity = '';
  $water = '';
  $other = '';

  // Calculated values
  $total_expenses = 0.0;
  $net_profit = $revenue;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calc_profit') {
    // Helper to normalize numeric inputs
    $num = function($key) {
      $val = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
      if ($val === '') return null; // treat empty as null for optionals
      // Replace comma with dot, then cast
      $val = str_replace(',', '.', $val);
      return is_numeric($val) ? (float)$val : null;
    };

    $taxN = $num('tax');
    $rentN = $num('rent');
    $elecN = $num('electricity');
    $waterN = $num('water');
    $otherN = $num('other');

    // Preserve entered values for sticky form
    $tax = isset($_POST['tax']) ? (string)$_POST['tax'] : '';
    $rent = isset($_POST['rent']) ? (string)$_POST['rent'] : '';
    $electricity = isset($_POST['electricity']) ? (string)$_POST['electricity'] : '';
    $water = isset($_POST['water']) ? (string)$_POST['water'] : '';
    $other = isset($_POST['other']) ? (string)$_POST['other'] : '';

    // Validate: tax is required and must be >= 0
    if ($taxN === null || $taxN < 0) {
      $message = 'Please enter a valid, non-negative Tax amount (required).';
    } else {
      // Optional fields: treat null/empty as 0; must be >= 0 when provided
      $rentN = ($rentN === null) ? 0 : max(0, $rentN);
      $elecN = ($elecN === null) ? 0 : max(0, $elecN);
      $waterN = ($waterN === null) ? 0 : max(0, $waterN);
      $otherN = ($otherN === null) ? 0 : max(0, $otherN);

      $total_expenses = (float)$taxN + (float)$rentN + (float)$elecN + (float)$waterN + (float)$otherN;
      $net_profit = max(0, $revenue - $total_expenses);

      // Persist a snapshot to profit_history_log (append-only)
      try {
        // Ensure table exists (idempotent)
        db_exec("CREATE TABLE IF NOT EXISTS profit_history_log (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          period_month DATE NOT NULL,
          revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          rent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          electricity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          water DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          other DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          total_expenses DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          net_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          created_by VARCHAR(100) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_period_month (period_month),
          KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $period = date('Y-m-01');
        $user = isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;

        // Always insert a new log row
        db_exec('INSERT INTO profit_history_log (period_month, revenue, tax, rent, electricity, water, other, total_expenses, net_profit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
          $period,
          $revenue,
          $taxN,
          $rentN,
          $elecN,
          $waterN,
          $otherN,
          $total_expenses,
          $net_profit,
          $user,
        ]);
        // After snapshot is saved, reset revenue by clearing sales (respecting FK constraints)
        try {
          // Delete dependent sale_items first, then sales
          db_exec('DELETE si FROM sale_items si INNER JOIN sales s ON si.sale_id = s.id');
          db_exec('DELETE FROM sales');
          $message = 'Snapshot saved for ' . date('F Y', strtotime($period)) . '. Total Revenue has been reset to 0.';
        } catch (Throwable $e2) {
          $message = 'Snapshot saved for ' . date('F Y', strtotime($period)) . '. However, failed to reset revenue: ' . $e2->getMessage();
        }
      } catch (Throwable $e) {
        // Non-fatal: just show message
        $message = 'Calculated. Saving snapshot failed: ' . $e->getMessage();
      }
    }
    // After handling POST (valid or invalid), redirect to avoid duplicate submits on refresh
    $_SESSION['profit_flash'] = $message;
    header('Location: profit.php');
    exit;
  }
  // Load recent profit history if table exists
  $history_rows = [];
  $history_error = '';
  try {
    // Check if profit_history_log table exists
    $exists = db_query("SHOW TABLES LIKE 'profit_history_log'")->fetch();
    if ($exists) {
      $history_rows = db_query(
        "SELECT period_month, revenue, tax, rent, electricity, water, other, total_expenses, net_profit, created_by, created_at\n         FROM profit_history_log\n         ORDER BY created_at DESC\n         LIMIT 12"
      )->fetchAll();
    }
  } catch (Throwable $e) {
    $history_error = 'Unable to load profit history.';
  }
  // On initial GET (non-POST) load, prefill latest expenses so Net Profit reflects Revenue minus Expenses by default
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($history_rows)) {
    $latest = $history_rows[0];
    // Show the previously calculated net profit from the last snapshot
    $net_profit = (float)($latest['net_profit'] ?? 0);
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profit</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="profit.css" />
  <script>
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) { window.location.reload(); }
    });
  </script>
  </head>
<body>
  <main class="page" role="main" aria-label="Profit Calculator">
    <?php if (!empty($message)): ?>
      <div id="toast-alert" class="toast-alert" role="alert" aria-live="assertive">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>
    <div class="topbar" role="navigation" aria-label="Quick Navigation">
      <a class="nav-btn" href="inventory.php">Inventory</a>
      <a class="nav-btn" href="sales.php">Sales</a>
      <a class="nav-btn" href="profit.php" aria-label="Go to Profit Calculator" aria-current="page">Profit</a>
      <a class="nav-btn" href="logout.php" aria-label="Log out" style="margin-left:auto">Log out</a>
    </div>

    <div class="brand">
      <h1>Profit</h1>
      <p>Enter expenses to calculate net profit from total revenue.</p>
    </div>

    <section class="two-col" aria-label="Profit Content">
      <div class="col left">
        <section class="panel" aria-label="Revenue and Result">
          <h2 class="section-title">Summary</h2>
          <div class="summary">
            <div class="summary-item">
              <div class="summary-label">Total Revenue</div>
              <div class="summary-value">₱<?php echo number_format($revenue, 2); ?></div>
            </div>
            <div class="summary-item">
              <div class="summary-label">Total Expenses</div>
              <div class="summary-value">₱<?php echo number_format($total_expenses, 2); ?></div>
            </div>
          </div>
          <div class="summary" style="margin-top:12px;">
            <div class="summary-item" style="grid-column: span 2;">
              <div class="summary-label">Last Calculated Net Profit</div>
              <div class="summary-value">₱<?php echo number_format($net_profit, 2); ?></div>
            </div>
          </div>
          <div class="actions" style="margin-top:12px; display:flex; gap:8px; justify-content:center;">
            <button type="button" class="btn-primary" onclick="document.getElementById('profit-form')?.submit()">Calculate &amp; Save Snapshot</button>
            <a href="#profit-history-modal" id="open-history" class="nav-btn" style="text-decoration:none">View Profit History</a>
          </div>
        </section>
      </div>

      <div class="col right">
        <section class="panel" aria-label="Enter Expenses">
          <h2 class="section-title">Expenses</h2>
          <form id="profit-form" method="POST" action="">
            <input type="hidden" name="action" value="calc_profit" />
            <div class="add-form grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
              <div class="field" style="grid-column: span 2;">
                <label class="label" for="tax">Tax (required)</label>
                <input class="input" type="number" id="tax" name="tax" min="0" step="0.01" placeholder="e.g., 500.00" required value="<?php echo htmlspecialchars($tax); ?>" />
              </div>
              <div class="field">
                <label class="label" for="rent">Rent (optional)</label>
                <input class="input" type="number" id="rent" name="rent" min="0" step="0.01" placeholder="e.g., 1000.00" value="<?php echo htmlspecialchars($rent); ?>" />
              </div>
              <div class="field">
                <label class="label" for="electricity">Electricity (optional)</label>
                <input class="input" type="number" id="electricity" name="electricity" min="0" step="0.01" placeholder="e.g., 800.00" value="<?php echo htmlspecialchars($electricity); ?>" />
              </div>
              <div class="field">
                <label class="label" for="water">Water (optional)</label>
                <input class="input" type="number" id="water" name="water" min="0" step="0.01" placeholder="e.g., 300.00" value="<?php echo htmlspecialchars($water); ?>" />
              </div>
              <div class="field">
                <label class="label" for="other">Other (optional)</label>
                <input class="input" type="number" id="other" name="other" min="0" step="0.01" placeholder="e.g., 200.00" value="<?php echo htmlspecialchars($other); ?>" />
              </div>
            </div>
            
          </form>
        </section>
      </div>
    </section>

    <!-- Profit History Modal -->
    <div id="profit-history-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="history-title" aria-hidden="true" style="display:none;">
      <div class="modal" role="document">
        <div class="modal-header">
          <h2 class="section-title" id="history-title">Profit History</h2>
          <button type="button" class="modal-close" id="close-history" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <?php if (!empty($history_error)): ?>
            <div class="alert" role="alert"><?php echo htmlspecialchars($history_error); ?></div>
          <?php endif; ?>
          <?php if (empty($history_rows)): ?>
            <div class="alert" role="status">No history yet. Create a monthly snapshot by recording your expenses and saving them.</div>
          <?php else: ?>
            <div class="table-wrapper">
              <table class="table" role="table">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th>Revenue (₱)</th>
                    <th>Tax (₱)</th>
                    <th>Rent (₱)</th>
                    <th>Electricity (₱)</th>
                    <th>Water (₱)</th>
                    <th>Other (₱)</th>
                    <th>Total Expenses (₱)</th>
                    <th>Net Profit (₱)</th>
                    <th>Saved By</th>
                    <th>Saved At</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($history_rows as $h): ?>
                    <tr>
                      <td><?php echo htmlspecialchars(date('Y-m', strtotime($h['period_month']))); ?></td>
                      <td><?php echo number_format((float)$h['revenue'], 2); ?></td>
                      <td><?php echo number_format((float)$h['tax'], 2); ?></td>
                      <td><?php echo number_format((float)$h['rent'], 2); ?></td>
                      <td><?php echo number_format((float)$h['electricity'], 2); ?></td>
                      <td><?php echo number_format((float)$h['water'], 2); ?></td>
                      <td><?php echo number_format((float)$h['other'], 2); ?></td>
                      <td><?php echo number_format((float)$h['total_expenses'], 2); ?></td>
                      <td><?php echo number_format((float)$h['net_profit'], 2); ?></td>
                      <td><?php echo htmlspecialchars($h['created_by'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($h['created_at'] ?? ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="footer">&copy; THE MINI-MARKET 2025</div>
  </main>
  <script>
    (function(){
      const openBtn = document.getElementById('open-history');
      const modal = document.getElementById('profit-history-modal');
      const closeBtn = document.getElementById('close-history');
      function openModal(e){ if(e) e.preventDefault(); modal.style.display = 'flex'; modal.setAttribute('aria-hidden','false'); }
      function closeModal(){ modal.style.display = 'none'; modal.setAttribute('aria-hidden','true'); }
      openBtn && openBtn.addEventListener('click', openModal);
      closeBtn && closeBtn.addEventListener('click', closeModal);
      modal && modal.addEventListener('click', function(ev){ if (ev.target === modal) closeModal(); });
      document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape' && modal && modal.style.display !== 'none') closeModal(); });

      // Auto-dismiss toast alert like Inventory
      const toast = document.getElementById('toast-alert');
      if (toast) {
        setTimeout(() => {
          toast.classList.add('hide');
          setTimeout(() => { toast.remove(); }, 300);
        }, 3000);
      }
    })();
  </script>
</body>
</html>
