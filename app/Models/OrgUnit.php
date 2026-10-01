<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Node in the academic hierarchy (ACD-02).
 *
 * One typed adjacency list serves both institution shapes rather than separate
 * school and university tables, because a secondary school needs
 * faculty > class and a university needs faculty > department > programme >
 * class. The same rows and the same queries cover both.
 *
 * Course units are deliberately NOT org units. They carry credit, grading and
 * teacher assignments and are referenced directly by examinations, so they get
 * their own table.
 */
class OrgUnit extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_FACULTY = 'faculty';

    public const TYPE_SCHOOL = 'school';

    public const TYPE_DEPARTMENT = 'department';

    public const TYPE_PROGRAMME = 'programme';

    public const TYPE_CLASS = 'class';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_FACULTY,
        self::TYPE_SCHOOL,
        self::TYPE_DEPARTMENT,
        self::TYPE_PROGRAMME,
        self::TYPE_CLASS,
    ];

    /**
     * Types that may sit directly under a root. Faculty and school are the two
     * ways a top-level division is named, and they are alternatives rather than
     * levels, so both are allowed at the root.
     *
     * @var list<string>
     */
    public const ROOT_TYPES = [
        self::TYPE_FACULTY,
        self::TYPE_SCHOOL,
    ];

    /**
     * Which child types are meaningful under each parent type. Enforced in the
     * service, not the database, so the error can explain the rule.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_CHILDREN = [
        self::TYPE_FACULTY => [self::TYPE_DEPARTMENT, self::TYPE_CLASS],
        self::TYPE_SCHOOL => [self::TYPE_DEPARTMENT, self::TYPE_CLASS],
        self::TYPE_DEPARTMENT => [self::TYPE_PROGRAMME, self::TYPE_CLASS],
        self::TYPE_PROGRAMME => [self::TYPE_CLASS],
        self::TYPE_CLASS => [],
    ];

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'parent_id',
        'type',
        'name',
        'code',
        'depth',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function courseUnits(): HasMany
    {
        return $this->hasMany(CourseUnit::class);
    }

    /**
     * Every descendant, walking children breadth first.
     *
     * Used to refuse a reparent that would detach a whole subtree, and to
     * delete a branch cleanly.
     *
     * @return Collection<int, OrgUnit>
     */
    public function descendants(): Collection
    {
        $all = new Collection;
        $frontier = collect([$this]);

        while ($frontier->isNotEmpty()) {
            $children = OrgUnit::query()
                ->whereIn('parent_id', $frontier->pluck('id'))
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            $all = $all->merge($children);
            $frontier = $children;
        }

        return $all;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @return list<string>
     */
    public function allowedChildTypes(): array
    {
        return self::ALLOWED_CHILDREN[$this->type] ?? [];
    }
}
