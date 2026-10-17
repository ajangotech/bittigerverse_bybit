@extends('layouts.app')

@section('content')

<div id="toast" class="app-toast"></div>

<style>
    .app-toast {
        position: fixed; right: 30px; bottom: 30px; z-index: 9999;
        padding: 15px 20px; border-radius: 10px; color: #fff;
        opacity: 0; transition: .3s; background: #111;
    }
    .app-toast.show { opacity: 1; }
    .app-toast.success { background: #E37216; }
    .app-toast.error { background: #dc3545; }
    .app-toast.info { background: #0d6efd; }

    .ads-card { border: none; border-radius: 18px; box-shadow: 0 5px 20px rgba(0,0,0,.08); }
    .badge-copying { background-color: #198754; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    .badge-live { background-color: #dc3545; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; animation: pulse 1s infinite; }
    .badge-top { background-color: #E37216; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    .badge-gone { background-color: #6c757d; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    .badge-probe { background-color: #0d6efd; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    .badge-lock { background-color: #198754; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .ctrl-input { max-width: 140px; }
    .merchant-head { font-weight: 700; }

    .predict-card {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff; border-radius: 18px; padding: 24px;
    }
    .predict-price { font-size: 42px; font-weight: 800; color: #E37216; }
    .predict-range { font-size: 14px; color: #adbac7; }
    .predict-bar {
        height: 8px; border-radius: 4px; background: #333; overflow: hidden; margin-top: 8px;
    }
    .predict-bar-fill {
        height: 100%; background: #E37216; transition: width .5s;
    }

    .log-box {
        max-height: 280px; overflow-y: auto;
        background: #0d1117; border-radius: 10px; padding: 12px 16px;
        font-family: 'Courier New', monospace; font-size: 12.5px; line-height: 1.7;
    }
    .log-line { color: #adbac7; }
    .log-time { color: #6e7681; }
    .log-info  { color: #58a6ff; }
    .log-warn  { color: #d29922; }
    .log-error { color: #f85149; }
    .log-ok    { color: #3fb950; }
    .log-swap  { color: #bc8cff; }
    .log-predict { color: #E37216; font-weight: bold; }
</style>

<div class="container-fluid py-4">

    <!-- ============ PREDICTION PANEL ============ -->
    <div class="predict-card mb-4">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="text-uppercase small text-muted mb-1">Predicted Competitive Price</div>
                <div class="predict-price" id="predictedPrice">---</div>
            </div>
            <div class="col-md-3">
                <div class="text-uppercase small text-muted mb-1">Search Range</div>
                <div class="predict-range" id="searchRange">[ --- , --- ]</div>
                <div class="predict-bar"><div class="predict-bar-fill" id="rangeBar" style="width:100%"></div></div>
            </div>
            <div class="col-md-2">
                <div class="text-uppercase small text-muted mb-1">Confidence</div>
                <div class="predict-price" style="font-size:28px" id="confidence">0%</div>
            </div>
            <div class="col-md-3">
                <div class="text-uppercase small text-muted mb-1">Status</div>
                <div class="fw-bold fs-5" id="predictStatus">Idle</div>
            </div>
        </div>
    </div>

    <!-- ============ CONTROL BAR ============ -->
    <div class="card ads-card mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-cpu me-1"></i> Price Prediction Engine (Two-Probe Convergence)</h5>
            <div class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="mb-1 small fw-bold">Min Price</label>
                    <input type="number" step="0.01" min="0" id="minPrice" class="form-control ctrl-input" placeholder="floor">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Max Price</label>
                    <input type="number" step="0.01" min="0" id="maxPrice" class="form-control ctrl-input" placeholder="ceiling">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Initial Step</label>
                    <input type="number" step="0.01" min="0" id="stepAmount" class="form-control ctrl-input" value="1.00">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Min Step</label>
                    <input type="number" step="0.01" min="0" id="minStep" class="form-control ctrl-input" value="0.01">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Interval (ms)</label>
                    <input type="number" step="100" min="200" id="intervalMs" class="form-control ctrl-input" value="800">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Side</label>
                    <select id="sideSelect" class="form-select ctrl-input">
                        <option value="0">Sell (lower = top)</option>
                        <option value="1">Buy (higher = top)</option>
                    </select>
                </div>
                <button id="startBtn" class="btn btn-success fw-bold"><i class="bi bi-play-fill"></i> Start</button>
                <button id="stopBtn" class="btn btn-danger fw-bold" disabled><i class="bi bi-stop-fill"></i> Stop</button>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ============ MERCHANT A (Lower Probe) ============ -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span class="merchant-head"><i class="bi bi-person-badge me-1 text-primary"></i> Merchant A (Primary)</span>
                        <span id="badgeA" class="badge-gone">Idle</span>
                    </h5>

                    <select class="form-select form-select-lg mb-2" id="userSelectA">
                        <option value="">Select User (Merchant A)...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>

                    <select class="form-select form-select-lg mb-3" id="adsSelectA" disabled>
                        <option value="">Select user first...</option>
                    </select>
                    <input type="hidden" id="adIdA">

                    <div class="border rounded p-3 bg-light">
                        <p class="mb-2"><b>Pair:</b> <span id="pairTextA">---</span></p>
                        <p class="mb-2"><b>Current Price:</b> <span id="currentPriceA" class="fw-bold text-primary">---</span></p>
                        <p class="mb-2"><b>Last Visible Price:</b> <span id="lastGoodA" class="fw-bold">---</span></p>
                        <p class="mb-2"><b>Rank:</b> <span id="rankA">---</span></p>
                        <p class="mb-2"><b>Min/Max Limit:</b> <span id="limitTextA">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusTextA">---</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ MERCHANT B (Upper Probe) ============ -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span class="merchant-head"><i class="bi bi-person-badge me-1 text-success"></i> Merchant B (Secondary)</span>
                        <span id="badgeB" class="badge-gone">Idle</span>
                    </h5>

                    <select class="form-select form-select-lg mb-2" id="userSelectB">
                        <option value="">Select User (Merchant B)...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>

                    <select class="form-select form-select-lg mb-3" id="adsSelectB" disabled>
                        <option value="">Select user first...</option>
                    </select>
                    <input type="hidden" id="adIdB">

                    <div class="border rounded p-3 bg-light">
                        <p class="mb-2"><b>Pair:</b> <span id="pairTextB">---</span></p>
                        <p class="mb-2"><b>Current Price:</b> <span id="currentPriceB" class="fw-bold text-success">---</span></p>
                        <p class="mb-2"><b>Last Visible Price:</b> <span id="lastGoodB" class="fw-bold">---</span></p>
                        <p class="mb-2"><b>Rank:</b> <span id="rankB">---</span></p>
                        <p class="mb-2"><b>Min/Max Limit:</b> <span id="limitTextB">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusTextB">---</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ LIVE TOP LIST + LOG ============ -->
    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-bar-chart-line me-1"></i> Live Market Top List</span>
                        <span id="liveBadge" class="badge-live d-none">LIVE</span>
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr><th>#</th><th>Merchant</th><th>Price</th><th>Available</th><th>Ours?</th></tr>
                            </thead>
                            <tbody id="topListBody">
                                <tr><td colspan="5" class="text-center text-muted">Select both users & ads, then press Start.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 mb-0"><b>Engine:</b> <span id="engineStatus" class="fw-bold text-secondary">Idle</span></p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-journal-text me-1"></i> Prediction Log</h5>
                    <div id="logBox" class="log-box">
                        <div class="log-line log-time">--- Awaiting start ---</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================================
     |  USER CREDENTIAL MAP
     ===================================================================== */
    const USER_MAP = {
        @foreach($users as $u)
            "{{ $u->id }}": {
                url:    "{{ $u->api_url }}",
                key:    "{{ $u->bybit_api_key }}",
                secret: "{{ $u->bybit_api_secret }}"
            },
        @endforeach
    };

    const $ = id => document.getElementById(id);

    /* =====================================================================
     |  MERCHANT STATE
     ===================================================================== */
    function newMerchant() {
        return {
            url: '', key: '', secret: '',
            ads: [], ad: null, updating: false,
            lastVisiblePrice: null,  // last price where this merchant appeared in the top list
            visible: false,
            rank: null,
            lastTriedPrice: null,   // the price we pushed last tick (for disappearance detection)
            probeRole: null,         // 'lower' or 'upper' — assigned during probing
        };
    }
    const A = newMerchant();
    const B = newMerchant();
    const MERCHANTS = [A, B];

    /* =====================================================================
     |  PREDICTION STATE — the heart of the engine
     |
     |  The competitive price is UNKNOWN. We maintain a search range
     |  [rangeLow, rangeHigh] and converge using two probes (A and B).
     |  Each probe is placed at a different price; we observe which one
     |  appears / ranks better, and shrink the range accordingly.
     ===================================================================== */
    const prediction = {
        rangeLow: 0,
        rangeHigh: 0,
        predicted: null,       // current best estimate of the hidden price
        confidence: 0,         // 0-100, how sure we are
        step: 1.0,             // current adaptive step (shrinks as we converge)
        found: false,          // true when a merchant locks at #1
        lockedMerchant: null,  // which merchant (A/B) found the price
        lockedPrice: null,     // the price that won #1
        lockTime: null,        // when we found it
        observations: [],      // history of {price, rank} per merchant
        topPriceHistory: [],   // history of #1 prices observed
        convergeCount: 0,      // how many times we've narrowed the range
    };

    /* =====================================================================
     |  ENGINE STATE
     ===================================================================== */
    let running = false;
    let syncing = false;
    let loopId = null;
    let tickCount = 0;
    let phase = 'observe';     // observe -> probe -> converge -> lock -> monitor

    /* =====================================================================
     |  TOAST + LOG
     ===================================================================== */
    function toast(msg, type = 'success') {
        const t = $('toast');
        t.innerHTML = msg;
        t.className = `app-toast show ${type}`;
        setTimeout(() => t.className = 'app-toast', 3000);
    }

    function log(msg, level = 'info') {
        const box = $('logBox');
        const now = new Date().toLocaleTimeString();
        const line = document.createElement('div');
        line.className = `log-line log-${level}`;
        line.innerHTML = `<span class="log-time">[${now}]</span> ${msg}`;
        box.appendChild(line);
        box.scrollTop = box.scrollHeight;
        while (box.children.length > 150) box.removeChild(box.firstChild);
    }

    /* =====================================================================
     |  CONFIG
     ===================================================================== */
    function side()     { return $('sideSelect').value; }
    function minP()     { return parseFloat($('minPrice').value) || 0; }
    function maxP()     { return parseFloat($('maxPrice').value) || 0; }
    function initStep() { return parseFloat($('stepAmount').value) || 1.0; }
    function minStep()  { return parseFloat($('minStep').value) || 0.01; }
    function interval() { return parseInt($('intervalMs').value) || 800; }
    function isSell()   { return side() === '0'; }

    function clampPrice(p) {
        if (minP() > 0 && p < minP()) p = minP();
        if (maxP() > 0 && p > maxP()) p = maxP();
        return p;
    }

    function prefix(i) { return i === 0 ? 'A' : 'B'; }
    function merchant(i) { return MERCHANTS[i]; }

    function setBadge(i, state) {
        const el = $('badge' + prefix(i));
        el.className = 'badge-' + state;
        const labels = { top: '#1 Top', gone: 'Gone', push: 'Pushing', probe: 'Probing', lock: 'LOCKED', idle: 'Idle' };
        el.textContent = labels[state] || state;
    }

    /* =====================================================================
     |  RENDER
     ===================================================================== */
    function renderAd(m, p) {
        if (!m.ad) {
            $('pairText'+p).innerHTML = '---';
            $('currentPrice'+p).innerHTML = '---';
            $('limitText'+p).innerHTML = '---';
            $('statusText'+p).innerHTML = '---';
            return;
        }
        $('adId'+p).value = m.ad.id;
        $('pairText'+p).innerHTML = `${m.ad.tokenId}/${m.ad.currencyId}`;
        $('currentPrice'+p).innerHTML = m.ad.price;
        $('limitText'+p).innerHTML = `${m.ad.minAmount} - ${m.ad.maxAmount}`;
        $('statusText'+p).innerHTML = m.ad.status ?? 'Active';
    }

    function renderRank(m, p) {
        $('rank'+p).innerHTML = m.visible ? `#${m.rank}` : 'Not in top';
        $('lastGood'+p).innerHTML = m.lastVisiblePrice ?? '---';
    }

    function renderPrediction() {
        $('predictedPrice').innerHTML = prediction.predicted !== null
            ? prediction.predicted.toFixed(2)
            : '---';
        $('searchRange').innerHTML = `[ ${prediction.rangeLow.toFixed(2)} , ${prediction.rangeHigh.toFixed(2)} ]`;

        const range = prediction.rangeHigh - prediction.rangeLow;
        const totalRange = maxP() - minP();
        const pct = totalRange > 0 ? Math.max(2, (range / totalRange) * 100) : 100;
        $('rangeBar').style.width = pct + '%';

        $('confidence').innerHTML = prediction.confidence.toFixed(0) + '%';

        if (prediction.found) {
            $('predictStatus').innerHTML = `LOCKED by ${prediction.lockedMerchant} @ ${prediction.lockedPrice}`;
        } else if (phase === 'observe') {
            $('predictStatus').innerHTML = 'Observing market...';
        } else if (phase === 'probe') {
            $('predictStatus').innerHTML = 'Probing with A & B...';
        } else if (phase === 'converge') {
            $('predictStatus').innerHTML = 'Converging...';
        } else if (phase === 'lock') {
            $('predictStatus').innerHTML = 'Verifying #1 lock...';
        } else if (phase === 'monitor') {
            $('predictStatus').innerHTML = 'Monitoring locked position';
        }
    }

    function renderTopList(competitors) {
        const body = $('topListBody');
        if (!competitors || competitors.length === 0) {
            body.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No competitors.</td></tr>`;
            return;
        }
        body.innerHTML = competitors.map((c, i) => {
            const isA = A.visible && A.rank === (i + 1);
            const isB = B.visible && B.rank === (i + 1);
            const tag = isA ? '<span class="badge-top">A</span>' : isB ? '<span class="badge-copying">B</span>' : '';
            return `<tr>
                <td>${i+1}</td>
                <td>${c.nickName}</td>
                <td><b>${c.price}</b></td>
                <td>${c.balance ?? '---'}</td>
                <td>${tag}</td>
            </tr>`;
        }).join('');
    }

    /* =====================================================================
     |  LOAD ADS
     ===================================================================== */
    async function loadAds(m, selectEl, label) {
        if (!m.key || !m.secret) { toast(`Select ${label} user first.`, 'error'); return; }
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

    /* =====================================================================
     |  BINDINGS
     ===================================================================== */
    function bindUserSelect(sel, m, adsSel, label) {
        sel.addEventListener('change', function () {
            const creds = USER_MAP[this.value];
            if (!creds) {
                m.url = m.key = m.secret = '';
                m.ad = null; m.ads = [];
                adsSel.disabled = true;
                adsSel.innerHTML = `<option value="">Select user first...</option>`;
                renderAd(m, label);
                return;
            }
            m.url = creds.url; m.key = creds.key; m.secret = creds.secret;
            adsSel.disabled = false;
            loadAds(m, adsSel, label);
        });
    }
    bindUserSelect($('userSelectA'), A, $('adsSelectA'), 'A');
    bindUserSelect($('userSelectB'), B, $('adsSelectB'), 'B');

    function bindAdSelect(sel, m, p) {
        sel.addEventListener('change', function () {
            m.ad = m.ads.find(x => String(x.id) === String(this.value)) || null;
            renderAd(m, p);
        });
    }
    bindAdSelect($('adsSelectA'), A, 'A');
    bindAdSelect($('adsSelectB'), B, 'B');

    /* =====================================================================
     |  PUSH PRICE UPDATE
     ===================================================================== */
    async function pushPrice(m, newPrice, p) {
        if (!m.ad) return false;
        const current = parseFloat(m.ad.price);
        if (!isNaN(current) && current === newPrice) return true;

        m.updating = true;
        m.lastTriedPrice = newPrice;
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
                $('currentPrice'+p).innerHTML = newPrice;
                return true;
            }
            log(`${p} update rejected: ${result?.ret_msg || result?.error || 'unknown'}`, 'error');
            return false;
        } catch (e) {
            log(`${p} network error: ${e.message}`, 'error');
            return false;
        } finally {
            m.updating = false;
        }
    }

    /* =====================================================================
     |  DETECT MERCHANT IN TOP LIST
     ===================================================================== */
    function detectMerchant(m, competitors) {
        if (!m.ad) return null;
        const myPrice = parseFloat(m.ad.price);
        if (m.ad.userId) {
            let match = competitors.find(c => String(c.id) === String(m.ad.userId));
            if (match) return match;
        }
        return competitors.find(c => parseFloat(c.price) === myPrice) || null;
    }

    /* =====================================================================
     |  LOG TOP MERCHANT TO DB
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
        } catch (e) { /* silent */ }
    }

    /* =====================================================================
     |  OBSERVE — read market, update visibility, collect price data
     ===================================================================== */
    async function observe() {
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
            return null;
        }

        const competitors = data.top_10_competitors;
        const topPrice = parseFloat(competitors[0].price);

        // Track top price history
        prediction.topPriceHistory.push(topPrice);
        if (prediction.topPriceHistory.length > 20) prediction.topPriceHistory.shift();

        // Detect each merchant
        MERCHANTS.forEach((m, i) => {
            const match = detectMerchant(m, competitors);
            if (match) {
                m.visible = true;
                m.rank = competitors.indexOf(match) + 1;
                m.lastVisiblePrice = parseFloat(match.price);
                renderRank(m, prefix(i));
            } else {
                m.visible = false;
                m.rank = null;
                renderRank(m, prefix(i));
            }
        });

        renderTopList(competitors);
        await logTopMerchant(competitors[0]);

        // Collect all top prices as clues
        const topPrices = competitors.map(c => parseFloat(c.price)).filter(p => !isNaN(p));

        return { competitors, topPrice, topPrices };
    }

    /* =====================================================================
     |  PREDICTION HELPERS
     ===================================================================== */

    // Build an initial estimate from the cluster of top prices.
    // The competitive price is likely near the most common / median top price.
    function estimateFromTops(topPrices) {
        if (!topPrices || topPrices.length === 0) return null;
        const sorted = [...topPrices].sort((a, b) => a - b);
        const median = sorted[Math.floor(sorted.length / 2)];
        return median;
    }

    // Find the most frequent price (mode) in the top list — this is the
    // price the market seems to accept most.
    function modePrice(topPrices) {
        if (!topPrices || topPrices.length === 0) return null;
        const counts = {};
        topPrices.forEach(p => { counts[p] = (counts[p] || 0) + 1; });
        let mode = topPrices[0], maxCount = 0;
        for (const [p, c] of Object.entries(counts)) {
            if (c > maxCount) { maxCount = c; mode = parseFloat(p); }
        }
        return mode;
    }

    // Shrink the search range based on probe results.
    // If a probe at price P is visible and ranked well, the competitive
    // price is at or near P. If it disappeared, the boundary is just past P.
    function narrowRange() {
        const oldRange = prediction.rangeHigh - prediction.rangeLow;

        // Evidence from both probes
        const aVis = A.visible;
        const bVis = B.visible;
        const aPrice = parseFloat(A.ad.price);
        const bPrice = parseFloat(B.ad.price);

        if (isSell()) {
            // Sell: lower = more competitive. The hidden price is the LOWEST
            // price that still appears in the top list.
            if (aVis && bVis) {
                // Both visible -> the competitive price is at or below the
                // lower of the two. Push the lower boundary down.
                const lower = Math.min(aPrice, bPrice);
                prediction.rangeHigh = lower;
                prediction.predicted = lower;
            } else if (aVis && !bVis) {
                // A visible, B not -> boundary is between A and B's prices.
                prediction.rangeHigh = aPrice;
                if (bPrice > aPrice) prediction.rangeLow = Math.max(prediction.rangeLow, aPrice);
                prediction.predicted = aPrice;
            } else if (!aVis && bVis) {
                prediction.rangeHigh = bPrice;
                if (aPrice > bPrice) prediction.rangeLow = Math.max(prediction.rangeLow, bPrice);
                prediction.predicted = bPrice;
            } else {
                // Both invisible -> both went too low (below the floor the
                // market accepts). The competitive price is ABOVE both.
                const higher = Math.max(aPrice, bPrice);
                prediction.rangeLow = higher;
                prediction.predicted = higher;
            }
        } else {
            // Buy: higher = more competitive. The hidden price is the HIGHEST
            // price that still appears.
            if (aVis && bVis) {
                const higher = Math.max(aPrice, bPrice);
                prediction.rangeLow = higher;
                prediction.predicted = higher;
            } else if (aVis && !bVis) {
                prediction.rangeLow = aPrice;
                if (bPrice < aPrice) prediction.rangeHigh = Math.min(prediction.rangeHigh, aPrice);
                prediction.predicted = aPrice;
            } else if (!aVis && bVis) {
                prediction.rangeLow = bPrice;
                if (aPrice < bPrice) prediction.rangeHigh = Math.min(prediction.rangeHigh, bPrice);
                prediction.predicted = bPrice;
            } else {
                // Both invisible -> both went too high. Competitive price is BELOW.
                const lower = Math.min(aPrice, bPrice);
                prediction.rangeHigh = lower;
                prediction.predicted = lower;
            }
        }

        // Clamp to bounds
        prediction.rangeLow = Math.max(prediction.rangeLow, minP());
        prediction.rangeHigh = Math.min(prediction.rangeHigh, maxP());

        // Ensure rangeLow <= rangeHigh
        if (prediction.rangeLow > prediction.rangeHigh) {
            const mid = (prediction.rangeLow + prediction.rangeHigh) / 2;
            prediction.rangeLow = mid;
            prediction.rangeHigh = mid;
        }

        const newRange = prediction.rangeHigh - prediction.rangeLow;
        prediction.convergeCount++;

        // Shrink step proportionally to the remaining range
        prediction.step = Math.max(minStep(), newRange / 4);

        // Update confidence: higher when range is small
        const totalRange = maxP() - minP();
        if (totalRange > 0) {
            prediction.confidence = Math.min(99, Math.round(100 * (1 - newRange / totalRange)));
        }

        log(`Range narrowed: [${prediction.rangeLow.toFixed(2)}, ${prediction.rangeHigh.toFixed(2)}] step=${prediction.step.toFixed(2)} conf=${prediction.confidence}%`, 'predict');
    }

    /* =====================================================================
     |  THE PREDICTION ENGINE
     |
     |  Phase 1: OBSERVE  — read the market, collect top prices, build estimate
     |  Phase 2: PROBE    — place A and B at different prices around the estimate
     |  Phase 3: CONVERGE — observe which probe survives, narrow the range,
     |                       shrink step, repeat
     |  Phase 4: LOCK     — when range is tiny, place A at the predicted price.
     |                       If A hits #1, we've found it.
     |  Phase 5: MONITOR  — A is locked at #1. Keep B nearby. If A drops,
     |                       re-converge.
     ===================================================================== */
    async function tick() {
        if (!running) return;
        if (!A.ad || !B.ad) { $('engineStatus').innerHTML = 'Select both ads.'; return; }
        if (syncing) return;
        syncing = true;
        tickCount++;

        try {
            // ---------- OBSERVE ----------
            const obs = await observe();
            if (!obs) {
                $('engineStatus').innerHTML = 'No competitors found.';
                if (tickCount === 1) log('No competitors in market.', 'warn');
                syncing = false;
                return;
            }
            const { competitors, topPrice, topPrices } = obs;

            // ---------- DISAPPEARANCE DETECTION ----------
            // If a merchant was pushed last tick and is now invisible,
            // that price was past the market boundary. Revert to last visible.
            let reverted = false;
            for (let i = 0; i < 2; i++) {
                const m = merchant(i);
                const p = prefix(i);
                if (!m.visible && m.lastTriedPrice != null && m.lastVisiblePrice != null) {
                    const tried = m.lastTriedPrice;
                    const good = m.lastVisiblePrice;
                    // Did we push further than where we were last visible?
                    const wasFurther = isSell() ? tried < good : tried > good;
                    if (wasFurther) {
                        log(`${p} disappeared at ${tried} — market boundary crossed. Reverting to ${good}.`, 'error');
                        await pushPrice(m, good, p);
                        m.lastTriedPrice = null;
                        setBadge(i, 'gone');
                        // This disappearance tells us the boundary is between
                        // good and tried. Narrow the range.
                        if (isSell()) {
                            prediction.rangeLow = Math.max(prediction.rangeLow, good);
                            prediction.rangeHigh = Math.min(prediction.rangeHigh, tried);
                        } else {
                            prediction.rangeHigh = Math.min(prediction.rangeHigh, good);
                            prediction.rangeLow = Math.max(prediction.rangeLow, tried);
                        }
                        reverted = true;
                    }
                }
            }
            if (reverted) {
                renderPrediction();
                syncing = false;
                return;
            }

            // ---------- PHASE: LOCK CHECK ----------
            // If a merchant is at #1, we may have found the price.
            if (A.visible && A.rank === 1) {
                if (!prediction.found) {
                    prediction.found = true;
                    prediction.lockedMerchant = 'A';
                    prediction.lockedPrice = parseFloat(A.ad.price);
                    prediction.lockTime = Date.now();
                    prediction.confidence = 100;
                    setBadge(0, 'lock');
                    log(`*** PRICE FOUND! A locked at #1 with price ${prediction.lockedPrice} ***`, 'ok');
                    toast(`A found the competitive price: ${prediction.lockedPrice}`, 'success');
                }
                phase = 'monitor';
                $('engineStatus').innerHTML = `A LOCKED at #1 (${A.ad.price}). Monitoring.`;

                // Keep B near the locked price so it's ready as backup
                if (B.lastVisiblePrice !== prediction.lockedPrice) {
                    const bTarget = clampPrice(prediction.lockedPrice);
                    if (bTarget !== parseFloat(B.ad.price)) {
                        log(`Syncing B to locked price ${bTarget} as backup.`, 'info');
                        await pushPrice(B, bTarget, 'B');
                        setBadge(1, 'probe');
                    }
                }
                renderPrediction();
                syncing = false;
                return;
            }

            if (B.visible && B.rank === 1) {
                if (!prediction.found) {
                    prediction.found = true;
                    prediction.lockedMerchant = 'B';
                    prediction.lockedPrice = parseFloat(B.ad.price);
                    prediction.lockTime = Date.now();
                    prediction.confidence = 100;
                    setBadge(1, 'lock');
                    log(`*** PRICE FOUND! B locked at #1 with price ${prediction.lockedPrice} ***`, 'ok');
                    toast(`B found the competitive price: ${prediction.lockedPrice}`, 'success');

                    // Immediately push A to the same price (A has priority)
                    const aTarget = clampPrice(prediction.lockedPrice);
                    if (aTarget !== parseFloat(A.ad.price)) {
                        log(`Copying locked price to A (priority) at ${aTarget}.`, 'info');
                        await pushPrice(A, aTarget, 'A');
                        setBadge(0, 'probe');
                    }
                }
                phase = 'monitor';
                $('engineStatus').innerHTML = `B LOCKED at #1 (${B.ad.price}). A syncing.`;
                renderPrediction();
                syncing = false;
                return;
            }

            // ---------- PHASE: MONITOR (already found, watching) ----------
            if (prediction.found) {
                // The locked merchant dropped from #1 — competitor undercut.
                // Re-enter convergence to find the new price.
                if ((!A.visible || A.rank !== 1) && (!B.visible || B.rank !== 1)) {
                    log(`Lost #1 position. Re-entering convergence to find new price.`, 'warn');
                    prediction.found = false;
                    prediction.lockedMerchant = null;
                    prediction.confidence = Math.max(40, prediction.confidence - 30);
                    phase = 'converge';
                    // Reset range around the last known good price +/- some buffer
                    const last = prediction.lockedPrice || topPrice;
                    prediction.rangeLow = clampPrice(isSell() ? last - prediction.step * 5 : last);
                    prediction.rangeHigh = clampPrice(isSell() ? last : last + prediction.step * 5);
                    prediction.step = initStep();
                    setBadge(0, 'probe');
                    setBadge(1, 'probe');
                }
                renderPrediction();
                syncing = false;
                return;
            }

            // ---------- PHASE: INITIAL OBSERVATION ----------
            if (phase === 'observe' || tickCount === 1) {
                // Build initial estimate from top prices
                const median = estimateFromTops(topPrices);
                const mode = modePrice(topPrices);

                // Use mode as primary estimate (the price the market accepts most)
                prediction.predicted = mode || median || topPrice;

                // Set initial search range
                prediction.rangeLow = minP() || (prediction.predicted - initStep() * 10);
                prediction.rangeHigh = maxP() || (prediction.predicted + initStep() * 10);
                prediction.step = initStep();

                log(`Initial observation: topPrice=${topPrice}, mode=${mode}, median=${median}, predicted=${prediction.predicted}`, 'predict');
                log(`Search range: [${prediction.rangeLow.toFixed(2)}, ${prediction.rangeHigh.toFixed(2)}]`, 'info');

                // Move to probing phase
                phase = 'probe';
                renderPrediction();
                syncing = false;
                return;
            }

            // ---------- PHASE: PROBE ----------
            // Place A and B at two different prices around the predicted value
            // to triangulate the hidden competitive price.
            if (phase === 'probe') {
                const mid = prediction.predicted;
                const s = prediction.step;

                // A (primary) goes to the predicted price
                const aTarget = clampPrice(mid);
                // B (secondary) goes one step further (more aggressive)
                const bTarget = clampPrice(isSell() ? mid - s : mid + s);

                log(`Probing: A -> ${aTarget.toFixed(2)}, B -> ${bTarget.toFixed(2)} (step ${s.toFixed(2)})`, 'predict');

                if (aTarget !== parseFloat(A.ad.price)) {
                    await pushPrice(A, aTarget, 'A');
                    A.probeRole = 'center';
                    setBadge(0, 'probe');
                }
                if (bTarget !== parseFloat(B.ad.price)) {
                    await pushPrice(B, bTarget, 'B');
                    B.probeRole = isSell() ? 'lower' : 'upper';
                    setBadge(1, 'probe');
                }

                // Move to converge — next tick will read the results
                phase = 'converge';
                renderPrediction();
                $('engineStatus').innerHTML = `Probing: A=${aTarget.toFixed(2)} B=${bTarget.toFixed(2)}`;
                syncing = false;
                return;
            }

            // ---------- PHASE: CONVERGE ----------
            // Read the probe results, narrow the range, re-probe with a
            // smaller step. Repeat until the range is tiny, then lock.
            if (phase === 'converge') {
                narrowRange();
                renderPrediction();

                const range = prediction.rangeHigh - prediction.rangeLow;

                // If range is very small, we've converged — try to lock
                if (range <= minStep() * 2) {
                    phase = 'lock';
                    log(`Converged to [${prediction.rangeLow.toFixed(2)}, ${prediction.rangeHigh.toFixed(2)}]. Attempting lock.`, 'ok');
                    syncing = false;
                    return;
                }

                // Re-probe with the narrowed range
                const mid = (prediction.rangeLow + prediction.rangeHigh) / 2;
                prediction.predicted = mid;
                const s = prediction.step;

                const aTarget = clampPrice(mid);
                const bTarget = clampPrice(isSell() ? mid - s : mid + s);

                log(`Converging: A -> ${aTarget.toFixed(2)}, B -> ${bTarget.toFixed(2)}, range=${range.toFixed(2)}`, 'predict');

                if (aTarget !== parseFloat(A.ad.price)) {
                    await pushPrice(A, aTarget, 'A');
                    setBadge(0, 'probe');
                }
                if (bTarget !== parseFloat(B.ad.price)) {
                    await pushPrice(B, bTarget, 'B');
                    setBadge(1, 'probe');
                }

                $('engineStatus').innerHTML = `Converging: predicted ${mid.toFixed(2)}, range ${range.toFixed(2)}`;
                syncing = false;
                return;
            }

            // ---------- PHASE: LOCK ----------
            // Place A at the final predicted price. If A appears at #1 next
            // tick, the lock phase above will catch it.
            if (phase === 'lock') {
                const finalPrice = clampPrice(prediction.predicted);
                log(`Lock attempt: placing A at ${finalPrice.toFixed(2)}`, 'ok');

                if (finalPrice !== parseFloat(A.ad.price)) {
                    await pushPrice(A, finalPrice, 'A');
                    setBadge(0, 'probe');
                }
                // B goes to the same price as backup
                if (finalPrice !== parseFloat(B.ad.price)) {
                    await pushPrice(B, finalPrice, 'B');
                    setBadge(1, 'probe');
                }

                // Next tick will check if A/B is at #1 (handled at the top).
                // If not, we'll fall through and re-converge.
                phase = 'converge'; // will re-check visibility next tick
                $('engineStatus').innerHTML = `Lock attempt at ${finalPrice.toFixed(2)} — awaiting verification`;
                syncing = false;
                return;
            }

        } catch (e) {
            console.error('Engine error:', e);
            log(`Engine error: ${e.message}`, 'error');
            $('engineStatus').innerHTML = 'Sync error (retrying...)';
        } finally {
            syncing = false;
            renderPrediction();
        }
    }

    /* =====================================================================
     |  START / STOP
     ===================================================================== */
    $('startBtn').addEventListener('click', () => {
        if (!A.ad) { toast('Select Merchant A\'s ad.', 'error'); return; }
        if (!B.ad) { toast('Select Merchant B\'s ad.', 'error'); return; }
        if (!minP() && !maxP()) { toast('Set at least a min or max price.', 'error'); return; }
        if (A.ad.tokenId !== B.ad.tokenId || A.ad.currencyId !== B.ad.currencyId) {
            toast('Both merchants must use the SAME pair.', 'error'); return;
        }

        // Reset state
        MERCHANTS.forEach(m => {
            m.lastVisiblePrice = null;
            m.visible = false;
            m.rank = null;
            m.lastTriedPrice = null;
            m.probeRole = null;
        });
        prediction.rangeLow = 0;
        prediction.rangeHigh = 0;
        prediction.predicted = null;
        prediction.confidence = 0;
        prediction.step = initStep();
        prediction.found = false;
        prediction.lockedMerchant = null;
        prediction.lockedPrice = null;
        prediction.lockTime = null;
        prediction.observations = [];
        prediction.topPriceHistory = [];
        prediction.convergeCount = 0;

        running = true;
        tickCount = 0;
        phase = 'observe';
        $('logBox').innerHTML = '';
        log('Prediction engine started. Searching for the hidden competitive price...', 'ok');
        $('startBtn').disabled = true;
        $('stopBtn').disabled = false;
        $('liveBadge').classList.remove('d-none');
        $('engineStatus').innerHTML = 'Engine started.';
        toast('Price prediction engine started.', 'info');

        tick();
        loopId = setInterval(tick, interval());
    });

    $('stopBtn').addEventListener('click', () => {
        running = false;
        if (loopId) { clearInterval(loopId); loopId = null; }
        $('startBtn').disabled = false;
        $('stopBtn').disabled = true;
        $('liveBadge').classList.add('d-none');
        setBadge(0, 'gone');
        setBadge(1, 'gone');
        $('engineStatus').innerHTML = 'Engine stopped.';
        log('Engine stopped.', 'warn');
        toast('Engine stopped.', 'info');
    });

});
</script>
