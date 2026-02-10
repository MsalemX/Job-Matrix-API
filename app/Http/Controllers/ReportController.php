<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Store a newly created report.
     */
    public function store(Request $request)
    {
        $request->validate([
            'reportable_id' => 'required|integer',
            'reportable_type' => 'required|in:user,project',
            'reason' => 'required|string|min:10',
        ]);

        $type = $request->reportable_type === 'user' ? User::class : Project::class;
        
        // Find the target to ensure it exists
        $target = $type::find($request->reportable_id);
        if (!$target) {
            return response()->json(['message' => 'Target not found'], 404);
        }

        // Prevent self-reporting
        if ($request->reportable_type === 'user' && $request->reportable_id == auth()->id()) {
            return response()->json(['message' => 'You cannot report yourself'], 400);
        }

        $report = Report::create([
            'reporter_id' => auth()->id(),
            'reportable_id' => $request->reportable_id,
            'reportable_type' => $type,
            'reason' => $request->reason,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Report submitted successfully. Thank you for helping keep our community safe.',
            'report' => $report
        ], 201);
    }
}
