<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Read account and unclaimed guest metadata without credentials or worship content. */
class AdminUserOverviewService
{
    /** @return array{registered_accounts: int, guest_identities: int, total_accounts_and_guest_identities: int} Counts are records, not unique people or visits. */
    public function totals(): array
    {
        $registered = User::query()->count();
        $guests = WorshipIdentity::query()->whereNull('user_id')->count();

        return [
            'registered_accounts' => $registered,
            'guest_identities' => $guests,
            'total_accounts_and_guest_identities' => $registered + $guests,
        ];
    }

    /** @param array{page?: int, per_page?: int} $input Validated pagination; return newest account IDs first. */
    public function users(array $input): LengthAwarePaginator
    {
        return User::query()->select(['id', 'name', 'email', 'account_status', 'created_at'])
            ->orderByDesc('id')->paginate($input['per_page'] ?? 20, ['*'], 'page', $input['page'] ?? 1)
            ->appends($input);
    }

    /** @param array{page?: int, per_page?: int} $input Validated pagination; exclude claimed identities. */
    public function guests(array $input): LengthAwarePaginator
    {
        return WorshipIdentity::query()->select(['id', 'public_id', 'created_at'])
            ->whereNull('user_id')->orderByDesc('id')
            ->paginate($input['per_page'] ?? 20, ['*'], 'page', $input['page'] ?? 1)->appends($input);
    }
}
