<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AllahName;
use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
class CacheDataController extends Controller
{
    public function getAllData()
    {
        $data = [
            'categories' => Category::with('subcategories', 'subcategories.dhikrs')->get(),
            'allah_names' => AllahName::all(),
        ];

        return response()->json($data);
    }
}
