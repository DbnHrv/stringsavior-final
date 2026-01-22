<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Confirm Order — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f6f7fb;padding:18px}</style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h4>Confirming Order</h4>
    <div>
      <a href="order.php" class="btn btn-outline-secondary">Back</a>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <div id="orderSummary"></div>
    <div class="text-end mt-3">
      <button id="cancelBtn" class="btn btn-outline-secondary">Cancel</button>
      <button id="confirmBtn" class="btn btn-primary">Order Now</button>
    </div>
  </div>
</div>

<script>
const LS_ORDERS = 'ss_orders_v1';
function getOrders(){ return JSON.parse(localStorage.getItem(LS_ORDERS) || '[]'); }
function saveOrders(list){ localStorage.setItem(LS_ORDERS, JSON.stringify(list || [])); }
function fmt(n){ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }

const checkoutId = (new URLSearchParams(location.search)).get('id') || localStorage.getItem('ss_checkout_id');
if(!checkoutId){ alert('No order selected'); location.href='orders.php'; }

const orders = getOrders();
const order = orders.find(o=>o.id===checkoutId);
if(!order){ alert('Order not found'); location.href='orders.php'; }

const container = document.getElementById('orderSummary');
let html = `<div class="small text-muted">Order ID: ${order.id}</div>
  <div class="small text-muted">Supplier: ${order.supplier}</div>
  <div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th class="text-end">Amount</th></tr></thead><tbody>`;
let subtotal = 0;
order.items.forEach(it=>{
  const amt = Number(it.quantity)*Number(it.budget); subtotal += amt;
  html += `<tr><td>${it.description}</td><td>${it.quantity}</td><td>${it.unit}</td><td class="text-end">₱${fmt(amt)}</td></tr>`;
});
html += `</tbody></table></div>
  <div class="text-end"><strong>Subtotal</strong> ₱${fmt(subtotal)}</div>`;
container.innerHTML = html;

document.getElementById('cancelBtn').addEventListener('click', ()=> { if(confirm('Cancel and return?')) location.href='order.php'; });

document.getElementById('confirmBtn').addEventListener('click', ()=>{
  if(!confirm('Confirm this order? This marks it as checked out locally.')) return;
  // mark checked_out
  const idx = orders.findIndex(o=>o.id===order.id);
  if(idx!==-1){ orders[idx].status='checked_out'; saveOrders(orders); }
  // optionally: send to server via fetch('/api/place_order.php') -- implement server endpoint later
  localStorage.removeItem('ss_checkout_id');
  alert('Order confirmed (local).');
  location.href='order.php';
});
</script>
</body>
</html>