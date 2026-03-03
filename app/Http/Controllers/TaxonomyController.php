<?php

namespace App\Http\Controllers;

use App\Models\ProjectCategory;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;

class TaxonomyController extends Controller
{
    public function skills(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Skill::orderBy('name')->get(),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ProjectCategory::orderBy('name')->get(),
        ]);
    }
}
