<?php require_once __DIR__ . '/auth_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Productivity Tracker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= htmlspecialchars(
    asset_path('/css/style.css'),
) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(
    asset_path('/css/auth.css'),
) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <div class="container auth-layout">
        <section class="card card-featured auth-story">
            <div class="auth-story__title">
                <h1 class="text-h1">Build a cleaner system for your day.</h1>
                <p class="text-muted">Create an account to track focused work, review your day visually, and keep activities organized under one personal timeline.</p>
            </div>
            <div class="auth-story__list">
                <div class="auth-story__item">
                    <span class="auth-story__item-title">Daily structure first</span>
                    <p class="text-muted">Log entries against real wake and sleep windows so the overview stays meaningful.</p>
                </div>
                <div class="auth-story__item">
                    <span class="auth-story__item-title">One account, one history</span>
                    <p class="text-muted">Your activities, reports, and tracking data stay scoped to your own session.</p>
                </div>
            </div>
        </section>

        <section class="card auth-form-card">
            <div class="auth-form-card__header">
                <h2 class="text-h2">Create account</h2>
                <p class="text-muted">Set up your identity and start logging.</p>
            </div>

            <p id="register-error" class="alert alert-danger auth-error-summary" hidden></p>

            <form id="register-form" class="auth-form" novalidate>
                <div class="auth-field">
                    <label class="form-label" for="register-username">Username</label>
                    <input id="register-username" name="username" class="input" type="text" autocomplete="username" placeholder="How should we address you?">
                    <p id="register-username-error" class="form-error" hidden></p>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="register-email">Email</label>
                    <input id="register-email" name="email" class="input" type="text" inputmode="email" autocomplete="email" placeholder="name@example.com">
                    <p id="register-email-error" class="form-error" hidden></p>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="register-password">Password</label>
                    <div class="auth-field__control">
                        <input id="register-password" name="password" class="input" type="password" autocomplete="new-password" placeholder="At least 8 characters">
                        <button type="button" class="auth-toggle" data-toggle-password="register-password">Show</button>
                    </div>
                    <p id="register-password-error" class="form-error" hidden></p>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="register-confirm-password">Confirm password</label>
                    <div class="auth-field__control">
                        <input id="register-confirm-password" name="confirm_password" class="input" type="password" autocomplete="new-password" placeholder="Repeat your password">
                        <button type="button" class="auth-toggle" data-toggle-password="register-confirm-password">Show</button>
                    </div>
                    <p id="register-confirm-password-error" class="form-error" hidden></p>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="btn btn-primary btn-lg auth-submit">Create Account</button>
                    <div class="auth-support">
                        <span>Already have an account?</span>
                        <a href="/login.php">Sign in instead</a>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                Usernames must be between 2 and 100 characters, and passwords must be at least 8 characters long.
            </div>
        </section>
    </div>
</main>

<script>
async function readJsonSafely(response) {
    const contentType = response.headers.get('content-type') || '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch {
        return null;
    }
}

function setFieldError(field, message) {
    const error = document.getElementById(`${field.id}-error`);
    field.classList.add('input-error');
    field.setAttribute('aria-invalid', 'true');
    if (error) {
        error.textContent = message;
        error.hidden = false;
    }
}

function clearFieldError(field) {
    const error = document.getElementById(`${field.id}-error`);
    field.classList.remove('input-error');
    field.removeAttribute('aria-invalid');
    if (error) {
        error.textContent = '';
        error.hidden = true;
    }
}

function showSummary(message) {
    const errorBox = document.querySelector('#register-error');
    errorBox.textContent = message;
    errorBox.hidden = false;
}

function clearSummary() {
    const errorBox = document.querySelector('#register-error');
    errorBox.textContent = '';
    errorBox.hidden = true;
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

document.querySelector('#register-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const submitButton = event.currentTarget.querySelector('button[type="submit"]');
    const usernameField = document.querySelector('#register-username');
    const emailField = document.querySelector('#register-email');
    const passwordField = document.querySelector('#register-password');
    const confirmPasswordField = document.querySelector('#register-confirm-password');
    const username = usernameField.value.trim();
    const email = emailField.value.trim();
    const password = passwordField.value;
    const confirmPassword = confirmPasswordField.value;

    clearSummary();
    [usernameField, emailField, passwordField, confirmPasswordField].forEach(clearFieldError);

    let hasError = false;

    if (username.length < 2 || username.length > 100) {
        setFieldError(usernameField, 'Username must be between 2 and 100 characters.');
        hasError = true;
    }

    if (email === '') {
        setFieldError(emailField, 'Email is required.');
        hasError = true;
    } else if (!isValidEmail(email)) {
        setFieldError(emailField, 'Enter a valid email address.');
        hasError = true;
    }

    if (password.length < 8) {
        setFieldError(passwordField, 'Password must be at least 8 characters.');
        hasError = true;
    }

    if (confirmPassword === '') {
        setFieldError(confirmPasswordField, 'Confirm your password.');
        hasError = true;
    } else if (password !== confirmPassword) {
        setFieldError(confirmPasswordField, 'Passwords do not match.');
        hasError = true;
    }

    if (hasError) {
        showSummary('Fix the highlighted fields and try again.');
        return;
    }

    submitButton.disabled = true;

    try {
        const response = await fetch('/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ username, email, password }),
        });

        const data = await readJsonSafely(response);

        if (!response.ok) {
            showSummary(data?.error || 'Register failed. Please try again.');
            return;
        }

        window.location.href = '/login.php';
    } catch (error) {
        showSummary('Unable to create your account right now. Please try again.');
    } finally {
        submitButton.disabled = false;
    }
});

document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);

        if (!input) {
            return;
        }

        const nextType = input.type === 'password' ? 'text' : 'password';
        input.type = nextType;
        button.textContent = nextType === 'password' ? 'Show' : 'Hide';
    });
});
</script>
</body>
</html>
