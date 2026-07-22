<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genuine .xlsx generation, replacing the HTML-table-saved-as-.xls trick every
 * export in this app used to use. That trick only ever rendered because desktop
 * Windows Excel has special legacy leniency for HTML wearing an .xls extension —
 * every other viewer (mobile Excel, Google Sheets, Numbers, etc.) opens the file
 * at face value, sees it isn't really a spreadsheet, and refuses it.
 */
class XlsxExport
{
    /**
     * Build a flat "column picker" worksheet — the shape every export in this app
     * except the Task Manager calendar grid uses (Contacts, To-Dos, Follow-Ups,
     * Site Availability, Task Manager table).
     *
     * @param array<int,string> $columns ordered column keys (a 'no' key is auto-numbered, not read from $getVal)
     * @param array<string,string> $labels column key => header label
     * @param array<string,int> $widths column key => width in points, same unit the old HTML export used
     * @param iterable $rows any iterable of row source objects/arrays
     * @param callable(mixed,string):mixed $getVal fn($row, string $col): mixed
     */
    public static function flatTable(string $sheetName, array $columns, array $labels, array $widths, iterable $rows, callable $getVal): Spreadsheet
    {
        self::raiseLimitsForLargeExport();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        $lastColLetter = Coordinate::stringFromColumnIndex(count($columns));

        foreach ($columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            self::writeText($sheet, "{$letter}1", $labels[$col] ?? $col);
            // Old export widths were points for an HTML <col>; xlsx column width is in
            // "characters" of the default font — no exact equivalent, this is a close visual match.
            $sheet->getColumnDimension($letter)->setWidth(max(8, ($widths[$col] ?? 100) / 7));
        }
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->freezePane('A2');

        $rowNum = 2;
        $no = 1;
        foreach ($rows as $row) {
            foreach ($columns as $i => $col) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $val = $col === 'no' ? $no : $getVal($row, $col);
                self::writeText($sheet, "{$letter}{$rowNum}", $val);
            }
            $rowNum++;
            $no++;
        }

        if ($rowNum > 2) {
            $sheet->getStyle("A2:{$lastColLetter}" . ($rowNum - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
        }

        return $spreadsheet;
    }

    /**
     * Write a value as a literal string cell — never as a number/formula. This is the
     * xlsx-native equivalent of Csv::safe()'s apostrophe-prefix trick: a cell explicitly
     * typed as string can never be reinterpreted by Excel as a formula, even if the
     * value starts with =, +, -, or @, without leaving a visible artifact in the cell.
     */
    public static function writeText(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    /**
     * A real spreadsheet object model costs far more memory/time per row than the old
     * HTML-string approach did — e.g. this app's largest table (~15k contacts) peaks
     * around 250MB and 8s, comfortably past PHP's 128M/30s defaults. This is an explicit
     * "generate my report" action, not a page load, so a generous bump here is safe.
     *
     * This covers every realistic export in this app, but NOT an unbounded one: a
     * deliberately wide date range on the two biggest tables (To-Dos: 81,840 rows;
     * Follow-Ups: 37,647 rows — vs. their default "today" / "this month" filters) can
     * still exceed memory even at a 1GB limit, confirmed live. Fixing that properly needs
     * PhpSpreadsheet's disk-backed cell cache, which needs either SQLite (not installed
     * here) or a fast key-value store — a one-file-per-cell version was tried and is far
     * too slow (800k+ tiny file writes blew past a 120s budget). Not worth solving
     * speculatively for a filter combination nothing in the UI encourages; revisit if a
     * real workflow needs bulk multi-year exports of those two tables.
     * Best-effort: silently no-ops on hosts that lock these down.
     */
    private static function raiseLimitsForLargeExport(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('memory_limit', '512M');
    }

    public static function download(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
