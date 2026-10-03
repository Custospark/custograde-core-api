<?php

namespace App\Services;

use App\Models\Script;

/**
 * The opaque identifier printed on a sheet and encoded in its QR (SHT-02).
 *
 * SHT-02 is unusually specific: "encode in each code only an opaque script
 * identifier with a check value and signature, and shall not embed personal
 * data in the code." That is a privacy requirement with teeth, because a sheet
 * is photographed, shared and posted online. A name or registration number in
 * the QR turns every leaked photograph into a disclosure of who sat the paper.
 *
 * So the payload carries four things and nothing else:
 *
 *   code   the script's existing opaque code, e.g. CG-00009-8A1AB5
 *   page   this page's number, so separated pages re-associate (SHT-03)
 *   total  how many pages the sheet has, so a missing page is detectable
 *   sig    an HMAC over the first three
 *
 * The code is deliberately NOT encrypted, and that is a measured decision
 * rather than an oversight. Encrypting it produced a 220 character payload,
 * which a QR has to spread across roughly a hundred modules, and the result was
 * measured UNREADABLE when printed at 16mm and scanned at 300dpi, which is
 * ordinary A4 handling. The short signed form measures as decodable at the same
 * size. SHT-02 asks for opacity and a signature, not confidentiality, and the
 * script code is already unguessable, so signing is what was wanted and
 * encryption only broke SHT-08.
 *
 * The lesson is recorded because it is easy to repeat. Anything drawn as a QR
 * has a module budget, and spending it on confidentiality trades away the very
 * thing the code was for.
 */
class SheetCodeService
{
    /**
     * Version prefix. A payload carries the scheme that produced it so a future
     * change can decode old sheets rather than orphaning every paper already
     * printed and sitting in a box somewhere.
     */
    private const VERSION = 'S1';

    /**
     * Build the value encoded into the QR for one page of one sheet.
     */
    public function encode(Script $script, int $page = 1, ?int $totalPages = null): string
    {
        $total = max(1, $totalPages ?? ((int) $script->page_count ?: 1));
        $page = max(1, $page);

        $payload = self::VERSION.'.'.$script->code.'.'.$page.'.'.$total;

        return $payload.'.'.$this->signature($payload);
    }

    /**
     * Recover the payload from a scanned code, or null when the code is not one
     * we issued. Null rather than an exception because the expected outcome on a
     * scan is genuinely "this is not our paper", which routes the page to the
     * exception queue instead of raising.
     *
     * Returns the script code rather than an id, because turning a code into a
     * script is a tenant-scoped query and belongs to the caller that has a user.
     *
     * @return array{code: string, page: int, total: int}|null
     */
    public function decode(string $code): ?array
    {
        $parts = explode('.', trim($code));

        if (count($parts) !== 5 || $parts[0] !== self::VERSION) {
            return null;
        }

        [$version, $scriptCode, $page, $total, $signature] = $parts;

        $payload = implode('.', [$version, $scriptCode, $page, $total]);

        // Verified in constant time, and before the payload is trusted rather
        // than after, so a forged code never reaches a lookup.
        if (! hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        if (! ctype_digit($page) || ! ctype_digit($total)) {
            return null;
        }

        return [
            'code' => $scriptCode,
            'page' => max(1, (int) $page),
            'total' => max(1, (int) $total),
        ];
    }

    /**
     * A short MAC over the payload.
     *
     * Short because it is printed inside a QR that has a module budget: every
     * character here costs modules across the whole symbol. Twelve hex digits is
     * ample for the one job it has, which is rejecting codes we never issued.
     */
    private function signature(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 12);
    }

    /**
     * The human-readable fallback printed beside the QR, for when a phone camera
     * will not focus on a damaged or smudged code.
     *
     * This is the script's existing opaque code plus the page number, not a
     * second encoding of the payload. Verification is therefore a database
     * lookup rather than a signature check, which is a different mechanism from
     * `decode()` on purpose: a value a human read off paper should be checked
     * against what we actually issued, not against a MAC computed on the spot.
     *
     * @return array{code: string, label: string}
     */
    public function humanReadable(Script $script, int $page = 1, ?int $totalPages = null): array
    {
        $total = max(1, $totalPages ?? (int) $script->page_count);

        return [
            'code' => $script->code,
            'label' => sprintf('%s  page %d of %d', $script->code, $page, $total),
        ];
    }
}