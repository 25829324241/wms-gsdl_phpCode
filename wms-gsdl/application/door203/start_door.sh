#!/bin/bash
# 启动门监控服务

BASE_DIR="/xp/www/wms-gsdl/application/door203"
SCRIPT_NAME="door_server.py"
PID_FILE="$BASE_DIR/door_server.pid"

echo "🚪 启动门监控服务"
echo "=========================================="

# 检查是否已在运行
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if ps -p $PID > /dev/null 2>&1; then
        echo "⚠ 服务已在运行 (PID: $PID)"
        echo "停止命令: ./stop_door.sh"
        exit 1
    else
        echo "⚠ 发现旧的PID文件，清理..."
        rm -f "$PID_FILE"
    fi
fi

# 检查端口占用
if netstat -tlnp 2>/dev/null | grep -q ":667 "; then
    echo "❌ 端口 667 已被占用！"
    netstat -tlnp | grep ":667 "
    exit 1
fi

# 检查Python是否安装
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 未安装！"
    exit 1
fi

# 检查requests库
if ! python3 -c "import requests" 2>/dev/null; then
    echo "⚠ Python requests库未安装，正在安装..."
    pip3 install requests
    if [ $? -ne 0 ]; then
        echo "❌ 安装requests库失败！"
        exit 1
    fi
fi

# 进入工作目录
cd "$BASE_DIR"

# 创建logs目录
mkdir -p logs

# 启动服务
echo "▶ 启动服务..."
nohup python3 "$SCRIPT_NAME" > /dev/null 2>&1 &
SERVER_PID=$!

# 等待启动
sleep 2

# 检查是否启动成功
if ps -p $SERVER_PID > /dev/null 2>&1; then
    # 保存PID
    echo $SERVER_PID > "$PID_FILE"
    
    echo ""
    echo "✅ 服务启动成功！"
    echo "📊 PID: $SERVER_PID"
    echo "👂 监听端口: 667"
    echo "📤 转发地址: http://192.168.31.85:666/api/v1/report/door_report"
    echo ""
    echo "📋 管理命令:"
    echo "1. 停止服务: ./stop_door.sh"
    echo "2. 重启服务: ./restart_door.sh"
    echo "3. 查看状态: ./status_door.sh"
    echo ""
    echo "📁 日志文件:"
    echo "  $BASE_DIR/logs/console.log"
    echo "  $BASE_DIR/logs/python_service.log"
    echo "  $BASE_DIR/logs/door_status.log"
    echo "  $BASE_DIR/door_json.log"
    echo "=========================================="
else
    echo "❌ 服务启动失败！"
    echo "查看错误: tail -f $BASE_DIR/logs/console.log"
    exit 1
fi