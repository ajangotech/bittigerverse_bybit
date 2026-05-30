<nav class="nav flex-column">

    <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}"
       href="/dashboard">
        <i class="bi bi-grid-fill me-2"></i> Dashboard
    </a>

    <a class="nav-link {{ request()->is('dashboard/ads*') ? 'active' : '' }}"
       href="/dashboard/ads">
        <i class="bi bi-wallet2 me-2"></i> Manage Ads
    </a>

    <a class="nav-link {{ request()->is('dashboard/adslist*') ? 'active' : '' }}"
       href="/dashboard/adslist">
        <i class="bi bi-wallet2 me-2"></i> Ads Lists
    </a>

    @auth
        @if(auth()->user()->role === 'admin')
            <a class="nav-link {{ request()->is('dashboard/users*') ? 'active' : '' }}"
            href="/dashboard/users">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
        @endif
    @endauth

    {{-- ✅ ADD PENDING ORDERS --}}
    <a class="nav-link {{ request()->is('/dashboard/orders*') ? 'active' : '' }}"
       href="/dashboard/orders">
        <i class="bi bi-hourglass-split me-2"></i> Pending Orders
    </a>

</nav>

<div class="mt-auto pt-4">
    <form action="/logout" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-danger w-100">
            <i class="bi bi-box-arrow-left me-2"></i> Logout
        </button>
    </form>
</div>