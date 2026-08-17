<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Assembles the final layered PDF for die-cut production (PRD §8).
 *
 * Output structure:
 *   - Single page containing the original artwork
 *   - The cut path is OVERLAID onto the same page (never a separate page)
 *   - The cut path is stroked in the "CutContour" spot color separation so
 *     print RIPs (Roland VersaWorks, Mimaki, etc.) recognise it as a cutter
 *     instruction rather than printing it
 *
 * Implementation:
 *   1. Ensure the source artwork is a PDF (ImageMagick converts rasters)
 *   2. Emit a single-page PDF containing only the cut path stroked in the
 *      CutContour spot color (see writeCutPathPdf) at the correct page size
 *   3. Overlay that PDF onto the artwork using qpdf --overlay (a real
 *      composite, not a page-append concatenation — the previous behaviour
 *      shipped the cut path on page 2, which some RIPs would silently ignore)
 */
class PdfService
{
    private string $convert;

    private string $gs;

    private string $qpdf;

    private string $spotColorName;

    /** @var array{0: float, 1: float, 2: float, 3: float} CMYK 0..100 */
    private array $spotColorCmyk;

    private float $cutLineWidthPt;

    public function __construct()
    {
        $this->convert = config('cutjob.binaries.convert', 'convert');
        $this->gs = config('cutjob.binaries.gs', 'gs');
        $this->qpdf = config('cutjob.binaries.qpdf', 'qpdf');
        $this->spotColorName = config('cutjob.spot_color.name', 'CutContour');
        $this->spotColorCmyk = config('cutjob.spot_color.cmyk', [0, 100, 0, 0]);
        $this->cutLineWidthPt = (float) config('cutjob.cut_line_width_pt', 0.25);
    }

    /**
     * @throws RuntimeException
     */
    public function assemble(
        string $originalPath,
        string $svgPath,
        string $outputDir,
        string $originalName,
        int $width,
        int $height,
    ): string {
        $outputFilename = $this->buildFilename($originalName, $width, $height);
        $outputPath = $outputDir.'/'.$outputFilename;

        // Step 1: Ensure artwork is a PDF.
        $artworkPdf = $outputDir.'/artwork.pdf';
        if (strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) === 'pdf') {
            $artworkPdf = $originalPath;
        } else {
            $this->exec(sprintf(
                '%s %s -density 300 -compress LZW PDF:%s',
                escapeshellarg($this->convert),
                escapeshellarg($originalPath),
                escapeshellarg($artworkPdf),
            ), 'Artwork to PDF conversion failed');
        }

        // Step 2: Emit the cut path PDF in the CutContour spot color.
        $cutPathPdf = $this->writeCutPathPdf($svgPath, $outputDir, $width, $height);

        // Step 3: Overlay onto artwork — real composite, not append.
        $this->overlay($artworkPdf, $cutPathPdf, $outputPath);

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('PDF assembly produced an empty file.');
        }

        Log::debug('PdfService: assembled', ['output' => $outputPath, 'spot_color' => $this->spotColorName]);

        return $outputPath;
    }

    /**
     * Build a Ghostscript PostScript file that:
     *   - Sets the page size to match the artwork (in points)
     *   - Defines a named /Separation colorspace (the print RIP's cut trigger)
     *   - Strokes the SVG path in that separation
     *
     * The SVG path data is parsed into PostScript path operators (moveto,
     * lineto, curveto, closepath) so the output is a real vector cutter path
     * rather than a rasterised approximation.
     *
     * @throws RuntimeException
     */
    private function writeCutPathPdf(string $svgPath, string $outputDir, int $widthPx, int $heightPx): string
    {
        $svg = @file_get_contents($svgPath);
        if ($svg === false) {
            throw new RuntimeException("Cut path SVG not readable: {$svgPath}");
        }

        // Extract every `d="…"` payload from the SVG (potrace/AI both emit `<path d="…"/>`).
        if (! preg_match_all('/\sd="([^"]+)"/', $svg, $matches) || empty($matches[1])) {
            throw new RuntimeException('Cut path SVG contains no <path d="…"/> data.');
        }

        $dpi = (int) config('cutjob.dpi', 300);
        $widthPt = $widthPx / $dpi * 72.0;
        $heightPt = $heightPx / $dpi * 72.0;
        $scale = 72.0 / $dpi;

        $psPath = $outputDir.'/cutpath.ps';
        $pdfPath = $outputDir.'/cutpath.pdf';

        $psPathData = '';
        foreach ($matches[1] as $data) {
            $psPathData .= $this->svgPathToPostScript($data)."\n";
        }

        $ps = $this->buildSpotColorPostScript($widthPt, $heightPt, $scale, $psPathData);

        if (file_put_contents($psPath, $ps) === false) {
            throw new RuntimeException("Failed to write cut path PostScript to {$psPath}");
        }

        $this->exec(sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s',
            escapeshellarg($this->gs),
            escapeshellarg($pdfPath),
            escapeshellarg($psPath),
        ), 'Cut path PDF generation failed (gs required)');

        return $pdfPath;
    }

    /**
     * Compose the artwork PDF with the cut path PDF as an overlay.
     *
     * qpdf --overlay places page 1 of stamp.pdf on top of page 1 of the source
     * PDF and writes a single-page result. This is the correct primitive for
     * die-cut layouts — see qpdf docs "Page overlay and underlay".
     *
     * @throws RuntimeException
     */
    private function overlay(string $artworkPdf, string $cutPathPdf, string $outputPath): void
    {
        $this->exec(sprintf(
            '%s %s --overlay %s -- %s',
            escapeshellarg($this->qpdf),
            escapeshellarg($artworkPdf),
            escapeshellarg($cutPathPdf),
            escapeshellarg($outputPath),
        ), 'PDF overlay failed (qpdf required — install with `apt install qpdf` or `brew install qpdf`)');
    }

    /**
     * Emit PostScript that defines the CutContour spot color separation and
     * strokes the given path in it. Alternate color is the CMYK fallback for
     * on-screen viewers that don't render separations.
     */
    private function buildSpotColorPostScript(float $widthPt, float $heightPt, float $scale, string $pathBody): string
    {
        [$c, $m, $y, $k] = array_map(fn ($v) => max(0.0, min(1.0, $v / 100.0)), $this->spotColorCmyk);
        $safeName = preg_replace('/[^A-Za-z0-9]/', '', $this->spotColorName) ?: 'CutContour';

        $lineWidth = $this->cutLineWidthPt;

        return <<<PS
%!PS-Adobe-3.0
<< /PageSize [{$widthPt} {$heightPt}] >> setpagedevice

% Define the "{$safeName}" spot color separation.
% The tint transform maps a single-channel intensity to the CMYK fallback the
% RIP shows when the separation is not resolved to plate.
/{$safeName}Sep [
    /Separation
    /{$safeName}
    /DeviceCMYK
    { dup {$c} mul exch dup {$m} mul exch dup {$y} mul exch {$k} mul }
] def

{$safeName}Sep setcolorspace
1 setcolor
{$lineWidth} setlinewidth
1 setlinecap
1 setlinejoin
[] 0 setdash

% User space is in points. Path data is in raster pixels at {$scale} pt/px.
gsave
{$scale} {$scale} scale

newpath
{$pathBody}
stroke

grestore
showpage
PS;
    }

    /**
     * Convert a subset of SVG path data (produced by Potrace and by the AI
     * agent) to PostScript path operators. Supports M/L/C/Z, absolute and
     * relative, with implicit repeated operators.
     */
    private function svgPathToPostScript(string $d): string
    {
        // Split packed numbers like "1.5-2.3" or "4+5" into "1.5 -2.3" and
        // "4 +5" so `is_numeric()` accepts each token — otherwise readNum()
        // returns null and the path collapses to a point.
        $d = preg_replace('/([0-9.])([-+])/', '$1 $2', $d);
        // Insert a space before each command letter so tokens split cleanly.
        $normalized = preg_replace('/([MmLlCcZzHhVvSsQqTtAa])/', ' $1 ', $d);
        $normalized = preg_replace('/,/', ' ', $normalized);
        $tokens = preg_split('/\s+/', trim($normalized ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        $x = 0.0;
        $y = 0.0;
        $cmd = '';
        $i = 0;

        $readNum = function () use (&$tokens, &$i): ?float {
            if (! isset($tokens[$i])) {
                return null;
            }
            $v = $tokens[$i];
            if (! is_numeric($v)) {
                return null;
            }
            $i++;

            return (float) $v;
        };

        while ($i < count($tokens)) {
            $before = $i;
            $token = $tokens[$i];

            if (in_array($token, ['M', 'm', 'L', 'l', 'C', 'c', 'Z', 'z', 'H', 'h', 'V', 'v'], true)) {
                $cmd = $token;
                $i++;

                // Z/z takes no arguments — emit closepath immediately.
                if ($token === 'Z' || $token === 'z') {
                    $out[] = 'closepath';
                }

                continue;
            }

            switch ($cmd) {
                case 'M':
                    $x = $readNum() ?? $x;
                    $y = $readNum() ?? $y;
                    $out[] = sprintf('%.4f %.4f moveto', $x, $y);
                    $cmd = 'L';
                    break;
                case 'm':
                    $x += $readNum() ?? 0;
                    $y += $readNum() ?? 0;
                    $out[] = sprintf('%.4f %.4f moveto', $x, $y);
                    $cmd = 'l';
                    break;
                case 'L':
                    $x = $readNum() ?? $x;
                    $y = $readNum() ?? $y;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'l':
                    $x += $readNum() ?? 0;
                    $y += $readNum() ?? 0;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'H':
                    $x = $readNum() ?? $x;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'h':
                    $x += $readNum() ?? 0;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'V':
                    $y = $readNum() ?? $y;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'v':
                    $y += $readNum() ?? 0;
                    $out[] = sprintf('%.4f %.4f lineto', $x, $y);
                    break;
                case 'C':
                    $x1 = $readNum() ?? 0;
                    $y1 = $readNum() ?? 0;
                    $x2 = $readNum() ?? 0;
                    $y2 = $readNum() ?? 0;
                    $x = $readNum() ?? $x;
                    $y = $readNum() ?? $y;
                    $out[] = sprintf('%.4f %.4f %.4f %.4f %.4f %.4f curveto', $x1, $y1, $x2, $y2, $x, $y);
                    break;
                case 'c':
                    $x1 = $x + ($readNum() ?? 0);
                    $y1 = $y + ($readNum() ?? 0);
                    $x2 = $x + ($readNum() ?? 0);
                    $y2 = $y + ($readNum() ?? 0);
                    $nx = $x + ($readNum() ?? 0);
                    $ny = $y + ($readNum() ?? 0);
                    $out[] = sprintf('%.4f %.4f %.4f %.4f %.4f %.4f curveto', $x1, $y1, $x2, $y2, $nx, $ny);
                    $x = $nx;
                    $y = $ny;
                    break;
                case 'Z':
                case 'z':
                    $out[] = 'closepath';
                    break;
            }

            // Never silently drop a token — corrupt cut paths are a nightmare
            // to debug. If nothing in this iteration advanced $i we would loop
            // forever, which happens for SVG operators the parser doesn't
            // implement (S/s, Q/q, T/t, A/a) or any non-numeric junk in the
            // argument stream. Fail loudly instead.
            if ($i === $before) {
                throw new RuntimeException(
                    "Unsupported SVG path operator or malformed token: {$token}"
                );
            }
        }

        return implode("\n", $out);
    }

    /** Build the output filename per PRD §8: `{original_name}_{width}x{height}.pdf` */
    public function buildFilename(string $originalName, int $width, int $height): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);

        return "{$base}_{$width}x{$height}.pdf";
    }

    /** @throws RuntimeException */
    private function exec(string $command, string $errorContext): void
    {
        $output = [];
        $code = 0;

        exec("{$command} 2>&1", $output, $code);

        if ($code !== 0) {
            $detail = implode(' ', $output);
            Log::error("PdfService: {$errorContext}", ['output' => $detail]);
            throw new RuntimeException("{$errorContext}: {$detail}");
        }
    }
}
