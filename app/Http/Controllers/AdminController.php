<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Report;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function usersIndex()
    {
        return response()->json(User::with('profile')->latest()->get());
    }

    /**
     * Remove the specified user from storage.
     */
    public function userDestroy(User $user)
    {
        if ($user->role === 'system_admin' && User::where('role', 'system_admin')->count() <= 1) {
            return response()->json(['message' => 'Cannot delete the only admin.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Display a listing of all projects (including private) with essential info.
     */
    public function projectsIndex()
    {
        return response()->json(
            \App\Models\Project::with('owner')
                ->withCount('participants', 'sections', 'tasks')
                ->latest()
                ->get()
        );
    }

    /**
     * Display full details of any project for the system admin.
     */
    public function projectShow(\App\Models\Project $project)
    {
        return response()->json(
            $project->load(['owner', 'participants.user', 'sections', 'tasks.assignee'])
        );
    }

    /**
     * Remove any project from storage.
     */
    public function projectDestroy(\App\Models\Project $project)
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully by admin.']);
    }

    /**
     * Display a listing of all pending reports.
     */
    public function reportsIndex()
    {
        return response()->json(
            Report::with(['reporter', 'reportable'])->where('status', 'pending')->latest()->get()
        );
    }

    /**
     * Resolve a report (dismiss or take action).
     */
    public function resolveReport(Request $request, Report $report)
    {
        $request->validate([
            'action' => 'required|in:dismiss,resolve',
            'admin_note' => 'nullable|string'
        ]);

        if ($request->action === 'dismiss') {
            $report->update([
                'status' => 'dismissed',
                'admin_note' => $request->admin_note
            ]);
            return response()->json(['message' => 'Report dismissed.']);
        }

        // Action is 'resolve' - delete the target
        $target = $report->reportable;
        if ($target) {
            // Check if user is the last admin
            if ($report->reportable_type === User::class && $target->role === 'system_admin' && User::where('role', 'system_admin')->count() <= 1) {
                return response()->json(['message' => 'Cannot delete the only admin via report.'], 403);
            }
            
            $target->delete();
        }

        $report->update([
            'status' => 'resolved',
            'admin_note' => $request->admin_note ?? 'Action taken: Target deleted.'
        ]);

        return response()->json(['message' => 'Report resolved and target deleted.']);
    }
}
