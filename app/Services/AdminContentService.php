<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\PublicationStatus;
use App\Rules\PlainContentText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Persist validated editor data while preserving hierarchy and publication authorization. */
class AdminContentService
{
    /** @param class-string<Category|Subcategory|Dhikr> $modelClass
     * @param  array<string, mixed>  $filters  Bounded pagination and fixed parent/status filters only.
     */
    public function list(string $modelClass, array $filters): LengthAwarePaginator
    {
        $query = $modelClass::query();
        foreach (['category_id', 'subcategory_id', 'publication_status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderBy('sort_order')->orderBy('id')->paginate($perPage)->appends($filters);
    }

    /** @param class-string<Category|Subcategory|Dhikr> $modelClass
     * @param  array<string, mixed>  $data  Validated write fields only; relationship IDs are assigned explicitly.
     */
    public function create(string $modelClass, array $data): Model
    {
        return DB::transaction(function () use ($modelClass, $data): Model {
            $content = new $modelClass;
            $this->fill($content, $data);
            $this->validatePublication($content);
            $content->save();

            return $content->fresh();
        });
    }

    /** @param array<string, mixed> $data Recheck publication authority after locking the latest state. */
    public function update(Model $content, array $data): Model
    {
        return DB::transaction(function () use ($content, $data): Model {
            $locked = $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            if (array_key_exists('publication_status', $data)) {
                Gate::authorize('publish', $locked);
            }
            $this->fill($locked, $data);
            $this->validatePublication($locked);
            $locked->save();

            return $locked->fresh();
        });
    }

    /** Delete an authorized leaf record; dependency conflicts return HTTP 409 without database details. */
    public function delete(Model $content): void
    {
        try {
            DB::transaction(function () use ($content): void {
                $locked = $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('delete', $locked);
                if (($locked instanceof Category && $locked->subcategories()->exists())
                    || ($locked instanceof Subcategory && $locked->dhikrs()->exists())) {
                    throw new ConflictHttpException('Remove dependent content first.');
                }
                $locked->delete();
            });
        } catch (QueryException $exception) {
            // Foreign keys remain the final guard against concurrent child creation.
            if (in_array($exception->errorInfo[0] ?? null, ['23000', '23503'], true)) {
                throw new ConflictHttpException('Remove dependent content first.');
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data Associate locked parents before filling explicitly validated attributes. */
    private function fill(Model $content, array $data): void
    {
        if ($content instanceof Subcategory && isset($data['category_id'])) {
            $content->category()->associate(Category::query()->lockForUpdate()->findOrFail($data['category_id']));
            unset($data['category_id']);
        }
        if ($content instanceof Dhikr && isset($data['subcategory_id'])) {
            $content->subcategory()->associate(Subcategory::query()->lockForUpdate()->findOrFail($data['subcategory_id']));
            unset($data['subcategory_id']);
        }
        $content->fill($data);
    }

    /** Validate the merged record, including legacy drafts, before making or keeping Dhikr public. */
    private function validatePublication(Model $content): void
    {
        if ($content instanceof Dhikr && $content->publication_status === PublicationStatus::Published) {
            Validator::make($content->only(['title', 'text', 'description', 'source', 'virtue']), [
                'title' => ['required', 'string', 'max:160', new PlainContentText],
                'text' => ['required', 'string', 'max:10000', new PlainContentText],
                'description' => ['required', 'string', 'max:10000', new PlainContentText],
                'source' => ['required', 'string', 'max:10000', new PlainContentText],
                'virtue' => ['required', 'string', 'max:10000', new PlainContentText],
            ])->validate();
        }
    }
}
