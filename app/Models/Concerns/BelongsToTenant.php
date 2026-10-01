<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two-branch tenant scope for every tenant-owned model (ADR-002).
 *
 * A personal account has a NULL institution_id and is its own tenant. An
 * institutional account owns its rows through institution_id. Every
 * tenant-owned table therefore carries BOTH columns, and exactly one is set.
 *
 * The alternative - a placeholder institution row per solo teacher - would let
 * every query read the same way, at the cost of a fiction that leaks into
 * reporting and billing later. The branch is unavoidable either way, so it is
 * made explicit and lives in one place.
 *
 * Usage on a migration:
 *   $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
 *   $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
 *
 * Usage on a model:
 *   use BelongsToTenant;
 *   protected array $tenantColumns = ['institution_id', 'owner_user_id'];
 */
trait BelongsToTenant
{
    /**
     * The columns that carry tenancy. Overridable so a table that only ever
     * hangs off an institution can narrow this.
     *
     * @var list<string>
     */
    protected array $tenantColumns = ['institution_id', 'owner_user_id'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Institution::class, 'institution_id');
    }

    /**
     * The user who owns this row directly, set only for personal accounts.
     *
     * Named ownerUser rather than owner to avoid colliding with the
     * Institution::owner() relation, which points at the institutional owner.
     */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Restrict a query to what this user is allowed to see.
     *
     * An institutional user sees their whole institution. A personal user sees
     * only rows they own. A user with no institution and no rows of their own
     * sees nothing, which is the safe default for a malformed token.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->institution_id !== null) {
            return $query->where($this->qualifyColumn('institution_id'), $user->institution_id);
        }

        return $query->where($this->qualifyColumn('owner_user_id'), $user->id);
    }

    /**
     * Apply the tenant columns to a new row.
     *
     * Called by the services rather than set by the controller, so a client
     * cannot choose which tenant it writes into.
     *
     * @param array<string, mixed> $attributes
     */
    public function assignTenant(User $user, array &$attributes): void
    {
        $attributes['institution_id'] = $user->institution_id;
        $attributes['owner_user_id'] = $user->isPersonal() ? $user->id : null;
    }

    /**
     * True when exactly one tenant column is populated.
     *
     * A row with both set belongs to an institution and to a user, which would
     * make visibility ambiguous. A row with neither is orphaned.
     */
    public function hasValidTenant(): bool
    {
        $values = array_map(
            fn (string $column): bool => $this->getAttribute($column) !== null,
            $this->tenantColumns
        );

        return count(array_filter($values)) === 1;
    }
}
