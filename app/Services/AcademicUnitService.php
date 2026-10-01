<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\User;
use App\Repositories\Contracts\OrgUnitRepositoryInterface;
use App\Services\Contracts\AcademicUnitServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The academic hierarchy (ACD-02).
 *
 * The tree rules are enforced here rather than by the database, because a
 * foreign key cannot explain that a class is the end of the structure or that a
 * move must not create a loop. A teacher gets a sentence they can act on.
 */
class AcademicUnitService implements AcademicUnitServiceInterface
{
    public function __construct(private OrgUnitRepositoryInterface $orgUnits) {}

    public function list(User $user, ?int $parentId = null): Collection
    {
        if ($parentId === null) {
            return $this->orgUnits->allVisible($user);
        }

        return $this->orgUnits->childrenOf($parentId, $user);
    }

    public function find(int $id, User $user): OrgUnit
    {
        $unit = $this->orgUnits->findVisible($id, $user);

        if ($unit === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that unit in your institution. It may have been removed, or it may belong to another school.',
            ]);
        }

        return $unit;
    }

    public function create(User $user, array $data): OrgUnit
    {
        $type = $data['type'];
        $parentId = $data['parent_id'] ?? null;

        $this->assertKnownType($type);
        $this->assertCodeIsFree($user, $data['code'] ?? null);
        $this->assertParentAccepts($user, $parentId, $type);

        $attributes = [
            'name' => $data['name'],
            'type' => $type,
            'code' => $data['code'] ?? null,
            'parent_id' => $parentId,
            'depth' => $parentId === null ? 0 : $this->parentOf($user, $parentId)->depth + 1,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new OrgUnit;
        $model->assignTenant($user, $attributes);

        return $this->orgUnits->create($attributes);
    }

    public function update(int $id, User $user, array $data): OrgUnit
    {
        $unit = $this->find($id, $user);

        if (array_key_exists('code', $data)) {
            $this->assertCodeIsFree($user, $data['code'], $unit->id);
        }

        $attributes = array_filter(
            [
                'name' => $data['name'] ?? null,
                'code' => array_key_exists('code', $data) ? $data['code'] : null,
                'is_active' => $data['is_active'] ?? null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        return $this->orgUnits->update($unit, $attributes);
    }

    public function delete(int $id, User $user): void
    {
        $unit = $this->find($id, $user);

        $children = $this->orgUnits->childrenOf($unit->id, $user);

        if ($children->isNotEmpty()) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" still holds %d unit(s). Move or remove them first, so nothing is left without a place in the structure.',
                    $unit->name,
                    $children->count()
                ),
            ]);
        }

        if ($unit->courseUnits()->exists()) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" still has courses attached to it. Move those courses to another unit first.',
                    $unit->name
                ),
            ]);
        }

        $this->orgUnits->delete($unit);
    }

    public function move(int $id, User $user, ?int $newParentId): OrgUnit
    {
        return DB::transaction(function () use ($id, $user, $newParentId) {
            $unit = $this->find($id, $user);

            if ($newParentId === null) {
                $this->assertUnitCanBeTopLevel($unit);
            } else {
                $this->assertNotItsOwnParent($unit, $newParentId);
                $this->assertParentAccepts($user, $newParentId, $unit->type);
                $this->assertNotMovingBelowOwnChild($user, $unit, $newParentId);
            }

            $depth = $newParentId === null ? 0 : $this->parentOf($user, $newParentId)->depth + 1;
            $previousDepth = (int) $unit->depth;

            $moved = $this->orgUnits->update($unit, [
                'parent_id' => $newParentId,
                'depth' => $depth,
                'updated_by' => $user->id,
            ]);

            if ($depth !== $previousDepth) {
                $this->shiftSubtreeDepth($moved, $depth - $previousDepth);
            }

            return $moved;
        });
    }

    /**
     * Types outside the hierarchy are rejected before the tree rules, otherwise
     * the parent check would report a confusing "not allowed here" for a type
     * that simply does not exist.
     */
    private function assertKnownType(string $type): void
    {
        if (in_array($type, OrgUnit::TYPES, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'type' => sprintf(
                'That is not a kind of unit we recognise. Choose one of: %s.',
                implode(', ', OrgUnit::TYPES)
            ),
        ]);
    }

    private function assertCodeIsFree(User $user, ?string $code, ?int $exceptId = null): void
    {
        if ($code === null || trim($code) === '') {
            return;
        }

        if ($this->orgUnits->codeExistsFor($user, trim($code), $exceptId)) {
            throw ValidationException::withMessages([
                'code' => sprintf(
                    'The code "%s" is already used by another unit in your institution. Codes have to be unique so reports can be traced back.',
                    trim($code)
                ),
            ]);
        }
    }

    /**
     * The single gate every write goes through: a root must be a root type, and
     * a child must sit under a parent that accepts its type.
     */
    private function assertParentAccepts(User $user, ?int $parentId, string $type): void
    {
        if ($parentId === null) {
            if (in_array($type, OrgUnit::ROOT_TYPES, true)) {
                return;
            }

            throw ValidationException::withMessages([
                'parent_id' => sprintf(
                    'A %s cannot sit at the top of your structure. Only a faculty or a school can, so please choose the unit this one belongs under.',
                    $type
                ),
            ]);
        }

        $parent = $this->parentOf($user, $parentId);

        if ($parent->type === OrgUnit::TYPE_CLASS) {
            throw ValidationException::withMessages([
                'parent_id' => sprintf(
                    '"%s" is a class, and a class is the end of the structure, so nothing can be added beneath it. Pick the department or programme instead.',
                    $parent->name
                ),
            ]);
        }

        $allowed = $parent->allowedChildTypes();

        if (in_array($type, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_id' => sprintf(
                'A %s cannot be placed directly under a %s. Under a %s you can add: %s.',
                $type,
                $parent->type,
                $parent->type,
                $allowed === [] ? 'nothing' : implode(', ', $allowed)
            ),
        ]);
    }

    private function assertUnitCanBeTopLevel(OrgUnit $unit): void
    {
        if ($unit->isRoot()) {
            return;
        }

        if (! in_array($unit->type, OrgUnit::ROOT_TYPES, true)) {
            throw ValidationException::withMessages([
                'parent_id' => sprintf(
                    'A %s cannot become a top level unit, because a top level unit has to be a faculty or a school. Please choose the unit it belongs under.',
                    $unit->type
                ),
            ]);
        }

        $children = $unit->children()->exists();

        if ($children) {
            throw ValidationException::withMessages([
                'parent_id' => sprintf(
                    '"%s" already holds other units, so making it top level would leave them detached from the structure. Move those units first.',
                    $unit->name
                ),
            ]);
        }
    }

    private function assertNotItsOwnParent(OrgUnit $unit, int $newParentId): void
    {
        if ($unit->id === $newParentId) {
            throw ValidationException::withMessages([
                'parent_id' => sprintf(
                    '"%s" cannot be moved inside itself. Choose a different unit to place it under.',
                    $unit->name
                ),
            ]);
        }
    }

    /**
     * A loop is the failure this whole check exists to prevent: without it a
     * move would produce a tree with no root, and every walk of it would hang.
     */
    private function assertNotMovingBelowOwnChild(User $user, OrgUnit $unit, int $newParentId): void
    {
        $target = $unit->descendants()->firstWhere('id', $newParentId);

        if ($target === null) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_id' => sprintf(
                '"%s" already sits inside "%s". Moving it under one of its own units would create a loop in your structure, so please choose a parent outside that branch.',
                $target->name,
                $unit->name
            ),
        ]);
    }

    /**
     * Depths are stored so a list can be indented without walking the tree, so
     * a move has to carry the whole branch with it.
     */
    private function shiftSubtreeDepth(OrgUnit $unit, int $offset): void
    {
        foreach ($unit->descendants() as $descendant) {
            $descendant->forceFill([
                'depth' => ((int) $descendant->depth) + $offset,
                'updated_by' => $descendant->updated_by,
            ])->saveQuietly();
        }
    }

    private function parentOf(User $user, int $parentId): OrgUnit
    {
        $parent = $this->orgUnits->findVisible($parentId, $user);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'We could not find that unit in your institution. Please pick a unit from your own structure.',
            ]);
        }

        return $parent;
    }
}