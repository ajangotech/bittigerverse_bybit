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



    .plus-panel{

        background-color:#fffdfa;

        border:2px solid #E37216;

        border-radius:10px;

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



                        <p class="mb-0">

                            <b>Time to Re-edit:</b>

                            <span id="plusCountdownText" class="text-danger fw-bold">---</span>

                        </p>



                    </div>



                    <!-- Competitor Plus Panel -->

                    <div class="p-3 mb-3 mt-3 plus-panel">

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



        /*

        |--------------------------------------------------------------------------

        | Timer Elements

        |--------------------------------------------------------------------------

        */

        const plusModeToggle = document.getElementById('plusModeToggle');

        const plusTimer = document.getElementById('plusTimer');

        const countdownEl = document.getElementById('plusCountdownText');



        let adsData = [];

        let competitors = [];



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

        | Timer State

        |--------------------------------------------------------------------------

        */

        let lastSuccessfulUpdateTime = Date.now();

        let plusFetching = false;



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

        | Timer Helper

        |--------------------------------------------------------------------------

        */

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

                toast(

                    'Failed to load advertisements.',

                    'error'

                );

            }

        }



        loadAds();



        /*

        |--------------------------------------------------------------------------

        | Advertisement Selected

        |--------------------------------------------------------------------------

        */

        adsSelect.addEventListener(

            'change',

            async function () {



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



                document.getElementById(

                    'adId'

                ).value = ad.id;



                document.getElementById(

                    'pairText'

                ).innerHTML =

                    `${ad.tokenId}/${ad.currencyId}`;

                document.getElementById(

                    'currentPrice'

                ).innerHTML =

                    ad.price;



                document.getElementById(

                    'minText'

                ).innerHTML =

                    ad.minAmount;



                document.getElementById(

                    'maxText'

                ).innerHTML =

                    ad.maxAmount;



                document.getElementById(

                    'statusText'

                ).innerHTML =

                    ad.status ?? '---';



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



                lastSuccessfulUpdateTime = Date.now();

                plusFetching = false;



                document.getElementById(

                    'merchantName'

                ).innerHTML = '---';



                document.getElementById(

                    'merchantPrice'

                ).innerHTML = '---';



                document.getElementById(

                    'trackingStatus'

                ).innerHTML = 'Stopped';



                countdownEl.innerHTML = '---';



                merchantSelect.innerHTML = `

                    <option value="">

                        Loading competitors...

                    </option>

                `;



                await fetchCompetitors();

            }

        );



        /*

        |--------------------------------------------------------------------------

        | Fetch Competitors

        |--------------------------------------------------------------------------

        */

        async function fetchCompetitors() {



            if (



                !selectedToken ||

                !selectedCurrency



            ) {

                return;

            }



            try {



                const res = await fetch(

                    `${API_URL}/analyze-market`,

                    {

                        method: 'POST',

                        headers: {

                            'Content-Type':

                                'application/json'

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



                const data =

                    await res.json();



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



            const selected =

                selectedMerchantId;



            merchantSelect.innerHTML = `

                <option value="">

                    Select Merchant

                </option>

            `;



            competitors.forEach(

                (merchant, index) => {



                    merchantSelect.innerHTML += `

                        <option

                            value="${merchant.id}"

                            data-price="${merchant.price}"

                            data-name="${merchant.nickName}"

                            ${selected == merchant.id

                                ? 'selected'

                                : ''}>

                            #${index + 1}

                            |

                            ${merchant.nickName}

                            |

                            ${merchant.price}

                        </option>

                    `;

                }

            );

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

                    this.options[

                        this.selectedIndex

                    ];



                if (!option.value) {

                    return;

                }



                selectedMerchantId =

                    option.value;



                selectedMerchantName =

                    option.dataset.name;



                referencePrice =

                    parseFloat(

                        option.dataset.price

                    );



                lastMerchantPrice =

                    referencePrice;



                tracking = true;

                paused = false;



                lastSuccessfulUpdateTime = Date.now();



                document.getElementById(

                    'merchantName'

                ).innerHTML =

                    selectedMerchantName;



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

                                selectedMerchantName,

                            price:

                                referencePrice

                        })

                    }

                );



                await updateAdPrice(

                    referencePrice

                );



                toast(

                    `Tracking ${selectedMerchantName}`

                );

            }

        );



        /*

        |--------------------------------------------------------------------------

        | Track Merchant (Ratchet / Up-Only Mode + Competitor Plus Timer)

        |--------------------------------------------------------------------------

        */

        async function trackMerchant() {



            const merchant = competitors.find(

                x => String(x.id) === String(selectedMerchantId)

            );



            /*

            |--------------------------------------------------------------------------

            | Merchant disappeared from top 10

            |--------------------------------------------------------------------------

            */

            if (!merchant) {

                document.getElementById('trackingStatus').innerHTML = 'Merchant not in Top 10';

                return;

            }



            const currentPrice = parseFloat(merchant.price);



            // Update the UI to show the competitor's actual live price

            document.getElementById('merchantPrice').innerHTML = currentPrice;



            /*

            |--------------------------------------------------------------------------

            | Competitor Plus Mode (Timer-based re-edit)

            |--------------------------------------------------------------------------

            | When Plus is enabled, the ad is re-synced to the competitor's current

            | price after the configured timer expires, even if the price has not

            | gone up. If the price goes up before the timer expires, it syncs

            | immediately and resets the timer.

            */

            if (plusModeToggle && plusModeToggle.checked) {



                if (plusFetching) {

                    return;

                }



                const plusTimerMs = activeTimerMs();

                const timeSinceLastUpdate = Date.now() - lastSuccessfulUpdateTime;



                /*

                |----------------------------------------------------------------------

                | New high reached — sync immediately and reset timer

                |----------------------------------------------------------------------

                */

                if (currentPrice > lastMerchantPrice) {

                    document.getElementById('trackingStatus').innerHTML =

                        '<span class="text-success fw-bold">Tracking Upwards</span>';



                    lastMerchantPrice = currentPrice;

                    lastSuccessfulUpdateTime = Date.now();



                    await updateAdPrice(currentPrice);



                    try {

                        await fetch(

                            "{{ route('dashboard.com.store') }}",

                            {

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

                            }

                        );

                    } catch (e) {

                        console.log(e);

                    }



                    return;

                }



                /*

                |----------------------------------------------------------------------

                | Timer expired — re-sync to competitor's current price

                |----------------------------------------------------------------------

                */

                if (timeSinceLastUpdate >= plusTimerMs) {

                    plusFetching = true;



                    document.getElementById('trackingStatus').innerHTML =

                        '<span class="text-warning fw-bold">Timer Expired! Re-syncing...</span>';



                    toast(

                        `Timer expired! Re-editing to ${selectedMerchantName}'s current price (${currentPrice})`,

                        'success'

                    );



                    try {

                        lastMerchantPrice = currentPrice;

                        lastSuccessfulUpdateTime = Date.now();



                        document.getElementById('trackingStatus').innerHTML =

                            '<span class="text-success fw-bold">Timer Resync</span>';



                        await updateAdPrice(currentPrice);



                        try {

                            await fetch(

                                "{{ route('dashboard.com.store') }}",

                                {

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

                                }

                            );

                        } catch (e) {

                            console.log(e);

                        }

                    } finally {

                        plusFetching = false;

                    }



                    return;

                }



                /*

                |----------------------------------------------------------------------

                | Holding position — timer still counting

                |----------------------------------------------------------------------

                */

                document.getElementById('trackingStatus').innerHTML =

                    `Maintaining High (${lastMerchantPrice})`;



                return;

            }



            /*

            |--------------------------------------------------------------------------

            | Standard Ratchet Check (NEVER GO DOWN)

            |--------------------------------------------------------------------------

            | If the competitor's price is LESS THAN OR EQUAL TO our last posted price,

            | we hit the brakes. We do not update. We hold our high position.

            */

            if (currentPrice <= lastMerchantPrice) {

                document.getElementById('trackingStatus').innerHTML = `Maintaining High (${lastMerchantPrice})`;

                return; // Script stops here. No API calls are made. Ad does not drop.

            }



            /*

            |--------------------------------------------------------------------------

            | New High Reached (currentPrice is > lastMerchantPrice)

            |--------------------------------------------------------------------------

            */

            document.getElementById('trackingStatus').innerHTML = 'Tracking Upwards';

            

            // Update our internal tracker to the new highest price

            lastMerchantPrice = currentPrice;



            // Implement the new higher price

            await updateAdPrice(currentPrice);



            try {

                await fetch(

                    "{{ route('dashboard.com.store') }}",

                    {

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

                    }

                );



            } catch (e) {

                console.log(e);

            }

        }

        

        /*

        |--------------------------------------------------------------------------

        | Update Advertisement (With 1-Second Auto-Retry)

        |--------------------------------------------------------------------------

        */

        let targetAdPrice = null; // Keeps track of the most recent price requested



        async function updateAdPrice(newPrice) {



            // Always update our target to the newest requested price

            targetAdPrice = newPrice;



            // If an update or retry loop is already running, don't spawn another one.

            // It will automatically pick up the new targetAdPrice on its next loop.

            if (updatingAd) {

                return;

            }



            updatingAd = true;



            // Loop indefinitely until the update successfully hits the backend

            while (true) {



                const ad = adsData.find(

                    x => String(x.id) === String(document.getElementById('adId').value)

                );



                if (!ad) {

                    updatingAd = false;

                    toast('Please select your Ad first.', 'error');

                    return;

                }



                // Grab the freshest target price right before we send the request

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

                        

                        // Success! Update UI and local data.

                        ad.price = priceToUpdate;

                        document.getElementById('currentPrice').innerHTML = priceToUpdate;

                        toast(`Ad updated to ${priceToUpdate}`);

                        

                        // If no newer price was requested while we were waiting, we are fully synced.

                        if (targetAdPrice === priceToUpdate) {

                            break; // Exit the retry loop

                        }



                    } else {

                        // Backend returned an error (e.g., rate limit, min/max error)

                        console.log('Backend Error:', result);

                        toast('Retrying in 1 second...', 'error');

                        

                        // Sleep for 1 second before retrying

                        await new Promise(resolve => setTimeout(resolve, 1000));

                    }



                } catch (e) {

                    // Network failed (e.g., connection lost, timeout)

                    console.log('Network Error:', e);

                    toast('Network error. Retrying in 1 second...', 'error');

                    

                    // Sleep for 1 second before retrying

                    await new Promise(resolve => setTimeout(resolve, 1000));

                }

            }



            // Loop finished successfully, release the lock

            updatingAd = false;

        }





        /*

        |--------------------------------------------------------------------------

        | Update Advertisement

        |--------------------------------------------------------------------------

        

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



        */



        /*

        |--------------------------------------------------------------------------

        | Polling

        |--------------------------------------------------------------------------

        */

        setInterval(() => {



            if (

                selectedToken &&

                selectedCurrency

            ) {

                fetchCompetitors();

            }



        }, 500);



        /*

        |--------------------------------------------------------------------------

        | Visual Countdown Timer (updates every 1 second)

        |--------------------------------------------------------------------------

        */

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

