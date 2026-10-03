"""Print an OMR grid, rasterize it, and read the pixels back.

This is the equivalent of the QR round-trip test, and it exists for the same
reason. OmrLayout publishes millimetre coordinates and BubbleReader measures
them; nothing in a unit test proves the two describe the same page. Only
rendering the sheet, rasterizing it at print resolution, and asking the reader
what it says proves that the geometry a developer reads is the geometry that
comes off a printer.

The failure this catches is the quiet one. If the sheet drifts by two
millimetres, every objective answer is read from the wrong bubble, and because
the drift is uniform the results still look plausible: a candidate who answered
1a, 2c, 3b is reported as 1a, 2c, 3b on a sheet whose real answers were
something else. Nothing raises. Every candidate is marked, and the marks are
wrong in the same way, which reads as a plausible result rather than a bug.

So the check is deliberately end to end. Render the real PDF through dompdf,
rasterize with PDFium, mark some bubbles the way a pencil would, and ask the
reader what the candidate answered. Any disagreement between what was drawn,
what was intended and what was read is a failure, at any tolerance.

Run:  AI_Service/.venv/Scripts/python.exe Backend/scripts/omr_roundtrip.py
"""

from __future__ import annotations

import base64
import json
import subprocess
import sys
import tempfile
from pathlib import Path

BACKEND = Path(__file__).resolve().parents[1]

# A scan at the resolution a school flatbed or a decent phone produces.
DPI = 300
MM_PER_INCH = 25.4


def px(mm: float) -> int:
    return round(mm / MM_PER_INCH * DPI)


def main() -> int:
    if not (BACKEND / "artisan").exists():
        print(f"no artisan in {BACKEND}")
        return 1

    ensure_fixture()
    layout = json.loads(render_layout())
    print(f"layout declares {len(layout)} question(s) with bubbles")
    if not layout:
        print("no bubbled questions, so there is nothing to check")
        return 1

    pdf_path, page_index = render_sheet()
    image = rasterize(pdf_path, page_index)

    # Mark a different option per question, cycling through whatever that
    # question actually offers. Using the same option every time would let a
    # reader that simply always guessed one column still pass.
    marked = {}
    for index, question_id in enumerate(layout):
        letters = list(layout[question_id]["options"])
        marked[question_id] = letters[index % len(letters)]
    print("marking: " + ", ".join(f"q{q}={o}" for q, o in marked.items()))

    findings = check_blank(image, layout)
    findings += check_marked(image, layout, marked)

    for line in findings:
        print(f"  FAIL {line}")

    if findings:
        print(f"\n{len(findings)} problem(s). The reader and the printed sheet disagree.")
        return 1

    print("\nThe printed sheet and the reader agree on every bubble.")
    return 0


def ensure_fixture() -> None:
    """Make sure there is an exam with bubbled questions and a script on it."""
    PHP(
        r"""
        $script = omr_fixture_script();
        echo 'fixture: script ' . $script->id . ', exam ' . $script->exam_id . PHP_EOL;
        """
    )


def render_layout() -> str:
    """Ask the backend for the geometry it will print."""
    return PHP(
        r"""
        $s = omr_fixture_script();
        echo json_encode(App\Services\OmrLayout::forQuestions($s->exam->questions()->get()));
        """
    )


def render_sheet() -> tuple[Path, int]:
    """Render the real PDF and report which page holds the grid."""
    out = PHP(r"""
        $s = omr_fixture_script();
        $pdf = app(App\Services\AnswerSheetService::class)->render($s);
        $path = getenv('CUSTOGRADE_PDF_OUT');
        file_put_contents($path, $pdf);
        // Written pages come first, so the grid starts after them.
        $written = $s->exam->questions()->get()
            ->reject(fn ($q) => App\Services\OmrLayout::isBubbled($q));
        $pages = (int) ceil($written->count() / App\Services\AnswerSheetService::QUESTIONS_PER_PAGE);
        echo $pages;
    """, env={"CUSTOGRADE_PDF_OUT": str(tempfile.gettempdir()) + "/omr_rt.pdf"})
    pdf_path = Path(tempfile.gettempdir()) / "omr_rt.pdf"
    if not pdf_path.exists():
        raise SystemExit("the backend did not produce a PDF")
    return pdf_path, int(out)


def rasterize(pdf_path: Path, page_index: int):
    import pypdfium2 as pdfium

    pdf = pdfium.PdfDocument(str(pdf_path))
    # Saved as RGB rather than greyscale. A greyscale PNG is written as a palette
    # image, and reading one back through GD returns palette indices rather than
    # colours. BubbleReader copes with that now, but a real scan arrives as RGB,
    # so testing against RGB keeps the check honest about the actual input.
    return pdf[page_index].render(scale=DPI / 72).to_pil().convert("RGB")


def fill(image, cx_mm: float, cy_mm: float, diameter_mm: float) -> None:
    """Darken a disc, the way a pencil does: not perfectly, not centrally."""
    from PIL import ImageDraw

    radius = px(diameter_mm / 2) * 0.8
    draw = ImageDraw.Draw(image)
    draw.ellipse(
        [px(cx_mm) - radius, px(cy_mm) - radius, px(cx_mm) + radius, px(cy_mm) + radius],
        fill=40,
    )


def interior_is_light(image, cx_mm: float, cy_mm: float) -> bool:
    """Whether the middle of a printed bubble is still white."""
    values = []
    for dx in range(-2, 3):
        for dy in range(-2, 3):
            r, g, b = image.getpixel((px(cx_mm) + dx, px(cy_mm) + dy))[:3]
            values.append((0.299 * r) + (0.587 * g) + (0.114 * b))
    return sum(values) / len(values) > 200


def check_blank(image, layout: dict) -> list[str]:
    """An untouched sheet must have a printed ring exactly where the layout says.

    Checking that the pixel at the centre is white is not enough, and finding out
    that the hard way is why this looks the way it does. Blank paper is also
    white, so a sheet whose bubbles had drifted five millimetres sideways still
    passed, and the drift was only caught by looking at the picture. The centre
    pixel is now compared against a located ring instead.
    """
    rings = find_rings(image)
    problems = []

    for question_id, entry in layout.items():
        for letter, point in entry["options"].items():
            near = closest_ring(rings, point["x"], point["y"])

            if near is None:
                problems.append(
                    f"q{question_id}{letter} has no printed bubble near "
                    f"({point['x']}, {point['y']}), so there is nothing for a candidate to fill"
                )
                continue

            dx = near[0] - point["x"]
            dy = near[1] - point["y"]
            if abs(dx) > TOLERANCE_MM or abs(dy) > TOLERANCE_MM:
                problems.append(
                    f"q{question_id}{letter} printed at ({near[0]}, {near[1]}) "
                    f"but the reader looks at ({point['x']}, {point['y']}): "
                    f"off by {dx:+.2f}mm across, {dy:+.2f}mm down"
                )

    return problems


# How far the printed sheet may sit from the published coordinate.
#
# Comfortably larger than the 0.34mm dompdf rounds to, and comfortably smaller
# than the 5mm the table version drifted by. Half a millimetre is also well
# inside the ring's inner radius, so a sheet within tolerance is one the reader
# samples correctly.
TOLERANCE_MM = 0.5


def find_rings(image) -> list[tuple[float, float]]:
    """Locate printed bubbles by connected component.

    A ring is the one thing on the page that is a roughly 4.9mm square outline.
    Letters are about a millimetre tall and question numbers about three, so
    filtering by size and ink count separates them without any cleverness.
    """
    from PIL import Image

    px = image.load()
    width, height = image.size

    # Only the grid band. Fiducials and header text are outside it.
    band_top, band_bottom = 25, 80
    left, right = 12, 90

    def to_px(mm_value: float) -> int:
        return round(mm_value / MM_PER_INCH * DPI)

    def is_ink(pixel) -> bool:
        r, g, b = pixel[:3]
        return (0.299 * r) + (0.587 * g) + (0.114 * b) < 128

    dark = []
    for y in range(to_px(band_top), min(to_px(band_bottom), height)):
        for x in range(to_px(left), min(to_px(right), width)):
            if is_ink(px[x, y]):
                dark.append((x, y))

    index = {point: i for i, point in enumerate(dark)}
    parent = list(range(len(dark)))

    def find(i: int) -> int:
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i

    for i, (x, y) in enumerate(dark):
        for dx in (-1, 0, 1):
            for dy in (-1, 0, 1):
                j = index.get((x + dx, y + dy))
                if j is not None and j != i:
                    a, b = find(i), find(j)
                    if a != b:
                        parent[a] = b

    groups: dict[int, list[tuple[int, int]]] = {}
    for i, point in enumerate(dark):
        groups.setdefault(find(i), []).append(point)

    rings = []
    for points in groups.values():
        xs = [p[0] for p in points]
        ys = [p[1] for p in points]
        width_mm = (max(xs) - min(xs)) * MM_PER_INCH / DPI
        height_mm = (max(ys) - min(ys)) * MM_PER_INCH / DPI
        if 3.5 <= width_mm <= 6.5 and 3.5 <= height_mm <= 6.5 and len(points) > 60:
            rings.append(
                (
                    sum(xs) / len(xs) * MM_PER_INCH / DPI,
                    sum(ys) / len(ys) * MM_PER_INCH / DPI,
                )
            )

    return rings


def closest_ring(rings, x_mm: float, y_mm: float):
    if not rings:
        return None
    return min(rings, key=lambda r: (r[0] - x_mm) ** 2 + (r[1] - y_mm) ** 2)


def check_marked(image, layout: dict, marked: dict[int, str]) -> list[str]:
    """After marking, the reader must return exactly what was marked."""
    for question_id, letter in marked.items():
        point = layout[str(question_id)]["options"][letter]
        fill(image, point["x"], point["y"], 4.2)

    path = Path(tempfile.gettempdir()) / "omr_rt_marked.png"
    image.save(path)

    readings = PHP(
        r"""
        $s = omr_fixture_script();
        $layout = App\Services\OmrLayout::forQuestions($s->exam->questions()->get());
        $img = imagecreatefrompng(getenv('CUSTOGRADE_MARKED'));
        $reader = new App\Services\BubbleReader;
        $out = [];
        foreach ($reader->readPage($img, $layout, 1, (int) getenv('CUSTOGRADE_DPI')) as $id => $r) {
            $out[$id] = $r['status'] . ':' . ($r['option'] ?? '-');
        }
        echo json_encode($out);
        """,
        env={"CUSTOGRADE_MARKED": str(path), "CUSTOGRADE_DPI": str(DPI)},
    )

    got = json.loads(readings)
    problems = []
    for question_id, letter in marked.items():
        want = f"marked:{letter}"
        have = got.get(str(question_id), "missing")
        if have != want:
            problems.append(f"q{question_id} was marked {letter}, reader said {have}")
    return problems


def PHP(code: str, env: dict | None = None) -> str:
    """Run PHP inside the Laravel app so the real classes are used."""
    import os

    environment = dict(os.environ)
    if env:
        environment.update(env)

    script = f"<?php require '{BACKEND / 'vendor' / 'autoload.php'}';"
    script += f"$app = require '{BACKEND / 'bootstrap' / 'app.php'}';"
    script += "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
    script += FIXTURE_PHP
    script += code

    with tempfile.NamedTemporaryFile("w", suffix=".php", delete=False, encoding="utf-8") as handle:
        handle.write(script)
        path = handle.name

    try:
        result = subprocess.run(
            ["php", str(path)],
            cwd=BACKEND,
            capture_output=True,
            text=True,
            env=environment,
        )
        if result.returncode != 0:
            raise SystemExit(f"php failed: {result.stderr.strip()[:400]}")

        out = result.stdout.strip()

        # Laravel's exception handler prints to stdout and still exits zero, so a
        # broken snippet looks exactly like a successful one that returned nothing.
        # Left alone this script reported an empty layout as a JSON error three
        # layers away from the cause, which is not a useful place to be told.
        if "Exception" in out or "Fatal error" in out:
            raise SystemExit(f"php raised: {out[:400]}")
        if not out:
            raise SystemExit("php produced no output")

        return out
    finally:
        Path(path).unlink(missing_ok=True)


# Builds an exam with three four-option multiple choice questions and a script to
# print it on, reusing whatever already exists.
#
# A verification script that only runs against one developer's seeded database is
# a script that stops running the moment somebody else clones the repo, and fails
# silently in the meantime because nobody is looking. So this builds its own
# fixture rather than hoping one is there.
FIXTURE_PHP = r"""
function omr_fixture_script(): App\Models\Script {
    $script = App\Models\Script::whereHas(
        'exam.questions',
        fn ($q) => $q->where('kind', 'multiple_choice')
    )->first();

    if ($script) {
        return $script;
    }

    $institution = App\Models\Institution::first() ?? App\Models\Institution::create([
        'name' => 'OMR Fixture School',
        'type' => 'Secondary School',
        'email' => 'omr@fixture.test',
        'status' => 'active',
    ]);

    $owner = App\Models\User::where('institution_id', $institution->id)->first();
    if (! $owner) {
        $owner = App\Models\User::create([
            'institution_id' => $institution->id,
            'account_type' => 'institutional',
            'name' => 'OMR Fixture',
            'email' => 'omr-owner@fixture.test',
            'role' => 'institution_admin',
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
        ]);
    }

    $course = App\Models\CourseUnit::firstOrCreate(
        ['institution_id' => $institution->id, 'code' => 'OMR101'],
        ['owner_user_id' => $owner->id, 'title' => 'Objective Reading', 'is_active' => true]
    );

    $exam = App\Models\Exam::create([
        'institution_id' => $institution->id,
        'owner_user_id' => $owner->id,
        'course_unit_id' => $course->id,
        'title' => 'Objective Reading Fixture',
        'type' => 'end_of_term',
        'exam_date' => now()->toDateString(),
        'status' => 'marking',
    ]);

    foreach ([1, 2, 3] as $number) {
        $exam->questions()->create([
            'number' => $number,
            'prompt' => 'Fixture question ' . $number,
            'kind' => 'multiple_choice',
            'max_mark' => 1,
            'granularity' => 1,
            'options' => ['options' => ['alpha', 'beta', 'gamma', 'delta']],
        ]);
    }

    $student = App\Models\Student::create([
        'institution_id' => $institution->id,
        'owner_user_id' => $owner->id,
        'reg_no' => 'OMR-' . $exam->id,
        'first_name' => 'Ola',
        'last_name' => 'Objective',
        'status' => 'active',
    ]);

    // The code format matches the controller's, so a fixture sheet is
    // indistinguishable from a real one if it is ever opened in the app.
    $code = sprintf('CG-%05d-%s', $exam->id, strtoupper(bin2hex(random_bytes(3))));

    return $exam->scripts()->create([
        'institution_id' => $institution->id,
        'owner_user_id' => $owner->id,
        'student_id' => $student->id,
        'code' => $code,
        'status' => App\Models\Script::STATUS_ISSUED,
    ]);
}
"""

if __name__ == "__main__":
    raise SystemExit(main())
