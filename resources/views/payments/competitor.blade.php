@extends('layouts.app')

@section('content')

<div id="toast" class="app-toast"></div>

<style>
    .app-toast{
        position:fixed;
        right:30px;
        bottom:30px;
        z-index:9999;
        padding:15px 20px;
        border-radius:10px;
        color:#fff;
        opacity:0;
        transition:.3s;
        background:#111;
    }

    .app-toast.show{
        opacity:1;
    }

    .app-toast.success{
        background:#E37216;
    }

    .app-toast.error{
        background:#dc3545;
    }

    .ads-card{
        border:none;
        border-radius:18px;
        box-shadow:0 5px 20px rgba(0,0,0,.08);
    }

    .price-box{
        font-size:30px;
        font-weight:bold;
        color:#E37216;
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

                    <select
                        class="form-select form-select-lg mb-4"
                        id="adsSelect">

                        <option>
                            Loading...
                        </option>

                    </select>

                    <input type="hidden" id="adId">

                    <div class="border rounded p-3">

                        <p>
                            <b>Pair:</b>
                            <span id="pairText">---</span>
                        </p>

                        <p>
                            <b>Current Price:</b>
                            <span id="currentPrice">---</span>
                        </p>

                        <p>
                            <b>Min:</b>
                            <span id="minText">---</span>
                        </p>

                        <p>
                            <b>Max:</b>
                            <span id="maxText">---</span>
                        </p>

                        <p>
                            <b>Status:</b>
                            <span id="statusText">
                                ---
                            </span>
                        </p>

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

                    <select
                        class="form-select form-select-lg mb-4"
                        id="merchantSelect">

                        <option value="">
                            Loading competitors...
                        </option>

                    </select>

                    <div class="border rounded p-3">

                        <p>
                            <b>Merchant:</b>
                            <span id="merchantName">
                                ---
                            </span>
                        </p>

                        <p>
                            <b>Merchant Price:</b>
                            <span id="merchantPrice">
                                ---
                            </span>
                        </p>

                        <p>
                            <b>Tracking:</b>
                            <span id="trackingStatus">
                                Stopped
                            </span>
                        </p>

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

        let adsData = [];
        let competitors = [];

        // Special TOPPER-BTC Merchant Object
        let topperMerchant = {
            id: 'TOPPER-BTC',
            nickName: 'TOPPER-BTC',
            price: 'Loading...'
        };

        let selectedMerchantId = null;
        let selectedMerchantName = null;

        let referencePrice = null;
        let lastMerchantPrice = null;

        let selectedToken = null;
        let selectedCurrency = null;

        let tracking = false;
        let paused = false;
        let updatingAd = false;

        /*
        |--------------------------------------------------------------------------
        | Toast
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
        | Load Advertisements
        |--------------------------------------------------------------------------
        */
        async function loadAds() {

            try {
                const res = await fetch(
                    `${API_URL}/ads`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            api_key: API_KEY,
                            api_secret: API_SECRET
                        })
                    }
                );

                const data = await res.json();
                adsData = data?.result?.items || [];

                adsSelect.innerHTML = `
                    <option value="">
                        Select Advertisement
                    </option>
                `;

                adsData.forEach(ad => {
                    adsSelect.innerHTML += `
                        <option value="${ad.id}">
                            ${ad.tokenId}/${ad.currencyId}
                            | ${ad.price}
                        </option>
                    `;
                });

            } catch (e) {
                console.log(e);
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

                merchantSelect.innerHTML = `
                    <option value="">
                        Select Advertisement First
                    </option>
                `;
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

            /*
            |--------------------------------------------------------------------------
            | Reset Tracking
            |--------------------------------------------------------------------------
            */
            selectedMerchantId = null;
            selectedMerchantName = null;
            referencePrice = null;
            lastMerchantPrice = null;

            tracking = false;
            paused = false;

            document.getElementById('merchantName').innerHTML = '---';
            document.getElementById('merchantPrice').innerHTML = '---';
            document.getElementById('trackingStatus').innerHTML = 'Stopped';

            merchantSelect.innerHTML = `
                <option value="">
                    Loading competitors...
                </option>
            `;

            // Trigger immediate fetches
            fetchTopperPrice();
            await fetchCompetitors();
        });

        /*
        |--------------------------------------------------------------------------
        | Fetch TOPPER-BTC Price (1-Second API Check)
        |--------------------------------------------------------------------------
        */
        /*
        |--------------------------------------------------------------------------
        | Fetch TOPPER-BTC Price (1-Second API Check)
        |--------------------------------------------------------------------------
        */
        async function fetchTopperPrice() {
            if (!selectedToken || !selectedCurrency) return;

            const adId = document.getElementById('adId').value;
            if (!adId) return;

            // Find the full ad object from the loaded data
            const ad = adsData.find(x => String(x.id) === String(adId));
            if (!ad) return;

            try {
                // Construct the full payload exactly as requested
                const payload = {
                    api_key: API_KEY,
                    api_secret: API_SECRET,
                    id: adId,
                    price: "2",
                    priceType: 0,
                    premium: 0,
                    minAmount: "10.000",
                    maxAmount: "100000.000",
                    lastQuantity: "1.99985746",
                    paymentPeriod: 30,
                    paymentTerms: [
                        { "id": "16736255" },
                        { "id": "16736274" },
                        { "id": "5074256" }
                    ],
                    tradingPreferenceSet: {
                        hasUnPostAd: 0,
                        isKyc: 0,
                        isEmail: 0,
                        isMobile: 0,
                        hasRegisterTime: 0,
                        registerTimeThreshold: 0,
                        orderFinishNumberDay30: 0,
                        hasOrderFinishNumberDay30: 0,
                        hasCompleteRateDay30: 0,
                        hasNationalLimit: 0,
                        completeRateDay30: "",
                        nationalLimit: ""
                    }
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

                    // Update the price internally in the competitors array if it exists
                    const compIndex = competitors.findIndex(x => x.id === 'TOPPER-BTC');
                    if (compIndex > -1) {
                        competitors[compIndex].price = topperMerchant.price;
                    }

                    // Update UI Option directly without causing a full select re-render
                    const topperOption = document.querySelector('option[value="TOPPER-BTC"]');
                    if (topperOption) {
                        topperOption.dataset.price = topperMerchant.price;
                        topperOption.innerHTML = `⭐ | TOPPER-BTC | ${topperMerchant.price}`;
                    }

                    // If actively tracking TOPPER-BTC, trigger the ratchet check
                    if (tracking && selectedMerchantId === 'TOPPER-BTC') {
                        trackMerchant();
                    }
                }
            } catch (e) {
                console.log("Failed to fetch Topper limit:", e);
            }
        }

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
                    headers: {
                        'Content-Type': 'application/json'
                    },
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

                // Inject TOPPER-BTC at the very top of the list
                competitors.unshift({ ...topperMerchant });

                renderCompetitors();

                if (tracking && selectedMerchantId) {
                    await trackMerchant();
                }

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

            merchantSelect.innerHTML = `
                <option value="">
                    Select Merchant
                </option>
            `;

            competitors.forEach((merchant, index) => {
                // If it's TOPPER-BTC, give it a star, otherwise use standard numbering
                const displayPrefix = merchant.id === 'TOPPER-BTC' ? '⭐' : `#${index}`;

                merchantSelect.innerHTML += `
                    <option
                        value="${merchant.id}"
                        data-price="${merchant.price}"
                        data-name="${merchant.nickName}"
                        ${selected == merchant.id ? 'selected' : ''}>
                        ${displayPrefix} | ${merchant.nickName} | ${merchant.price}
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

            referencePrice = parseFloat(option.dataset.price) || option.dataset.price;
            lastMerchantPrice = referencePrice;

            tracking = true;
            paused = false;

            document.getElementById('merchantName').innerHTML = selectedMerchantName;
            document.getElementById('merchantPrice').innerHTML = referencePrice;
            document.getElementById('trackingStatus').innerHTML = 'Tracking';

            // Don't send local DB tracking stats for the system's own TOPPER-BTC limit
            if (selectedMerchantId !== 'TOPPER-BTC') {
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
            }

            // Only update immediately if it's not "Loading..."
            if (!isNaN(referencePrice)) {
                await updateAdPrice(referencePrice);
            }

            toast(`Tracking ${selectedMerchantName}`);
        });

        /*
        |--------------------------------------------------------------------------
        | Track Merchant
        |--------------------------------------------------------------------------
        */
        async function trackMerchant() {

            const merchant = competitors.find(x => String(x.id) === String(selectedMerchantId));

            if (!merchant) {
                document.getElementById('trackingStatus').innerHTML = 'Merchant not in Top 10';
                return;
            }

            const currentPrice = parseFloat(merchant.price);
            if (isNaN(currentPrice)) return;

            document.getElementById('merchantPrice').innerHTML = currentPrice;

            // Behavior for our injected TOPPER-BTC limit
            if (selectedMerchantId === 'TOPPER-BTC') {
                
                // Halt if the price goes down or stays the same (Only track UP)
                if (currentPrice <= lastMerchantPrice) {
                    document.getElementById('trackingStatus').innerHTML = `At API Limit (${lastMerchantPrice})`;
                    return; 
                }
                
                document.getElementById('trackingStatus').innerHTML = 'Syncing to Limit (Upwards)';
            
            // Behavior for standard merchants
            } else {
                
                // Halt ONLY if the price hasn't changed at all (Tracks up and down)
                if (currentPrice === lastMerchantPrice) {
                    document.getElementById('trackingStatus').innerHTML = `Matching Market (${lastMerchantPrice})`;
                    return; 
                }
                
                // Update status text based on movement direction
                if (currentPrice > lastMerchantPrice) {
                    document.getElementById('trackingStatus').innerHTML = 'Tracking Upwards';
                } else {
                    document.getElementById('trackingStatus').innerHTML = 'Tracking Downwards';
                }
            }

            // Update our internal tracker to the new price
            lastMerchantPrice = currentPrice;
            await updateAdPrice(currentPrice);

            // Send standard merchant stat updates
            if (selectedMerchantId !== 'TOPPER-BTC') {
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
                } catch (e) {
                    console.log(e);
                }
            }
        }
        

        /*
        |--------------------------------------------------------------------------
        | Update Advertisement (With 1-Second Auto-Retry)
        |--------------------------------------------------------------------------
        */
        let targetAdPrice = null;

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
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await res.json();

                    if (res.ok && !result.error) {
                        
                        ad.price = priceToUpdate;
                        document.getElementById('currentPrice').innerHTML = priceToUpdate;
                        toast(`Ad updated to ${priceToUpdate}`);
                        
                        if (targetAdPrice === priceToUpdate) break; 

                    } else {
                        console.log('Backend Error:', result);
                        toast('Retrying in 1 second...', 'error');
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }

                } catch (e) {
                    console.log('Network Error:', e);
                    toast('Network error. Retrying in 1 second...', 'error');
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
            }

            updatingAd = false;
        }

        /*
        |--------------------------------------------------------------------------
        | Polling
        |--------------------------------------------------------------------------
        */
        
        // 1. Fetch the TOPPER Limit Price every 1 second
        setInterval(fetchTopperPrice, 1000);

        // 2. Fetch the Standard Market Competitors every 3 seconds
        setInterval(() => {
            if (selectedToken && selectedCurrency) {
                fetchCompetitors();
            }
        }, 1000);

    });
</script>