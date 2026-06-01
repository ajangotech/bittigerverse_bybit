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

    .app-toast.success {
        background: #E37216;
    }

    .app-toast.error {
        background: #000;
        border: 1px solid #E37216;
    }
    .ads-card {
        border-radius: 16px;
        border: 1px solid #eee;
        box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    }

    .price-box {
        font-size: 1.5rem;
        font-weight: 700;
        color: #E37216;
    }

    .mini-info {
        font-size: 12px;
        color: #888;
    }

    .fast-input {
        font-size: 18px;
        font-weight: 600;
    }
</style>

<div class="container">

    <div class="row g-4">

        <!-- LEFT: ADS LIST -->
        <div class="col-md-7">

            <div class="card ads-card p-3">
                <h5 class="fw-bold mb-3">All Advertisements (Live API)</h5>

                <select id="adsSelect" class="form-select form-select-lg">
                    <option value="">Loading ads...</option>
                </select>

                <div class="mt-4 p-3 border rounded-3 bg-light">
                    <div class="mini-info">Selected Ad Info</div>

                    <div class="mt-2">
                        <div><b>Pair:</b> <span id="pairText">---</span></div>
                        <div><b>Min:</b> <span id="minText">---</span> | <b>Max:</b> <span id="maxText">---</span></div>
                        <div><b>Price:</b> <span id="currentPrice">---</span></div>
                        <div><b>Status:</b> <span id="statusText">---</span></div>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-5">

            <div class="card ads-card p-4 border-0 shadow-sm rounded-4">
                <h5 class="fw-bold mb-4">Quick Price Update</h5>

                <input type="hidden" id="adId">

                <div class="form-group mb-4">
                    <label class="form-label fw-semibold text-muted text-uppercase small mb-2">
                        Set New Price
                    </label>
                    
                    <div class="input-group input-group-lg shadow-sm rounded-3">
                        <span class="input-group-text bg-white border-end-0 fs-3 fw-bold text-black-50">
                            ₦
                        </span>
                        
                        <input 
                            type="number" 
                            id="priceInput" 
                            class="form-control border-start-0 ps-0 fast-input fs-2 fw-bolder text-dark"
                            placeholder="0.00"
                            style="box-shadow: none;" 
                        >
                    </div>
                </div>

                <button id="updateBtn" class="btn btn-lg w-100 mt-2 text-white fw-bold rounded-3 shadow-sm" style="background:#E37216;">
                    Update Price
                </button>

                <div class="mt-3 text-muted small text-center">
                    Updates are sent directly to the API.
                </div>
            </div>

        </div>
    </div>

    <div class="row g-4">

        <!-- LEFT: ADS LIST -->
        <div class="col-md-7">

            <div class="card ads-card p-3">
                <h5 class="fw-bold mb-3">Auto Select Update Price Ads (Live API)</h5>

                <select id="autoselectUpdateAds" class="form-select form-select-lg">
                    <option value="">Loading ads...</option>
                </select>

                <div class="mt-4 p-3 border rounded-3 bg-light">
                    
                </div>
            </div>

        </div>

        
    </div>

    
</div>

@endsection

<script>
    document.addEventListener("DOMContentLoaded", function () {

        // =====================
        // CONFIG
        // =====================
        const API_URL = "{{ $apiUrl }}";
        const API_KEY = "{{ auth()->user()->bybit_api_key }}";
        const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

        // =====================
        // DOM ELEMENTS
        // =====================
        const select = document.getElementById('adsSelect');
        const priceInput = document.getElementById('priceInput');
        const adId = document.getElementById('adId');

        const pairText = document.getElementById('pairText');
        const minText = document.getElementById('minText');
        const maxText = document.getElementById('maxText');
        const currentPrice = document.getElementById('currentPrice');
        const statusText = document.getElementById('statusText');

        let adsData = [];
        let debounceTimer = null;
        let lastSubmittedPrice = null;

        const autoSelect = document.getElementById('autoselectUpdateAds');
        let marketData = null;
        let autoUpdateInterval = null;

        if (!select) {
            console.error("adsSelect not found");
            return;
        }

        // =====================
        // TOAST
        // =====================
        function showToast(message, type = "success") {
            const toast = document.getElementById("toast");

            toast.className = "app-toast show " + type;
            toast.innerText = message;

            setTimeout(() => {
                toast.className = "app-toast";
            }, 2500);
        }

        async function fetchMarketAnalysis() {
            try {
                const res = await fetch(`${API_URL}/analyze-market`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        api_key: API_KEY,
                        api_secret: API_SECRET,
                        tokenId: "BTC",
                        currencyId: "USD",
                        side: "0",
                        minAmount: 0,
                        marginPct: 4
                    })
                });

                const data = await res.json();

                if (!data.status) return;

                marketData = data;

                renderMarketCompetitors(data.top_10_competitors);

            } catch (err) {
                console.error("Market fetch error:", err);
            }
        }

        function renderMarketCompetitors(items) {
            if (!autoSelect) return;

            autoSelect.innerHTML = `<option value="">-- Select Competitor Price --</option>`;

            items.forEach((item, index) => {
                autoSelect.innerHTML += `
                    <option 
                        value="${item.price}" 
                        data-id="${item.id}">
                        #${index + 1} | ${item.nickName} | $${item.price}
                    </option>
                `;
            });
        }

        function startMarketPolling() {
            fetchMarketAnalysis(); // first run immediately

            autoUpdateInterval = setInterval(() => {
                fetchMarketAnalysis();
            }, 3000);
        }

        startMarketPolling();

        autoSelect.addEventListener('change', function () {

            const selectedPrice = parseFloat(this.value);

            if (!selectedPrice || isNaN(selectedPrice)) return;

            // auto fill input
            priceInput.value = selectedPrice;
            currentPrice.innerText = selectedPrice;

            // auto trigger update
            updatePrice(selectedPrice);
        });

        // =====================
        // LOAD ADS
        // =====================
        async function loadAds() {
            try {
                const res = await fetch(`${API_URL}/ads`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        api_key: API_KEY,
                        api_secret: API_SECRET
                    })
                });

                const data = await res.json();

                adsData = data?.result?.items || [];

                select.innerHTML = `<option value="">-- Select Ad --</option>`;

                adsData.forEach(ad => {
                    select.innerHTML += `
                        <option value="${ad.id}">
                            ${ad.tokenId}/${ad.currencyId} | ${ad.price}
                        </option>
                    `;
                });

            } catch (err) {
                console.error("Failed to load ads", err);
                showToast("Failed to load ads", "error");
            }
        }

        loadAds();

        // =====================
        // SELECT AD
        // =====================
        select.addEventListener('change', function () {

            const selectedId = String(this.value); 

            const ad = adsData.find(a => String(a.id) === selectedId);


            if (!ad) {
                console.error("Ads not found", selectedId, adsData);
                showToast("Ad not found", "error");
                return;
            }

            adId.value = ad.id;

            currentPrice.innerText = ad.price;

            pairText.innerText = `${ad.tokenId}/${ad.currencyId}`;
            minText.innerText = ad.minAmount ?? '---';
            maxText.innerText = ad.maxAmount ?? '---';
            statusText.innerText = ad.showStatus ?? ad.status ?? '---';

            setTimeout(() => {
                priceInput.focus();
                priceInput.select();
            }, 100);
        });

        // =====================
        // AUTO UPDATE ON INPUT (PASTE + TYPE)
        // =====================
        priceInput.addEventListener('input', function () {

            const value = parseFloat(this.value);

            // update UI instantly
            currentPrice.innerText = this.value || '---';

            // validation
            if (!adId.value || isNaN(value)) return;

            // prevent duplicate API calls
            if (value === lastSubmittedPrice) return;

            clearTimeout(debounceTimer);

            debounceTimer = setTimeout(() => {
                updatePrice(value);
            }, 500); // smooth + fast
        });

        // =====================
        // UPDATE PRICE
        // =====================
        async function updatePrice(newPrice) {

            const ad = adsData.find(a => a.id === adId.value);
            if (!ad) {
                showToast("Ad not found", "error");
                return;
            }

            const payload = {
                ...ad,
                price: parseFloat(newPrice),
                api_key: API_KEY,
                api_secret: API_SECRET
            };

            try {
                const res = await fetch(`${API_URL}/update-ad`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();

                if (res.ok && !result.error) {
                    lastSubmittedPrice = newPrice;
                    currentPrice.innerText = newPrice;

                    showToast("Price Updated", "success");
                    priceInput.value = ''
                } else {
                    showToast("Update failed", "error");
                    priceInput.value = ''
                }

            } catch (err) {
                console.error("Update failed");
                showToast("Network error", "error");
                priceInput.value = ''
            }
        }

    });
</script>