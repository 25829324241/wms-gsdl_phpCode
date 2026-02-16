<?php
// namespace printer;
namespace app\common;

class HanYinPrinter
{
    private $printerName;
    private $labelWidth = 800;  // 默认宽度(点)
    private $labelHeight = 1000; // 默认高度(点)
    private $dpi = 203;         // 打印机DPI
    private $offsetX = 0;       // X轴偏移量

    public function __construct($printerName = null)
    {
        if (!extension_loaded('printer')) {
            throw new \Exception("请启用php_printer扩展");
        }
        $this->printerName = $printerName ?: $this->getDefaultPrinter();
    }

    public function setLabelSize($widthMM, $heightMM = null)
    {
        $this->labelWidth = round($widthMM * ($this->dpi/25.4));
        $this->labelHeight = $heightMM ? round($heightMM * ($this->dpi/25.4)) : $this->labelHeight;
        $this->offsetX = (864 - $this->labelWidth)/2; // 864是打印机最大宽度
        return $this;
    }

    public function printMixedContent($contents)
    {
        $zpl = "^XA\n^PW{$this->labelWidth}\n^LL{$this->labelHeight}\n^CI28\n";
        $currentY = 50;

        foreach ($contents as $item) {
            switch ($item['type']) {
                case 'text':
                    $zpl .= $this->buildText(
                        $item['content'],
                        $currentY,
                        $item['options'] ?? []
                    );
                    $currentY += ($item['options']['fontSize'] ?? 30) + 20;
                    break;
                case 'barcode':
                    $zpl .= $this->buildBarcode(
                        $item['content'],
                        $currentY,
                        $item['height'] ?? 50,
                        $item['options'] ?? []
                    );
                    $currentY += ($item['height'] ?? 50) + 30;
                    break;
                case 'qrcode':
                    $zpl .= $this->buildQrcode(
                        $item['content'],
                        $currentY,
                        $item['size'] ?? 5
                    );
                    $currentY += 150;
                    break;
                case 'line':
                    $zpl .= $this->buildLine(
                        $currentY,
                        $item['width'] ?? $this->labelWidth - 100,
                        $item['thickness'] ?? 2
                    );
                    $currentY += ($item['thickness'] ?? 2) + 20;
                    break;
            }
        }

        $zpl .= "^XZ";
        $this->sendToPrinter($zpl);
    }

    private function buildText($text, $y, $options)
    {
        $opts = array_merge([
            'fontSize' => 30,
            'bold' => false,
            'align' => 'left',
            'font' => '0'
        ], $options);

        $x = $this->offsetX;
        if ($opts['align'] === 'center') {
            $x += ($this->labelWidth - $this->calcTextWidth($text, $opts['fontSize']))/2;
        } elseif ($opts['align'] === 'right') {
            $x = $this->offsetX + $this->labelWidth - $this->calcTextWidth($text, $opts['fontSize']);
        }

        // 中文处理采用Unicode编码
        $content = $this->encodeChinese($text);
        $fontCmd = ($opts['font'] === '0') ? 'A' : 'A@';

        $zpl = sprintf("^FO%d,%d^%s%s,%d,%d^FD%s^FS\n",
            $x, $y, $fontCmd, $opts['font'], $opts['fontSize'], $opts['fontSize'], $content);

        if ($opts['bold']) {
            $zpl .= sprintf("^FO%d,%d^%s%s,%d,%d^FD%s^FS\n",
                $x+1, $y+1, $fontCmd, $opts['font'], $opts['fontSize'], $opts['fontSize'], $content);
        }
        return $zpl;
    }

    private function encodeChinese($text)
    {
        $encoded = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            if (ord($char) > 127) {
                $encoded .= '_' . strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $char)));
            } else {
                $encoded .= $char;
            }
        }
        return $encoded;
    }

    private function calcTextWidth($text, $fontSize)
    {
        $len = mb_strlen($text, 'UTF-8');
        $width = 0;
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $width += (ord($char) > 127) ? $fontSize : $fontSize * 0.6;
        }
        return $width;
    }

    private function buildBarcode($data, $y, $height, $options)
    {
        $opts = array_merge([
            'type' => '128',
            'width' => 2,
            'ratio' => 3.0,
            'text' => true
        ], $options);

        $x = $this->offsetX + ($this->labelWidth - ($opts['width'] * 120))/2;
        return sprintf("^FO%d,%d^BY%d,%.1f,10^B%sN,%d,Y,N^FD%s^FS\n",
            $x, $y, $opts['width'], $opts['ratio'], $opts['type'], $height, $data);
    }

    private function buildQrcode($data, $y, $size)
    {
        $x = $this->offsetX + ($this->labelWidth - ($size * 25))/2;
        return "^FO{$x},{$y}^BQN,2,{$size}^FDMA,{$data}^FS\n";
    }

    private function buildLine($y, $width, $thickness)
    {
        $x = $this->offsetX + ($this->labelWidth - $width)/2;
        return "^FO{$x},{$y}^GB{$width},{$thickness},B^FS\n";
    }

    private function sendToPrinter($zpl)
    {
        $handle = printer_open($this->printerName);
        printer_set_option($handle, PRINTER_MODE, "RAW");
        printer_write($handle, $zpl);
        printer_close($handle);
    }

    private function getDefaultPrinter()
    {
        $printers = printer_list(PRINTER_ENUM_LOCAL);
        return $printers[0]['NAME'] ?? null;
    }
}


/*
namespace app\index\controller;

use extend\HanYinPrinter;

class Print
{
    public function printProductLabel()
    {
        try {
            $printer = new HanYinPrinter('汉印E3plus');
            $printer->setLabelSize(80, 120); // 80mm宽 120mm高

            $contents = [
                [
                    'type' => 'text',
                    'content' => '产品检验合格证',
                    'options' => [
                        'fontSize' => 40,
                        'bold' => true,
                        'align' => 'center',
                        'font' => 'N' // 使用打印机内置字体
                    ]
                ],
                [
                    'type' => 'line',
                    'width' => 600,
                    'thickness' => 3
                ],
                [
                    'type' => 'text',
                    'content' => '产品名称：汉印打印机E3 Plus',
                    'options' => ['align' => 'left']
                ],
                [
                    'type' => 'text',
                    'content' => '型号：E3-2025',
                    'options' => ['align' => 'left']
                ],
                [
                    'type' => 'barcode',
                    'content' => 'SN20250612001',
                    'height' => 60,
                    'options' => [
                        'type' => '128',
                        'width' => 2,
                        'ratio' => 3.0
                    ]
                ],
                [
                    'type' => 'qrcode',
                    'content' => 'https://trace.com/p/20250612001',
                    'size' => 6
                ],
                [
                    'type' => 'text',
                    'content' => '生产日期：2025-06-12',
                    'options' => ['align' => 'right']
                ]
            ];

            $printer->printMixedContent($contents);
            return json(['status' => 1, 'msg' => '打印成功']);
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }
}

 */
