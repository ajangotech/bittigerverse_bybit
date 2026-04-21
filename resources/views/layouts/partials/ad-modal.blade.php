<div class="modal fade" id="adModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form id="adForm" class="modal-content border-0 rounded-4">
            
            <div class="modal-header border-0 p-4">
                <h5 class="modal-title fw-bold">Create / Update Advertisement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">

                <!-- Hidden ID (for update) -->
                <input type="hidden" name="id" id="ad_id">

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ads ID</label>
                        <input type="text" name="adsID" class="form-control" placeholder="1993994642220000000">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Pair</label>
                        <input type="text" name="pair" class="form-control" placeholder="BTC/NGN">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Price Type</label>
                        <select name="priceType" class="form-select">
                            <option value="0">Fixed</option>
                            <option value="1">Floating</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Action Type</label>
                        <select name="actionType" class="form-select">
                            <option value="CREATE">CREATE</option>
                            <option value="MODIFY">MODIFY</option>
                            <option value="ACTIVE">ACTIVE</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" step="0.00000001" name="price" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Premium (%)</label>
                        <input type="number" step="0.0001" name="premium" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Min Amount</label>
                        <input type="number" step="0.00000001" name="minAmount" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Max Amount</label>
                        <input type="number" step="0.00000001" name="maxAmount" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Period (mins)</label>
                        <input type="number" name="paymentPeriod" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Remark</label>
                        <input type="text" name="remark" class="form-control" placeholder="Optional note">
                    </div>

                </div>

                <hr>

                <!-- PAYMENT IDS -->
                <h6 class="fw-bold">Payment Methods</h6>
                <div class="mb-3">
                    <input type="text" name="paymentIds[]" class="form-control mb-2" placeholder="Payment ID 1">
                    <input type="text" name="paymentIds[]" class="form-control mb-2" placeholder="Payment ID 2">
                </div>

                <hr>

                <!-- TRADING PREFERENCES -->
                <h6 class="fw-bold">Trading Preferences</h6>

                <div class="row g-2">

                    <div class="col-md-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="tradingPreferenceSet[isKyc]" value="1">
                        <label class="form-check-label">KYC Required</label>
                    </div>

                    <div class="col-md-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="tradingPreferenceSet[isEmail]" value="1">
                        <label class="form-check-label">Email Required</label>
                    </div>

                    <div class="col-md-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="tradingPreferenceSet[isMobile]" value="1">
                        <label class="form-check-label">Mobile Required</label>
                    </div>

                    <div class="col-md-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="tradingPreferenceSet[hasUnPostAd]" value="1">
                        <label class="form-check-label">Has UnPost Ad</label>
                    </div>

                </div>

            </div>

            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn text-white" style="background:#E37216;">
                    Save Advertisement
                </button>
            </div>

        </form>

        <script>
            document.getElementById('adForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = e.target;
                const formData = new FormData(form);

                // FIX CHECKBOXES (IMPORTANT)
                const prefKeys = [
                    "isKyc", "isEmail", "isMobile", "hasUnPostAd"
                ];

                prefKeys.forEach(key => {
                    if (!formData.has(`tradingPreferenceSet[${key}]`)) {
                        formData.append(`tradingPreferenceSet[${key}]`, 0);
                    }
                });

                const res = await fetch('/dashboard/ads/store', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token
                    },
                    body: formData
                });

                const data = await res.json();

                if (data.status === 'success') {
                    alert('Ad saved successfully');
                    location.reload(); // simple refresh (you can optimize later)
                } else {
                    alert('Error saving ad');
                }
            });
            </script>
    </div>
</div>