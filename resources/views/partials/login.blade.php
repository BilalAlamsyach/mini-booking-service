<section class="card card-narrow" id="login-card">
    <h1 class="card-title">Masuk</h1>
    <p class="card-hint">Gunakan salah satu akun yang sudah disediakan.</p>

    <div class="alert" id="login-alert" hidden></div>

    <form id="login-form" novalidate>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" placeholder="nama@example.com" required>
            <span class="field-error" id="email-error"></span>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
            <span class="field-error" id="password-error"></span>
        </div>

        <button type="submit" class="btn btn-block" id="login-button">Masuk</button>
    </form>

    <div class="accounts">
        <span class="accounts-label">Akun tersedia</span>
        <button type="button" class="account" data-fill-email="user@example.com" data-fill-password="password">
            <span class="account-name">Mochamad Bilal Alamsyach</span>
            <span class="account-email">user@example.com</span>
        </button>
        <button type="button" class="account" data-fill-email="user2@example.com" data-fill-password="password">
            <span class="account-name">Firmansyach</span>
            <span class="account-email">user2@example.com</span>
        </button>
    </div>
</section>
