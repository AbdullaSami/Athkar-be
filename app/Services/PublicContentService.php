<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Read application-owned content in stable order without exposing unpublished Dhikr. */
class PublicContentService
{
    /** Return a bounded category page ordered by configured position then ID. */
    public function categories(int $perPage): LengthAwarePaginator
    {
        return Category::query()->orderBy('sort_order')->orderBy('id')->paginate($perPage)->appends(['per_page' => $perPage]);
    }

    /** Return only the requested category's children with published counts. */
    public function subcategories(Category $category, int $perPage): LengthAwarePaginator
    {
        return $category->subcategories()->withCount(['dhikrs' => fn (Builder $query) => $query->published()])
            ->orderBy('sort_order')->orderBy('id')->paginate($perPage)->appends(['per_page' => $perPage]);
    }

    /** Return a subcategory with a published-only content count. */
    public function subcategory(Subcategory $subcategory): Subcategory
    {
        return $subcategory->loadCount(['dhikrs' => fn (Builder $query) => $query->published()]);
    }

    /** Return one subcategory's published content; counters remain client-local. */
    public function dhikrs(Subcategory $subcategory, int $perPage): LengthAwarePaginator
    {
        return $subcategory->dhikrs()->published()->orderBy('sort_order')->orderBy('id')
            ->paginate($perPage)->appends(['per_page' => $perPage]);
    }

    /**
     * @return array{dhikr: Dhikr, navigation: array{previous_dhikr_id: ?int, next_dhikr_id: ?int}}
     *                                                                                              Resolve published content before calculating neighbors within its own subcategory.
     */
    public function dhikr(string $id): array
    {
        $dhikr = Dhikr::query()->published()->findOrFail($id);

        return ['dhikr' => $dhikr, 'navigation' => [
            'previous_dhikr_id' => $this->neighbor($dhikr, previous: true),
            'next_dhikr_id' => $this->neighbor($dhikr),
        ]];
    }

    /** Find one published neighbor using the same position/ID order as the list. */
    private function neighbor(Dhikr $dhikr, bool $previous = false): ?int
    {
        $operator = $previous ? '<' : '>';
        $direction = $previous ? 'desc' : 'asc';
        $id = Dhikr::query()->published()->where('subcategory_id', $dhikr->subcategory_id)
            ->where(function (Builder $query) use ($dhikr, $operator): void {
                $query->where('sort_order', $operator, $dhikr->sort_order)
                    ->orWhere(function (Builder $query) use ($dhikr, $operator): void {
                        $query->where('sort_order', $dhikr->sort_order)->where('id', $operator, $dhikr->id);
                    });
            })->orderBy('sort_order', $direction)->orderBy('id', $direction)->value('id');

        return $id === null ? null : (int) $id;
    }
}
