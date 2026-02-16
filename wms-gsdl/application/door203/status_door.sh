#!/bin/bash
# 查看门监控服务状态

BASE_DIR="/xp/www/wms-gsdl/application/door203"
PID_FILE="$BASE_DIR/door_server.pid"

echo "📊 门监控服务状态"
echo "=========================================="

# 检查服务是否运行
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if ps -p $PID > /dev/null 2>&1; then
        echo "✅ 服务运行中 (PID: $PID)"
        echo "   监听端口: 667"
        echo "   启动时间: $(ps -p $PID -o lstart | tail -1)"
    else
        echo "❌ PID文件存在但进程不存在"
    fi
else
    echo "❌ 服务未运行"
fi

echo ""

# 检查端口监听
echo "🔍 端口检查:"
if netstat -tlnp 2>/dev/null | grep -q ":667 "; then
    echo "✅ 端口 667 正在监听"
else
    echo "❌ 端口 667 未监听"
fi

echo ""

# 显示当前门状态
echo "🚪 当前门状态:"
if [ -f "$BASE_DIR/door_state.json" ]; then
    python3 -c "
import json, os, sys
try:
    with open('$BASE_DIR/door_state.json', 'r') as f:
        data = json.load(f)
    
    state_icon = '🟢' if data.get('state') == 'open' else '🔴' if data.get('state') == 'closed' else '⚪'
    print(f'{state_icon} 状态: {data.get(\"state_cn\", \"未知\")}')
    print(f'📊 状态码: {data.get(\"state_code\", 0)} (1=开门, 0=关门)')
    print(f'📅 最后事件: {data.get(\"last_event\", \"无\")}')
    print(f'🕒 事件时间: {data.get(\"last_event_time\", \"无\")}')
    print(f'🔢 事件计数: {data.get(\"event_count\", 0)}')
except Exception as e:
    print(f'读取状态失败: {e}')
"
else
    echo "📝 状态文件不存在"
fi

echo ""

# 显示最近日志
echo "📝 最近日志:"
if [ -f "$BASE_DIR/logs/door_status.log" ]; then
    echo "最近门状态变化:"
    tail -3 "$BASE_DIR/logs/door_status.log" 2>/dev/null | while read line; do
        echo "  $line"
    done
fi

if [ -f "$BASE_DIR/logs/console.log" ]; then
    echo ""
    echo "最近控制台日志:"
    tail -5 "$BASE_DIR/logs/console.log" 2>/dev/null | while read line; do
        echo "  $line"
    done
fi

echo ""
echo "🔍 查看详细日志:"
echo "  tail -f $BASE_DIR/logs/console.log        # 控制台日志"
echo "  tail -f $BASE_DIR/logs/door_status.log    # 门状态日志"
echo "  tail -f $BASE_DIR/logs/python_service.log # Python服务日志"
echo "  tail -f $BASE_DIR/door_json.log           # JSON状态日志"
echo "=========================================="