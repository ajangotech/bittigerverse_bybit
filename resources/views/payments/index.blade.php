@extends('layouts.app')

@section('content')

<div class="container py-4">

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">My Payment Methods</h5>
                    <small class="text-muted">Fetched from Bybit API</small>
                </div>
                <span class="badge bg-primary" id="totalCount">0</span>
            </div>

            <!-- Loading -->
            <div id="loading" class="text-center py-4">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">Loading payment methods...</p>
            </div>

            <!-- Content -->
            <div id="paymentContainer" class="row g-3 d-none"></div>

            <!-- Empty -->
            <div id="emptyState" class="text-center py-4 d-none">
                <i class="bi bi-wallet2 fs-1 text-muted"></i>
                <p class="text-muted">No payment methods found </p>
            </div>

        </div>
    </div>

</div>

<script>
const API_URL = "{{ $apiUrl }}/payment-types"; 
const API_KEY = "{{ auth()->user()->bybit_api_key }}";
const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

document.addEventListener("DOMContentLoaded", async () => {
    await loadPayments();
});

async function loadPayments() {
    try {
        const res = await fetch(API_URL, {
            method: "POST", // IMPORTANT (API usually requires POST for auth)
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                api_key: API_KEY,
                api_secret: API_SECRET
            })
        });

        const data = await res.json();

        const container = document.getElementById('paymentContainer');
        const loading = document.getElementById('loading');
        const empty = document.getElementById('emptyState');
        const count = document.getElementById('totalCount');

        loading.classList.add('d-none');

        if (!data.result || data.result.length === 0) {
            empty.classList.remove('d-none');
            return;
        }

        count.innerText = data.result.length;
        container.classList.remove('d-none');

        container.innerHTML = ""; // prevent duplicates

        data.result.forEach(item => {
            container.innerHTML += renderCard(item);
        });

    } catch (err) {
        console.error(err);
        document.getElementById('loading').innerHTML =
            "<p class='text-danger'>Failed to load data</p>";
    }
}

function renderCard(item) {

    let type = item.paymentConfigVo?.paymentName || "Unknown";

    return `
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">${type}</h6>
                    <span class="badge ${item.online == 1 ? 'bg-success' : 'bg-secondary'}">
                        ${item.online == 1 ? 'Active' : 'Inactive'}
                    </span>
                </div>

                <hr>

                <div class="small">

                    <p class="mb-1">
                        <strong>Payment ID:</strong> ${item.id}
                    </p>

                    ${item.accountNo ? `
                    <p class="mb-1">
                        <strong>Account:</strong> ${item.accountNo}
                    </p>` : ''}

                    ${item.bankName ? `
                    <p class="mb-1">
                        <strong>Bank:</strong> ${item.bankName}
                    </p>` : ''}

                    ${item.realName ? `
                    <p class="mb-1">
                        <strong>Name:</strong> ${item.realName}
                    </p>` : ''}

                    ${item.payMessage ? `
                    <p class="mb-1 text-muted">
                        ${item.payMessage}
                    </p>` : ''}

                </div>

            </div>
        </div>
    </div>
    `;
}
</script>

@endsection