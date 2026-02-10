<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of tasks for a project.
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

        return response()->json($project->tasks()->with('assignee', 'attachments')->get());
    }

    /**
     * Store a newly created task in a project.
     */
    public function store(StoreTaskRequest $request, Project $project)
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $project) {
            $task = $project->tasks()->create($request->validated());

            if ($request->has('depends_on')) {
                foreach ($request->depends_on as $dependsOnId) {
                    $task->dependencies()->create([
                        'depends_on_task_id' => $dependsOnId
                    ]);
                }
            }

            $this->logActivity('Created Task', $task, "Task '{$task->name}' created", null, $project->id);

            return response()->json($task->load('dependencies'), 201);
        });
    }

    /**
     * Display the specified task.
     */
    public function show(Project $project, Task $task)
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

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($task->load('assignee', 'attachments', 'dependencies'));
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (!$isProjectAdmin) {
            return response()->json(['message' => 'Unauthorized. Project Admin access required.'], 403);
        }

        $task->update($request->validated());

        return response()->json($task);
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request, Project $project, Task $task)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (!$isProjectAdmin) {
            return response()->json(['message' => 'Unauthorized. Project Admin access required.'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    /**
     * Mark task as completed or toggle status.
     */
    public function toggleStatus(Request $request, Project $project, Task $task)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        $isAccepted = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (!$isAccepted) {
            return response()->json(['message' => 'Unauthorized. Only project participants can toggle task status.'], 403);
        }

        $oldStatus = $task->status;
        $newStatus = $oldStatus === 'completed' ? 'in_progress' : 'completed';
        
        // Dependency check
        if ($newStatus === 'completed') {
            $pendingDependencies = $task->dependencies()
                ->whereHas('dependsOn', function ($query) {
                    $query->where('status', '!=', 'completed');
                })
                ->exists();

            if ($pendingDependencies) {
                return response()->json([
                    'message' => 'Cannot complete task. There are pending dependencies that must be completed first.'
                ], 403);
            }
        }

        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'completed' ? now() : null
        ]);

        $this->logActivity(
            'Updated Task Status', 
            $task, 
            "Task status changed to {$newStatus}", 
            ['old_status' => $oldStatus, 'new_status' => $newStatus], 
            $task->project_id
        );

        // Points logic
        if ($task->assigned_to) {
            $assignee = \App\Models\User::find($task->assigned_to);
            if ($assignee && $assignee->profile) {
                if ($newStatus === 'completed') {
                    $assignee->profile->increment('points', $task->points ?? 0);
                } elseif ($oldStatus === 'completed') {
                    $assignee->profile->decrement('points', $task->points ?? 0);
                }
            }
        }

        return response()->json($task->load('assignee'));
    }
    /**
     * Assign a task to a user.
     */
    public function assign(Request $request, Project $project, Task $task)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        
        $participantInfo = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->first();

        if (!$participantInfo) {
            return response()->json(['message' => 'Unauthorized. Only project participants can assign tasks.'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $targetUserId = $request->user_id;

        // Role check
        if ($participantInfo->role !== 'team_admin' && $targetUserId != $user->id) {
            return response()->json(['message' => 'Members can only assign tasks to themselves'], 403);
        }

        // Check if target user is an accepted participant
        $isTargetParticipant = $project->participants()
            ->where('user_id', $targetUserId)
            ->where('status', 'accepted')
            ->exists();

        if (!$isTargetParticipant) {
            return response()->json(['message' => 'Target user is not an accepted participant of this project'], 400);
        }

        $task->update([
            'assigned_to' => $targetUserId,
            'status' => 'in_progress'
        ]);

        return response()->json(['message' => 'Task assigned successfully', 'task' => $task->load('assignee')]);
    }

    /**
     * Unassign a task.
     */
    public function unassign(Project $project, Task $task)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        
        $participantInfo = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->first();

        if (!$participantInfo) {
            return response()->json(['message' => 'Unauthorized. Only project participants can unassign tasks.'], 403);
        }

        // Role check: admins can unassign anyone, members can only unassign themselves
        if ($participantInfo->role !== 'team_admin' && $task->assigned_to != $user->id) {
            return response()->json(['message' => 'Members can only unassign tasks from themselves'], 403);
        }

        $task->update(['assigned_to' => null]);

        return response()->json(['message' => 'Task unassigned successfully', 'task' => $task]);
    }
}
