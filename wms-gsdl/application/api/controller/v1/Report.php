<?php
namespace app\api\controller\v1;
use think\Config;
use think\Db;
use think\Exception;

use app\common\model\IotTask as IotTaskModel; //IOT任务
use app\common\model\WarehouseLocation as WarehouseLocationModel; //库位
use app\common\model\WarehouseContainer as WarehouseContainerModel;//容器
use app\common\model\WorkbenchBit as WorkbenchBitModel; //工作台工作位
use app\common\model\Robotarm  as RobotarmModel; //机械臂任务
use app\api\controller\v1\Hik as HikController; //海康api控制器
use app\common\model\WarehouseShelves as WarehouseShelvesModel; //货架

class Report extends Base
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    public function door_report()
    {
        // 设置日志目录
        $log_dir = '/xp/www/wms-gsdl/application/log/';
        $log_file = $log_dir . 'door_report.log';
        $debug_file = $log_dir . 'door_debug.log';
        
        // 创建日志目录
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        // 获取参数
        $door_id = $this->request->param('door_id', '203');
        $door_name = $this->request->param('door_name', '人工门');
        $state = $this->request->param('state', 'unknown');
        $state_cn = $this->request->param('state_cn', '未知');
        $last_event = $this->request->param('last_event', '');
        $last_event_time = $this->request->param('last_event_time', date('Y-m-d H:i:s.v'));
        $event_count = $this->request->param('event_count/d', 0);
        $callback_time = $this->request->param('callback_time', date('Y-m-d H:i:s.v'));
        $source = $this->request->param('source', 'unknown');
        $event_type = $this->request->param('event_type', '');
        
        // 检查是否来自Python服务器的回调
        $is_python_callback = ($source === 'python_server');
        
        // 生成状态代码
        $state_code = 0;
        if ($state === 'open' || $state_cn === '开门') {
            $state_code = 1;
        } elseif ($state === 'closed' || $state_cn === '关门') {
            $state_code = 0;
        }
        // 调用hik
        $hik = new HikController();
        
        // 当前时间
        $current_time = date('Y-m-d H:i:s.v');
        
        // 只记录来自Python服务器的调用（状态更新）
        if ($is_python_callback) {
            // 相关hik暂停
            if ($state === 'open' || $state_cn === '开门') {
                 $hik->stop_area(1);
                  $hik->stop_robot_arm(1);
            } elseif ($state === 'closed' || $state_cn === '关门') {
                 $hik->stop_area(0);
                 $hik->stop_robot_arm(0);
            }
           
            // 记录到door_report.log（状态更新日志）
            $log_content = sprintf(
                "[%s] [Python调用door_report接口] 来源: %s, 门ID: %s(%s), 当前状态: %s(%s, 代码: %d), " .
                "最后事件: %s, 事件时间: %s, 事件计数: %d, 回调时间: %s",
                $current_time,
                $source,
                $door_id,
                $door_name,
                $state_cn,
                $state,
                $state_code,
                $last_event,
                $last_event_time,
                $event_count,
                $callback_time
            );
            
            file_put_contents($log_file, $log_content . PHP_EOL, FILE_APPEND);
            
            // 记录到door_debug.log（调试日志，只记录Python调用）
            $debug_info = [
                'timestamp' => $current_time,
                'method' => $this->request->method(),
                'ip' => $this->request->ip(),
                'source' => $source,
                'door_id' => $door_id,
                'door_name' => $door_name,
                'state' => $state,
                'state_cn' => $state_cn,
                'state_code' => $state_code,
                'last_event' => $last_event,
                'last_event_time' => $last_event_time,
                'event_count' => $event_count,
                'callback_time' => $callback_time,
                'event_type' => $event_type
            ];
            
            file_put_contents($debug_file, json_encode($debug_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
            
            // 输出响应给Python服务器
            $json_response = [
                'state' => $state,
                'state_cn' => $state_cn,
                'state_code' => $state_code,
                'last_event' => $last_event,
                'last_event_time' => $last_event_time,
                'event_count' => $event_count,
                'callback_time' => $callback_time,
                'record_time' => $current_time,
                'message' => 'Python调用door_report接口成功',
                'status' => 'success'
            ];
            
            return json($json_response);
        } else {
            // 直接访问，只返回状态，不记录日志（避免日志无限增长）
            $json_response = [
                'state' => $state,
                'state_cn' => $state_cn,
                'state_code' => $state_code,
                'last_event' => $last_event,
                'last_event_time' => $last_event_time,
                'event_count' => $event_count,
                'record_time' => $current_time,
                'message' => 'door_report接口调用成功',
                'status' => 'success'
            ];
            
            return json($json_response);
        }
    }

    public function ctu_report(){
        $data = input('param.');
        $task_code = $data['robotTaskCode'];
        $method = $data['extra']['values']['method'];
        if($method == 'end'){
            $msg = $this->ctu_end($task_code);
        }
        return json_encode([
            "code" => 'SUCCESS',
            "message" => "成功",
            "data" => ["robotTaskCode"=>'abc**13']
        ]);

    }
    public function agv_report(){
        
        $data = input('param.');
        $task_code = $data['robotTaskCode'];
        $method = $data['extra']['values']['method'];
        if($method == 'end'){
            $msg = $this->agv_end($task_code);
        }
        return json_encode([
            "code" => 'SUCCESS',
            "message" => "成功",
            "data" => ["robotTaskCode"=>'abc**13']
        ]);
    }

    public function arm_report(){
        write_log(var_export('机械臂任务start：',true),'robotarm_log');
        $data = input('param.');
        $msg = $this->arm_end();
        write_log(var_export('机械臂任务进入：'.$msg,true),'robotarm_log');
        return json_encode([
            "code" => 'SUCCESS',
            "message" => "成功",
            "data" => ["robotTaskCode"=>'abc**13']
        ]);
    }

    public function ctu_end($task_code){
        $task = IotTaskModel::where(['task_code'=>$task_code])->find();
        $to = WarehouseLocationModel::where(['real_location'=>$task['to_location_code']])->find();
        if(strpos($task_code,'task_tote_outbound') !== false){
            // 解绑容器
            $api = new HikController();
            $res = $api->bind_container($task['container_code'],$task['to_location_code'],1,'UNBIND');
        }
        
        // 修改容器，库位，任务状态
        Db::startTrans();
        try{
            // 任务
            IotTaskModel::update([
                'status'=>3, //任务状态:1=创建,2=执行中,3=执行成功,4=执行失败
                'up_time'=>time(),
            ],[
                'task_code'=>$task_code,
            ]);
            if(strpos($task_code,'task_tote_outbound') === false){
                // 容器
                WarehouseContainerModel::update([
                    'wh_l_id'=> $to['id'], //绑定库位id
                    'wh_l_real_location'=> $task['to_location_code'],
                    'wh_l_numbering' => $task['to_location_code'],
                    'wh_a_id' => $to['wh_a_id'], //库区id
                    'up_time'=>time(),
                    'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                ],[
                    'numbering'=>$task['container_code'],
                ]);
            }else{
                WarehouseContainerModel::update([
                    'wh_l_id' => 0,
                    'wh_l_real_location'=>0,
                    'wh_l_numbering'=>0,
                    'wh_l_roadway'=>0,
                    'wh_s_id' => 0,
                    'wh_a_id' => 0,
                    'up_time'=>time(),
                    'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                ],[
                    'numbering'=>$task['container_code'],
                ]);
            }
            // 库位
            WarehouseLocationModel::update([
                'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                'up_time'=>time(),
            ],[
                'real_location'=>$task['to_location_code'],
            ]);
            
            
            Db::commit();
        }catch (Exception $e) {
            Db::rollback();
            $this->error('任务回调 容器库位 出库状态 更新失败');
        }

        if($task['type'] == 2){
            // 看还有没有任务
            $content = unserialize($task['content']);
            if(isset($content['sort'],$content['count']) && $content['count'] > $content['sort']){
                $find = IotTaskModel::where('task_code','like','%outbound_ctu%')->where('status','<',3)->where('id','>',$task['id'])->order('id asc')->find();
                $find_content = unserialize($find['content']);
                $hik = new HikController();
                $ret = $hik->send_ctu_task('2',$find['task_code'],$find['container_code'],$find['to_location_code']);
                write_log(var_export('触发ctu新任务:'.$find_content['count'].'/'.$find_content['sort'],true),'ctu_task_');
            }
            
        }

    }

    // 检测两车是否到达点位,创建机械臂任务
    public function agv_end($task_code){
        $hik = new HikController();
        // 旋转任务
        if(strpos($task_code,'rotate_') !== false){
            write_log(var_export('start触发旋转回调',true),'agv_routate_');
            // 旋转任务结束
            $task = Db::name('agv_task')->where(['task_code'=>$task_code])->find();
            Db::name('agv_task')->where('id',$task['id'])->update([
                'status'=>3, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消
                'update_time'=>time(),
            ]);
            $task = Db::name('agv_task')->where(['task_code'=>$task_code])->find();
            // 修改货架位置
            if($task['wh_s_id'] == 10){
                $shelves_ten = WarehouseShelvesModel::where('id',$task['wh_s_id'])->find();
                if(empty($shelves_ten['rotate']) || $shelves_ten['rotate'] > 0){
                    $change_ten_routate = -90;
                }else{
                    $change_ten_routate = 90;
                }
                WarehouseShelvesModel::update([
                    'rotate'=> $change_ten_routate,
                ],[
                    'id'=>$task['wh_s_id'],
                ]);
            }
            // 找是不是有另外一个要转的
            $between = $task['robotarm_id'] - $task['sort'];
            // 找另外一个车转了没有
            $task2 = Db::name('agv_task')->where('robotarm_id','>',$between)
            ->where('wh_s_id','<>',$task['wh_s_id'])
            ->where('task_code','like','%rotate%')
            ->where('robotarm_id','<=',$task['robotarm_id'])
            ->where('status','<>',3)
            ->find();
            if($task2){
                write_log(var_export('另一个车还没转完'.$task2['wh_s_id'],true),'agv_routate_');
                return false;
            }else{
                write_log(var_export('只有一个车要转',true),'agv_routate_');
            }
            write_log(var_export('agv旋转触发机械臂了',true),'agv_routate_');
            // 同货架旋转完了，同一个iot_id继续
            $this->arm_task();
            
            return true;
        }
        // 小车回家
        if(strpos($task_code,'move_') !== false){
            $task = Db::name('agv_task')->where(['task_code'=>$task_code])->find();
            Db::name('agv_task')->where('id',$task['id'])->update([
                'status'=>3, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消
                'update_time'=>time(),
            ]);
            write_log(var_export('move回调触发',true),'agv_move_');
            // 看是不是还要拉4层的
            $new = Db::name('robotarm')->where('status','in',[1,6])->order('id asc')->find();
            if(empty($new)){
                write_log(var_export('没任务了',true),'agv_move_');
                return false;
            }
            $find_iot = IotTaskModel::where(['id'=>$new['iot_id']])->find();
            if($find_iot['status'] != 3){
                write_log(var_export('agv拉新的货架：'.$new['iot_id'],true),'agv_move_');
                if(strpos($find_iot['name'],'outbound') === false){
                    // 是入库
                    $location_all = WarehouseLocationModel::where(['numbering'=>$new['to_location_code']])->find();
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$location_all['shelves_id'],'type'=>2])->find();
                    write_log(var_export('小车拉新的货架为---'.$shelf_number_arr['numbering'],true),'agv_move_');
                }else{
                    // 出库
                    $go_back = WarehouseLocationModel::where(['numbering'=>$new['from_location_code']])->find();
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$go_back['shelves_id'],'type'=>2])->find();
                    write_log(var_export('小车拉新的货架为---'.$shelf_number_arr['numbering'],true),'agv_move_');
                }
                // 拉新的货架
                $ret = $hik->agv_shelf($find_iot['task_code'],$shelf_number_arr['numbering']);
                write_log(var_export('小车拉新的货架：'.json_encode($ret),true),'agv_move_');
                return true;
            }
            
            return true;
        }
        
        // 判断是不是agv移动
        $iot = IotTaskModel::where(['task_code'=>$task_code])->find();
        if(empty($iot)){
            return ['code'=>1,'msg'=>'不存在此任务'];
        } 
        if($iot['status'] != 3){
            IotTaskModel::update([
                'status'=>3, //任务状态:1=创建,2=执行中,3=执行成功,4=执行失败
                'up_time'=>time(),
            ],[
                'task_code'=>$task_code,
            ]);
            $iot = IotTaskModel::where(['task_code'=>$task_code])->find();
        }
        if(strpos($task_code,'shelf') !== false){
            write_log(var_export('start触发4层货架',true),'agv_task_');
            // 找3层货架
            $iot_task = IotTaskModel::where([
                'task_code'=>['<>',$task_code],
                'from_location_code'=>$iot['from_location_code'],
                'to_location_code'=>$iot['to_location_code'],
            ])->where('id',$iot['id']-1)->where('type',$iot['type'])->order('id desc')->find();
            
            
            // 没找到就看上面一个是不是也是4层货架的
            if(!$iot_task){
                write_log(var_export('start找4层货架',true),'agv_task_');
                $find_top = IotTaskModel::where('id',$iot['id']-1)->find();
                write_log(var_export('start找4层货架---'.$find_top['id'],true),'agv_task_');
                if(strpos($find_top['task_code'],'shelf') !== false){
                    // 是4层的
                    $iot_task = $find_top;
                }else{
                    return false;
                }
            }else{
                write_log(var_export('start找到3层货架：'.json_encode($iot_task['id']),true),'agv_task_');
            }

        }else{
            write_log(var_export('start触发3层货架',true),'arm_task_');
            // 找4层货架
            $iot_task = IotTaskModel::where('id','>',$iot['id'])->where('type',$iot['type'])->order('id asc')->find();
            if(strlen($iot_task['task_code']) <= strlen($iot['task_code'])){
                // task_code的长度3层比4层小
                return false;
            }
        }
        if($iot_task['status']==3 && $iot['status'] == 3){
            write_log(var_export('start触发机械臂了',true),'agv_task_');
            $this->arm_task();
        }

    }

    public function arm_end(){
        write_log(var_export('start触发机械臂回调了',true),'arm_task_');
        $hik = new HikController();

        $arm = RobotarmModel::where('status',2)->order('id asc')->find();
        if($arm){
            /** 
             * 正常任务
             * 更新状态->找点位->容器/库位更新
            */
            $iot = Db::name('iot_task')->where(['id'=>$arm['iot_id']])->find();
            write_log(var_export('机械臂任务'.$arm['id'],true),'arm_task_');
            RobotarmModel::update([
                'status'=>3, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                'usetime'=>time(),
            ],[     
                'id'=>$arm['id'],
            ]);
            $arm = RobotarmModel::where('id',$arm['id'])->find();
            write_log(var_export('机械臂任务更新'.$arm['id'],true),'arm_task_');

            $hik->send_robot_arm(0,0);
            write_log(var_export('机械臂回0',true),'arm_task_');

            if($iot['type'] == 1){
                write_log(var_export('触发入库'.$arm['to_location_code'],true),'arm_task_');
                // 没找到，是入库
                // 库位数，目标库位
                $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$location_all['shelves_id'],'type'=>2])->find();
                write_log(var_export('小车回家位置？'.$shelf_number_arr['numbering'],true),'arm_task_');
            }else{
                write_log(var_export('触发出库----'.$arm['to_location_code'].'-------'.$arm['from_location_code'],true),'arm_task_');
                // 出库
                // 库位数，目标站位
                $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                $go_back = WarehouseLocationModel::where(['numbering'=>$arm['from_location_code']])->find();
                $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$go_back['shelves_id'],'type'=>2])->find();
                write_log(var_export('小车回家位置？'.$shelf_number_arr['numbering'],true),'arm_task_');
            }
            
            // 更新库位,能被2整除
            if($arm['sort']%2==0){
                // 更新库位
                
                // 容器解绑与重新绑定
                $unbind = $hik->bind_container($arm['container_code'],$arm['from_location_code'],1,'UNBIND');
                if($unbind['code'] = 'SUCCESS'){
                    write_log(var_export('【'.$arm['container_code'].'】料箱解绑【'.$arm['from_location_code'].'】成功:'.$unbind['message'],true),'robotarm_task');
                }else{
                    write_log(var_export('【'.$arm['container_code'].'】料箱解绑【'.$arm['from_location_code'].'】失败:'.$unbind['message'],true),'robotarm_task');
                }
                $bind = $hik->bind_container($arm['container_code'],$arm['to_location_code'],1,'BIND');
                if($bind['code'] = 'SUCCESS'){
                    write_log(var_export('【'.$arm['container_code'].'】料箱绑定【'.$arm['to_location_code'].'】成功:'.$bind['message'],true),'robotarm_task');
                }else{
                    write_log(var_export('【'.$arm['container_code'].'】料箱绑定【'.$arm['to_location_code'].'】失败:'.$bind['message'],true),'robotarm_task');
                }
                WarehouseContainerModel::update([
                    'wh_l_id'=> $location_all['id'], //绑定库位id
                    'wh_l_real_location'=> $location_all['real_location'],
                    'wh_l_numbering' => $location_all['real_location'],
                    'wh_a_id' => $location_all['wh_a_id'], //库区id
                    'wh_s_id' => $location_all['shelves_id'],
                    'wh_l_roadway' => $location_all['roadway'],
                    'up_time'=>time(),
                    'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                ],[
                    'numbering'=>$arm['container_code'],
                ]);
                WarehouseLocationModel::update([
                    'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                    'up_time'=>time(),
                ],[
                    'real_location'=>$location_all['real_location'],
                ]);
            }



        }else{
            /** 
             * 回复的任务 ,用id做为标识，因为一个iot_id可能会有两次转
             * 找点位
            */
            $arm_reply = Db::name('robotarm')->where('sort',0)->where('status',5)->order('id desc')->find();
            $arm = Db::name('robotarm')->where('id',$arm_reply['iot_id'])->find();
            $hik->send_robot_arm(0,0);
            write_log(var_export('机械臂回0',true),'arm_task_');
            $iot = Db::name('iot_task')->where(['id'=>$arm['iot_id']])->find();
            if($iot['type'] == 1){
                write_log(var_export('触发入库'.$arm['to_location_code'],true),'arm_task_');
                // 没找到，是入库
                // 库位数，目标库位
                $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$location_all['shelves_id'],'type'=>2])->find();
                write_log(var_export('小车回家位置？'.$shelf_number_arr['numbering'],true),'arm_task_');
            }else{
                write_log(var_export('触发出库----'.$arm['to_location_code'].'-------'.$arm['from_location_code'],true),'arm_task_');
                // 出库
                // 库位数，目标站位
                $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                $go_back = WarehouseLocationModel::where(['numbering'=>$arm['from_location_code']])->find();
                $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$go_back['shelves_id'],'type'=>2])->find();
                write_log(var_export('小车回家位置？'.$shelf_number_arr['numbering'],true),'arm_task_');
            }
        }
        
        

        try {

        // 找全部小车的是不是没任务了
        $find_arm = Db::name('robotarm')->where('status','in',[1,6])->where('iot_id',$arm['iot_id'])->find();
        $find_end = Db::name('robotarm')->where('status','in',[1,6])->find();
        
        if(empty($find_arm) && empty($find_end)){
            write_log(var_export('机械臂任务是不是空的？'.'-----机械臂没用任务了',true),'arm_task_');
            $reply = $this->reply_arm($arm['id']);
            if(!$reply){
                write_log(var_export('机械臂没有回复',true),'arm_task_');
                return false;
            }
            // 3层回去,入库不回去
            if($iot['type'] == 2){
                $task_code = 'move_agv_'.time().rand(1000,9999);
                $data = [
                    'task_code' => $task_code,
                    'robotarm_id' => $arm['id'],
                    'iot_id' => $arm['iot_id'],
                    'wh_s_id' => 10,
                    'sort' => $arm['sort'],
                    'from_depth' =>0,
                    'to_depth' =>0,
                    'status' => 1,
                    'add_time' => time(),
                ];
                Db::name('agv_task')->insert($data);
                $ret = $hik->agv_back($task_code);
                write_log(var_export('3层move小车回家：'.json_encode($ret),true),'arm_task_');

            }
            


            // 4层回去
            $task_code = 'move_'.time().rand(1000,9999);
            $data = [
                'task_code' => $task_code,
                'robotarm_id' => $arm['id'],
                'iot_id' => $arm['iot_id'],
                'wh_s_id' => $shelf_number_arr['shelves_id'],
                'sort' => $arm['sort'],
                'from_depth' =>0,
                'to_depth' =>0,
                'status' => 1,
                'add_time' => time(),
            ];
            Db::name('agv_task')->insert($data);
            $ret = $hik->agv_shelf_back($task_code,$shelf_number_arr['numbering']);
            write_log(var_export('4层小车move回家：'.json_encode($ret),true),'arm_task_');
            return true;
        }
        if(empty($find_arm)){
            write_log(var_export('机械臂任务是不是空的？'.'-----机械臂还有其他任务',true),'arm_task_');

            $reply = $this->reply_arm($arm['id']);
            if(!$reply){
                write_log(var_export('机械臂没有回复',true),'arm_task_');
                return false;
            }
            // 4层回去
            if($shelf_number_arr['shelves_id']==10){
                if($iot['type'] == 2){
                    $task_code = 'move_agv_'.time().rand(1000,9999);
                    $data = [
                        'task_code' => $task_code,
                        'robotarm_id' => $arm['id'],
                        'iot_id' => $arm['iot_id'],
                        'wh_s_id' => $shelf_number_arr['shelves_id'],
                        'sort' => $arm['sort'],
                        'from_depth' =>0,
                        'to_depth' =>0,
                        'status' => 1,
                        'add_time' => time(),
                    ];
                    Db::name('agv_task')->insert($data);
                    $ret = $hik->agv_back($task_code);
                    write_log(var_export($shelf_number_arr['shelves_id'].'--小车move回家：'.json_encode($ret),true),'arm_task_');
                }
                
            }else{
                $task_code = 'move_'.time().rand(1000,9999);
                $data = [
                    'task_code' => $task_code,
                    'robotarm_id' => $arm['id'],
                    'iot_id' => $arm['iot_id'],
                    'wh_s_id' => $shelf_number_arr['shelves_id'],
                    'sort' => $arm['sort'],
                    'from_depth' =>0,
                    'to_depth' =>0,
                    'status' => 1,
                    'add_time' => time(),
                ];
                Db::name('agv_task')->insert($data);
                $ret = $hik->agv_shelf_back($task_code,$shelf_number_arr['numbering']);
                write_log(var_export($shelf_number_arr['shelves_id'].'--小车move回家：'.json_encode($ret),true),'arm_task_');
            }
            return false;
           
            

        }
        
        $hik->send_robot_arm(0,0);
        // 执行下一个任务
        $this->arm_task();

        } catch (\Exception $e){
            write_log(var_export('错误信息: ' . $e->getMessage(),true),'arm_error_');
            write_log(var_export('错误行号: ' . $e->getLine(),true),'arm_error_');
        }

        return true;
    }

    public function arm_task(){
        // 先看本小车的的任务结束没
        // 看其他小车的机械臂任务
        $arm = Db::name('robotarm')->where('status','in',[1,6])->order('id asc')->find();
        $arm2 = Db::name('robotarm')->where('id',$arm['id']-1)->find();
        // 没有任务
        if(empty($arm)){
            write_log(var_export('没有任务',true),'arm_task_');
            return false;
        }
        $hik = new HikController();
        // 这一个的status是1，看是不是新的iot_id的,或者看上一个iot是不是执行完了
        
        if($arm['sort'] > 1 && !empty($arm2) && $arm2['iot_id'] != $arm['iot_id']){
            // 查上一个任务的iot_id是不是有移动的,没有先把上一个去移动
            $find_agv_task = Db::name('agv_task')->where('task_code','like','%move%')->where(['iot_id'=>$arm2['iot_id']])->find();
            if(empty($find_agv_task)){

                $reply = $this->reply_arm($arm['id']);
                if(!$reply){
                    write_log(var_export('机械臂没有回复',true),'arm_task_');
                    return false;
                }

                $iot2 = Db::name('iot_task')->where(['id'=>$arm2['iot_id']])->find();
                if($iot2['type'] == 1){
                    // 没找到，是入库
                    // 库位数，目标库位
                    $location_all = WarehouseLocationModel::where(['numbering'=>$arm2['to_location_code']])->find();
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$location_all['shelves_id'],'type'=>2])->find();
                }else{
                    // 出库
                    // 库位数，目标站位
                    $location_all = WarehouseLocationModel::where(['numbering'=>$arm2['to_location_code']])->find();
                    $go_back = WarehouseLocationModel::where(['numbering'=>$arm2['from_location_code']])->find();
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$go_back['shelves_id'],'type'=>2])->find();
                    
                }
                $task_code = 'move_'.time().rand(1000,9999);
                $data = [
                    'task_code' => $task_code,
                    'robotarm_id' => $arm2['id'],
                    'iot_id' => $arm2['iot_id'],
                    'wh_s_id' => $shelf_number_arr['shelves_id'],
                    'sort' => $arm2['sort'],
                    'from_depth' =>0,
                    'to_depth' =>0,
                    'status' => 1,
                    'add_time' => time(),
                ];
                Db::name('agv_task')->insert($data);
                $ret = $hik->agv_shelf_back($task_code,$shelf_number_arr['numbering']);
                write_log(var_export($shelf_number_arr['shelves_id'].'小车move：',true),'arm_task_');
                return false;
            }
        }

        if($arm['status'] == 1){ 
            write_log(var_export('机械臂动：'.json_encode([$arm['layer'],$arm['num']]),true),'arm_task_');
            $res = $hik->send_robot_arm($arm['layer'],$arm['num']);
            RobotarmModel::update([
                'status'=>2, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                'usetime'=>time(),
            ],[     
                'id'=>$arm['id'],
            ]);
            
        }
        if($arm['status'] == 6){
            // 先看三层货架的角度
            $from_shelves = WarehouseShelvesModel::where('id',10)->find();
            $between = $arm['id']-$arm['sort'];
            // 此刻小车的方向
            if(empty($from_shelves['rotate']) || $from_shelves['rotate'] > 0){
                $rotate = 1; //是depth 2
            }else{
                $rotate = 2; //是depth 1
            }
            $need_routate = false;
            $need_routate_four = false;
            if (($arm['type'] == 1 && $arm['from_depth'] == $rotate ) || ($arm['type'] == 2 && $arm['to_depth'] == $rotate)) {
                // 3层要转 
                $is_routate = Db::name('agv_task')->where(['iot_id'=>$arm['iot_id']])
                ->where('task_code', 'like', '%rotate%')->where('wh_s_id',10)
                ->where('robotarm_id','>',$between)
                ->where('robotarm_id','<=',$arm['id'])->find();
                if(!$is_routate){
                    // 检测是否复位
                    $reply = $this->reply_arm($arm['id']);
                    if(!$reply){
                        write_log(var_export('机械臂没有回复',true),'arm_task_');
                        return false;
                    }
            
                    $need_routate = true; 
                    $task_code = 'rotate_agv_'.time().rand(1000,9999);
                    $data = [
                        'task_code' => $task_code,
                        'robotarm_id' => $arm['id'],
                        'iot_id' => $arm['iot_id'],
                        'wh_s_id' => 10,
                        'sort' => $arm['sort'],
                        'from_depth' =>$arm['from_depth'],
                        'to_depth' =>$arm['to_depth'],
                        'status' => 1,
                        'add_time' => time(),
                    ];
                    Db::name('agv_task')->insert($data);
                    // 执行旋转 1=》4层 2=》3层
                    $type = 2;
                    $num_type = $rotate == 1 ? 2 : 1;
                    $ret = $hik->agv_rotate($type,$task_code,$num_type);
                    write_log(var_export('小车旋转-10：'.json_encode($ret),true),'arm_task_');
                }
            }
            if(($arm['type'] == 1 && $arm['to_depth'] == 2) || ($arm['type'] == 2 && $arm['from_depth'] == 2)){
                // 4层要转
                $find_location_four = $arm['type'] == 1 ? $arm['to_location_code'] : $arm['from_location_code'];
                $location = WarehouseLocationModel::where('numbering',$find_location_four)->find();
                $is_routate_four = Db::name('agv_task')->where(['iot_id'=>$arm['iot_id']])
                ->where('task_code', 'like', '%rotate%')
                ->where('wh_s_id',$location['shelves_id'])
                ->where('robotarm_id','>',$between)
                ->where('robotarm_id','<=',$arm['id'])->find();
                if(!$is_routate_four){
                    // 检测是否复位
                    $reply = $this->reply_arm($arm['id']);
                    if(!$reply){
                        write_log(var_export('机械臂没有回复',true),'arm_task_');
                        return false;
                    }

                    $need_routate_four = true; 
                    $task_code = 'rotate_'.time().rand(1000,9999);
                    $data = [
                        'task_code' => $task_code,
                        'robotarm_id' => $arm['id'],
                        'iot_id' => $arm['iot_id'],
                        'wh_s_id' => $location['shelves_id'],
                        'sort' => $arm['sort'],
                        'from_depth' =>$arm['from_depth'],
                        'to_depth' =>$arm['to_depth'],
                        'status' => 1,
                        'add_time' => time(),
                    ];
                    Db::name('agv_task')->insert($data);
                    // 执行旋转 1=》4层 2=》3层
                    $type = 1;
                    $num_type = 2;
                    $ret = $hik->agv_rotate($type,$task_code,$num_type);
                    write_log(var_export('小车旋转-'.$location['shelves_id'].':'.json_encode($ret),true),'arm_task_');
   
                }
            }
            if($need_routate == false && $need_routate_four == false){
                // 都不用转，执行任务
                write_log(var_export('小车都不要旋转，机械臂开始',true),'arm_task_');
                RobotarmModel::update([
                    'status'=>1, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                    'usetime'=>time(),
                ],[     
                    'iot_id'=>$arm['iot_id'],
                    'from_depth'=>$arm['from_depth'],
                    'to_depth'=>$arm['to_depth'],
                    'status'=>6,
                ]);
                $arm = RobotarmModel::where('status',1)->find();
                write_log(var_export('小车都不要旋转，机械臂任务：'.$arm['id'],true),'arm_task_');
                $res = $hik->send_robot_arm($arm['layer'],$arm['num']);
                RobotarmModel::update([
                    'status'=>2, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                    'usetime'=>time(),
                ],[     
                    'id'=>$arm['id'],
                ]);
                return false;
            }
        }
    }  

    public function reply_arm($task_id){
        // 找回复的任务
        $arm = RobotarmModel::where(['iot_id'=>$task_id])->where('sort',0)->where('status',5)->find();
        if(empty($arm)){
            // 添加回复任务
            Db::name('robotarm')->insert([
                'sort'=>0,
                'status' =>5,
                'iot_id' => $task_id,
                'addtime' => time(),
            ]);
            $hik = new HikController();
            $res = $hik->robot_arm_reply();
            return false;
        }
        return true;
    }
}