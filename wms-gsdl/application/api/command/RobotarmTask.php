<?php
//海柔计划任务
namespace app\api\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;
use think\Config;


use app\common\model\Project as ProjectModel;//项目关联

use app\common\model\IotTask as IotTaskModel; //IOT任务
use app\common\model\WarehouseLocation as WarehouseLocationModel; //库位
use app\common\model\WarehouseContainer as WarehouseContainerModel;//容器
use app\common\model\WorkbenchBit as WorkbenchBitModel; //工作台工作位
use app\common\model\Robotarm as RobotarmModel; //机械臂任务
use app\api\controller\v1\Hik as HikController; //海康api控制器

class RobotarmTask extends Command
{

   
    protected function configure(){

        $this->setName('RobotarmTask')->setDescription("计划任务 RobotarmTask");
    }

    // 料箱号做组
    // 修改status为执行中2
    // 遇到status=>2  break

    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output){

        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        $this->task();
        $output->writeln('task 已经执行...');

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');

    }

  
    public function task(){
        try{
 
        write_log(var_export('机械臂任务计划任务执行',true),'robotarm_task');
        // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
        $arm = Db::name('robotarm')->where('status','<>',3)->order('sort asc')->find();
        write_log(var_export('机械臂任务计划任务执行-找到数据：'.json_encode($arm),true),'robotarm_task');
        $iot = Db::name('iot_task')->where(['id'=>$arm['iot_id']])->find();
        $hik = new HikController();
         write_log(var_export('==========aaaa======',true),'robotarm_task');  
        // 没有任务
        if(empty($arm)){
            write_log(var_export('无机械臂任务',true),'robotarm_task');
            return false;
        } 
        // 执行中或已完成
        if($arm['status'] == 2){
            write_log(var_export('机械臂任务还在执行',true),'robotarm_task');
            return false;
        } 
        if($arm['status'] == 3){
            write_log(var_export('机械臂任务已完成',true),'robotarm_task');
            return false;
        }
        write_log(var_export('机械臂任务不是layer=0'.$arm['layer'],true),'robotarm_task'); 
        //layer为0
        if($arm['layer'] == 0 ){

            // 发送，执行下一个命令
           $zero = $hik->send_robot_arm($arm['layer'],$arm['num']); 
           write_log(var_export('机械臂任务回位：'.$arm['id'],true),'robotarm_task');
            RobotarmModel::update([
                'status'=>3, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                'usetime'=>time(),
            ],[     
                'id'=>$arm['id'],
            ]);
            write_log(var_export('机械臂任务更新容器与库位状态',true),'robotarm_task');
            //是不是这一组的最后一个
            if($arm['sort'] == 4){
                try{
                write_log(var_export('更新东西:'.$iot['name'],true),'robotarm_task');
                if(strpos($iot['name'],'outbound') === false){
                    // 没找到，是入库
                    // 库位数，目标库位
                    write_log(var_export('进入入库:'.$arm['to_location_code'],true),'robotarm_task');
                    $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                    write_log(var_export('是入库:'.json_encode($location_all),true),'robotarm_task');
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$location_all['shelves_id'],'type'=>2])->find();
                    write_log(var_export('4ceng小车回家：'.$shelf_number_arr['numbering'],true),'robotarm_task');
                }else{
                    // 出库
                    // 库位数，目标站位
                    write_log(var_export('进入出库:'.$arm['from_location_code'],true),'robotarm_task');
                    $location_all = WarehouseLocationModel::where(['numbering'=>$arm['to_location_code']])->find();
                    write_log(var_export('是出库：'.json_encode($location_all),true),'robotarm_task');
                    $go_back = WarehouseLocationModel::where(['numbering'=>$arm['from_location_code']])->find();
                    $shelf_number_arr = WarehouseLocationModel::where(['shelves_id'=>$go_back['shelves_id'],'type'=>2])->find();
                    write_log(var_export('4ceng小车回家：'.$shelf_number_arr['numbering'],true),'robotarm_task');
                }
                // 容器解绑与重新绑定
                $unbind = $hik->bind_container($arm['container_code'],$arm['from_location_code'],1,'UNBIND');
                if($unbind['code'] = 'SUCCESS'){
                    write_log(var_export('【'.$arm['container_code'].'】料箱解绑【'.$arm['to_location_code'].'】成功:'.$unbind['message'],true),'robotarm_task');
                }else{
                    write_log(var_export('【'.$arm['container_code'].'】料箱解绑【'.$arm['to_location_code'].'】失败:'.$unbind['message'],true),'robotarm_task');
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
                write_log(var_export('容器更改',true),'robotarm_task');
                WarehouseLocationModel::update([
                    'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                    'up_time'=>time(),
                ],[
                    'real_location'=>$location_all['real_location'],
                ]);  
                write_log(var_export('库位更改',true),'robotarm_task');
                }catch (Exception $e){
                    write_log(var_export('错误信息:'.$e,true),'robotarm_task');
                }
            }
            write_log(var_export('机械臂任务更新容器与库位状态完成',true),'robotarm_task');
            // 执行小车回家
            $ret = $hik->agv_task_back(2,'','',$shelf_number_arr['numbering']);
            write_log(var_export('小车回家：'.json_encode($ret),true),'robotarm_task');
            return true;
        }else{
           write_log(var_export('机械臂任务不是layer=0',true),'robotarm_task'); 
        }

       
        // 刚创建，发送
        if($arm['status'] == 1){
            write_log(var_export('==========到达sattus=1======',true),'robotarm_task'); 
            $res = $hik->send_robot_arm($arm['layer'],$arm['num']);
            write_log(var_export('机械臂任务计划任务执行-发送status；1-11111：'.json_encode($res),true),'robotarm_task');
            write_log(var_export('查找'.json_encode($arm),true),'robotarm_task');
            RobotarmModel::update([
                'status'=>2, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                'usetime'=>time(),
            ],[     
                'id'=>$arm['id'],
            ]);
            write_log(var_export('==========到修改======',true),'robotarm_task');
            $end = RobotarmModel::update([
                'status'=>2, // 状态:1=创建,2=执行中,3=完成,4=失败,5=取消,6=计划
                'usetime'=>time(),
            ],[     
                'id'=>$arm['id'],
            ]);
            write_log(var_export('机械臂任务发新的：status=1  '.$end,true),'robotarm_task');
            return true;
        }
        }catch(Exception $e){
            write_log(var_export('机械臂错误信息：'.$e,true),'robotarm_task');
        }
       
    }
}