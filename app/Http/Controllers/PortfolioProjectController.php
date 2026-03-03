<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortfolioProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = PortfolioProject::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    public function show(Request $request, $id)
    {
        $project = PortfolioProject::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'project_url' => 'nullable|url',
            'github_url' => 'nullable|url',
            'client' => 'nullable|string|max:255',
            'completion_date' => 'nullable|string|max:255',
            'technologies' => 'nullable|array',
            'featured' => 'boolean',
            'image' => 'nullable|image|max:2048', // 2MB Max
        ]);

        $entry = new PortfolioProject($data);
        $entry->user_id = $request->user()->id;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('portfolio', 'public');
            $entry->image_url = Storage::url($path);
        }

        $entry->save();

        return response()->json([
            'success' => true,
            'message' => 'Proyecto creado exitosamente',
            'data' => $entry
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $project = PortfolioProject::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'project_url' => 'nullable|url',
            'github_url' => 'nullable|url',
            'client' => 'nullable|string|max:255',
            'completion_date' => 'nullable|string|max:255',
            'technologies' => 'nullable|array',
            'featured' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($project->image_url) {
                $oldPath = str_replace('/storage/', '', $project->image_url);
                Storage::Disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('portfolio', 'public');
            $project->image_url = Storage::url($path);
        }

        $project->fill($data);
        $project->save();

        return response()->json([
            'success' => true,
            'message' => 'Proyecto actualizado',
            'data' => $project
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $project = PortfolioProject::where('user_id', $request->user()->id)->findOrFail($id);

        if ($project->image_url) {
            $oldPath = str_replace('/storage/', '', $project->image_url);
            Storage::Disk('public')->delete($oldPath);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proyecto eliminado'
        ]);
    }
}
