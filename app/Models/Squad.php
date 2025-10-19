<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Squad extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'timezone',
        'workdays',
        'sprint_duration_days',
        'jira_board_id',
        'project_key',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workdays' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the members of this squad.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'squad_members')
            ->withPivot('role', 'joined_at', 'left_at', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get the sprints for this squad.
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * Get the attendance records for this squad.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Get the leave requests for this squad.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the compliance rules for this squad.
     */
    public function complianceRules(): HasMany
    {
        return $this->hasMany(ComplianceRule::class);
    }

    /**
     * Get the compliance scores for this squad.
     */
    public function complianceScores(): HasMany
    {
        return $this->hasMany(ComplianceScore::class);
    }

    /**
     * Get the active sprint for this squad.
     */
    public function activeSprint()
    {
        return $this->sprints()->where('status', 'active')->first();
    }

    /**
     * Get the squad leads.
     */
    public function leads(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'lead');
    }
}
