<?php

namespace App\Http\Requests;

use App\Models\Category;

/** Validate editable category metadata and authorize its bound record. */
class AdminCategoryRequest extends AdminContentRequest
{
    protected const MODEL = Category::class;

    protected const PARAMETER = 'category';

    /** @return array<string, array<mixed>> Category fields; parent relationships cannot be injected. */
    protected function writeRules(): array
    {
        return ['title' => $this->textRules(160), 'description' => $this->textRules(10000, nullable: true),
            'icon_path' => $this->mediaRules('icons', 'png|jpg|jpeg|webp'),
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000']];
    }
}
