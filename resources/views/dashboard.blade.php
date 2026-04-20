@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <button class="btn btn-primary mb-4 w-500 px-4" data-bs-toggle="modal" data-bs-target="#adModal" style="background-color: #E37216; border: none;">
                        <i class="bi bi-plus-lg me-2"></i> Create New Ad
                    </button>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Active Advertisements</h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="adsTable" class="table table-bordered align-middle">
                            <thead class="text-muted small text-uppercase">
                                <tr><th>Pair</th><th>Price</th><th>Limit</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="adsTableBody">
                                @forelse($ads as $ad)
                                    <tr id="ad-row-{{ $ad->id }}">

                                        <td>
                                            <div class="fw-bold">{{ $ad->pair }}</div>
                                            <small class="text-muted">ID: {{ $ad->ads_id }}</small>
                                        </td>

                                        <td>
                                            <span class="fw-bold text-success">
                                                ₦{{ number_format($ad->price, 2) }}
                                            </span>
                                        </td>

                                        <td>
                                            <small>
                                                {{ $ad->min_amount }} - {{ $ad->max_amount }}
                                            </small>
                                        </td>

                                        <td>
                                            @if($ad->action_type == 'ACTIVE')
                                                <span class="badge bg-success">Active</span>
                                            @elseif($ad->action_type == 'MODIFY')
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>

                                        <td>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="deleteAd({{ $ad->id }})">
                                                Delete
                                            </button>
                                        </td>

                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No advertisements found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="position-sticky" style="top: 20px;">
                <div class="row g-3 mb-4">
                    <!-- USERS -->
                    <div class="col-6">
                        <div class="card p-3 border-0 shadow-sm rounded-4 text-center">
                            <small class="text-muted">Total Users</small>
                            <h4 class="fw-bold mt-1">{{ $usersCount }}</h4>
                        </div>
                    </div>

                    <!-- ADS -->
                    <div class="col-6">
                        <div class="card p-3 border-0 shadow-sm rounded-4 text-center">
                            <small class="text-muted">Total Ads</small>
                            <h4 class="fw-bold mt-1 text-primary">{{ $adsCount }}</h4>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h6 class="fw-bold mb-3">Quick Actions</h6>
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="d-grid gap-2">

                            <a href="/dashboard/users" class="btn btn-light text-start">
                                <i class="bi bi-people me-2"></i> Manage Users
                            </a>

                            <a href="/dashboard/ads" class="btn btn-light text-start">
                                <i class="bi bi-megaphone me-2"></i> Manage Ads
                            </a>

                            <button onclick="location.reload()" class="btn btn-light text-start">
                                <i class="bi bi-arrow-clockwise me-2"></i> Refresh Page
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Typography */
    body { font-family: 'Inter', system-ui, sans-serif; }
    
    /* Rounded Cards */
    .rounded-4 { border-radius: 1rem !important; }
    
    /* Inputs */
    .form-control:focus {
        border-color: #E37216;
        box-shadow: 0 0 0 0.25rem rgba(227, 114, 22, 0.1);
    }
    
    /* Toggle Switches */
    .form-check-input:checked {
        background-color: #E37216;
        border-color: #E37216;
    }

    /* Table Spacing */
    .table th { background: transparent !important; }
    .table td { padding: 1rem; }
</style>

<script>
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    async function deleteAd(id) {
        if (!confirm("Are you sure you want to delete this ad?")) return;

        const res = await fetch(`/dashboard/ads/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": token,
                "Content-Type": "application/json"
            }
        });

        const data = await res.json();

        if (data.status === "success") {
            document.getElementById(`ad-row-${id}`).remove();
        } else {
            alert("Failed to delete ad");
        }
    }

    // Optional quick edit hook (connect later to modal)
    function editAd(id, price) {
        document.getElementById('ad_id').value = id;
        document.getElementById('priceInput').value = price;

        new bootstrap.Modal(document.getElementById('adModal')).show();
    }
</script>

<script>
    $(document).ready(function () {
        $('#adsTable').DataTable({
            pageLength: 10,
            ordering: true,
            searching: true,
            responsive: true
        });
    });
</script>

@include('layouts.partials.ad-modal')
@endsection
