@extends('layouts.app')

@section('content')

<div class="container-fluid py-4">

    <div class="row g-4">

        <!-- MAIN CONTENT -->
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">

                    <!-- ACTIONS -->
                    <div class="d-flex gap-2 mb-4">
                        <button id="refreshBtn" class="btn text-white px-4"
                            style="background:#E37216; border:none;">
                            
                            <span id="refreshText">Refresh Ads</span>
                            <span id="refreshSpinner" class="spinner-border spinner-border-sm d-none"></span>
                        </button>

                        <a href="{{ route('dashboard.payments') }}" class="btn btn-dark px-4">
                            Payment IDs
                        </a>
                    </div>

                    <!-- HEADER -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Live Advertisements (API)</h5>
                        <small class="text-muted">Fetched from Bybit API</small>
                    </div>

                    <!-- TABLE -->
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="adsTable">

                            <thead class="text-muted small text-uppercase">
                                <tr>
                                    <th>Pair</th>
                                    <th>Price</th>
                                    <th>Limit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody id="adsTableBody">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Loading ads...
                                    </td>
                                </tr>
                            </tbody>

                        </table>
                    </div>

                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="col-lg-4">

            <div class="position-sticky" style="top: 20px;">

                <!-- STATS -->
                <div class="row g-3 mb-4">

                    <div class="col-6">
                        <div class="card p-3 border-0 shadow-sm rounded-4 text-center">
                            <small class="text-muted">Total Users</small>
                            <h4 class="fw-bold mt-1">{{ $usersCount }}</h4>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="card p-3 border-0 shadow-sm rounded-4 text-center">
                            <small class="text-muted">Total Ads (Live)</small>
                            <h4 class="fw-bold mt-1 text-primary" id="adsCount">0</h4>
                        </div>
                    </div>

                </div>

                <!-- QUICK ACTIONS -->
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h6 class="fw-bold mb-3">Quick Actions</h6>

                    <div class="d-grid gap-2">

                        <a href="/dashboard/users" class="btn btn-light text-start">
                            Manage Users
                        </a>

                        <a href="/dashboard/ads" class="btn btn-light text-start">
                            Manage Ads
                        </a>

                        <button onclick="loadAds()" class="btn btn-light text-start">
                            Reload API Data
                        </button>

                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

@endsection


{{-- ===================== --}}
{{-- STYLES --}}
{{-- ===================== --}}
@push('styles')
<style>
    body { font-family: Inter, sans-serif; }

    .rounded-4 { border-radius: 1rem !important; }

    .table td { padding: 0.9rem; }

    .badge {
        font-size: 12px;
        padding: 6px 10px;
    }
</style>
@endpush


{{-- ===================== --}}
{{-- SCRIPT --}}
{{-- ===================== --}}
@push('scripts')

<script>

const API_URL = "{{ auth()->user()->api_url }}";
const API_KEY = "{{ auth()->user()->bybit_api_key }}";
const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

let adsData = [];

// =====================
// LOADING UI
// =====================
function setLoading(state) {
    const btn = document.getElementById("refreshBtn");
    const text = document.getElementById("refreshText");
    const spinner = document.getElementById("refreshSpinner");

    btn.disabled = state;

    if (state) {
        spinner.classList.remove("d-none");
        text.innerText = "Loading...";
    } else {
        spinner.classList.add("d-none");
        text.innerText = "Refresh Ads";
    }
}

// =====================
// FETCH ADS FROM API
// =====================
async function loadAds() {

    setLoading(true);

    try {
        const res = await fetch(`${API_URL}/ads`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                api_key: API_KEY,
                api_secret: API_SECRET
            })
        });

        const data = await res.json();

        adsData = data?.result?.items || [];

        renderTable();

        // update count
        document.getElementById("adsCount").innerText = adsData.length;

    } catch (err) {
        console.error(err);

        document.getElementById("adsTableBody").innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger py-4">
                    Failed to load ads
                </td>
            </tr>
        `;
    }

    setLoading(false);
}

// =====================
// RENDER TABLE
// =====================
function renderTable() {

    const tbody = document.getElementById("adsTableBody");

    if (!adsData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">
                    No ads found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = "";

    adsData.forEach(ad => {

        const status = ad.showStatus || (ad.isOnline ? "ONLINE" : "OFFLINE");

        let color = "secondary";
        if (status === "ONLINE") color = "success";
        else if (status === "HIDDEN") color = "warning";
        else if (status === "OFFLINE") color = "dark";

        tbody.innerHTML += `
            <tr>

                <td>
                    <div class="fw-bold">${ad.tokenId}/${ad.currencyId}</div>
                    <small class="text-muted">${ad.id}</small>
                </td>

                <td>
                    <span class="fw-bold text-success">
                        ₦${parseFloat(ad.price).toLocaleString()}
                    </span>
                </td>

                <td>
                    <small>
                        ${ad.minAmount || '-'} - ${ad.maxAmount || '-'}
                    </small>
                </td>

                <td>
                    <span class="badge bg-${color}">
                        ${status}
                    </span>
                </td>

            </tr>
        `;
    });
}



// =====================
// EDIT HOOK (OPTIONAL)
// =====================
function editAd(id) {
    const ad = adsData.find(a => a.id === id);
    console.log("Selected Ad:", ad);
}

// =====================
// INIT
// =====================
document.addEventListener("DOMContentLoaded", loadAds);

document.getElementById("refreshBtn").addEventListener("click", loadAds);

</script>

@endpush