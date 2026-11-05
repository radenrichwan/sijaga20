<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 fw-bold mb-0">Notifikasi</h1>
                <p class="text-secondary mb-0">{{ $unreadCount }} belum dibaca</p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-primary" type="submit">Tandai semua dibaca</button>
                </form>
            @endif
        </div>
    </x-slot>

    <section class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <article class="list-group-item p-3 p-lg-4 {{ $notification->read_at ? '' : 'list-group-item-primary' }}">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            @if (data_get($notification->data, 'title'))
                                <p class="fw-semibold mb-1">{{ data_get($notification->data, 'title') }}</p>
                            @endif
                            <p class="mb-1">{{ data_get($notification->data, 'message', 'Anda memiliki notifikasi baru.') }}</p>
                            <time class="small text-secondary" datetime="{{ $notification->created_at->toIso8601String() }}">
                                {{ $notification->created_at->format('d/m/Y H:i') }}
                            </time>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-md-auto">
                            @if (data_get($notification->data, 'url'))
                                <a class="btn btn-sm btn-outline-secondary" href="{{ data_get($notification->data, 'url') }}">Buka</a>
                            @endif
                            @if (!$notification->read_at)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Tandai dibaca</button>
                                </form>
                            @else
                                <span class="badge text-bg-light">Sudah dibaca</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-5 text-center text-secondary">Belum ada notifikasi.</div>
            @endforelse
        </div>
        <div class="card-body border-top">{{ $notifications->links() }}</div>
    </section>
</x-app-layout>
