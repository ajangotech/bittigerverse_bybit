@extends('layouts.app')

@section('content')

<style>
    .ads-card {
        border-radius: 16px;
        border: 1px solid #eee;
        box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    }

    .price-box {
        font-size: 1.5rem;
        font-weight: 700;
        color: #E37216;
    }

    .mini-info {
        font-size: 12px;
        color: #888;
    }

    .fast-input {
        font-size: 18px;
        font-weight: 600;
    }
</style>

<div class="container">

    <div class="row g-4">

        <!-- LEFT: ADS LIST -->
        <div class="col-md-7">

            <div class="card ads-card p-3">
                <h5 class="fw-bold mb-3">All Advertisements</h5>

                <select id="adsSelect" class="form-select form-select-lg">
                    <option value="">-- Select Ad (Pair | Price) --</option>

                    @foreach($ads as $ad)
                        <option 
                            value="{{ $ad->id }}"
                            data-pair="{{ $ad->pair }}"
                            data-price="{{ $ad->price }}"
                            data-premium="{{ $ad->premium }}"
                            data-min="{{ $ad->min_amount }}"
                            data-max="{{ $ad->max_amount }}"
                        >
                            {{ $ad->pair }} | ₦{{ number_format($ad->price, 2) }}
                        </option>
                    @endforeach
                </select>

                <div class="mt-4 p-3 border rounded-3 bg-light">
                    <div class="mini-info">Selected Ad Info</div>

                    <div class="mt-2">
                        <div><b>Pair:</b> <span id="pairText">---</span></div>
                        <div><b>Min:</b> <span id="minText">---</span> | <b>Max:</b> <span id="maxText">---</span></div>
                        <div><b>Premium:</b> <span id="premiumText">---</span></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT: QUICK PRICE UPDATE -->
        <div class="col-md-5">

            <div class="card ads-card p-4">
                <h5 class="fw-bold">Quick Price Update</h5>

                <input type="hidden" id="adId">

                <label class="form-label mt-3">Price</label>
                <input 
                    type="number" 
                    id="priceInput" 
                    class="form-control fast-input"
                    placeholder="Enter new price"
                >

                <button id="updateBtn" class="btn w-100 mt-3 text-white" style="background:#E37216;">
                    Update Price
                </button>

                <div class="mt-3 text-muted small">
                    Tip: You can paste a price here and it updates automatically.
                </div>
            </div>

        </div>

    </div>
</div>

@endsection

@push('scripts')

<script>
const select = document.getElementById('adsSelect');
const priceInput = document.getElementById('priceInput');
const adId = document.getElementById('adId');

const pairText = document.getElementById('pairText');
const minText = document.getElementById('minText');
const maxText = document.getElementById('maxText');
const premiumText = document.getElementById('premiumText');

// CSRF
const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// ON SELECT AD
select.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];

    adId.value = this.value;
    priceInput.value = opt.dataset.price || '';

    pairText.innerText = opt.dataset.pair || '---';
    minText.innerText = opt.dataset.min || '---';
    maxText.innerText = opt.dataset.max || '---';
    premiumText.innerText = opt.dataset.premium || '---';
});

// AUTO UPDATE FUNCTION
async function updatePrice() {

    if (!adId.value) return;

    const payload = {
        id: adId.value,
        price: priceInput.value
    };

    await fetch('/dashboard/ads/update-price', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify(payload)
    });

}

// CLICK BUTTON
document.getElementById('updateBtn').addEventListener('click', updatePrice);

// AUTO UPDATE ON PASTE / CHANGE (FAST MODE)
priceInput.addEventListener('paste', () => {
    setTimeout(updatePrice, 500);
});

priceInput.addEventListener('change', () => {
    updatePrice();
});

</script>

@endpush