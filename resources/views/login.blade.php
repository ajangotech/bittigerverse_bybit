<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <meta name="csrf-token" content="{{ csrf_token() }}"> <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | Admin System</title>
    
    <link rel="shortcut icon" href="/logo.png" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --brand-primary: #E37216;
            --bg-color: #F5F7FA;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .logo-box { margin-bottom: 2rem; text-align: center; }
        .logo-box img { width: 64px; height: auto; }

        .form-label { font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
        .form-control { 
            border-radius: 8px; 
            padding: 0.75rem 1rem; 
            border: 1px solid #D1D5DB; 
            font-size: 0.95rem; 
            transition: all 0.2s;
        }
        .form-control:focus { 
            border-color: var(--brand-primary); 
            box-shadow: 0 0 0 4px rgba(227, 114, 22, 0.1); 
        }

        .btn-primary { 
            background-color: var(--brand-primary); 
            border: none; 
            padding: 0.75rem; 
            border-radius: 8px; 
            font-weight: 600; 
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        .btn-primary:hover { background-color: #cc6513; }

        .text-muted { color: #6B7280 !important; font-size: 0.875rem; }
        .alert { border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-box">
            <img src="/logo.png" alt="Logo">
        </div>
        
        <div class="mb-4 text-center">
            <h4 class="fw-bold mb-1">Welcome Back</h4>
            <p class="text-muted">Sign in to your dashboard</p>
        </div>

        <div id="alertBox" class="alert alert-danger d-none p-2 text-center" role="alert"></div>

        <form id="loginForm">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" placeholder="name@example.com" required autofocus>
                <div id="emailError" class="invalid-feedback"></div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                <div id="passwordError" class="invalid-feedback"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                <span class="button-text">Sign In</span>
                <span class="spinner-border spinner-border-sm d-none ms-2" id="btnSpinner"></span>
            </button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const spinner = document.getElementById('btnSpinner');
            
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const res = await fetch('/', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                },
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            });

            const data = await res.json();
            if(res.ok) {
                console.log(data)
                window.location.href = data.redirect;
            } else {
                document.getElementById('alertBox').textContent = 'Invalid credentials provided.';
                document.getElementById('alertBox').classList.remove('d-none');
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });
    </script>
</body>
</html>