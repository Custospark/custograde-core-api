<?php

namespace App\Services;

use App\Models\CourseResource;
use App\Models\CourseUnit;
use App\Models\User;
use App\Repositories\Contracts\CourseResourceRepositoryInterface;
use App\Repositories\Contracts\CourseUnitRepositoryInterface;
use App\Services\Contracts\CourseResourceServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Course documents (ACD-02, EXM-03, EXM-07).
 *
 * Two decisions shape this service. Files go to a private disk, never a public
 * path, because a marking guide is not something to serve by guessing a URL.
 * And a re-upload supersedes rather than overwrites, because an examination
 * marked against an earlier guide has to stay re-derivable afterwards (EXM-07).
 */
class CourseResourceService implements CourseResourceServiceInterface
{
    /**
     * 20 MB in kilobytes, the unit a Laravel upload rule is written in.
     *
     * A marking guide is a document a teacher keeps on a phone before a session,
     * not a scan of a whole script, so a generous but finite ceiling.
     */
    public const MAX_UPLOAD_KILOBYTES = 20 * 1024;

    /** Private by design. Nothing here is servable straight from the web root. */
    public const DISK = 'local';

    public function __construct(
        private CourseResourceRepositoryInterface $resources,
        private CourseUnitRepositoryInterface $courseUnits,
    ) {}

    public function listForCourse(int $courseUnitId, User $user, bool $currentOnly = true): Collection
    {
        // A read of a course that is not there is a 404, not a validation
        // failure. Sharing the upload guard with this path answered a GET with 422
        // and a message about a document not being uploaded, which is not what
        // happened and left the page stuck on a spinner.
        //
        // 404 rather than 403 on purpose: another school's course unit must be
        // indistinguishable from one that does not exist, or this endpoint lets a
        // caller enumerate every course id in the platform.
        if ($this->courseUnits->findVisible($courseUnitId, $user) === null) {
            throw (new ModelNotFoundException)->setModel(CourseUnit::class, [$courseUnitId]);
        }

        return $this->resources->forCourseUnit($courseUnitId, $user, $currentOnly);
    }

    public function find(int $id, User $user): CourseResource
    {
        $resource = $this->resources->findVisible($id, $user);

        if ($resource === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that document on any of your courses. It may have been removed.',
            ]);
        }

        return $resource;
    }

    public function upload(User $user, int $courseUnitId, UploadedFile $file, array $data = []): CourseResource
    {
        $courseUnit = $this->assertCourseIsVisible($user, $courseUnitId);

        $kind = $data['kind'] ?? CourseResource::KIND_OTHER;
        $this->assertKindIsKnown($kind);
        $this->assertFileIsAcceptable($file);

        $attributes = [
            'course_unit_id' => $courseUnit->id,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new CourseResource;
        $model->assignTenant($user, $attributes);

        return DB::transaction(function () use ($user, $courseUnit, $file, $kind, $attributes) {
            $previous = $this->resources->latestVersionFor($courseUnit->id, $kind, $user);

            $attributes['version'] = $previous === null ? 1 : (int) $previous->version + 1;
            $attributes['is_current'] = true;
            $attributes['disk'] = self::DISK;
            $attributes['path'] = $this->storeFile($file, $courseUnit, $kind);

            if ($previous !== null) {
                $this->resources->update($previous, ['is_current' => false]);
            }

            return $this->resources->create($attributes);
        });
    }

    public function update(int $id, User $user, array $data): CourseResource
    {
        $resource = $this->find($id, $user);

        if (array_key_exists('kind', $data)) {
            $this->assertKindIsKnown($data['kind']);
        }

        return $this->resources->update($resource, array_filter(
            [
                'title' => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'kind' => $data['kind'] ?? null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        ));
    }

    public function delete(int $id, User $user): void
    {
        $resource = $this->find($id, $user);

        // Row and file together, so a half-deleted document never leaves an
        // orphaned file behind holding storage.
        Storage::disk($resource->disk ?: self::DISK)->delete($resource->path);

        $this->resources->delete($resource);
    }

    public function setCurrentVersion(int $id, User $user): CourseResource
    {
        return DB::transaction(function () use ($id, $user) {
            $resource = $this->find($id, $user);

            foreach ($this->resources->forCourseUnit((int) $resource->course_unit_id, $user, false) as $sibling) {
                if ($sibling->kind === $resource->kind && $sibling->id !== $resource->id) {
                    $this->resources->update($sibling, ['is_current' => false]);
                }
            }

            return $this->resources->update($resource, [
                'is_current' => true,
                'updated_by' => $user->id,
            ]);
        });
    }

    /**
     * The path is built from the course, the kind and a timestamp rather than
     * the client's filename alone, so two uploads cannot collide and a name
     * cannot be used to write somewhere unexpected.
     */
    private function storeFile(UploadedFile $file, CourseUnit $courseUnit, string $kind): string
    {
        $extension = $file->getClientOriginalExtension();
        $stem = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        $filename = sprintf(
            '%s-%d-%s',
            $kind,
            now()->getTimestamp(),
            $stem === '' ? 'document' : $stem.($extension === '' ? '' : '.'.$extension)
        );

        $directory = sprintf('courses/%d', $courseUnit->id);

        $path = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'We could not save that document just now. Please try the upload again in a moment.',
            ]);
        }

        return $path;
    }

    /**
     * The guard for write paths, where a helpful message beats a bare 404.
     *
     * Somebody who picked the wrong course in a form needs to be told which field
     * is wrong and what to do about it. Somebody listing resources on a course
     * that is not theirs needs only for the request to fail, so that path uses its
     * own check and does not come through here.
     */
    private function assertCourseIsVisible(User $user, int $courseUnitId): CourseUnit
    {
        $courseUnit = $this->courseUnits->findVisible($courseUnitId, $user);

        if ($courseUnit === null) {
            throw ValidationException::withMessages([
                'course_unit_id' => 'We could not find that course in your institution, so the document was not uploaded. Please pick a course from your own list.',
            ]);
        }

        return $courseUnit;
    }

    private function assertKindIsKnown(string $kind): void
    {
        if (in_array($kind, CourseResource::KINDS, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'kind' => sprintf(
                'That is not a kind of document we keep on a course. Please choose one of: %s.',
                implode(', ', CourseResource::KINDS)
            ),
        ]);
    }

    /**
     * The accepted list is EXM-03's, and a marking guide has to be something the
     * reading service can actually open later, so an image or a spreadsheet is
     * refused by name rather than by a file extension the client controls.
     */
    private function assertFileIsAcceptable(UploadedFile $file): void
    {
        $sizeKb = (int) ceil($file->getSize() / 1024);

        if ($sizeKb > self::MAX_UPLOAD_KILOBYTES) {
            throw ValidationException::withMessages([
                'file' => sprintf(
                    '"%s" is %d MB, which is over the %d MB limit for a course document. Please upload a smaller file, or split it into parts.',
                    $file->getClientOriginalName(),
                    (int) ceil($sizeKb / 1024),
                    (int) (self::MAX_UPLOAD_KILOBYTES / 1024)
                ),
            ]);
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, CourseResource::ACCEPTED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => sprintf(
                    'We cannot read "%s" as a course document. Please upload a PDF, a Word document (.doc or .docx), or a plain text file (.txt).',
                    $file->getClientOriginalName()
                ),
            ]);
        }
    }
}