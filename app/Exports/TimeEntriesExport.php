<?php 

namespace App\Exports;

use App\Models\TimeEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TimeEntriesExport
{
    public function download()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Fejlécek
        $sheet->setCellValue('A1', 'Employee ID');
        $sheet->setCellValue('B1', 'Type');
        $sheet->setCellValue('C1', 'Start Date');
        $sheet->setCellValue('D1', 'End Date');
        $sheet->setCellValue('E1', 'Hours');
        $sheet->setCellValue('F1', 'Status');
        $sheet->setCellValue('G1', 'Note');

        // Adatok
        $entries = TimeEntry::query()->get();
        $row = 2;
        foreach ($entries as $entry) {
            $sheet->setCellValue("A{$row}", $entry->employee_id);
            $sheet->setCellValue("B{$row}", $entry->type);
            $sheet->setCellValue("C{$row}", $entry->start_date);
            $sheet->setCellValue("D{$row}", $entry->end_date);
            $sheet->setCellValue("E{$row}", $entry->hours);
            $sheet->setCellValue("F{$row}", $entry->status);
            $sheet->setCellValue("G{$row}", $entry->note);
            $row++;
        }

        // Letöltés
        $writer = new Xlsx($spreadsheet);
        $filename = 'time_entries.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        $writer->save('php://output');
        exit;
    }
}