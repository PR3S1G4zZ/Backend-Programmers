<?php

namespace App\Http\Controllers;

use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Store a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:project_categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7', // Hex color
            'icon' => 'nullable|string|max:50', // Icon name (e.g. Lucide)
        ]);

        $category = ProjectCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada exitosamente.',
            'category' => $category
        ], 201);
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $category = ProjectCategory::find($id);

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:project_categories,name,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente.',
            'category' => $category
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy($id): JsonResponse
    {
        $category = ProjectCategory::find($id);

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada.'], 404);
        }

        // Check if category is used? Maybe later. For now just delete.
        // If soft deletes used, use forceDelete or delete.
        
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente.'
        ]);
    }
}
