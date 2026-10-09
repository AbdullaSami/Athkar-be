<?php

namespace App\Policies;

use App\AccountStatus;
use App\Models\Dhikr;
use App\Models\User;
use App\PublicationStatus;
use Illuminate\Database\Eloquent\Model;

/** Require explicit content permissions in addition to dashboard authentication. */
class ContentPolicy
{
    /** Deny suspended accounts and accounts lacking dashboard or content access. */
    public function viewAny(User $user): bool
    {
        return $user->account_status === AccountStatus::Active && $user->can('access-dashboard') && $user->can('manage-content');
    }

    /** Permit authorized editors to read drafts and published records. */
    public function view(User $user, Model $content): bool
    {
        return $this->viewAny($user);
    }

    /** Permit authorized editors to create drafts. */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Editing live Dhikr is publication and requires publisher permission. */
    public function update(User $user, Model $content): bool
    {
        return $this->viewAny($user) && (! $content instanceof Dhikr || $content->publication_status !== PublicationStatus::Published || $this->publish($user));
    }

    /** Removing live Dhikr also requires publication authority. */
    public function delete(User $user, Model $content): bool
    {
        return $this->update($user, $content);
    }

    /** Allow explicit publication-state changes only for authorized publishers. */
    public function publish(User $user): bool
    {
        return $this->viewAny($user) && $user->can('publish-content');
    }
}
