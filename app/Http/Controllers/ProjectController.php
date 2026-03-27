<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\TaskAttachment;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

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
     * Search only public projects.
     */
    public function publicSearch(Request $request)
    {
        $term = $request->query('q');
        $perPage = (int) $request->query('per_page', 15);

        $projects = Project::where('is_archived', false)
            ->where('visibility', 'public')
            ->search($term)
            ->with('owner')
            ->latest()
            ->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Search projects by a query term. Returns public projects and projects
     * the authenticated user is a member of.
     */
    public function search(Request $request)
    {
        $term = $request->query('q');
        $perPage = (int) $request->query('per_page', 15);
        $user = $request->user();

        $projects = Project::where('is_archived', false)
            ->where(function ($q) use ($user) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $user->id)
                    ->orWhereHas('participants', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)->where('status', 'accepted');
                    });
            })
            ->search($term)
            ->with('owner', 'participants')
            ->latest()
            ->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Search projects the authenticated user has joined (owner or accepted participant).
     */
    public function joinedSearch(Request $request)
    {
        $term = $request->query('q');
        $perPage = (int) $request->query('per_page', 15);
        $user = $request->user();

        $projects = Project::where('is_archived', false)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)->where('status', 'accepted');
                    });
            })
            ->search($term)
            ->with('owner', 'participants')
            ->latest()
            ->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Search the authenticated user's private projects (owned and private).
     */
    public function privateSearch(Request $request)
    {
        $term = $request->query('q');
        $perPage = (int) $request->query('per_page', 15);
        $user = $request->user();

        $projects = Project::where('is_archived', false)
            ->where('visibility', 'private')
            ->where('user_id', $user->id)
            ->search($term)
            ->with('owner', 'participants')
            ->latest()
            ->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Filter projects by skill(s). Query param: `skill` or `skills` (comma-separated or array).
     */
    public function filterBySkill(Request $request)
    {
        $skills = $request->query('skills') ?? $request->query('skill');
        $perPage = (int) $request->query('per_page', 15);
        $user = $request->user();

        $projects = Project::where('is_archived', false)
            ->where(function ($q) use ($user) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $user->id)
                    ->orWhereHas('participants', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)->where('status', 'accepted');
                    });
            })
            ->filterBySkills($skills)
            ->with('owner', 'participants')
            ->latest()
            ->paginate($perPage);

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
     * Return projects the authenticated user has joined (owner or accepted participant).
     */
    public function myProjects(Request $request)
    {
        $user = $request->user();

        $projects = Project::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('participants', function ($q2) use ($user) {
                    $q2->where('user_id', $user->id)->where('status', 'accepted');
                });
        })->with('owner', 'participants')->get();

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
            'status' => 'accepted',
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
        if (! $isAdmin && $project->visibility === 'private' && ! $isAccepted) {
            return response()->json(['message' => 'Unauthorized or request pending'], 403);
        }

        return response()->json($project->load('owner', 'participants.user', 'sections', 'tasks'));
    }

    /**
     * Return membership status for the authenticated user for this project.
     */
    public function membership(Project $project)
    {
        $user = auth()->user();

        return response()->json([
            'is_member' => $user->isTeamMember($project),
            'is_admin' => $user->isTeamAdmin($project),
            'participant' => $user->projectParticipant($project) ? $user->projectParticipant($project)->load('user') : null,
        ]);
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
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Join request sent']);
    }

    /**
     * Add a user to the project by username (immediately accepted).
     */
    public function addByUsername(Request $request, Project $project)
    {
        $this->authorizeProjectAdmin($project);

        $validated = $request->validate([
            'username' => 'required|string|exists:users,username',
            'role' => 'nullable|in:team_member,team_admin',
        ]);

        $user = \App\Models\User::where('username', $validated['username'])->first();

        if ($project->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User already a participant'], 400);
        }

        $participant = $project->participants()->create([
            'user_id' => $user->id,
            'role' => $validated['role'] ?? 'team_member',
            'status' => 'accepted',
        ]);

        return response()->json(['message' => 'User added', 'participant' => $participant->load('user')]);
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
     * Generate or refresh an invite link for the project.
     */
    public function invite(Project $project)
    {
        $this->authorizeProjectAdmin($project);

        $project->update(['invite_link' => \Illuminate\Support\Str::random(40)]);

        $url = url("/api/projects/join/{$project->invite_link}");

        return response()->json(['invite_link' => $project->invite_link, 'url' => $url]);
    }

    /**
     * Join a project using an invite link.
     */
    public function joinWithInvite($invite)
    {
        $project = Project::where('invite_link', $invite)->first();

        if (! $project) {
            return response()->json(['message' => 'Invalid invite link'], 404);
        }

        $user = auth()->user();

        if ($project->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Already a participant'], 400);
        }

        $project->participants()->create([
            'user_id' => $user->id,
            'role' => 'team_member',
            'status' => 'accepted',
        ]);

        return response()->json(['message' => 'Joined project', 'project' => $project->load('owner')]);
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

        if (! $isProjectAdmin) {
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

        if (! $isSystemAdmin && ! $isProjectAdmin) {
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

    /**
     * Download all project task attachments as a single ZIP archive.
     */
    public function downloadArchive(Project $project)
    {
        $user = auth()->user();
        $isSystemAdmin = $user->role === 'system_admin';
        $isOwner = (int) $project->user_id === (int) $user->id;

        if (! $isOwner && ! $isSystemAdmin) {
            return response()->json([
                'message' => 'Unauthorized. Only project owner can download full archive.',
            ], 403);
        }

        $attachments = TaskAttachment::query()
            ->whereHas('task', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['task:id,project_id,section_id,name', 'task.section:id,name'])
            ->get();

        if ($attachments->isEmpty()) {
            return response()->json([
                'message' => 'No attachments found for this project.',
            ], 404);
        }

        $tempDir = storage_path('app/private/exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $safeProjectName = Str::slug($project->name ?: 'project');
        $zipFileName = "{$safeProjectName}-{$project->id}-attachments.zip";
        $zipPath = $tempDir.DIRECTORY_SEPARATOR.$zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Failed to create archive file.'], 500);
        }

        $addedFiles = 0;

        foreach ($attachments as $attachment) {
            if (! $attachment->task || ! $attachment->file_path) {
                continue;
            }

            if (! Storage::disk('public')->exists($attachment->file_path)) {
                continue;
            }

            $fullPath = Storage::disk('public')->path($attachment->file_path);
            $sectionName = Str::slug(optional($attachment->task->section)->name ?: 'no-section');
            $taskName = Str::slug($attachment->task->name ?: 'task-'.$attachment->task->id);
            $originalName = $attachment->file_name ?: basename($attachment->file_path);

            $entryPath = "project-{$project->id}/{$sectionName}/{$taskName}/{$originalName}";
            if ($zip->addFile($fullPath, $entryPath)) {
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return response()->json([
                'message' => 'No accessible files found to include in archive.',
            ], 404);
        }

        if (! file_exists($zipPath)) {
            return response()->json(['message' => 'Archive generation failed.'], 500);
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
