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
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .ctrl-input { max-width: 150px; }
    .merchant-head { font-weight: 700; }
</style>

<div class="container-fluid py-4">

    <!-- ============ GLOBAL CONTROL BAR ============ -->
    <div class="card ads-card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-trophy me-1"></i> Beat-To-Top Engine (Two-Merchant Leapfrog)</h5>

            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Floor Price</label>
                    <input type="number" step="0.01" min="0" id="floorPrice" class="form-control ctrl-input" placeholder="e.g. 95000">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Step</label>
                    <input type="number" step="0.01" min="0" id="stepAmount" class="form-control ctrl-input" value="0.10">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small fw-bold">Side</label>
                    <select id="sideSelect" class="form-select ctrl-input">
                        <option value="0">Sell (lower = top)</option>
                        <option value="1">Buy (higher = top)</option>
                    </select>
                </div>
                <button id="startBtn" class="btn btn-success fw-bold"><i class="bi bi-play-fill"></i> Start Beating</button>
                <button id="stopBtn" class="btn btn-danger fw-bold" disabled><i class="bi bi-stop-fill"></i> Stop</button>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ============ MERCHANT A ============ -->
        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                        <span class="merchant-head"><i class="bi bi-person-badge me-1 text-primary"></i> Merchant A</span>
                        <span id="badgeA" class="badge-copying d-none">Pushing</span>
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
                        <span class="merchant-head"><i class="bi bi-person-badge me-1 text-success"></i> Merchant B</span>
                        <span id="badgeB" class="badge-copying d-none">Pushing</span>
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
                        <p class="mb-2"><b>Min Limit:</b> <span id="minTextB">---</span></p>
                        <p class="mb-2"><b>Max Limit:</b> <span id="maxTextB">---</span></p>
                        <p class="mb-0"><b>Status:</b> <span id="statusTextB">---</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ LIVE MARKET TOP LIST ============ -->
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
                                <tr><th>#</th><th>Merchant</th><th>Price</th><th>Available</th></tr>
                            </thead>
                            <tbody id="topListBody">
                                <tr><td colspan="4" class="text-center text-muted">Select both users & ads, then press Start.</td></tr>
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
     |  USER CREDENTIAL MAP (injected from $users — same pattern as the
     |  original auth()->user() usage, now extended to every user).
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

    /* =====================================================================
     |  MERCHANT STATE
     ===================================================================== */
    function newMerchant() {
        return { url: '', key: '', secret: '', ads: [], ad: null, updating: false };
    }
    const A = newMerchant();
    const B = newMerchant();

    /* =====================================================================
     |  ENGINE STATE
     ===================================================================== */
    let running = false;
    let syncing = false;
    let activeIndex = 0;        // 0 = A, 1 = B (the one we are currently pushing)
    let lastBeatPrice = null;

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
    function side()  { return $('sideSelect').value; }
    function floor() { return parseFloat($('floorPrice').value) || 0; }
    function step()  { return parseFloat($('stepAmount').value) || 0.10; }

    // Beat price = top price +/- step, clamped to the floor so neither
    // merchant ever crosses the fixed market boundary the user set.
    function beatPrice(topPrice) {
        const s = step();
        let p = (side() === '0') ? topPrice - s : topPrice + s;
        if (side() === '0') {
            if (floor() > 0 && p < floor()) p = floor();
        } else {
            if (floor() > 0 && p > floor()) p = floor();
        }
        return p;
    }

    function isAtTop(myPrice, topPrice) {
        if (myPrice == null || isNaN(myPrice)) return false;
        return (side() === '0') ? myPrice <= topPrice : myPrice >= topPrice;
    }

    function merchant(i) { return i === 0 ? A : B; }
    function prefix(i)   { return i === 0 ? 'A' : 'B'; }

    function renderAd(m, p) {
        if (!m.ad) {
            $('pairText'+p).innerHTML = '---';
            $('currentPrice'+p).innerHTML = '---';
            $('minText'+p).innerHTML = '---';
            $('maxText'+p).innerHTML = '---';
            $('statusText'+p).innerHTML = '---';
            return;
        }
        $('adId'+p).value = m.ad.id;
        $('pairText'+p).innerHTML = `${m.ad.tokenId}/${m.ad.currencyId}`;
        $('currentPrice'+p).innerHTML = m.ad.price;
        $('minText'+p).innerHTML = m.ad.minAmount;
        $('maxText'+p).innerHTML = m.ad.maxAmount;
        $('statusText'+p).innerHTML = m.ad.status ?? 'Active';
    }

    /* =====================================================================
     |  LOAD ADS for a merchant (from their own credentials)
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
     |  USER SELECT BINDING
     ===================================================================== */
    function bindUserSelect(sel, m, adsSel, label) {
        sel.addEventListener('change', function () {
            const creds = USER_MAP[this.value];
            if (!creds) {
                m.url = m.key = m.secret = '';
                m.ad = null; m.ads = [];
                adsSel.disabled = true;
                adsSel.innerHTML = `<option value="">Select user first...</option>`;
                renderAd(m, label === 'A' ? 'A' : 'B');
                return;
            }
            m.url = creds.url; m.key = creds.key; m.secret = creds.secret;
            adsSel.disabled = false;
            loadAds(m, adsSel, label);
        });
    }
    bindUserSelect($('userSelectA'), A, $('adsSelectA'), 'A');
    bindUserSelect($('userSelectB'), B, $('adsSelectB'), 'B');

    /* =====================================================================
     |  AD SELECT BINDING
     ===================================================================== */
    function bindAdSelect(sel, m, p) {
        sel.addEventListener('change', function () {
            m.ad = m.ads.find(x => String(x.id) === String(this.value)) || null;
            renderAd(m, p);
        });
    }
    bindAdSelect($('adsSelectA'), A, 'A');
    bindAdSelect($('adsSelectB'), B, 'B');

    /* =====================================================================
     |  PUSH A PRICE UPDATE (single attempt, returns true on success)
     ===================================================================== */
    async function pushMerchantPrice(m, newPrice, p) {
        if (!m.ad) return false;
        const current = parseFloat(m.ad.price);
        if (!isNaN(current) && current === newPrice) return true;

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
                $('currentPrice'+p).innerHTML = newPrice;
                return true;
            }
            console.log(`Update ${p} error:`, result);
            return false;
        } catch (e) {
            console.error(`Update ${p} network error:`, e);
            return false;
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
            <tr><td>${i+1}</td><td>${c.nickName}</td><td><b>${c.price}</b></td><td>${c.balance ?? '---'}</td></tr>
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
     |  BEAT-TO-TOP ENGINE — continuous leapfrog
     |
     |  Reader = Merchant A's credentials (single reader, as requested).
     |  1. Read market top list.
     |  2. target = beatPrice(topPrice).
     |  3. If active merchant NOT at top -> push it to target. Keep ticking.
     |  4. When active reaches top (disappears from competitor list as #1):
     |     - SWITCH active to the other merchant.
     |     - Push the OTHER merchant to target (so it now takes #1).
     |     - Re-glue the first one at target too (both compete at same price).
     |  5. Floor hit -> hold both, no further undercut.
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

            const ai = activeIndex;
            const active = merchant(ai);
            const other  = merchant(1 - ai);
            const ap = prefix(ai);
            const op = prefix(1 - ai);

            const activePrice = parseFloat(active.ad.price);
            const target = beatPrice(topPrice);

            const floorHit = (side() === '0')
                ? (floor() > 0 && target <= floor() && activePrice <= floor())
                : (floor() > 0 && target >= floor() && activePrice >= floor());

            if (floorHit) {
                $('engineStatus').innerHTML = `Floor reached (${floor()}). Holding both merchants.`;
                $('badgeA').classList.add('d-none');
                $('badgeB').classList.add('d-none');
                await pushMerchantPrice(active, floor(), ap);
                await pushMerchantPrice(other, floor(), op);
                lastBeatPrice = floor();

            } else if (isAtTop(activePrice, topPrice)) {
                // Active merchant IS at the top -> it "disappeared" from the
                // competitor list as the #1. Leapfrog: switch to the other
                // merchant and push it to target, while re-gluing this one so
                // both compete at the same price and hold the top slots.
                $('badge'+ap).classList.add('d-none');
                $('badge'+op).classList.remove('d-none');
                $('engineStatus').innerHTML =
                    `${ap} at top (${activePrice}). Leapfrog -> pushing ${op} to ${target}, re-gluing ${ap}.`;

                await pushMerchantPrice(other, target, op);
                await pushMerchantPrice(active, target, ap);
                activeIndex = 1 - ai;
                lastBeatPrice = target;

            } else {
                // Active merchant NOT at top -> increment toward #1.
                $('badge'+ap).classList.remove('d-none');
                $('badge'+op).classList.add('d-none');
                $('engineStatus').innerHTML =
                    `${ap} not at top (${activePrice} vs #1 ${topPrice}). Beating to ${target}`;
                await pushMerchantPrice(active, target, ap);
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
        if (!$('floorPrice').value) { toast('Set a floor price.', 'error'); return; }
        if (A.ad.tokenId !== B.ad.tokenId || A.ad.currencyId !== B.ad.currencyId) {
            toast('Both merchants must use the SAME pair.', 'error'); return;
        }

        running = true;
        activeIndex = 0;
        lastBeatPrice = null;
        $('startBtn').disabled = true;
        $('stopBtn').disabled = false;
        $('liveBadge').classList.remove('d-none');
        $('engineStatus').innerHTML = 'Engine started.';
        toast('Beat-to-Top engine started.', 'info');

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
