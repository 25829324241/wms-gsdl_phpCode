#!/usr/bin/env python3
# 门监控服务 - 精简后台版

import socket
import struct
import json
import os
import sys
import threading
import xml.etree.ElementTree as ET
from datetime import datetime
import requests
import time

# ==================== 配置 ====================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
STATE_FILE = os.path.join(BASE_DIR, "door_state.json")
JSON_LOG_FILE = os.path.join(BASE_DIR, "door_json.log")
LOG_DIR = os.path.join(BASE_DIR, "logs")

# 日志文件
PYTHON_SERVICE_LOG = os.path.join(LOG_DIR, "python_service.log")
DOOR_STATUS_LOG = os.path.join(LOG_DIR, "door_status.log")
CONSOLE_LOG = os.path.join(LOG_DIR, "console.log")

# TCP服务器配置
TCP_HOST = "0.0.0.0"
TCP_PORT = 667

# WMS接口地址
WMS_API_URL = "http://192.168.31.85:666/api/v1/report/door_report"

# 状态反转开关
REVERSE_LOGIC = False

# 门状态
door_state = {
    "state": "unknown",
    "state_cn": "未知",
    "state_code": 0,
    "last_event": "",
    "last_event_time": "",
    "event_count": 0,
    "record_time": "",
    "message": "初始化",
    "status": "initial"
}

# ==================== 日志函数 ====================
def ensure_log_dir():
    """确保日志目录存在"""
    if not os.path.exists(LOG_DIR):
        os.makedirs(LOG_DIR, 0o755, True)

def log_console(message):
    """记录控制台日志"""
    ensure_log_dir()
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_line = f"[{timestamp}] {message}\n"
    
    try:
        with open(CONSOLE_LOG, 'a', encoding='utf-8') as f:
            f.write(log_line)
    except:
        pass

def log_python_service(message):
    """只记录重要的Python服务日志"""
    ensure_log_dir()
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
    log_line = f"[{timestamp}] {message}\n"
    
    try:
        with open(PYTHON_SERVICE_LOG, 'a', encoding='utf-8') as f:
            f.write(log_line)
    except:
        pass

def log_door_status(message):
    """只记录门状态变化日志"""
    ensure_log_dir()
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
    log_line = f"[{timestamp}] {message}\n"
    
    try:
        with open(DOOR_STATUS_LOG, 'a', encoding='utf-8') as f:
            f.write(log_line)
    except:
        pass

def log_json_state():
    """以JSON格式记录当前状态"""
    try:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        door_state["record_time"] = timestamp
        
        with open(JSON_LOG_FILE, 'a', encoding='utf-8') as f:
            json.dump(door_state, f, ensure_ascii=False)
            f.write('\n')
    except:
        pass

# ==================== 转发给WMS接口 ====================
def forward_to_wms(event_type):
    """将状态转发给WMS接口"""
    try:
        # 准备转发数据
        params = {
            'door_id': '203',
            'door_name': '人工门',
            'state': door_state["state"],
            'state_cn': door_state["state_cn"],
            'state_code': door_state["state_code"],
            'last_event': door_state["last_event"],
            'last_event_time': door_state["last_event_time"],
            'event_count': door_state["event_count"],
            'callback_time': datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3],
            'source': 'python_server',
            'event_type': event_type
        }
        
        log_python_service(f"📤 转发数据: {params}")
        
        # 发送GET请求到WMS接口
        response = requests.get(WMS_API_URL, params=params, timeout=3)
        
        if response.status_code == 200:
            log_python_service(f"✅ 转发成功: {response.text[:100]}")
            return True, response.text
        else:
            log_python_service(f"❌ 转发失败: {response.status_code}")
            return False, f"HTTP {response.status_code}"
            
    except Exception as e:
        log_python_service(f"❌ 转发异常: {str(e)}")
        return False, str(e)

# ==================== 状态管理 ====================
def save_state():
    """保存状态到状态文件"""
    try:
        door_state["record_time"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        with open(STATE_FILE, 'w', encoding='utf-8') as f:
            json.dump(door_state, f, ensure_ascii=False, indent=2)
    except Exception as e:
        log_python_service(f"❌ 保存状态失败: {e}")

def load_state():
    """从文件加载状态"""
    global door_state
    try:
        if os.path.exists(STATE_FILE):
            with open(STATE_FILE, 'r', encoding='utf-8') as f:
                door_state.update(json.load(f))
            log_console(f"✅ 状态已加载: {door_state['state_cn']}")
        else:
            log_console("ℹ 状态文件不存在，使用初始状态")
    except Exception as e:
        log_console(f"❌ 加载状态失败: {e}")

# ==================== 状态更新函数 ====================
def update_door_state(event_type):
    """更新门状态并转发给WMS"""
    current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
    
    # 应用反转逻辑
    if REVERSE_LOGIC:
        if event_type == "open":
            event_type = "close"
        elif event_type == "close":
            event_type = "open"
    
    # 确定状态码
    state_code = 1 if event_type == "open" else 0
    status_text = "开门" if event_type == "open" else "关门"
    
    # 检查状态是否变化
    if door_state["state"] == ("open" if event_type == "open" else "closed"):
        log_python_service(f"⏭ 状态未变化，跳过: {status_text}")
        return door_state
    
    # 更新状态
    door_state.update({
        "state": "open" if event_type == "open" else "closed",
        "state_cn": status_text,
        "state_code": state_code,
        "last_event": "door_open" if event_type == "open" else "door_close",
        "last_event_time": current_time,
        "event_count": door_state.get("event_count", 0) + 1,
        "record_time": current_time,
        "message": f"控制器上报: 门已{status_text}",
        "status": "success"
    })
    
    # 记录门状态变化
    log_door_status(f"门状态变化: {status_text}, 状态码: {state_code}, 事件计数: {door_state['event_count']}")
    log_console(f"🚪 状态变化: {status_text} (事件计数: {door_state['event_count']})")
    
    # 保存状态
    save_state()
    
    # 记录JSON日志
    log_json_state()
    
    # 转发给WMS接口
    forward_success, _ = forward_to_wms(event_type)
    
    if forward_success:
        log_console(f"✅ 转发成功: {status_text}")
    else:
        log_console(f"⚠️  转发失败: {status_text}")
    
    return door_state

# ==================== TCP服务器 ====================
def handle_client(client_socket, address):
    """处理客户端连接"""
    client_ip, _ = address
    
    try:
        data = client_socket.recv(1024)
        if not data:
            return
        
        # 判断是否为海康协议
        is_hik = len(data) >= 28 and data[:4] == b'HKMV'
        
        # 提取XML数据
        xml_str = ""
        if is_hik:
            xml_data = data[28:]
            xml_str = xml_data.decode('utf-8', errors='ignore')
        else:
            xml_str = data.decode('utf-8', errors='ignore')
        
        if xml_str:
            xml_clean = xml_str.strip('\x00\r\n ')
            
            try:
                root = ET.fromstring(xml_clean)
                para_0 = root.findtext('para_0', '')
                para_1 = root.findtext('para_1', '')
                
                # 处理门上报事件
                if para_0 == '/api/v1/report/door_report' or para_0 == '':
                    event_type = para_1.lower()
                    
                    if event_type in ['open', 'close']:
                        log_python_service(f"🎯 收到控制器事件: {event_type} from {client_ip}")
                        
                        # 更新状态
                        update_door_state(event_type)
                        
                        # 发送响应
                        response_xml = '<?xml version="1.0" encoding="UTF-8"?><Message><dev_type>0</dev_type><dev_id>203</dev_id><ret_code>0</ret_code></Message>'
                        
                        if is_hik:
                            response = b'HKMV' + struct.pack('>I', 28 + len(response_xml)) + b'\x00'*20 + response_xml.encode('utf-8')
                        else:
                            response = response_xml.encode('utf-8')
                        
                        client_socket.send(response)
                        
            except Exception:
                pass
    
    except Exception:
        pass
    finally:
        client_socket.close()

def start_server():
    """启动TCP服务器"""
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    
    try:
        server.bind((TCP_HOST, TCP_PORT))
        server.listen(5)
        
        log_console("=" * 60)
        log_console("🚪 Python门监控服务启动")
        log_console(f"👂 监听端口: {TCP_PORT}")
        log_console(f"📤 转发地址: {WMS_API_URL}")
        log_console(f"📁 PID: {os.getpid()}")
        log_console(f"📅 启动时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        log_console("=" * 60)
        log_console("等待控制器上报...")
        log_console("=" * 60)
        
        log_python_service("Python门监控服务启动")
        
        while True:
            client_socket, address = server.accept()
            client_thread = threading.Thread(
                target=handle_client,
                args=(client_socket, address)
            )
            client_thread.daemon = True
            client_thread.start()
            
    except KeyboardInterrupt:
        log_console("正在停止服务器...")
        save_state()
        log_console("状态已保存")
    except Exception as e:
        log_console(f"服务器错误: {e}")
    finally:
        server.close()

# ==================== 主程序 ====================
if __name__ == '__main__':
    # 确保日志目录存在
    ensure_log_dir()
    
    # 检查requests库
    try:
        import requests
    except ImportError:
        log_console("❌ 错误: requests库未安装！")
        log_console("安装命令: pip3 install requests")
        sys.exit(1)
    
    # 守护进程化
    if os.fork() != 0:
        sys.exit(0)
    
    os.setsid()
    os.umask(0)
    
    if os.fork() != 0:
        sys.exit(0)
    
    # 重定向标准输出
    sys.stdout.flush()
    sys.stderr.flush()
    
    # 加载状态
    load_state()
    
    # 启动服务器
    start_server()