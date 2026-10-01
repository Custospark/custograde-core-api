<?php

namespace App\Services\Contracts;

use App\Models\CourseResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

/**
 * Reference documents attached to a course (ACD-02, EXM-03, EXM-07).
 *
 * Files live on a private disk and are never exposed under a public path, so a
 * past paper cannot be fetched by guessing a URL.
 */
interface CourseResourceServiceInterface
{
    /**
     * Documents on one course, current versions only unless asked otherwise.
     *
     * @return Collection<int, CourseResource>
     */
    public function listForCourse(int $courseUnitId, User $user, bool $currentOnly = true): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): CourseResource;

    /**
     * Store a document and mark it as the current version of its kind.
     *
     * Uploading the same kind again supersedes the previous version rather than
     * overwriting it, so earlier results stay re-derivable (EXM-07).
     *
     * @param array{title?: string|null, description?: string|null, kind?: string} $data
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function upload(User $user, int $courseUnitId, UploadedFile $file, array $data = []): CourseResource;

    /**
     * Metadata only. The stored file is never replaced by an edit, because a
     * new document is a new version.
     *
     * @param array{title?: string|null, description?: string|null, kind?: string} $data
     */
    public function update(int $id, User $user, array $data): CourseResource;

    /**
     * Removes the row and the stored file together.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(int $id, User $user): void;

    /**
     * Roll one version back to being the current document of its kind, without
     * deleting the newer upload.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setCurrentVersion(int $id, User $user): CourseResource;
}