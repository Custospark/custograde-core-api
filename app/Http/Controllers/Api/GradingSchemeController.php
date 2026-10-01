<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplaceGradeBandsRequest;
use App\Http\Requests\StoreGradingSchemeRequest;
use App\Http\Requests\UpdateGradingSchemeRequest;
use App\Http\Resources\GradeBandResource;
use App\Http\Resources\GradingSchemeCollection;
use App\Http\Resources\GradingSchemeResource;
use App\Models\GradingScheme;
use App\Models\User;
use App\Services\Contracts\GradingSchemeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GradingSchemeController extends Controller
{
    public function __construct(
        protected GradingSchemeServiceInterface $schemes,
    ) {}

    /**
     * Every scheme visible to this user. Bands are not eager loaded for the
     * list, because a school rarely has more than a handful of schemes and a
     * band set per scheme would dominate the payload.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): GradingSchemeCollection
    {
        return new GradingSchemeCollection($this->schemes->list($request->user()));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreGradingSchemeRequest $request): JsonResponse
    {
        $scheme = $this->schemes->create($request->user(), $request->validated());

        return response()->json(new GradingSchemeResource($scheme->load('bands')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): GradingSchemeResource|JsonResponse
    {
        $scheme = $this->findOrRefuse((int) $id, $request->user());

        return $scheme instanceof GradingScheme
            ? new GradingSchemeResource($scheme->load('bands'))
            : $scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateGradingSchemeRequest $request, string $id): GradingSchemeResource|JsonResponse
    {
        $scheme = $this->findOrRefuse((int) $id, $request->user());

        if (! $scheme instanceof GradingScheme) {
            return $scheme;
        }

        $updated = $this->schemes->update($scheme->id, $request->user(), $request->validated());

        return new GradingSchemeResource($updated->load('bands'));
    }

    /**
     * Swaps the bands for a new set. The service refuses the whole set if any
     * percentage is covered twice or not at all, and lists every problem found,
     * so the teacher can fix all of them in one pass.
     *
     * @return array<string, mixed>
     */
    public function replaceBands(ReplaceGradeBandsRequest $request, string $id): JsonResponse
    {
        $scheme = $this->findOrRefuse((int) $id, $request->user());

        if (! $scheme instanceof GradingScheme) {
            return $scheme;
        }

        $bands = $this->schemes->replaceBands(
            $scheme->id,
            $request->user(),
            $request->validated()['bands'],
        );

        return response()->json([
            'message' => sprintf('The grade bands for "%s" have been replaced.', $scheme->name),
            'bands' => GradeBandResource::collection($bands),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $scheme = $this->findOrRefuse((int) $id, $request->user());

        if (! $scheme instanceof GradingScheme) {
            return $scheme;
        }

        $this->schemes->delete($scheme->id, $request->user());

        return response()->json([
            'message' => sprintf('The grading scheme "%s" has been deleted.', $scheme->name),
        ]);
    }

    /**
     * A scheme the caller cannot see is reported exactly like one that does not
     * exist, so another school's grading setup cannot be probed by id.
     */
    private function findOrRefuse(int $id, User $user): GradingScheme|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that grading scheme.'], 404);
        }

        try {
            return $this->schemes->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that grading scheme.'], 404);
            }

            throw $exception;
        }
    }
}