<?php

namespace App\Http\Requests;

use App\Models\Dhikr;
use App\PublicationStatus;
use Illuminate\Validation\Rule;

/** Validate religious content as unchanged plain text with explicit publication permission. */
class AdminDhikrRequest extends AdminContentRequest
{
    protected const MODEL = Dhikr::class;

    protected const PARAMETER = 'dhikr';

    /** @return array<string, array<mixed>> Fixed parent and enum filters; arbitrary sorting is unsupported. */
    protected function listRules(): array
    {
        return [...parent::listRules(), 'subcategory_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999999999999', 'exists:subcategories,id'],
            'publication_status' => ['sometimes', 'required', Rule::enum(PublicationStatus::class)]];
    }

    /** @return array<string, array<mixed>> Required source and virtue apply to drafts and published content. */
    protected function writeRules(): array
    {
        return ['title' => $this->textRules(160), 'text' => $this->textRules(10000),
            'description' => $this->textRules(10000), 'source' => $this->textRules(10000), 'virtue' => $this->textRules(10000),
            'explanation' => $this->textRules(10000, nullable: true),
            'subcategory_id' => [...($this->isMethod('PATCH') ? ['sometimes'] : []), 'required', 'integer', 'min:1', 'max:999999999999999999', 'exists:subcategories,id'],
            'repetition_target' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'icon_path' => $this->mediaRules('icons', 'png|jpg|jpeg|webp'),
            'audio_path' => $this->mediaRules('audio', 'mp3|m4a|ogg|wav'),
            'publication_status' => ['sometimes', 'required', Rule::enum(PublicationStatus::class)]];
    }
}
