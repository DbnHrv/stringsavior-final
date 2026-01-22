<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Orders — StringSavior</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f6f7fb;color:#222;padding:18px}</style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Orders</h4>
    <div>
      <a href="order_new.php" class="btn btn-primary">New Order</a>
    </div>
  </div>
  <div class="mb-3">
      <a href="music_store_owner_home.php" class="btn btn-outline-secondary">Dashboard</a>
  </div>
  <div class="card p-3 mb-3">
    <div class="small text-muted mb-2">Saved orders (local). Click View to inspect, Checkout to confirm.</div>
    <div class="table-responsive">
      <table class="table table-sm" id="ordersTable">
        <thead><tr><th>Order ID</th><th>Supplier</th><th>Date</th><th class="text-end">Total (₱)</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const LS_ORDERS = 'ss_orders_v1';
function fmt(n){ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
function getOrders(){ return JSON.parse(localStorage.getItem(LS_ORDERS) || '[]'); }
function saveOrders(list){ localStorage.setItem(LS_ORDERS, JSON.stringify(list || [])); }

function render(){
  const $body = document.querySelector('#ordersTable tbody');
  $body.innerHTML = '';
  const orders = getOrders();
  if(!orders.length){
    $body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No orders yet.</td></tr>';
    return;
  }
  orders.forEach(o=>{
    const total = o.items.reduce((s,it)=> s + Number(it.quantity)*Number(it.budget),0);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${o.id}</td>
      <td>${o.supplier||''}</td>
      <td>${new Date(o.createdAt).toLocaleString()}</td>
      <td class="text-end">₱${fmt(total)}</td>
      <td>${o.status === 'checked_out' ? '<span class="badge bg-success">Checked</span>' : '<span class="badge bg-secondary">Pending</span>'}</td>
      <td>
        <a class="btn btn-sm btn-outline-primary" href="order_confirm.php?id=${encodeURIComponent(o.id)}">View</a>
        <button class="btn btn-sm btn-outline-success checkout-btn" data-id="${o.id}" ${o.status==='checked_out'?'disabled':''}>Checkout</button>
        <button class="btn btn-sm btn-outline-danger del-btn" data-id="${o.id}">Delete</button>
      </td>`;
    $body.appendChild(tr);
  });
  // attach events
  document.querySelectorAll('.del-btn').forEach(b=> b.onclick = (e)=>{
    if(!confirm('Delete order?')) return;
    const id = e.target.dataset.id;
    let list = getOrders(); list = list.filter(x=>x.id!==id); saveOrders(list); render();
  });
  document.querySelectorAll('.checkout-btn').forEach(b=> b.onclick = (e)=>{
    const id = e.target.dataset.id;
    localStorage.setItem('ss_checkout_id', id);
    location.href = 'order_confirm.php';
  });
}

render();
</script>
</body>
</html>