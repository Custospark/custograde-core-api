<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use Illuminate\Support\Facades\Log;

/**
 * Reads the code off a scan and works out which script it belongs to (IDN-02).
 *
 * Until now a sheet carried a code nobody read, so identification was a person
 * picking a candidate from a dropdown. Fine for one paper, hopeless for thirty,
 * and it is the step between "works" and "scales".
 *
 * Two measurements shaped this, and both contradicted the obvious approach.
 *
 * Decoding a whole A4 page does not work at all. The code is 16mm on a 210mm
 * sheet, so it occupies about 120 pixels of a 1654 pixel wide page, and the
 * detector reported "could not find enough finder patterns" after 14 seconds and
 * 300MB. Enlarging the page first was worse: upscaling three times asked for a
 * 34 megapixel buffer and exhausted a 1GB limit outright.
 *
 * Cropping to the region the code actually occupies decoded it exactly, in 1.3
 * seconds and 82MB. So this crops rather than scaling, and it crops to a fixed
 * set of regions covering where a sheet puts its code, since we control the
 * template and know the answer is near a corner.
 *
 * A second consequence is that identification runs in the queue, not in the
 * upload request. At 1.3 seconds a successful attempt, and several seconds for a
 * page where nothing reads, adding that to an upload would make the scanner feel
 * broken on exactly the bad scans where the operator most needs feedback.
 *
 * Every failure returns null rather than throwing. "We could not read this one"
 * is an ordinary outcome that routes the paper to the exception queue; an
 * exception here would lose a real script.
 *
 * Measured on a 200dpi A4 scan: 1.6 seconds when the code reads, 17.6 seconds
 * when nothing on the page reads at all, 102MB peak.
 */
class ScanIdentifier
{
    /**
     * Longest edge, in pixels, of a region handed to the detector.
     *
     * Decode cost scales with area. A 700x500 crop was measured reading a 16mm
     * code exactly, while a full 1654x2339 A4 page took 14 seconds and then
     * failed anyway. Capping here is what keeps an unreadable page at seconds
     * rather than a minute, which is the difference between a queue that drains
     * and one that does not.
     */
    private const MAX_CROP_EDGE = 900;

    /**
     * Regions to try, as fractions of width and height.
     *
     * Our template prints the code at the top right of every page, so that is
     * first. The others are insurance against a page that was fed in upside down
     * or from a template we did not produce, which is precisely the exception
     * queue's job.
     *
     * @var list<array{0: float, 1: float, 2: float, 3: float}>
     */
    private const REGIONS = [
        // x, y, width, height as fractions. Top right, where we print it.
        [0.55, 0.02, 0.45, 0.35],
        // Top left, for a sheet fed in mirrored or a different template.
        [0.00, 0.02, 0.45, 0.35],
        // Wide top band, catching a code placed centrally in a header.
        [0.00, 0.00, 1.00, 0.40],
    ];

    /**
     * Identify a stored scan.
     *
     * `$maxRegions` and `$thorough` exist because the caller has to decide
     * something this class cannot know.
     *
     * The capture path runs inside a web request, so it makes exactly one
     * attempt: our own template, one region, one variant. That reads a code in
     * about 1.4 seconds and, crucially, gives up quickly rather than making an
     * operator watch a spinner on a bad scan. Anything it misses stays
     * unidentified and gets a deeper queued search afterwards.
     *
     * Measured, 200dpi A4: quick path 1.4s on success, 1.5s on failure.
     * Thorough search, all regions and variants: 1.6s on success, 17.6s when
     * nothing on the page reads.
     *
     * @return array{code: string, page: int, total: int}|null
     */
    public function identifyFromPath(
        string $absolutePath,
        string $mimeType,
        int $maxRegions = 1,
        bool $thorough = false,
    ): ?array {
        if (! $this->canAttempt($mimeType)) {
            return null;
        }

        $image = $this->load($absolutePath);

        if ($image === null) {
            return null;
        }

        try {
            foreach (array_slice(self::REGIONS, 0, max(1, $maxRegions)) as $region) {
                $crop = $this->crop($image, $region);

                if ($crop === null) {
                    continue;
                }

                $found = $thorough ? $this->readFirstCode($crop) : $this->readCode($crop);

                imagedestroy($crop);

                if ($found === null) {
                    continue;
                }

                // The signature is verified here, so a random string that happens
                // to scan, or another school's code, never reaches a lookup.
                $decoded = app(SheetCodeService::class)->decode($found);

                if ($decoded !== null) {
                    return $decoded;
                }
            }
        } catch (\Throwable $exception) {
            Log::info('Scan identification gave up', ['reason' => $exception->getMessage()]);
        } finally {
            imagedestroy($image);
        }

        return null;
    }

    /**
     * Only raster images can be decoded here. A PDF would need rasterising, which
     * is page-assembly work (IDN-01); PDF uploads simply stay unidentified rather
     * than failing.
     */
    private function canAttempt(string $mimeType): bool
    {
        return in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'], true);
    }

    /**
     * @return \GdImage|null
     */
    private function load(string $path)
    {
        if (! is_file($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        return $image === false ? null : $image;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $region
     * @return \GdImage|null
     */
    private function crop($image, array $region)
    {
        $pageWidth = imagesx($image);
        $pageHeight = imagesy($image);

        $width = (int) round($pageWidth * $region[2]);
        $height = (int) round($pageHeight * $region[3]);
        $left = (int) round($pageWidth * $region[0]);
        $top = (int) round($pageHeight * $region[1]);

        $width = min($width, $pageWidth - $left);
        $height = min($height, $pageHeight - $top);

        // Too small to hold a code, and decoding it would only burn time.
        if ($width < 60 || $height < 60) {
            return null;
        }

        $crop = imagecreatetruecolor($width, $height);
        imagecopy($crop, $image, 0, 0, $left, $top, $width, $height);

        return $this->cap($crop);
    }

    /**
     * Shrink a crop so neither edge exceeds the cap, keeping it square-ish so the
     * code's aspect is not distorted.
     *
     * @param  GdImage  $crop
     * @return GdImage
     */
    private function cap($crop)
    {
        $width = imagesx($crop);
        $height = imagesy($crop);
        $longest = max($width, $height);

        if ($longest <= self::MAX_CROP_EDGE) {
            return $crop;
        }

        $scale = self::MAX_CROP_EDGE / $longest;
        $smaller = imagecreatetruecolor(
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale))
        );

        imagecopyresampled(
            $smaller,
            $crop,
            0, 0, 0, 0,
            imagesx($smaller), imagesy($smaller),
            $width, $height
        );

        imagedestroy($crop);

        return $smaller;
    }

    /**
     * Try a crop as-is, then greyscaled, then with the tones crushed.
     *
     * `imagefilter` rather than a per-pixel loop: the loop version spent most of a
     * second on a single megapixel crop and the filters do the same work in C.
     *
     * @param  \GdImage  $crop
     */
    private function readFirstCode($crop): ?string
    {
        $raw = $this->readCode($crop);

        if ($raw !== null) {
            return $raw;
        }

        $grey = imagecreatetruecolor(imagesx($crop), imagesy($crop));
        imagecopy($grey, $crop, 0, 0, 0, 0, imagesx($crop), imagesy($crop));
        imagefilter($grey, IMG_FILTER_GRAYSCALE);

        $raw = $this->readCode($grey);

        if ($raw !== null) {
            imagedestroy($grey);

            return $raw;
        }

        // Push the tones apart, which is what rescues a photograph with a shadow
        // across it, where the paper is not actually white.
        imagefilter($grey, IMG_FILTER_CONTRAST, -60);
        $raw = $this->readCode($grey);
        imagedestroy($grey);

        return $raw;
    }

    /**
     * @param  \GdImage  $image
     */
    private function readCode($image): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'idn').'.png';
        imagepng($image, $path);

        try {
            $value = trim((string) (new QRCode)->readFromFile($path));

            return $value === '' ? null : $value;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($path);
        }
    }
}