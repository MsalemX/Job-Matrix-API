<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the authenticated user's activities.
     */
    public function index(Request $request)
    {
        $activities = ActivityLog::where('user_id', auth()->id())
            ->with(['loggable', 'project'])
            ->latest()
            ->paginate(50);

        return response()->json($activities);
    }

    /**
     * Display a listing of all activities for the system admin.
     */
    public function adminIndex(Request $request)
    {
        $query = ActivityLog::with(['user.profile', 'loggable', 'project']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', 'LIKE', "%{$request->action}%");
        }

        return response()->json($query->latest()->paginate(100));
    }
}
