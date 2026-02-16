<?php
// namespace printer;
namespace app\common;

class HanYinPrinterOld
{
    protected $config = [
        'ip' => '192.168.1.100',
        'port' => 9100,
        'labelWidth' => 800,
        'labelHeight' => 600,
        'margin' => 50,
        'dpi' => 203
    ];

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    public function setLabelSize($widthMM, $heightMM)
    {
        $this->config['labelWidth'] = $widthMM * ($this->config['dpi']/25.4);
        $this->config['labelHeight'] = $heightMM * ($this->config['dpi']/25.4);
        return $this;
    }

    protected function zplHeader()
    {
        return "^XA\n^LH0,0\n^PW{$this->config['labelWidth']}\n";
    }

    protected function zplFooter()
    {
        return "^XZ\n";
    }

    protected function encodeChinese($text)
    {
        return bin2hex(iconv('UTF-8', 'GB18030//IGNORE', $text));
    }

    public function addText($text, $x, $y, $options = [])
    {
        $defaults = [
            'fontSize' => 30,
            'rotation' => 'N',
            'bold' => false,
            'center' => false
        ];
        $opts = array_merge($defaults, $options);

        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $text)) {
            return $this->addChineseText($text, $x, $y, $opts);
        }

        $fontMap = [30=>'0', 50=>'1', 67=>'2'];
        $font = $fontMap[$opts['fontSize']] ?? '0';
        
        if ($opts['center']) {
            $charWidth = $opts['fontSize'] * 0.6;
            $textWidth = strlen($text) * $charWidth;
            $x = ($this->config['labelWidth'] - $textWidth) / 2;
        }

        $zpl = sprintf("^FO%d,%d^A%s,%d,%d^FD%s^FS\n",
            $x, $y, $font, $opts['fontSize']/10, $opts['fontSize']/10, $text);

        if ($opts['bold']) {
            $zpl .= sprintf("^FO%d,%d^A%s,%d,%d^FD%s^FS\n",
                $x+1, $y+1, $font, $opts['fontSize']/10, $opts['fontSize']/10, $text);
        }

        return $zpl;
    }

    public function addChineseText($text, $x, $y, $options)
    {
        $font = $options['fontSize'] <= 30 ? 'H' : 'G';
        $encoded = $this->encodeChinese($text);
        
        if ($options['center']) {
            $charWidth = $options['fontSize'] * 1.2;
            $textWidth = mb_strlen($text) * $charWidth;
            $x = ($this->config['labelWidth'] - $textWidth) / 2;
        }

        return sprintf("^FO%d,%d^A%cN,%d,%d^FH^FD%s^FS\n",
            $x, $y, $font, $options['fontSize'], $options['fontSize'], $encoded);
    }

    public function addBarcode($data, $x, $y, $options = [])
    {
        $defaults = ['height'=>50, 'width'=>2, 'type'=>'128'];
        $opts = array_merge($defaults, $options);
        
        return sprintf("^FO%d,%d^BY%d^BC%s,%d,Y,N,N^FD%s^FS\n",
            $x, $y, $opts['width'], $opts['type'], $opts['height'], $data);
    }

    public function addQrcode($data, $x, $y, $options = [])
    {
        $defaults = ['size'=>5, 'correction'=>'M'];
        $opts = array_merge($defaults, $options);
        
        return sprintf("^FO%d,%d^BQN,2,%d^FD%s,A%s^FS\n",
            $x, $y, $opts['size'], $opts['correction'], $data);
    }

    public function print($zplCommands)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!socket_connect($socket, $this->config['ip'], $this->config['port'])) {
            throw new \Exception("打印机连接失败");
        }

        $fullZpl = $this->zplHeader() . $zplCommands . $this->zplFooter();
        socket_write($socket, $fullZpl, strlen($fullZpl));
        socket_close($socket);
        return true;
    }
}

/* namespace app\controller;

use extend\HanYinPrinter;

class PrintController
{
    public function printProductLabel()
    {
        try {
            $printer = new HanYinPrinter(['ip'=>'192.168.1.150']);
            $printer->setLabelSize(80, 60); // 80mm×60mm标签

            $zpl = '';
            $zpl .= $printer->addText('产品标签', 0, 30, [
                'fontSize'=>50, 
                'center'=>true
            ]);
            $zpl .= $printer->addText('型号：E3 Plus', 0, 90, [
                'center'=>true
            ]);
            $zpl .= $printer->addChineseText('生产日期：2025年06月', 0, 150, [
                'fontSize'=>30,
                'center'=>true
            ]);
            $zpl .= $printer->addBarcode('SN20250612001', 100, 220);
            $zpl .= $printer->addQrcode('https://example.com/p/20250612001', 400, 200);

            $printer->print($zpl);
            return json(['status'=>1]);
        } catch (\Exception $e) {
            return json(['status'=>0, 'msg'=>$e->getMessage()]);
        }
    }
}
 */
