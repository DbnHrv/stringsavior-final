<?php
session_start();
require_once 'db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// determine supplier id / record
$supplier = null;
$supplierId = $_SESSION['user_id'] ?? null;
try {
    // if explicit supplier_id in session, try to load
    if ($supplierId) {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare('SELECT * FROM `Supplier` WHERE SupplierID = ? LIMIT 1');
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif (isset($conn) && ($conn instanceof mysqli)) {
            $stmt = $conn->prepare('SELECT SupplierID, SupplierName, SupplierContact, SupplierAddress FROM `Supplier` WHERE SupplierID = ? LIMIT 1');
            $stmt->bind_param('s', $supplierId);
            $stmt->execute();
            $res = $stmt->get_result();
            $supplier = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    }
    // fallback: try to resolve using logged user info if user_type == 'Supplier'
    if (!$supplier && !empty($_SESSION['user'])) {
        $user = $_SESSION['user'];
        $u_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if (!empty($u_name)) {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare('SELECT * FROM `Supplier` WHERE SupplierName LIKE ? LIMIT 1');
                $stmt->execute([$u_name . '%']);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif (isset($conn) && ($conn instanceof mysqli)) {
                $like = $u_name . '%';
                $stmt = $conn->prepare('SELECT SupplierID, SupplierName, SupplierContact, SupplierAddress FROM `Supplier` WHERE SupplierName LIKE ? LIMIT 1');
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $res = $stmt->get_result();
                $supplier = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            }
        }
    }
} catch (Exception $ex) {
    // ignore — show UI with message
}

// if still no supplier, show minimal page with link to create supplier record
if (!$supplier) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Supplier Home — StringSavior</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
      <div class="container py-5">
        <div class="card shadow-sm">
          <div class="card-body text-center">
            <h4 class="mb-3">Supplier account not linked</h4>
            <p class="text-muted">No supplier record was found for your account. Create or link a supplier profile to continue.</p>
            <a href="supplier_profile.php" class="btn btn-warning">Create / Link Supplier Profile</a>
            <a href="supplier_home.php" class="btn btn-outline-secondary ms-2">Dashboard</a>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// fetch supplier products (aggregate from Music_Inventory_Detail)
$products = [];
try {
    $sql = "
      SELECT mid.ProductID,
             COALESCE(p.Description,'') AS Description,
             COALESCE(pb.BrandName,'') AS Brand,
             COALESCE(pm.ModelName,'') AS Model,
             IFNULL(SUM(mid.Qty),0) AS stock,
             mid.UnitID
      FROM `Music_Inventory_Detail` mid
      LEFT JOIN `Product` p ON mid.ProductID = p.ProductID
      LEFT JOIN `Product_Brand` pb ON p.PBrandID = pb.PBrandID
      LEFT JOIN `Product_Model` pm ON p.PModelID = pm.PModelID
      WHERE mid.SupplierID = ?
      GROUP BY mid.ProductID
      ORDER BY p.Description ASC
    ";
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$supplier['SupplierID']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            // get latest price
            $price = null;
            $pstmt = $pdo->prepare('SELECT Price FROM `Product_Price` WHERE ProductID = ? ORDER BY `Date` DESC LIMIT 1');
            $pstmt->execute([$r['ProductID']]);
            $pp = $pstmt->fetch(PDO::FETCH_ASSOC);
            if ($pp) $price = (float)$pp['Price'];

            $products[] = [
                'id' => $r['ProductID'],
                'title' => trim(($r['Brand'] ? $r['Brand'].' ' : '') . ($r['Model'] ? $r['Model'] : '')),
                'description' => $r['Description'],
                'stock' => (int)$r['stock'],
                'price' => $price
            ];
        }
    } elseif (isset($conn) && ($conn instanceof mysqli)) {
        $stmt = $conn->prepare($sql);
        $sid = $supplier['SupplierID'];
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $price = null;
            $pr = $conn->prepare('SELECT Price FROM `Product_Price` WHERE ProductID = ? ORDER BY `Date` DESC LIMIT 1');
            if ($pr) {
                $pr->bind_param('s', $r['ProductID']);
                $pr->execute();
                $g = $pr->get_result()->fetch_assoc();
                $price = $g ? (float)$g['Price'] : null;
                $pr->close();
            }
            $products[] = [
                'id' => $r['ProductID'],
                'title' => trim(($r['Brand'] ? $r['Brand'].' ' : '') . ($r['Model'] ? $r['Model'] : '')),
                'description' => $r['Description'],
                'stock' => (int)$r['stock'],
                'price' => $price
            ];
        }
        $stmt->close();
    }
} catch (Exception $ex) { /* ignore */ }

// fetch orders consigned to this supplier (Order_Detail)
$orders = [];
try {
    $sql = "
      SELECT od.OrderDetailID, od.OrderID, od.ProductID, COALESCE(p.Description,'') AS Description,
             od.Qty, od.DeclaredValue, od.TotalPrice, od.ConsignedTo, od.Address, o.InventoryID
      FROM `Order_Detail` od
      LEFT JOIN `Product` p ON od.ProductID = p.ProductID
      LEFT JOIN `Orders` o ON od.OrderID = o.OrderID
      WHERE (od.ConsignedTo = ? OR od.SupplierID = ?)
      ORDER BY od.OrderID DESC, od.OrderDetailID DESC
      LIMIT 200
    ";
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$supplier['SupplierName'], $supplier['SupplierID']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif (isset($conn) && ($conn instanceof mysqli)) {
        $stmt = $conn->prepare($sql);
        $sname = $supplier['SupplierName'];
        $sid = $supplier['SupplierID'];
        $stmt->bind_param('ss', $sname, $sid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $orders[] = $r;
        $stmt->close();
    }
} catch (Exception $ex) { /* ignore */ }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Supplier — <?= e($supplier['SupplierName']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{ background:#f7f8fa; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; }
    .brand{ color:#ff8800; font-weight:700; text-decoration:none; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand brand" href="music_store_owner_home.php">StringSavior</a>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <div class="small text-muted me-3"><?= e($supplier['SupplierName']) ?></div>
      <a class="btn btn-outline-secondary btn-sm" href="supplier_profile.php">Profile</a>
      <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container my-4">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><?= e($supplier['SupplierName']) ?></h5>
          <p class="mb-1 small text-muted">Contact</p>
          <p class="mb-2"><?= e($supplier['SupplierContact'] ?? '-') ?></p>
          <p class="mb-1 small text-muted">Address</p>
          <p class="mb-2"><?= e($supplier['SupplierAddress'] ?? '-') ?></p>
          <a href="supplier_profile.php" class="btn btn-sm btn-warning">Edit Profile</a>
        </div>
      </div>

      <div class="card mt-3 shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Summary</h6>
          <div class="small text-muted">Products supplied</div>
          <div class="fs-4 fw-semibold"><?= count($products) ?></div>
          <div class="small text-muted mt-2">Pending orders</div>
          <div class="fs-4 fw-semibold"><?= count($orders) ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Products Supplied</h5>
            <a href="inventory.php" class="btn btn-sm btn-outline-secondary">Manage Inventory</a>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr><th>Product ID</th><th>Product</th><th class="text-end">Stock</th><th class="text-end">Price</th></tr>
              </thead>
              <tbody>
                <?php if (empty($products)): ?>
                  <tr><td colspan="4" class="text-center small text-muted">No products found for this supplier.</td></tr>
                <?php else: foreach ($products as $p): ?>
                  <tr>
                    <td><?= e($p['id']) ?></td>
                    <td><?= e(($p['title'] ?: $p['description'])) ?></td>
                    <td class="text-end"><?= (int)$p['stock'] ?></td>
                    <td class="text-end"><?= isset($p['price']) && $p['price'] !== null ? '₱'.number_format($p['price'],2) : '-' ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3">Orders Consigned to You</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead>
                <tr><th>Order ID</th><th>Detail ID</th><th>Product</th><th>Qty</th><th class="text-end">Total Price</th><th>Address</th></tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr><td colspan="6" class="text-center small text-muted">No orders found.</td></tr>
                <?php else: foreach ($orders as $o): ?>
                  <tr>
                    <td><?= e($o['OrderID']) ?></td>
                    <td><?= e($o['OrderDetailID']) ?></td>
                    <td><?= e(($o['Description'] ?? '') . ' (' . ($o['ProductID'] ?? '') . ')') ?></td>
                    <td><?= (int)($o['Qty'] ?? 0) ?></td>
                    <td class="text-end"><?= isset($o['TotalPrice']) ? '₱'.number_format((float)$o['TotalPrice'],2) : '-' ?></td>
                    <td><?= e($o['Address'] ?? '') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>