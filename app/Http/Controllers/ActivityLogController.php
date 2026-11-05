<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isActive() && $request->user()->isGuru(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'in:create,update,delete,approve,reject'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        return view('activity-logs.index', [
            'filters' => $filters,
            'logs' => ActivityLog::query()
                ->with('user')
                ->when(isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
                ->when(isset($filters['action']), fn (Builder $query) => $query->where('action', $filters['action']))
                ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $filters['date_from']))
                ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $filters['date_to']))
                ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                    $search = trim($filters['search']);
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('category', 'like', "%{$search}%")
                            ->orWhere('subject_id', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(metadata AS TEXT) LIKE ?', ["%{$search}%"])
                            ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%"));
                    });
                })
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }
}
