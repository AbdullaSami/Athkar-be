<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\Models\User;
use App\PublicationStatus;

/** Aggregate current application records without collecting visitor or worship history. */
class AdminAnalyticsService
{
    /** Reuse the existing definition of registered accounts and unclaimed guest identities. */
    public function __construct(private AdminUserOverviewService $overview) {}

    /** @return array<string, mixed> Current inventory counts; active means account status, never recent usage. */
    public function snapshot(): array
    {
        $accounts = User::query()->toBase()->select('account_status')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('account_status')->pluck('aggregate', 'account_status');
        $dhikrs = Dhikr::query()->toBase()->select('publication_status')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('publication_status')->pluck('aggregate', 'publication_status');
        $draft = (int) $dhikrs->get(PublicationStatus::Draft->value, 0);
        $published = (int) $dhikrs->get(PublicationStatus::Published->value, 0);

        return [
            'generated_at' => now()->toIso8601String(),
            'scope' => 'current_records',
            'accounts' => [
                ...$this->overview->totals(),
                'active_accounts' => (int) $accounts->get(AccountStatus::Active->value, 0),
                'suspended_accounts' => (int) $accounts->get(AccountStatus::Suspended->value, 0),
            ],
            'content' => [
                'categories' => Category::query()->count(),
                'subcategories' => Subcategory::query()->count(),
                'dhikrs' => ['total' => $draft + $published, 'draft' => $draft, 'published' => $published],
            ],
        ];
    }
}
