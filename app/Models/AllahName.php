<?php

namespace App\Models;

use Database\Factories\AllahNameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Stores application-owned AllahName data; input must be validated by callers. */
#[Fillable(['number', 'arabic_name', 'transliteration', 'meaning', 'language'])]
class AllahName extends Model
{
    /** @use HasFactory<AllahNameFactory> */
    use HasFactory;

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }
}
