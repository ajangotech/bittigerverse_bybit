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

                    <div class="border rounded p-3">
                        <p><b>Merchant:</b> <span id="merchantName">---</span></p>
                        <p><b>Merchant Price:</b> <span id="merchantPrice" class="text-primary fw-bold">---</span></p>
                        <p><b>Tracking:</b> <span id="trackingStatus">Stopped</span></p>
                        <p class="mb-0"><b>Time to Re-edit:</b> <span id="plusCountdownText" class="text-danger fw-bold">---</span></p>
                    </div>

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
                            <option value="390000">6.5 Minutes</option>
                            <option value="420000">7.0 Minutes</option>
                        </select>
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
        const countdownEl = document.getElementById('plusCountdownText');

        let adsData = [];
        let competitors = [];

        let lastSuccessfulUpdateTime = Date.now(); 

        let selectedMerchantId = null;
        let selectedMerchantName = null;

        let referencePrice = null;
        let lastMerchantPrice = null;

        let selectedToken = null;
        let selectedCurrency = null;

        let tracking = false;
        let updatingAd = false;
        let targetAdPrice = null; 

        const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        function toast(message, type = 'success') {
            const t = document.getElementById('toast');
            t.innerHTML = message;
            t.className = `app-toast show ${type}`;
            setTimeout(() => { t.className = 'app-toast'; }, 3000);
        }

        if (plusModeToggle) {
            plusModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    lastSuccessfulUpdateTime = Date.now();
                    toast('Competitor Plus Activated');
                }
            });
        }

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

                if (tracking && selectedMerchantId) {
                    if (plusModeToggle && plusModeToggle.checked) {
                        await trackMerchantPlus();
                    } else {
                        await trackMerchantRatchet();
                    }
                }
            } catch (e) {
                console.log(e);
            }
        }

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

        merchantSelect.addEventListener('change', async function () {
            const option = this.options[this.selectedIndex];
            if (!option.value) return;

            selectedMerchantId = option.value;
            selectedMerchantName = option.dataset.name;
            referencePrice = parseFloat(option.dataset.price);
            lastMerchantPrice = referencePrice;
            
            lastSuccessfulUpdateTime = Date.now(); 
            tracking = true;

            document.getElementById('merchantName').innerHTML = selectedMerchantName;
            document.getElementById('merchantPrice').innerHTML = referencePrice;
            document.getElementById('trackingStatus').innerHTML = 'Tracking Initiated';

            await updateAdPrice(referencePrice);
            toast(`Tracking ${selectedMerchantName}`);
        });

        async function trackMerchantRatchet() {
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'Merchant not in Top 10';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            document.getElementById('merchantPrice').innerHTML = currentPrice;

            // 🚀 --- SPECIAL TOPPER-BTC RULE --- 🚀
            if (selectedMerchantName === 'TOPPER-BTC') {
                document.getElementById('trackingStatus').innerHTML = '<span class="text-warning fw-bold">Syncing Exact TOPPER-BTC Price</span>';
                if (currentPrice !== lastMerchantPrice) {
                    lastMerchantPrice = currentPrice;
                    await updateAdPrice(currentPrice);
                }
                return; // Bypass the ratchet restrictions below for TOPPER-BTC
            }

            if (currentPrice <= lastMerchantPrice) {
                document.getElementById('trackingStatus').innerHTML = `Maintaining High (${lastMerchantPrice})`;
                return;
            }

            document.getElementById('trackingStatus').innerHTML = 'Tracking Upwards';
            lastMerchantPrice = currentPrice;
            await updateAdPrice(currentPrice);
        }

        async function trackMerchantPlus() {
            const plusTimerMs = parseInt(document.getElementById('plusTimer')?.value) || 0;
            const timeSinceLastUpdate = Date.now() - lastSuccessfulUpdateTime;

            // 1. Check if the current merchant is still in the Top 10
            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'Plus: Merchant lost. Waiting...';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            document.getElementById('merchantPrice').innerHTML = currentPrice;

            // 🚀 --- SPECIAL TOPPER-BTC RULE (PLUS MODE) --- 🚀
            if (selectedMerchantName === 'TOPPER-BTC') {
                document.getElementById('trackingStatus').innerHTML = '<span class="text-warning fw-bold">Syncing Exact TOPPER-BTC Price (Plus)</span>';
                if (currentPrice !== lastMerchantPrice || timeSinceLastUpdate >= plusTimerMs) {
                    toast(`Syncing to TOPPER-BTC's price (${currentPrice})`, 'success');
                    lastMerchantPrice = currentPrice;
                    lastSuccessfulUpdateTime = Date.now();
                    await updateAdPrice(currentPrice);
                }
                return; // Bypass the ratchet restrictions below for TOPPER-BTC
            }

            // 2. Evaluate Timer Expiration (Re-edit / Re-sync to the SAME merchant)
            if (timeSinceLastUpdate >= plusTimerMs) {
                toast(`Timer expired! Re-editing to ${selectedMerchantName}'s current price (${currentPrice})`, 'success');
                
                lastMerchantPrice = currentPrice;
                lastSuccessfulUpdateTime = Date.now(); // Reset timer countdown
                
                document.getElementById('trackingStatus').innerHTML = '<span class="text-success fw-bold">Timer Resync</span>';
                await updateAdPrice(currentPrice);
                return;
            }

            // 3. Process normal upward movement (Ratchet behavior if competitor increases price before timer)
            if (currentPrice > lastMerchantPrice) {
                document.getElementById('trackingStatus').innerHTML = '<span class="text-success fw-bold">Tracking Upwards</span>';
                lastMerchantPrice = currentPrice;
                lastSuccessfulUpdateTime = Date.now(); // Reset timer on price jump
                await updateAdPrice(currentPrice);
            } else {
                document.getElementById('trackingStatus').innerHTML = `Maintaining (${lastMerchantPrice})`;
            }
        }

        async function updateAdPrice(newPrice) {
            targetAdPrice = newPrice;
            if (updatingAd) return;
            updatingAd = true;

            while (true) {
                const ad = adsData.find(x => String(x.id) === String(document.getElementById('adId').value));
                if (!ad) {
                    updatingAd = false;
                    toast('Please select your Ad first.', 'error');
                    return;
                }

                const priceToUpdate = targetAdPrice;
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
                        toast(`Ad updated to ${priceToUpdate}`);
                        
                        if (targetAdPrice === priceToUpdate) break; 
                    } else {
                        toast('Retrying in 1 second...', 'error');
                        await sleep(1000);
                    }
                } catch (e) {
                    toast('Network error. Retrying in 1 second...', 'error');
                    await sleep(1000);
                }
            }
            updatingAd = false;
        }

        // Market Poll Loop
        setInterval(() => {
            if (selectedToken && selectedCurrency) {
                fetchCompetitors();
            }
        }, 3000);

        // Visual Countdown Timer UI Loop
        setInterval(() => {
            if (tracking && selectedMerchantId && plusModeToggle && plusModeToggle.checked) {
                const plusTimerMs = parseInt(document.getElementById('plusTimer')?.value) || 0;
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