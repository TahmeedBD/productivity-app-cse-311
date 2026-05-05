<?php require_once __DIR__ . '/auth_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Productivity Tracker</title>
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
                <h1 class="text-h1">Return to your day with context.</h1>
                <p class="text-muted">Sign in to jump straight back into your timeline, overview, and activity tracking without losing the structure you already built.</p>
            </div>
            <div class="auth-story__list">
                <div class="auth-story__item">
                    <span class="auth-story__item-title">Track current work instantly</span>
                    <p class="text-muted">Pick up your running entry, review today’s log, and keep the day coherent.</p>
                </div>
                <div class="auth-story__item">
                    <span class="auth-story__item-title">See the full day clearly</span>
                    <p class="text-muted">Use Day Overview to inspect gaps, edit entries, and understand where time actually went.</p>
                </div>
            </div>
        </section>

        <section class="card auth-form-card">
            <div class="auth-form-card__header">
                <h2 class="text-h2">Sign in</h2>
                <p class="text-muted">Use your existing account to continue.</p>
            </div>

            <p id="login-error" class="alert alert-danger auth-error-summary" hidden></p>

            <form id="login-form" class="auth-form" novalidate>
                <div class="auth-field">
                    <label class="form-label" for="login-identifier">Email or username</label>
                    <input id="login-identifier" name="identifier" class="input" type="text" autocomplete="username" placeholder="name@example.com or tahmeed">
                    <p id="login-identifier-error" class="form-error" hidden></p>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="login-password">Password</label>
                    <div class="auth-field__control">
                        <input id="login-password" name="password" class="input" type="password" autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="auth-toggle" data-toggle-password="login-password">Show</button>
                    </div>
                    <p id="login-password-error" class="form-error" hidden></p>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="btn btn-primary btn-lg auth-submit">Sign In</button>
                    <div class="auth-support">
                        <span>Need an account?</span>
                        <a href="/register.php">Create one now</a>
                    </div>
                </div>
            </form>

            <div class="auth-footer">
                Productivity Tracker keeps your daily logging, overview, and activity history scoped to your account.
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
    const errorBox = document.querySelector('#login-error');
    errorBox.textContent = message;
    errorBox.hidden = false;
}

function clearSummary() {
    const errorBox = document.querySelector('#login-error');
    errorBox.textContent = '';
    errorBox.hidden = true;
}

document.querySelector('#login-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const submitButton = event.currentTarget.querySelector('button[type="submit"]');
    const identifierField = document.querySelector('#login-identifier');
    const passwordField = document.querySelector('#login-password');
    const identifier = identifierField.value.trim();
    const password = passwordField.value;

    clearSummary();
    clearFieldError(identifierField);
    clearFieldError(passwordField);

    let hasError = false;

    if (identifier === '') {
        setFieldError(identifierField, 'Email or username is required.');
        hasError = true;
    }

    if (password === '') {
        setFieldError(passwordField, 'Password is required.');
        hasError = true;
    }

    if (hasError) {
        showSummary('Fix the highlighted fields and try again.');
        return;
    }

    submitButton.disabled = true;

    try {
        const response = await fetch('/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ identifier, password }),
        });

        const data = await readJsonSafely(response);

        if (!response.ok) {
            showSummary(data?.error || 'Login failed. Please try again.');
            return;
        }

        window.location.href = '/';
    } catch (error) {
        showSummary('Unable to sign in right now. Please try again.');
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