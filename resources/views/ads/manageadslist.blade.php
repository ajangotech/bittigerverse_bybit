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

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Select Token</label>
                        <select class="form-control" id="tokenSelect">
                            <option value="USDT">USDT</option>
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                            <option value="BNB">BNB</option>
                            <option value="SOL">SOL</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Select Currency</label>
                        <select class="form-control" id="currencySelect">
                            <option value="USD">USD</option>
                            <option value="NGN">NGN</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="CNY">CNY</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-control" id="sortBySelect">
                            <option value="price">Price</option>
                            <option value="orders">Orders</option>
                            <option value="completion_rate">Completion Rate</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button style="background:#E37216; border:none;" class="btn text-white w-100" id="filterBtn">
                            Filter
                        </button>
                    </div>
                </div>

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

        const API_URL = "{{ auth()->user()->api_url }}";
        const API_KEY = "{{ auth()->user()->bybit_api_key }}";
        const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

        const tokenSelect = document.getElementById("tokenSelect");
        const currencySelect = document.getElementById("currencySelect");
        const sortBySelect = document.getElementById("sortBySelect");
        const filterBtn = document.getElementById("filterBtn");
        const marketTableBody = document.getElementById("marketTableBody");



        let selectedToken = "USDT";
        let selectedCurrency = "USD";
        let selectedSortBy = "price";
        let timer = null;

        function showToast(msg, type = "success") {
            const toast = document.getElementById("toast");
            toast.className = "app-toast show " + type;
            toast.innerText = msg;
            setTimeout(() => {
                toast.className = "app-toast";
            }, 2000);
        }


        // Point fetch to your internal Laravel proxy endpoint
        const PROXY_API_URL = "/bybit/p2p-market";

        async function fetchMarket() {
            try {
                const res = await fetch(PROXY_API_URL, {
                    method: "POST",
                    headers: { 
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        tokenId: selectedToken,
                        currencyId: selectedCurrency,
                        side: "0",
                        page: "1",
                        rows: "30"
                    })
                });

                const data = await res.json();

                if (data.retCode !== 0 && !data.result) {
                    showToast("Failed to load market", "error");
                    return;
                }

                let items = data.result?.items || data.result || [];

                // Client-side sorting
                if (selectedSortBy === "price") {
                    items.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
                } else if (selectedSortBy === "orders") {
                    items.sort((a, b) => (parseInt(b.totalOrderNum) || 0) - (parseInt(a.totalOrderNum) || 0));
                } else if (selectedSortBy === "completion_rate") {
                    items.sort((a, b) => (parseFloat(b.recentExecuteRate) || 0) - (parseFloat(a.recentExecuteRate) || 0));
                }

                renderTable(items);

            } catch (err) {
                console.error(err);
                showToast("Network error", "error");
            }
        }

        /*
        async function fetchMarket() {
            try {
                const res = await fetch('https://www.bybitglobal.com/x-api/fiat/otc/item/recommend/online', {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({

                        api_key: API_KEY,
                        api_secret: API_SECRET,
                        tokenId: selectedToken,
                        currencyId: selectedCurrency,
                        side: "0",
                        minAmount: 0,
                        marginPct: 4,
                        limit: 30,
                        sortBy: selectedSortBy

                    })

                });



                const data = await res.json();

                // Make sure to access the correct array depending on your Python endpoint's JSON response format
                const items = data.top_10_competitors || data;

                if (!items || (data.status === false && !data.top_10_competitors) || data.error) {
                    showToast("Failed to load market", "error");
                    return;
                }
                renderTable(items);

            } catch (err) {
                console.error(err);
                showToast("Network error", "error");
            }
        }

        */

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
                        <td class="big-name">${item.nickName || item.nickname}</td>
                        <td class="big-price">
                            ${parseFloat(item.price).toLocaleString()}
                        </td>

                        <td class="small-qty">
                            ${item.quantity || 'N/A'}
                        </td>
                    </tr>
                `;
            });
        }



        filterBtn.addEventListener("click", function () {
            selectedToken = tokenSelect.value;
            selectedCurrency = currencySelect.value;
            selectedSortBy = sortBySelect.value;

            showToast(
                `Loading ${selectedToken}/${selectedCurrency} sorted by ${selectedSortBy}...`
            );
            startLive();
        });

        function startLive() {
            if (timer) {
                clearInterval(timer);
            }
            fetchMarket();
            timer = setInterval(() => {
                fetchMarket();
            }, 2000);
        }
        startLive();
    });

</script>

