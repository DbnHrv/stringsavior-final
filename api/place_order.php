<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }

$supplier = trim($body['supplier'] ?? '');
$items = $body['items'] ?? [];

if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (!$supplier || !is_array($items) || empty($items)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing supplier or items']); exit; }

$orderId = 'ORD' . time() . rand(100,999);
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->beginTransaction();
        // insert Orders
        $insO = $pdo->prepare('INSERT INTO `Orders` (OrderID, InventoryID, Qty) VALUES (?, ?, ?)');
        // InventoryID not known here; store NULL for now, Qty = total items qty
        $totalQty = array_sum(array_map(function($i){ return intval($i['quantity'] ?? 0); }, $items));
        $insO->execute([$orderId, null, $totalQty]);

        $detailStmt = $pdo->prepare('INSERT INTO `Order_Detail` (OrderDetailID, OrderID, ProductID, UnitID, Qty, DeclaredValue, TotalPrice, ConsignedTo, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $it) {
            $odId = 'OD' . time() . rand(100,999) . rand(10,99);
            $productId = !empty($it['product_id']) ? $it['product_id'] : null;
            $unitId = isset($it['unit_id']) && $it['unit_id'] !== '' ? intval($it['unit_id']) : null;
            $qty = intval($it['quantity'] ?? 0);
            $decl = is_numeric($it['budget'] ?? null) ? (float)$it['budget'] : 0.0;
            $total = $qty * $decl;
            $consignedTo = $supplier;
            $address = trim($it['address'] ?? '');
            $detailStmt->execute([$odId, $orderId, $productId, $unitId, $qty, $decl, $total, $consignedTo, $address]);

            // if product exists -> create inventory incoming records and update product.stock
            if ($productId && $qty > 0) {
                $invId = 'INV' . time() . rand(100,999);
                $invDetId = 'IND' . time() . rand(100,999);
                $insInv = $pdo->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
                $insInv->execute([$invId, $productId, null, $unitId, $qty]);
                $insInvDet = $pdo->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
                $insInvDet->execute([$invDetId, $invId, $productId, null, $unitId, $qty]);

                // update product stock if exists
                try {
                    $upd = $pdo->prepare('UPDATE `Product` SET stock = COALESCE(stock,0) + ? WHERE ProductID = ?');
                    $upd->execute([$qty, $productId]);
                } catch (\Throwable $e) { /* ignore */ }
            }
        }

        $pdo->commit();
        echo json_encode(['success'=>true,'order_id'=>$orderId]);
        exit;
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $conn->begin_transaction();
        $totalQty = array_sum(array_map(function($i){ return intval($i['quantity'] ?? 0); }, $items));
        $stmt = $conn->prepare('INSERT INTO `Orders` (OrderID, InventoryID, Qty) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $orderId, $nullInv = $null, $totalQty);
        $stmt->execute(); $stmt->close();

        $detailStmt = $conn->prepare('INSERT INTO `Order_Detail` (OrderDetailID, OrderID, ProductID, UnitID, Qty, DeclaredValue, TotalPrice, ConsignedTo, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $it) {
            $odId = 'OD' . time() . rand(100,999) . rand(10,99);
            $productId = !empty($it['product_id']) ? $it['product_id'] : null;
            $unitId = isset($it['unit_id']) && $it['unit_id'] !== '' ? intval($it['unit_id']) : null;
            $qty = intval($it['quantity'] ?? 0);
            $decl = is_numeric($it['budget'] ?? null) ? (float)$it['budget'] : 0.0;
            $total = $qty * $decl;
            $consignedTo = $supplier;
            $address = trim($it['address'] ?? '');
            $detailStmt->bind_param('ssssidsss', $odId, $orderId, $productId, $unitId_param = $unitId !== null ? (string)$unitId : null, $qty, $decl, $total, $consignedTo, $address);
            // mysqli binding quirks handled by casting; if nulls required adjust accordingly
            $detailStmt->execute();
            // create inventory if product exists
            if ($productId && $qty > 0) {
                $invId = 'INV' . time() . rand(100,999);
                $invDetId = 'IND' . time() . rand(100,999);
                $stmt = $conn->prepare('INSERT INTO `Musical_Instrument_Inventory` (InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssis', $invId, $productId, $nullSupplier = null, $unitId, $qty); $stmt->execute(); $stmt->close();
                $stmt = $conn->prepare('INSERT INTO `Music_Inventory_Detail` (InventoryDetailID, InventoryID, ProductID, SupplierID, UnitID, Qty) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssis', $invDetId, $invId, $productId, $nullSupplier = null, $unitId, $qty); $stmt->execute(); $stmt->close();
                try {
                    $stmt = $conn->prepare('UPDATE `Product` SET stock = COALESCE(stock,0) + ? WHERE ProductID = ?');
                    if ($stmt) { $stmt->bind_param('is', $qty, $productId); $stmt->execute(); $stmt->close(); }
                } catch (\Throwable $e) {}
            }
        }
        $detailStmt->close();
        $conn->commit();
        echo json_encode(['success'=>true,'order_id'=>$orderId]);
        exit;
    } else {
        http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB not available']); exit;
    }
} catch (\Throwable $ex) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($conn) && $conn instanceof mysqli) $conn->rollback();
    http_response_code(500); echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); exit;
}
?>