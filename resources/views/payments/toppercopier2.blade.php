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
    .badge-paused { background-color: #d29922; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .ctrl-input { max-width: 160px; }
    .merchant-head { font-weight: 700; }
    .merchant-card { height: 100%; }
    .use-price-btn { padding: 2px 8px; font-size: 12px; border-radius: 6px; }
</style>

<div class="container-fluid py-4">

    <!-- ============ CONTROL BAR ============ -->
    <div class="card ads-card mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="bi bi-cpu me-1"></i> Price Copier Engine</h5>
            <div class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="mb-1 small fw-bold">Set Price</label>
                    <input type="number" step="0.01" min="0" id="setPrice" class="form-control ctrl-input" placeholder="price to push">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Margin (figure)</label>
                    <input type="number" step="0.01" min="0" id="marginAmt" class="form-control ctrl-input" value="10">
                </div>
                <div>
                    <label class="mb-1 small fw-bold">Interval (ms)</label>
                    <input type="number" step="100" min="500" id="intervalMs" class="form-control ctrl-input" value="2000">
                </div>
                <button id="applyAllBtn" class="btn btn-success fw-bold"><i class="bi bi-lightning-charge"></i> Apply to All</button>
                <button id="startBtn" class="btn btn-primary fw-bold"><i class="bi bi-play-fill"></i> Start</button>
                <button id="pauseBtn" class="btn btn-warning fw-bold" disabled><i class="bi bi-pause-fill"></i> Pause</button>
            </div>
            <p class="mt-3 mb-0 text-muted small">
                Set a base price and a margin figure. Each selected merchant gets a price spaced by
                that margin, forming an ordered ladder (e.g. base 100, margin 10 &rarr; 100, 110, 120, ...).
                Or click "Use Price" on any competitor below to copy their price as the base.
            </p>
        </div>
    </div>

    <!-- ============ MERCHANT CARDS ============ -->
    <div class="row g-4" id="merchantRow">
        {{-- Merchant cards are rendered by JS below --}}
    </div>

    <!-- ============ LIVE TOP LIST ============ -->
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
                                    <th>Ours?</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="topListBody">
                                <tr><td colspan="6" class="text-center text-muted">Select at least one user &amp; ad to load the market.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 mb-0"><b>Engine:</b> <span id="engineStatus" class="fw-bold text-secondary">Idle</span></p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================================
     |  USER CREDENTIAL MAP — each account has its own unique api_url
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

    const MERCHANT_COUNT = 5;
    const LABELS = ['A', 'B', 'C', 'D', 'E'];
    const COLORS = ['primary', 'success', 'warning', 'info', 'danger'];

    /* =====================================================================
     |  MERCHANT STATE
     ===================================================================== */
    function newMerchant() {
        return {
            url: '', key: '', secret: '',
            ads: [], ad: null, updating: false,
            lastVisiblePrice: null,
            visible: false,
            rank: null,
        };
    }
    const MERCHANTS = [];
    for (let i = 0; i < MERCHANT_COUNT; i++) MERCHANTS.push(newMerchant());

    /* =====================================================================
     |  ENGINE STATE
     ===================================================================== */
    let running = false;
    let syncing = false;
    let loopId = null;

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
     |  CONFIG
     ===================================================================== */
    function interval()   { return parseInt($('intervalMs').value) || 2000; }
    function marginAmt()  { return parseFloat($('marginAmt').value) || 0; }

    // The margin sent to the market analysis endpoint. With a fixed figure
    // margin, convert to an approximate percentage of the base price so the
    // endpoint still returns a useful competitor window.
    function marginPctFor(basePrice) {
        const amt = marginAmt();
        if (!amt || !basePrice || basePrice <= 0) return 4;
        return Math.max(1, (amt / basePrice) * 100);
    }

    /* =====================================================================
     |  HELPERS
     ===================================================================== */
    function prefix(i) { return LABELS[i]; }

    function activeMerchants() {
        return MERCHANTS.filter(m => m.ad);
    }

    /* =====================================================================
     |  RENDER MERCHANT CARDS
     ===================================================================== */
    function buildMerchantCards() {
        const row = $('merchantRow');
        row.innerHTML = '';
        for (let i = 0; i < MERCHANT_COUNT; i++) {
            const p = LABELS[i];
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 col-xxl';
            col.innerHTML = `
                <div class="card ads-card merchant-card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                            <span class="merchant-head"><i class="bi bi-person-badge me-1 text-${COLORS[i]}"></i> Merchant ${p}</span>
                            <span id="badge${p}" class="badge-gone">Idle</span>
                        </h5>

                        <select class="form-select form-select-lg mb-2" id="userSelect${p}">
                            <option value="">Select User (Merchant ${p})...</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>

                        <select class="form-select form-select-lg mb-3" id="adsSelect${p}" disabled>
                            <option value="">Select user first...</option>
                        </select>
                        <input type="hidden" id="adId${p}">

                        <div class="border rounded p-3 bg-light">
                            <p class="mb-2"><b>Pair:</b> <span id="pairText${p}">---</span></p>
                            <p class="mb-2"><b>Current Price:</b> <span id="currentPrice${p}" class="fw-bold text-${COLORS[i]}">---</span></p>
                            <p class="mb-2"><b>Last Visible Price:</b> <span id="lastGood${p}" class="fw-bold">---</span></p>
                            <p class="mb-2"><b>Rank:</b> <span id="rank${p}">---</span></p>
                            <p class="mb-2"><b>Min/Max Limit:</b> <span id="limitText${p}">---</span></p>
                            <p class="mb-0"><b>Status:</b> <span id="statusText${p}">---</span></p>
                        </div>
                    </div>
                </div>
            `;
            row.appendChild(col);
        }
    }
    buildMerchantCards();

    function setBadge(i, state) {
        const el = $('badge' + prefix(i));
        if (!el) return;
        el.className = 'badge-' + state;
        const labels = { top: '#1 Top', gone: 'Gone', push: 'Pushing', probe: 'Probing', lock: 'LOCKED', idle: 'Idle', paused: 'Paused' };
        el.textContent = labels[state] || state;
    }

    /* =====================================================================
     |  RENDER AD INFO
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
        const rankEl = $('rank'+p);
        const lastEl = $('lastGood'+p);
        if (rankEl) rankEl.innerHTML = m.visible ? `#${m.rank}` : 'Not in top';
        if (lastEl) lastEl.innerHTML = m.lastVisiblePrice ?? '---';
    }

    /* =====================================================================
     |  LOAD ADS — uses each account's unique api_url
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
     |  BINDINGS — user + ad selects for each merchant
     ===================================================================== */
    for (let i = 0; i < MERCHANT_COUNT; i++) {
        const p = LABELS[i];
        const m = MERCHANTS[i];
        const userSel = $('userSelect' + p);
        const adsSel = $('adsSelect' + p);

        ((merchant, userSelect, adsSelect, label) => {
            userSelect.addEventListener('change', function () {
                const creds = USER_MAP[this.value];
                if (!creds) {
                    merchant.url = merchant.key = merchant.secret = '';
                    merchant.ad = null; merchant.ads = [];
                    adsSelect.disabled = true;
                    adsSelect.innerHTML = `<option value="">Select user first...</option>`;
                    renderAd(merchant, label);
                    return;
                }
                merchant.url = creds.url; merchant.key = creds.key; merchant.secret = creds.secret;
                adsSelect.disabled = false;
                loadAds(merchant, adsSelect, label);
            });

            adsSelect.addEventListener('change', function () {
                merchant.ad = merchant.ads.find(x => String(x.id) === String(this.value)) || null;
                renderAd(merchant, label);
                // Auto-load the market top list as soon as an ad is picked
                if (merchant.ad && !running) loadMarketOnce();
            });
        })(m, userSel, adsSel, p);
    }

    /* =====================================================================
     |  PUSH PRICE UPDATE — to a single merchant via its own api_url
     ===================================================================== */
    async function pushPrice(m, newPrice, p) {
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
            toast(`${p} update rejected: ${result?.ret_msg || result?.error || 'unknown'}`, 'error');
            return false;
        } catch (e) {
            toast(`${p} network error: ${e.message}`, 'error');
            return false;
        } finally {
            m.updating = false;
        }
    }

    /* =====================================================================
     |  APPLY AN ORDERED PRICE LADDER TO ALL SELECTED MERCHANTS
     |
     |  The first selected merchant gets the base price; each subsequent
     |  merchant gets base + (margin * position), producing an ordered list
     |  where every account is spaced by the margin figure.
     ===================================================================== */
    async function applyPriceToAll(basePrice, useMargin = true) {
        if (isNaN(basePrice) || basePrice <= 0) { toast('Enter a valid price.', 'error'); return; }
        const ready = activeMerchants();
        if (ready.length === 0) { toast('Select at least one merchant ad.', 'error'); return; }

        const amt = useMargin ? marginAmt() : 0;
        const label = useMargin ? `ordered ladder (base ${basePrice}, margin ${amt})` : `exact price ${basePrice} (no margin)`;
        toast(`Pushing ${label} to ${ready.length} merchant(s)...`, 'info');
        let ok = 0, skipped = 0;
        let pos = 0;
        for (let i = 0; i < MERCHANT_COUNT; i++) {
            const m = MERCHANTS[i];
            const p = prefix(i);
            if (!m.ad) continue;
            const targetPrice = basePrice + (amt * pos);
            pos++;
            const current = parseFloat(m.ad.price);
            if (!isNaN(current) && current === targetPrice) {
                skipped++;
                continue;
            }
            setBadge(i, 'push');
            const success = await pushPrice(m, targetPrice, p);
            if (success) ok++;
            setBadge(i, success ? 'probe' : 'gone');
        }
        const msg = skipped > 0
            ? `Price applied to ${ok} merchant(s), ${skipped} already at that price (skipped).`
            : `Price applied to ${ok} merchant(s).`;
        toast(msg, ok > 0 || skipped > 0 ? 'success' : 'error');
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
     |  RENDER TOP LIST — each row has a "Use This Price" button
     ===================================================================== */
    function renderTopList(competitors) {
        const body = $('topListBody');
        if (!competitors || competitors.length === 0) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No competitors.</td></tr>`;
            return;
        }
        body.innerHTML = competitors.map((c, i) => {
            const tags = MERCHANTS.map((m, mi) => {
                if (m.visible && m.rank === (i + 1)) {
                    return `<span class="badge-top" style="margin-left:2px">${prefix(mi)}</span>`;
                }
                return '';
            }).join('');
            const price = parseFloat(c.price);
            return `<tr>
                <td>${i+1}</td>
                <td>${c.nickName}</td>
                <td><b>${c.price}</b></td>
                <td>${c.balance ?? '---'}</td>
                <td>${tags}</td>
                <td>
                    <button class="btn btn-outline-success use-price-btn"
                        onclick="useCompetitorPrice(${price})"
                        ${isNaN(price) ? 'disabled' : ''}>
                        <i class="bi bi-copy"></i> Use Price
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    // Exposed for inline onclick
    window.useCompetitorPrice = function (price) {
        if (isNaN(price)) { toast('Invalid competitor price.', 'error'); return; }
        $('setPrice').value = price;
        applyPriceToAll(price, false);
    };

    /* =====================================================================
     |  FETCH MARKET TOP LIST — shared by one-shot load and live loop
     ===================================================================== */
    async function fetchMarket() {
        const reader = MERCHANTS.find(m => m.ad);
        if (!reader) return null;

        const res = await fetch(`${reader.url}/analyze-market`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                api_key: reader.key,
                api_secret: reader.secret,
                tokenId: reader.ad.tokenId,
                currencyId: reader.ad.currencyId,
                side: '0',
                marginPct: marginPctFor(parseFloat(reader.ad.price))
            })
        });
        const data = await res.json();

        if (!data || !data.status || !data.top_10_competitors || data.top_10_competitors.length === 0) {
            return null;
        }

        const competitors = data.top_10_competitors;

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

        return { competitors, topPrice: parseFloat(competitors[0].price) };
    }

    /* =====================================================================
     |  ONE-SHOT MARKET LOAD (when an ad is selected, before going live)
     ===================================================================== */
    let marketLoadingOnce = false;
    async function loadMarketOnce() {
        if (marketLoadingOnce) return;
        marketLoadingOnce = true;
        $('engineStatus').innerHTML = 'Loading market...';
        try {
            const obs = await fetchMarket();
            if (!obs) {
                $('engineStatus').innerHTML = 'No competitors found.';
                $('topListBody').innerHTML = `<tr><td colspan="6" class="text-center text-muted">No competitors found.</td></tr>`;
            } else {
                $('engineStatus').innerHTML = `Market loaded — #1 price: ${obs.topPrice}`;
            }
        } catch (e) {
            console.error('Market load error:', e);
            $('engineStatus').innerHTML = 'Market load error.';
        } finally {
            marketLoadingOnce = false;
        }
    }

    /* =====================================================================
     |  LIVE LOOP
     ===================================================================== */
    async function tick() {
        if (!running) return;
        if (syncing) return;
        syncing = true;
        try {
            const obs = await fetchMarket();
            if (!obs) {
                $('engineStatus').innerHTML = 'No competitors found.';
            } else {
                $('engineStatus').innerHTML = `Live — #1 price: ${obs.topPrice}`;
            }
        } catch (e) {
            console.error('Live error:', e);
            $('engineStatus').innerHTML = 'Sync error (retrying...)';
        } finally {
            syncing = false;
        }
    }

    /* =====================================================================
     |  START / PAUSE
     |
     |  Start  — begins (or resumes) the live loop and market requests
     |  Pause  — immediately stops ALL requests; the loop halts and no
     |            fetch is in-flight or scheduled. Press Start to resume.
     ===================================================================== */
    function startLive() {
        const ready = activeMerchants();
        if (ready.length === 0) {
            toast('Select at least one merchant ad first.', 'error');
            return;
        }

        // Ensure all selected merchants use the same pair
        const first = ready[0].ad;
        for (let i = 0; i < MERCHANT_COUNT; i++) {
            const m = MERCHANTS[i];
            if (!m.ad) continue;
            if (m.ad.tokenId !== first.tokenId || m.ad.currencyId !== first.currencyId) {
                toast('All selected merchants must use the SAME pair.', 'error');
                return;
            }
        }

        running = true;
        MERCHANTS.forEach((m, i) => { if (m.ad) setBadge(i, 'probe'); });
        $('startBtn').disabled = true;
        $('pauseBtn').disabled = false;
        $('liveBadge').classList.remove('d-none');
        $('engineStatus').innerHTML = 'Live engine started.';
        toast('Live engine started.', 'info');

        tick();
        loopId = setInterval(tick, interval());
    }

    function pauseLive() {
        // Stop everything — no more requests will be sent
        running = false;
        if (loopId) { clearInterval(loopId); loopId = null; }
        $('startBtn').disabled = false;
        $('pauseBtn').disabled = true;
        $('liveBadge').classList.add('d-none');
        MERCHANTS.forEach((m, i) => { if (m.ad) setBadge(i, 'paused'); });
        $('engineStatus').innerHTML = 'Paused — all requests stopped.';
        toast('Paused. All requests stopped.', 'info');
    }

    /* =====================================================================
     |  BUTTONS
     ===================================================================== */
    $('applyAllBtn').addEventListener('click', () => {
        const price = parseFloat($('setPrice').value);
        applyPriceToAll(price);
    });

    $('startBtn').addEventListener('click', startLive);
    $('pauseBtn').addEventListener('click', pauseLive);

});
</script>
