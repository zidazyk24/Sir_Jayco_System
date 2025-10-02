<?php
  // Session and access control
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  // DB helper
  require_once __DIR__ . '/marketdb.php';
  // Prevent caching so Back button reloads the page
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');

  // Only allow access if logged in
  if (empty($_SESSION['username'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Access Denied</title>
      <link rel="stylesheet" href="styles.css" />
      <script>
        window.addEventListener('pageshow', function (event) {
          if (event.persisted) window.location.reload();
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

  $message = '';
  $isEditing = false;
  $editIndex = -1;
  $form_product = '';
  $form_quantity = '';
  $form_price = '';

  // Flash message support (for PRG pattern)
  if (!empty($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
  }

  // Handle actions: add, update, delete
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add' || $action === 'update') {
      $product = isset($_POST['product']) ? trim($_POST['product']) : '';
      $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
      $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;

      if ($product === '' || $qty <= 0 || $price < 0) {
        $message = 'Please provide a valid product name, quantity (>0), and price (>=0).';
        // Keep form values if invalid
        $form_product = $product;
        $form_quantity = $qty;
        $form_price = $price;
        $isEditing = ($action === 'update');
        if ($isEditing && isset($_POST['id'])) { $editIndex = (int)$_POST['id']; }
      } else {
        if ($action === 'add') {
          // Merge by same name + price -> increase qty; else insert new
          $existing = db_query('SELECT id FROM products WHERE name = ? AND unit_price = ? LIMIT 1', [$product, $price])->fetch();
          if ($existing) {
            db_exec('UPDATE products SET qty_on_hand = qty_on_hand + ? WHERE id = ?', [$qty, (int)$existing['id']]);
          } else {
            db_exec('INSERT INTO products (name, unit_price, qty_on_hand) VALUES (?, ?, ?)', [$product, $price, $qty]);
          }
          $_SESSION['flash'] = 'Product added successfully!';
          header('Location: inventory.php');
          exit;
        } else { // update existing by id
          if (isset($_POST['id'])) {
            $pid = (int)$_POST['id'];
            db_exec('UPDATE products SET name = ?, unit_price = ?, qty_on_hand = ? WHERE id = ?', [$product, $price, $qty, $pid]);
            $_SESSION['flash'] = 'Product updated successfully!';
            header('Location: inventory.php');
            exit;
          }
        }
      }
    } elseif ($action === 'delete' && isset($_POST['index'])) {
      // Maintain compatibility with existing form field name 'index'
      $pid = (int)$_POST['index'];
      db_exec('DELETE FROM products WHERE id = ?', [$pid]);
      $_SESSION['flash'] = 'Product deleted.';
      header('Location: inventory.php');
      exit;
    }
  }

  // If editing via GET param, prefill form
  if (isset($_GET['edit'])) {
    $editIndex = (int)$_GET['edit'];
    $row = db_query('SELECT id, name, qty_on_hand, unit_price FROM products WHERE id = ? LIMIT 1', [$editIndex])->fetch();
    if ($row) {
      $isEditing = true;
      $form_product = $row['name'];
      $form_quantity = (int)$row['qty_on_hand'];
      $form_price = (float)$row['unit_price'];
    }
  }

  // Compute totals
  $totals = db_query('SELECT COUNT(*) AS cnt, COALESCE(SUM(qty_on_hand * unit_price),0) AS total_val FROM products')->fetch();
  $product_count = (int)$totals['cnt'];
  $total_value = (float)$totals['total_val'];

  // Load list
  $product_rows = db_query('SELECT id, name, qty_on_hand, unit_price FROM products ORDER BY name ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="inventory.css" />
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
  <main class="page" role="main" aria-label="Inventory">
    <?php if (!empty($message)): ?>
      <div id="toast-alert" class="toast-alert" role="alert" aria-live="assertive">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>
    <div class="topbar" role="navigation" aria-label="Quick Navigation">
      <a class="nav-btn" href="inventory.php" aria-label="Go to Inventory" aria-current="page">Inventory</a>
      <a class="nav-btn" href="sales.php" aria-label="Go to Sales">Sales</a>
      <a class="nav-btn" href="profit.php" aria-label="Go to Profit Calculator">Profit</a>
      <a class="nav-btn" href="logout.php" aria-label="Log out" style="margin-left:auto">Log out</a>
    </div>
    <div class="brand">
      <h1>Inventory</h1>
      <p>Manage your products, quantities, and prices.</p>
    </div>

    <section class="two-col" aria-label="Inventory Content">
      <div class="col left">
        <div class="products-panel panel" aria-label="Products Panel">
          <section class="list" aria-label="Product List">
          <h2 class="section-title">Available Products</h2>
            <div class="table-wrapper">
              <table class="table" role="table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price (₱)</th>
                    <th>Total (₱)</th>
                    <th style="width: 140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($product_rows)): ?>
                  <tr>
                    <td colspan="5" style="text-align:center; color: var(--muted);">No products added</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($product_rows as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo number_format((int)$row['qty_on_hand']); ?></td>
                    <td><?php echo number_format((float)$row['unit_price'], 2); ?></td>
                    <td><?php echo number_format(((int)$row['qty_on_hand']) * ((float)$row['unit_price']), 2); ?></td>
                    <td>
                      <div class="row-actions">
                        <form method="GET" action="" style="display:inline">
                          <input type="hidden" name="edit" value="<?php echo (int)$row['id']; ?>" />
                          <button type="submit" class="btn-secondary small">Edit</button>
                        </form>
                        <form method="POST" action="" style="display:inline" onsubmit="return confirm('Delete this product?');">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="index" value="<?php echo (int)$row['id']; ?>" />
                          <button type="submit" class="btn-danger small">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>

      <div class="col right">
        <section class="panel" aria-label="Summary">
          <h2 class="section-title">Summary</h2>
          <div class="summary">
            <div class="summary-item">
              <div class="summary-label">Total Products</div>
              <div class="summary-value"><?php echo number_format((int)$product_count); ?></div>
            </div>
            <div class="summary-item">
              <div class="summary-label">Total Value</div>
              <div class="summary-value">₱<?php echo number_format($total_value, 2); ?></div>
            </div>
          </div>
        </section>
        <section class="add-form panel" aria-label="Add Product">
          <h2 class="section-title"><?php echo $isEditing ? 'Edit Product' : 'Add Product'; ?></h2>
          <form method="POST" action="">
            <?php if ($isEditing): ?>
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="id" value="<?php echo (int)$editIndex; ?>" />
            <?php else: ?>
              <input type="hidden" name="action" value="add" />
            <?php endif; ?>
            <div class="grid">
              <div class="field">
                <label class="label" for="product">Product</label>
                <input class="input" type="text" id="product" name="product" placeholder="e.g., Milk" required value="<?php echo htmlspecialchars((string)$form_product); ?>" />
              </div>
              <div class="field">
                <label class="label" for="quantity">Quantity</label>
                <input class="input" type="number" id="quantity" name="quantity" min="1" step="1" placeholder="e.g., 10" required value="<?php echo htmlspecialchars((string)$form_quantity); ?>" />
              </div>
              <div class="field">
                <label class="label" for="price">Price</label>
                <input class="input" type="number" id="price" name="price" min="0" step="0.01" placeholder="e.g., 49.99" required value="<?php echo htmlspecialchars((string)$form_price); ?>" />
              </div>
            </div>
            <div class="actions">
              <button type="submit" class="btn-primary"><?php echo $isEditing ? 'Update Product' : 'Add to Inventory'; ?></button>
            </div>
          </form>
        </section>
      </div>
    </section>

  </main>
  <script>
    (function() {
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
