<?php

namespace avadim\FastExcelWriter;

use avadim\FastExcelWriter\Exception\Exception;
use avadim\FastExcelWriter\Exception\ExceptionAddress;

/**
 * Class Sheet
 *
 * @package avadim\FastExcelWriter
 */
class Sheet
{
    // constants for auo width
    protected const WIDTH_LOWER_CASE_LETTER = 1.00;
    protected const WIDTH_UPPER_CASE_LETTER = 1.26;
    protected const WIDTH_DOTS_SYMBOLS = 0.20;
    protected const WIDTH_PADDING = 0.56;

    /** @var Excel */
    public Excel $excel;

    /** @var int Index of the sheet */
    public int $index;

    /** @var string Key of the sheet */
    public string $key;

    /** @var string $relId Id of the relationship */
    public string $relId;

    public bool $active = false;
    public string $fileName = '';
    public string $sheetName = '';
    public string $xmlName = '';

    public string $fileRels = '';
    public string $xmlRels = '';

    public bool $open = false;
    public bool $close = false;

    public int $rowCount = 0;
    public int $colCount = 0;

    public ?WriterBuffer $fileWriter = null;

    public array $defaultStyle = [];

    protected array $sheetStylesSummary = [];

    // ZERO based
    public int $freezeRows = 0;
    public int $freezeColumns = 0;

    public ?string $autoFilter = null;
    public string $absoluteAutoFilter = '';

    // ZERO based
    public array $colWidths = [];
    public array $colFormulas = [];
    public array $colStyles = [];

    protected array $colStylesSummary = [];

    // ZERO based
    public array $rowHeights = [];
    public array $rowStyles = [];

    // ZERO based
    protected array $cells = [];

    // Current row index
    protected int $currentRow = 0;

    // Current column index
    protected int $currentCol = 0;

    protected array $mergeCells = [];
    protected array $totalArea = [];
    protected array $areas = [];

    protected array $pageOptions = [];

    protected array $externalLinks = [];
    protected int $externalLinksCount = 0;

    protected array $lastTouch = [];

    /**
     * Sheet constructor
     *
     * @param string $sheetName
     */
    public function __construct(string $sheetName)
    {
        $this->setName($sheetName);
        $this->pageOptions['orientation'] = 'portrait';
        $this->cells = [
            'values' => [],
            'styles' => [],
        ];
        $this->_setCellData('A1', '', [], false);
        $this->lastTouch = [
            'cell' => [
                'row_idx' => 0,
                'col_idx' => 0,
            ],
            'row' => [],
            'area' => [
                'row_idx1' => 0,
                'row_idx2' => 0,
                'col_idx1' => 0,
                'col_idx2' => 0,
            ],
            'ref' => 'cell',
        ];
        $this->withLastCell();
    }

    /**
     * Compatibility with previous versions
     *
     * @param $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        if ($name === 'book') {
            return $this->excel;
        }
        $trace = debug_backtrace();

        trigger_error(
            'Undefined property: ' . get_class() . '::$' . $name .
            ' (call in file ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'] . ') ',
            E_USER_NOTICE);

        return null;
    }

    /**
     * @param WriterBuffer $fileWriter
     *
     * @return $this
     */
    public function setFileWriter($fileWriter)
    {
        if (!$this->fileWriter) {
            $this->fileWriter = $fileWriter;
            $this->fileName = $fileWriter->getFileName();
            $this->fileRels = $this->fileName . '.rels';
        }

        return $this;
    }

    /**
     * @param WriterBuffer $fileWriter
     *
     * @return $this
     */
    public function resetFileWriter($fileWriter)
    {
        $this->fileWriter = $fileWriter;
        $this->fileName = $fileWriter->getFileName();
        $this->fileRels = $this->fileName . '.rels';

        return $this;
    }

    /**
     * @return array
     */
    public function getExternalLinks(): array
    {
        return $this->externalLinks;
    }

    /**
     * @return string|null
     */
    public function getXmlRels(): ?string
    {
        if ($this->externalLinks) {
            $result = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
            $result .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            foreach ($this->externalLinks as $id => $data) {
                $result .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . $data['link'] . '" TargetMode="External"/>';
            }
            $result .= '</Relationships>';

            return $result;
        }

        return null;
    }

    /**
     * Set sheet name
     *
     * @param string $sheetName
     *
     * @return $this
     */
    public function setName(string $sheetName): Sheet
    {
        $this->sheetName = $sheetName;

        return $this;
    }

    /**
     * Get sheet name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->sheetName;
    }

    /**
     * @param string $option
     * @param mixed $value
     *
     * @return $this
     */
    public function setPageOptions(string $option, $value): Sheet
    {
        if ($this->rowCount) {
            throw new Exception('Cannot set page settings after rows writing');
        }
        $this->pageOptions[$option] = $value;

        return $this;
    }

    /**
     * @param $option
     *
     * @return mixed|null
     */
    public function getPageOptions($option)
    {
        return $this->pageOptions[$option] ?? null;
    }

    /**
     * @return $this
     */
    public function pageOrientationPortrait(): Sheet
    {
        return $this->setPageOptions('orientation', 'portrait');
    }

    /**
     * @return $this
     */
    public function pageOrientationLandscape(): Sheet
    {
        return $this->setPageOptions('orientation', 'landscape');
    }

    /**
     * @param int|string|null $numPage
     *
     * @return $this
     */
    public function pageFitToWidth($numPage = 1): Sheet
    {
        if (strtolower($numPage) === 'auto') {
            $numPage = 0;
        }
        if ($numPage >=0) {
            $this->setPageOptions('fit_width', (int)$numPage);
        }
        return $this;
    }

    /**
     * @param int|string|null $numPage
     *
     * @return $this
     */
    public function pageFitToHeight($numPage = 1): Sheet
    {
        if (strtolower($numPage) === 'auto') {
            $numPage = 0;
        }
        if ($numPage >=0) {
            $this->setPageOptions('fit_height', (int)$numPage);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getPageOrientation(): string
    {
        return $this->pageOptions['orientation'] ?? 'portrait';
    }

    /**
     * @return int
     */
    public function getPageFitToWidth(): int
    {
        return (int)($this->pageOptions['fit_width'] ?? 0);
    }

    /**
     * @return int
     */
    public function getPageFitToHeight(): int
    {
        return (int)($this->pageOptions['fit_height'] ?? 0);
    }

    /**
     * @return bool
     */
    public function getPageFit(): bool
    {
        return $this->getPageFitToWidth() || $this->getPageFitToHeight();
    }

    /**
     * setFreeze(3, 3) - number rows and columns to freeze
     * setFreeze('C3') - left top cell of free area
     *
     * @param mixed $freezeRows
     * @param mixed $freezeColumns
     *
     * @return $this
     */
    public function setFreeze($freezeRows, $freezeColumns = null): Sheet
    {
        if (!is_numeric($freezeRows) && null === $freezeColumns) {
            $dimension = Excel::rangeDimension($freezeRows);
            if ($dimension) {
                $this->setFreezeRows($dimension['row'] - 1);
                $this->setFreezeColumns($dimension['col'] - 1);
            }
        }
        else {
            $this->setFreezeRows((int)$freezeRows);
            $this->setFreezeColumns((int)$freezeColumns);
        }

        return $this;
    }

    /**
     * @param int $freezeRows Number rows to freeze
     *
     * @return $this
     */
    public function setFreezeRows(int $freezeRows): Sheet
    {
        $this->freezeRows = ($freezeRows > 0) ? $freezeRows : 0;

        return $this;
    }

    /**
     * @param int $freezeColumns Number columns to freeze
     *
     * @return $this
     */
    public function setFreezeColumns(int $freezeColumns): Sheet
    {
        $this->freezeColumns = ($freezeColumns > 0) ? $freezeColumns : 0;

        return $this;
    }

    /**
     * @param int|null $row
     * @param int|null $col
     *
     * @return $this
     */
    public function setAutofilter(?int $row = 1, ?int $col = 1): Sheet
    {
        if ($row >= 0) {
            if (empty($row)) {
                $this->autoFilter = null;
            }
            else {
                $this->autoFilter = Excel::cellAddress($row, $col);
            }
        }
        $this->absoluteAutoFilter = Excel::cellAddress($row, $col, true);

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return array
     */
    protected function normalizeColKeys(array $columns): array
    {
        $keys = array_keys($columns);
        if (reset($keys) === 0) {
            foreach ($keys as $n => $key) {
                if (is_int($key)) {
                    $keys[$n] = $key + 1;
                }
            }
            $result = array_combine($keys, array_values($columns));
        }
        else {
            $result = $columns;
        }

        return $result;
    }

    /**
     * Set options of columns (widths, styles, formats, etc)
     *
     * Call examples:
     *  setColOptions('B', ['width' = 20]) - options for column 'B'
     *  setColOptions('B:D', ['width' = 'auto']) - options for range of columns
     *  setColOptions(['B' => ['width' = 20], 'C' => ['color' = '#f00']]) - options for several columns 'B' and 'C'
     *
     * @param mixed $arg1
     * @param array|null $arg2
     *
     * @return $this
     */
    public function setColOptions($arg1, array $arg2 = null): Sheet
    {
        if ($arg2 === null) {
            $options = array_combine(Excel::colLetterRange(array_keys($arg1)), array_values($arg1));
            foreach ($options as $col => $colOptions) {
                if ($colOptions) {
                    $options[$col] = Style::normalize($colOptions);
                }
                else {
                    $options[$col] = null;
                }
            }
        }
        else {
            $options = [];
            $colNumbers = Excel::colNumberRange($arg1);
            if ($colNumbers) {
                $colOptions = Style::normalize($arg2);
                foreach ($colNumbers as $col) {
                    $options[$col] = $colOptions;
                }
            }
        }
        $options = array_filter($options);
        if ($options) {
            $options = $this->normalizeColKeys($options);
            foreach($options as $col => $colOptions) {
                $style = [];
                foreach($colOptions as $optionName => $optionValue) {
                    if ($optionName === 'width') {
                        $this->setColWidth($col, $optionValue);
                    }
                    elseif ($optionName === 'formula') {
                        $this->setColFormula($col, $optionValue);
                    }
                    else {
                        $style[$optionName] = $optionValue;
                    }
                }
                if ($style) {
                    $this->setColStyle($col, $style);
                }
            }
        }
        $this->clearSummary();

        return $this;
    }

    /**
     * Set width of single or multiple column(s)
     *
     * @param int|string|array $col Column number or column letter (or array of these)
     * @param int|float|string $width
     *
     * @return $this
     */
    public function setColWidth($col, $width): Sheet
    {
        $colIndexes = Excel::colIndexRange($col);
        foreach($colIndexes as $colIdx) {
            if ($colIdx >= 0) {
                if (strtolower($width) === 'auto') {
                    $this->colStyles[$colIdx]['options']['width-auto'] = true;
                }
                else {
                    $width = Style::numFloat($width);
                    if (is_numeric($width)) {
                        $this->colWidths[$colIdx] = $width;
                    }
                }
            }
        }
        $this->clearSummary();

        return $this;
    }

    /**
     * @param array $widths
     *
     * @return $this
     */
    public function setColWidths(array $widths): Sheet
    {
        if ($widths) {
            $widths = Excel::colKeysToLetters($widths);
            foreach ($widths as $col => $width) {
                $this->setColWidth($col, $width);
            }
        }

        return $this;
    }

    /**
     * Set width of single or multiple column(s)
     *
     * @param int|string|array $col Column number or column letter (or array of these)
     *
     * @return $this
     */
    public function setColWidthAuto($col): Sheet
    {
        return $this->setColWidth($col, 'auto');
    }

    /**
     * Set style of single or multiple column(s)
     *
     * @param int|string|array $col Column number or column letter (or array of these)
     * @param mixed $style
     *
     * @return $this
     */
    public function setColStyle($col, $style): Sheet
    {
        $colIndexes = Excel::colIndexRange($col);
        foreach($colIndexes as $colIdx) {
            if ($colIdx >= 0) {
                $style = Style::normalize($style);
                if (!empty($this->colStyles[$colIdx])) {
                    $this->colStyles[$colIdx] = array_replace_recursive($this->colStyles[$colIdx], $style);
                }
                else {
                    $this->colStyles[$colIdx] = $style;
                }
            }
        }
        $this->clearSummary();

        return $this;
    }

    /**
     * Set formula for single or multiple column(s)
     *
     * @param int|string|array $col Column number or column letter (or array of these)
     * @param string $formula
     *
     * @return $this
     */
    public function setColFormula($col, string $formula): Sheet
    {
        $colIndexes = Excel::colIndexRange($col);
        if ($formula) {
            if ($formula[0] !== '=') {
                $formula = '=' . $formula;
            }
        }
        foreach($colIndexes as $colIdx) {
            if ($colIdx >= 0) {
                $this->colFormulas[$colIdx] = $formula;
            }
        }

        return $this;
    }

    /**
     * Set format of single or multiple column(s)
     *
     * @param int|string|array $col Column number or column letter (or array of these)
     * @param mixed $format
     *
     * @return $this
     */
    public function setColFormat($col, $format): Sheet
    {
        $this->setColStyle($col, ['format' => $format]);
        $this->clearSummary();

        return $this;
    }

    /**
     * Set formats of columns
     *
     * @param array $formats
     *
     * @return $this
     */
    public function setColFormats(array $formats): Sheet
    {
        if ($formats) {
            $formats = array_combine(Excel::colLetterRange(array_keys($formats)), array_values($formats));
            foreach ($formats as $col => $format) {
                $this->setColFormat($col, $format);
            }
        }

        return $this;
    }

    /**
     * Height of a specific row
     *
     * @param $rowNum
     * @param $height
     *
     * @return $this
     */
    public function setRowHeight($rowNum, $height): Sheet
    {
        if (is_array($rowNum)) {
            foreach ($rowNum as $row) {
                $this->setRowHeight($row, $height);
            }
        }
        elseif (is_numeric($rowNum)) {
            $this->rowHeights[(int)$rowNum - 1] = str_replace(',', '.', (float)$height);
        }
        return $this;
    }

    /**
     * Multiple rows height
     *
     * @param array $heights
     *
     * @return $this
     */
    public function setRowHeights(array $heights): Sheet
    {
        foreach ($heights as $rowNum => $rowHeight) {
            $this->setRowHeight($rowNum, $rowHeight);
        }
        return $this;
    }

    /**
     * setRowOptions(3, ['height' = 20]) - options for row number 3
     * setRowOptions([3 => ['height' = 20], 4 => ['color' = '#f00']]) - options for several rows
     * setRowOptions('2:5', ['color' = '#f00']) - options for range of rows
     *
     * @param mixed $arg1
     * @param array|null $arg2
     *
     * @return $this
     */
    public function setRowOptions($arg1, array $arg2 = null): Sheet
    {
        if ($arg2 === null) {
            $options = $arg1;
        }
        else {
            if (is_string($arg1) && preg_match('/^(\d+):(\d+)$/', $arg1, $m)) {
                $options = [];
                for ($row = $m[1]; $row <= $m[2]; $row++) {
                    $options[$row] = $arg2;
                }
            }
            elseif (is_numeric($arg1)) {
                $options[(int)$arg1] = $arg2;
            }
            else {
                $options = [];
            }
        }
        foreach ($options as $rowNum => $rowOptions) {
            $rowIdx = (int)$rowNum - 1;
            if (isset($rowOptions['height'])) {
                $this->setRowHeight($rowNum, $rowOptions['height']);
                unset($rowOptions['height']);
            }
            if ($rowOptions) {
                if (isset($this->rowStyles[$rowIdx])) {
                    $this->rowStyles[$rowIdx] = array_replace_recursive($this->rowStyles[$rowIdx], $rowOptions);
                }
                else {
                    $this->rowStyles[$rowIdx] = $rowOptions;
                }
            }
        }
        return $this;
    }

    /**
     * @param string $address
     * @param string $link
     */
    protected function _addExternalLink(string $address, string $link)
    {
        $this->externalLinks[++$this->externalLinksCount] = [
            'cell' => $address,
            'link' => $link,
        ];
    }

    /**
     * @param Writer|null $writer
     * @param array|null $row Values of all cells of row (incl. empty)
     * @param array|null $rowOptions Specified style for the row
     * @param array|null $cellsOptions Styles of all cells of row (incl. empty)
     */
    protected function _writeRow(?Writer $writer, array $row = [], array $rowOptions = null, array $cellsOptions = null)
    {
        $rowAttr = '';
        if (!empty($rowOptions['height'])) {
            $height = $rowOptions['height'];
        }
        elseif (isset($this->rowHeights[$this->rowCount])) {
            $height = $this->rowHeights[$this->rowCount];
        }
        else {
            $height = null;
        }
        if ($height !== null) {
            $rowAttr .= ' customHeight="1" ht="' . (float)$height . '" ';
        }
        if (!empty($rowOptions['hidden'])) {
            $rowAttr .= ' hidden="1" ';
        }
        if (!empty($rowOptions['collapsed'])) {
            $rowAttr .= ' collapsed="1" ';
        }

        // add auto formulas of columns
        if ($this->colFormulas && $row) {
            foreach($this->colFormulas as $colIdx => $formula) {
                if (!isset($row[$colIdx])) {
                    $row[$colIdx] = $formula;
                }
            }
            ksort($row);
        }

        if ($row) {
            if (empty($this->sheetStylesSummary)) {
                if ($this->defaultStyle) {
                    $this->sheetStylesSummary = [
                        'general' => Style::mergeStyles([$this->excel->style->defaultStyle, $this->defaultStyle]),
                        'hyperlink' => Style::mergeStyles([$this->excel->style->hyperlinkStyle, $this->defaultStyle]),
                    ];
                }
                else {
                    $this->sheetStylesSummary = [
                        'general' => $this->excel->style->defaultStyle,
                        'hyperlink' => $this->excel->style->hyperlinkStyle,
                    ];
                }
            }
            $this->fileWriter->write('<row r="' . ($this->rowCount + 1) . '" ' . $rowAttr . '>');
            $rowIdx = $this->rowCount;
            foreach ($row as $colIdx => $cellValue) {
                if (!isset($this->colStylesSummary[$colIdx])) {
                    if (!isset($this->colStyles[$colIdx])) {
                        $this->colStylesSummary[$colIdx] = $this->sheetStylesSummary;
                    }
                    else {
                        $this->colStylesSummary[$colIdx] = [
                            'general' => Style::mergeStyles([
                                $this->sheetStylesSummary['general'],
                                $this->colStyles[$colIdx],
                            ]),
                            'hyperlink' => Style::mergeStyles([
                                $this->sheetStylesSummary['hyperlink'],
                                $this->colStyles[$colIdx],
                            ]),
                        ];
                    }
                }

                $styleStack = [
                    !empty($cellsOptions[$colIdx]['hyperlink']) ? $this->colStylesSummary[$colIdx]['hyperlink'] : $this->colStylesSummary[$colIdx]['general'],
                ];
                if (!empty($this->rowStyles[$rowIdx])) {
                    $styleStack[] = $this->rowStyles[$rowIdx];
                }
                if (!empty($this->cells['styles'][$rowIdx][$colIdx])) {
                    $styleStack[] = $this->cells['styles'][$rowIdx][$colIdx];
                }
                if (!empty($rowOptions)) {
                    $styleStack[] = $rowOptions;
                }
                if (!empty($cellsOptions[$colIdx])) {
                    $styleStack[] = $cellsOptions[$colIdx];
                }
                if (count($styleStack)) {
                    $cellStyle = Style::mergeStyles($styleStack);
                }
                else {
                    $cellStyle = $styleStack[0];
                }
                if (!empty($cellStyle['format']) && !empty($this->excel->style->defaultFormatStyles[$cellStyle['format']])) {
                    $cellStyle = Style::mergeStyles([$this->excel->style->defaultFormatStyles[$cellStyle['format']], $cellStyle]);
                }

                if (isset($cellStyle['hyperlink'])) {
                    if (!empty($cellStyle['hyperlink'])) {
                        if (is_string($cellStyle['hyperlink'])) {
                            $link = $cellStyle['hyperlink'];
                        }
                        else {
                            $link = $cellValue;
                        }
                        $cellValue = [
                            'shared_value' => $cellValue,
                            'shared_index' => $this->excel->addSharedString($cellValue),
                        ];
                        $this->_addExternalLink(Excel::cellAddress($rowIdx + 1, $colIdx + 1), $link);
                        if (!empty($this->excel->style->hyperlinkStyle)) {
                            $cellStyle = Style::mergeStyles([$this->excel->style->hyperlinkStyle, $cellStyle]);
                        }
                    }
                    unset($cellStyle['hyperlink']);
                }
                $cellStyleIdx = $this->excel->style->addStyle($cellStyle, $resultStyle);

                $numberFormat = $resultStyle['number_format'];
                $numberFormatType = $resultStyle['number_format_type'];

                if (!empty($cellStyle['options']['width-auto'])) {
                    $this->_columnWidth($colIdx, $cellValue, $numberFormat, $resultStyle ?? []);
                }

                if (!$writer) {
                    $writer = $this->excel->getWriter();
                }
                $writer->_writeCell($this->fileWriter, $rowIdx + 1, $colIdx + 1, $cellValue, $numberFormatType, $cellStyleIdx);
                $colIdx++;
                if ($colIdx > $this->colCount) {
                    $this->colCount = $colIdx;
                }
            }
            $this->fileWriter->write('</row>');
        }
        else {
            $this->fileWriter->write('<row r="' . ($this->rowCount + 1) . '" ' . $rowAttr . '/>');
        }
        $this->rowCount++;
    }

    /**
     * @param string $str
     * @param int|float $fontSize
     * @param bool|null $numFormat
     *
     * @return float
     */
    protected function _calcWidth(string $str, $fontSize, ?bool $numFormat = false): float
    {
        if ($numFormat && strpos($str, ';')) {
            $lenArray = [];
            foreach (explode(';', $str) as $part) {
                $lenArray[] = $this->_calcWidth($part, $fontSize);
            }

            return max(...$lenArray);
        }

        $len = mb_strlen($str);
        $upperCount = 0;
        $dotsCount = 0;
        if (preg_match_all("/[[:upper:]#@02-9]/u", $str, $matches)) {
            $upperCount = count($matches[0]);
        }
        if (preg_match_all("/[,\.\-\+]/u", $str, $matches)) {
            $dotsCount = count($matches[0]);
        }
        $k = $fontSize / 10;

        return ($len - $upperCount - $dotsCount) * self::WIDTH_LOWER_CASE_LETTER * $k +
            $upperCount * self::WIDTH_UPPER_CASE_LETTER * $k +
            $dotsCount * self::WIDTH_DOTS_SYMBOLS * $k + self::WIDTH_PADDING;
    }

    /**
     * @param mixed $value
     * @param string $format
     *
     * @return string
     */
    protected function _formatValue($value, string $format): string
    {
        if (is_numeric($value) && preg_match('/0(\.0+)?/', $format, $m)) {
            $value = number_format($value, $m[1] ? strlen($m[1]) - 1 : 0);
            $cnt = substr_count($format, '\\');
            if ($cnt) {
                $value .= str_repeat('-', $cnt);
            }
            if (preg_match('/\[\$.+\]/U', $format, $m)) {
                $value .= str_replace(['[$', ']'], '', $m[0]);
            }

            return $value;
        }

        return $format;
    }

    /**
     * @param $colNum
     * @param $cellValue
     * @param $numberFormat
     * @param $style
     */
    protected function _columnWidth($colNum, $cellValue, $numberFormat, $style)
    {
        static $cache = [];

        if ($cellValue) {
            $fontSize = $style['font']['val']['size'] ?? 10;
            $value = (isset($cellValue['shared_value'])) ? $cellValue['shared_value'] : $cellValue;
            $key = '[[[' . $fontSize . ']]][[[' . $numberFormat . ']]][[[' . $value . ']]]';
            if (isset($cache[$key])) {
                $len = $cache[$key];
            }
            else {
                $len = $this->_calcWidth($value, $fontSize);
                if ($numberFormat !== 'GENERAL') {
                    $numberFormat = $this->_formatValue($value, $numberFormat);
                    $len = max($len, $this->_calcWidth(str_replace('\\', '', $numberFormat), $fontSize, true));
                }
                $cache[$key] = $len;
            }
            if (empty($this->colWidths[$colNum]) || $this->colWidths[$colNum] < $len) {
                $this->colWidths[$colNum] = $len;
            }
        }
    }

    protected function clearSummary()
    {
        if ($this->sheetStylesSummary) {
            $this->sheetStylesSummary = [];
        }
        if ($this->colStylesSummary) {
            $this->colStylesSummary = [];
        }
    }

    /**
     * Sets default style
     *
     * @param array $style
     *
     * @return $this
     */
    public function setDefaultStyle(array $style): Sheet
    {
        $this->defaultStyle = $style;
        $this->clearSummary();

        return $this;
    }

    /**
     * Returns default style
     *
     * @return array
     */
    public function getDefaultStyle(): array
    {
        return $this->defaultStyle;
    }

    /**
     * Write value to the current cell and move pointer to the next cell in the row
     *
     * @param mixed $value
     * @param array|null $styles
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function writeCell($value, array $styles = null): Sheet
    {
        if ($this->lastTouch['ref'] === 'row') {
            $this->_writeCurrentRow();
        }
        ///-- $styles = $styles ? Style::normalize($styles) : [];
        if ($this->currentRow < $this->rowCount) {
            $this->currentRow = $this->rowCount;
        }
        $cellAddress = [
            'row' => 1 + $this->currentRow,
            'col' => 1 + $this->currentCol++,
        ];
        $this->_setCellData($cellAddress, $value, $styles, false);
        $this->withLastCell();

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function nextCell(): Sheet
    {
        $this->writeCell(null);

        return $this;
    }

    /**
     * writeHeader(['title1', 'title2', 'title3']) - texts for cells of header
     * writeHeader(['title1' => 'text', 'title2' => 'YYYY-MM-DD', 'title3' => ['format' => ..., 'font' => ...]]) - texts and formats of columns
     * writeHeader([...], [...]) - texts and formats of columns and options of row
     *
     * @param array $header
     * @param array|null $rowStyle
     *
     * @return $this
     */
    public function writeHeader(array $header, array $rowStyle = null): Sheet
    {
        $rowValues = [];
        $colStyles = [];
        $colNum = 0;
        foreach($header as $key => $val) {
            if (!is_int($key)) {
                $rowValues[$colNum] = $key;
                if (is_scalar($val)) {
                    $colStyles[$colNum]['format'] = $val;
                }
                else {
                    $colStyles[$colNum] = $val;
                }
            }
            else {
                $rowValues[$colNum] = $val;
                $colStyles[$colNum] = null;
            }
            $colNum++;
        }
        $this->writeRow($rowValues, $rowStyle);
        if ($colStyles) {
            $this->setColOptions($colStyles);
        }

        return $this;
    }

    /**
     * @param string|array $cellAddress
     *
     * @return array|bool
     */
    protected function _moveTo(&$cellAddress)
    {
        $address = $this->_parseAddress($cellAddress);
        if (!isset($address['row'], $address['col'])) {
            ExceptionAddress::throwNew('Wrong cell address %s', print_r($address, 1));
        }
        elseif ($address['row'] <= $this->rowCount) {
            ExceptionAddress::throwNew('Row number must be greater then written rows');
        }
        else {
            $cellAddress = $address;
        }

        if (isset($address['colIndex'], $address['rowIndex'])) {
            $this->currentCol = $address['colIndex'];
            $this->currentRow = $address['rowIndex'];
        }
        else {
            while ($this->currentRow < $cellAddress['row'] - 1) {
                $this->nextRow();
            }
        }

        return $address;
    }

    /**
     * Write value to the specified cell and move pointer to the next cell in the row
     *
     * $cellAddress formats:
     *  'B5'
     *  'B5:C7'
     *  ['row' => 6, 'col' => 7]
     *  [6, 7]
     *
     * @param string|array $cellAddress
     * @param mixed $value
     * @param array|null $styles
     *
     * @return $this
     */
    public function writeTo($cellAddress, $value, ?array $styles = []): Sheet
    {
        $address = $this->_moveTo($cellAddress);

        ///-- $styles = $styles ? Style::normalize($styles) : null;
        $this->_setCellData($cellAddress, $value, $styles, true, true);
        if (isset($address['width'], $address['range']) && $address['width'] > 1) {
            $this->mergeCells($address['range']);
            $this->currentCol += $address['width'];
        }
        else {
            $this->currentCol++;
        }
        $this->withLastCell();

        return $this;
    }

    /**
     * Merge cells
     *
     * mergeCells('A1:C3')
     * mergeCells(['A1:B2', 'C1:D2'])
     *
     * @param array|string|int $rangeSet
     *
     * @return $this
     */
    public function mergeCells($rangeSet): Sheet
    {
        foreach((array)$rangeSet as $range) {
            if (isset($this->mergeCells[$range]) || empty($range)) {
                // cells are already merged
                continue;
            }
            $dimension = Excel::rangeDimension($range, true);
            // check intersection with saved merged cells
            foreach ($this->mergeCells as $savedRange => $savedDimension) {
                if (
                    ((($dimension['rowNum1'] >= $savedDimension['rowNum1']) && ($dimension['rowNum1'] <= $savedDimension['rowNum2']))
                        || (($dimension['rowNum2'] >= $savedDimension['rowNum1']) && ($dimension['rowNum2'] <= $savedDimension['rowNum2'])))
                    && ((($dimension['colNum1'] >= $savedDimension['colNum1']) && ($dimension['colNum1'] <= $savedDimension['colNum2']))
                        || (($dimension['colNum2'] >= $savedDimension['colNum1']) && ($dimension['colNum2'] <= $savedDimension['colNum2'])))
                ) {
                    throw new Exception("Cannot merge cells $range because they are intersecting with $savedRange");
                }
            }
            $this->mergeCells[$dimension['range']] = $dimension;
        }

        return $this;
    }

    /**
     * Merge relative cells
     *
     * mergeCells(3) -> 3 columns of current row -> mergeCells('A5:C5') // if current row is 5
     * mergeCells(['RC3:RC5', 'RC6:RC7']) -> mergeCells(['C7:E7', 'F7:G7']) // if current row is 7
     *
     * @param array|string|int $rangeSet
     *
     * @return $this
     */
    public function mergeRelCells($rangeSet): Sheet
    {
        if (is_int($rangeSet)) {
            $rangeSet = 'A' . $this->rowCount . ':' . Excel::colLetter($rangeSet)  . $this->rowCount;
        }
        foreach((array)$rangeSet as $range) {
            if (isset($this->mergeCells[$range]) || empty($range)) {
                // cells are already merged
                continue;
            }
            $dimension = $this->_rangeDimension($range, 1, 0);
            // check intersection with saved merged cells
            foreach ($this->mergeCells as $savedRange => $savedDimension) {
                if (
                    ((($dimension['rowNum1'] >= $savedDimension['rowNum1']) && ($dimension['rowNum1'] <= $savedDimension['rowNum2']))
                        || (($dimension['rowNum2'] >= $savedDimension['rowNum1']) && ($dimension['rowNum2'] <= $savedDimension['rowNum2'])))
                    && ((($dimension['colNum1'] >= $savedDimension['colNum1']) && ($dimension['colNum1'] <= $savedDimension['colNum2']))
                        || (($dimension['colNum2'] >= $savedDimension['colNum1']) && ($dimension['colNum2'] <= $savedDimension['colNum2'])))
                ) {
                    if ($range !== $dimension['range']) {
                        $range .= ' (' . $dimension['range'] . ')';
                    }
                    throw new Exception("Cannot merge cells $range because they are intersecting with $savedRange");
                }
            }
            $this->mergeCells[$dimension['range']] = $dimension;
        }

        return $this;
    }

    /**
     * Returns merged cells
     *
     * @return array
     */
    public function getMergedCells(): array
    {
        return array_keys($this->mergeCells);
    }

    /**
     * @return void
     */
    protected function _writeCurrentRow()
    {
        $writer = $this->excel->getWriter();
        for ($rowIdx = $this->rowCount; $rowIdx <= $this->currentRow; $rowIdx++) {
            if (isset($this->cells['values'][$rowIdx])) {
                $values = $this->cells['values'][$rowIdx];
                unset($this->cells['values'][$rowIdx]);
            }
            else {
                $values = [];
            }
            if (isset($this->cells['styles'][$rowIdx])) {
                $styles = $this->cells['styles'][$rowIdx];
                unset($this->cells['styles'][$rowIdx]);
            }
            else {
                $styles = null;
            }
            if ($values || $styles) {
                if (!$this->open) {
                    $writer->writeSheetDataBegin($this);
                }

                $this->_writeRow($writer, $values, $this->rowStyles[$rowIdx] ?? null, $styles);
            }
            else {
                $this->rowCount++;
            }
            if (isset($this->rowStyles[$rowIdx])) {
                unset($this->rowStyles[$rowIdx]);
            }
        }

        $this->currentCol = Excel::MIN_COL;
        $this->currentRow++;

        $this->withLastCell();
    }

    /**
     * Write values to the current row
     *
     * @param array $rowValues Values of cells
     * @param array|null $rowStyle Style applied to the entire row
     * @param array|null $cellStyles Styles of specified cells in the row
     *
     * @return $this
     */
    public function writeRow(array $rowValues = [], array $rowStyle = null, array $cellStyles = null): Sheet
    {
        if ($this->currentCol) {
            $this->_writeCurrentRow();
        }

        if (!is_array($rowValues)) {
            $rowValues = [$rowValues];
        }
        if (is_array($cellStyles)) {
            $key = array_key_first($cellStyles);
            if (!is_int($key)) {
                $cellStyles = Excel::colKeysToIndexes($cellStyles);
            }
        }
        else {
            $cellStyles = null;
        }

        if ($rowStyle) {
            $this->rowStyles[$this->currentRow] = $rowStyle;
        }

        $this->lastTouch['area']['col_idx1'] = $this->lastTouch['area']['col_idx2'] = -1;
        if ($cellStyles) {
            $keys = array_keys($cellStyles);
            if ($rowValues) {
                $keys = array_unique($keys, array_keys($rowValues));
                sort($keys);
            }
            foreach ($keys as $colIdx) {
                if ($this->lastTouch['area']['col_idx1'] === -1) {
                    $this->lastTouch['area']['col_idx1'] = $colIdx;
                }
                $this->lastTouch['area']['col_idx2'] = $colIdx;
                $this->_setCellData(null, $rowValues[$colIdx] ?? null, $cellStyles[$colIdx] ?? null);
            }
        }
        elseif ($rowValues) {
            foreach ($rowValues as $colIdx => $value) {
                if ($this->lastTouch['area']['col_idx1'] === -1) {
                    $this->lastTouch['area']['col_idx1'] = $colIdx;
                }
                $this->lastTouch['area']['col_idx2'] = $colIdx;
                $this->currentCol = $this->lastTouch['cell']['col_idx'] = $colIdx;
                $this->_setCellData(null, $value);
            }
            $this->currentCol++;
        }
        $this->withLastRow();

        return $this;
    }

    /**
     * Move to the next row
     *
     * @param array|null $options
     *
     * @return $this
     */
    public function nextRow(?array $options = []): Sheet
    {
        if (!empty($options)) {
            $this->rowStyles[$this->currentRow] = $options;
        }
        $this->_writeCurrentRow();
        $this->withLastRow();

        return $this;
    }

    /**
     * Skip rows
     *
     * @param int|null $rowCount
     *
     * @return $this
     */
    public function skipRow(?int $rowCount = 1): Sheet
    {
        for ($i = 0; $i < $rowCount; $i++) {
            $this->nextRow();
        }

        return $this;
    }

    /**
     * Make area for writing
     *
     * @param string $range A1:Z9 or R1C1:R9C28
     *
     * @return Area
     */
    public function makeArea(string $range): Area
    {
        $area = new Area($this, $range);

        $this->areas[] = $area->setIndex(count($this->areas));
        $coord = $area->getCoord();
        if (empty($this->totalArea['coord'])) {
            $this->totalArea['coord'] = $coord;
        }
        else {
            if ($this->totalArea['coord'][0]['row'] > $coord[0]['row']) {
                $this->totalArea['coord'][0]['row'] = $coord[0]['row'];
            }
            if ($this->totalArea['coord'][0]['col'] > $coord[0]['col']) {
                $this->totalArea['coord'][0]['col'] = $coord[0]['col'];
            }
            if ($this->totalArea['coord'][1]['row'] > $coord[1]['row']) {
                $this->totalArea['coord'][1]['row'] = $coord[1]['row'];
            }
            if ($this->totalArea['coord'][1]['col'] > $coord[1]['col']) {
                $this->totalArea['coord'][1]['col'] = $coord[1]['col'];
            }
        }


        return $area;
    }

    /**
     * Begin a new area
     *
     * @param string|null $cellAddress Upper left cell of area
     *
     * @return Area
     */
    public function beginArea(string $cellAddress = null): Area
    {
        if (null === $cellAddress) {
            $cellAddress = 'A' . ($this->rowCount + 1);
        }
        $dimension = Excel::rangeDimension($cellAddress, true);
        if ($dimension['rowNum1'] <= $this->rowCount) {
            throw new Exception("Cannot make area from $cellAddress (row number must be greater then written rows)");
        }
        $maxCell = Excel::cellAddress(Excel::MAX_ROW, Excel::MAX_COL);

        return $this->makeArea($cellAddress . ':' . $maxCell);
    }

    /**
     * @param $cellAddress
     *
     * @return array|bool
     */
    protected function _parseAddress($cellAddress)
    {
        if (is_array($cellAddress) && isset($cellAddress['row'], $cellAddress['col'])) {
            return $cellAddress;
        }

        $result = ['row' => null, 'col' => null];
        if (is_string($cellAddress)) {
            $result = $this->_rangeDimension($cellAddress);
        }
        elseif (is_array($cellAddress)) {
            if (isset($cellAddress['row'], $cellAddress['col'])) {
                $result = $cellAddress;
            }
            else {
                [$row, $col] = array_values($cellAddress);
                $result = ['row' => $row, 'col' => $col];
            }
        }

        return $result;
    }

    /**
     * @param string $cellAddress
     * @param int|null $colOffset
     * @param int|null $rowOffset
     *
     * @return array|bool
     */
    protected function _rangeDimension(string $cellAddress, ?int $colOffset = 1, ?int $rowOffset = 1)
    {
        if (preg_match('/^R\[?(-?\d+)?\]?C/', $cellAddress)) {
            // relative address
            $relAddress = $cellAddress;
            $cellAddress = Excel::colLetter($colOffset) . ($this->rowCount + $rowOffset);
            $dimension = Excel::rangeDimensionRelative($cellAddress, $relAddress, true);
        }
        else {
            // absolute address
            $dimension = Excel::rangeDimension($cellAddress, true);
        }

        return $dimension;
    }

    /**
     * @param string|array|null $cellAddress
     * @param mixed $value
     * @param mixed|null $styles
     * @param bool|null $merge
     * @param bool|null $changeCurrent
     *
     * @throws Exception
     */
    protected function _setCellData($cellAddress, $value, $styles = null, ?bool $merge = false, ?bool $changeCurrent = false)
    {
        if (null === $cellAddress) {
            $rowIdx = $this->lastTouch['cell']['row_idx'];
            $colIdx = $this->lastTouch['cell']['col_idx'];
        }
        else {
            if (isset($cellAddress['row'], $cellAddress['col'])) {
                $row = $cellAddress['row'];
                $col = $cellAddress['col'];
                $dimension = $cellAddress;
            }
            else {
                $dimension = $this->_parseAddress($cellAddress);
                $row = $dimension['row'];
                $col = $dimension['col'];
                if ($merge && isset($dimension['width'], $dimension['height']) && ($dimension['width'] > 1 || $dimension['height'] > 1)) {
                    $this->mergeCells($dimension['range']);
                }
            }

            if ($row === null || $col === null) {
                ExceptionAddress::throwNew('Wrong cell address %s', print_r($cellAddress, 1));
            }
            if ($row < $this->currentRow) {
                ExceptionAddress::throwNew('Row number must be greater then written rows');
            }
            $rowIdx = $row - 1;
            $colIdx = $col - 1;

            $this->lastTouch['cell'] = ['row_idx' => $rowIdx, 'col_idx' => $colIdx, 'dimension' => $dimension];
        }

        if ($value !== null) {
            if (!is_scalar($value)) {
                $addr = Excel::cellAddress($colIdx + 1, $rowIdx + 1);
                Exception::throwNew('Value for cell %s must be scalar', $addr);
            }
            $this->cells['values'][$rowIdx][$colIdx] = $value;
            if ($changeCurrent) {
                $this->currentRow = $rowIdx;
                $this->currentCol = $colIdx;
            }
        }
        if ($styles !== null) {
            $this->cells['styles'][$rowIdx][$colIdx] = $styles;
        }
    }

    /**
     * Set a value to the single cell or to the cell range
     *
     * $cellAddress formats:
     *      'B5'
     *      'B5:C7'
     *      ['row' => 6, 'col' => 7]
     *      [6, 7]
     *
     * @param string|array $cellAddress
     * @param mixed $value
     * @param array|null $styles
     *
     * @return $this
     */
    public function setValue($cellAddress, $value, ?array $styles = null): Sheet
    {
        ///-- $styles = $styles ? Style::normalize($styles) : null;
        $this->_setCellData($cellAddress, $value, $styles, true);

        return $this;
    }

    /**
     * Set a formula to the single cell or to the cell range
     *
     * $cellAddress formats:
     *      'B5'
     *      'B5:C7'
     *      ['row' => 6, 'col' => 7]
     *      [6, 7]
     *
     * @param string|array $cellAddress
     * @param mixed $value
     * @param array|null $styles
     *
     * @return $this
     */
    public function setFormula($cellAddress, $value, array $styles = null): Sheet
    {
        if (empty($value)) {
            $value = null;
        }
        elseif (strpos($value, '=') !== 0) {
            $value = '=' . $value;
        }

        ///-- $styles = $styles ? Style::normalize($styles) : null;
        $this->_setCellData($cellAddress, $value, $styles, true);

        return $this;
    }

    /**
     * @param string $cellAddress
     * @param mixed $style
     * @param bool|null $mergeStyles
     *
     * @return $this
     */
    public function applyStyle(string $cellAddress, $style, ?bool $mergeStyles = false): Sheet
    {
        $dimension = $this->_rangeDimension($cellAddress);
        if ($dimension['rowNum1'] <= $this->rowCount) {
            throw new Exception('Row number must be greater then written rows');
        }
        ///-- $style = Style::normalize($style);
        for ($row = $dimension['rowNum1'] - 1; $row < $dimension['rowNum2']; $row++) {
            for ($col = $dimension['colNum1'] - 1; $col < $dimension['colNum2']; $col++) {
                if ($mergeStyles && isset($this->cells['styles'][$row][$col])) {
                    $this->cells['styles'][$row][$col] = array_merge($this->cells['styles'][$row][$col], $style);
                }
                else {
                    $this->cells['styles'][$row][$col] = $style;
                }
                if (!isset($this->cells['values'][$row][$col])) {
                    $this->cells['values'][$row][$col] = '';
                }
            }
        }

        return $this;
    }

    /**
     * @param string $cellAddr
     * @param array $style
     *
     * @return $this
     */
    public function setStyle(string $cellAddr, array $style): Sheet
    {
        return $this->applyStyle($cellAddr, $style, false);
    }

    /**
     * @param string $cellAddr
     * @param array $style
     *
     * @return $this
     */
    public function addStyle(string $cellAddr, array $style): Sheet
    {
        return $this->applyStyle($cellAddr, $style, true);
    }

    /**
     * @param string $cellAddr
     * @param string $format
     *
     * @return $this
     */
    public function setFormat(string $cellAddr, string $format): Sheet
    {
        return $this->applyStyle($cellAddr, ['format' => $format], true);
    }

    /**
     * @param array $addr
     * @param string $key
     * @param mixed $val
     * @param bool|null $append
     *
     * @return $this
     */
    protected function _setOptions(array $addr, string $key, $val, ?bool $append = false): Sheet
    {
        if ($addr) {
            $rowIdx = $addr['row_idx'];
            $colIdx = $addr['col_idx'];
            $ref = 'cell';
        }
        else {
            $rowIdx = $this->lastTouch['cell']['row_idx'];
            $colIdx = $this->lastTouch['cell']['col_idx'];
            $ref = $this->lastTouch['ref'];
        }

        if ($ref === 'cell') {
            if ($append && !empty($this->cells['styles'][$rowIdx][$colIdx][$key])) {
                if (!is_array($this->cells['styles'][$rowIdx][$colIdx][$key])) {
                    $this->cells['styles'][$rowIdx][$colIdx][$key] = [$this->cells['styles'][$rowIdx][$colIdx][$key]];
                }
                foreach ($val as $k => $v) {
                    $this->cells['styles'][$rowIdx][$colIdx][$key][$k] = $v;
                }
            }
            else {
                $this->cells['styles'][$rowIdx][$colIdx][$key] = $val;
            }
        }
        else {
            $rowIdx = $this->lastTouch['row']['row_idx'];
            if ($append && !empty($this->rowStyles[$rowIdx][$key])) {
                if (!is_array($this->rowStyles[$rowIdx][$key])) {
                    $this->rowStyles[$rowIdx][$key] = [$this->rowStyles[$rowIdx][$key]];
                }
                if (!is_array($val)) {
                    $this->rowStyles[$rowIdx][$key][] = $val;
                }
                else {
                    foreach ($val as $k => $v) {
                        $this->rowStyles[$rowIdx][$key][$k] = $v;
                    }
                }
            }
            else {
                $this->rowStyles[$rowIdx][$key] = $val;
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function withLastCell(): Sheet
    {
        $this->lastTouch['cell'] = [
            'row_idx' => $this->currentRow,
            'col_idx' => $this->currentCol,
        ];
        $this->lastTouch['row'] = [
            'row_idx' => $this->currentRow,
        ];
        $this->lastTouch['area'] = [
            'row_idx1' => $this->currentRow,
            'row_idx2' => $this->currentRow,
            'col_idx1' => $this->currentCol,
            'col_idx2' => $this->currentCol,
        ];
        $this->lastTouch['ref'] = 'cell';

        return $this;
    }

    /**
     * @return $this
     */
    public function withLastRow(): Sheet
    {
        $this->lastTouch['cell'] = [
            'row_idx' => $this->currentRow,
            'col_idx' => $this->currentCol,
        ];
        $this->lastTouch['row'] = ['row_idx' => $this->currentRow];
        $this->lastTouch['ref'] = 'row';

        return $this;
    }

    /**
     * @param string $range
     * @param string|array $style
     *
     * @return $this
     */
    public function setOuterBorder(string $range, $style): Sheet
    {
        $dimension = $this->_rangeDimension($range);
        if ($dimension['rowNum1'] <= $this->rowCount) {
            throw new Exception('Row number must be greater then written rows');
        }
        $border = Style::borderOptions($style);
        foreach ($border as $side => $sideOptions) {
            if ($side & Style::BORDER_LEFT) {
                $colIdx = $dimension['colNum1'] - 1;
                for ($rowIdx = $dimension['rowNum1'] - 1; $rowIdx < $dimension['rowNum2']; $rowIdx++) {
                    if (!empty($sideOptions['style'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['left']['style'] = $sideOptions['style'];
                    }
                    if (!empty($sideOptions['color'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['left']['color'] = $sideOptions['color'];
                    }
                }
            }

            if ($side & Style::BORDER_RIGHT) {
                $colIdx = $dimension['colNum2'] - 1;
                for ($rowIdx = $dimension['rowNum1'] - 1; $rowIdx < $dimension['rowNum2']; $rowIdx++) {
                    if (!empty($sideOptions['style'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['right']['style'] = $sideOptions['style'];
                    }
                    if (!empty($sideOptions['color'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['right']['color'] = $sideOptions['color'];
                    }
                }
            }

            if ($side & Style::BORDER_TOP) {
                $rowIdx = $dimension['row'] - 1;
                for ($colIdx = $dimension['colNum1'] - 1; $colIdx < $dimension['colNum2']; $colIdx++) {
                    if (!empty($sideOptions['style'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['top']['style'] = $sideOptions['style'];
                    }
                    if (!empty($sideOptions['color'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['top']['color'] = $sideOptions['color'];
                    }
                }
            }

            if ($side & Style::BORDER_BOTTOM) {
                $rowIdx = $dimension['rowNum2'] - 1;
                for ($colIdx = $dimension['colNum1'] - 1; $colIdx < $dimension['colNum2']; $colIdx++) {
                    if (!empty($sideOptions['style'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['bottom']['style'] = $sideOptions['style'];
                    }
                    if (!empty($sideOptions['color'])) {
                        $this->cells['styles'][$rowIdx][$colIdx]['border']['bottom']['color'] = $sideOptions['color'];
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function writeAreas(): Sheet
    {
        $writer = $this->excel->getWriter();
        if ($this->open) {
            $this->writeAreasRows($writer);
        } else {
            $writer->writeSheetDataBegin($this);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function clearAreas(): Sheet
    {
        $this->cells = [];
        $this->areas = [];
        $this->totalArea = [];

        return $this;
    }

    /**
     * Write all active areas
     *
     * @return $this
     */
    public function writeAreasRows($writer): Sheet
    {
        if (!$this->areas) {
            return $this;
        }

        if (!empty($this->cells['values']) || !empty($this->cells['styles'])) {
            $maxRow = max(array_keys($this->cells['values']) + array_keys($this->cells['styles']));
            // writes row by row
            for ($numRow = $this->rowCount; $numRow <= $maxRow; $numRow++) {
                if (isset($this->cells['values'][$numRow]) || isset($this->cells['styles'][$numRow])) {
                    $colMax = 0;
                    $rowValues = $this->cells['values'][$numRow] ?? [];
                    if ($rowValues && ($keyMax = max(array_keys($rowValues))) > $colMax) {
                        $colMax = $keyMax;
                    }
                    $rowStyles = $this->cells['styles'][$numRow] ?? [];
                    if ($rowStyles && ($keyMax = max(array_keys($rowStyles))) > $colMax) {
                        $colMax = $keyMax;
                    }

                    for ($numCol = Excel::MIN_COL; $numCol <= $colMax; $numCol++) {
                        if (!isset($rowValues[$numCol])) {
                            $rowValues[$numCol] = null;
                        }
                        if (!isset($rowStyles[$numCol])) {
                            $rowStyles[$numCol] = [];
                        }
                    }
                    // array of all values
                    ksort($rowValues);
                    // array of all styles
                    ksort($rowStyles);
                    $this->_writeRow($writer, array_values($rowValues), null, array_values($rowStyles));
                }
                else {
                    $this->_writeRow($writer, [null]);
                }
            }
            $this->clearAreas();
        }

        return $this;
    }

    /**
     * @param $writer
     *
     * @return void
     */
    public function writeDataBegin($writer)
    {
        // if already initialized
        if ($this->open) {
            return;
        }

        $sheetFileName = $writer->tempFilename();
        $this->setFileWriter($writer::makeWriteBuffer($sheetFileName));

        $this->fileWriter->write('<sheetData>');

        $this->open = true;
        if ($this->areas) {
            $this->writeAreasRows($writer);
        }
    }

    /**
     * @return void
     */
    public function writeDataEnd()
    {
        if ($this->close) {
            return;
        }
        if ($this->areas) {
            $this->writeAreas();
        }

        $this->_writeCurrentRow();
        $this->fileWriter->flush(true);
        $this->fileWriter->write('</sheetData>');
    }

    /**
     * @return string
     */
    public function maxCell(): string
    {
        return Excel::cellAddress($this->rowCount, $this->colCount);
    }

    /**
     * @return bool
     */
    public function isRightToLeft(): bool
    {
        return $this->excel->isRightToLeft();
    }

    // === DESIGN STYLES === /

    /**
     * Sets all borders style
     *
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorder(string $style, string $color): Sheet
    {
        $options = [
            Style::BORDER_TOP => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
            Style::BORDER_RIGHT => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
            Style::BORDER_BOTTOM => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
            Style::BORDER_LEFT => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
        ];
        $this->_setOptions([], Style::BORDER, $options, true);

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderTop(string $style, string $color): Sheet
    {
        $options = [
            Style::BORDER_TOP => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
        ];
        $this->_setOptions([], Style::BORDER, $options, true);

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderRight(string $style, string $color): Sheet
    {
        $options = [
            Style::BORDER_RIGHT => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
        ];
        $this->_setOptions([], Style::BORDER, $options, true);

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderBottom(string $style, string $color): Sheet
    {
        $options = [
            Style::BORDER_BOTTOM => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
        ];
        $this->_setOptions([], Style::BORDER, $options, true);

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderLeft(string $style, string $color): Sheet
    {
        $options = [
            Style::BORDER_LEFT => [
                Style::BORDER_STYLE => $style,
                Style::BORDER_COLOR => $color,
            ],
        ];
        $this->_setOptions([], Style::BORDER, $options, true);

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderOuter(string $style, string $color): Sheet
    {
        if ($this->lastTouch['area']['row_idx1'] === $this->lastTouch['area']['row_idx2']
            && $this->lastTouch['area']['col_idx1'] === $this->lastTouch['area']['col_idx2']) {
            $options = [
                Style::BORDER_TOP => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_RIGHT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_BOTTOM => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_LEFT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $addr = [
                'row_idx' => $this->lastTouch['area']['row_idx1'],
                'col_idx' => $this->lastTouch['area']['col_idx1'],
            ];
            $this->_setOptions($addr, Style::BORDER, $options, true);
        }
        else {
            $options = [
                Style::BORDER_LEFT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_TOP => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $addr = [
                'row_idx' => $this->lastTouch['area']['row_idx1'],
                'col_idx' => $this->lastTouch['area']['col_idx1'],
            ];
            $this->_setOptions($addr, Style::BORDER, $options, true);

            $options = [
                Style::BORDER_TOP => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_RIGHT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $addr = [
                'row_idx' => $this->lastTouch['area']['row_idx1'],
                'col_idx' => $this->lastTouch['area']['col_idx2'],
            ];
            $this->_setOptions($addr, Style::BORDER, $options, true);

            $options = [
                Style::BORDER_RIGHT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_BOTTOM => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $addr = [
                'row_idx' => $this->lastTouch['area']['row_idx2'],
                'col_idx' => $this->lastTouch['area']['col_idx2'],
            ];
            $this->_setOptions($addr, Style::BORDER, $options, true);

            $options = [
                Style::BORDER_BOTTOM => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_LEFT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $addr = [
                'row_idx' => $this->lastTouch['area']['row_idx2'],
                'col_idx' => $this->lastTouch['area']['col_idx1'],
            ];
            $this->_setOptions($addr, Style::BORDER, $options, true);

            if ($this->lastTouch['area']['col_idx2'] - $this->lastTouch['area']['col_idx1'] > 1) {
                $options1 = [
                    Style::BORDER_TOP => [
                        Style::BORDER_STYLE => $style,
                        Style::BORDER_COLOR => $color,
                    ],
                ];
                $options2 = [
                    Style::BORDER_BOTTOM => [
                        Style::BORDER_STYLE => $style,
                        Style::BORDER_COLOR => $color,
                    ],
                ];
                for ($colIdx = $this->lastTouch['area']['col_idx1'] + 1; $colIdx < $this->lastTouch['area']['col_idx2']; $colIdx++) {
                    $addr['col_idx'] = $colIdx;
                    $addr['row_idx'] = $this->lastTouch['area']['row_idx1'];
                    $this->_setOptions($addr, Style::BORDER, $options1, true);
                    $addr['row_idx'] = $this->lastTouch['area']['row_idx2'];
                    $this->_setOptions($addr, Style::BORDER, $options2, true);
                }
            }


            if ($this->lastTouch['area']['row_idx2'] - $this->lastTouch['area']['row_idx1'] > 1) {
                $options1 = [
                    Style::BORDER_LEFT => [
                        Style::BORDER_STYLE => $style,
                        Style::BORDER_COLOR => $color,
                    ],
                ];
                $options2 = [
                    Style::BORDER_RIGHT => [
                        Style::BORDER_STYLE => $style,
                        Style::BORDER_COLOR => $color,
                    ],
                ];
                for ($rowIdx = $this->lastTouch['area']['row_idx1'] + 1; $rowIdx < $this->lastTouch['area']['row_idx2']; $rowIdx++) {
                    $addr['row_idx'] = $rowIdx;
                    $addr['col_idx'] = $this->lastTouch['area']['col_idx1'];
                    $this->_setOptions($addr, Style::BORDER, $options1, true);
                    $addr['col_idx'] = $this->lastTouch['area']['col_idx2'];
                    $this->_setOptions($addr, Style::BORDER, $options2, true);
                }
            }
        }

        return $this;
    }

    /**
     * @param string $style
     * @param string $color
     *
     * @return $this
     */
    public function applyBorderInner(string $style, string $color): Sheet
    {
        if ($this->lastTouch['area']['row_idx1'] < $this->lastTouch['area']['row_idx2']
            || $this->lastTouch['area']['col_idx1'] < $this->lastTouch['area']['col_idx2']) {

            $options = [
                Style::BORDER_RIGHT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            for ($colIdx = $this->lastTouch['area']['col_idx1']; $colIdx < $this->lastTouch['area']['col_idx2']; $colIdx++) {
                $addr = [
                    'row_idx' => $this->lastTouch['area']['row_idx1'],
                    'col_idx' => $colIdx,
                ];
                $this->_setOptions($addr, Style::BORDER, $options, true);
                $addr = [
                    'row_idx' => $this->lastTouch['area']['row_idx2'],
                    'col_idx' => $colIdx,
                ];
                $this->_setOptions($addr, Style::BORDER, $options, true);
            }

            $options1 = [
                Style::BORDER_TOP => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_RIGHT => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_BOTTOM => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            $options2 = [
                Style::BORDER_TOP => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
                Style::BORDER_BOTTOM => [
                    Style::BORDER_STYLE => $style,
                    Style::BORDER_COLOR => $color,
                ],
            ];
            for ($rowIdx = $this->lastTouch['area']['row_idx1']; $rowIdx < $this->lastTouch['area']['row_idx2']; $rowIdx++) {
                for ($colIdx = $this->lastTouch['area']['col_idx1']; $colIdx < $this->lastTouch['area']['col_idx2']; $colIdx++) {
                    $addr = [
                        'row_idx' => $rowIdx,
                        'col_idx' => $colIdx,
                    ];
                    $this->_setOptions($addr, Style::BORDER, $options1, true);
                }
                $addr = [
                    'row_idx' => $rowIdx,
                    'col_idx' => $this->lastTouch['area']['col_idx2'],
                ];
                $this->_setOptions($addr, Style::BORDER, $options2, true);
            }
        }

        return $this;
    }

    /**
     * @param string $fontName
     * @param int|null $fontSize
     * @param string|null $fontStyle
     * @param string|null $fontColor
     *
     * @return $this
     */
    public function applyFont(string $fontName, ?int $fontSize = null, ?string $fontStyle = null, ?string $fontColor = null): Sheet
    {
        $this->_setOptions([], 'font-name', $fontName);
        if ($fontSize) {
            $this->_setOptions([], 'font-size', $fontSize);
        }
        if ($fontStyle) {
            $this->_setOptions([], 'font-style', $fontStyle);
        }
        if ($fontColor) {
            $this->_setOptions([], 'font-color', $fontColor);
        }

        return $this;
    }


    /**
     * @param string $fontName
     *
     * @return $this
     */
    public function applyFontName(string $fontName): Sheet
    {
        $this->_setOptions([], 'font-name', $fontName);

        return $this;
    }

    /**
     * @param float $fontSize
     *
     * @return $this
     */
    public function applyFontSize(float $fontSize): Sheet
    {
        $this->_setOptions([], 'font-size', $fontSize);

        return $this;
    }

    /**
     * @param string $fontStyle
     * @param bool|null $append
     *
     * @return $this
     */
    public function applyFontStyle(string $fontStyle, ?bool $append = false): Sheet
    {
        $this->_setOptions([], 'font-style', $fontStyle);

        return $this;
    }

    public function applyFontStyleBold(?bool $append = false): Sheet
    {
        return $this->applyFontStyle('bold', $append);
    }

    /**
     * @param bool|null $append
     *
     * @return $this
     */
    public function applyFontStyleItalic(?bool $append = false): Sheet
    {
        return $this->applyFontStyle('italic', $append);
    }

    /**
     * @param bool|null $append
     *
     * @return $this
     */
    public function applyFontStyleUnderline(?bool $append = false): Sheet
    {
        return $this->applyFontStyle('underline', $append);
    }

    /**
     * @param bool|null $append
     *
     * @return $this
     */
    public function applyFontStyleStrikethrough(?bool $append = false): Sheet
    {
        return $this->applyFontStyle('strikethrough', $append);
    }

    /**
     * @param string $fontColor
     *
     * @return $this
     */
    public function aaplyFontColor(string $fontColor): Sheet
    {
        $this->_setOptions([], 'font-color', $fontColor);

        return $this;
    }

    /**
     * Alias of 'setFontColor()'
     *
     * @param string $color
     *
     * @return $this
     */
    public function applyColor(string $color): Sheet
    {
        return $this->aaplyFontColor($color);
    }

    /**
     * @param string $color
     *
     * @return $this
     */
    public function applyBackgroundColor(string $color): Sheet
    {
        return $this->_setOptions([], 'background-color', $color);
    }

    /**
     * Alias of 'backgroundColor()'
     *
     * @param string $color
     *
     * @return $this
     */
    public function applyFillColor(string $color): Sheet
    {
        return $this->applyBackgroundColor($color);
    }

    /**
     * @param string $textAlign
     *
     * @return $this
     */
    public function applyTextAlign(string $textAlign): Sheet
    {
        return $this->_setOptions([], 'text-align', $textAlign);
    }

    /**
     * @param string $verticalAlign
     *
     * @return $this
     */
    public function applyVerticalAlign(string $verticalAlign): Sheet
    {
        return $this->_setOptions([], 'vertical-align', $verticalAlign);
    }

    /**
     * @param bool $textWrap
     *
     * @return $this
     */
    public function applyTextWrap(bool $textWrap): Sheet
    {
        return $this->_setOptions([], 'text-wrap', $textWrap);
    }

}

// EOF