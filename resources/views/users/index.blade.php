@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Manage Users</h5>
                <span class="badge bg-primary">{{ $users->count() }} Users</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="text-muted small text-uppercase">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>API Keys</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($users as $user)
                        <tr id="user-{{ $user->id }}">

                            <td>
                                <div class="fw-bold">
                                    {{ $user->first_name }} {{ $user->last_name }}
                                </div>
                            </td>

                            <td>{{ $user->email }}</td>

                            <td>
                                <select class="form-select form-select-sm"
                                    onchange="updateStatus({{ $user->id }}, this.value)">
                                    <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="suspended" {{ $user->status == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                            </td>

                            <td>
                                <button class="btn btn-sm btn-outline-secondary"
                                    onclick="toggleKeys({{ $user->id }})">
                                    View Keys
                                </button>

                                <div id="keys-{{ $user->id }}" class="mt-2 d-none">
                                    <small class="text-muted">
                                        API KEY: {{ Str::limit($user->bybit_api_key, 20) }}<br>
                                        SECRET: {{ Str::limit($user->bybit_api_secret, 20) }}
                                    </small>
                                </div>
                            </td>

                            <td>
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteUser({{ $user->id }})">
                                    Delete
                                </button>
                            </td>

                        </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

<script>
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // UPDATE STATUS
    async function updateStatus(id, status) {
        await fetch(`/dashboard/users/${id}/status`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": token
            },
            body: JSON.stringify({ status })
        });
    }

    // TOGGLE API KEYS
    function toggleKeys(id) {
        const el = document.getElementById(`keys-${id}`);
        el.classList.toggle('d-none');
    }

    // DELETE USER
    async function deleteUser(id) {
        if (!confirm("Delete this user?")) return;

        const res = await fetch(`/dashboard/users/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": token
            }
        });

        const data = await res.json();

        if (data.status === "success") {
            document.getElementById(`user-${id}`).remove();
        }
    }
</script>

@endsection