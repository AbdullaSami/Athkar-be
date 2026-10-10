<?php

namespace App\Services;

use App\Models\AllahName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Read the locally seeded reference without making external API calls. */
class AllahNameService
{
    /** Return a stable, bounded page of names in the validated meaning language. */
    public function list(string $language, int $perPage): LengthAwarePaginator
    {
        return AllahName::query()->where('language', $language)->whereBetween('number', [1, 99])
            ->orderBy('number')->paginate($perPage)->appends(['language' => $language, 'per_page' => $perPage]);
    }

    /** Resolve the religious reference number rather than an internal database ID. */
    public function find(int $number, string $language): AllahName
    {
        return AllahName::query()->where('number', $number)->where('language', $language)->firstOrFail();
    }
}
