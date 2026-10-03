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

        /*
            Writing guides. Light enough not to compete with handwriting, dark
            enough to still print. Dotted so a candidate does not mistake them
            for answer boundaries.
        */
        .guide { border-bottom: 0.3mm dotted #666; height: 7mm; margin: 0 2mm; }

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

    @php $chunked = $questions->chunk($questionsPerPage); @endphp

    @foreach ($chunked as $chunkIndex => $chunk)
        @php $pageNumber = $chunkIndex + 1; @endphp

        {{-- One printed page per chunk. `break-after` is what makes dompdf
             start a real page here rather than merely reflowing. --}}
        <div class="page" style="break-after: page;">
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