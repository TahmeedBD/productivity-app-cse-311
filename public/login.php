<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Productivity Tracker</title>
<style>
body {
    margin: 0;
    padding: 0;
    background-color: #1a1512;
    font-family: system-ui, -apple-system, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    color: #fff;
}
.login-container {
    background-color: #26211e;
    padding: 40px;
    border-radius: 12px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    box-sizing: border-box;
}
.header {
    text-align: center;
    margin-bottom: 30px;
}
.icon {
    background-color: #3b2a1e;
    color: #ffb076;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 16px;
    font-size: 24px;
}
.header h2 {
    margin: 0 0 8px;
    font-size: 24px;
}
.header p {
    margin: 0;
    color: #a09893;
    font-size: 14px;
}
.form-group {
    margin-bottom: 20px;
    position: relative;
}
.form-group label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #d4cfcc;
    text-transform: uppercase;
}
.form-group a {
    color: #ffb076;
    text-decoration: none;
    text-transform: none;
}
.input-wrapper {
    position: relative;
}
.form-control {
    width: 100%;
    padding: 12px;
    background-color: #332d29;
    border: 1px solid #4a423d;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    box-sizing: border-box;
}
.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #a09893;
    user-select: none;
}
.btn-submit {
    width: 100%;
    padding: 14px;
    background-color: #ffb076;
    color: #1a1512;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
}
#login-error {
    color: #ff6b6b;
    font-size: 14px;
    margin-bottom: 15px;
    text-align: center;
}
.footer {
    text-align: center;
    margin-top: 24px;
    font-size: 14px;
    color: #a09893;
}
.footer a {
    color: #ffb076;
    text-decoration: none;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="login-container">
    <div class="header">
        <div class="icon">⚡</div>
        <h2>Productivity Tracker</h2>
        <p>Sign in to continue your focus session.</p>
    </div>

    <div id="login-error"></div>

    <form id="login-form">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
        </div>

        <div class="form-group">
            <label>
                Password
                <a href="#">Forgot password?</a>
            </label>
            <div class="input-wrapper">
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                <span class="toggle-password" onclick="togglePass('password')">👁️</span>
            </div>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>
    </form>

    <div class="footer">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

document.querySelector('#login-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const errorBox = document.querySelector('#login-error');
    errorBox.textContent = '';

    const email = document.querySelector('[name="email"]').value.trim();
    const password = document.querySelector('[name="password"]').value;

    const response = await fetch('auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password }),
    });

    const data = await response.json();
    if (!response.ok) {
        errorBox.textContent = data.error || 'Login failed.';
        return;
    }
    window.location.href = 'index.php';
});
</script>
</body>
</html>