#!/bin/bash
# 重启门监控服务

BASE_DIR="/xp/www/wms-gsdl/application/door203"

echo "🔄 重启门监控服务"
echo "=========================================="

# 先停止服务
if [ -f "$BASE_DIR/stop_door.sh" ]; then
    "$BASE_DIR/stop_door.sh"
    sleep 2
fi

# 再启动服务
if [ -f "$BASE_DIR/start_door.sh" ]; then
    "$BASE_DIR/start_door.sh"
else
    echo "❌ 启动脚本不存在: $BASE_DIR/start_door.sh"
    exit 1
fi

echo "=========================================="