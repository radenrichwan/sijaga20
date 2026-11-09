<nav class="nav flex-column px-2" aria-label="Navigasi {{ $currentUser->role->name }}">
    @foreach ($items as $item)
        @php
            $disabled = $item['disabled'] ?? false;
            $href = $item['href'] ?? (isset($item['route']) && Route::has($item['route']) ? route($item['route']) : '#');
            $active = isset($item['route']) && request()->routeIs($item['route'], $item['route'].'.*');
        @endphp
        @if ($disabled)
            <span class="nav-link sijaga-nav-link disabled" aria-disabled="true"
                aria-label="{{ $item['label'] }}" data-sidebar-tooltip="{{ $item['label'] }}">
                <i class="bi bi-{{ $item['icon'] ?? 'circle' }} sijaga-nav-icon me-2" aria-hidden="true"></i>
                <span class="sidebar-text">{{ $item['label'] }}</span> <span class="ms-auto small text-secondary sidebar-text">Segera</span>
            </span>
        @else
            <a class="nav-link sijaga-nav-link {{ $active ? 'active' : '' }}" href="{{ $href }}"
                aria-label="{{ $item['label'] }}" data-sidebar-tooltip="{{ $item['label'] }}"
                @if (str_contains($href, '#')) data-bs-dismiss="offcanvas" @endif>
                <i class="bi bi-{{ $item['icon'] ?? 'circle' }} sijaga-nav-icon me-2" aria-hidden="true"></i><span class="sidebar-text">{{ $item['label'] }}</span>
            </a>
        @endif
    @endforeach
</nav>
