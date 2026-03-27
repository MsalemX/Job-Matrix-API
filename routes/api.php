<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Public routes (Auth only)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/deactivate', [AuthController::class, 'deactivateAccount']);
    Route::get('/profiles/{user}', [AuthController::class, 'showProfile']);
    Route::get('/my/tasks', [AuthController::class, 'myTasks']);
    Route::get('/my/projects', [ProjectController::class, 'myProjects']);

    // Projects
    Route::get('/projects/search/public', [ProjectController::class, 'publicSearch']);
    Route::get('/projects/search/joined', [ProjectController::class, 'joinedSearch']);
    Route::get('/projects/search/private', [ProjectController::class, 'privateSearch']);
    Route::get('/projects/search', [ProjectController::class, 'search']);
    Route::get('/projects/filter-by-skill', [ProjectController::class, 'filterBySkill']);
    Route::get('/projects/public', [ProjectController::class, 'publicIndex']);
    Route::get('/projects/{project}/download-archive', [ProjectController::class, 'downloadArchive']);
    Route::post('/projects/{project}/join', [ProjectController::class, 'join']);
    Route::post('/projects/{project}/add-by-username', [ProjectController::class, 'addByUsername']);
    Route::post('/projects/{project}/invite', [ProjectController::class, 'invite']);
    Route::get('/projects/join/{invite}', [ProjectController::class, 'joinWithInvite']);
    Route::get('/projects/{project}/requests', [ProjectController::class, 'pendingRequests']);
    Route::post('/projects/{project}/requests/{participant}/approve', [ProjectController::class, 'approveRequest']);
    Route::post('/projects/{project}/requests/{participant}/reject', [ProjectController::class, 'rejectRequest']);
    Route::apiResource('projects', ProjectController::class);

    // Nested Project Sections
    Route::apiResource('projects.sections', SectionController::class);

    // Nested Project Tasks
    Route::apiResource('projects.tasks', TaskController::class);
    Route::post('projects/{project}/tasks/{task}/toggle', [TaskController::class, 'toggleStatus']);
    Route::post('projects/{project}/tasks/{task}/assign', [TaskController::class, 'assign']);
    Route::post('projects/{project}/tasks/{task}/unassign', [TaskController::class, 'unassign']);
    Route::post('projects/{project}/tasks/{task}/assign-self', [TaskController::class, 'assignSelf']);
    Route::get('/projects/{project}/membership', [ProjectController::class, 'membership']);

    // Task Attachments
    Route::post('projects/{project}/tasks/{task}/attachments', [TaskAttachmentController::class, 'store']);
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy']);

    // Reports
    Route::post('/reports', [ReportController::class, 'store']);

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{user}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);

    // AI
    Route::post('/ai/chat', [AIController::class, 'chat']);

    // Activity Logs
    Route::get('/activities', [ActivityLogController::class, 'index']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        // ... (existing admin routes)
        Route::get('/admin/activities', [ActivityLogController::class, 'adminIndex']);
        // User Management
        Route::get('/admin/users', [AdminController::class, 'usersIndex']);
        Route::get('/admin/users-with-tasks', [AdminController::class, 'usersWithTasks']);
        Route::delete('/admin/users/{user}', [AdminController::class, 'userDestroy']);

        // System Wide Visibility & Control
        Route::get('/admin/projects', [AdminController::class, 'projectsIndex']);
        Route::get('/admin/projects/{project}', [AdminController::class, 'projectShow']);
        Route::delete('/admin/projects/{project}', [AdminController::class, 'projectDestroy']);

        // Report Management
        Route::get('/admin/reports', [AdminController::class, 'reportsIndex']);
        Route::patch('/admin/reports/{report}/resolve', [AdminController::class, 'resolveReport']);
    });
});
