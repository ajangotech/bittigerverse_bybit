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
                            <option value="60000">1.0 Minutes</option>
                            <option value="90000">1.5 Minutes</option>
                            <option value="120000">2.0 Minutes</option>
                            <option value="150000">2.5 Minutes</option>
                            <option value="180000" selected>3.0 Minutes</option>
                            <option value="210000">3.5 Minutes</option>
                            <option value="240000">4.0 Minutes</option>
                            <option value="270000">4.5 Minutes</option>
                            <option value="300000">5.0 Minutes</option>
                            <option value="330000">5.5 Minutes</option>
                            <option value="360000">6.0 Minutes</option>
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

        let lastSuccessfulUpdateTime = Date.now(); // Tracks the exact time of the last edit

        let selectedMerchantId = null;
        let selectedMerchantName = null;

        let referencePrice = null;
        let lastMerchantPrice = null;

        let selectedToken = null;
        let selectedCurrency = null;

        let tracking = false;
        let updatingAd = false;
        let targetAdPrice = null; 

        // Helper: Async Sleep
        const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        /*
        |--------------------------------------------------------------------------
        | Toast
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
        | Advertisement Selected
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

            // Reset Tracking
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
                console.log(e);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Render Competitors
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
        | Merchant Selected
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
            });

            await updateAdPrice(referencePrice);
            toast(`Tracking ${selectedMerchantName}`);
        });

        /*
        |--------------------------------------------------------------------------
        | 🚀 COMPETITOR PLUS LOGIC (Constant Tracking + Forced Timer Refresh)
        |--------------------------------------------------------------------------
        */
        async function executeCompetitorPlusLogic() {
            // 1. Get the target timer value in milliseconds (e.g., 180000 for 3 mins)
            const plusTimerMs = parseInt(document.getElementById('plusTimer').value);
            
            // 2. Calculate how much time has passed since our last successful update
            const timeSinceLastUpdate = Date.now() - lastSuccessfulUpdateTime;

            // 3. Find our target merchant
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));
            if (!merchant) return; // Wait for next loop if merchant isn't found

            let currentPrice = parseFloat(merchant.price);
            
            // 4. Evaluate our two trigger conditions
            const priceChanged = currentPrice !== lastMerchantPrice;
            const timerExceeded = timeSinceLastUpdate >= plusTimerMs;

            // IF the market moved OR our timer finished, we execute an update!
            if (priceChanged || timerExceeded) {
                let success = false;
                let isFirstAttempt = true;

                while (!success && tracking && plusModeToggle.checked) {
                    
                    // If retry, wait 1 minute and re-fetch fresh market data
                    if (!isFirstAttempt) {
                        document.getElementById('trackingStatus').innerHTML = '<span class="text-danger">Plus: Edit Failed. Retrying in 1 Min...</span>';
                        await sleep(60000); 
                        await fetchCompetitors(); 
                        
                        // Re-find merchant and update current price so we don't post outdated prices
                        const retryMerchant = competitors.find(x => String(x.id) === String(selectedMerchantId));
                        if (retryMerchant) {
                            currentPrice = parseFloat(retryMerchant.price);
                        } else {
                            continue; // Merchant gone, loop again
                        }
                    }
                    isFirstAttempt = false;

                    document.getElementById('merchantPrice').innerHTML = currentPrice;
                    
                    // Show why we are updating (Price Change vs Forced Timer)
                    const updateReason = priceChanged ? "Price Changed" : "Timer Reached";
                    document.getElementById('trackingStatus').innerHTML = `<span class="text-info fw-bold">Plus: ${updateReason} - Editing Ad...</span>`;

                    // Attempt to update Ad
                    success = await singleUpdateAdAttempt(currentPrice, merchant.nickName);

                    if (success) {
                        document.getElementById('trackingStatus').innerHTML = `<span class="text-success fw-bold">Plus: Updated (${currentPrice})</span>`;
                        
                        // --- CRITICAL UPDATES ---
                        lastMerchantPrice = currentPrice; // Save the new price
                        lastSuccessfulUpdateTime = Date.now(); // RESET THE COUNTDOWN TIMER!
                    }
                }
            } else {
                // Market is stable AND timer hasn't finished yet.
                // Calculate remaining time and show it on the UI so you know it's working.
                let timeRemaining = plusTimerMs - timeSinceLastUpdate;
                let mins = Math.floor(timeRemaining / 60000);
                let secs = Math.floor((timeRemaining % 60000) / 1000);
                
                document.getElementById('trackingStatus').innerHTML = 
                    `<span class="text-muted">Plus: Market Stable. Force edit in ${mins}m ${secs}s</span>`;
            }
        }

        // Dedicated single-shot update function for Plus Mode
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
                    ad.price = priceToUpdate;
                    document.getElementById('currentPrice').innerHTML = priceToUpdate;
                    toast(`Competitor Plus: Ad updated to ${priceToUpdate}`);
                    
                    // Update database
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
                    }).catch(e => console.log(e));

                    return true; // Success! Break retry loop.
                } else {
                    return false; // Error (e.g., price invalid). Triggers 1s retry.
                }
            } catch (e) {
                return false; // Network error. Triggers 1s retry.
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Track Merchant (Classic Ratchet / Up-Only Mode)
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

            await updateAdPrice(currentPrice);

            try {
                await fetch("{{ route('dashboard.com.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        merchant_id: merchant.id,
                        username: merchant.nickName,
                        price: currentPrice
                    })
                });
            } catch (e) { console.log(e); }
        }

        /*
        |--------------------------------------------------------------------------
        | Original Update Ad (Used by Classic Ratchet Mode)
        |--------------------------------------------------------------------------
        */
        async function updateAdPrice(newPrice) {
            targetAdPrice = newPrice;
            if (updatingAd) return;
            updatingAd = true;

            while (true) {
                const ad = adsData.find(x => String(x.id) === String(document.getElementById('adId').value));
                if (!ad) {
                    updatingAd = false;
                    return;
                }

                const priceToUpdate = targetAdPrice;
                const payload = {
                    ...ad, price: priceToUpdate, api_key: API_KEY, api_secret: API_SECRET
                };

                try {
                    const res = await fetch(`${API_URL}/update-ad`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const result = await res.json();

                    if (res.ok && !result.error) {
                        ad.price = priceToUpdate;
                        document.getElementById('currentPrice').innerHTML = priceToUpdate;
                        toast(`Ad updated to ${priceToUpdate}`);
                        
                        if (targetAdPrice === priceToUpdate) break;
                    } else {
                        await sleep(1000);
                    }
                } catch (e) {
                    await sleep(1000);
                }
            }
            updatingAd = false;
        }

        /*
        |--------------------------------------------------------------------------
        | MASTER ASYNC POLLING ENGINE
        |--------------------------------------------------------------------------
        | Replaces setInterval to prevent race conditions. Checks if Competitor Plus
        | is active and dynamically manages the delays and fetch logic.
        */
        async function startMasterLoop() {
            while (true) {
                // Determine the current wait time based on mode
                const isPlusMode = plusModeToggle.checked;
                let currentDelay = isPlusMode ? parseInt(plusTimer.value) : 3000;

                if (selectedToken && selectedCurrency) {
                    // Always fetch latest market snapshot first
                    await fetchCompetitors();

                    if (tracking && selectedMerchantId) {
                        if (isPlusMode) {
                            await executeCompetitorPlusLogic();
                        } else {
                            await trackMerchantRatchet();
                        }
                    }
                }

                // Wait for the requested timer before starting the next cycle
                await sleep(currentDelay);
            }
        }

        // Ignite the engine
        startMasterLoop();

    });
</script>