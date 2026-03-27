<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function projectParticipants()
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function uploadedAttachments()
    {
        return $this->hasMany(TaskAttachment::class, 'uploaded_by');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'user1_id')->orWhere('user2_id', $this->id);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Check if the user is a participant (member) of the given project.
     */
    public function isTeamMember(Project $project)
    {
        if ($this->id === $project->user_id) {
            return true;
        }

        return $this->projectParticipants()
            ->where('project_id', $project->id)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Check if the user is a team admin for the given project.
     * Considers the project owner as admin and participants with role 'admin' or 'owner'.
     */
    public function isTeamAdmin(Project $project)
    {
        if ($this->id === $project->user_id) {
            return true;
        }

        return $this->projectParticipants()
            ->where('project_id', $project->id)
            ->whereIn('role', ['team_admin', 'admin', 'owner'])
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Return the participant record for this user in the given project, if any.
     */
    public function projectParticipant(Project $project)
    {
        return $this->projectParticipants()->where('project_id', $project->id)->first();
    }
}
