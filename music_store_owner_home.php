<?php
session_start();
require_once 'db.php';

// Helper: JSON response for AJAX
function json_resp($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// AJAX endpoint: record a sell -> create Musical_Instrument_Inventory and Music_Inventory_Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sell') {
    $productId = trim($_POST['product_id'] ?? '');
    $qty = intval($_POST['qty'] ?? 1);
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) json_resp(['success'=>false,'message'=>'Not authenticated']);
    if ($productId === '' || $qty <= 0) json_resp(['success'=>false,'message'=>'Invalid data']);

    $invId = 'INV' . time() . rand(100,999);
    $invDetailId = 'IND' . time() . rand(100,999);

    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdo->beginTransaction();

            $insInv = $pdo->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
            $insInv->execute([$invId, $productId, null, null, $qty]);

            $insDet = $pdo->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
            $insDet->execute([$invDetailId, $invId, $productId, null, null, $qty]);

            try {
                $upd = $pdo->prepare('UPDATE `Product` SET stock = stock - ? WHERE ProductID = ?');
                $upd->execute([$qty, $productId]);
            } catch (\Throwable $e) {}

            $pdo->commit();

            $stmt = $pdo->prepare('SELECT IFNULL(SUM(Qty),0) AS total_stock FROM `Music_Inventory_Detail` WHERE ProductID = ?');
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $newStock = isset($row['total_stock']) ? (int)$row['total_stock'] : null;

            json_resp(['success'=>true,'inventory_id'=>$invId,'inventory_detail_id'=>$invDetailId,'new_stock'=>$newStock]);
        } elseif (isset($conn) && ($conn instanceof mysqli)) {
            $conn->begin_transaction();

            $stmt = $conn->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
            $null = null; $unitNull = null;
            $stmt->bind_param('sssis', $invId, $productId, $null, $unitNull, $qty);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssis', $invDetailId, $invId, $productId, $null, $unitNull, $qty);
            $stmt->execute();
            $stmt->close();

            try {
                $stmt = $conn->prepare('UPDATE `Product` SET stock = stock - ? WHERE ProductID = ?');
                if ($stmt) { $stmt->bind_param('is', $qty, $productId); $stmt->execute(); $stmt->close(); }
            } catch (\Throwable $e){}

            $conn->commit();

            $stmt = $conn->prepare('SELECT IFNULL(SUM(Qty),0) AS total_stock FROM `Music_Inventory_Detail` WHERE ProductID = ?');
            $stmt->bind_param('s', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $newStock = isset($row['total_stock']) ? (int)$row['total_stock'] : null;
            $stmt->close();

            json_resp(['success'=>true,'inventory_id'=>$invId,'inventory_detail_id'=>$invDetailId,'new_stock'=>$newStock]);
        } else {
            json_resp(['success'=>false,'message'=>'DB connection not found']);
        }
    } catch (\Throwable $ex) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (isset($conn) && ($conn instanceof mysqli)) $conn->rollback();
        json_resp(['success'=>false,'message'=>$ex->getMessage()]);
    }
}

// --- page render below ---
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user = null;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email FROM `stringsavior_1`.`user` WHERE user_id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } elseif (isset($conn) && ($conn instanceof mysqli)) {
        $id = (int) $_SESSION['user_id'];
        $res = $conn->query("SELECT user_id, first_name, last_name, email FROM `stringsavior_1`.`user` WHERE user_id = $id LIMIT 1");
        $user = $res ? $res->fetch_assoc() : null;
    }
} catch (Exception $ex) {}

if (!$user) { session_unset(); session_destroy(); header('Location: login.php'); exit; }

$products = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT p.ProductID, p.Description, p.ProductCode, COALESCE(pb.BrandName,'') AS BrandName, COALESCE(pm.ModelName,'') AS ModelName FROM `Product` p LEFT JOIN `Product_Brand` pb ON p.PBrandID = pb.PBrandID LEFT JOIN `Product_Model` pm ON p.PModelID = pm.PModelID ORDER BY p.Description ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $st = $pdo->prepare('SELECT IFNULL(SUM(Qty),0) AS stock FROM `Music_Inventory_Detail` WHERE ProductID = ?');
            $st->execute([$r['ProductID']]);
            $srow = $st->fetch(PDO::FETCH_ASSOC);
            $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;

            $pstmt = $pdo->prepare('SELECT Price FROM `Product_Price` WHERE ProductID = ? ORDER BY `Date` DESC LIMIT 1');
            $pstmt->execute([$r['ProductID']]);
            $pp = $pstmt->fetch(PDO::FETCH_ASSOC);
            $price = $pp ? (float)$pp['Price'] : 0;

            $products[] = [
                'id' => $r['ProductID'],
                'code' => $r['ProductCode'] ?? '',
                'brand' => $r['BrandName'] ?? '',
                'model' => $r['ModelName'] ?? '',
                'name' => trim(($r['BrandName'] ? $r['BrandName'] . ' ' : '') . ($r['ModelName'] ? $r['ModelName'] : $r['Description'])),
                'description' => $r['Description'] ?? '',
                'price' => $price,
                'stock' => $stock,
                'image' => 'images/avatar.png'
            ];
        }
    } elseif (isset($conn) && ($conn instanceof mysqli)) {
        $res = $conn->query("SELECT ProductID, Description, ProductCode FROM `Product` ORDER BY Description ASC");
        if ($res && $res->num_rows) {
            while ($r = $res->fetch_assoc()) {
                $stmt = $conn->prepare('SELECT IFNULL(SUM(Qty),0) AS stock FROM `Music_Inventory_Detail` WHERE ProductID = ?');
                $stmt->bind_param('s',$r['ProductID']);
                $stmt->execute();
                $res2 = $stmt->get_result();
                $srow = $res2->fetch_assoc();
                $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
                $stmt->close();

                $price = 0;
                $pr = $conn->prepare('SELECT Price FROM `Product_Price` WHERE ProductID = ? ORDER BY `Date` DESC LIMIT 1');
                if ($pr) {
                    $pr->bind_param('s', $r['ProductID']);
                    $pr->execute();
                    $g = $pr->get_result()->fetch_assoc();
                    $price = $g ? (float)$g['Price'] : 0;
                    $pr->close();
                }

                $products[] = [
                    'id' => $r['ProductID'],
                    'code' => $r['ProductCode'] ?? '',
                    'brand' => '',
                    'model' => '',
                    'name' => $r['Description'],
                    'description' => $r['Description'],
                    'price' => $price,
                    'stock' => $stock,
                    'image' => 'images/avatar.png'
                ];
            }
        }
    }
} catch (Exception $e) {}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>StringSavior - Store Owner Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#f7f8fa; }
    .brand { color:#ff8800; font-weight:700; font-size:1.15rem; text-decoration:none; }
    .card-img-top { height:180px; object-fit:cover; }
    .small-muted { color:#6c757d; font-size:.9rem; }
    .container-sm { max-width:1100px; margin:auto; padding:18px; }
    .user-badge { display:flex; gap:.5rem; align-items:center; }
    .user-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; }
    .new-inventory { box-shadow: 0 0 0 4px rgba(255,136,0,0.14); border: 1px solid #ff8800; transition: box-shadow .3s, border-color .3s; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-sm">
    <a class="navbar-brand brand" href="#">StringSavior</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="sales_report.php"><i class="fa-solid fa-box-open me-1"></i>Sales Report</a>
      <a class="btn btn-outline-secondary btn-sm" href="inventory.php"><i class="fa-solid fa-box-open me-1"></i> Inventory</a>
      <a class="btn btn-outline-warning btn-sm" href="order.php"><i class="fa-solid fa-receipt me-1"></i> Orders</a>
      <div class="dropdown">
        <a class="btn btn-light btn-sm dropdown-toggle user-badge" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="images/avatar.png" alt="avatar" class="user-avatar">
          <span><?= e($user['first_name'] . ' ' . ($user['last_name'] ?? '')) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
          <li><a class="dropdown-item" href="profile.php"><i class="fa-solid fa-user me-2"></i> Profile</a></li>
          <li><a class="dropdown-item" href="inventory.php"><i class="fa-solid fa-box-open me-2"></i> Manage Inventory</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<main class="container-sm mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">Welcome, <?= e($user['first_name']) ?></h4>
      <div class="small-muted">Manage products, stock, and orders from here.</div>
    </div>
    <div class="text-end small-muted">
      Last opened: <div class="fw-semibold"><?= date('F j, Y, g:i A') ?></div>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-0">Your Products</h6>
        <div class="small-muted">Click product image to view details</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="javascript:location.reload()"><i class="fa-solid fa-arrows-rotate me-1"></i> Refresh</a>
        <a class="btn btn-sm btn-warning" href="inventory.php"><i class="fa-solid fa-plus me-1"></i> Add Product</a>
      </div>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3" id="productGrid">
    <?php foreach ($products as $p): ?>
      <div class="col product-card" data-product-id="<?= e($p['id']) ?>" id="product-card-<?= e($p['id']) ?>">
        <div class="card h-100 shadow-sm">
          <img src="<?= e($p['image']) ?>" class="card-img-top" alt="<?= e($p['name']) ?>" onerror="this.src='images/avatar.png'">
          <div class="card-body d-flex flex-column">
            <h6 class="card-title mb-1"><?= e($p['name']) ?></h6>

            <div class="mb-2 small-muted">
              <div><strong>Product Description:</strong> <?= e($p['description'] ?? '') ?></div>
              <div class="mt-1 price-stock">₱<?= number_format($p['price'] ?? 0, 2) ?> <span class="text-muted">• Qty: <span class="stock-count"><?= (int)$p['stock'] ?></span></span></div>
            </div>

            <div class="mt-auto d-flex gap-2">
              <button
                class="btn btn-sm btn-outline-primary w-100 view-btn"
                type="button"
                data-id="<?= e($p['id']) ?>"
                data-code="<?= e($p['code']) ?>"
                data-name="<?= e($p['name']) ?>"
                data-price="<?= e(number_format($p['price'] ?? 0, 2)) ?>"
                data-stock="<?= e((int)$p['stock']) ?>"
                data-image="<?= e($p['image']) ?>"
                data-description="<?= e($p['description'] ?? '') ?>">
                <i class="fa-solid fa-eye me-1"></i> View
              </button>

              <button class="btn btn-sm btn-outline-success sell-btn" type="button" data-id="<?= e($p['id']) ?>" data-qty="1">
                <i class="fa-solid fa-cart-plus me-1"></i> Sell 1
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<!-- Product modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="productTitle" class="modal-title">Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="productImage" src="" alt="product" class="img-fluid mb-3" style="max-height:420px; object-fit:contain;">
        <p id="productPrice" class="fw-semibold"></p>
        <p id="productStock" class="small-muted"></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button id="sellOne" class="btn btn-success">Sell 1</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  function qs(sel, el=document) { return el.querySelector(sel); }
  function qsa(sel, el=document) { return Array.from(el.querySelectorAll(sel)); }

  document.addEventListener('click', function(e){
    const v = e.target.closest('.view-btn');
    if (!v) return;
    const id = v.dataset.id;
    const name = v.dataset.name;
    const price = parseFloat(v.dataset.price || 0).toFixed(2);
    const stock = v.dataset.stock;
    const image = v.dataset.image || 'images/avatar.png';

    qs('#productTitle').textContent = name;
    qs('#productImage').src = image;
    qs('#productPrice').textContent = '₱' + price;
    qs('#productStock').textContent = 'Stock: ' + stock;
    qs('#sellOne').dataset.id = id;
    new bootstrap.Modal(qs('#productModal')).show();
  });

  async function doSell(productId, qty, cardEl) {
    try {
      const fd = new FormData();
      fd.append('action','sell');
      fd.append('product_id', productId);
      fd.append('qty', String(qty));

      const resp = await fetch(location.pathname, { method:'POST', body:fd, credentials:'same-origin' });
      const data = await resp.json();
      if (!data.success) {
        alert('Error: ' + (data.message || 'failed'));
        return;
      }

      if (typeof data.new_stock !== 'undefined' && data.new_stock !== null) {
        const stockSpan = cardEl.querySelector('.stock-count');
        if
