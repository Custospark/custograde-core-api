{{--
    The printable answer sheet (SHT-01, SHT-03, SHT-04, SHT-08).

    Everything here is black on white. SHT-08 requires the sheet to survive
    monochrome printing on A4, and the usual failure is a pale grey rule that
    disappears on a photocopier, taking the answer box boundary with it. So no
    greys below 30 percent, and the answer borders are a solid 0.6mm.

    The four corner squares are fiducial marks. They exist so page geometry can
    be normalised from a photo taken at whatever angle the paper was lying at,
    and they are sized and spaced identically on every page so detection does
    not have to be told which page it is looking at.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Answer sheet {{ $script->code }}</title>
    <style>
        @page { margin: 14mm 12mm 12mm 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
        }

        /*
            Fiducials must sit in the same physical corner on every page, otherwise
            page detection cannot find them without being told which page it is
            looking at. In-flow cells cannot do that, because they land after the
            content rather than at the page edge.

            So they are fixed and pulled out into the page margin with negative
            offsets. The offsets are derived from the @page margins above: the top
            margin is 14mm and the side margins are 12mm, so -11mm and -9mm put
            each 6mm mark just inside the physical sheet edge while still clearing
            the content box.
        */
        .fiducial {
            position: fixed;
            width: 6mm;
            height: 6mm;
            background: #000;
        }
        .fiducial.tl { top: -11mm; left: -9mm; }
        .fiducial.tr { top: -11mm; right: -9mm; }
        .fiducial.bl { bottom: -9mm; left: -9mm; }
        .fiducial.br { bottom: -9mm; right: -9mm; }

        .sheet-header {
            border-bottom: 0.5mm solid #000;
            padding-bottom: 3mm;
            margin-bottom: 4mm;
        }

        .institution { font-size: 9pt; font-weight: bold; letter-spacing: 0.4pt; }
        .exam-title { font-size: 13pt; font-weight: bold; margin-top: 1mm; }

        .identity { display: table; width: 100%; margin-top: 3mm; border-spacing: 0; }
        .identity .cell { display: table-cell; vertical-align: top; padding-right: 6mm; }
        .identity .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.3pt; }
        .identity .value { font-size: 11pt; font-weight: bold; border-bottom: 0.4mm solid #000; padding: 1mm 0; }

        /*
            The code block. The QR is the machine-readable path and the printed
            string beneath it is the fallback for a damaged code, so both are
            always present: neither is useful alone.
        */
        .code-block { text-align: right; }
        .code-block .qr { width: 22mm; height: 22mm; }
        .code-block .qr img { width: 22mm; height: 22mm; }
        .code-block .fallback { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; margin-top: 1mm; }

        .question {
            border: 0.6mm solid #000;
            margin-bottom: 4mm;
            page-break-inside: avoid;
        }
        .question-head {
            background: #000;
            color: #fff;
            padding: 1.5mm 2.5mm;
            font-weight: bold;
            font-size: 9.5pt;
        }
        .question-prompt { padding: 2mm 2.5mm 0 2.5mm; font-size: 9pt; }
        .answer-region { min-height: 26mm; margin: 2mm; }

        {{-- OMR-01: bubbles are placed at the exact millimetre coordinates
             OmrLayout publishes, rather than laid out in normal flow.

             A table with fixed cell widths was tried first, because dompdf is
             said to be more reliable with tables. It is not: it laid the number
             column out at roughly a third of the width it was given, which put
             every bubble five millimetres right of where the reader looks, and
             the pitch came out right while the origin came out wrong. That is
             the worst shape of bug, because a grid that is internally consistent
             looks correct and still reads the wrong column.

             Absolute placement was measured to be accurate to a tenth of a
             millimetre in both axes, which is well inside the reader's sampling
             radius. The correction is half the PRINTED diameter, not half the
             declared one: dompdf does not apply border-box sizing here, so the
             stroke sits outside the declared width and a ring centred on half
             the declared diameter lands a third of a millimetre low. --}}
        .omr-head {
            height: 20mm;
            overflow: hidden;
        }
        .omr-head .institution { font-size: 9pt; font-weight: bold; }
        .omr-head .exam-title { font-size: 10pt; }
        .omr-head .who { font-size: 9pt; }
        .omr-head .how { font-size: 8pt; }

        .omr-bubble {
            position: absolute;
            width: {{ \App\Services\OmrLayout::BUBBLE_DIAMETER_MM }}mm;
            height: {{ \App\Services\OmrLayout::BUBBLE_DIAMETER_MM }}mm;
            border: {{ \App\Services\OmrLayout::BUBBLE_BORDER_MM }}mm solid #000;
            border-radius: 50%;
        }
        .omr-letter {
            position: absolute;
            font-size: 7pt;
            line-height: 1;
            width: 6mm;
            margin-left: -3mm;
            text-align: center;
        }
        .omr-number {
            position: absolute;
            font-size: 9pt;
            font-weight: bold;
            line-height: 1;
            width: 10mm;
            margin-left: -16mm;
            text-align: right;
        }

        .sheet-footer {
            position: fixed;
            bottom: 7mm;
            left: 12mm;
            right: 12mm;
            font-size: 8pt;
            display: table;
            width: 100%;
        }
        .sheet-footer .left { display: table-cell; text-align: left; }
        .sheet-footer .right { display: table-cell; text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    {{-- Fiducials, fixed into the page corners. --}}
    <div class="fiducial tl"></div>
    <div class="fiducial tr"></div>
    <div class="fiducial bl"></div>
    <div class="fiducial br"></div>

    @if (! $questions->isEmpty())
    <div class="sheet-header">
        <table style="width:100%; border-spacing:0;">
            <tr>
                <td style="vertical-align:top;">
                    <div class="institution">{{ $script->institution?->name ?? 'Custograde' }}</div>
                    <div class="exam-title">{{ $exam->title }}</div>
                    {{-- SHT-01: the candidate's own details are printed in clear
                         text, because a marker needs to know whose paper this is.
                         SHT-02 governs the QR payload only, which stays opaque.
                         The name is assembled here rather than read from a
                         `full_name` attribute, which the model does not have. --}}
                    <div class="identity">
                        <div class="cell">
                            <div class="label">Candidate</div>
                            <div class="value">
                                {{ $student ? trim($student->first_name.' '.$student->last_name) : 'Unassigned' }}
                            </div>
                        </div>
                        <div class="cell">
                            <div class="label">Registration number</div>
                            <div class="value">{{ $student?->reg_no ?? '-' }}</div>
                        </div>
                    </div>
                </td>
                {{-- No QR here. The per-page header below carries one on every
                     page, which is what SHT-03 requires, and printing a second
                     copy in the identity block only adds ink to photocopy. --}}
                <td style="vertical-align:top; width:38%; text-align:right;">
                    <div class="fallback">{{ $readable['label'] }}</div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    @php $chunked = $questions->chunk($questionsPerPage); @endphp

    {{-- No written page at all when every question is bubbled. An exam that is
         entirely multiple choice should not hand a candidate a blank page with
         their name on it and nothing to do. --}}
    @foreach ($questions->isEmpty() ? [] : $chunked as $chunkIndex => $chunk)
        @php $pageNumber = $chunkIndex + 1; @endphp

        {{-- One printed page per chunk. `break-after` is what makes dompdf
             start a real page here rather than merely reflowing. --}}
        <div style="page-break-after: always; break-after: page;">
            {{--
                SHT-03: every page carries its own code, and that code names this
                page and the total. Not repeated from page 1, because the exact
                failure this prevents is a page separated from its bundle and
                then unattributable.
            --}}
            <table style="width:100%; border-spacing:0; margin-bottom:3mm;">
                <tr>
                    <td style="width:70%; font-size:9pt;">
                        <strong>{{ $exam->title }}</strong>
                        &nbsp;&middot;&nbsp; {{ $student ? trim($student->first_name.' '.$student->last_name) : 'Unassigned' }}
                        &nbsp;&middot;&nbsp; {{ $student?->reg_no ?? '-' }}
                    </td>
                    <td style="width:30%; text-align:right;">
                        <span class="code-block">
                            <span class="qr"><img src="{{ $qr($pageNumber) }}" alt="" style="width:16mm;height:16mm;"></span>
                        </span>
                    </td>
                </tr>
            </table>

            @foreach ($chunk as $question)
                {{-- SHT-04: one bordered region per question, in a fixed grid,
                     with the number in the header so a transcription request
                     can address a region rather than guess at a page. --}}
                <div class="question">
                    <div class="question-head">
                        Question {{ $question->number }}
                        &nbsp;&middot;&nbsp; {{ rtrim(rtrim(number_format((float) $question->max_mark, 2), '0'), '.') }} marks
                    </div>
                    <div class="question-prompt">{{ $question->prompt }}</div>
                    <div class="answer-region">
                        @for ($line = 0; $line < 4; $line++)
                            <div class="guide"></div>
                        @endfor
                    </div>
                </div>
            @endforeach

            <div style="font-size:8pt; text-align:right; margin-top:2mm;">
                {{ $readable['code'] }} &nbsp;&middot;&nbsp; Page {{ $pageNumber }} of {{ $pageCount }}
            </div>
        </div>
    @endforeach

    {{-- OMR-01: objective pages come after the written ones, grouped by the
         page OmrLayout assigned each question. They are separate pages rather
         than a section within them because written answers have variable height,
         so a bubble inside a mixed page would sit at a different height for every
         candidate. --}}
    @if ($bubbled->isNotEmpty())
        @php $omrPages = collect($omr)->groupBy(fn ($entry) => $entry['page']); @endphp

        @foreach ($omrPages as $omrPageNumber => $pageEntries)
            <div style="page-break-after: always; break-after: page;">
                {{-- A fixed height, so a long name cannot shift the grid. --}}
                <div class="omr-head">
                    <div class="institution">{{ $exam->courseUnit?->institution?->name ?? '' }}</div>
                    <div class="exam-title">{{ $exam->title }} &nbsp;&middot;&nbsp; Section A: multiple choice</div>
                    <div class="who">
                        {{ $student ? trim($student->first_name.' '.$student->last_name) : 'Unassigned' }}
                        &nbsp;&middot;&nbsp; {{ $student?->reg_no ?? '-' }}
                    </div>
                    <div class="how">Fill one bubble per question with a pencil. Erase fully to change your mind.</div>
                </div>

                @php $half = \App\Services\OmrLayout::printedDiameter() / 2; @endphp

                @foreach ($bubbled as $question)
                    @php $entry = $omr[$question->id] ?? null; @endphp
                    @continue(! $entry || $entry['page'] !== $omrPageNumber)

                    @php $first = $entry['options'][array_key_first($entry['options'])]; @endphp
                    @php $rowTop = \App\Services\OmrLayout::toContentOffset(0, $first['y']); @endphp

                    <div class="omr-number" style="top: {{ $rowTop['top'] - 1.8 }}mm;">{{ $question->number }}</div>

                    @foreach ($entry['options'] as $letter => $point)
                        @php $at = \App\Services\OmrLayout::toContentOffset($point['x'], $point['y']); @endphp
                        <div class="omr-bubble" style="left: {{ $at['left'] - $half }}mm; top: {{ $at['top'] - $half }}mm;"></div>
                        <div class="omr-letter" style="left: {{ $at['left'] }}mm; top: {{ $at['top'] + $half + 0.1 }}mm;">{{ $letter }}</div>
                    @endforeach
                @endforeach

                <div style="font-size:8pt; text-align:right; margin-top:2mm;">
                    {{ $readable['code'] }} &nbsp;&middot;&nbsp; Section A, sheet {{ $omrPageNumber }} of {{ $omrPages->count() }}
                </div>
            </div>
        @endforeach
    @endif

    {{-- The fallback string belongs only on the first page: it is the whole
         sheet's identity, not each page's, and repeating it on every page of a
         thirty script batch is thirty times the ink for no added safety. --}}
    <div style="font-size:8pt; margin-top:2mm;">
        If the code will not scan, quote this reference:
        <strong>{{ $readable['code'] }}</strong>
        &nbsp;&middot;&nbsp; {{ $pageCount }} page(s)
    </div>

</body>
</html>