<?php
/* KIDAN 72 — Small Excel (.xlsx) writer for the admin downloads.
   No extra software: an .xlsx file is a zip of a few XML files.

   $x = new Xlsx();
   $x->sheet('Orders', $rows, ['widths' => [12, 20], 'header' => 3, 'filter' => true]);
   $x->download('kidan72-report.xlsx');

   A cell is a plain value (text or number) or an array:
     ['v' => 1250.5, 's' => 'peso']      money   ₱1,250.50
     ['v' => $timestamp, 's' => 'date']  date and time
     ['v' => 'Total', 's' => 'bold']     bold text
   Styles: see STYLES below (title, sub, head, headr, peso, int, date, day, pct,
   wrap, text, banner, bannersub, kpilabel, kpi, kpiint, up, down, flat,
   section, note, muted, pesobold, intbold, totallabel).

   Sheet options:
     widths      column widths                  header   row number of the column headings (frozen, table lines below it)
     filter      filter arrows on the headings  grid     false = hide the grid lines
     landscape   print sideways                 onepage  print the whole sheet on one page
     fit         false = do not shrink to the page width
     heights     [row => height]                merge    ['A1:G1', ...]
     bars        [['C5:C12', 'F0A47A'], ...]    in-cell bars (Excel data bars)
     printcols   number of columns to print (the rest stay on screen only)
     footer      text at the bottom left of each printed page (page numbers on the right)
     chart       ['title', 'sheet', 'cats' => 'A4:A34', 'vals' => 'C4:C34', 'labels' => [...], 'values' => [...],
                  'from' => [col, row], 'to' => [col, row]]  (0-based cells; a column chart) */

if (!defined('KIDAN_ROOT')) exit;

class Xlsx {
    private array $sheets = [];

    /* name => [number format, font, fill, border, alignment] */
    private const STYLES = [
        ''          => [0, 0, 0, 0, ''],
        'title'     => [0, 1, 0, 0, ''],
        'sub'       => [0, 2, 0, 0, ''],
        'head'      => [0, 3, 2, 0, 'vertical="center" wrapText="1" indent="0"'],
        'headr'     => [0, 3, 2, 0, 'horizontal="right" vertical="center" wrapText="1"'],
        'bold'      => [0, 4, 0, 0, ''],
        'peso'      => [164, 0, 0, 2, 'vertical="top"'],
        'pesobold'  => [164, 4, 3, 1, ''],
        'int'       => [167, 0, 0, 2, 'vertical="top"'],
        'intbold'   => [167, 4, 3, 1, ''],
        'date'      => [165, 0, 0, 2, 'horizontal="left" vertical="top"'],
        'day'       => [166, 0, 0, 2, 'horizontal="center" vertical="top"'],
        'pct'       => [9, 0, 0, 2, 'vertical="top"'],
        'wrap'      => [0, 0, 0, 2, 'vertical="top" wrapText="1"'],
        'text'      => [0, 0, 0, 2, 'vertical="top"'],
        'banner'    => [0, 5, 2, 0, 'horizontal="left" vertical="center" indent="1"'],
        'bannersub' => [0, 6, 2, 0, 'horizontal="left" vertical="top" indent="1"'],
        'kpilabel'  => [0, 7, 4, 3, 'horizontal="left" vertical="bottom" indent="1"'],
        'kpi'       => [168, 8, 4, 3, 'horizontal="left" vertical="center" indent="1"'],
        'kpiint'    => [167, 8, 4, 3, 'horizontal="left" vertical="center" indent="1"'],
        'up'        => [0, 9, 4, 3, 'horizontal="left" vertical="top" indent="1"'],
        'down'      => [0, 10, 4, 3, 'horizontal="left" vertical="top" indent="1"'],
        'flat'      => [0, 2, 4, 3, 'horizontal="left" vertical="top" indent="1"'],
        'section'   => [0, 11, 0, 4, 'vertical="bottom"'],
        'note'      => [0, 2, 0, 0, 'vertical="top" wrapText="1"'],
        'muted'     => [0, 12, 0, 2, 'vertical="top"'],
        'totallabel'=> [0, 4, 3, 1, ''],
    ];

    public function sheet(string $name, array $rows, array $opt = []): void {
        $name = mb_substr(preg_replace('~[\\\\/?*\[\]:]~', ' ', $name), 0, 31);
        $this->sheets[] = [$name, $rows, $opt];
    }

    private static function col(int $i): string {           // 0 → A, 26 → AA
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private static function esc(string $v): string {
        $v = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $v) ?? '';
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function excelDate(int $ts): float {      // Excel counts days from 1899-12-30, in local (Manila) time
        return ($ts + (int)date('Z', $ts)) / 86400 + 25569;
    }

    private static function styleId(string $s): int {
        $i = array_search($s, array_keys(self::STYLES), true);
        return $i === false ? 0 : $i;
    }

    private static function lastCol(array $rows): int {
        $n = 0; foreach ($rows as $r) $n = max($n, count($r));
        return max(1, $n);
    }

    private function sheetXml(int $index, array $rows, array $opt): string {
        $widths = $opt['widths'] ?? [];
        $header = $opt['header'] ?? 0;      // 1-based row number of the column headings (0 = none)
        $onePage = $opt['onepage'] ?? false;
        $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if ($opt['fit'] ?? true) $out .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $out .= '<sheetViews><sheetView workbookViewId="0"' . ($index === 0 ? ' tabSelected="1"' : '') . ($opt['grid'] ?? true ? '' : ' showGridLines="0"') . '>';
        if ($header) $out .= '<pane ySplit="' . $header . '" topLeftCell="A' . ($header + 1) . '" activePane="bottomLeft" state="frozen"/>';
        $out .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="16"/>';
        if ($widths) {
            $out .= '<cols>';
            foreach ($widths as $i => $w) $out .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
            $out .= '</cols>';
        }
        $out .= '<sheetData>';
        $heights = ($opt['heights'] ?? []) + ($opt['tall'] ?? []);
        foreach (array_values($rows) as $r => $row) {
            $h = $heights[$r + 1] ?? null;
            $out .= '<row r="' . ($r + 1) . '"' . ($h ? ' ht="' . (float)$h . '" customHeight="1"' : '') . '>';
            foreach (array_values($row) as $c => $cell) {
                $style = ''; $v = $cell;
                if (is_array($cell)) { $v = $cell['v'] ?? null; $style = $cell['s'] ?? ''; }
                // plain cells in a table get the light row line
                if ($style === '' && $header && $r + 1 > $header) $style = 'text';
                $ref = self::col($c) . ($r + 1);
                $s = $style !== '' ? ' s="' . self::styleId($style) . '"' : '';
                if ($v === null || $v === '') { if ($style !== '') $out .= '<c r="' . $ref . '"' . $s . '/>'; continue; }
                if ($style === 'date' || $style === 'day') {
                    $out .= '<c r="' . $ref . '"' . $s . '><v>' . self::excelDate((int)$v) . '</v></c>';
                } elseif (is_int($v) || is_float($v)) {
                    $out .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
                } else {
                    $out .= '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::esc((string)$v) . '</t></is></c>';
                }
            }
            $out .= '</row>';
        }
        $out .= '</sheetData>';
        $maxCol = self::lastCol($rows);
        if ($header && ($opt['filter'] ?? false) && count($rows) > $header) {
            $out .= '<autoFilter ref="A' . $header . ':' . self::col($maxCol - 1) . count($rows) . '"/>';
        }
        if (!empty($opt['merge'])) {
            $out .= '<mergeCells count="' . count($opt['merge']) . '">';
            foreach ($opt['merge'] as $m) $out .= '<mergeCell ref="' . $m . '"/>';
            $out .= '</mergeCells>';
        }
        foreach ($opt['bars'] ?? [] as $k => [$range, $color]) {
            $out .= '<conditionalFormatting sqref="' . $range . '"><cfRule type="dataBar" priority="' . ($k + 1) . '"><dataBar>'
                  . '<cfvo type="num" val="0"/><cfvo type="max"/><color rgb="FF' . $color . '"/></dataBar></cfRule></conditionalFormatting>';
        }
        $out .= '<printOptions horizontalCentered="' . ($onePage ? '1' : '0') . '"/>'
              . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
              . '<pageSetup paperSize="9" orientation="' . ($opt['landscape'] ?? false ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="' . ($onePage ? '1' : '0') . '"/>';
        if (!empty($opt['footer'])) {
            $out .= '<headerFooter><oddFooter>&amp;L&amp;8&amp;K7D766D' . self::esc(str_replace('&', '&&', $opt['footer'])) . '&amp;R&amp;8&amp;K7D766DPage &amp;P of &amp;N</oddFooter></headerFooter>';
        }
        if (!empty($opt['chart'])) $out .= '<drawing r:id="rId1"/>';
        return $out . '</worksheet>';
    }

    private function stylesXml(): string {
        $font = fn(string $inner) => '<font>' . $inner . '<name val="Calibri"/><family val="2"/></font>';
        $fontList = [
            '<sz val="11"/><color rgb="FF17140F"/>',                       // 0 normal
            '<b/><sz val="16"/><color rgb="FF17140F"/>',                   // 1 title
            '<sz val="10"/><color rgb="FF7D766D"/>',                       // 2 small grey
            '<b/><sz val="10"/><color rgb="FFFFFFFF"/>',                   // 3 table heading
            '<b/><sz val="11"/><color rgb="FF17140F"/>',                   // 4 bold
            '<b/><sz val="20"/><color rgb="FFFFFFFF"/>',                   // 5 banner
            '<sz val="10"/><color rgb="FFD9CFC0"/>',                       // 6 banner line 2
            '<b/><sz val="9"/><color rgb="FF67615A"/>',                    // 7 card label
            '<b/><sz val="18"/><color rgb="FF17140F"/>',                   // 8 card number
            '<b/><sz val="9"/><color rgb="FF2F7A3E"/>',                    // 9 up (green)
            '<b/><sz val="9"/><color rgb="FFB23A2A"/>',                    // 10 down (red)
            '<b/><sz val="12"/><color rgb="FFA8411A"/>',                   // 11 section heading
            '<i/><sz val="11"/><color rgb="FF8A8279"/>',                   // 12 muted (cancelled)
        ];
        $fonts = '<fonts count="' . count($fontList) . '">' . implode('', array_map($font, $fontList)) . '</fonts>';
        $solid = fn(string $rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        $fills = '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
               . $solid('17140F') . $solid('F6F0E6') . $solid('F3ECE1') . '</fills>';
        $borders = '<borders count="5">'
                 . '<border><left/><right/><top/><bottom/><diagonal/></border>'                                                       // 0 none
                 . '<border><left/><right/><top style="thin"><color rgb="FF17140F"/></top><bottom/><diagonal/></border>'               // 1 total line
                 . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFE5DDD0"/></bottom><diagonal/></border>'            // 2 row line
                 . '<border><left style="thick"><color rgb="FFE2632A"/></left><right/><top/><bottom/><diagonal/></border>'             // 3 card accent
                 . '<border><left/><right/><top/><bottom style="medium"><color rgb="FFE2632A"/></bottom><diagonal/></border>'          // 4 section line
                 . '</borders>';
        $numFmts = '<numFmts count="5"><numFmt numFmtId="164" formatCode="&quot;₱&quot;#,##0.00"/>'
                 . '<numFmt numFmtId="165" formatCode="mmm d, yyyy h:mm AM/PM"/><numFmt numFmtId="166" formatCode="mmm d, yyyy"/>'
                 . '<numFmt numFmtId="167" formatCode="#,##0"/><numFmt numFmtId="168" formatCode="&quot;₱&quot;#,##0"/></numFmts>';
        $xfs = '';
        foreach (self::STYLES as [$f, $fo, $fi, $b, $al]) {
            $xfs .= '<xf numFmtId="' . $f . '" fontId="' . $fo . '" fillId="' . $fi . '" borderId="' . $b . '" xfId="0"'
                  . ($f ? ' applyNumberFormat="1"' : '') . ' applyFont="1"' . ($fi ? ' applyFill="1"' : '') . ($b ? ' applyBorder="1"' : '')
                  . ($al !== '' ? ' applyAlignment="1"><alignment ' . $al . '/></xf>' : '/>');
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . $numFmts . $fonts . $fills . $borders
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="' . count(self::STYLES) . '">' . $xfs . '</cellXfs>'
             . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    /* A simple column chart (sales per day or per month) */
    private function chartXml(array $ch): string {
        $sheet = "'" . str_replace("'", "''", $ch['sheet']) . "'!";
        $abs = fn(string $r) => preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $r);
        $n = count($ch['labels']);
        $strPts = ''; $numPts = '';
        foreach (array_values($ch['labels']) as $i => $l) $strPts .= '<c:pt idx="' . $i . '"><c:v>' . self::esc((string)$l) . '</c:v></c:pt>';
        foreach (array_values($ch['values']) as $i => $v) $numPts .= '<c:pt idx="' . $i . '"><c:v>' . (float)$v . '</c:v></c:pt>';
        $txt = fn(int $size, string $rgb) => '<c:txPr><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="' . $size . '"><a:solidFill><a:srgbClr val="' . $rgb . '"/></a:solidFill><a:latin typeface="Calibri"/></a:defRPr></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr>';
        $title = '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="1100" b="1"/></a:pPr><a:r><a:rPr lang="en-US" sz="1100" b="1"><a:solidFill><a:srgbClr val="17140F"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'
               . self::esc($ch['title']) . '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<c:roundedCorners val="0"/><c:chart>' . $title . '<c:autoTitleDeleted val="0"/><c:plotArea><c:layout/>'
             . '<c:barChart><c:barDir val="col"/><c:grouping val="clustered"/><c:varyColors val="0"/>'
             . '<c:ser><c:idx val="0"/><c:order val="0"/><c:tx><c:v>Sales</c:v></c:tx>'
             . '<c:spPr><a:solidFill><a:srgbClr val="E2632A"/></a:solidFill></c:spPr><c:invertIfNegative val="0"/>'
             . '<c:cat><c:strRef><c:f>' . $sheet . $abs($ch['cats']) . '</c:f><c:strCache><c:ptCount val="' . $n . '"/>' . $strPts . '</c:strCache></c:strRef></c:cat>'
             . '<c:val><c:numRef><c:f>' . $sheet . $abs($ch['vals']) . '</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="' . $n . '"/>' . $numPts . '</c:numCache></c:numRef></c:val>'
             . '</c:ser><c:gapWidth val="45"/><c:axId val="5001"/><c:axId val="5002"/></c:barChart>'
             . '<c:catAx><c:axId val="5001"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/>'
             . '<c:numFmt formatCode="General" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/>'
             . '<c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="B8AE9F"/></a:solidFill></a:ln></c:spPr>' . $txt(800, '5E5A55')
             . '<c:crossAx val="5002"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/><c:noMultiLvlLbl val="0"/></c:catAx>'
             . '<c:valAx><c:axId val="5002"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/>'
             . '<c:majorGridlines><c:spPr><a:ln w="6350"><a:solidFill><a:srgbClr val="E8E1D6"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>'
             . '<c:numFmt formatCode="&quot;₱&quot;#,##0" sourceLinked="0"/><c:majorTickMark val="none"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/>'
             . '<c:spPr><a:ln><a:noFill/></a:ln></c:spPr>' . $txt(800, '5E5A55')
             . '<c:crossAx val="5001"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>'
             . '<c:spPr><a:noFill/><a:ln><a:noFill/></a:ln></c:spPr></c:plotArea><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart>'
             . '<c:spPr><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln w="9525"><a:solidFill><a:srgbClr val="E5DDD0"/></a:solidFill></a:ln></c:spPr>'
             . $txt(900, '5E5A55') . '</c:chartSpace>';
    }

    private function drawingXml(array $ch): string {
        [$c1, $r1] = $ch['from']; [$c2, $r2] = $ch['to'];
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
             . '<xdr:twoCellAnchor editAs="oneCell"><xdr:from><xdr:col>' . $c1 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $r1 . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
             . '<xdr:to><xdr:col>' . $c2 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $r2 . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
             . '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="2" name="Sales chart"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>'
             . '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">'
             . '<c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId1"/>'
             . '</a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor></xdr:wsDr>';
    }

    public function build(): string {
        $tmp = tempnam(sys_get_temp_dir(), 'k72x');
        $z = new ZipArchive();
        if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create the Excel file.');
        $n = count($this->sheets);
        $rel = fn(string $inner) => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $inner . '</Relationships>';
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $charts = 0;
        foreach ($this->sheets as $i => [$name, $rows, $opt]) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            if (!empty($opt['chart'])) {
                $charts++;
                $ct .= '<Override PartName="/xl/drawings/drawing' . $charts . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
                     . '<Override PartName="/xl/charts/chart' . $charts . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
                $z->addFromString('xl/worksheets/_rels/sheet' . ($i + 1) . '.xml.rels', $rel('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $charts . '.xml"/>'));
                $z->addFromString('xl/drawings/drawing' . $charts . '.xml', $this->drawingXml($opt['chart']));
                $z->addFromString('xl/drawings/_rels/drawing' . $charts . '.xml.rels', $rel('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' . $charts . '.xml"/>'));
                $z->addFromString('xl/charts/chart' . $charts . '.xml', $this->chartXml($opt['chart']));
            }
        }
        $z->addFromString('[Content_Types].xml', $ct . '</Types>');
        $z->addFromString('_rels/.rels', $rel('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'));
        $z->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>KIDAN 72</dc:title><dc:creator>KIDAN 72 admin</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>';
        $rels = '';
        foreach ($this->sheets as $i => [$name, $rows, $opt]) {
            $wb .= '<sheet name="' . self::esc($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
            $z->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($i, $rows, $opt));
        }
        $wb .= '</sheets>';
        $defined = '';
        foreach ($this->sheets as $i => [$name, $rows, $opt]) {
            $q = '\'' . self::esc(str_replace("'", "''", $name)) . '\'!';
            $last = self::lastCol($rows);
            if (!empty($opt['header']) && !empty($opt['filter']) && count($rows) > $opt['header']) {
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">' . $q . '$A$' . $opt['header'] . ':$' . self::col($last - 1) . '$' . count($rows) . '</definedName>';
            }
            if (!empty($opt['printcols'])) {
                $defined .= '<definedName name="_xlnm.Print_Area" localSheetId="' . $i . '">' . $q . '$A$1:$' . self::col($opt['printcols'] - 1) . '$' . count($rows) . '</definedName>';
            }
            if (!empty($opt['header'])) {
                $defined .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . $q . '$' . $opt['header'] . ':$' . $opt['header'] . '</definedName>';
            }
        }
        if ($defined) $wb .= '<definedNames>' . $defined . '</definedNames>';
        $z->addFromString('xl/workbook.xml', $wb . '</workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', $rel($rels . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'));
        $z->addFromString('xl/styles.xml', $this->stylesXml());
        $z->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    public function download(string $filename): void {
        $data = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-store');
        echo $data;
        exit;
    }
}

/* Column letter for a 0-based column number (0 → A) */
function Xlsx_col(int $i): string {
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}
