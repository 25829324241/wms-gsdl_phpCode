<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP测试接口\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo "PHP版本: " . phpversion() . "\n";
echo "请求方法: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "POST数据: " . file_get_contents('php://input') . "\n";
echo "GET参数: " . json_encode($_GET) . "\n";
echo "POST参数: " . json_encode($_POST) . "\n";
