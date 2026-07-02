<?php

namespace App\Services;

use App\Models\BankStatement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelExporter
{
    public function export(BankStatement $statement): string
    {
        $spreadsheet = new Spreadsheet();

        // ----------------------------------------------------
        // Sheet 1: Movimientos
        // ----------------------------------------------------
        $sheetTxs = $spreadsheet->getActiveSheet();
        $sheetTxs->setTitle('Movimientos');

        // Set Headers
        $sheetTxs->setCellValue('A1', 'Fecha');
        $sheetTxs->setCellValue('B1', 'Etiqueta');
        $sheetTxs->setCellValue('C1', 'Contacto');
        $sheetTxs->setCellValue('D1', 'Importe');
        $sheetTxs->setCellValue('E1', 'Concepto / Etiqueta');

        // Style Headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'], // Premium Navy
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheetTxs->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheetTxs->getRowDimension(1)->setRowHeight(25);

        // Write transactions
        $row = 2;
        foreach ($statement->lines()->orderBy('fecha')->orderBy('id')->get() as $line) {
            $sheetTxs->setCellValue('A' . $row, Date::PHPToExcel($line->fecha));
            $sheetTxs->setCellValue('B' . $row, $line->sugerencia ?? '');
            $sheetTxs->setCellValue('C' . $row, $line->contacto ?? '');
            $sheetTxs->setCellValue('D' . $row, (float) $line->importe);
            $sheetTxs->setCellValue('E' . $row, $line->etiqueta);

            // Format cell values
            $sheetTxs->getStyle('A' . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheetTxs->getStyle('D' . $row)->getNumberFormat()->setFormatCode('$#,##0.00');
            
            // Alignments
            $sheetTxs->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheetTxs->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            $row++;
        }

        // Auto-fit columns
        foreach (range('A', 'E') as $col) {
            $sheetTxs->getColumnDimension($col)->setAutoSize(true);
        }

        // Enable filters
        if ($row > 2) {
            $sheetTxs->setAutoFilter('A1:E' . ($row - 1));
        }

        // ----------------------------------------------------
        // Sheet 2: Resumen y Control
        // ----------------------------------------------------
        $sheetRes = $spreadsheet->createSheet();
        $sheetRes->setTitle('Resumen y Control');

        // Enable gridlines
        $sheetRes->setShowGridlines(true);

        // Title Block
        $sheetRes->setCellValue('B2', 'CONCILIACIÓN Y CONTROL DE ESTADO DE CUENTA');
        $sheetRes->mergeCells('B2:F2');
        $sheetRes->getStyle('B2')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1F4E78');
        
        // Metadata Table
        $sheetRes->setCellValue('B4', 'Banco:');
        $sheetRes->setCellValue('C4', $statement->bank_type);
        $sheetRes->setCellValue('B5', 'Cuenta:');
        $sheetRes->setCellValue('C5', $statement->account_number ?? 'N/A');
        $sheetRes->setCellValue('B6', 'CLABE:');
        $sheetRes->setCellValue('C6', $statement->clabe ?? 'N/A');
        
        $sheetRes->setCellValue('E4', 'Periodo Inicio:');
        $sheetRes->setCellValue('F4', $statement->period_start ? $statement->period_start->format('Y-m-d') : 'N/A');
        $sheetRes->setCellValue('E5', 'Periodo Fin:');
        $sheetRes->setCellValue('F5', $statement->period_end ? $statement->period_end->format('Y-m-d') : 'N/A');
        $sheetRes->setCellValue('E6', 'Fecha Exportación:');
        $sheetRes->setCellValue('F6', now()->format('Y-m-d H:i'));

        $metadataLabelsStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '595959']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];
        $sheetRes->getStyle('B4:B6')->applyFromArray($metadataLabelsStyle);
        $sheetRes->getStyle('E4:E6')->applyFromArray($metadataLabelsStyle);

        // Comparison Table Headers
        $sheetRes->setCellValue('B9', 'Métrica de Control');
        $sheetRes->setCellValue('C9', 'PDF (Resumen)');
        $sheetRes->setCellValue('D9', 'Calculado (Líneas)');
        $sheetRes->setCellValue('E9', 'Diferencia');
        $sheetRes->setCellValue('F9', 'Resultado');

        $sheetRes->getStyle('B9:F9')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F5597'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheetRes->getRowDimension(9)->setRowHeight(22);

        // Math checking parameters
        $isCreditCard = str_contains($statement->bank_type, 'TC');
        
        // Row 10: Cargos
        $sheetRes->setCellValue('B10', 'Total Cargos');
        $sheetRes->setCellValue('C10', (float) $statement->total_cargos);
        $sheetRes->setCellValue('D10', (float) $statement->calculated_cargos);
        $sheetRes->setCellValue('E10', '=ABS(C10-D10)');
        $sheetRes->setCellValue('F10', '=IF(E10<0.05,"OK","Diferencia")');

        // Row 11: Abonos
        $sheetRes->setCellValue('B11', 'Total Abonos');
        $sheetRes->setCellValue('C11', (float) $statement->total_abonos);
        $sheetRes->setCellValue('D11', (float) $statement->calculated_abonos);
        $sheetRes->setCellValue('E11', '=ABS(C11-D11)');
        $sheetRes->setCellValue('F11', '=IF(E11<0.05,"OK","Diferencia")');

        // Row 12: Saldo Inicial
        $sheetRes->setCellValue('B12', 'Saldo Inicial');
        $sheetRes->setCellValue('C12', (float) $statement->saldo_inicial);
        $sheetRes->setCellValue('D12', 'N/A');
        $sheetRes->setCellValue('E12', '-');
        $sheetRes->setCellValue('F12', '-');

        // Row 13: Saldo Final / Cuadre
        $sheetRes->setCellValue('B13', 'Saldo Final');
        $sheetRes->setCellValue('C13', (float) $statement->saldo_final);
        
        // Calculated final balance formula
        if ($isCreditCard) {
            $sheetRes->setCellValue('D13', '=C12+D10-D11');
        } else {
            $sheetRes->setCellValue('D13', '=C12+D11-D10');
        }
        $sheetRes->setCellValue('E13', '=ABS(C13-D13)');
        $sheetRes->setCellValue('F13', '=IF(E13<0.1,"OK","Diferencia")');

        // Style the values
        $sheetRes->getStyle('C10:E13')->getNumberFormat()->setFormatCode('$#,##0.00');
        $sheetRes->getStyle('C10:E13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheetRes->getStyle('B10:B13')->getFont()->setBold(true);

        // Center the OK/Diferencia cells
        $sheetRes->getStyle('F10:F13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Borders for the table
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '595959'],
                ],
            ],
        ];
        $sheetRes->getStyle('B9:F13')->applyFromArray($borderStyle);

        // Highlight bottom totals with double lines
        $sheetRes->getStyle('B13:F13')->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_DOUBLE,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Auto-fit column widths for sheet 2
        foreach (range('B', 'F') as $col) {
            $sheetRes->getColumnDimension($col)->setAutoSize(true);
        }

        // Write to a temporary file path
        $tempFile = tempnam(sys_get_temp_dir(), 'bank_statement_export');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
