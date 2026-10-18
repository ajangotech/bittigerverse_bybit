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
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
    }

    .price-box {
        font-size: 30px;
        font-weight: bold;
        color: #E37216;
    }

    .badge-copying {
        background-color: #198754;
        color: white;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 12px;
    }
</style>

<div class="container-fluid py-4">
    <div class="row g-4">
        <!-- My Advertisement Section -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-megaphone me-1"></i> My Advertisements
                    </h5>

                    <select class="form-select form-select-lg mb-4" id="adsSelect">
                        <option value="">Loading Advertisements...</option>
                    </select>

                    <input type="hidden" id="adId">

                    <div class="border rounded p-3 bg-light mb-3">
                        <p class="mb-2"><b>Pair:</b> <span id="pairText">---</span></p>
                        <p class="mb-2"><b>My Current Price:</b> <span id="currentPrice" class="fw-bold text-primary">---</span></p>
                        <p class="mb-2"><b>Min Limit:</b> <span id="minText">---</span></p>
                        <p class="mb-2"><b>Max Limit:</b> <span id="maxText">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusText">---</span></p>
                    </div>

                    <!-- Skip Amount Input Field -->
                    <div class="mb-3">
                        <label for="skipAmount" class="form-label fw-bold text-muted small">Skip Price Threshold (Optional)</label>
                        <input type="number" step="any" class="form-control" id="skipAmount" placeholder="e.g. 1500 (Skip if topper matches)">
                    </div>

                    <!-- NEW: Pause / Resume Button -->
                    <div class="d-grid">
                        <button id="pauseBtn" class="btn btn-warning fw-bold d-none" type="button">
                            <i class="bi bi-pause-fill me-1"></i> Pause Copier
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Topper Copier Section -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-robot me-1"></i> Top Merchant (Copier Target)</span>
                        <span id="copierBadge" class="badge-copying d-none">Auto-Copier Active</span>
                    </h5>

                    <div class="form-control form-control-lg mb-4 bg-light d-flex align-items-center justify-content-between">
                        <span class="text-muted">Target Merchant:</span>
                        <strong id="topMerchantLabel" class="text-dark">Auto-Detecting #1 Merchant...</strong>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <p class="mb-2"><b>Top Merchant Name:</b> <span id="merchantName">---</span></p>
                        <p class="mb-2"><b>Top Merchant Price:</b> <span id="merchantPrice" class="fw-bold text-success">---</span></p>
                        <p class="mb-0"><b>Copier Engine Status:</b> <span id="trackingStatus" class="fw-bold text-secondary">Idle (Select Ad)</span></p>
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
        const pauseBtn = document.getElementById('pauseBtn');

        let adsData = [];
        let selectedToken = null;
        let selectedCurrency = null;
        let lastCopiedPrice = null;
        let updatingAd = false;
        let targetAdPrice = null;
        let isPaused = false; // Pause state variable

        /*
        |--------------------------------------------------------------------------
        | Toast Handler
        |--------------------------------------------------------------------------
        */
        function toast(message, type = 'success') {
            const t = document.getElementById('toast');
            t.innerHTML = message;
            t.className = `app-toast show ${type}`;

            setTimeout(() => {
                t.className = 'app-toast';
            }, 3000);
        }

        /*
        |--------------------------------------------------------------------------
        | Load User Advertisements
        |--------------------------------------------------------------------------
        */
        async function loadAds() {
            try {
                const res = await fetch(`${API_URL}/ads`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        api_key: API_KEY,
                        api_secret: API_SECRET
                    })
                });

                const data = await res.json();
                adsData = data?.result?.items || [];

                adsSelect.innerHTML = `<option value="">Select Advertisement to Copy</option>`;

                adsData.forEach(ad => {
                    adsSelect.innerHTML += `
                        <option value="${ad.id}">
                            ${ad.tokenId}/${ad.currencyId} | Price: ${ad.price}
                        </option>
                    `;
                });

            } catch (e) {
                console.error(e);
                toast('Failed to load advertisements.', 'error');
            }
        }

        loadAds();

        /*
        |--------------------------------------------------------------------------
        | Advertisement Selection Change Listener
        |--------------------------------------------------------------------------
        */
        adsSelect.addEventListener('change', async function () {
            const ad = adsData.find(x => String(x.id) === String(this.value));

            if (!ad) {
                selectedToken = null;
                selectedCurrency = null;
                lastCopiedPrice = null;
                isPaused = false;

                document.getElementById('adId').value = '';
                document.getElementById('pairText').innerHTML = '---';
                document.getElementById('currentPrice').innerHTML = '---';
                document.getElementById('minText').innerHTML = '---';
                document.getElementById('maxText').innerHTML = '---';
                document.getElementById('statusText').innerHTML = '---';

                document.getElementById('topMerchantLabel').innerHTML = 'Auto-Detecting #1 Merchant...';
                document.getElementById('merchantName').innerHTML = '---';
                document.getElementById('merchantPrice').innerHTML = '---';
                document.getElementById('trackingStatus').innerHTML = 'Idle (Select Ad)';
                document.getElementById('copierBadge').classList.add('d-none');
                pauseBtn.classList.add('d-none');
                return;
            }

            // Set global values for active Ad
            selectedToken = ad.tokenId;
            selectedCurrency = ad.currencyId;
            lastCopiedPrice = null;
            isPaused = false;

            // Reset pause button state
            pauseBtn.classList.remove('d-none');
            pauseBtn.className = 'btn btn-warning fw-bold';
            pauseBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause Copier';

            document.getElementById('adId').value = ad.id;
            document.getElementById('pairText').innerHTML = `${ad.tokenId}/${ad.currencyId}`;
            document.getElementById('currentPrice').innerHTML = ad.price;
            document.getElementById('minText').innerHTML = ad.minAmount;
            document.getElementById('maxText').innerHTML = ad.maxAmount;
            document.getElementById('statusText').innerHTML = ad.status ?? 'Active';

            document.getElementById('trackingStatus').innerHTML = 'Connecting to Market...';
            document.getElementById('copierBadge').classList.remove('d-none');
            document.getElementById('copierBadge').className = 'badge-copying';
            document.getElementById('copierBadge').innerHTML = 'Auto-Copier Active';

            toast(`Selected ${ad.tokenId}/${ad.currencyId}. Initializing Topper Copier...`);

            // Execute immediate fetch & sync
            await syncTopperCopier();
        });

        /*
        |--------------------------------------------------------------------------
        | Pause / Resume Button Click Listener
        |--------------------------------------------------------------------------
        */
        pauseBtn.addEventListener('click', function () {
            isPaused = !isPaused;

            if (isPaused) {
                pauseBtn.className = 'btn btn-success fw-bold';
                pauseBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Resume Copier';
                document.getElementById('trackingStatus').innerHTML = 'Paused by User';
                document.getElementById('copierBadge').className = 'badge bg-secondary text-white px-2 py-1 rounded';
                document.getElementById('copierBadge').innerHTML = 'Auto-Copier Paused';
                toast('Topper Copier paused.', 'error');
            } else {
                pauseBtn.className = 'btn btn-warning fw-bold';
                pauseBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause Copier';
                document.getElementById('copierBadge').className = 'badge-copying';
                document.getElementById('copierBadge').innerHTML = 'Auto-Copier Active';
                toast('Topper Copier resumed.');
                syncTopperCopier();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Fetch Top Merchant & Auto-Update Ad (0.5-Second Engine)
        |--------------------------------------------------------------------------
        */
        async function syncTopperCopier() {
            // Check if paused or missing selection
            if (isPaused || !selectedToken || !selectedCurrency) return;

            const adId = document.getElementById('adId').value;
            if (!adId) return;

            try {
                // Fetch Top Market Competitors
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

                // Double check if paused during async fetch
                if (isPaused) return;

                if (!data.status || !data.top_10_competitors || data.top_10_competitors.length === 0) {
                    document.getElementById('trackingStatus').innerHTML = 'No competitors found';
                    return;
                }

                // Extract #1 Top Merchant
                const topMerchant = data.top_10_competitors[0];
                const topPrice = parseFloat(topMerchant.price);

                if (isNaN(topPrice)) return;

                // 1. ALWAYS update the display to show the current #1 Merchant
                document.getElementById('topMerchantLabel').innerHTML = `#1 ${topMerchant.nickName} (${topPrice})`;
                document.getElementById('merchantName').innerHTML = topMerchant.nickName;
                document.getElementById('merchantPrice').innerHTML = topPrice;

                // Check if user entered a skip threshold amount
                const skipAmountInput = document.getElementById('skipAmount').value;
                if (skipAmountInput !== '') {
                    const skipAmountValue = parseFloat(skipAmountInput);
                    // If the top merchant's price matches the skip amount, skip copying
                    if (!isNaN(skipAmountValue) && topPrice === skipAmountValue) {
                        document.getElementById('trackingStatus').innerHTML = `Skipped (Price matches threshold: ${topPrice})`;
                        return;
                    }
                }

                // Find currently selected local ad object
                const currentAd = adsData.find(x => String(x.id) === String(adId));
                const currentAdPrice = currentAd ? parseFloat(currentAd.price) : null;

                // 2. CHECK PRICE: If top price is identical to our ad price or last copied price, STOP HERE.
                if (topPrice === currentAdPrice || topPrice === lastCopiedPrice) {
                    document.getElementById('trackingStatus').innerHTML = `Synced with #1 (${topPrice})`;
                    return;
                }

                // 3. EXECUTE ONLY WHEN THERE IS A NEW PRICE AT THE TOP
                document.getElementById('trackingStatus').innerHTML = `New top price (${topPrice}) detected! Updating...`;

                // Save merchant history to DB only on price change
                try {
                    await fetch("{{ route('dashboard.com.store') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            merchant_id: topMerchant.id,
                            username: topMerchant.nickName,
                            price: topPrice
                        })
                    });
                } catch (err) {
                    console.log('Failed to log merchant history:', err);
                }

                // Send API request to update your Advertisement price
                updateAdPrice(topPrice);

            } catch (e) {
                console.error('Topper Copier sync error:', e);
                document.getElementById('trackingStatus').innerHTML = 'Sync Error (Retrying...)';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Advertisement API Call (With Auto Retry - 0.5s)
        |--------------------------------------------------------------------------
        */
        async function updateAdPrice(newPrice) {
            targetAdPrice = newPrice;

            if (updatingAd) return;
            updatingAd = true;

            while (true) {
                // Stop trying if paused by user mid-retry
                if (isPaused) {
                    updatingAd = false;
                    break;
                }

                const adId = document.getElementById('adId').value;
                const ad = adsData.find(x => String(x.id) === String(adId));

                if (!ad) {
                    updatingAd = false;
                    toast('No Ad selected.', 'error');
                    return;
                }

                const priceToUpdate = targetAdPrice;

                const payload = {
                    ...ad,
                    price: String(priceToUpdate),
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

                    if (res.ok && result.status !== false && !result.error) {
                        ad.price = priceToUpdate;
                        lastCopiedPrice = priceToUpdate;
                        document.getElementById('currentPrice').innerHTML = priceToUpdate;
                        document.getElementById('trackingStatus').innerHTML = `Successfully Copied (${priceToUpdate})`;
                        toast(`Ad price updated to Top Merchant price (${priceToUpdate})`);

                        if (targetAdPrice === priceToUpdate) break;
                    } else {
                        console.log('Backend Error:', result);
                        toast('Retrying price update in 0.5s...', 'error');
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }

                } catch (e) {
                    console.error('Network Error:', e);
                    toast('Network issue. Retrying in 0.5s...', 'error');
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }

            updatingAd = false;
        }

        /*
        |--------------------------------------------------------------------------
        | 0.5-Second Topper Copier Loop (500ms)
        |--------------------------------------------------------------------------
        */
        setInterval(() => {
            if (!isPaused && selectedToken && selectedCurrency) {
                syncTopperCopier();
            }
        }, 200);

    });
</script>