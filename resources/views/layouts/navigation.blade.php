@php
    $currentUser = Auth::user();
    $dashboardRole = $currentUser->role->name;
    $unreadNotificationCount = $currentUser->unreadNotifications()->count();
    $studentHistoryHref = $dashboardRole === 'siswa' && $currentUser->student
        ? route('students.show', $currentUser->student)
        : route('dashboard');
    $guruItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'speedometer2'],
        ['label' => 'Data Siswa', 'route' => 'students.index', 'icon' => 'people'],
        ['label' => 'Pelanggaran', 'route' => 'violations.index', 'icon' => 'exclamation-triangle'],
        ['label' => 'Terlambat', 'route' => 'lateness.index', 'icon' => 'clock-history'],
        ['label' => 'Penghargaan Pagi', 'route' => 'morning-rewards.index', 'icon' => 'sunrise'],
        ['label' => 'Poin Tambahan', 'route' => 'additional-points.index', 'icon' => 'plus-circle'],
        ['label' => 'Jadwal Piket', 'route' => 'duty-schedules.index', 'icon' => 'calendar-week'],
        ['label' => 'Absensi Piket', 'route' => 'duty-attendances.index', 'icon' => 'clipboard-check'],
        ['label' => 'Keberatan', 'route' => 'objections.index', 'icon' => 'chat-left-text'],
        ['label' => 'Laporan', 'route' => 'reports.index', 'icon' => 'file-earmark-bar-graph'],
        ['label' => 'Activity Log', 'route' => 'activity-logs.index', 'icon' => 'journal-text'],
        ['label' => 'Kategori Poin', 'route' => 'categories.index', 'icon' => 'tags'],
        ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'bell'],
    ];
    $operasionalItems = [
        ['label' => 'Dashboard Operasional', 'route' => 'dashboard', 'icon' => 'speedometer2'],
        ['label' => 'Data Siswa', 'route' => 'students.index', 'icon' => 'people'],
        ['label' => 'Pelanggaran', 'route' => 'violations.index', 'icon' => 'exclamation-triangle'],
        ['label' => 'Terlambat', 'route' => 'lateness.index', 'icon' => 'clock-history'],
        ['label' => 'Penghargaan Pagi', 'route' => 'morning-rewards.index', 'icon' => 'sunrise'],
        ['label' => 'Poin Tambahan', 'route' => 'additional-points.index', 'icon' => 'plus-circle'],
        ['label' => 'Jadwal Piket', 'route' => 'duty-schedules.index', 'icon' => 'calendar-week'],
        ['label' => 'Absensi Piket', 'route' => 'duty-attendances.index', 'icon' => 'clipboard-check'],
        ['label' => 'Keberatan', 'route' => 'objections.index', 'icon' => 'chat-left-text'],
        ['label' => 'Laporan', 'route' => 'reports.index', 'icon' => 'file-earmark-bar-graph'],
        ['label' => 'Kategori Poin', 'route' => 'categories.index', 'icon' => 'tags'],
        ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'bell'],
    ];
    $siswaItems = [
        ['label' => 'Dashboard Pribadi', 'route' => 'dashboard', 'icon' => 'house'],
        ['label' => 'Riwayat Poin', 'href' => $studentHistoryHref, 'icon' => 'clock-history'],
        ['label' => 'Kategori Poin', 'route' => 'categories.catalog', 'icon' => 'tags'],
        ['label' => 'Notifikasi', 'route' => 'notifications.index', 'icon' => 'bell'],
        ['label' => 'Pengajuan Keberatan', 'route' => 'objections.create', 'icon' => 'chat-left-text'],
    ];
    $items = match ($dashboardRole) {
        'guru' => $guruItems,
        'operasional' => $operasionalItems,
        'siswa' => $siswaItems,
        default => [],
    };
@endphp

<nav class="navbar navbar-dark sijaga-topbar sticky-top">
    <div class="container-fluid">
        <button class="btn btn-sm sijaga-menu-button d-lg-none me-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Buka menu"><i class="bi bi-list me-1" aria-hidden="true"></i>Menu</button>
        <a class="navbar-brand fw-bold me-auto" href="{{ route('dashboard') }}">
            @if (file_exists(public_path('images/logo-smk20.png')))
                <img class="brand-logo me-2" src="{{ asset('images/logo-smk20.png') }}" alt="Logo SMKN 20 Jakarta" width="32" height="32">
            @endif
            SIJAGA 20
        </a>
        <div class="d-flex align-items-center gap-2 sijaga-nav-actions">
            <a class="btn btn-sm btn-outline-light position-relative" href="{{ route('notifications.index') }}" aria-label="Notifikasi">
                <i class="bi bi-bell me-1" aria-hidden="true"></i>Notifikasi
                @if ($unreadNotificationCount)
                    <span class="badge rounded-pill bg-danger ms-1">{{ $unreadNotificationCount }}</span>
                @endif
            </a>
            <span class="sijaga-nav-user d-flex flex-column text-white small lh-sm">
                <span class="fw-bold sijaga-nav-user-name sidebar-text">{{ $currentUser->name }}</span>
                <span class="text-white-50 sidebar-text">{{ ucfirst($dashboardRole) }}</span>
            </span>
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#logoutConfirmationModal">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Keluar
            </button>
        </div>
    </div>
</nav>

<aside class="sijaga-sidebar d-none d-lg-flex flex-column">
    <div class="sijaga-sidebar-heading d-flex align-items-center gap-2 px-2 pt-3 pb-2">
        <button class="btn btn-sm sijaga-menu-button flex-shrink-0" type="button" data-sidebar-collapse-toggle
            aria-label="Ciutkan atau lebarkan menu utama" aria-pressed="false">
            <i class="bi bi-list" aria-hidden="true"></i><span class="sijaga-sidebar-toggle-label ms-1">Menu</span>
        </button>
        <span class="sijaga-sidebar-title text-uppercase text-secondary small fw-bold">Menu utama</span>
    </div>
    @include('layouts.partials.role-navigation', ['items' => $items])
    <div class="mt-auto border-top p-3">
        <a class="btn btn-outline-secondary w-100 sijaga-profile-link" href="{{ route('profile.edit') }}"
            aria-label="Pengaturan profil" data-sidebar-tooltip="Pengaturan profil">
            <i class="bi bi-person-gear me-2" aria-hidden="true"></i><span class="sidebar-text">Pengaturan profil</span>
        </a>
    </div>
</aside>

<div class="offcanvas offcanvas-start sijaga-mobile-sidebar" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarTitle">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title fs-5 fw-bold" id="mobileSidebarTitle">SIJAGA 20</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="px-3 pt-4 pb-2 text-uppercase text-secondary small fw-bold">Menu utama</div>
        @include('layouts.partials.role-navigation', ['items' => $items])
        <div class="mt-auto border-top p-3">
            <a class="btn btn-outline-secondary w-100" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>Pengaturan profil</a>
        </div>
    </div>
</div>
