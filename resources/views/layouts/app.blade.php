<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Dashboard') }}</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- jQuery (required) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <link rel="shortcut icon" href="/logo.png" type="image/x-icon">

    <style>
        :root { --brand-primary: #E37216; --bg-color: #F5F7FA; --sidebar-width: 250px; }
        body { background-color: var(--bg-color); font-family: 'Inter', sans-serif; }
        
        /* Layout Structure */
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: #ffffff; border-right: 1px solid #e0e0e0; padding: 1.5rem; flex-shrink: 0; }
        .content { flex-grow: 1; padding: 2rem; width: 100%; }
        
        /* Brand/Logo Styling */
        .brand-logo { color: #111827; font-weight: 700; font-size: 1.4rem; display: flex; align-items: center; gap: 10px; margin-bottom: 2rem; }
        .brand-logo img { width: 32px; height: auto; object-fit: contain; }
        
        /* Navigation */
        .nav-link { color: #667085; font-weight: 500; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background: #fff5eb; color: var(--brand-primary); }
        
        /* Mobile Header */
        .mobile-header { background: #fff; padding: 1rem; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
    </style>
</head>
<body>

    <div class="mobile-header d-md-none">
        <div class="brand-logo mb-0">
            <img src="/logo.png" alt="Logo"> {{ config('app.name') }}
        </div>
        <button class="btn btn-outline-dark" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="wrapper">
        <aside class="sidebar d-none d-md-flex flex-column">
            <div class="brand-logo">
                <img src="/logo.png" alt="Logo"> {{ config('app.name') }}
            </div>
            @include('layouts.partials.nav-links')
        </aside>

        <main class="content">
            <!-- TOP NAVBAR -->
            <nav class="navbar navbar-expand-lg mb-4 bg-white rounded-3 shadow-sm px-3 py-2">
                
                <div class="container-fluid">

                    <div>
                        <h6 class="mb-0 fw-bold">Dashboard</h6>
                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto">

                        <!-- Notifications -->
                        <button class="btn btn-light position-relative">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge bg-danger">
                                3
                            </span>
                        </button>

                        <!-- USER DROPDOWN -->
                        <div class="dropdown">

                            <a class="d-flex align-items-center text-decoration-none dropdown-toggle"
                            href="#"
                            data-bs-toggle="dropdown">

                                <div class="bg-dark text-white rounded-circle d-flex justify-content-center align-items-center"
                                    style="width:35px;height:35px;">
                                    <i class="bi bi-person"></i>
                                </div>

                                <span class="ms-2 fw-medium text-dark">
                                    {{ Auth::user()->first_name ?? 'User' }}
                                </span>

                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                <li>
                                    <form method="POST" action="/logout">
                                        @csrf
                                        <button class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                                        </button>
                                    </form>
                                </li>

                            </ul>

                        </div>

                    </div>

                </div>

            </nav>

            @yield('content')

        </main>
    </div>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header">
            <div class="brand-logo"><img src="/logo.png" alt="Logo"> MySystem</div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            @include('layouts.partials.nav-links')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>