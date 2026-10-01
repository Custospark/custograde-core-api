<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermRequest;
use App\Http\Requests\UpdateTermRequest;
use App\Http\Resources\TermCollection;
use App\Http\Resources\TermResource;
use App\Models\Term;
use App\Models\User;
use App\Services\Contracts\TermServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TermController extends Controller
{
    public function __construct(
        protected TermServiceInterface $terms,
    ) {}

    /**
     * Terms for the whole tenant, or one academic year when filtered. The
     * service applies the tenant scope, so the controller passes no user filter
     * of its own and cannot widen the list by accident.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): TermCollection
    {
        $academicYearId = $request->integer('academic_year_id') ?: null;

        return new TermCollection(
            $this->terms->list($request->user(), $academicYearId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreTermRequest $request): JsonResponse
    {
        $term = $this->terms->create($request->user(), $request->validated());

        return response()->json(new TermResource($term->load('academicYear')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): TermResource|JsonResponse
    {
        $term = $this->findOrRefuse((int) $id, $request->user());

        return $term instanceof Term
            ? new TermResource($term->load('academicYear'))
            : $term;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateTermRequest $request, string $id): TermResource|JsonResponse
    {
        $term = $this->findOrRefuse((int) $id, $request->user());

        if (! $term instanceof Term) {
            return $term;
        }

        $updated = $this->terms->update($term->id, $request->user(), $request->validated());

        return new TermResource($updated->load('academicYear'));
    }

    /**
     * Opens the term and closes whichever other term is running in the same
     * year. That pair is one write inside the service, so it is not attempted
     * here as two separate calls.
     *
     * @return array<string, mixed>
     */
    public function activate(Request $request, string $id): TermResource|JsonResponse
    {
        $term = $this->findOrRefuse((int) $id, $request->user());

        if (! $term instanceof Term) {
            return $term;
        }

        $active = $this->terms->setActive($term->id, $request->user());

        return new TermResource($active->load('academicYear'));
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $term = $this->findOrRefuse((int) $id, $request->user());

        if (! $term instanceof Term) {
            return $term;
        }

        $this->terms->delete($term->id, $request->user());

        return response()->json(['message' => sprintf('The term "%s" has been deleted.', $term->name)]);
    }

    /**
     * The service throws a readable ValidationException for a term outside the
     * caller's institution, so this is only the shape guard. A term the caller
     * cannot see is reported the same way as one that does not exist, which is
     * what stops existence leaking between schools.
     */
    private function findOrRefuse(int $id, User $user): Term|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that term.'], 404);
        }

        try {
            return $this->terms->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that term.'], 404);
            }

            throw $exception;
        }
    }
}