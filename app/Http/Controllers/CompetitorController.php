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
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .ctrl-input { max-width: 140px; }
    .merchant-head { font-weight: 700; }

    .log-box {
        max-height: 260px; overflow-y: auto;
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
</style>

<div class="container-fluid py-4">

    <!-- ============ CONTROL BAR ============ -->
    <div class="card ads-card mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-trophy me-1"></i> Adaptive Price Discovery Engine</h5>
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
                    <label class="mb-1 small fw-bold">Step</label>
                    <input type="number" step="0.01" min="0" id="stepAmount" class="form-control ctrl-input" value="0.10">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Interval (ms)</label>
                    <input type="number" step="100" min="200" id="intervalMs" class="form-control ctrl-input" value="800">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Max Retries</label>
                    <input type="number" step="1" min="1" id="maxRetries" class="form-control ctrl-input" value="5">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Cooldown (s)</label>
                    <input type="number" step="1" min="1" id="cooldownSec" class="form-control ctrl-input" value="10">
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

        <!-- ============ MERCHANT A ============ -->
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
                        <p class="mb-2"><b>Last Good Price:</b> <span id="lastGoodA" class="fw-bold">---</span></p>
                        <p class="mb-2"><b>Rank:</b> <span id="rankA">---</span></p>
                        <p class="mb-2"><b>Min/Max Limit:</b> <span id="limitTextA">---</span></p>
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
                        <p class="mb-2"><b>Last Good Price:</b> <span id="lastGoodB" class="fw-bold">---</span></p>
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
                    <h5 class="fw-bold mb-3"><i class="bi bi-journal-text me-1"></i> Decision Log</h5>
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
     |  MERCHANT STATE — each merchant tracks its own discovery history
     ===================================================================== */
    function newMerchant() {
        return {
            url: '', key: '', secret: '',
            ads: [], ad: null, updating: false,
            lastGoodPrice: null,
            visible: false,
            rank: null,
            consecutiveDisappear: 0,
            cooldownUntil: 0,
            adjustedLastTick: false,
            lastTriedPrice: null
        };
    }
    const A = newMerchant();
    const B = newMerchant();
    const MERCHANTS = [A, B];

    /* =====================================================================
     |  ENGINE STATE
     ===================================================================== */
    let running = false;
    let syncing = false;
    let focusIndex = 0;          // who we're currently optimising (A priority)
    let loopId = null;
    let tickCount = 0;

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
     |  DECISION LOG
     ===================================================================== */
    function log(msg, level = 'info') {
        const box = $('logBox');
        const now = new Date().toLocaleTimeString();
        const line = document.createElement('div');
        line.className = `log-line log-${level}`;
        line.innerHTML = `<span class="log-time">[${now}]</span> ${msg}`;
        box.appendChild(line);
        box.scrollTop = box.scrollHeight;
        while (box.children.length > 120) box.removeChild(box.firstChild);
    }

    /* =====================================================================
     |  CONFIG READERS
     ===================================================================== */
    function side()   { return $('sideSelect').value; }
    function minP()   { return parseFloat($('minPrice').value) || 0; }
    function maxP()   { return parseFloat($('maxPrice').value) || 0; }
    function step()   { return parseFloat($('stepAmount').value) || 0.10; }
    function interval() { return parseInt($('intervalMs').value) || 800; }
    function maxRetries() { return parseInt($('maxRetries').value) || 5; }
    function cooldownSec() { return parseInt($('cooldownSec').value) || 10; }

    // Sell: competitive = lower. Buy: competitive = higher.
    function isSell() { return side() === '0'; }

    function clampPrice(p) {
        if (minP() > 0 && p < minP()) p = minP();
        if (maxP() > 0 && p > maxP()) p = maxP();
        return p;
    }

    // Move one step in the competitive direction (toward #1).
    function competePrice(currentPrice) {
        let p = isSell() ? currentPrice - step() : currentPrice + step();
        return clampPrice(p);
    }

    // Move one step AWAY from competitive (back-off direction).
    function backoffPrice(currentPrice) {
        let p = isSell() ? currentPrice + step() : currentPrice - step();
        return clampPrice(p);
    }

    // Is myPrice at least as competitive as topPrice?
    function isAtTop(myPrice, topPrice) {
        if (myPrice == null || isNaN(myPrice)) return false;
        return isSell() ? myPrice <= topPrice : myPrice >= topPrice;
    }

    function prefix(i) { return i === 0 ? 'A' : 'B'; }
    function merchant(i) { return MERCHANTS[i]; }

    function inCooldown(m) { return Date.now() < m.cooldownUntil; }

    function setBadge(i, state) {
        const el = $('badge' + prefix(i));
        el.className = 'badge-' + state;
        el.textContent = state === 'top' ? '#1 Top' : state === 'gone' ? 'Gone' : state === 'push' ? 'Pushing' : 'Idle';
    }

    /* =====================================================================
     |  RENDER AD DETAILS
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
        $('lastGood'+p).innerHTML = m.lastGoodPrice ?? '---';
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
     |  PUSH PRICE UPDATE (verify success before trusting)
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
                log(`${p} price updated -> ${newPrice}`, 'ok');
                return true;
            }
            log(`${p} update REJECTED by API: ${result?.ret_msg || result?.error || 'unknown'}`, 'error');
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
     |  Match by userId if available, otherwise by exact price.
     ===================================================================== */
    function detectMerchant(m, competitors) {
        if (!m.ad) return null;
        const myPrice = parseFloat(m.ad.price);

        // Try userId match if the ad carries one
        if (m.ad.userId) {
            let match = competitors.find(c => String(c.id) === String(m.ad.userId));
            if (match) return match;
        }
        // Fallback: exact price match
        return competitors.find(c => parseFloat(c.price) === myPrice) || null;
    }

    /* =====================================================================
     |  RENDER TOP LIST
     ===================================================================== */
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
     |  LOG TOP MERCHANT (DB store)
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
     |  OBSERVATION PHASE
     |  Read the market and update each merchant's visibility / lastGoodPrice.
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

        // Detect each merchant
        MERCHANTS.forEach((m, i) => {
            const match = detectMerchant(m, competitors);
            const wasVisible = m.visible;
            if (match) {
                m.visible = true;
                m.rank = competitors.indexOf(match) + 1;
                m.lastGoodPrice = parseFloat(match.price);
                m.consecutiveDisappear = 0;
                m.cooldownUntil = 0;
                renderRank(m, prefix(i));
            } else {
                m.visible = false;
                m.rank = null;
                renderRank(m, prefix(i));
            }
            m.adjustedLastTick = false; // reset for this tick
        });

        renderTopList(competitors);
        await logTopMerchant(competitors[0]);

        return { competitors, topPrice };
    }

    /* =====================================================================
     |  ADAPTIVE ENGINE — core decision logic
     |
     |  Priority: A first. Discover price through observation + small steps.
     |  If a merchant disappears after an adjustment, revert to lastGoodPrice.
     |  Never exceed min/max. Cooldown after maxRetries consecutive disappears.
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
                log('No competitors in market.', 'warn');
                syncing = false;
                return;
            }
            const { competitors, topPrice } = obs;

            const aPrice = parseFloat(A.ad.price);
            const bPrice = parseFloat(B.ad.price);

            // ---------- DETECT DISAPPEARANCE ----------
            // If a merchant was adjusted last tick and is now invisible,
            // the adjustment was too aggressive -> revert to lastGoodPrice.
            let reverted = false;
            for (let i = 0; i < 2; i++) {
                const m = merchant(i);
                const p = prefix(i);
                if (!m.visible && m.lastTriedPrice != null && m.lastGoodPrice != null) {
                    const tried = m.lastTriedPrice;
                    const good = m.lastGoodPrice;
                    // Only revert if the tried price was MORE aggressive than last good
                    const wasAggressive = isSell() ? tried < good : tried > good;
                    if (wasAggressive) {
                        log(`${p} DISAPPEARED after push to ${tried}. Reverting to last good ${good}.`, 'error');
                        await pushPrice(m, good, p);
                        m.consecutiveDisappear++;
                        m.lastTriedPrice = null;
                        setBadge(i, 'gone');
                        // Cooldown if too many disappearances
                        if (m.consecutiveDisappear >= maxRetries()) {
                            m.cooldownUntil = Date.now() + cooldownSec() * 1000;
                            log(`${p} on cooldown for ${cooldownSec()}s (${maxRetries()} disappearances).`, 'warn');
                        }
                        reverted = true;
                    }
                }
            }

            if (reverted) { syncing = false; return; }

            // ---------- DECIDE ACTION ----------
            // Priority 1: A at top -> maintain A, then check B
            if (A.visible && A.rank === 1) {
                setBadge(0, 'top');
                $('engineStatus').innerHTML = `A holding #1 at ${aPrice}. Monitoring.`;

                // If B is not visible and not on cooldown, try to place B competitively
                if (!B.visible && !inCooldown(B)) {
                    const bTarget = clampPrice(isSell() ? topPrice - step() : topPrice + step());
                    if (bTarget !== bPrice) {
                        log(`A at #1. Placing B at competitive ${bTarget} (top ${topPrice}).`, 'info');
                        await pushPrice(B, bTarget, 'B');
                        B.adjustedLastTick = true;
                        setBadge(1, 'push');
                    }
                } else if (B.visible) {
                    setBadge(1, B.rank === 1 ? 'top' : 'push');
                }
                syncing = false;
                return;
            }

            // Priority 2: A visible but not #1 -> push A toward top
            if (A.visible && A.rank > 1 && !inCooldown(A)) {
                const aTarget = competePrice(aPrice);
                if (aTarget === aPrice) {
                    // Hit min/max boundary
                    log(`A at rank #${A.rank}, can't go further (${aPrice} vs top ${topPrice}). Bound reached.`, 'warn');
                    setBadge(0, 'gone');
                } else {
                    log(`A at rank #${A.rank} (${aPrice}). Pushing to ${aTarget} (top ${topPrice}).`, 'info');
                    await pushPrice(A, aTarget, 'A');
                    A.adjustedLastTick = true;
                    setBadge(0, 'push');
                }
                // Still check B opportunistically
                if (!B.visible && !inCooldown(B)) {
                    const bTarget = clampPrice(isSell() ? topPrice - step() : topPrice + step());
                    if (bTarget !== bPrice) {
                        log(`Also placing B at ${bTarget}.`, 'info');
                        await pushPrice(B, bTarget, 'B');
                        B.adjustedLastTick = true;
                        setBadge(1, 'push');
                    }
                }
                syncing = false;
                return;
            }

            // Priority 3: A not visible (and not just reverted) -> try to place A
            if (!A.visible && !inCooldown(A)) {
                // Place A at a competitive price relative to current top
                const aTarget = clampPrice(isSell() ? topPrice - step() : topPrice + step());
                if (aTarget !== aPrice) {
                    log(`A not in top list. Placing A at ${aTarget} (top ${topPrice}).`, 'info');
                    await pushPrice(A, aTarget, 'A');
                    A.adjustedLastTick = true;
                    setBadge(0, 'push');
                    $('engineStatus').innerHTML = `Placing A at ${aTarget}, awaiting market verification.`;
                }
                syncing = false;
                return;
            }

            // Priority 4: A on cooldown -> optimise B instead
            if (inCooldown(A)) {
                $('engineStatus').innerHTML = 'A on cooldown. Optimising B.';
                setBadge(0, 'gone');
                log('A on cooldown, switching focus to B.', 'swap');

                if (B.visible && B.rank === 1) {
                    setBadge(1, 'top');
                    $('engineStatus').innerHTML = `B holding #1 at ${bPrice}. A on cooldown.`;
                } else if (B.visible && B.rank > 1 && !inCooldown(B)) {
                    const bTarget = competePrice(bPrice);
                    if (bTarget !== bPrice) {
                        log(`B at rank #${B.rank} (${bPrice}). Pushing to ${bTarget}.`, 'info');
                        await pushPrice(B, bTarget, 'B');
                        B.adjustedLastTick = true;
                        setBadge(1, 'push');
                    }
                } else if (!B.visible && !inCooldown(B)) {
                    const bTarget = clampPrice(isSell() ? topPrice - step() : topPrice + step());
                    if (bTarget !== bPrice) {
                        log(`B not in top. Placing B at ${bTarget}.`, 'info');
                        await pushPrice(B, bTarget, 'B');
                        B.adjustedLastTick = true;
                        setBadge(1, 'push');
                    }
                }
                syncing = false;
                return;
            }

            // Priority 5: Both on cooldown or neither can move
            if (inCooldown(A) && inCooldown(B)) {
                $('engineStatus').innerHTML = 'Both merchants on cooldown. Waiting.';
                log('Both A and B on cooldown. Pausing adjustments.', 'warn');
                setBadge(0, 'gone');
                setBadge(1, 'gone');
            }

        } catch (e) {
            console.error('Engine error:', e);
            log(`Engine error: ${e.message}`, 'error');
            $('engineStatus').innerHTML = 'Sync error (retrying...)';
        } finally {
            syncing = false;
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

        // Reset merchant states
        MERCHANTS.forEach(m => {
            m.lastGoodPrice = null;
            m.visible = false;
            m.rank = null;
            m.consecutiveDisappear = 0;
            m.cooldownUntil = 0;
            m.adjustedLastTick = false;
            m.lastTriedPrice = null;
        });

        running = true;
        tickCount = 0;
        $('logBox').innerHTML = '';
        log('Engine started. Discovering market price...', 'ok');
        $('startBtn').disabled = true;
        $('stopBtn').disabled = false;
        $('liveBadge').classList.remove('d-none');
        $('engineStatus').innerHTML = 'Engine started.';
        toast('Adaptive engine started.', 'info');

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
