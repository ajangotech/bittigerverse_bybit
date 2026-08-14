@extends('layouts.app')

@section('content')

<div id="toast" class="app-toast"></div>

<style>
    .app-toast {
        position: fixed;
        right: 30px;
        bottom: 30px;
        z-index: 9999;
        padding: 15px 20px;
        border-radius: 10px;
        color: #fff;
        opacity: 0;
        transition: .3s;
        background: #111;
    }

    .app-toast.show {
        opacity: 1;
    }

    .app-toast.success {
        background: #E37216;
    }

    .app-toast.error {
        background: #dc3545;
    }

    .ads-card {
        border: none;
        border-radius: 18px;
        box-shadow: 0 5px 20px rgba(0,0,0,.08);
    }

    .price-box {
        font-size: 30px;
        font-weight: bold;
        color: #E37216;
    }
    
    .plus-panel {
        background-color: #fffdfa;
        border: 2px solid #E37216;
        border-radius: 10px;
    }
</style>

<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        My Advertisements
                    </h5>

                    <select class="form-select form-select-lg mb-4" id="adsSelect">
                        <option>Loading...</option>
                    </select>

                    <input type="hidden" id="adId">

                    <div class="border rounded p-3">
                        <p><b>Pair:</b> <span id="pairText">---</span></p>
                        <p><b>Current Price:</b> <span id="currentPrice" class="text-success fw-bold">---</span></p>
                        <p><b>Min:</b> <span id="minText">---</span></p>
                        <p><b>Max:</b> <span id="maxText">---</span></p>
                        <p><b>Status:</b> <span id="statusText">---</span></p>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        Competitor
                        <i class="bi bi-person-badge"></i>
                    </h5>

                    <select class="form-select form-select-lg mb-3" id="merchantSelect">
                        <option value="">Loading competitors...</option>
                    </select>

                    <!-- 🚀 Competitor Plus Panel -->
                    <div class="p-3 mb-3 plus-panel">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="plusModeToggle">
                            <label class="form-check-label fw-bold text-dark" for="plusModeToggle">
                                🚀 Enable Competitor Plus
                            </label>
                        </div>
                        <label class="small text-muted mb-1">Set Re-edit Timer:</label>
                        <select class="form-select form-select-sm" id="plusTimer">
                            <option value="1000">1.0 Second</option>
                            <option value="1500">1.5 Seconds</option>
                            <option value="2000">2.0 Seconds</option>
                            <option value="2500">2.5 Seconds</option>
                            <option value="3000" selected>3.0 Seconds</option>
                            <option value="3500">3.5 Seconds</option>
                            <option value="4000">4.0 Seconds</option>
                            <option value="4500">4.5 Seconds</option>
                            <option value="5000">5.0 Seconds</option>
                            <option value="5500">5.5 Seconds</option>
                            <option value="6000">6.0 Seconds</option>
                        </select>
                    </div>

                    <div class="border rounded p-3">
                        <p><b>Merchant:</b> <span id="merchantName">---</span></p>
                        <p><b>Merchant Price:</b> <span id="merchantPrice" class="text-primary fw-bold">---</span></p>
                        <p><b>Tracking:</b> <span id="trackingStatus">Stopped</span></p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const API_URL = "{{ auth()->user()->api_url }}";
        const API_KEY = "{{ auth()->user()->bybit_api_key }}";
        const API_SECRET = "{{ auth()->user()->bybit_api_secret }}";

        const adsSelect = document.getElementById('adsSelect');
        const merchantSelect = document.getElementById('merchantSelect');
        const plusModeToggle = document.getElementById('plusModeToggle');
        const plusTimer = document.getElementById('plusTimer');

        let adsData = [];
        let competitors = [];

        let selectedMerchantId = null;
        let selectedMerchantName = null;

        let referencePrice = null;
        let lastMerchantPrice = null;

        let selectedToken = null;
        let selectedCurrency = null;

        let tracking = false;

        // Helper: Async Sleep
        const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        /*
        |--------------------------------------------------------------------------
        | Toast Notifications
        |--------------------------------------------------------------------------
        */
        function toast(message, type = 'success') {
            const t = document.getElementById('toast');
            t.innerHTML = message;
            t.className = `app-toast show ${type}`;
            setTimeout(() => { t.className = 'app-toast'; }, 3000);
        }

        /*
        |--------------------------------------------------------------------------
        | Load Advertisements
        |--------------------------------------------------------------------------
        */
        async function loadAds() {
            try {
                const res = await fetch(`${API_URL}/ads`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ api_key: API_KEY, api_secret: API_SECRET })
                });
                const data = await res.json();
                adsData = data?.result?.items || [];

                adsSelect.innerHTML = `<option value="">Select Advertisement</option>`;
                adsData.forEach(ad => {
                    adsSelect.innerHTML += `
                        <option value="${ad.id}">
                            ${ad.tokenId}/${ad.currencyId} | ${ad.price}
                        </option>
                    `;
                });
            } catch (e) {
                toast('Failed to load advertisements.', 'error');
            }
        }
        loadAds();

        /*
        |--------------------------------------------------------------------------
        | Advertisement Selection Handler
        |--------------------------------------------------------------------------
        */
        adsSelect.addEventListener('change', async function () {
            const ad = adsData.find(x => String(x.id) === String(this.value));

            if (!ad) {
                selectedToken = null;
                selectedCurrency = null;
                merchantSelect.innerHTML = `<option value="">Select Advertisement First</option>`;
                return;
            }

            selectedToken = ad.tokenId;
            selectedCurrency = ad.currencyId;
            document.getElementById('adId').value = ad.id;
            document.getElementById('pairText').innerHTML = `${ad.tokenId}/${ad.currencyId}`;
            document.getElementById('currentPrice').innerHTML = ad.price;
            document.getElementById('minText').innerHTML = ad.minAmount;
            document.getElementById('maxText').innerHTML = ad.maxAmount;
            document.getElementById('statusText').innerHTML = ad.status ?? '---';

            // Reset Tracking parameters
            selectedMerchantId = null;
            selectedMerchantName = null;
            referencePrice = null;
            lastMerchantPrice = null;
            tracking = false;

            document.getElementById('merchantName').innerHTML = '---';
            document.getElementById('merchantPrice').innerHTML = '---';
            document.getElementById('trackingStatus').innerHTML = 'Stopped';

            merchantSelect.innerHTML = `<option value="">Loading competitors...</option>`;
            await fetchCompetitors();
        });

        /*
        |--------------------------------------------------------------------------
        | Fetch Competitors
        |--------------------------------------------------------------------------
        */
        async function fetchCompetitors() {
            if (!selectedToken || !selectedCurrency) return;

            try {
                const res = await fetch(`${API_URL}/analyze-market`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        api_key: API_KEY,
                        api_secret: API_SECRET,
                        tokenId: selectedToken,
                        currencyId: selectedCurrency,
                        side: '0',
                        marginPct: 4
                    })
                });

                const data = await res.json();
                if (!data.status) return;

                competitors = data.top_10_competitors || [];
                renderCompetitors();
            } catch (e) {
                console.error("Error fetching competitors:", e);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Render Competitors Dropdown
        |--------------------------------------------------------------------------
        */
        function renderCompetitors() {
            const selected = selectedMerchantId;
            merchantSelect.innerHTML = `<option value="">Select Merchant</option>`;

            competitors.forEach((merchant, index) => {
                merchantSelect.innerHTML += `
                    <option value="${merchant.id}" data-price="${merchant.price}" data-name="${merchant.nickName}" ${selected == merchant.id ? 'selected' : ''}>
                        #${index + 1} | ${merchant.nickName} | ${merchant.price}
                    </option>
                `;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Merchant Selection Handler
        |--------------------------------------------------------------------------
        */
        merchantSelect.addEventListener('change', async function () {
            const option = this.options[this.selectedIndex];
            if (!option.value) return;

            selectedMerchantId = option.value;
            selectedMerchantName = option.dataset.name;
            referencePrice = parseFloat(option.dataset.price);
            lastMerchantPrice = referencePrice;
            tracking = true;

            document.getElementById('merchantName').innerHTML = selectedMerchantName;
            document.getElementById('merchantPrice').innerHTML = referencePrice;
            document.getElementById('trackingStatus').innerHTML = 'Tracking Initiated';

            // Store tracking initiation in DB
            await fetch("{{ route('dashboard.com.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    merchant_id: selectedMerchantId,
                    username: selectedMerchantName,
                    price: referencePrice
                })
            }).catch(e => console.error(e));

            // Sync ad immediately upon selection
            await executeCompetitorPlusLogic();
            toast(`Tracking ${selectedMerchantName}`);
        });

        /*
        |--------------------------------------------------------------------------
        | 🚀 COMPETITOR PLUS LOGIC (Dynamic Up & Down Tracking)
        |--------------------------------------------------------------------------
        */
        async function executeCompetitorPlusLogic() {
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));
            
            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = '<span class="text-warning">Plus: Merchant not in Top 10</span>';
                return;
            }

            const targetPrice = parseFloat(merchant.price);
            document.getElementById('merchantPrice').innerHTML = targetPrice;

            const currentAd = adsData.find(x => String(x.id) === String(document.getElementById('adId').value));
            if (!currentAd) return;

            const currentAdPrice = parseFloat(currentAd.price);

            // GUARD 1: If current ad price already matches competitor price, DO NOTHING.
            if (currentAdPrice === targetPrice) {
                document.getElementById('trackingStatus').innerHTML = `<span class="text-success fw-bold">Synced (${targetPrice})</span>`;
                return;
            }

            // GUARD 2: Price changed! Attempt to update with a maximum of 3 retries.
            document.getElementById('trackingStatus').innerHTML = `<span class="text-info fw-bold">Updating to ${targetPrice}...</span>`;

            let success = false;
            let retries = 0;
            const maxRetries = 3;

            while (!success && retries < maxRetries && tracking && plusModeToggle.checked) {
                if (retries > 0) {
                    document.getElementById('trackingStatus').innerHTML = `<span class="text-danger">Failed. Retry ${retries}/${maxRetries} in 1s...</span>`;
                    await sleep(1000);
                    await fetchCompetitors(); // Re-fetch market before retrying
                }

                retries++;
                
                // Get fresh merchant price in case it shifted during re-fetch
                const updatedMerchant = competitors.find(x => String(x.id) === String(selectedMerchantId));
                const priceToSet = updatedMerchant ? parseFloat(updatedMerchant.price) : targetPrice;

                success = await singleUpdateAdAttempt(priceToSet, merchant.nickName);
            }

            if (success) {
                document.getElementById('trackingStatus').innerHTML = `<span class="text-success fw-bold">Updated to ${targetPrice}</span>`;
                lastMerchantPrice = targetPrice;
            } else {
                document.getElementById('trackingStatus').innerHTML = `<span class="text-danger fw-bold">Update Failed after ${maxRetries} attempts</span>`;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Single Attempt Update Helper
        |--------------------------------------------------------------------------
        */
        async function singleUpdateAdAttempt(priceToUpdate, username) {
            const ad = adsData.find(x => String(x.id) === String(document.getElementById('adId').value));
            if (!ad) return false;

            const payload = {
                ...ad,
                price: priceToUpdate,
                api_key: API_KEY,
                api_secret: API_SECRET
            };

            try {
                const res = await fetch(`${API_URL}/update-ad`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await res.json();

                if (res.ok && !result.error) {
                    ad.price = priceToUpdate; // Update in-memory state
                    document.getElementById('currentPrice').innerHTML = priceToUpdate;
                    toast(`Ad price updated to ${priceToUpdate}`);
                    
                    // Log update to backend DB
                    await fetch("{{ route('dashboard.com.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            merchant_id: selectedMerchantId,
                            username: username,
                            price: priceToUpdate
                        })
                    }).catch(e => console.error(e));

                    return true;
                } else {
                    return false;
                }
            } catch (e) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Classic Ratchet Logic (Up-Only Mode)
        |--------------------------------------------------------------------------
        */
        async function trackMerchantRatchet() {
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'Merchant not in Top 10';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            document.getElementById('merchantPrice').innerHTML = currentPrice;

            if (currentPrice <= lastMerchantPrice) {
                document.getElementById('trackingStatus').innerHTML = `Maintaining High (${lastMerchantPrice})`;
                return;
            }

            document.getElementById('trackingStatus').innerHTML = 'Tracking Upwards';
            lastMerchantPrice = currentPrice;

            const ad = adsData.find(x => String(x.id) === String(document.getElementById('adId').value));
            if (ad) {
                await singleUpdateAdAttempt(currentPrice, merchant.nickName);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Master Engine Loop
        |--------------------------------------------------------------------------
        */
        async function startMasterLoop() {
            while (true) {
                const isPlusMode = plusModeToggle.checked;
                const currentDelay = isPlusMode ? parseInt(plusTimer.value) : 3000;

                if (selectedToken && selectedCurrency) {
                    await fetchCompetitors();

                    if (tracking && selectedMerchantId) {
                        if (isPlusMode) {
                            await executeCompetitorPlusLogic();
                        } else {
                            await trackMerchantRatchet();
                        }
                    }
                }

                await sleep(currentDelay);
            }
        }

        // Start execution loop
        startMasterLoop();

    });
</script>