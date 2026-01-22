<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$rows = [];
try {
    // latest 5 sale records (optionally filter by product_id)
    $productFilter = trim($_GET['product_id'] ?? '');
    $sql = "
      SELECT
        sr.SaleID,
        COALESCE(p.ProductCode, sr.ProductID, p.ProductID) AS ProductCode,
        COALESCE(pb.BrandName,'') AS Brand,
        COALESCE(pm.ModelName,'') AS Model,
        sr.Date,
        sr.Qty,
        sr.TotalSale,
        sr.ProductID
      FROM `Sale_Report` sr
      LEFT JOIN `Product` p ON sr.ProductID = p.ProductID
      LEFT JOIN `Product_Brand` pb ON p.PBrandID = pb.PBrandID
      LEFT JOIN `Product_Model` pm ON p.PModelID = pm.PModelID
      " . ($productFilter ? " WHERE sr.ProductID = ? " : "") . "
      ORDER BY sr.Date DESC, sr.SaleID DESC
      LIMIT 5
    ";
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        if ($productFilter) $stmt->execute([$productFilter]); else $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        if ($productFilter) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $productFilter);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
    }
} catch (Exception $ex) { /* ignore */ }
?>
<!doctype html>
<html><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Recent Sales (peek)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="p-4">
  <div class="container">
    <div class="d-flex justify-content-between mb-3">
      <h4>Recent Sales</h4>
      <div>
        <a href="product_sales.php" class="btn btn-sm btn-outline-secondary">Product Sales</a>
        <a href="order_report.php" class="btn btn-sm btn-outline-secondary">All Orders</a>
        <a href="music_store_owner_home.php" class="btn btn-sm btn-outline-secondary">Dashboard</a>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Sale ID</th>
            <th>Product Code</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Date</th>
            <th>Qty</th>
            <th class="text-end">Total Sale</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center small text-muted">No recent sales found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['SaleID']) ?></td>
              <td><?= e($r['ProductCode']) ?></td>
              <td><?= e($r['Brand']) ?></td>
              <td><?= e($r['Model']) ?></td>
              <td><?= e(date('Y-m-d', strtotime($r['Date'] ?? ''))) ?></td>
              <td><?= (int)($r['Qty'] ?? 0) ?></td>
              <td class="text-end"><?= isset($r['TotalSale']) ? '₱'.number_format((float)$r['TotalSale'],2) : '-' ?></td>
              <td>
                <a href="order_report.php?product_id=<?= urlencode($r['ProductID']) ?>" class="btn btn-sm btn-outline-primary">View Orders</a>
                <a href="product_sales.php?product_id=<?= urlencode($r['ProductID']) ?>" class="btn btn-sm btn-outline-secondary">Product Sales</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body></html>