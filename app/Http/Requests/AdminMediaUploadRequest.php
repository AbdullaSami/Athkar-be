<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Models\Category;
use Illuminate\Support\Facades\Gate;

/** Validate multipart media using detected content, bounded size and allowed filename extensions. */
class AdminMediaUploadRequest extends StrictAuthRequest
{
    /** Uploading assets requires the existing dashboard/editor policy; attaching live content is checked separately. */
    public function authorize(): bool
    {
        return Gate::allows('create', Category::class);
    }

    /** @return array<string, array<string>> Only one file is accepted; clients cannot select paths or disk names. */
    public function rules(): array
    {
        return ['file' => $this->route()->getActionMethod() === 'icon'
            ? ['bail', 'required', 'file', 'max:2048', 'image', 'mimetypes:image/png,image/jpeg,image/webp', 'extensions:png,jpg,jpeg,webp', 'dimensions:min_width=1,min_height=1,max_width=1024,max_height=1024']
            : ['bail', 'required', 'file', 'max:20480', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/ogg,audio/wav,audio/x-wav,audio/vnd.wave', 'extensions:mp3,m4a,ogg,wav']];
    }
}
