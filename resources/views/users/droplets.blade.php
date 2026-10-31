@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1">Droplets Management</h5>
                    <small class="text-muted">
                        Manage your DigitalOcean servers
                    </small>
                </div>

                <button class="btn btn-dark btn-sm" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-1"></i>
                    Refresh
                </button>
            </div>

            @if(isset($error) && $error)
                <div class="alert alert-danger">
                    <strong>DigitalOcean Error:</strong>
                    {{ $error }}
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Your Droplets</h6>

                <span class="badge bg-dark">
                    {{ count($droplets) }} Droplets
                </span>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="text-muted small text-uppercase">
                        <tr>
                            <th>Droplet</th>
                            <th>ID</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>Region</th>
                            <th>Resources</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($droplets as $droplet)

                            @php
                                $publicIp = collect($droplet['networks']['v4'] ?? [])
                                    ->firstWhere('type', 'public')['ip_address'] ?? 'N/A';
                            @endphp

                            <tr id="droplet-{{ $droplet['id'] }}">

                                {{-- NAME --}}
                                <td>

                                    <div class="fw-bold">
                                        {{ $droplet['name'] }}
                                    </div>

                                    <small class="text-muted">
                                        {{ $droplet['image']['distribution'] ?? 'Unknown OS' }}
                                        {{ $droplet['image']['version'] ?? '' }}
                                    </small>

                                </td>

                                {{-- ID --}}
                                <td>
                                    <span class="badge bg-light text-dark">
                                        {{ $droplet['id'] }}
                                    </span>
                                </td>

                                {{-- STATUS --}}
                                <td>

                                    @if($droplet['status'] === 'active')

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    @elseif($droplet['status'] === 'off')

                                        <span class="badge bg-secondary">
                                            Off
                                        </span>

                                    @else

                                        <span class="badge bg-warning text-dark">
                                            {{ ucfirst($droplet['status']) }}
                                        </span>

                                    @endif

                                </td>

                                {{-- IP --}}
                                <td>
                                    <code>{{ $publicIp }}</code>
                                </td>

                                {{-- REGION --}}
                                <td>
                                    {{ $droplet['region']['name'] ?? 'N/A' }}

                                    <br>

                                    <small class="text-muted">
                                        {{ $droplet['region']['slug'] ?? '' }}
                                    </small>
                                </td>

                                {{-- RESOURCES --}}
                                <td>

                                    <small>

                                        <strong>
                                            {{ $droplet['vcpus'] }}
                                        </strong>
                                        CPU

                                        <br>

                                        <strong>
                                            {{ number_format($droplet['memory'] / 1024, 1) }} GB
                                        </strong>
                                        RAM

                                        <br>

                                        <strong>
                                            {{ $droplet['disk'] }} GB
                                        </strong>
                                        Disk

                                    </small>

                                </td>

                                {{-- ACTIONS --}}
                                <td>

                                    <div class="dropdown">

                                        <button
                                            class="btn btn-sm btn-outline-dark dropdown-toggle"
                                            data-bs-toggle="dropdown">

                                            Manage

                                        </button>

                                        <ul class="dropdown-menu dropdown-menu-end">

                                            <li>
                                                <button
                                                    class="dropdown-item"
                                                    onclick="restartDroplet(
                                                        {{ $droplet['id'] }},
                                                        '{{ addslashes($droplet['name']) }}'
                                                    )">

                                                    <i class="fas fa-sync-alt me-2"></i>
                                                    Restart
                                                </button>
                                            </li>

                                            <li>
                                                <button
                                                    class="dropdown-item text-danger"
                                                    onclick="powerCycleDroplet(
                                                        {{ $droplet['id'] }},
                                                        '{{ addslashes($droplet['name']) }}'
                                                    )">

                                                    <i class="fas fa-power-off me-2"></i>
                                                    Power Cycle
                                                </button>
                                            </li>

                                        </ul>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="7" class="text-center py-5">

                                    <div class="text-muted">

                                        <i class="fas fa-server fa-2x mb-3"></i>

                                        <div>
                                            No DigitalOcean droplets found.
                                        </div>

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<script>

const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute('content');


/*
|--------------------------------------------------------------------------
| RESTART DROPLET
|--------------------------------------------------------------------------
*/

async function restartDroplet(id, name)
{
    if (!confirm(
        `Are you sure you want to restart "${name}"?`
    )) {
        return;
    }

    try {

        const response = await fetch(
            `/dashboard/droplets/${id}/restart`,
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            }
        );

        const data = await response.json();

        if (data.status === 'success') {

            alert(
                `Restart initiated for ${name}.`
            );

        } else {

            alert(
                data.message || 'Failed to restart droplet.'
            );

        }

    } catch (error) {

        console.error(error);

        alert(
            'Unable to communicate with the server.'
        );

    }
}


/*
|--------------------------------------------------------------------------
| POWER CYCLE DROPLET
|--------------------------------------------------------------------------
*/

async function powerCycleDroplet(id, name)
{
    const confirmed = confirm(
        `WARNING!\n\n` +
        `Power Cycle will forcibly restart "${name}".\n\n` +
        `Are you sure you want to continue?`
    );

    if (!confirmed) {
        return;
    }

    try {

        const response = await fetch(
            `/dashboard/droplets/${id}/power-cycle`,
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            }
        );

        const data = await response.json();

        if (data.status === 'success') {

            alert(
                `Power cycle initiated for ${name}.`
            );

        } else {

            alert(
                data.message || 'Failed to power cycle droplet.'
            );

        }

    } catch (error) {

        console.error(error);

        alert(
            'Unable to communicate with the server.'
        );

    }
}

</script>

@endsection