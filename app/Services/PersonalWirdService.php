<?php

namespace App\Services;

use App\Models\PersonalWird;
use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Access Wird exclusively through its owner and serialize counter changes. */
class PersonalWirdService
{
    /** Return a bounded page containing only this owner's items. */
    public function list(User|WorshipIdentity $owner, int $perPage): LengthAwarePaginator
    {
        return $this->ownedQuery($owner)->orderByDesc('id')->paginate($perPage);
    }

    /** @param array{text: string, target_count: int|string} $data Create an owned item with a zero counter. */
    public function create(User|WorshipIdentity $owner, array $data): PersonalWird
    {
        return DB::transaction(function () use ($owner, $data): PersonalWird {
            $identity = $owner;
            if ($owner instanceof User) {
                User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                $identity = $owner->worshipIdentities()->orderBy('id')->first();
                if (! $identity) {
                    $identity = $owner->worshipIdentities()->make();
                    $identity->public_id = (string) Str::uuid();
                    $identity->save();
                }
            }

            return $identity->personalWirds()->create(['text' => $data['text'], 'target_count' => $data['target_count'], 'current_count' => 0])->fresh();
        });
    }

    /** Return an owned item or HTTP 404, including when another owner holds the ID. */
    public function find(User|WorshipIdentity $owner, string $id): PersonalWird
    {
        return $this->ownedQuery($owner)->whereKey($id)->firstOrFail();
    }

    /** @param array{text?: string, target_count?: int|string} $data Update only validated content fields. */
    public function update(User|WorshipIdentity $owner, string $id, array $data): PersonalWird
    {
        return DB::transaction(function () use ($owner, $id, $data): PersonalWird {
            $wird = $this->ownedQuery($owner)->whereKey($id)->lockForUpdate()->firstOrFail();
            $wird->update($data);

            return $wird->fresh();
        });
    }

    /** Increment by exactly one or reset to zero; targets never cap a personal Wird counter. */
    public function count(User|WorshipIdentity $owner, string $id, bool $reset = false): PersonalWird
    {
        return DB::transaction(function () use ($owner, $id, $reset): PersonalWird {
            $wird = $this->ownedQuery($owner)->whereKey($id)->lockForUpdate()->firstOrFail();
            if (! $reset && $wird->current_count >= PHP_INT_MAX) {
                throw new ConflictHttpException('Counter limit reached.');
            }
            $wird->current_count = $reset ? 0 : $wird->current_count + 1;
            $wird->save();

            return $wird;
        });
    }

    /** Delete an owned item without revealing other owners' items. */
    public function delete(User|WorshipIdentity $owner, string $id): void
    {
        $this->find($owner, $id)->delete();
    }

    /** @return Builder<PersonalWird> Build a bound ownership filter before resolving any item ID. */
    private function ownedQuery(User|WorshipIdentity $owner): Builder
    {
        $query = PersonalWird::query();

        return $owner instanceof User
            ? $query->whereIn('worship_identity_id', $owner->worshipIdentities()->select('id'))
            : $query->where('worship_identity_id', $owner->id);
    }
}
