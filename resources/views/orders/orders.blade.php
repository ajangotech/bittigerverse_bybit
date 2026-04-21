@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">

            <!-- HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Orders Management</h5>

                <select id="orderType" class="form-select w-auto">
                    <option value="pending">Pending Orders</option>
                    <option value="all">All Orders</option>
                </select>
            </div>

            <!-- LOADER -->
            <div id="loader" class="text-center py-5 d-none">
                <div class="spinner-border" style="color:#E37216;"></div>
                <p class="mt-2 text-muted">Fetching orders...</p>
            </div>

            <!-- TABLE -->
            <div class="table-responsive">
                <table id="ordersTable" class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Token</th>
                            <th>Amount</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody"></tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<style>
    body { font-family: 'Inter', sans-serif; }

    .rounded-4 { border-radius: 1rem !important; }

    .table td, .table th {
        padding: 1rem;
    }
</style>

<script>
const API_URL = "{{ env('API_URL') }}";
const API_KEY = "{{ auth()->user()->bybit_api_key }}";
const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

let table;

// ==========================
// INIT
// ==========================
document.addEventListener("DOMContentLoaded", () => {
    initTable();
    loadOrders("pending");

    document.getElementById('orderType').addEventListener('change', function () {
        const type = this.value;
        loadOrders(type);
    });
});

// ==========================
// INIT DATATABLE
// ==========================
function initTable() {
    table = $('#ordersTable').DataTable({
        pageLength: 10,
        ordering: true,
        searching: true,
        destroy: true
    });
}

// ==========================
// LOAD ORDERS
// ==========================
async function loadOrders(type = "pending") {

    const loader = document.getElementById('loader');
    const tbody = document.getElementById('ordersTableBody');

    loader.classList.remove('d-none');
    tbody.innerHTML = "";

    try {

        const endpoint = type === "pending"
            ? `${API_URL}/pending-orders`
            : `${API_URL}/orders`;

        const res = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                api_key: API_KEY,
                api_secret: API_SECRET,
                page: "1",
                size: "10"
            })
        });

        const data = await res.json();

        const items = data?.result?.items || [];

        table.clear();

        items.forEach(order => {

            const total = (parseFloat(order.amount) * parseFloat(order.price)).toFixed(2);

            const statusBadge = getStatusBadge(order.status);

            table.row.add([
                order.id || '---',
                `${order.tokenId}/${order.currencyId}`,
                order.amount,
                `₦${order.price}`,
                `₦${total}`,
                statusBadge,
                formatDate(order.createDate)
            ]);
        });

        table.draw();

    } catch (err) {
        console.error("Error loading orders:", err);
        alert("Failed to load orders");
    } finally {
        loader.classList.add('d-none');
    }
}

// ==========================
// STATUS BADGE
// ==========================
function getStatusBadge(status) {

    const map = {
        10: '<span class="badge bg-warning text-dark">Pending</span>',
        20: '<span class="badge bg-success">Completed</span>',
        30: '<span class="badge bg-danger">Cancelled</span>'
    };

    return map[status] || `<span class="badge bg-secondary">${status}</span>`;
}

// ==========================
// FORMAT DATE
// ==========================
function formatDate(timestamp) {
    if (!timestamp) return '---';

    const date = new Date(parseInt(timestamp));
    return date.toLocaleString();
}
</script>

@endsection