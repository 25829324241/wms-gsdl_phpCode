<?php
namespace app\common;

class ZplPrinter
{
    private $host;
    private $port = 9100;
    private $dpi = 203;
    public $labelWidth;
    public $labelHeight;
    private $chineseFont = 'TST24.BF2';
    public $currentY = 20;
    
    public function __construct($host, $width = 800, $height = 600, $port = 9100) {
        $this->host = $host;
        $this->port = $port;
        $this->setLabelSize($width, $height);
    }

    public function setLabelSize($width, $height) {
        $this->labelWidth = (int)$width;
        $this->labelHeight = (int)$height + 24;
        return $this;
    }

    /**
     * 修改printLabel方法，解决缓存问题和白纸问题
     */
    public function printLabel($content) {
        try {
            // 1. 建立连接
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);
            if (!$socket) {
                throw new \Exception("连接打印机失败: $errstr ($errno)");
            }
            
            // 2. 设置流超时
            stream_set_timeout($socket, 5);
            
            // 3. 发送打印前初始化（ESC @）- 清除缓存但不会打印空白标签
            fwrite($socket, "\x1B\x40");
            usleep(50000); // 等待50ms
            
            // 4. 发送清除图像缓冲区命令（不包装在^XA^XZ中，避免出白纸）
            fwrite($socket, "^ID*");
            usleep(30000); // 等待30ms
            
            // 5. 确保内容是完整的ZPL命令
            if (strpos($content, '^XA') === false) {
                // 如果不是完整的ZPL，包装它
                $content = "^XA\n" . $content . "\n^XZ";
            }
            
            // 6. 发送实际的打印内容
            fwrite($socket, $content);
            
            // 7. 发送换行确保命令执行完成
            fwrite($socket, "\n");
            fflush($socket); // 强制刷新输出缓冲区
            
            // 8. 根据标签高度计算等待时间（避免过早关闭连接）
            $waitTime = max(100000, $this->labelHeight * 100); // 至少100ms，每点高度增加0.1ms
            usleep(min($waitTime, 300000)); // 最多等待300ms
            
            fclose($socket);
            
            return true;
            
        } catch (\Exception $e) {
            // 记录错误但不中断流程
            error_log("打印机错误: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 安全的校准方法（避免多次走纸）
     */
    public function calibrate()
    {
        try {
            // 只发送一次校准命令
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
            if ($socket) {
                // 先发送初始化
                fwrite($socket, "\x1B\x40");
                usleep(50000);
                
                // 发送校准命令
                $calibrateCmd = "^XA^MNW^JMA^XZ";
                fwrite($socket, $calibrateCmd);
                fwrite($socket, "\n");
                fflush($socket);
                
                // 校准需要更长时间
                usleep(300000); // 300ms
                
                fclose($socket);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // 重置打印位置到顶部
    public function resetPosition() {
        $this->currentY = 20;
    }

    // 添加垂直间距
    public function addSpacing($spacing = 30) {
        $this->currentY += $spacing;
        return $this->currentY;
    }

    public function generateText($text, $options = []) {
        $size = $options['size'] ?? 24;
        $x = $options['x'] ?? 'C'; // C居中, L左对齐, R右对齐

        $fontCmd = (preg_match('/[\x{4e00}-\x{9fa5}]/u', $text))
            ? "^CWX,$this->chineseFont^CI26"
            : "^CI0";

        // 居中计算
        if ($x === 'C') {
            $textWidth = strlen($text) * $size * 0.4; // 估算文本宽度
            $x = ($this->labelWidth - $textWidth) / 2;
        } elseif ($x === 'L') {
            $x = 20; // 左对齐留20dot边距
        } elseif ($x === 'R') {
            $textWidth = strlen($text) * $size * 0.6;
            $x = $this->labelWidth - $textWidth - 40; // 右对齐留边距
        }

        $element = "$fontCmd^FO{$x},{$this->currentY}^A0N,$size,$size^FD$text^FS";
  
        $this->currentY += $size * 1.5; // 文本高度估算
        return $element;
    }

    public function generateBarcode($data, $options = []) {
        $type = $options['type'] ?? 'CODE128';
        $height = $options['height'] ?? 60;
        $barcodeWidth = strlen($data) * 30; // 估算宽度

        $x = ($this->labelWidth - $barcodeWidth) / 2; // 居中
        $element = "^FO$x,{$this->currentY}^BY2^BCN,$height,Y,N,N^FD$data^FS";
        $this->currentY += $height + 10; // 条码高度+间距
        return $element;
    }

    public function generateQRCode($data, $options = []) {
        $size = $options['size'] ?? 6;
        // 30=>25
        $qrHeight = $size * 25;
        $x = ($this->labelWidth - $qrHeight) / 2 - 20; // 居中

        $element = "^FO$x,{$this->currentY}^BQN,2,$size^FDMA,$data^FS";
        // 20=》10
        $this->currentY += $qrHeight + 10; // 二维码高度+间距
        return $element;
    }

    /**
     * 构建ZPL命令（增强版，确保清除之前的缓存）
     */
    public function buildZplCommand($elements) {
        // 在ZPL命令开始前添加初始化（但不作为单独的标签）
        $zpl = "";
        
        // 标准ZPL开始
        $zpl .= "^XA";
        
        // 打印机设置
        $zpl .= "^PW{$this->labelWidth}";
        $zpl .= "^LL{$this->labelHeight}";
        $zpl .= "^CI28";
        
        // 添加清除命令到当前标签中（这样不会单独出白纸）
        $zpl .= "^ID*"; // 清除图像缓冲区
        
        // 添加内容
        if (is_array($elements)) {
            $zpl .= "\n" . implode("\n", $elements);
        } else {
            $zpl .= "\n" . $elements;
        }
        
        // 结束命令
        $zpl .= "\n^XZ";
        
        return $zpl;
    }
    
    /**
     * 简单的清除缓存方法（不打印空白标签）
     */
    public function clearCache() {
        try {
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 3);
            if ($socket) {
                // 只发送ESC/POS命令，不会打印标签
                fwrite($socket, "\x1B\x40"); // 初始化
                fwrite($socket, "^ID*");     // 清除图像
                fwrite($socket, "\n");
                fclose($socket);
                usleep(100000); // 等待100ms
                return true;
            }
        } catch (\Exception $e) {
            // 静默处理
        }
        return false;
    }
}