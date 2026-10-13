<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class FinancialReportWorkbook
{
    /**
     * @param  iterable<object>  $dailyTrends
     * @param  iterable<object>  $transactions
     */
    public function create(
        string $path,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $income,
        int $expenses,
        int $refunds,
        iterable $dailyTrends,
        iterable $transactions,
    ): void {
        $trends = collect($dailyTrends)->values();

        if ($trends->isEmpty()) {
            $trends = collect([(object) [
                'payment_date' => $periodStart->toDateString(),
                'income_amount' => 0,
                'expense_amount' => 0,
                'net_amount' => 0,
            ]]);
        }

        $summarySheetPath = tempnam(sys_get_temp_dir(), 'gotham-summary-');
        $transactionSheetPath = tempnam(sys_get_temp_dir(), 'gotham-transactions-');

        if ($summarySheetPath === false || $transactionSheetPath === false) {
            throw new RuntimeException('Tidak dapat menyiapkan file sementara untuk laporan Excel.');
        }

        try {
            $this->writeSummarySheet(
                $summarySheetPath,
                $periodStart,
                $periodEnd,
                $income,
                $expenses,
                $refunds,
                $trends,
            );
            $this->writeTransactionsSheet($transactionSheetPath, $periodStart, $periodEnd, $transactions);
            $this->createArchive($path, $summarySheetPath, $transactionSheetPath, $trends->count());
        } finally {
            @unlink($summarySheetPath);
            @unlink($transactionSheetPath);
        }
    }

    /**
     * @param  Collection<int, object>  $trends
     */
    private function writeSummarySheet(
        string $path,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $income,
        int $expenses,
        int $refunds,
        Collection $trends,
    ): void {
        $stream = fopen($path, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Tidak dapat menulis ringkasan laporan Excel.');
        }

        fwrite($stream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($stream, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($stream, '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="20" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/><col min="6" max="6" width="20" customWidth="1"/></cols><sheetData>');
        fwrite($stream, '<row r="1" ht="34" customHeight="1">'.$this->stringCell('A1', 'GOTHAM CITY COFFEE  |  LAPORAN KEUANGAN', 1).'</row>');
        fwrite($stream, '<row r="2" ht="24" customHeight="1">'.$this->stringCell('A2', 'Periode '.$periodStart->format('d M Y').' — '.$periodEnd->format('d M Y'), 2).'</row>');
        fwrite($stream, '<row r="4" ht="24" customHeight="1">'.$this->stringCell('A4', 'TOTAL PEMASUKAN', 3).$this->stringCell('C4', 'TOTAL PENGELUARAN', 4).$this->stringCell('E4', 'LABA BERSIH', 5).'</row>');
        fwrite($stream, '<row r="5" ht="34" customHeight="1">'.$this->numberCell('A5', $income, 6).$this->numberCell('C5', $expenses, 7).$this->numberCell('E5', $income - $expenses, 8).'</row>');
        fwrite($stream, '<row r="6" ht="24" customHeight="1">'.$this->stringCell('A6', 'REFUND', 4).$this->stringCell('C6', 'MATA UANG', 3).$this->stringCell('E6', 'RENTANG', 3).'</row>');
        fwrite($stream, '<row r="7" ht="28" customHeight="1">'.$this->numberCell('A7', $refunds, 7).$this->stringCell('C7', 'IDR · Rupiah', 2).$this->stringCell('E7', $trends->count().' hari', 2).'</row>');
        fwrite($stream, '<row r="9" ht="28" customHeight="1">'.$this->stringCell('A9', 'TREN TRANSAKSI HARIAN', 9).'</row>');
        fwrite($stream, '<row r="10" ht="23" customHeight="1">'.$this->stringCell('A10', 'Tanggal', 10).$this->stringCell('B10', 'Pemasukan', 10).$this->stringCell('C10', 'Pengeluaran', 10).$this->stringCell('D10', 'Laba Bersih', 10).'</row>');

        foreach ($trends as $index => $trend) {
            $row = $index + 11;
            fwrite($stream, '<row r="'.$row.'">'.$this->stringCell('A'.$row, (string) $trend->payment_date, 11)
                .$this->numberCell('B'.$row, (int) $trend->income_amount, 12)
                .$this->numberCell('C'.$row, (int) $trend->expense_amount, 13)
                .$this->numberCell('D'.$row, (int) $trend->net_amount, 14).'</row>');
        }

        fwrite($stream, '</sheetData><mergeCells count="6"><mergeCell ref="A1:F1"/><mergeCell ref="A2:F2"/><mergeCell ref="A9:D9"/><mergeCell ref="A4:B4"/><mergeCell ref="C4:D4"/><mergeCell ref="E4:F4"/></mergeCells><drawing r:id="rId1"/></worksheet>');
        fclose($stream);
    }

    /**
     * @param  iterable<object>  $transactions
     */
    private function writeTransactionsSheet(string $path, Carbon $periodStart, Carbon $periodEnd, iterable $transactions): void
    {
        $stream = fopen($path, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Tidak dapat menulis detail transaksi laporan Excel.');
        }

        fwrite($stream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($stream, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($stream, '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="12" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/><col min="4" max="4" width="34" customWidth="1"/><col min="5" max="5" width="22" customWidth="1"/><col min="6" max="6" width="22" customWidth="1"/><col min="7" max="7" width="20" customWidth="1"/><col min="8" max="8" width="36" customWidth="1"/><col min="9" max="9" width="18" customWidth="1"/></cols><sheetData>');
        fwrite($stream, '<row r="1" ht="34" customHeight="1">'.$this->stringCell('A1', 'DETAIL SELURUH TRANSAKSI', 1).'</row>');
        fwrite($stream, '<row r="2">'.$this->stringCell('A2', 'Periode '.$periodStart->format('d M Y').' — '.$periodEnd->format('d M Y'), 2).'</row>');
        fwrite($stream, '<row r="4" ht="28" customHeight="1">');

        foreach ([
            'Jenis transaksi', 'Referensi', 'Tanggal', 'Deskripsi', 'Staff / Waiter',
            'Kategori / Metode', 'Nominal (IDR)', 'Catatan / Alasan', 'Status',
        ] as $index => $heading) {
            fwrite($stream, $this->stringCell($this->columnName($index + 1).'4', $heading, 10));
        }

        fwrite($stream, '</row>');
        $row = 5;

        foreach ($transactions as $transaction) {
            $textStyle = $row % 2 === 1 ? 14 : 11;
            $amountStyle = $row % 2 === 1 ? 13 : 12;

            fwrite($stream, '<row r="'.$row.'">'
                .$this->stringCell('A'.$row, (string) $transaction->transaction_type, $textStyle)
                .$this->numberCell('B'.$row, (int) $transaction->reference, $textStyle)
                .$this->stringCell('C'.$row, (string) $transaction->happened_at, $textStyle)
                .$this->stringCell('D'.$row, (string) $transaction->description, $textStyle)
                .$this->stringCell('E'.$row, (string) ($transaction->staff_name ?? ''), $textStyle)
                .$this->stringCell('F'.$row, (string) ($transaction->category ?? ''), $textStyle)
                .$this->numberCell('G'.$row, (int) $transaction->amount, $amountStyle)
                .$this->stringCell('H'.$row, (string) ($transaction->notes ?? ''), $textStyle)
                .$this->stringCell('I'.$row, (string) $transaction->status, $textStyle)
                .'</row>');
            $row++;
        }

        fwrite($stream, '</sheetData><autoFilter ref="A4:I'.max(4, $row - 1).'"/></worksheet>');
        fclose($stream);
    }

    private function createArchive(string $path, string $summaryPath, string $transactionsPath, int $trendCount): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat file Excel.');
        }

        $lastTrendRow = max(11, 10 + $trendCount);
        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $summaryPath,
            'xl/worksheets/sheet2.xml' => $transactionsPath,
            'xl/worksheets/_rels/sheet1.xml.rels' => $this->worksheetRelationships(),
            'xl/drawings/drawing1.xml' => $this->drawing(),
            'xl/drawings/_rels/drawing1.xml.rels' => $this->drawingRelationships(),
            'xl/charts/chart1.xml' => $this->chart($lastTrendRow),
        ];

        foreach ($files as $name => $contents) {
            $added = is_file($contents)
                ? $archive->addFile($contents, $name)
                : $archive->addFromString($name, $contents);

            if (! $added) {
                $archive->close();

                throw new RuntimeException('Gagal menambahkan bagian laporan Excel: '.$name);
            }
        }

        if (! $archive->close()) {
            throw new RuntimeException('Gagal menyelesaikan file Excel.');
        }
    }

    private function stringCell(string $reference, string $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escapeXml($value)
            .'</t></is></c>';
    }

    private function numberCell(string $reference, int $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
            .'<Override PartName="/xl/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Ringkasan" sheetId="1" r:id="rId1"/><sheet name="Detail Transaksi" sheetId="2" r:id="rId2"/></sheets>'
            .'<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function worksheetRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            .'</Relationships>';
    }

    private function drawing(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">'
            .'<xdr:twoCellAnchor><xdr:from><xdr:col>6</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>8</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:to><xdr:col>14</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>24</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
            .'<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="2" name="Kurva Tren Harian"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>'
            .'<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId1"/></a:graphicData></a:graphic></xdr:graphicFrame>'
            .'<xdr:clientData/></xdr:twoCellAnchor></xdr:wsDr>';
    }

    private function drawingRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/>'
            .'</Relationships>';
    }

    private function chart(int $lastRow): string
    {
        $series = '';

        foreach ([
            ['B', '00A878'],
            ['C', 'E07855'],
            ['D', '5875D6'],
        ] as $index => [$column, $color]) {
            $series .= '<c:ser><c:idx val="'.$index.'"/><c:order val="'.$index.'"/>'
                .'<c:tx><c:strRef><c:f>\'Ringkasan\'!$'.$column.'$10</c:f></c:strRef></c:tx>'
                .'<c:spPr><a:ln w="28575"><a:solidFill><a:srgbClr val="'.$color.'"/></a:solidFill><a:prstDash val="solid"/></a:ln></c:spPr>'
                .'<c:marker><c:symbol val="circle"/><c:size val="5"/></c:marker>'
                .'<c:cat><c:strRef><c:f>\'Ringkasan\'!$A$11:$A$'.$lastRow.'</c:f></c:strRef></c:cat>'
                .'<c:val><c:numRef><c:f>\'Ringkasan\'!$'.$column.'$11:$'.$column.'$'.$lastRow.'</c:f></c:numRef></c:val><c:smooth val="1"/>'
                .'</c:ser>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<c:lang val="id-ID"/><c:chart><c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr b="1" sz="1400"><a:solidFill><a:srgbClr val="174B3A"/></a:solidFill></a:defRPr></a:pPr><a:r><a:t>Kurva Pemasukan, Pengeluaran &amp; Laba</a:t></a:r><a:endParaRPr lang="id-ID"/></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>'
            .'<c:plotArea><c:layout/><c:lineChart><c:grouping val="standard"/>'.$series.'<c:axId val="48650112"/><c:axId val="48672768"/></c:lineChart>'
            .'<c:catAx><c:axId val="48650112"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="48672768"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/></c:catAx>'
            .'<c:valAx><c:axId val="48672768"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:majorGridlines><c:spPr><a:ln w="6350"><a:solidFill><a:srgbClr val="E6ECE8"/></a:solidFill></a:ln></c:spPr></c:majorGridlines><c:numFmt formatCode="#,##0" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/><c:crossAx val="48650112"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>'
            .'</c:plotArea><c:legend><c:legendPos val="b"/><c:overlay val="0"/></c:legend><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart><c:printSettings><c:headerFooter/><c:pageMargins b="0.5" l="0.25" r="0.25" t="0.5" header="0.3" footer="0.3"/></c:printSettings></c:chartSpace>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode=\'&quot;Rp&quot; #,##0;[Red]-&quot;Rp&quot; #,##0\'/></numFmts>'
            .'<fonts count="5"><font><sz val="11"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><sz val="11"/><color rgb="FFE8F2ED"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font><font><b/><sz val="15"/><name val="Aptos Display"/></font></fonts>'
            .'<fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF174B3A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF00A878"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE07855"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF5875D6"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F5F2"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE1E9E4"/></left><right style="thin"><color rgb="FFE1E9E4"/></right><top style="thin"><color rgb="FFE1E9E4"/></top><bottom style="thin"><color rgb="FFE1E9E4"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="16">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="4" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="5" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="4" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="4" fillId="4" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="4" fillId="5" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
