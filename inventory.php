<?php
session_start();
require_once 'db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// load lookups: products, suppliers, units
$products = $suppliers = $units = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $products = $pdo->query("SELECT ProductID, Description FROM `Product` ORDER BY Description ASC")->fetchAll(PDO::FETCH_ASSOC);
        $suppliers = $pdo->query("SELECT SupplierID, SupplierName FROM `Supplier` ORDER BY SupplierName ASC")->fetchAll(PDO::FETCH_ASSOC);
        $units = $pdo->query("SELECT UnitID, MetricUnit FROM `Unit_of_Measurement` ORDER BY MetricUnit ASC")->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query("SELECT ProductID, Description FROM `Product` ORDER BY Description ASC");
        if ($res) while($r = $res->fetch_assoc()) $products[] = $r;
        $res = $conn->query("SELECT SupplierID, SupplierName FROM `Supplier` ORDER BY SupplierName ASC");
        if ($res) while($r = $res->fetch_assoc()) $suppliers[] = $r;
        $res = $conn->query("SELECT UnitID, MetricUnit FROM `Unit_of_Measurement` ORDER BY MetricUnit ASC");
        if ($res) while($r = $res->fetch_assoc()) $units[] = $r;
    }
} catch (Exception $ex) { /* ignore lookups on error */ }

// handle POST add inventory
$error = '';
$success = '';
$highlightId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inventory') {
    $productId = trim($_POST['product_id'] ?? '');
    $supplierId = trim($_POST['supplier_id'] ?? '') ?: null;
    $unitId = intval($_POST['unit_id'] ?? 0) ?: null;
    $qty = intval($_POST['qty'] ?? 0);
    $price = is_numeric($_POST['price'] ?? '') ? (float)$_POST['price'] : null;

    if ($productId === '' || $qty <= 0) {
        $error = 'Product and positive quantity are required.';
    } else {
        // generate ids
        $invId = 'INV'.time().rand(100,999);
        $invDetId = 'IND'.time().rand(100,999);

        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $pdo->beginTransaction();

                // insert into Musical_Instrument_Inventory
                $ins = $pdo->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
                $ins->execute([$invId, $productId, $supplierId, $unitId, $qty]);

                // insert into Music_Inventory_Detail
                $ins2 = $pdo->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
                $ins2->execute([$invDetId, $invId, $productId, $supplierId, $unitId, $qty]);

                // record price if provided
                if ($price !== null) {
                    $priceId = 'PR'.time().rand(100,999);
                    $pp = $pdo->prepare('INSERT INTO `Product_Price` (PriceID, ProductID, `Date`, Price) VALUES (?, ?, CURDATE(), ?)');
                    $pp->execute([$priceId, $productId, $price]);
                }

                // attempt to update Product.stock if exists
                try {
                    $upd = $pdo->prepare('UPDATE `Product` SET stock = COALESCE(stock,0) + ? WHERE ProductID = ?');
                    $upd->execute([$qty, $productId]);
                } catch (\Throwable $e) { /* ignore missing column */ }

                $pdo->commit();
                $success = 'Inventory recorded.';
                $highlightId = $invId;
                // redirect to avoid repost
                header('Location: inventory.php?highlight=' . urlencode($highlightId));
                exit;
            } elseif (isset($conn) && $conn instanceof mysqli) {
                $conn->begin_transaction();

                $stmt = $conn->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssis', $invId, $productId, $supplierId, $unitId, $qty);
                $stmt->execute(); $stmt->close();

                $stmt = $conn->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssis', $invDetId, $invId, $productId, $supplierId, $unitId, $qty);
                $stmt->execute(); $stmt->close();

                if ($price !== null) {
                    $priceId = 'PR'.time().rand(100,999);
                    $stmt = $conn->prepare('INSERT INTO `Product_Price` (PriceID, ProductID, `Date`, Price) VALUES (?, ?, CURDATE(), ?)');
                    $stmt->bind_param('ssd', $priceId, $productId, $price);
                    $stmt->execute(); $stmt->close();
                }

                // try update product.stock
                try {
                    $stmt = $conn->prepare('UPDATE `Product` SET stock = COALESCE(stock,0) + ? WHERE ProductID = ?');
                    if ($stmt) { $stmt->bind_param('is', $qty, $productId); $stmt->execute(); $stmt->close(); }
                } catch (\Throwable $e) {}

                $conn->commit();
                $success = 'Inventory recorded.';
                $highlightId = $invId;
                header('Location: inventory.php?highlight=' . urlencode($highlightId));
                exit;
            } else {
                $error = 'Database connection not available.';
            }
        } catch (\Throwable $ex) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            if (isset($conn) && $conn instanceof mysqli) $conn->rollback();
            $error = 'Failed to add inventory: ' . $ex->getMessage();
        }
    }
}

// fetch recent inventory aggregate for display
$inventory = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("
            SELECT mid.InventoryID, mid.InventoryDetailID, mid.ProductID, COALESCE(p.Description,'') AS Description,
                   mid.SupplierID, COALESCE(s.SupplierName,'') AS SupplierName, mid.UnitID, COALESCE(u.MetricUnit,'') AS UnitName,
                   mid.Qty, mid.InventoryID AS inv_ref
            FROM `Music_Inventory_Detail` mid
            LEFT JOIN `Product` p ON mid.ProductID = p.ProductID
            LEFT JOIN `Supplier` s ON mid.SupplierID = s.SupplierID
            LEFT JOIN `Unit_of_Measurement` u ON mid.UnitID = u.UnitID
            ORDER BY mid.InventoryDetailID DESC
            LIMIT 200
        ");
        $inventory = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $sql = "
            SELECT mid.InventoryID, mid.InventoryDetailID, mid.ProductID, COALESCE(p.Description,'') AS Description,
                   mid.SupplierID, COALESCE(s.SupplierName,'') AS SupplierName, mid.UnitID, COALESCE(u.MetricUnit,'') AS UnitName,
                   mid.Qty, mid.InventoryID AS inv_ref
            FROM `Music_Inventory_Detail` mid
            LEFT JOIN `Product` p ON mid.ProductID = p.ProductID
            LEFT JOIN `Supplier` s ON mid.SupplierID = s.SupplierID
            LEFT JOIN `Unit_of_Measurement` u ON mid.UnitID = u.UnitID
            ORDER BY mid.InventoryDetailID DESC
            LIMIT 200
        ";
        $res = $conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $inventory[] = $r;
    }
} catch (Exception $ex) { /* ignore */ }

// highlight id from query param
$highlight = trim($_GET['highlight'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventory — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .new-inv { box-shadow: 0 0 0 4px rgba(255,136,0,0.12); border:1px solid #ff8800; }
  </style>
</head>
<body class="p-3">
  <div class="container">
    <div class="d-flex justify-content-between mb-3">
      <h4>Inventory Management</h4>
      <a class="btn btn-sm btn-outline-secondary" href="music_store_owner_home.php">Dashboard</a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_inventory">
          <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
              <option value="">-- select product --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= e($p['ProductID']) ?>"><?= e($p['Description']) ?> (<?= e($p['ProductID']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select">
              <option value="">-- optional supplier --</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= e($s['SupplierID']) ?>"><?= e($s['SupplierName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Unit</label>
            <select name="unit_id" class="form-select">
              <option value="">-- unit --</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= e($u['UnitID']) ?>"><?= e($u['MetricUnit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label">Qty</label>
            <input type="number" name="qty" class="form-control" min="1" value="1" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Price (optional)</label>
            <input type="number" name="price" step="0.01" min="0" class="form-control">
          </div>

          <div class="col-12 text-end">
            <button class="btn btn-primary">Add Inventory</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h6 class="mb-3">Recent Inventory Details</h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr><th>InventoryID</th><th>DetailID</th><th>Product</th><th>Supplier</th><th>Unit</th><th class="text-end">Qty</th></tr>
            </thead>
            <tbody>
              <?php if (empty($inventory)): ?>
                <tr><td colspan="6" class="text-center small text-muted">No inventory details yet.</td></tr>
              <?php else: foreach ($inventory as $row): 
                $isNew = ($highlight && $highlight === ($row['InventoryID'] ?? $row['inv_ref']));
              ?>
                <tr class="<?= $isNew ? 'new-inv' : '' ?>">
                  <td><?= e($row['InventoryID']) ?></td>
                  <td><?= e($row['InventoryDetailID']) ?></td>
                  <td><?= e($row['Description'] . ' (' . $row['ProductID'] . ')') ?></td>
                  <td><?= e($row['SupplierName'] ?? $row['SupplierID']) ?></td>
                  <td><?= e($row['UnitName'] ?? $row['UnitID']) ?></td>
                  <td class="text-end"><?= (int)$row['Qty'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
  <script>
    // remove highlight after 7s for UX
    setTimeout(()=> {
      document.querySelectorAll('.new-inv').forEach(el => el.classList.remove('new-inv'));
    }, 7000);
  </script>
</body>
</html>
