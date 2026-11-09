<x-guest-layout>
    <section class="card sijaga-login-card rounded-4 border-0 shadow-lg">
        <div class="card-body p-4 p-sm-5">
            <div class="sijaga-login-brand text-center mb-4">
                <span class="sijaga-login-mark"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></span>
                <h1 class="h3 fw-bold mt-3 mb-1">SIJAGA 20</h1>
                <p class="text-secondary mb-0">Sistem Informasi Jaga Gerbang &amp; Kedisiplinan Siswa</p>
            </div>

            <x-auth-session-status class="alert alert-success mb-3" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="sijaga-login-form">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="username">Nama pengguna</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text sijaga-login-input-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                        <input id="username" class="form-control form-control-lg" type="text" name="username"
                            value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="Masukkan nama pengguna">
                    </div>
                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="password">Kata sandi</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text sijaga-login-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                        <input id="password" class="form-control form-control-lg" type="password" name="password"
                            required autocomplete="current-password" placeholder="Masukkan kata sandi">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password aria-label="Tampilkan kata sandi"
                            aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <label for="remember_me" class="form-check mb-0">
                        <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                        <span class="form-check-label">Ingat saya</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="small text-decoration-none" href="{{ route('password.request') }}">Lupa kata sandi?</a>
                    @endif
                </div>

                <button class="btn btn-primary btn-lg w-100 fw-bold" type="submit">
                    <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Masuk
                </button>
            </form>
        </div>
    </section>

    <aside class="sijaga-demo-card mt-3" aria-label="Kredensial akun demo">
        <h2 class="h6 fw-bold mb-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Akun demo</h2>
        <div class="small d-grid gap-1">
            <span>Guru: <code>guru0001</code> / <code>guru0001</code></span>
            <span>Operasional: <code>piket001</code> / <code>piket001</code></span>
            <span>Siswa: <code>12345</code> / <code>12345</code></span>
        </div>
    </aside>

    <script>
        document.querySelector('[data-toggle-password]')?.addEventListener('click', (event) => {
            const button = event.currentTarget;
            const password = document.getElementById('password');
            const icon = button.querySelector('i');
            const visible = password.type === 'password';

            password.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            icon.classList.toggle('bi-eye', !visible);
            icon.classList.toggle('bi-eye-slash', visible);
        });
    </script>
</x-guest-layout>
