<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Role catalogue (spec AUT-05, Section 2.3). Full Role entity lands next;
     * until then roles are validated against this list.
     *
     * @var list<string>
     */
    public const ROLES = [
        self::ROLE_SYSTEM_ADMIN,
        self::ROLE_INSTITUTION_ADMIN,
        self::ROLE_EXAMINATION_OFFICER,
        self::ROLE_TEACHER,
        self::ROLE_MODERATOR,
        self::ROLE_SCANNING_OPERATOR,
        self::ROLE_AUDITOR,
        self::ROLE_STUDENT,
        self::ROLE_INTEGRATION_CLIENT,
    ];

    /**
     * Named role constants, so authorisation code refers to a role rather than
     * repeating its string. The strings themselves are unchanged: this makes the
     * existing catalogue type safe without a migration or a data change.
     */
    public const ROLE_SYSTEM_ADMIN = 'system_admin';
    public const ROLE_INSTITUTION_ADMIN = 'institution_admin';
    public const ROLE_EXAMINATION_OFFICER = 'examination_officer';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_SCANNING_OPERATOR = 'scanning_operator';
    public const ROLE_AUDITOR = 'auditor';
    public const ROLE_STUDENT = 'student';
    public const ROLE_INTEGRATION_CLIENT = 'integration_client';

    /**
     * Account types a user can register with.
     *
     * `personal` is an individual teacher with no institution: institution_id
     * stays NULL and the user scopes their own records (AUT-06).
     * `institutional` is a staff member of a school, university or exam body
     * and is always attached to an institution row.
     */
    public const ACCOUNT_TYPE_PERSONAL = 'personal';

    public const ACCOUNT_TYPE_INSTITUTIONAL = 'institutional';

    /**
     * @var list<string>
     */
    public const ACCOUNT_TYPES = [
        self::ACCOUNT_TYPE_PERSONAL,
        self::ACCOUNT_TYPE_INSTITUTIONAL,
    ];

    /**
     * Role granted on self-registration, per account type. Personal users are
     * teachers by definition; institutional registrants administer the tenant.
     *
     * @var array<string, string>
     */
    public const ROLE_FOR_ACCOUNT_TYPE = [
        self::ACCOUNT_TYPE_PERSONAL => 'teacher',
        self::ACCOUNT_TYPE_INSTITUTIONAL => 'institution_admin',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'institution_id',
        'account_type',
        'role',
        'phone',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * A personal account has no institution and is its own tenant (AUT-06).
     */
    public function isPersonal(): bool
    {
        return $this->account_type === self::ACCOUNT_TYPE_PERSONAL;
    }

    /**
     * Courses this user teaches or is responsible for (ACD-04).
     */
    public function courseUnits(): BelongsToMany
    {
        return $this->belongsToMany(CourseUnit::class, 'course_unit_teachers')
            ->withPivot('is_responsible')
            ->withTimestamps();
    }

    /**
     * Courses where this user is the accountable lecturer.
     */
    public function responsibleForCourseUnits(): BelongsToMany
    {
        return $this->courseUnits()->wherePivot('is_responsible', true);
    }

    /**
     * Examinations this user owns directly, which is only ever populated for a
     * personal account.
     *
     * Owned and institutional papers are separate relations rather than one
     * union because the two tenancy branches are genuinely different (ADR-002):
     * a personal user owns their rows, an institutional user inherits the whole
     * institution's. Callers that need both should ask the scope, not a union
     * that quietly assumes the current user is one kind of account.
     */
    public function ownedExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'owner_user_id');
    }

    public function institutionExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'institution_id');
    }

    public function ownedStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'owner_user_id');
    }

    public function institutionStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'institution_id');
    }

    /**
     * Answers this user has approved. Naming a person is what makes a mark
     * decided under BR-02, so this is the trail of who actually marked.
     */
    public function markedAnswers(): HasMany
    {
        return $this->hasMany(ScriptAnswer::class, 'approved_by');
    }

    /**
     * Scripts this user locked.
     */
    public function lockedScripts(): HasMany
    {
        return $this->hasMany(Script::class, 'locked_by');
    }
}
