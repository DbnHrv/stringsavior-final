<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$rows = [];
try {
    $productFilter = trim($_GET['product_id'] ?? '');

    $sql = "
      SELECT
        COALESCE(p.ProductID, sr.ProductID) AS ProductID,
        COALESCE(p.ProductCode, p.ProductID, sr.ProductID) AS ProductCode,
        COALESCE(pb.BrandName,'') AS Brand,
        COALESCE(pm.ModelName,'') AS Model,
        MAX(sr.Date) AS LastSaleDate,
        IFNULL(SUM(sr.Qty),0) AS TotalQty,
        IFNULL(SUM(sr.TotalSale),0) AS TotalSales
      FROM `Sale_Report` sr
      LEFT JOIN `Product` p ON sr.ProductID = p.ProductID
      LEFT JOIN `Product_Brand` pb ON p.PBrandID = pb.PBrandID
      LEFT JOIN `Product_Model` pm ON p.PModelID = pm.PModelID
      " . ($productFilter ? " WHERE sr.ProductID = ? " : "") . "
      GROUP BY COALESCE(p.ProductID, sr.ProductID)
      ORDER BY TotalSales DESC, LastSaleDate DESC
      LIMIT 100
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
<html>
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Product Sales — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h4>Product Sales</h4>
    <div>
      <a href="sales_report.php" class="btn btn-sm btn-outline-secondary">Recent Sales</a>
      <a href="order_report.php" class="btn btn-sm btn-outline-secondary">Orders</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Product ID</th>
          <th>Product Code</th>
          <th>Brand</th>
          <th>Model</th>
          <th>Last Sale</th>
          <th>Qty</th>
          <th class="text-end">Total Sale</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center small text-muted">No product sales yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['ProductID']) ?></td>
            <td><?= e($r['ProductCode']) ?></td>
            <td><?= e($r['Brand']) ?></td>
            <td><?= e($r['Model']) ?></td>
            <td><?= e(date('Y-m-d', strtotime($r['LastSaleDate'] ?? ''))) ?></td>
            <td><?= (int)($r['TotalQty'] ?? 0) ?></td>
            <td class="text-end"><?= '₱'.number_format((float)($r['TotalSales'] ?? 0),2) ?></td>
            <td>
              <a href="sales_report.php?product_id=<?= urlencode($r['ProductID']) ?>" class="btn btn-sm btn-outline-primary">View Sales</a>
              <a href="order_report.php?product_id=<?= urlencode($r['ProductID']) ?>" class="btn btn-sm btn-outline-secondary">View Orders</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>