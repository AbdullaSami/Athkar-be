<?php

namespace App\Http\Requests;

use App\Models\Subcategory;

/** Validate subcategory metadata and an existing parent category. */
class AdminSubcategoryRequest extends AdminCategoryRequest
{
    protected const MODEL = Subcategory::class;

    protected const PARAMETER = 'subcategory';

    /** @return array<string, array<mixed>> Add an optional fixed parent filter to bounded pagination. */
    protected function listRules(): array
    {
        return [...parent::listRules(), 'category_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999999999999', 'exists:categories,id']];
    }

    /** @return array<string, array<mixed>> Parent association is mandatory on POST/PUT and explicit on PATCH. */
    protected function writeRules(): array
    {
        return [...parent::writeRules(), 'category_id' => [...($this->isMethod('PATCH') ? ['sometimes'] : []), 'required', 'integer', 'min:1', 'max:999999999999999999', 'exists:categories,id']];
    }
}
