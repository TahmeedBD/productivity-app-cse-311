    </main><!-- /#main-content -->
    <script src="<?= htmlspecialchars(
        asset_path('/js/app/shared.js'),
    ) ?>"></script>
    <script src="<?= htmlspecialchars(
        asset_path('/js/app/time-log.js'),
    ) ?>"></script>
    <script src="<?= htmlspecialchars(
        asset_path('/js/app/reports.js'),
    ) ?>"></script>
    <script src="<?= htmlspecialchars(
        asset_path('/js/app/reports-dashboard.js'),
    ) ?>"></script>
    <script src="<?= htmlspecialchars(
        asset_path('/js/app/dashboard.js'),
    ) ?>"></script>
    <?php if (isset($currentUser)): ?>
        <script>
        document.querySelector('#nav-logout-button')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const csrfToken = button.dataset.csrfToken || '';

            button.disabled = true;

            try {
                const response = await fetch('/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                });
                const contentType = response.headers.get('content-type') || '';
                const data = contentType.includes('application/json')
                    ? await response.json()
                    : null;

                if (!response.ok) {
                    throw new Error(data?.error || 'Unable to sign out right now.');
                }

                window.location.href = '/login.php';
            } catch (error) {
                window.alert(
                    error instanceof Error
                        ? error.message
                        : 'Unable to sign out right now.',
                );
                button.disabled = false;
            }
        });
        </script>
    <?php endif; ?>
</body>
</html>
