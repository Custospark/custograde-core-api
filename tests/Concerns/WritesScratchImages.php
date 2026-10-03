<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Gives a test a private directory for images it writes to disk.
 *
 * These tests build scans and write them out so the identifier can read real
 * pixels. They used to write to the working directory, which during a test run is
 * the application root, so a green run left idn_ok.png, idn_retired.png and a
 * pile of QR fragments lying next to composer.json. Worse, those files were
 * committed, so a run that passed could still leave the working tree dirty and a
 * later diff could not tell a deliberate change from a leftover.
 *
 * The directory is created lazily and removed in tearDown, so a test that never
 * writes an image never creates one.
 */
trait WritesScratchImages
{
    private ?string $scratchDirectory = null;

    /**
     * An absolute path inside this test's private directory.
     *
     * Absolute because the callers hand the result to GD, to Symfony's
     * UploadedFile and to the identifier, and each of those resolves a relative
     * path against a different directory.
     */
    protected function scratch(string $name): string
    {
        if ($this->scratchDirectory === null) {
            $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'custograde-scratch-'.bin2hex(random_bytes(6));
            mkdir($dir, 0700, true);
            $this->scratchDirectory = $dir;
        }

        return $this->scratchDirectory.DIRECTORY_SEPARATOR.$name;
    }

    protected function tearDown(): void
    {
        $this->deleteScratchDirectory();

        parent::tearDown();
    }

    private function deleteScratchDirectory(): void
    {
        if ($this->scratchDirectory === null || ! is_dir($this->scratchDirectory)) {
            return;
        }

        foreach (glob($this->scratchDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->scratchDirectory);
        $this->scratchDirectory = null;
    }
}
