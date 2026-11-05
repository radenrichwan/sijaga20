<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::query()->orderBy('type')->orderBy('name')->paginate(15),
        ]);
    }

    public function catalog(): View
    {
        return view('categories.catalog', [
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('categories.index');
    }

    public function store(StoreCategoryRequest $request, ActivityLogService $activityLogs): RedirectResponse
    {
        DB::transaction(function () use ($request, $activityLogs): void {
            $category = Category::query()->create($request->validated());
            $activityLogs->record($request->user(), 'create', 'category', $category, null, $category->only([
                'name', 'type', 'points', 'description',
            ]));
        });

        return to_route('categories.index')->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function show(Category $category): RedirectResponse
    {
        return to_route('categories.index');
    }

    public function edit(Category $category): RedirectResponse
    {
        return to_route('categories.index');
    }

    public function update(StoreCategoryRequest $request, Category $category, ActivityLogService $activityLogs): RedirectResponse
    {
        DB::transaction(function () use ($request, $category, $activityLogs): void {
            $oldValues = $category->only(['name', 'type', 'points', 'description']);
            $category->update($request->safe()->only(['name', 'type', 'points', 'description']));
            $activityLogs->record(
                $request->user(),
                'update',
                'category',
                $category,
                $oldValues,
                $category->only(['name', 'type', 'points', 'description']),
            );
        });

        return to_route('categories.index')->with('status', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category, ActivityLogService $activityLogs): RedirectResponse
    {
        if ($category->pointLedgers()->exists()) {
            return to_route('categories.index')
                ->withErrors(['category' => 'Kategori yang sudah digunakan pada ledger tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($category, $activityLogs): void {
            $oldValues = $category->only(['name', 'type', 'points', 'description']);
            $activityLogs->record(request()->user(), 'delete', 'category', $category, $oldValues);
            $category->delete();
        });

        return to_route('categories.index')->with('status', 'Kategori berhasil dihapus.');
    }
}
