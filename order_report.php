<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$rows = [];
try {
    $orderFilter = trim($_GET['order_id'] ?? '');
    $productFilter = trim($_GET['product_id'] ?? '');

    $sql = "
      SELECT
        o.OrderID,
        od.OrderDetailID AS DetailID,
        COALESCE(p.ProductCode, od.ProductID, p.ProductID) AS ProductCode,
        COALESCE(pb.BrandName,'') AS Brand,
        COALESCE(pm.ModelName,'') AS Model,
        COALESCE(o.OrderDate, od.Date, od.CreatedAt, o.CreatedAt) AS Date,
        od.Qty,
        od.TotalPrice,
        od.PriceID
      FROM `Order_Detail` od
      LEFT JOIN `Orders` o ON od.OrderID = o.OrderID
      LEFT JOIN `Product` p ON od.ProductID = p.ProductID
      LEFT JOIN `Product_Brand` pb ON p.PBrandID = pb.PBrandID
      LEFT JOIN `Product_Model` pm ON p.PModelID = pm.PModelID
      WHERE 1=1
      " . ($orderFilter ? " AND o.OrderID = ? " : "") . "
      " . ($productFilter ? " AND od.ProductID = ? " : "") . "
      ORDER BY Date DESC, o.OrderID DESC, od.OrderDetailID DESC
      LIMIT 500
    ";

    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare($sql);
        $params = [];
        if ($orderFilter) $params[] = $orderFilter;
        if ($productFilter) $params[] = $productFilter;
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        // bind dynamically
        $params = [];
        $types = '';
        if ($orderFilter) { $types .= 's'; $params[] = $orderFilter; }
        if ($productFilter) { $types .= 's'; $params[] = $productFilter; }

        if ($stmt = $conn->prepare($sql)) {
            if ($types) {
                $bind_names = [];
                $bind_names[] = $types;
                for ($i = 0; $i < count($params); $i++) {
                    $bind_names[] = &$params[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_names);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        } else {
            // fallback: run without filter
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
  <title>Order Report — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h4>Order Report</h4>
    <div>
      <a href="product_sales.php" class="btn btn-sm btn-outline-secondary">Product Sales</a>
      <a href="sales_report.php" class="btn btn-sm btn-outline-secondary">Recent Sales</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Detail ID</th>
          <th>Product Code</th>
          <th>Brand</th>
          <th>Model</th>
          <th>Date</th>
          <th>Qty</th>
          <th class="text-end">Total Price</th>
          <th>PriceID</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center small text-muted">No order details found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['OrderID']) ?></td>
            <td><?= e($r['DetailID']) ?></td>
            <td><?= e($r['ProductCode']) ?></td>
            <td><?= e($r['Brand']) ?></td>
            <td><?= e($r['Model']) ?></td>
            <td><?= e(date('Y-m-d', strtotime($r['Date'] ?? ''))) ?></td>
            <td><?= (int)($r['Qty'] ?? 0) ?></td>
            <td class="text-end"><?= isset($r['TotalPrice']) ? '₱'.number_format((float)$r['TotalPrice'],2) : '-' ?></td>
            <td><?= e($r['PriceID'] ?? '') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>