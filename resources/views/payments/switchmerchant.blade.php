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
    
    .switch-panel {
        background-color: #f8f9fa;
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
                        <p><b>Status:</b> <span id="statusText" class="fw-bold">---</span></p>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card ads-card">
                <div class="card-body">

                    <h5 class="fw-bold mb-4">
                        Ad Status Auto-Switcher
                        <i class="bi bi-toggle-on"></i>
                    </h5>

                    <!-- ⚙️ Switch Configuration Panel -->
                    <div class="p-3 mb-3 switch-panel">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="switcherModeToggle">
                            <label class="form-check-label fw-bold text-dark" for="switcherModeToggle">
                                🚀 Enable Ad Status Auto-Switcher
                            </label>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted mb-1">Turn ON After (Minutes):</label>
                                <input type="number" class="form-control form-control-sm" id="onDuration" value="5" min="1">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1">Turn OFF After (Minutes):</label>
                                <input type="number" class="form-control form-control-sm" id="offDuration" value="2" min="1">
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-3">
                        <p><b>Selected Ad ID:</b> <span id="displayAdId" class="text-primary fw-bold">---</span></p>
                        <p><b>Automation State:</b> <span id="schedulerState" class="fw-bold text-muted">Stopped</span></p>
                        <p class="mb-0"><b>Next Action In:</b> <span id="schedulerCountdown" class="text-danger fw-bold">---</span></p>
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
        const switcherToggle = document.getElementById('switcherModeToggle');
        const onDurationInput = document.getElementById('onDuration');
        const offDurationInput = document.getElementById('offDuration');
        const countdownEl = document.getElementById('schedulerCountdown');
        const schedulerStateEl = document.getElementById('schedulerState');

        let adsData = [];
        let cycleStartTime = Date.now();
        let isCurrentlyActive = false; 

        let activeAd = null;
        let tracking = false;
        let updatingAd = false;

        const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        function toast(message, type = 'success') {
            const t = document.getElementById('toast');
            t.innerHTML = message;
            t.className = `app-toast show ${type}`;
            setTimeout(() => { t.className = 'app-toast'; }, 3000);
        }

        if (switcherToggle) {
            switcherToggle.addEventListener('change', function() {
                if (this.checked) {
                    if (!activeAd) {
                        this.checked = false;
                        toast('Please select an Advertisement first.', 'error');
                        return;
                    }
                    cycleStartTime = Date.now();
                    isCurrentlyActive = true; 
                    tracking = true;
                    schedulerStateEl.innerHTML = 'ONLINE (ON)';
                    schedulerStateEl.className = 'fw-bold text-success';
                    toast('Ad Auto-Switcher Started');
                    updateAdStatus('ONLINE');
                } else {
                    tracking = false;
                    schedulerStateEl.innerHTML = 'Stopped';
                    schedulerStateEl.className = 'fw-bold text-muted';
                    toast('Ad Auto-Switcher Stopped');
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
                            ID: ${ad.id} | ${ad.tokenId}/${ad.currencyId} | Price: ${ad.price} | Status: ${ad.status}
                        </option>
                    `;
                });
            } catch (e) {
                toast('Failed to load advertisements.', 'error');
            }
        }
        loadAds();

        adsSelect.addEventListener('change', function () {
            const adId = this.value;
            activeAd = adsData.find(x => String(x.id) === String(adId));

            if (!activeAd) {
                tracking = false;
                if (switcherToggle) switcherToggle.checked = false;
                document.getElementById('pairText').innerHTML = '---';
                document.getElementById('currentPrice').innerHTML = '---';
                document.getElementById('minText').innerHTML = '---';
                document.getElementById('maxText').innerHTML = '---';
                document.getElementById('statusText').innerHTML = '---';
                document.getElementById('displayAdId').innerHTML = '---';
                return;
            }

            document.getElementById('adId').value = activeAd.id;
            document.getElementById('displayAdId').innerHTML = activeAd.id;
            document.getElementById('pairText').innerHTML = `${activeAd.tokenId}/${activeAd.currencyId}`;
            document.getElementById('currentPrice').innerHTML = activeAd.price;
            document.getElementById('minText').innerHTML = activeAd.minAmount;
            document.getElementById('maxText').innerHTML = activeAd.maxAmount;
            document.getElementById('statusText').innerHTML = activeAd.status ?? '---';

            if (switcherToggle.checked) {
                cycleStartTime = Date.now();
                isCurrentlyActive = true;
                tracking = true;
                schedulerStateEl.innerHTML = 'ONLINE (ON)';
                schedulerStateEl.className = 'fw-bold text-success';
                updateAdStatus('ONLINE');
            }
        });

        async function processScheduler() {
            if (!tracking || !activeAd || !switcherToggle.checked) return;

            const onMs = (parseFloat(onDurationInput.value) || 5) * 60000;
            const offMs = (parseFloat(offDurationInput.value) || 2) * 60000;
            const elapsed = Date.now() - cycleStartTime;

            if (isCurrentlyActive) {
                if (elapsed >= onMs) {
                    toast(`Ad #${activeAd.id}: ON duration ended. Switching OFF...`, 'error');
                    isCurrentlyActive = false;
                    cycleStartTime = Date.now();
                    schedulerStateEl.innerHTML = 'OFFLINE (OFF)';
                    schedulerStateEl.className = 'fw-bold text-danger';
                    await updateAdStatus('OFFLINE');
                }
            } else {
                if (elapsed >= offMs) {
                    toast(`Ad #${activeAd.id}: OFF duration ended. Switching ON...`, 'success');
                    isCurrentlyActive = true;
                    cycleStartTime = Date.now();
                    schedulerStateEl.innerHTML = 'ONLINE (ON)';
                    schedulerStateEl.className = 'fw-bold text-success';
                    await updateAdStatus('ONLINE');
                }
            }
        }

        async function updateAdStatus(targetState) {
            if (!activeAd || updatingAd) return;
            updatingAd = true;

            while (true) {
                const currentAd = adsData.find(x => String(x.id) === String(activeAd.id));
                if (!currentAd) {
                    updatingAd = false;
                    return;
                }

                const payload = {
                    ...currentAd,
                    status: targetState,
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
                        currentAd.status = targetState;
                        activeAd.status = targetState;
                        document.getElementById('statusText').innerHTML = targetState;
                        toast(`Ad #${activeAd.id} status successfully switched to ${targetState}`);
                        break;
                    } else {
                        toast('Retrying status switch...', 'error');
                        await sleep(1000);
                    }
                } catch (e) {
                    toast('Network error. Retrying...', 'error');
                    await sleep(1000);
                }
            }
            updatingAd = false;
        }

        setInterval(() => {
            if (tracking && activeAd && switcherToggle.checked) {
                processScheduler();

                const onMs = (parseFloat(onDurationInput.value) || 5) * 60000;
                const offMs = (parseFloat(offDurationInput.value) || 2) * 60000;
                const limitMs = isCurrentlyActive ? onMs : offMs;
                const elapsed = Date.now() - cycleStartTime;
                const timeLeft = Math.max(0, limitMs - elapsed);

                const mins = Math.floor(timeLeft / 60000);
                const secs = Math.floor((timeLeft % 60000) / 1000);
                countdownEl.innerHTML = `${mins}m ${secs}s (${isCurrentlyActive ? 'Turn OFF in' : 'Turn ON in'})`;
            } else {
                countdownEl.innerHTML = '---';
            }
        }, 1000);

    });
</script>