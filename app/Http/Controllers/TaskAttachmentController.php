<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    /**
     * Store a new attachment for a task.
     */
    public function store(Request $request, Project $project, Task $task)
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
            return response()->json(['message' => 'Unauthorized. Only project participants can upload files.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('tasks/' . $task->id, 'public');

            $attachment = $task->attachments()->create([
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            // Automatically set task status to overdue (verification state)
            $task->update(['status' => 'overdue']);

            return response()->json([
                'message' => 'File uploaded successfully and task set to review (overdue)',
                'attachment' => $attachment,
                'task' => $task->fresh()
            ], 201);
        }

        return response()->json(['message' => 'No file provided'], 400);
    }

    /**
     * Remove an attachment.
     */
    public function destroy(Project $project, Task $task, TaskAttachment $attachment)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';
        
        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($attachment->task_id !== $task->id || $task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        // Only uploader, project admin, or system admin can delete
        if (!$isAdmin && !$isProjectAdmin && $attachment->uploaded_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }
}
