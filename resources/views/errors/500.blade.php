<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>500 - Server Error</title>
    
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
        
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif; 
        }

        .error-box {
            max-width: 500px;
            margin: auto;
            margin-top: 10vh;
            text-align: center;
        }

        .error-code {
            font-size: 110px;
            font-weight: 800;
            color: #E37216;
        }

        .card {
            border: none;
        }

        .btn-primary {
            background-color: #E37216;
            border: none;
        }

        .btn-primary:hover {
            background-color: #c95f0f;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="error-box">

        <div class="card shadow-sm rounded-4 p-5 bg-white">
            <div class="error-code">500</div>

            <h4 class="fw-bold mt-3">Server Error</h4>

            <p class="text-muted mt-2">
                Something went wrong on our end. Please try again.
            </p>

            <div class="mt-4 d-grid gap-2">
                <button onclick="location.reload()" class="btn btn-primary">
                    Retry
                </button>

                <a href="/dashboard" class="btn btn-outline-secondary">
                    Go to Dashboard
                </a>
            </div>
        </div>

    </div>
</div>

</body>
</html>