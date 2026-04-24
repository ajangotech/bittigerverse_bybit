<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="/logo.png" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
:root {
    --brand-primary: #E37216;
    --bg: #F5F7FA;
}

body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    height: 100vh;
    background: var(--bg);
}

.split-container {
    display: flex;
    height: 100vh;
}

/* LEFT SIDE */
.left-panel {
    flex: 1;
    background: linear-gradient(135deg, #E37216, #cc6513);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px;
}

.left-panel h1 {
    font-weight: 700;
    margin-bottom: 10px;
}

.left-panel p {
    opacity: 0.9;
}

/* RIGHT SIDE */
.right-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.form-box {
    width: 100%;
    max-width: 420px;
    background: #fff;
    padding: 30px;
    border-radius: 14px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}

.form-control {
    border-radius: 8px;
    padding: 10px;
}

.btn-primary {
    background: var(--brand-primary);
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 600;
}

.btn-primary:hover {
    background: #cc6513;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .split-container {
        flex-direction: column;
    }

    .left-panel {
        display: none; /* optional hide on mobile */
    }

    .right-panel {
        flex: none;
        height: 100vh;
    }
}
</style>
</head>

<body>

<div class="split-container">

    <!-- LEFT SIDE -->
    <div class="left-panel">
        <h1>BittigerVerse</h1>
        <p>Trade smarter. Manage your Bybit API securely and efficiently.</p>
        <p>Secure • Fast • Reliable</p>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right-panel">

        <div class="form-box">

            <h4 class="fw-bold mb-4 text-center">Create Account</h4>

            <div id="alertBox" class="alert alert-danger d-none text-center p-2 mb-3"></div>

            <form id="registerForm">

                <div class="row">
                    <div class="col-6 mb-2">
                        <input name="first_name" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="col-6 mb-2">
                        <input name="last_name" class="form-control" placeholder="Last Name" required>
                    </div>
                </div>

                <input name="email" class="form-control mb-2" placeholder="Email" required>
                <input name="bybit_api_key" class="form-control mb-2" placeholder="API Key" required>
                <input name="bybit_api_secret" class="form-control mb-2" placeholder="API Secret" required>
                <input type="password" name="password" class="form-control mb-2" placeholder="Password" required>
                <input type="password" name="password_confirmation" class="form-control mb-3" placeholder="Confirm Password" required>

                <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                    <span class="button-text">Sign Up</span>
                    <span class="spinner-border spinner-border-sm d-none ms-2" id="btnSpinner"></span>
                </button>

                <div class="text-center mt-3">
                    <a href="/" style="color:#E37216; text-decoration:none;">
                        Already have an account? Login
                    </a>
                </div>

            </form>

            <script>
                document.getElementById('registerForm').addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const btn = document.getElementById('submitBtn');
                    const spinner = document.getElementById('btnSpinner');
                    const alertBox = document.getElementById('alertBox');

                    btn.disabled = true;
                    spinner.classList.remove('d-none');
                    alertBox.classList.add('d-none');

                    try {
                        const res = await fetch('/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(Object.fromEntries(new FormData(this)))
                        });

                        const data = await res.json();

                        if (res.ok) {
                            window.location.href = data.redirect;
                            return;
                        }

                        alertBox.textContent =
                            data.errors
                                ? Object.values(data.errors).flat().join(', ')
                                : 'Registration failed';

                        alertBox.classList.remove('d-none');

                    } catch (error) {
                        alertBox.textContent = 'Server error. Please try again.' + error;
                        alertBox.classList.remove('d-none');
                    } finally {
                        btn.disabled = false;
                        spinner.classList.add('d-none');
                    }
                });
                </script>

        </div>

    </div>
</div>

</body>
</html>