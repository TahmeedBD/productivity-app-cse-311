<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Productivity Tracker</title>
<style>
body {
    margin: 0;
    padding: 0;
    background-color: #1a1512;
    font-family: system-ui, -apple-system, sans-serif;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    color: #fff;
}
.page-header {
    text-align: center;
    margin-bottom: 30px;
}
.page-header h1 {
    color: #ffb076;
    margin: 0 0 10px 0;
    font-size: 28px;
}
.page-header p {
    color: #a09893;
    font-size: 15px;
}
.register-container {
    background-color: #26211e;
    padding: 40px;
    border-radius: 12px;
    width: 100%;
    max-width: 420px;
    box-sizing: border-box;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #d4cfcc;
    text-transform: uppercase;
}
.input-wrapper {
    position: relative;
}
.form-control {
    width: 100%;
    padding: 12px;
    background-color: #1a1512;
    border: 1px solid #332d29;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    box-sizing: border-box;
}
.form-control:focus {
    outline: none;
    border-color: #ffb076;
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
#register-error {
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

<div class="page-header">
    <h1>Productivity Tracker</h1>
    <p>Create an account to get started.</p>
</div>

<div class="register-container">
    <div id="register-error"></div>
    <form id="register-form">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrapper">
                <input type="password" id="reg-pass" name="password" class="form-control" placeholder="••••••••" required>
                <span class="toggle-password" onclick="togglePass('reg-pass')">👁️</span>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <div class="input-wrapper">
                <input type="password" id="conf-pass" name="confirm_password" class="form-control" placeholder="••••••••" required>
                <span class="toggle-password" onclick="togglePass('conf-pass')">👁️</span>
            </div>
        </div>

        <button type="submit" class="btn-submit">Create Account</button>
    </form>
    <div class="footer">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

document.querySelector('#register-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const errorBox = document.querySelector('#register-error');
    errorBox.textContent = '';

    const username = document.querySelector('[name="username"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const password = document.querySelector('[name="password"]').value;
    const confirm_password = document.querySelector('[name="confirm_password"]').value;

    if (password !== confirm_password) {
        errorBox.textContent = 'Passwords do not match.';
        return;
    }

    const response = await fetch('auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ username, email, password }),
    });

    const data = await response.json();
    if (!response.ok) {
        errorBox.textContent = data.error || 'Register failed.';
        return;
    }
    window.location.href = 'login.php';
});
</script>
</body>
</html>