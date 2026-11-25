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

    .interval-panel {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
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

                    <label class="small text-muted mb-1">TOPPER Ad <span class="text-danger">(used when tracking TOPPER-BTC)</span>:</label>
                    <select class="form-select form-select-lg mb-3" id="topperAdsSelect">
                        <option value="">Loading...</option>
                    </select>

                    <label class="small text-muted mb-1">Competitor Ad <span class="text-danger">(used when tracking other merchants)</span>:</label>
                    <select class="form-select form-select-lg mb-4" id="competitorAdsSelect">
                        <option value="">Loading...</option>
                    </select>

                    <div class="border rounded p-3">
                        <p><b>Active Ad:</b> <span id="activeAdLabel" class="text-danger fw-bold">---</span></p>
                        <p><b>Pair:</b> <span id="pairText">---</span></p>
                        <p><b>Current Price:</b> <span id="currentPrice" class="text-success fw-bold">---</span></p>
                        <p><b>Min:</b> <span id="minText">---</span></p>
                        <p><b>Max:</b> <span id="maxText">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusText">---</span></p>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        Competitor Pro
                        <i class="bi bi-person-badge"></i>
                    </h5>

                    <select class="form-select form-select-lg mb-3" id="merchantSelect">
                        <option value="">Loading competitors...</option>
                    </select>

                    <div class="border rounded p-3 mb-3">
                        <p><b>Merchant:</b> <span id="merchantName">---</span></p>
                        <p><b>Merchant Price:</b> <span id="merchantPrice" class="text-primary fw-bold">---</span></p>
                        <p><b>Tracking:</b> <span id="trackingStatus">Stopped</span></p>
                        <p class="mb-0"><b>Time to Re-edit:</b> <span id="plusCountdownText" class="text-danger fw-bold">---</span></p>
                    </div>

                    <!-- Start / Stop toggle button -->
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" id="trackingToggleBtn" class="btn btn-success fw-bold flex-grow-1" disabled>
                            <i class="bi bi-play-fill"></i> Start
                        </button>
                    </div>

                    <!-- Competitor Plus Panel -->
                    <div class="p-3 mb-3 plus-panel">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="plusModeToggle">
                            <label class="form-check-label fw-bold text-dark" for="plusModeToggle">
                                Enable Competitor Plus
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
                            <option value="390000">6.5 Minutes</option>
                            <option value="420000">7.0 Minutes</option>
                        </select>
                    </div>

                    <!-- Refresh Intervals Panel -->
                    <div class="p-3 mb-3 interval-panel">
                        <h6 class="fw-bold mb-3">Refresh Intervals</h6>

                        <label class="small text-muted mb-1">TOPPER Price Refresh:</label>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <input type="range" min="0.2" max="10" step="0.1" id="topperIntervalSlider" value="1" class="form-range flex-grow-1">
                            <span id="topperIntervalLabel" class="fw-bold text-nowrap" style="min-width:70px">1.0 sec</span>
                        </div>

                        <label class="small text-muted mb-1">Market Price Refresh:</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" min="0.2" max="15" step="0.1" id="marketIntervalSlider" value="3" class="form-range flex-grow-1">
                            <span id="marketIntervalLabel" class="fw-bold text-nowrap" style="min-width:70px">3.0 sec</span>
                        </div>
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

        const topperAdsSelect = document.getElementById('topperAdsSelect');
        const competitorAdsSelect = document.getElementById('competitorAdsSelect');
        const merchantSelect = document.getElementById('merchantSelect');
        const plusModeToggle = document.getElementById('plusModeToggle');
        const plusTimer = document.getElementById('plusTimer');
        const countdownEl = document.getElementById('plusCountdownText');

        const trackingToggleBtn = document.getElementById('trackingToggleBtn');

        // Refresh interval elements
        const topperIntervalSlider = document.getElementById('topperIntervalSlider');
        const topperIntervalLabel = document.getElementById('topperIntervalLabel');
        const marketIntervalSlider = document.getElementById('marketIntervalSlider');
        const marketIntervalLabel = document.getElementById('marketIntervalLabel');

        let adsData = [];
        let competitors = [];

        // Two separate ad selections:
        //   topperAd      -> used for all TOPPER-BTC tracking requests
        //   competitorAd  -> used for every other (non-TOPPER) merchant request
        let topperAd = null;
        let competitorAd = null;

        // Special TOPPER-BTC Merchant Object
        let topperMerchant = {
            id: 'TOPPER-BTC',
            nickName: 'TOPPER-BTC',
            price: 'Loading...'
        };

        let lastSuccessfulUpdateTime = Date.now();

        let selectedMerchantId = null;
        let selectedMerchantName = null;

        let referencePrice = null;
        let lastMerchantPrice = null;

        let selectedToken = null;
        let selectedCurrency = null;

        let tracking = false;
        let paused = false;
        let updatingAd = false;
        let targetAdPrice = null;
        let plusFetching = false;

        const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        function toast(message, type = 'success') {
            const t = document.getElementById('toast');
            t.innerHTML = message;
            t.className = `app-toast show ${type}`;
            setTimeout(() => { t.className = 'app-toast'; }, 3000);
        }

        /*
        |------------------------------------------------------------------------
        | Active ad helper — which ad is in play depends on the merchant
        |------------------------------------------------------------------------
        */
        function activeAd() {
            return selectedMerchantId === 'TOPPER-BTC' ? topperAd : competitorAd;
        }

        function adMissingMessage() {
            return selectedMerchantId === 'TOPPER-BTC'
                ? 'Please select a TOPPER Ad first.'
                : 'Please select a Competitor Ad first.';
        }

        function renderAdPanel(ad) {
            if (!ad) {
                document.getElementById('activeAdLabel').innerHTML = '---';
                document.getElementById('pairText').innerHTML = '---';
                document.getElementById('currentPrice').innerHTML = '---';
                document.getElementById('minText').innerHTML = '---';
                document.getElementById('maxText').innerHTML = '---';
                document.getElementById('statusText').innerHTML = '---';
                return;
            }

            const label = (selectedMerchantId === 'TOPPER-BTC')
                ? `TOPPER Ad (#${ad.id})`
                : `Competitor Ad (#${ad.id})`;

            document.getElementById('activeAdLabel').innerHTML = label;
            document.getElementById('pairText').innerHTML = `${ad.tokenId}/${ad.currencyId}`;
            document.getElementById('currentPrice').innerHTML = ad.price;
            document.getElementById('minText').innerHTML = ad.minAmount;
            document.getElementById('maxText').innerHTML = ad.maxAmount;
            document.getElementById('statusText').innerHTML = ad.status ?? '---';
        }

        /* =====================================================================
         |  REFRESH INTERVAL MANAGEMENT
         ===================================================================== */
        let topperIntervalSec = 1.0;
        let marketIntervalSec = 3.0;
        let topperIntervalId = null;
        let marketIntervalId = null;

        function startPolling() {
            if (topperIntervalId) clearInterval(topperIntervalId);
            if (marketIntervalId) clearInterval(marketIntervalId);
            topperIntervalId = setInterval(fetchTopperPrice, topperIntervalSec * 1000);
            marketIntervalId = setInterval(() => {
                if (selectedToken && selectedCurrency) {
                    fetchCompetitors();
                }
            }, marketIntervalSec * 1000);
        }

        topperIntervalSlider.addEventListener('input', () => {
            topperIntervalSec = parseFloat(topperIntervalSlider.value) || 0.2;
            topperIntervalLabel.innerHTML = `${topperIntervalSec.toFixed(1)} sec`;
            startPolling();
        });

        marketIntervalSlider.addEventListener('input', () => {
            marketIntervalSec = parseFloat(marketIntervalSlider.value) || 0.2;
            marketIntervalLabel.innerHTML = `${marketIntervalSec.toFixed(1)} sec`;
            startPolling();
        });

        function activeTimerMs() {
            return parseInt(plusTimer?.value) || 180000;
        }

        if (plusModeToggle) {
            plusModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    lastSuccessfulUpdateTime = Date.now();
                    toast('Competitor Plus Activated');
                }
            });
        }

        /*
        |------------------------------------------------------------------------
        | Load Advertisements (fills BOTH dropdowns)
        |------------------------------------------------------------------------
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

                const optionsHtml = adsData.map(ad => `
                    <option value="${ad.id}">
                        ${ad.tokenId}/${ad.currencyId} | ${ad.price}
                    </option>
                `).join('');

                topperAdsSelect.innerHTML = `<option value="">Select TOPPER Ad</option>` + optionsHtml;
                competitorAdsSelect.innerHTML = `<option value="">Select Competitor Ad</option>` + optionsHtml;
            } catch (e) {
                console.log(e);
                toast('Failed to load advertisements.', 'error');
            }
        }
        loadAds();

        /*
        |------------------------------------------------------------------------
        | Advertisement Selected (shared handler for both dropdowns)
        |------------------------------------------------------------------------
        */
        function resetMerchantTrackingState() {
            selectedMerchantId = null;
            selectedMerchantName = null;
            referencePrice = null;
            lastMerchantPrice = null;
            tracking = false;
            paused = false;

            document.getElementById('merchantName').innerHTML = '---';
            document.getElementById('merchantPrice').innerHTML = '---';
            document.getElementById('trackingStatus').innerHTML = 'Stopped';

            trackingToggleBtn.disabled = true;
            trackingToggleBtn.className = 'btn btn-success fw-bold flex-grow-1';
            trackingToggleBtn.innerHTML = '<i class="bi bi-play-fill"></i> Start';

            merchantSelect.innerHTML = `<option value="">Loading competitors...</option>`;
        }

        async function onAdSelected(which) {
            const selectEl = which === 'topper' ? topperAdsSelect : competitorAdsSelect;
            const ad = adsData.find(x => String(x.id) === String(selectEl.value));

            if (which === 'topper') {
                topperAd = ad || null;
            } else {
                competitorAd = ad || null;
            }

            resetMerchantTrackingState();

            if (!ad) {
                selectedToken = null;
                selectedCurrency = null;
                renderAdPanel(null);
                merchantSelect.innerHTML = `<option value="">Select Advertisement First</option>`;
                return;
            }

            // The TOPPER price-limit check is driven by the TOPPER ad's pair
            if (which === 'topper') {
                selectedToken = ad.tokenId;
                selectedCurrency = ad.currencyId;
            }

            // Show the ad that is relevant to the current tracking mode
            renderAdPanel(activeAd() || ad);

            fetchTopperPrice();
            await fetchCompetitors();
        }

        topperAdsSelect.addEventListener('change', () => onAdSelected('topper'));
        competitorAdsSelect.addEventListener('change', () => onAdSelected('competitor'));

        /*
        |------------------------------------------------------------------------
        | Fetch TOPPER-BTC Price (1-Second API Check) — uses the TOPPER ad
        |------------------------------------------------------------------------
        */
        async function fetchTopperPrice() {
            if (!topperAd) return;

            try {

                const payload = {
                    api_key: API_KEY,
                    api_secret: API_SECRET,
                    id: topperAd.id,
                    price: "999999999", // Static price
                    priceType: topperAd.priceType !== undefined ? topperAd.priceType : 0,
                    premium: topperAd.premium !== undefined ? topperAd.premium : 0,
                    minAmount: topperAd.minAmount,
                    maxAmount: topperAd.maxAmount,
                    lastQuantity: topperAd.lastQuantity,
                    paymentPeriod: topperAd.paymentPeriod,
                    paymentTerms: topperAd.paymentTerms,
                    tradingPreferenceSet: topperAd.tradingPreferenceSet
                };

                const res = await fetch(`${API_URL}/ad-price-limit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data && data.price) {
                    topperMerchant.price = parseFloat(data.price);

                    const compIndex = competitors.findIndex(x => x.id === 'TOPPER-BTC');
                    if (compIndex > -1) {
                        competitors[compIndex].price = topperMerchant.price;
                    }

                    const topperOption = document.querySelector('option[value="TOPPER-BTC"]');
                    if (topperOption) {
                        topperOption.dataset.price = topperMerchant.price;
                        topperOption.innerHTML = `⭐ | TOPPER-BTC | ${topperMerchant.price}`;
                    }

                    if (tracking && selectedMerchantId === 'TOPPER-BTC') {
                        if (plusModeToggle && plusModeToggle.checked) {
                            trackMerchantPlus();
                        } else {
                            trackMerchantRatchet();
                        }
                    }
                }


            } catch (e) {
                console.log("Failed to fetch Topper limit:", e);
            }
        }

        /*
        |------------------------------------------------------------------------
        | Fetch Competitors — Only TOPPER-BTC
        |------------------------------------------------------------------------
        */
        async function fetchCompetitors() {
            if (!selectedToken || !selectedCurrency) return;

            competitors = [{ ...topperMerchant }];

            renderCompetitors();

            if (tracking && selectedMerchantId) {
                if (plusModeToggle && plusModeToggle.checked) {
                    await trackMerchantPlus();
                } else {
                    await trackMerchantRatchet();
                }
            }
        }

        /*
        |------------------------------------------------------------------------
        | Render Competitors — Only TOPPER-BTC
        |------------------------------------------------------------------------
        */
        function renderCompetitors() {
            const selected = selectedMerchantId;
            merchantSelect.innerHTML = `<option value="">Select Merchant</option>`;

            competitors.forEach((merchant, index) => {
                const displayPrefix = merchant.id === 'TOPPER-BTC' ? '⭐' : `#${index}`;

                merchantSelect.innerHTML += ``
                    + `<option value="${merchant.id}" data-price="${merchant.price}" data-name="${merchant.nickName}" ${selected == merchant.id ? 'selected' : ''}>`
                    + `${displayPrefix} | ${merchant.nickName} | ${merchant.price}`
                    + `</option>`;
            });
        }

        /*
        |------------------------------------------------------------------------
        | Merchant Selected
        |------------------------------------------------------------------------
        */
        merchantSelect.addEventListener('change', async function () {
            const option = this.options[this.selectedIndex];
            if (!option.value) return;

            selectedMerchantId = option.value;
            selectedMerchantName = option.dataset.name;

            referencePrice = parseFloat(option.dataset.price) || option.dataset.price;
            lastMerchantPrice = referencePrice;

            document.getElementById('merchantName').innerHTML = selectedMerchantName;
            document.getElementById('merchantPrice').innerHTML = referencePrice;
            document.getElementById('trackingStatus').innerHTML = 'Ready — press Start';

            // The info panel now reflects the ad that will actually be edited
            renderAdPanel(activeAd());

            trackingToggleBtn.disabled = false;

            toast(`${selectedMerchantName} selected. Press Start to begin tracking.`);
        });

        /* =====================================================================
         |  START / STOP TRACKING TOGGLE
         ===================================================================== */
        trackingToggleBtn.addEventListener('click', async function () {
            if (!selectedMerchantId) {
                toast('Select a merchant first.', 'error');
                return;
            }

            if (!activeAd()) {
                toast(adMissingMessage(), 'error');
                return;
            }

            if (!tracking) {
                // Start tracking
                tracking = true;
                paused = false;
                lastSuccessfulUpdateTime = Date.now();

                trackingToggleBtn.className = 'btn btn-danger fw-bold flex-grow-1';
                trackingToggleBtn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
                document.getElementById('trackingStatus').innerHTML = 'Tracking Active';

                const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));
                const currentPrice = merchant ? parseFloat(merchant.price) : referencePrice;

                if (!isNaN(currentPrice)) {
                    lastMerchantPrice = currentPrice;
                    await updateAdPrice(currentPrice);
                }

                toast(`Tracking started for ${selectedMerchantName}`);
            } else {
                // Stop tracking
                tracking = false;
                paused = true;

                trackingToggleBtn.className = 'btn btn-success fw-bold flex-grow-1';
                trackingToggleBtn.innerHTML = '<i class="bi bi-play-fill"></i> Start';
                document.getElementById('trackingStatus').innerHTML = 'Stopped';

                toast('Tracking stopped.', 'error');
            }
        });

        /*
        |------------------------------------------------------------------------
        | Track Merchant Ratchet (Standard Mode) — Price Only Goes Up
        |------------------------------------------------------------------------
        */
        async function trackMerchantRatchet() {
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'TOPPER-BTC not loaded yet';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            if (isNaN(currentPrice)) return;

            document.getElementById('merchantPrice').innerHTML = currentPrice;

            if (selectedMerchantId === 'TOPPER-BTC') {
                if (currentPrice <= lastMerchantPrice) {
                    document.getElementById('trackingStatus').innerHTML = `At API Limit (${lastMerchantPrice})`;
                    return;
                }
                document.getElementById('trackingStatus').innerHTML = 'Syncing to Limit (Upwards)';
            }

            lastMerchantPrice = currentPrice;
            await updateAdPrice(currentPrice);
        }

        /*
        |------------------------------------------------------------------------
        | Direct fetch of TOPPER price limit (no side-effects, no re-entrancy)
        | Always queries the TOPPER ad.
        |------------------------------------------------------------------------
        */
        async function fetchTopperPriceDirect() {
            if (!topperAd) return null;

            const payload = {
                api_key: API_KEY,
                api_secret: API_SECRET,
                id: topperAd.id,
                price: "999999999",
                priceType: topperAd.priceType !== undefined ? topperAd.priceType : 0,
                premium: topperAd.premium !== undefined ? topperAd.premium : 0,
                minAmount: topperAd.minAmount,
                maxAmount: topperAd.maxAmount,
                lastQuantity: topperAd.lastQuantity,
                paymentPeriod: topperAd.paymentPeriod,
                paymentTerms: topperAd.paymentTerms,
                tradingPreferenceSet: topperAd.tradingPreferenceSet
            };

            const res = await fetch(`${API_URL}/ad-price-limit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (data && data.price) {
                const freshPrice = parseFloat(data.price);
                topperMerchant.price = freshPrice;

                const compIndex = competitors.findIndex(x => x.id === 'TOPPER-BTC');
                if (compIndex > -1) {
                    competitors[compIndex].price = freshPrice;
                }

                const topperOption = document.querySelector('option[value="TOPPER-BTC"]');
                if (topperOption) {
                    topperOption.dataset.price = freshPrice;
                    topperOption.innerHTML = `⭐ | TOPPER-BTC | ${freshPrice}`;
                }

                document.getElementById('merchantPrice').innerHTML = freshPrice;
                return freshPrice;
            }

            return null;
        }

        /*
        |------------------------------------------------------------------------
        | Track Merchant Plus Mode — Timer re-fetches TOPPER, Price Only Goes Up
        |------------------------------------------------------------------------
        */
        async function trackMerchantPlus() {
            if (plusFetching) return;

            const plusTimerMs = activeTimerMs();
            const timeSinceLastUpdate = Date.now() - lastSuccessfulUpdateTime;

            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'Plus: TOPPER-BTC not loaded. Waiting...';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            if (isNaN(currentPrice)) return;

            document.getElementById('merchantPrice').innerHTML = currentPrice;

            if (selectedMerchantId === 'TOPPER-BTC') {
                if (currentPrice > lastMerchantPrice) {
                    document.getElementById('trackingStatus').innerHTML = '<span class="text-success fw-bold">Syncing Limit (Plus)</span>';
                    lastMerchantPrice = currentPrice;
                    lastSuccessfulUpdateTime = Date.now();
                    await updateAdPrice(currentPrice);
                    return;
                }

                if (timeSinceLastUpdate >= plusTimerMs) {
                    plusFetching = true;
                    document.getElementById('trackingStatus').innerHTML = '<span class="text-warning fw-bold">Timer Expired! Re-fetching TOPPER...</span>';
                    toast('Timer expired! Re-fetching TOPPER price...', 'success');

                    try {
                        const freshPrice = await fetchTopperPriceDirect();

                        if (freshPrice !== null && !isNaN(freshPrice)) {
                            lastMerchantPrice = freshPrice;
                            if (freshPrice > lastMerchantPrice) {
                                document.getElementById('trackingStatus').innerHTML = `<span class="text-success fw-bold">Re-fetched! Syncing to ${freshPrice}</span>`;
                            } else {
                                document.getElementById('trackingStatus').innerHTML = `<span class="text-success fw-bold">Timer Resync to ${freshPrice}</span>`;
                            }
                            await updateAdPrice(freshPrice);
                        }
                    } catch (e) {
                        console.log("Failed to re-fetch Topper limit on timer expiry:", e);
                    } finally {
                        plusFetching = false;
                    }

                    lastSuccessfulUpdateTime = Date.now();
                    return;
                }

                document.getElementById('trackingStatus').innerHTML = `At Limit (${lastMerchantPrice})`;
                return;
            }

            if (timeSinceLastUpdate >= plusTimerMs) {
                toast(`Timer expired! Re-editing to ${selectedMerchantName}'s current price (${currentPrice})`, 'success');

                lastMerchantPrice = currentPrice;
                lastSuccessfulUpdateTime = Date.now();

                document.getElementById('trackingStatus').innerHTML = '<span class="text-success fw-bold">Timer Resync</span>';
                await updateAdPrice(currentPrice);
                return;
            }

            if (currentPrice > lastMerchantPrice) {
                document.getElementById('trackingStatus').innerHTML = '<span class="text-success fw-bold">Tracking Upwards</span>';
                lastMerchantPrice = currentPrice;
                lastSuccessfulUpdateTime = Date.now();
                await updateAdPrice(currentPrice);
            } else {
                document.getElementById('trackingStatus').innerHTML = `Maintaining (${lastMerchantPrice})`;
            }
        }

        /*
        |------------------------------------------------------------------------
        | Update Advertisement (With Exponential Backoff Retry)
        | Edits the ad matching the current tracking mode:
        |   - Tracking TOPPER-BTC        -> the TOPPER Ad
        |   - Tracking any other merchant -> the Competitor Ad
        |------------------------------------------------------------------------
        */
        async function updateAdPrice(newPrice) {
            const ad = activeAd();

            if (!ad) {
                toast(adMissingMessage(), 'error');
                return;
            }

            targetAdPrice = newPrice;
            if (updatingAd) return;
            updatingAd = true;

            let lastUpdatedPrice = null;
            let retryDelay = 2000; // start at 2 seconds
            const maxRetries = 5;

            for (let attempt = 0; attempt < maxRetries; attempt++) {
                // Bail if tracking was stopped
                if (!tracking) {
                    updatingAd = false;
                    return;
                }

                // Bail if a newer price target superseded this one
                if (targetAdPrice !== newPrice) {
                    updatingAd = false;
                    return;
                }

                // Skip if the price hasn't changed since the last successful write
                if (lastUpdatedPrice === newPrice) {
                    updatingAd = false;
                    return;
                }

                const currentAd = activeAd();
                if (!currentAd) {
                    updatingAd = false;
                    toast(adMissingMessage(), 'error');
                    return;
                }

                const priceToUpdate = targetAdPrice;
                const payload = {
                    ...currentAd,
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
                        currentAd.price = priceToUpdate;
                        lastUpdatedPrice = priceToUpdate;
                        document.getElementById('currentPrice').innerHTML = priceToUpdate;
                        toast(`Ad updated to ${priceToUpdate}`);

                        // If target hasn't changed, we're done
                        if (targetAdPrice === priceToUpdate) {
                            updatingAd = false;
                            return;
                        }
                        // Target changed — loop again with the new target
                        newPrice = targetAdPrice;
                        retryDelay = 2000;
                    } else {
                        // 429 = rate limited, or other error — back off
                        toast(`Update failed, retrying in ${retryDelay / 1000}s...`, 'error');
                        await sleep(retryDelay);
                        retryDelay = Math.min(retryDelay * 2, 32000);
                    }
                } catch (e) {
                    toast(`Network error, retrying in ${retryDelay / 1000}s...`, 'error');
                    await sleep(retryDelay);
                    retryDelay = Math.min(retryDelay * 2, 32000);
                }
            }

            // Exhausted retries
            if (tracking) {
                toast('Ad update failed after multiple retries. Will try again on next price change.', 'error');
            }
            updatingAd = false;
        }

        /*
        |------------------------------------------------------------------------
        | Intervals / Polling Loops
        |------------------------------------------------------------------------
        */

        // 1. Fetch TOPPER Limit Price (interval managed by slider)
        // 2. Market Poll Loop (interval managed by slider)
        startPolling();

        // 3. Visual Countdown Timer UI Loop every 1 second
        setInterval(() => {
            if (tracking && selectedMerchantId && plusModeToggle && plusModeToggle.checked) {
                const plusTimerMs = activeTimerMs();
                const timeSinceLastUpdate = Date.now() - lastSuccessfulUpdateTime;
                const timeLeft = Math.max(0, plusTimerMs - timeSinceLastUpdate);

                if (timeLeft > 0) {
                    const mins = Math.floor(timeLeft / 60000);
                    const secs = Math.floor((timeLeft % 60000) / 1000);
                    countdownEl.innerHTML = `${mins}m ${secs}s`;
                } else {
                    countdownEl.innerHTML = 'Resyncing...';
                }
            } else {
                countdownEl.innerHTML = '---';
            }
        }, 1000);

    });
</script>
