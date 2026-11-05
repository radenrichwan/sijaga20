<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'SIJAGA 20'))</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="sijaga-body">
        @auth
            @include('layouts.navigation')
        @endauth

        <div class="sijaga-page">
            @isset($header)
                <header class="sijaga-page-header">
                    <div class="container-fluid py-3 py-lg-4">{{ $header }}</div>
                </header>
            @endisset

            <main class="container-fluid sijaga-main py-3 py-lg-4">
                @if (session('status'))
                    <div class="toast-container position-fixed top-0 end-0 p-3" aria-live="polite" aria-atomic="true">
                        <div class="toast text-bg-success border-0" role="status" data-sijaga-toast>
                            <div class="d-flex">
                                <div class="toast-body">{{ session('status') }}</div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Periksa kembali data yang dimasukkan.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <div class="modal fade" id="appModal" tabindex="-1" aria-labelledby="appModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="appModalTitle">Konfirmasi</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body" id="appModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        @auth
            <div class="modal fade" id="logoutConfirmationModal" tabindex="-1" aria-labelledby="logoutConfirmationTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="logoutConfirmationTitle">Konfirmasi Keluar</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">Apakah Anda yakin ingin keluar dari akun?</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="btn btn-danger" type="submit">Ya, Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endauth
    </body>
</html>
