#!/bin/bash
# 停止门监控服务

BASE_DIR="/xp/www/wms-gsdl/application/door203"
PID_FILE="$BASE_DIR/door_server.pid"

echo "🛑 停止门监控服务"
echo "=========================================="

if [ ! -f "$PID_FILE" ]; then
    echo "ℹ 未找到PID文件，服务可能未运行"
    # 尝试查找进程
    PID=$(ps aux | grep "python3.*door_server.py" | grep -v grep | awk '{print $2}')
    if [ -n "$PID" ]; then
        echo "⚠ 发现未管理的进程: $PID，正在停止..."
        kill $PID
        sleep 1
        if ps -p $PID > /dev/null 2>&1; then
            kill -9 $PID
        fi
        echo "✅ 进程已停止"
    fi
    exit 0
fi

PID=$(cat "$PID_FILE")

if ps -p $PID > /dev/null 2>&1; then
    echo "正在停止进程 $PID ..."
    kill $PID
    
    # 等待进程结束
    for i in {1..10}; do
        if ps -p $PID > /dev/null 2>&1; then
            sleep 1
            echo -n "."
        else
            break
        fi
    done
    
    echo ""
    
    if ps -p $PID > /dev/null 2>&1; then
        echo "⚠ 进程未正常结束，强制停止..."
        kill -9 $PID
        sleep 1
    fi
    
    rm -f "$PID_FILE"
    echo "✅ 服务已停止"
else
    echo "ℹ 进程 $PID 未运行，清理PID文件"
    rm -f "$PID_FILE"
fi

# 清理可能残留的进程
REMAINING_PIDS=$(ps aux | grep "python3.*door_server.py" | grep -v grep | awk '{print $2}')
if [ -n "$REMAINING_PIDS" ]; then
    echo "⚠ 清理残留进程: $REMAINING_PIDS"
    kill -9 $REMAINING_PIDS 2>/dev/null
fi

echo "=========================================="