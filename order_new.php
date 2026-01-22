<?php
session_start();
require_once 'db.php';
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// fetch suppliers and products for selects
$suppliers = $products = $units = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $suppliers = $pdo->query("SELECT SupplierID, SupplierName FROM `Supplier` ORDER BY SupplierName ASC")->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query("SELECT ProductID, Description FROM `Product` ORDER BY Description ASC")->fetchAll(PDO::FETCH_ASSOC);
        $units = $pdo->query("SELECT UnitID, MetricUnit FROM `Unit_of_Measurement` ORDER BY MetricUnit ASC")->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query("SELECT SupplierID, SupplierName FROM `Supplier` ORDER BY SupplierName ASC"); if ($res) while($r=$res->fetch_assoc()) $suppliers[]=$r;
        $res = $conn->query("SELECT ProductID, Description FROM `Product` ORDER BY Description ASC"); if ($res) while($r=$res->fetch_assoc()) $products[]=$r;
        $res = $conn->query("SELECT UnitID, MetricUnit FROM `Unit_of_Measurement` ORDER BY MetricUnit ASC"); if ($res) while($r=$res->fetch_assoc()) $units[]=$r;
    }
} catch (Exception $ex) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Order — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f6f7fb;padding:18px}</style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h4>Add Order</h4>
    <div>
      <a href="order.php" class="btn btn-outline-secondary">Back</a>
      <button id="goToConfirm" class="btn btn-success">Place Order</button>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <div class="mb-2 small text-muted">Supplier</div>
    <select id="supplier" class="form-select mb-2">
      <option value="">-- select supplier --</option>
      <?php foreach($suppliers as $s): ?>
        <option value="<?= e($s['SupplierName']) ?>" data-id="<?= e($s['SupplierID']) ?>"><?= e($s['SupplierName']) ?></option>
      <?php endforeach; ?>
      <option value="__other">Other...</option>
    </select>
    <input id="supplierOther" class="form-control mb-3" placeholder="Other supplier name" style="display:none">
    <div class="row g-2">
      <div class="col-md-6"><input id="prodDesc" class="form-control" placeholder="Product description"></div>
      <div class="col-md-2"><input id="qty" class="form-control" type="number" min="1" value="1"></div>
      <div class="col-md-2"><input id="unit" class="form-control" placeholder="Unit (pcs)"></div>
      <div class="col-md-2"><input id="budget" class="form-control" type="number" min="0" step="0.01" value="0"></div>
    </div>
    <div class="mt-2 d-flex gap-2">
      <button id="addItem" class="btn btn-primary">Add Item</button>
      <button id="clearItems" class="btn btn-outline-secondary">Clear Items</button>
    </div>
  </div>

  <div class="card p-3">
    <h6>Order Items <small id="itemCount" class="text-muted">0</small></h6>
    <div class="table-responsive">
      <table class="table table-sm" id="itemsTable"><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th class="text-end">Budget</th><th></th></tr></thead><tbody></tbody>
      <tfoot><tr><td colspan="3" class="text-end"><strong>Total</strong></td><td class="text-end" id="itemsTotal">₱0.00</td><td></td></tr></tfoot></table>
    </div>
  </div>
</div>

<script>
const LS_ORDERS = 'ss_orders_v1';
function uid(){ return 'ORD-'+Date.now().toString(36).toUpperCase(); }
function getOrders(){ return JSON.parse(localStorage.getItem(LS_ORDERS) || '[]'); }
function saveOrders(list){ localStorage.setItem(LS_ORDERS, JSON.stringify(list || [])); }
function fmt(n){ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }

let currentItems = [];

function renderItems(){
  const tbody = document.querySelector('#itemsTable tbody'); tbody.innerHTML='';
  let total = 0;
  if(!currentItems.length){ document.getElementById('itemCount').textContent='0'; document.getElementById('itemsTotal').textContent='₱0.00'; return; }
  currentItems.forEach((it,i)=>{
    total += Number(it.quantity)*Number(it.budget);
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${it.description}</td><td>${it.quantity}</td><td>${it.unit}</td><td class="text-end">₱${fmt(Number(it.budget)*Number(it.quantity))}</td>
      <td><button class="btn btn-sm btn-outline-danger remove" data-i="${i}">Remove</button></td>`;
    tbody.appendChild(tr);
  });
  document.getElementById('itemCount').textContent = currentItems.length;
  document.getElementById('itemsTotal').textContent = '₱'+fmt(total);
  document.querySelectorAll('.remove').forEach(b=> b.onclick = e=> { currentItems.splice(e.target.dataset.i,1); renderItems(); });
}

document.getElementById('supplier').addEventListener('change', function(){
  document.getElementById('supplierOther').style.display = (this.value==='__other') ? 'block':'none';
});

document.getElementById('addItem').addEventListener('click', function(){
  const desc = document.getElementById('prodDesc').value.trim();
  const qty = Number(document.getElementById('qty').value)||0;
  const unit = document.getElementById('unit').value.trim() || 'pcs';
  const budget = Number(document.getElementById('budget').value)||0;
  if(!desc || qty<=0){ alert('Fill description and positive qty'); return; }
  currentItems.push({ description: desc, quantity: qty, unit, budget });
  document.getElementById('prodDesc').value=''; document.getElementById('qty').value=1; document.getElementById('budget').value=0;
  renderItems();
});

document.getElementById('clearItems').addEventListener('click', ()=> { if(!confirm('Clear?')) return; currentItems=[]; renderItems(); });

document.getElementById('goToConfirm').addEventListener('click', ()=>{
  const sup = document.getElementById('supplier').value === '__other' ? document.getElementById('supplierOther').value.trim() : document.getElementById('supplier').value;
  if(!sup){ alert('Select supplier'); return; }
  if(currentItems.length===0){ alert('Add at least one item'); return; }
  // save order
  const orders = getOrders();
  const order = { id: uid(), supplier: sup, items: currentItems.slice(), status: 'pending', createdAt: new Date().toISOString() };
  orders.unshift(order); saveOrders(orders);
  localStorage.setItem('ss_checkout_id', order.id);
  // go to confirm page
  location.href = 'order_confirm.php';
});

renderItems();
</script>
</body>
</html>