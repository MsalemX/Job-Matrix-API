<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of all public projects for authenticated users.
     */
    public function publicIndex()
    {
        $projects = Project::where('visibility', 'public')
            ->where('is_archived', false)
            ->with('owner')
            ->latest()
            ->get();
            
        return response()->json($projects);
    }

    /**
     * Display a listing of the authenticated user's projects.
     */
    public function index(Request $request)
    {
        $projects = $request->user()->projects()->with('participants')->get();
        return response()->json($projects);
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();
        
        $project = $request->user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'visibility' => $validated['visibility'],
            'skills' => $validated['skills'],
            'invite_link' => \Illuminate\Support\Str::random(10),
        ]);

        // Add creator as accepted team_admin
        $project->participants()->create([
            'user_id' => $request->user()->id,
            'role' => 'team_admin',
            'status' => 'accepted'
        ]);

        $this->logActivity('Created Project', $project, "Project created by {$request->user()->name}", null, $project->id);

        return response()->json($project->load('owner'), 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        
        // Check if user is an accepted participant
        $participant = $project->participants()->where('user_id', $user->id)->first();
        $isAccepted = $participant && $participant->status === 'accepted';

        // Visibility check
        if (!$isAdmin && $project->visibility === 'private' && !$isAccepted) {
            return response()->json(['message' => 'Unauthorized or request pending'], 403);
        }

        return response()->json($project->load('owner', 'participants.user', 'sections', 'tasks'));
    }

    /**
     * Request to join a project.
     */
    public function join(Project $project)
    {
        // Check if already a participant
        if ($project->participants()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Already requested or joined'], 400);
        }

        $project->participants()->create([
            'user_id' => auth()->id(),
            'role' => 'team_member',
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Join request sent']);
    }

    /**
     * List pending join requests for a project.
     */
    public function pendingRequests(Project $project)
    {
        $this->authorizeProjectAdmin($project);

        $requests = $project->participants()
            ->where('status', 'pending')
            ->with('user')
            ->get();

        return response()->json($requests);
    }

    /**
     * Approve a join request.
     */
    public function approveRequest(Project $project, \App\Models\ProjectParticipant $participant)
    {
        $this->authorizeProjectAdmin($project);

        if ($participant->project_id !== $project->id) {
            return response()->json(['message' => 'Invalid participant for this project'], 400);
        }

        $participant->update(['status' => 'accepted']);

        return response()->json(['message' => 'Request approved', 'participant' => $participant->load('user')]);
    }

    /**
     * Reject a join request.
     */
    public function rejectRequest(Project $project, \App\Models\ProjectParticipant $participant)
    {
        $this->authorizeProjectAdmin($project);

        if ($participant->project_id !== $project->id) {
            return response()->json(['message' => 'Invalid participant for this project'], 400);
        }

        $participant->delete();

        return response()->json(['message' => 'Request rejected and removed']);
    }

    /**
     * Helper to check if current user is strictly a project admin (team_admin).
     */
    private function authorizeProjectAdmin(Project $project)
    {
        $user = auth()->user();
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if (!$isProjectAdmin) {
            abort(403, 'Unauthorized. Project Admin access required.');
        }
    }

    /**
     * Helper to check if current user is project admin or system admin.
     */
    private function authorizeAdmin(Project $project)
    {
        $user = auth()->user();
        $isSystemAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if (!$isSystemAdmin && !$isProjectAdmin) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Update the specified project in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->authorizeProjectAdmin($project);

        $project->update($request->validated());

        return response()->json($project);
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Request $request, Project $project)
    {
        $this->authorizeAdmin($project);

        $project->delete();

        return response()->json(['message' => 'Project deleted']);
    }
}
