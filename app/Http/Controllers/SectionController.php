<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSection;
use App\Http\Requests\Section\StoreSectionRequest;
use App\Http\Requests\Section\UpdateSectionRequest;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Display a listing of sections for a project.
     */
    public function index(Project $project)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isAccepted = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if (!$isAdmin && $project->visibility === 'private' && !$isAccepted) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($project->sections);
    }

    /**
     * Store a newly created section in a specific project.
     */
    public function store(StoreSectionRequest $request, Project $project)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if (!$isProjectAdmin) {
            return response()->json(['message' => 'Unauthorized. Project Admin access required.'], 403);
        }

        $section = $project->sections()->create($request->validated());

        return response()->json($section, 201);
    }

    /**
     * Display the specified section.
     */
    public function show(Project $project, ProjectSection $section)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isAccepted = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if (!$isAdmin && $project->visibility === 'private' && !$isAccepted) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($section->project_id !== $project->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($section);
    }

    /**
     * Update the specified section.
     */
    public function update(UpdateSectionRequest $request, Project $project, ProjectSection $section)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($section->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (!$isProjectAdmin) {
            return response()->json(['message' => 'Unauthorized. Project Admin access required.'], 403);
        }

        $section->update($request->validated());

        return response()->json($section);
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Request $request, Project $project, ProjectSection $section)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($section->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (!$isProjectAdmin) {
            return response()->json(['message' => 'Unauthorized. Project Admin access required.'], 403);
        }

        $section->delete();

        return response()->json(['message' => 'Section deleted']);
    }
}
