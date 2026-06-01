@extends('layouts.app')

@section('content')

<div class="container">

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"></h5>
                <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    + Add User
                </button>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Manage Users</h5>
                <span class="badge bg-dark">{{ $users->count() }} Users</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="text-muted small text-uppercase">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>API Keys</th>
                            <th>API URL</th>
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
                                <div id="keys-{{ $user->id }}" class="mt-2 d-none">
                                    <small class="text-muted">
                                        API URL: {{ $user->api_url }}<br>
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

<!-- CREATE USER MODAL -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Create User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-2">
                    <input type="text" id="first_name" class="form-control" placeholder="First Name">
                </div>

                <div class="mb-2">
                    <input type="text" id="last_name" class="form-control" placeholder="Last Name">
                </div>

                <div class="mb-2">
                    <input type="email" id="email" class="form-control" placeholder="Email">
                </div>

                <div class="mb-2">
                    <input type="password" id="password" class="form-control" placeholder="Password">
                </div>

                <div class="mb-2">
                    <input type="text" id="bybit_api_key" class="form-control" placeholder="Bybit API Key">
                </div>

                <div class="mb-2">
                    <input type="text" id="bybit_api_secret" class="form-control" placeholder="Bybit API Secret">
                </div>

                <div class="mb-2">
                    <input type="text" id="api_url" class="form-control" placeholder="e.g. https://00-bittiger.ajango.com.ng/api">
                </div>

                <div class="mb-2">
                    <select id="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-dark" onclick="createUser()">Create User</button>
            </div>

        </div>
    </div>
</div>

<script>
    async function createUser() {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const data = {
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            status: document.getElementById('status').value,

            // NEW FIELDS
            bybit_api_key: document.getElementById('bybit_api_key').value,
            bybit_api_secret: document.getElementById('bybit_api_secret').value,
            api_url: document.getElementById('api_url').value,
        };

        const res = await fetch('/dashboard/users', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": token
            },
            body: JSON.stringify(data)
        });

        const result = await res.json();

        if (result.status === "success") {
            alert("User created successfully!");
            location.reload();
        } else {
            alert(result.message || "Failed to create user");
        }
    }
</script>

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