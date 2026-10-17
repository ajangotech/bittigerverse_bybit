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
    .app-toast.show { opacity: 1; }
    .app-toast.success { background: #E37216; }
    .app-toast.error { background: #dc3545; }
    .app-toast.info { background: #0d6efd; }

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
    .badge-live {
        background-color: #dc3545;
        color: white;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 12px;
        animation: pulse 1s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }
    .merchant-tab {
        cursor: pointer;
        padding: 10px 18px;
        border-bottom: 3px solid transparent;
        font-weight: 600;
        color: #888;
    }
    .merchant-tab.active {
        color: #E37216;
        border-bottom-color: #E37216;
    }
    .ctrl-input {
        max-width: 130px;
    }
</style>

<div class="container-fluid py-4">

    <!-- ============ GLOBAL CONTROL BAR ============ -->
    <div class="card ads-card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-trophy me-1"></i> Beat-To-Top Engine (Two-Merchant Leapfrog)
            </h5>

            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Floor Price</label>
                    <input type="number" step="0.01" min="0" id="floorPrice" class="form-control ctrl-input" placeholder="e.g. 95000">
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Step</label>
                    <input type="number" step="0.01" min="0" id="stepAmount" class="form-control ctrl-input" value="0.10" placeholder="0.10">
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Side</label>
                    <select id="sideSelect" class="form-select ctrl-input">
                        <option value="0">Sell (lower = top)</option>
                        <option value="1">Buy (higher = top)</option>
                    </select>
                </div>

                <button id="startBtn" class="btn btn-success fw-bold">
                    <i class="bi bi-play-fill"></i> Start Beating
                </button>
                <button id="stopBtn" class="btn btn-danger fw-bold" disabled>
                    <i class="bi bi-stop-fill"></i> Stop
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ============ MERCHANT A ============ -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-person-badge me-1 text-primary"></i> Merchant A</span>
                        <span id="badgeA" class="badge-copying d-none">Pushing</span>
                    </h5>

                    <select class="form-select form-select-lg mb-3" id="adsSelectA">
                        <option value="">Loading Advertisements...</option>
                    </select>
                    <input type="hidden" id="adIdA">

                    <div class="border rounded p-3 bg-light">
                        <p class="mb-2"><b>Pair:</b> <span id="pairTextA">---</span></p>
                        <p class="mb-2"><b>Current Price:</b> <span id="currentPriceA" class="fw-bold text-primary">---</span></p>
                        <p class="mb-2"><b>Min Limit:</b> <span id="minTextA">---</span></p>
                        <p class="mb-2"><b>Max Limit:</b> <span id="maxTextA">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusTextA">---</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ MERCHANT B ============ -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-person-badge me-1 text-success"></i> Merchant B</span>
                        <span id="badgeB" class="badge-copying d-none">Pushing</span>
                    </h5>

                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <input type="text" id="apiKeyB" class="form-control form-control-sm" placeholder="Merchant B API Key">
                        </div>
                        <div class="col-12">
                            <input type="text" id="apiSecretB" class="form-control form-control-sm" placeholder="Merchant B API Secret">
                        </div>
                        <div class="col-12">
                            <input type="text" id="apiUrlB" class="form-control form-control-sm" placeholder="Merchant B API URL (same as A if shared)">
                        </div>
                    </div>

                    <button id="loadAdsB" class="btn btn-outline-success btn-sm w-100 mb-3">
                        <i class="bi bi-arrow-repeat"></i> Load B's Advertisements
                    </button>

                    <select class="form-select form-select-lg mb-3" id="adsSelectB" disabled>
                        <option value="">Load B's credentials first...</option>
                    </select>
                    <input type="hidden" id="adIdB">

                    <div class="border rounded p-3 bg-light">
                        <p class="mb-2"><b>Pair:</b> <span id="pairTextB">---</span></p>
                        <p class="mb-2"><b>Current Price:</b> <span id="currentPriceB" class="fw-bold text-success">---</span></p>
                        <p class="mb-2"><b>Min Limit:</b> <span id="minTextB">---</span></p>
                        <p class="mb-2"><b>Max Limit:</b> <span id="maxTextB">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusTextB">---</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ MARKET / TOP LIST PANEL ============ -->
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-bar-chart-line me-1"></i> Live Market Top List</span>
                        <span id="liveBadge" class="badge-live d-none">LIVE</span>
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Merchant</th>
                                    <th>Price</th>
                                    <th>Available</th>
                                </tr>
                            </thead>
                            <tbody id="topListBody">
                                <tr><td colspan="4" class="text-center text-muted">Select ads and press Start.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 mb-0"><b>Engine Status:</b> <span id="engineStatus" class="fw-bold text-secondary">Idle</span></p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================================
     |  CREDENTIALS
     |  Merchant A = the logged-in user. Merchant B = extra inputs.
     |  Only ONE credential set is used to READ the market (Merchant A).
     ===================================================================== */
    const A = {
        url:    "{{ auth()->user()->api_url }}",
        key:    "{{ auth()->user()->bybit_api_key }}",
        secret: "{{ auth()->user()->bybit_api_secret }}",
        ads: [],
        ad: null,
        updating: false
    };

    const B = {
        url:    '',
        key:    '',
        secret: '',
        ads: [],
        ad: null,
        updating: false
    };

    /* =====================================================================
     |  ENGINE STATE
     ===================================================================== */
    let running = false;
    let syncing = false;
    let activeIndex = 0;        // which merchant we are currently pushing to #1
    let lastBeatPrice = null;   // last price we pushed, to avoid duplicate calls

    const $ = id => document.getElementById(id);

    /* =====================================================================
     |  TOAST
     ===================================================================== */
    function toast(msg, type = 'success') {
        const t = $('toast');
        t.innerHTML = msg;
        t.className = `app-toast show ${type}`;
        setTimeout(() => t.className = 'app-toast', 3000);
    }

    /* =====================================================================
     |  HELPERS
     ===================================================================== */
    function side() { return $('sideSelect').value; }            // '0' sell, '1' buy
    function floor() { return parseFloat($('floorPrice').value) || 0; }
    function step()  { return parseFloat($('stepAmount').value) || 0.10; }

    // For SELL: top of list = LOWEST price -> to beat, go LOWER (topPrice - step)
    // For BUY:  top of list = HIGHEST price -> to beat, go HIGHER (topPrice + step)
    function beatPrice(topPrice) {
        const s = step();
        let p = (side() === '0') ? topPrice - s : topPrice + s;
        // Clamp so we never go beyond the floor / ceiling the user set.
        if (side() === '0') {
            if (p < floor()) { p = floor(); }
        } else {
            if (floor() > 0 && p > floor()) { p = floor(); }
        }
        return p;
    }

    // Is `myPrice` already at (or past) the top compared to `topPrice`?
    function isAtTop(myPrice, topPrice) {
        if (myPrice == null || isNaN(myPrice)) return false;
        return (side() === '0') ? myPrice <= topPrice : myPrice >= topPrice;
    }

    function merchant(i) { return i === 0 ? A : B; }

    function renderAd(m, prefix) {
        if (!m.ad) {
            $('pairText' + prefix).innerHTML = '---';
            $('currentPrice' + prefix).innerHTML = '---';
            $('minText' + prefix).innerHTML = '---';
            $('maxText' + prefix).innerHTML = '---';
            $('statusText' + prefix).innerHTML = '---';
            return;
        }
        $('adId' + prefix).value = m.ad.id;
        $('pairText' + prefix).innerHTML = `${m.ad.tokenId}/${m.ad.currencyId}`;
        $('currentPrice' + prefix).innerHTML = m.ad.price;
        $('minText' + prefix).innerHTML = m.ad.minAmount;
        $('maxText' + prefix).innerHTML = m.ad.maxAmount;
        $('statusText' + prefix).innerHTML = m.ad.status ?? 'Active';
    }

    /* =====================================================================
     |  LOAD ADS — per merchant
     ===================================================================== */
    async function loadAds(m, selectEl, label) {
        try {
            selectEl.innerHTML = `<option value="">Loading...</option>`;
            const res = await fetch(`${m.url}/ads`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key: m.key, api_secret: m.secret })
            });
            const data = await res.json();
            m.ads = data?.result?.items || [];
            selectEl.innerHTML = `<option value="">Select ${label} Advertisement</option>`;
            m.ads.forEach(ad => {
                selectEl.innerHTML += `<option value="${ad.id}">${ad.tokenId}/${ad.currencyId} | Price: ${ad.price}</option>`;
            });
        } catch (e) {
            console.error(e);
            toast(`Failed to load ${label} advertisements.`, 'error');
        }
    }

    loadAds(A, $('adsSelectA'), 'A');

    $('loadAdsB').addEventListener('click', () => {
        B.key    = $('apiKeyB').value.trim();
        B.secret = $('apiSecretB').value.trim();
        B.url    = $('apiUrlB').value.trim() || A.url;
        if (!B.key || !B.secret) {
            toast('Enter Merchant B API key and secret.', 'error');
            return;
        }
        $('adsSelectB').disabled = false;
        loadAds(B, $('adsSelectB'), 'B');
    });

    /* =====================================================================
     |  AD SELECTION
     ===================================================================== */
    function bindSelect(selectEl, m, prefix) {
        selectEl.addEventListener('change', function () {
            m.ad = m.ads.find(x => String(x.id) === String(this.value)) || null;
            renderAd(m, prefix);
        });
    }
    bindSelect($('adsSelectA'), A, 'A');
    bindSelect($('adsSelectB'), B, 'B');

    /* =====================================================================
     |  UPDATE A SINGLE MERCHANT'S AD PRICE
     ===================================================================== */
    async function pushMerchantPrice(m, newPrice, prefix) {
        if (!m.ad || m.updating) return;
        const current = parseFloat(m.ad.price);
        if (!isNaN(current) && current === newPrice) return; // nothing to do

        m.updating = true;
        const payload = { ...m.ad, price: String(newPrice), api_key: m.key, api_secret: m.secret };

        try {
            const res = await fetch(`${m.url}/update-ad`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            if (res.ok && result.status !== false && !result.error) {
                m.ad.price = newPrice;
                $('currentPrice' + prefix).innerHTML = newPrice;
            } else {
                console.log(`Update ${prefix} error:`, result);
            }
        } catch (e) {
            console.error(`Update ${prefix} network error:`, e);
        } finally {
            m.updating = false;
        }
    }

    /* =====================================================================
     |  RENDER TOP LIST
     ===================================================================== */
    function renderTopList(competitors) {
        const body = $('topListBody');
        if (!competitors || competitors.length === 0) {
            body.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No competitors.</td></tr>`;
            return;
        }
        body.innerHTML = competitors.map((c, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${c.nickName}</td>
                <td><b>${c.price}</b></td>
                <td>${c.balance ?? '---'}</td>
            </tr>
        `).join('');
    }

    /* =====================================================================
     |  LOG TOP MERCHANT (optional DB store)
     ===================================================================== */
    async function logTopMerchant(topMerchant) {
        try {
            await fetch("{{ route('dashboard.com.store') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    merchant_id: topMerchant.id,
                    username: topMerchant.nickName,
                    price: topMerchant.price
                })
            });
        } catch (e) { console.log('log failed', e); }
    }

    /* =====================================================================
     |  THE BEAT-TO-TOP ENGINE (leapfrog between A and B)
     |
     |  1. Read market top list using Merchant A's credentials (reader).
     |  2. Compute beatPrice = topPrice +/- step, clamped to floor.
     |  3. If the ACTIVE merchant is NOT at the top, push it to beatPrice.
     |     Keep pushing (increment) each tick until it reaches the top.
     |  4. When the active merchant reaches the top it "disappears" from the
     |     competitor list (own ad not shown). At that point SWITCH active to
     |     the OTHER merchant and push THAT one to beatPrice too — so the two
     |     leapfrog each other and hold the top two slots.
     |  5. Never push below the floor price (or above the ceiling for buy).
     ===================================================================== */
    async function tick() {
        if (!running) return;
        if (!A.ad || !B.ad) {
            $('engineStatus').innerHTML = 'Select both merchants\' ads first.';
            return;
        }
        if (syncing) return;
        syncing = true;

        try {
            // Use Merchant A (reader) to read the market for A's selected pair.
            const res = await fetch(`${A.url}/analyze-market`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    api_key: A.key,
                    api_secret: A.secret,
                    tokenId: A.ad.tokenId,
                    currencyId: A.ad.currencyId,
                    side: side(),
                    marginPct: 4
                })
            });
            const data = await res.json();

            if (!data.status || !data.top_10_competitors || data.top_10_competitors.length === 0) {
                $('engineStatus').innerHTML = 'No competitors found.';
                syncing = false;
                return;
            }

            const competitors = data.top_10_competitors;
            renderTopList(competitors);

            const topMerchant = competitors[0];
            const topPrice = parseFloat(topMerchant.price);
            if (isNaN(topPrice)) { syncing = false; return; }

            await logTopMerchant(topMerchant);

            const active = merchant(activeIndex);
            const other   = merchant(1 - activeIndex);
            const activePrefix = activeIndex === 0 ? 'A' : 'B';
            const otherPrefix   = activeIndex === 0 ? 'B' : 'A';

            const activePrice = parseFloat(active.ad.price);
            const target = beatPrice(topPrice);

            // Floor protection: if we can't beat any further, hold and switch.
            const floorHit = (side() === '0')
                ? (target <= floor() && activePrice <= floor())
                : (floor() > 0 && target >= floor() && activePrice >= floor());

            if (isAtTop(activePrice, topPrice)) {
                // Active merchant IS at the top (it has "disappeared" from the
                // competitor list as far as our own price tracking is concerned).
                // -> Switch: reduce / re-update the OTHER merchant to beatPrice,
                //    and re-update this one too so both stay at the top.
                $('badge' + activePrefix).classList.add('d-none');
                $('badge' + otherPrefix).classList.remove('d-none');

                $('engineStatus').innerHTML =
                    `${activePrefix} at top (${activePrice}). Leapfrogging -> pushing ${otherPrefix} to ${target}`;

                await pushMerchantPrice(other, target, otherPrefix);
                // keep the active one glued to the top as well
                await pushMerchantPrice(active, target, activePrefix);

                activeIndex = 1 - activeIndex;
                lastBeatPrice = target;

            } else if (floorHit) {
                // We've reached the fixed floor — do not go beyond the market.
                $('engineStatus').innerHTML =
                    `Floor reached (${floor()}). Holding both merchants. No further undercut.`;
                $('badgeA').classList.add('d-none');
                $('badgeB').classList.add('d-none');
                await pushMerchantPrice(active, floor(), activePrefix);
                await pushMerchantPrice(other, floor(), otherPrefix);

            } else {
                // Active merchant is NOT at the top -> increment (beat) toward #1.
                $('badge' + activePrefix).classList.remove('d-none');
                $('badge' + otherPrefix).classList.add('d-none');

                $('engineStatus').innerHTML =
                    `${activePrefix} not at top (${activePrice} vs #1 ${topPrice}). Beating to ${target}`;

                await pushMerchantPrice(active, target, activePrefix);
                lastBeatPrice = target;
            }

        } catch (e) {
            console.error('Engine tick error:', e);
            $('engineStatus').innerHTML = 'Sync error (retrying...)';
        } finally {
            syncing = false;
        }
    }

    /* =====================================================================
     |  START / STOP
     ===================================================================== */
    let loopId = null;

    $('startBtn').addEventListener('click', () => {
        if (!A.ad) { toast('Select Merchant A\'s ad.', 'error'); return; }
        if (!B.ad) { toast('Select Merchant B\'s ad.', 'error'); return; }
        if (!floor() && floor() !== 0) { toast('Set a floor price.', 'error'); return; }
        if (!B.key || !B.secret) { toast('Load Merchant B credentials first.', 'error'); return; }

        running = true;
        activeIndex = 0;
        lastBeatPrice = null;
        $('startBtn').disabled = true;
        $('stopBtn').disabled = false;
        $('liveBadge').classList.remove('d-none');
        $('engineStatus').innerHTML = 'Engine started.';
        toast('Beat-to-Top engine started.', 'info');

        // immediate first tick, then every 800ms
        tick();
        loopId = setInterval(tick, 800);
    });

    $('stopBtn').addEventListener('click', () => {
        running = false;
        if (loopId) { clearInterval(loopId); loopId = null; }
        $('startBtn').disabled = false;
        $('stopBtn').disabled = true;
        $('liveBadge').classList.add('d-none');
        $('badgeA').classList.add('d-none');
        $('badgeB').classList.add('d-none');
        $('engineStatus').innerHTML = 'Engine stopped.';
        toast('Engine stopped.', 'info');
    });

});
</script>
