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

    let selectedMerchantId = null;
    let lastMerchantPrice = null;
    let referencePrice = null;

    let tracking = false;
    let updatingAd = false;

    let selectedToken = null;
    let selectedCurrency = null;

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
    | Load Ads
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
    | Select Advertisement
    |--------------------------------------------------------------------------
    */
    adsSelect.addEventListener('change', async function () {

        const ad = adsData.find(
            x => String(x.id) === String(this.value)
        );

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

        document.getElementById('pairText').innerHTML =
            `${ad.tokenId}/${ad.currencyId}`;

        document.getElementById('currentPrice').innerHTML =
            ad.price;

        document.getElementById('minText').innerHTML =
            ad.minAmount;

        document.getElementById('maxText').innerHTML =
            ad.maxAmount;

        document.getElementById('statusText').innerHTML =
            ad.status ?? '---';

        // Reset tracking state
        selectedMerchantId = null;
        lastMerchantPrice = null;
        referencePrice = null;
        tracking = false;

        document.getElementById(
            'merchantName'
        ).innerHTML = '---';

        document.getElementById(
            'merchantPrice'
        ).innerHTML = '---';

        document.getElementById(
            'trackingStatus'
        ).innerHTML = 'Stopped';

        merchantSelect.innerHTML = `
            <option value="">
                Loading competitors...
            </option>
        `;

        await fetchCompetitors();
    });

    /*
    |--------------------------------------------------------------------------
    | Fetch Competitors
    |--------------------------------------------------------------------------
    */
    async function fetchCompetitors() {

        if (!selectedToken || !selectedCurrency) {
            return;
        }

        try {

            const res = await fetch(
                `${API_URL}/analyze-market`,
                {
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
                }
            );

            const data = await res.json();

            if (!data.status) {
                return;
            }

            competitors =
                data.top_10_competitors || [];

            renderCompetitors();

            if (
                tracking &&
                selectedMerchantId
            ) {
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

            merchantSelect.innerHTML += `
                <option
                    value="${merchant.id}"
                    data-price="${merchant.price}"
                    data-name="${merchant.nickName}"
                    ${selected == merchant.id ? 'selected' : ''}>
                    #${index + 1}
                    |
                    ${merchant.nickName}
                    |
                    ${merchant.price}
                </option>
            `;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Merchant Selected
    |--------------------------------------------------------------------------
    */
    merchantSelect.addEventListener(
        'change',
        async function () {

            const option =
                this.options[this.selectedIndex];

            if (!option.value) {
                return;
            }

            selectedMerchantId =
                option.value;

            referencePrice =
                parseFloat(option.dataset.price);

            lastMerchantPrice =
                referencePrice;

            tracking = true;

            document.getElementById(
                'merchantName'
            ).innerHTML =
                option.dataset.name;

            document.getElementById(
                'merchantPrice'
            ).innerHTML =
                referencePrice;

            document.getElementById(
                'trackingStatus'
            ).innerHTML =
                'Tracking';

            await fetch(
                "{{ route('dashboard.com.store') }}",
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'X-CSRF-TOKEN':
                            '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        merchant_id:
                            selectedMerchantId,
                        username:
                            option.dataset.name,
                        price:
                            referencePrice
                    })
                }
            );

            await updateAdPrice(
                referencePrice
            );

            toast('Merchant selected.');
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Track Merchant
    |--------------------------------------------------------------------------
    */
    async function trackMerchant() {

        const merchant =
            competitors.find(
                x =>
                String(x.id) ===
                String(selectedMerchantId)
            );

        if (!merchant) {
            return;
        }

        const currentPrice =
            parseFloat(merchant.price);

        document.getElementById(
            'merchantPrice'
        ).innerHTML =
            currentPrice;

        /*
        |--------------------------------------------------------------------------
        | Market is below reference price
        |--------------------------------------------------------------------------
        */
        if (
            currentPrice <
            referencePrice
        ) {

            document.getElementById(
                'trackingStatus'
            ).innerHTML =
                'Waiting for recovery';

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Market recovered
        |--------------------------------------------------------------------------
        */
        document.getElementById(
            'trackingStatus'
        ).innerHTML =
            'Tracking';

        /*
        |--------------------------------------------------------------------------
        | No change
        |--------------------------------------------------------------------------
        */
        if (
            currentPrice ===
            lastMerchantPrice
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Price increased
        |--------------------------------------------------------------------------
        */
        lastMerchantPrice =
            currentPrice;

        await updateAdPrice(
            currentPrice
        );

        await fetch(
            "{{ route('dashboard.com.store') }}",
            {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/json',
                    'X-CSRF-TOKEN':
                        '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    merchant_id:
                        merchant.id,
                    username:
                        merchant.nickName,
                    price:
                        currentPrice
                })
            }
        );

        toast(
            `Market updated to ${currentPrice}`
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Advertisement
    |--------------------------------------------------------------------------
    */
    async function updateAdPrice(
        newPrice
    ) {

        if (updatingAd) {
            return;
        }

        updatingAd = true;

        const ad =
            adsData.find(
                x =>
                String(x.id) ===
                String(
                    document.getElementById(
                        'adId'
                    ).value
                )
            );

        if (!ad) {

            updatingAd = false;

            toast(
                'Please select your Ad first.',
                'error'
            );

            return;
        }

        const payload = {
            ...ad,
            price: newPrice,
            api_key: API_KEY,
            api_secret: API_SECRET
        };

        try {

            const res =
                await fetch(
                    `${API_URL}/update-ad`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type':
                                'application/json'
                        },
                        body: JSON.stringify(
                            payload
                        )
                    }
                );

            const result =
                await res.json();

            if (
                res.ok &&
                !result.error
            ) {

                ad.price =
                    newPrice;

                document.getElementById(
                    'currentPrice'
                ).innerHTML =
                    newPrice;

                toast(
                    `Ad updated to ${newPrice}`
                );
            }

        } catch (e) {
            console.log(e);

            toast(
                'Failed to update ad.',
                'error'
            );
        }

        updatingAd = false;
    }

    /*
    |--------------------------------------------------------------------------
    | Start Polling
    |--------------------------------------------------------------------------
    */
    setInterval(() => {

        if (
            selectedToken &&
            selectedCurrency
        ) {
            fetchCompetitors();
        }

    }, 2000);

});
</script>

