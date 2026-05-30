@extends('layouts.app')

@section('content')

<div id="toast" class="app-toast"></div>

<style>
    .app-toast {
        position: fixed;
        bottom: 30px;
        right: 30px;
        min-width: 250px;
        padding: 14px 18px;
        border-radius: 10px;
        color: #fff;
        font-weight: 500;
        font-size: 14px;
        background: #111;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        z-index: 9999;
    }

    .app-toast.show {
        opacity: 1;
        transform: translateY(0);
    }

    .app-toast.success { background: #E37216; }
    .app-toast.error {
        background: #000;
        border: 1px solid #E37216;
    }

    .ads-card {
        border-radius: 16px;
        border: 1px solid #eee;
        box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    }

    .big-name {
        font-size: 18px;
        font-weight: 700;
    }

    .big-price {
        font-size: 20px;
        font-weight: 800;
        color: #E37216;
    }

    .small-qty {
        font-size: 13px;
        color: #666;
    }

    .table thead th {
        font-size: 13px;
        text-transform: uppercase;
        color: #999;
    }
</style>

<div class="container">

    <div class="row g-4">

        <!-- MARKET TABLE -->
        <div class="col-md-12">

            <div class="card ads-card p-3">

                <h5 class="fw-bold mb-3">
                    📊 Live Bybit P2P Market
                </h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Advertiser</th>
                                <th>Price</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>

                        <tbody id="marketTableBody">
                            <tr>
                                <td colspan="3">Loading market data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

    </div>

</div>

@endsection

<script>
document.addEventListener("DOMContentLoaded", function () {

    const API_URL = "{{ env('API_URL') }}";
    const API_KEY = "{{ auth()->user()->bybit_api_key }}";
    const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

    const marketTableBody = document.getElementById("marketTableBody");

    let timer = null;

    function showToast(msg, type = "success") {
        const toast = document.getElementById("toast");
        toast.className = "app-toast show " + type;
        toast.innerText = msg;

        setTimeout(() => {
            toast.className = "app-toast";
        }, 2000);
    }

    async function fetchMarket() {
        try {
            const res = await fetch(`http://127.0.0.1:8080/api/analyze-market`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    api_key: API_KEY,
                    api_secret: API_SECRET,
                    tokenId: "BTC",
                    currencyId: "USD",
                    side: "0",
                    minAmount: 0,
                    marginPct: 4,
                    limit: 30
                })
            });

            const data = await res.json();

            if (!data.status) {
                showToast("Failed to load market", "error");
                return;
            }

            renderTable(data.top_10_competitors);

        } catch (err) {
            console.error(err);
            showToast("Network error", "error");
        }
    }

    function renderTable(items) {

        if (!items || items.length === 0) {
            marketTableBody.innerHTML = `
                <tr>
                    <td colspan="3">No market data found</td>
                </tr>`;
            return;
        }

        marketTableBody.innerHTML = "";

        items.forEach(item => {
            marketTableBody.innerHTML += `
                <tr>
                    <td class="big-name">${item.nickName}</td>

                    <td class="big-price">
                        ₦ ${parseFloat(item.price).toLocaleString()}
                    </td>

                    <td class="small-qty">
                        ${item.quantity}
                    </td>
                </tr>
            `;
        });
    }

    function startLive() {
        fetchMarket();

        timer = setInterval(() => {
            fetchMarket();
        }, 3000);
    }

    startLive();

});
</script>