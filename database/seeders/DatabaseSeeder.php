<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectSection;
use App\Models\Report;
use App\Models\Skill;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = collect([
            [
                'name' => 'Sara Ahmed',
                'email' => 'sara@example.com',
                'username' => 'sara_ahmed',
                'bio' => 'Frontend developer who enjoys clean interfaces and practical teamwork.',
                'points' => 420,
                'skills' => ['Vue.js', 'Tailwind CSS', 'UI Design'],
            ],
            [
                'name' => 'Omar Khaled',
                'email' => 'omar@example.com',
                'username' => 'omar_khaled',
                'bio' => 'Backend engineer focused on APIs, databases, and Laravel services.',
                'points' => 510,
                'skills' => ['Laravel', 'MySQL', 'REST APIs'],
            ],
            [
                'name' => 'Lina Hassan',
                'email' => 'lina@example.com',
                'username' => 'lina_hassan',
                'bio' => 'Product-minded designer who turns messy ideas into usable flows.',
                'points' => 360,
                'skills' => ['UX Research', 'Figma', 'Prototyping'],
            ],
            [
                'name' => 'Yousef Ali',
                'email' => 'yousef@example.com',
                'username' => 'yousef_ali',
                'bio' => 'Mobile developer building fast Flutter experiences.',
                'points' => 295,
                'skills' => ['Flutter', 'Dart', 'Firebase'],
            ],
            [
                'name' => 'Maya Nasser',
                'email' => 'maya@example.com',
                'username' => 'maya_nasser',
                'bio' => 'Data analyst who likes dashboards, metrics, and clear decisions.',
                'points' => 275,
                'skills' => ['Python', 'Power BI', 'SQL'],
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'username' => 'admin',
                'role' => 'system_admin',
                'bio' => 'System administrator account for local testing.',
                'points' => 900,
                'skills' => ['Administration', 'Security', 'Operations'],
            ],
        ])->map(function (array $userData) {
            $profileData = [
                'bio' => $userData['bio'],
                'points' => $userData['points'],
            ];
            $skills = $userData['skills'];
            unset($userData['bio'], $userData['points'], $userData['skills']);

            $user = User::where('email', $userData['email'])
                ->orWhere('username', $userData['username'])
                ->first();

            $userPayload = array_merge($userData, [
                'role' => $userData['role'] ?? 'user',
                'active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            if ($user) {
                $user->update($userPayload);
            } else {
                $user = User::create($userPayload);
            }

            $profile = Profile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            foreach ($skills as $skill) {
                Skill::firstOrCreate([
                    'profile_id' => $profile->id,
                    'name' => $skill,
                ]);
            }

            return $user;
        });

        $projects = [
            [
                'owner' => 'sara@example.com',
                'name' => 'Job Matrix Web Portal',
                'description' => 'A collaborative portal for matching team members with project tasks based on skills and availability.',
                'visibility' => 'public',
                'skills' => ['Laravel', 'Vue.js', 'Tailwind CSS', 'MySQL'],
                'invite_link' => 'job-matrix-web-portal',
                'members' => [
                    'omar@example.com' => 'team_admin',
                    'lina@example.com' => 'team_member',
                    'maya@example.com' => 'team_member',
                ],
                'sections' => [
                    [
                        'name' => 'Planning',
                        'description' => 'Scope, requirements, and interface decisions.',
                        'tasks' => [
                            ['name' => 'Define project roles', 'status' => 'completed', 'assigned_to' => 'lina@example.com', 'skills' => ['UX Research'], 'points' => 20],
                            ['name' => 'Prepare API contract', 'status' => 'in_progress', 'assigned_to' => 'omar@example.com', 'skills' => ['REST APIs', 'Laravel'], 'points' => 35],
                        ],
                    ],
                    [
                        'name' => 'Implementation',
                        'description' => 'Frontend and backend build work.',
                        'tasks' => [
                            ['name' => 'Build project list screen', 'status' => 'pending', 'assigned_to' => 'sara@example.com', 'skills' => ['Vue.js', 'Tailwind CSS'], 'points' => 30],
                            ['name' => 'Create dashboard metrics', 'status' => 'pending', 'assigned_to' => 'maya@example.com', 'skills' => ['SQL', 'Power BI'], 'points' => 25],
                        ],
                    ],
                ],
            ],
            [
                'owner' => 'yousef@example.com',
                'name' => 'Freelancer Mobile Companion',
                'description' => 'A mobile app for freelancers to track tasks, messages, and project notifications on the go.',
                'visibility' => 'private',
                'skills' => ['Flutter', 'Firebase', 'Laravel'],
                'invite_link' => 'freelancer-mobile-companion',
                'members' => [
                    'sara@example.com' => 'team_member',
                    'omar@example.com' => 'team_admin',
                ],
                'sections' => [
                    [
                        'name' => 'Mobile App',
                        'description' => 'Core Flutter screens and app behavior.',
                        'tasks' => [
                            ['name' => 'Design notification center', 'status' => 'in_progress', 'assigned_to' => 'yousef@example.com', 'skills' => ['Flutter', 'Dart'], 'points' => 30],
                            ['name' => 'Connect authentication API', 'status' => 'pending', 'assigned_to' => 'omar@example.com', 'skills' => ['Laravel', 'REST APIs'], 'points' => 25],
                        ],
                    ],
                ],
            ],
            [
                'owner' => 'maya@example.com',
                'name' => 'Team Performance Analytics',
                'description' => 'Analytics workspace for reviewing completed tasks, points, and team contribution trends.',
                'visibility' => 'public',
                'skills' => ['Python', 'SQL', 'Power BI'],
                'invite_link' => 'team-performance-analytics',
                'members' => [
                    'lina@example.com' => 'team_member',
                    'admin@example.com' => 'team_admin',
                ],
                'sections' => [
                    [
                        'name' => 'Reporting',
                        'description' => 'Data models and visual summaries.',
                        'tasks' => [
                            ['name' => 'Model task completion data', 'status' => 'completed', 'assigned_to' => 'maya@example.com', 'skills' => ['SQL', 'Python'], 'points' => 40],
                            ['name' => 'Prototype team dashboard', 'status' => 'in_progress', 'assigned_to' => 'lina@example.com', 'skills' => ['Figma', 'Prototyping'], 'points' => 30],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $owner = $users->firstWhere('email', $projectData['owner']);

            $project = Project::updateOrCreate(
                ['invite_link' => $projectData['invite_link']],
                [
                    'user_id' => $owner->id,
                    'name' => $projectData['name'],
                    'description' => $projectData['description'],
                    'visibility' => $projectData['visibility'],
                    'skills' => $projectData['skills'],
                    'is_archived' => false,
                ]
            );

            foreach ($projectData['members'] as $email => $role) {
                $member = $users->firstWhere('email', $email);

                ProjectParticipant::updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $member->id],
                    ['role' => $role, 'status' => 'accepted']
                );
            }

            foreach ($projectData['sections'] as $sectionData) {
                $section = ProjectSection::updateOrCreate(
                    ['project_id' => $project->id, 'name' => $sectionData['name']],
                    ['description' => $sectionData['description']]
                );

                foreach ($sectionData['tasks'] as $taskData) {
                    $assignee = $users->firstWhere('email', $taskData['assigned_to']);

                    Task::updateOrCreate(
                        [
                            'project_id' => $project->id,
                            'section_id' => $section->id,
                            'name' => $taskData['name'],
                        ],
                        [
                            'description' => 'Seed task for local testing.',
                            'skills' => $taskData['skills'],
                            'assigned_to' => $assignee?->id,
                            'deadline' => now()->addDays(rand(7, 30))->toDateString(),
                            'status' => $taskData['status'],
                            'points' => $taskData['points'],
                            'completed_at' => $taskData['status'] === 'completed' ? now() : null,
                            'is_archived' => false,
                        ]
                    );
                }
            }
        }

        $taskByName = fn (string $name) => Task::where('name', $name)->first();
        $projectByInvite = fn (string $inviteLink) => Project::where('invite_link', $inviteLink)->first();

        $dependencies = [
            ['task' => 'Build project list screen', 'depends_on' => 'Prepare API contract'],
            ['task' => 'Create dashboard metrics', 'depends_on' => 'Model task completion data'],
            ['task' => 'Connect authentication API', 'depends_on' => 'Prepare API contract'],
            ['task' => 'Prototype team dashboard', 'depends_on' => 'Model task completion data'],
        ];

        foreach ($dependencies as $dependencyData) {
            $task = $taskByName($dependencyData['task']);
            $dependsOnTask = $taskByName($dependencyData['depends_on']);

            if (! $task || ! $dependsOnTask) {
                continue;
            }

            TaskDependency::firstOrCreate([
                'task_id' => $task->id,
                'depends_on_task_id' => $dependsOnTask->id,
            ]);
        }

        $attachments = [
            ['task' => 'Define project roles', 'uploaded_by' => 'lina@example.com', 'file_path' => 'seed/attachments/project-roles.pdf', 'file_type' => 'application/pdf', 'file_size' => 245760],
            ['task' => 'Prepare API contract', 'uploaded_by' => 'omar@example.com', 'file_path' => 'seed/attachments/api-contract.json', 'file_type' => 'application/json', 'file_size' => 18432],
            ['task' => 'Prototype team dashboard', 'uploaded_by' => 'lina@example.com', 'file_path' => 'seed/attachments/dashboard-wireframe.png', 'file_type' => 'image/png', 'file_size' => 512000],
        ];

        foreach ($attachments as $attachmentData) {
            $task = $taskByName($attachmentData['task']);
            $uploader = $users->firstWhere('email', $attachmentData['uploaded_by']);

            if (! $task || ! $uploader) {
                continue;
            }

            TaskAttachment::updateOrCreate(
                ['file_path' => $attachmentData['file_path']],
                [
                    'task_id' => $task->id,
                    'uploaded_by' => $uploader->id,
                    'file_type' => $attachmentData['file_type'],
                    'file_size' => $attachmentData['file_size'],
                ]
            );
        }

        $conversations = [
            [
                'users' => ['sara@example.com', 'omar@example.com'],
                'messages' => [
                    ['sender' => 'sara@example.com', 'content' => 'Can you review the API contract before I connect the project list?', 'is_read' => true],
                    ['sender' => 'omar@example.com', 'content' => 'Yes, I updated the endpoints and added the status filters.', 'is_read' => true],
                ],
            ],
            [
                'users' => ['yousef@example.com', 'omar@example.com'],
                'messages' => [
                    ['sender' => 'yousef@example.com', 'content' => 'I need the auth endpoints for the mobile companion build.', 'is_read' => true],
                    ['sender' => 'omar@example.com', 'content' => 'I will share the token flow after finishing the contract task.', 'is_read' => false],
                ],
            ],
            [
                'users' => ['maya@example.com', 'lina@example.com'],
                'messages' => [
                    ['sender' => 'maya@example.com', 'content' => 'The completion data is ready for the dashboard prototype.', 'is_read' => true],
                    ['sender' => 'lina@example.com', 'content' => 'Great, I will use it for the first analytics screen.', 'is_read' => false],
                ],
            ],
        ];

        foreach ($conversations as $conversationData) {
            $firstUser = $users->firstWhere('email', $conversationData['users'][0]);
            $secondUser = $users->firstWhere('email', $conversationData['users'][1]);
            $lastMessage = collect($conversationData['messages'])->last();

            if (! $firstUser || ! $secondUser) {
                continue;
            }

            $conversation = Conversation::updateOrCreate(
                [
                    'user1_id' => min($firstUser->id, $secondUser->id),
                    'user2_id' => max($firstUser->id, $secondUser->id),
                ],
                [
                    'last_message' => $lastMessage['content'],
                    'last_message_at' => now(),
                ]
            );

            foreach ($conversationData['messages'] as $messageData) {
                $sender = $users->firstWhere('email', $messageData['sender']);

                Message::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $sender->id,
                        'content' => $messageData['content'],
                    ],
                    ['is_read' => $messageData['is_read']]
                );
            }
        }

        $notifications = [
            ['user' => 'sara@example.com', 'title' => 'Task assigned', 'content' => 'You have been assigned to build the project list screen.', 'type' => 'task_assigned', 'is_read' => false],
            ['user' => 'omar@example.com', 'title' => 'New dependency', 'content' => 'A mobile authentication task now depends on the API contract.', 'type' => 'task_dependency', 'is_read' => false],
            ['user' => 'lina@example.com', 'title' => 'Report prototype update', 'content' => 'The analytics data is ready for your prototype task.', 'type' => 'project_update', 'is_read' => true],
            ['user' => 'admin@example.com', 'title' => 'New report pending', 'content' => 'A project report is waiting for admin review.', 'type' => 'report_created', 'is_read' => false],
        ];

        foreach ($notifications as $notificationData) {
            $user = $users->firstWhere('email', $notificationData['user']);

            if (! $user) {
                continue;
            }

            Notification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $notificationData['title'],
                    'type' => $notificationData['type'],
                ],
                [
                    'content' => $notificationData['content'],
                    'is_read' => $notificationData['is_read'],
                ]
            );
        }

        $reportedProject = $projectByInvite('freelancer-mobile-companion');
        $reportedTask = $taskByName('Connect authentication API');
        $reporter = $users->firstWhere('email', 'sara@example.com');
        $admin = $users->firstWhere('email', 'admin@example.com');

        if ($reportedProject && $reporter) {
            Report::updateOrCreate(
                [
                    'reporter_id' => $reporter->id,
                    'reportable_id' => $reportedProject->id,
                    'reportable_type' => Project::class,
                ],
                [
                    'reason' => 'The project description needs clearer acceptance criteria.',
                    'status' => 'pending',
                    'admin_note' => null,
                ]
            );
        }

        if ($reportedTask && $admin) {
            Report::updateOrCreate(
                [
                    'reporter_id' => $admin->id,
                    'reportable_id' => $reportedTask->id,
                    'reportable_type' => Task::class,
                ],
                [
                    'reason' => 'Seeded task report for testing moderation workflows.',
                    'status' => 'resolved',
                    'admin_note' => 'Reviewed during local seed setup.',
                ]
            );
        }

        $activityLogs = [
            ['user' => 'sara@example.com', 'project' => 'job-matrix-web-portal', 'action' => 'project_created', 'loggable' => $projectByInvite('job-matrix-web-portal'), 'description' => 'Created the Job Matrix Web Portal project.', 'metadata' => ['source' => 'seed']],
            ['user' => 'omar@example.com', 'project' => 'job-matrix-web-portal', 'action' => 'task_updated', 'loggable' => $taskByName('Prepare API contract'), 'description' => 'Moved API contract task to in progress.', 'metadata' => ['status' => 'in_progress']],
            ['user' => 'maya@example.com', 'project' => 'team-performance-analytics', 'action' => 'task_completed', 'loggable' => $taskByName('Model task completion data'), 'description' => 'Completed analytics data modeling task.', 'metadata' => ['points' => 40]],
            ['user' => 'admin@example.com', 'project' => 'freelancer-mobile-companion', 'action' => 'report_resolved', 'loggable' => $reportedTask, 'description' => 'Resolved a seeded task report.', 'metadata' => ['status' => 'resolved']],
        ];

        foreach ($activityLogs as $logData) {
            $user = $users->firstWhere('email', $logData['user']);
            $project = $projectByInvite($logData['project']);
            $loggable = $logData['loggable'];

            if (! $user || ! $project || ! $loggable) {
                continue;
            }

            ActivityLog::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'action' => $logData['action'],
                    'loggable_type' => $loggable::class,
                    'loggable_id' => $loggable->id,
                ],
                [
                    'project_id' => $project->id,
                    'description' => $logData['description'],
                    'ip_address' => '127.0.0.1',
                    'metadata' => $logData['metadata'],
                ]
            );
        }
    }
}
