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

//机器人
use app\common\model\HaiRobot as HaiRobotModel;//海柔机器人
use app\common\model\HaiLocation as HaiLocationModel;//工作位
use app\common\model\HaiStation as HaiStationModel;//工作站
use app\common\model\HaiContainer as HaiContainerModel;//容器


//播种墙-无播种墙-关联233行
//use app\common\model\SowBz as SowBzModel;
//use app\common\model\SowBzBit as SowBzBitModel;

//use app\api\controller\v1\Hai as HaiController;

class HaiTask extends Command
{

    //执行开关 1打开 2关闭
    protected $up_switch =[
        'up_robot'=>1,
        'up_location'=>1,
        'up_station'=>1,
        'up_container'=>1,
    ];

    //单页数据量
    protected $limit ='50';


    protected function configure(){

        $this->setName('HaiTask')->setDescription("计划任务 HaiTask");
    }


    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output){

        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/

        //更新机器人状态
        if($this->up_switch['up_robot']==1){
            $this->up_robot();
            $output->writeln('up_robot 已经执行...');
        }

        //同步工作位
        if($this->up_switch['up_location']==1){
            $this->up_location();
            $output->writeln('up_location 已经执行...');
        }
        //同步工作站
        if($this->up_switch['up_station']==1){
            $this->up_station();
            $output->writeln('up_station 已经执行...');
        }

        //同步容器
        if($this->up_switch['up_container']==1){
            $this->up_container();
            $output->writeln('up_container 已经执行...');
        }


        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');

    }

    //同步容器
    public function up_container(){
        $project = ProjectModel::where(['status'=>'1'])->select();
        $in_api_url = Config::get('in_api_url');
        foreach ($project as $val) {
            $data = [
                'lfb_key' => $val['key'],
            ];
            $ret1 = go_curl($in_api_url.'hai/ess_api_container',"POST",$data);
            $ret1= json_decode($ret1,true);
            if(!empty($ret1) && is_array($ret1) && $ret1['code']=='1'){
                write_log(var_export($ret1['data']['total'],true),'up_container_total_');

               $page = floor($ret1['data']['total'] / $this->limit)+1;
                for ($i = 1; $i <= $page; $i++) {
                    $data['page'] =$i;
                    $ret = go_curl($in_api_url.'hai/ess_api_container',"POST",$data);
                    $ret = json_decode($ret,true);
                    if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
                        foreach ($ret['data']['containers'] as $val1){

                            $check = HaiContainerModel::where(['code'=>$val1['code'],'project_id'=>$val['id']])->find();
                            if(!empty($check)){
                                //更新
                                HaiContainerModel::update([
                                    'container_type_code'=>$val1['containerTypeCode'],
                                    'last_storage_location_code'=>$val1['lastStorageLocationCode'],
                                    'last_station_code'=>$val1['lastStationCode'],
                                    'position_code'=>$val1['positionCode'],
                                    'is_inside'=>$val1['isInside'],
                                    'is_locked'=>$val1['isLocked'],
                                    'state'=>$val1['state'],
                                    'content'=>json_encode($val1,true),
                                    'up_time'=>time(),

                                ],[
                                    'code'=>$val1['code'],
                                    'project_id'=>$val['id'],
                                ]);
                            }else{
                                //写入
                                HaiContainerModel::insert([
                                    'code'=>$val1['code'],
                                    'container_type_code'=>$val1['containerTypeCode'],
                                    'last_storage_location_code'=>$val1['lastStorageLocationCode'],
                                    'last_station_code'=>$val1['lastStationCode'],
                                    'position_code'=>$val1['positionCode'],
                                    'is_inside'=>$val1['isInside'],
                                    'is_locked'=>$val1['isLocked'],
                                    'state'=>$val1['state'],
                                    'content'=>json_encode($val1,true),
                                    'up_time'=>time(),
                                    'project_id'=>$val['id'],
                                ]);
                            }
                        }
                    }

                }

            }

        }
    }

    //同步工作站
    public function up_station(){

        $project = ProjectModel::where(['status'=>'1'])->select();
        $in_api_url = Config::get('in_api_url');
        foreach ($project as $val) {

            $data = [
                'h_q_id' => '4',//工作站查询
                'lfb_key' => $val['key'],
            ];
            $ret = go_curl($in_api_url.'hai/get_hai_q',"POST",$data);
            $ret = json_decode($ret,true);
            //write_log(var_export($ret,true),'up_station_');
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
                foreach ($ret['data']['stations'] as $val1){

                    $check = HaiStationModel::where(['station_code'=>$val1['stationCode'],'project_id'=>$val['id']])->find();
                    // write_log(var_export($check,true),'up_robot2_');
                    if(!empty($check)){
                        //更新
                        HaiStationModel::update([
                            'station_model_code'=>$val1['stationModelCode'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),

                        ],[
                            'station_code'=>$val1['stationCode'],
                            'project_id'=>$val['id'],
                        ]);
                    }else{
                        //写入
                        HaiStationModel::insert([
                            'station_model_code'=>$val1['stationModelCode'],
                            'station_code'=>$val1['stationCode'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),
                            'project_id'=>$val['id'],
                        ]);
                    }

                }
            }


        }
    }

    //同步工作位
    public function up_location(){
        $project = ProjectModel::where(['status'=>'1'])->select();
        $in_api_url = Config::get('in_api_url');
        foreach ($project as $val){

            $data =[
                'h_q_id'=>'3',//工作位查询
                'lfb_key'=>$val['key'],
            ];
            $ret = go_curl($in_api_url.'hai/get_hai_q',"POST",$data);
            $ret = json_decode($ret,true);
            //write_log(var_export($ret,true),'up_location_');
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
                foreach ($ret['data']['locations'] as $val1){

                    $check = HaiLocationModel::where(['location_code'=>$val1['locationCode'],'project_id'=>$val['id']])->find();
                    // write_log(var_export($check,true),'up_robot2_');
                    if(!empty($check)){
                        //更新
                        HaiLocationModel::update([
                            'location_type_code'=>$val1['locationTypeCode'],
                            'station_model_code'=>$val1['stationModelCode'],
                            'station_code'=>$val1['stationCode'],
                            'position_x'=>$val1['positionX'],
                            'position_y'=>$val1['positionY'],
                            'position_z'=>$val1['positionZ'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),

                        ],[
                            'location_code'=>$val1['locationCode'],
                            'project_id'=>$val['id'],
                        ]);
                    }else{
                        //写入
                        HaiLocationModel::insert([
                            'location_code'=>$val1['locationCode'],
                            'location_type_code'=>$val1['locationTypeCode'],
                            'station_model_code'=>$val1['stationModelCode'],
                            'station_code'=>$val1['stationCode'],
                            'position_x'=>$val1['positionX'],
                            'position_y'=>$val1['positionY'],
                            'position_z'=>$val1['positionZ'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),
                            'project_id'=>$val['id'],
                        ]);
                    }

                    //更新播种墙库位
                    /*switch ($val['wall_type']){
                        case '1'://灵联
                            if($val1['stationModelCode']=='RACK_BUFFER_STATION'){//缓存货架
                                $sow_bit =  SowBzBitModel::where(['station_code'=>$val1['stationCode'],'location_code'=>$val1['locationCode']])->find();
                                if(empty($sow_bit)){

                                    SowBzBitModel::insert([
                                        'station_code'=>$val1['stationCode'],
                                        'location_code'=>$val1['locationCode'],
                                        'up_time'=>time(),
                                        'project_id'=>$val['id'],
                                    ]);

                                }
                            }


                            break;
                        case '2':
                            break;
                    }*/
                }
            }
        }
    }

    //更新机器人
    public function up_robot(){

        $project = ProjectModel::where(['status'=>'1'])->select();
        $in_api_url = Config::get('in_api_url');
        foreach ($project as $val){
            $data =[
                'h_q_id'=>'7',//获取机器人列表
                'lfb_key'=>$val['key'],
            ];
            $ret = go_curl($in_api_url.'hai/get_hai_q',"POST",$data);
            //write_log(var_export($ret,true),'up_robot_');
            $ret = json_decode($ret,true);
           // write_log(var_export($ret['data']['robots'],true),'up_robot_');
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
                foreach ($ret['data']['robots'] as $val1){

                    $check = HaiRobotModel::where(['robot_code'=>$val1['robotCode'],'project_id'=>$val['id']])->find();
                   // write_log(var_export($check,true),'up_robot2_');
                    if(!empty($check)){
                        //更新
                        HaiRobotModel::update([
                            'point_code'=>$val1['pointCode'],
                            'state'=>$val1['state'],
                            'hardware_state'=>$val1['hardwareState'],
                            'robot_type_code'=>$val1['robotTypeCode'],
                            'station_code'=>$val1['stationCode'],
                            'location_code'=>$val1['locationCode'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),

                        ],[
                            'robot_code'=>$val1['robotCode'],
                            'project_id'=>$val['id'],
                        ]);
                    }else{
                        //写入
                        HaiRobotModel::insert([
                            'point_code'=>$val1['pointCode'],
                            'state'=>$val1['state'],
                            'hardware_state'=>$val1['hardwareState'],
                            'robot_type_code'=>$val1['robotTypeCode'],
                            'station_code'=>$val1['stationCode'],
                            'location_code'=>$val1['locationCode'],
                            'content'=>json_encode($val1,true),
                            'up_time'=>time(),
                            'robot_code'=>$val1['robotCode'],
                            'project_id'=>$val['id'],
                        ]);
                    }
                }
            }
        }

    }



}