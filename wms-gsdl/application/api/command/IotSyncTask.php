<?php
//Lot同步计划任务
namespace app\api\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;
use think\Config;
use think\Cache;

use app\api\controller\v1\MoveCabinets as MoveCabinetsController; //密集柜 api控制器

use app\common\model\Warehouse as WarehouseModel;//仓库
use app\common\model\Workbench as WorkbenchModel;//工作站
use app\common\model\WorkbenchBit as WorkbenchBitModel;//工作站位置
use app\common\model\PickWall as PickWallModel;//分拣墙
use app\common\model\PickWallBit as PickWallBitModel;//分拣墙位置
use app\common\model\WarehouseArea as WarehouseAreaModel;//库区
use app\common\model\WarehouseContainer as WarehouseContainerModel;//容器
use app\common\model\WarehouseLocation as WarehouseLocationModel;//库位
use app\common\model\Conveyor as ConveyorModel;//输送线
use app\common\model\Agv as AgvModel;//机器人
use app\common\model\IotConfig as IotConfigModel;//Iot配置

class IotSyncTask extends Command
{

    protected $robot_type ='1'; //机器人 1海柔  2 其他
    protected $wall_type= '1'; //播种墙 1灵联 2其他
    //protected $is_hai_wall ='2';//是否使用海柔播种墙 1是 2不是
    protected $api_name =[
        'up_workbench'=>'',//工作站
        'up_location'=>'',//库位
        'up_container'=>'',//容器
        'up_pick_wall'=>'',//播种墙
        'up_pick_wall_bit'=>'',//播种墙位
    ];

    //机器人类型
    protected $robot_type_code =[
        'RT_KUBOT'=>'1',
        'RT_HAIFLEX_NO_LOAD'=>'2',
    ];
    //机器人状态
    protected $robot_state =[
        'UNAVAILABLE'=>'2',
        'UNKNOWN'=>'2',
        'ERROR'=>'3',
        'IDLE'=>'1',
        'EXECUTING'=>'1',
        'AWAITING'=>'1',
    ];

    protected $robot_x= 6050;//机器人休息点x
    protected $robot_y= 1500;//机器人休息点y
    protected $robot_status= 'ROBOT_IDLE';
    protected $robot_status_num = 0;




    protected function configure(){

        $this->setName('IotSyncTask')->setDescription("计划任务 IotSyncTask");
    }


    //调用SendMessage 这个类时,会自动运行execute方法
    protected function execute(Input $input, Output $output){

        //机器人厂家选接口
        switch ($this->robot_type){
            case '1':
                $this->api_name=[
                    'up_workbench'=>'hai/get_station',
                    'up_location'=>'hai/get_location',
                    'up_container'=>'hai/get_container',
                    'up_robot'=>'hai/get_robot',
                ];
                break;
            case '2':

                break;
        }

        //播种机器厂家选接口-无分拣墙
       /* switch ($this->wall_type){
            case '1':
                $this->api_name['up_pick_wall']='sow/get_bz';
                $this->api_name['up_pick_wall_bit']='sow/get_bz_bit';
                break;
            case '2':

                break;
        }*/



        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/


        //同步工作站
        $this->up_workbench();
        //同步分拣墙-无分拣墙
        ////$this->up_pick_wall();
        //同步库位
        $this->up_location();
        //同步分拣墙 位置
        //$this->up_pick_wall_bit();
        //同步容器
        $this->up_container();
        //同步机器人
        $this->up_robot();


        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');

    }

    //同步机器人
    public function up_robot(){
        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');

        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_robot'],"POST",$data);
            $ret = json_decode($ret,true);
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){

                //机器人类型
                switch ($this->robot_type) {

                    case '1'://海柔
                        foreach ($ret['data'] as $val1){
                            $check = AgvModel::where(['robot_code'=>$val1['robot_code'],'cid'=>$val['cid']])->find();
                            if(!empty($check)){
                                //更新
                                AgvModel::update([
                                    'name'=>$val1['robot_code'],
                                    'robot_code'=>$val1['robot_code'],
                                    'robot_type_code'=>$val1['robot_type_code'],
                                    'type'=>$this->robot_type_code[$val1['robot_type_code']],
                                    'status'=>$this->robot_state[$val1['state']],
                                    'up_time'=>time(),

                                ],['robot_code'=>$val1['robot_code'],'cid'=>$val['cid']]);

                            }else{
                                //添加
                                AgvModel::insert([
                                    'name'=>$val1['robot_code'],
                                    'robot_code'=>$val1['robot_code'],
                                    'robot_type_code'=>$val1['robot_type_code'],
                                    'type'=>$this->robot_type_code[$val1['robot_type_code']],
                                    'status'=>$this->robot_state[$val1['state']],
                                    'aid'=>$val['aid'],
                                    'cid'=>$val['cid'],
                                    'up_time'=>time(),
                                    'add_time'=>time(),
                                ]);

                            }
                            $content = json_decode($val1['content'],true);
                            $lot = new MoveCabinetsController();
                            if(!empty($content) && is_array($content)){

                                if(empty(Cache::get('move_status'))) {
                                    Cache::set('move_status',[
                                        'status'=>2,//1打开 2关闭
                                        'time'=>time(),
                                    ]);
                                }
                                $move_status = Cache::get('move_status');

                                if(empty(Cache::get('robot_status'))) {
                                    Cache::set('robot_status',[
                                        'status'=>$content['hardwareState'],
                                        'time'=>time(),
                                    ]);
                                }
                                $robot_status = Cache::get('robot_status');
                                if($content['hardwareState']!=$robot_status['status']){
                                    Cache::set('robot_status',[
                                        'status'=>$content['hardwareState'],
                                        'time'=>time(),
                                    ]);
                                }
                                //hardwareState
                                if($content['hardwareState']!='ROBOT_RUNNING'){
                                    if(($content['positionX']>=$this->robot_x-10 && $content['positionX']<=$this->robot_x+10) && ($content['positionY']>=$this->robot_y-10 && $content['positionY']<=$this->robot_y+10) ){
                                        $ret = $lot->get_status_data();
                                        if(!empty($ret) && is_array($ret)){
                                            if($ret[0]<85 || $ret[1]<85 || $ret[1]<85 || $ret[3]<85){

                                                $robot_status = Cache::get('robot_status');
                                               // write_log('A3'.var_export($robot_status,true),'get_status_data_');
                                                if(time()-$robot_status['time']>120 && $robot_status['status']!='ROBOT_RUNNING'){

                                                    /*Cache::set('robot_status',[
                                                        'status'=>$content['hardwareState'],
                                                        'time'=>time(),
                                                    ]);*/

                                                    if($move_status['status']==1 && time()-$move_status['time']>600){
                                                       // $lot->to_off();
                                                    }

                                                }

                                            }
                                        }

                                    }
                                }







                            }



                        }


                        break;

                    case '2'://其他

                        break;
                }
            }

        }
    }


    //同步分拣墙/播种墙
    /*public function up_pick_wall(){
        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');

        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_pick_wall'],"POST",$data);
            $ret = json_decode($ret,true);
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){

                //播种墙类型
                switch ($this->wall_type) {

                    case '1'://灵联
                        foreach ($ret['data'] as $val1){
                            $pick_wall_id = '';//分拣墙id
                            $check = PickWallModel::where(['real_wall_code'=>$val1['dev_code'],'cid'=>$val['cid']])->find();
                            if(!empty($check)){
                                $pick_wall_id = $check['id'];
                                PickWallModel::update([
                                    'name'=>$val1['dev_code'],
                                    'numbering'=>$val1['dev_code'],
                                    'real_station'=>$val1['station_code'],
                                    'bit_num'=>$val1['tags_num'],//暂存位
                                    'up_aid'=>$val['aid'],
                                    'up_time'=>time(),

                                ],[
                                    'real_wall_code'=>$val1['dev_code'],
                                    'cid'=>$val['cid'],
                                ]);

                            }else{
                                $pick_wall_id = PickWallModel::insertGetId([
                                    'real_wall_code'=>$val1['dev_code'],
                                    'cid'=>$val['cid'],
                                    'name'=>$val1['dev_code'],
                                    'numbering'=>$val1['dev_code'],
                                    'real_station'=>$val1['station_code'],
                                    'bit_num'=>$val1['tags_num'],//暂存位
                                    'aid'=>$val['aid'],
                                    'add_time'=>time(),
                                ]);


                            }
                            //关联工作位
                            if(!empty($pick_wall_id)){
                                $pick_wall =PickWallModel::where(['real_wall_code'=>$val1['dev_code'],'cid'=>$val['cid']])->find();
                                WorkbenchModel::update([
                                    'wall_numbering'=>$pick_wall['numbering'],
                                    'wall_id'=>$pick_wall['id'],
                                    'up_aid'=>$val['aid'],
                                    'up_time'=>time(),

                                ],[
                                    'real_station'=>$pick_wall['real_station'],
                                ]);
                            }

                        }

                        break;
                    case '2':
                        break;
                }

            }

        }
    }*/

    //
    public function up_pick_wall_bit(){
        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');

        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_pick_wall_bit'],"POST",$data);
            $ret = json_decode($ret,true);
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
                //播种墙类型
                switch ($this->wall_type) {

                    case '1'://灵联
                        foreach ($ret['data'] as $val1){

                            //取播种墙
                            $pkw = PickWallModel::where(['real_wall_code'=>$val1['sow_bz_code']])->find();
                            //write_log(var_export($pkw,true),'pkw_');
                            //取库位
                            $whl = WarehouseLocationModel::where(['real_location'=>$val1['location_code']])->find();


                            $check = PickWallBitModel::where(['real_location'=>$val1['sow_bz_code'],'sort'=>$val1['ordinal'],'cid'=>$val['cid']])->find();
                            //write_log(var_export($check,true),'check_');


                            if(!empty($pkw) && !empty($whl)){

                                if(!empty($check)){

                                    PickWallBitModel::update([
                                        'wall_id'=>$pkw['id'],
                                        'wall_numbering'=>$pkw['numbering'],
                                        'wh_l_id'=>$whl['id'],
                                        'wh_l_numbering'=>$whl['numbering'],
                                        //'sort'=>$val1['ordinal'],//位置号
                                        'up_aid'=>$val['aid'],
                                        'up_time'=>time(),

                                    ],[
                                        'real_location'=>$val1['sow_bz_code'],
                                        'sort'=>$val1['ordinal'],
                                        'cid'=>$val['cid'],
                                    ]);

                                }else{
                                    PickWallBitModel::insert([
                                        'real_location'=>$val1['sow_bz_code'],
                                        'cid'=>$val['cid'],
                                        'wall_id'=>$pkw['id'],
                                        'wall_numbering'=>$pkw['numbering'],
                                        'wh_l_id'=>$whl['id'],
                                        'wh_l_numbering'=>$whl['numbering'],
                                        'sort'=>$val1['ordinal'],//位置号
                                        'aid'=>$val['aid'],
                                        'add_time'=>time(),
                                    ]);


                                }
                            }


                        }

                        break;
                    case '2':
                        break;
                }

            }

            }
    }

    //同步容器
    public function up_container(){
        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');

        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_container'],"POST",$data);
            $ret = json_decode($ret,true);
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
               // write_log(var_export($ret['data'],true),'up_container_total_');

                //机器人类型
                switch ($this->robot_type){

                    case '1'://海柔

                        foreach ($ret['data'] as $val1){

                            $wh_l_id = ''; //库位id
                            $wh_l_numbering = ''; //库位编码
                            //real_location 库位绑定编号 ess的last_storage_location_code最后存放库位
                            /*if(!empty($val1['last_storage_location_code'])){
                                $wh_l = WarehouseLocationModel::where(['real_location'=>$val1['last_storage_location_code']])->find();
                                if(!empty($wh_l)){
                                    $wh_l_id = $wh_l['id'];
                                    $wh_l_numbering = $wh_l['numbering'];
                                }

                            }*/
                            //当前容器库位
                            if(!empty($val1['position_code'])){
                                $wh_l = WarehouseLocationModel::where(['real_location'=>$val1['position_code']])->find();
                                if(!empty($wh_l)){
                                    $wh_l_id = $wh_l['id'];
                                    $wh_l_numbering = $wh_l['numbering'];
                                    //目标库位锁定
                                    WarehouseLocationModel::update([
                                        //'status'=>3, //状态:1=可用,2=出入库中,3=锁定
                                        'up_time'=>time(),
                                    ],[
                                        'id'=>$wh_l['id'],
                                    ]);

                                }

                            }
                            $w_id = '';
                            $w_numbering= '';
                            //最后出现的工作台
                            if(!empty($val1['last_station_code'])){
                                $w_l = WorkbenchModel::where(['real_station'=>$val1['last_station_code']])->find();
                                if(!empty($w_l)){
                                    $w_id = $w_l['id'];
                                    $w_numbering = $w_l['numbering'];
                                }
                            }
                            //numbering 容器编码 ess一一对应

                            if(!empty($val1['last_storage_location_code'])){
                                $wh_l_real_location = $val1['last_storage_location_code'];
                            }else{
                                $wh_l_real_location = $val1['position_code'];
                            }

                            $check = WarehouseContainerModel::where(['numbering'=>$val1['code'],'cid'=>$val['cid']])->find();
                            if(!empty($check)){



                                //更新
                                WarehouseContainerModel::update([
                                    //'numbering'=>$val1['code'],
                                    'name'=>$val1['code'],
                                    'wh_l_numbering'=>$wh_l_numbering,
                                    'container_type_code'=>$val1['container_type_code'],
                                    //'wh_l_real_location'=>$val1['last_storage_location_code'],
                                    'wh_l_real_location'=>$wh_l_real_location,
                                    'wh_l_id'=>$wh_l_id,
                                    'w_real_station'=>$val1['last_station_code'],
                                    'w_numbering'=>$w_numbering,
                                    'w_id'=>$w_id,
                                    'status'=>2,//可用
                                    //'aid'=>$val['aid'],
                                    'up_aid'=>$val['aid'],
                                    'up_time'=>time(),

                                ],[
                                    'numbering'=>$val1['code'],
                                    'cid'=>$val['cid'],
                                ]);
                            }else{
                                //写入
                                WarehouseContainerModel::insert([
                                    'name'=>$val1['code'],
                                    'numbering'=>$val1['code'],
                                    'container_type_code'=>$val1['container_type_code'],
                                    'cid'=>$val['cid'],
                                    'wh_l_numbering'=>$wh_l_numbering,
                                   // 'wh_l_real_location'=>$val1['last_storage_location_code'],
                                    'wh_l_real_location'=>$wh_l_real_location,
                                    'wh_l_id'=>$wh_l_id,
                                    'w_real_station'=>$val1['last_station_code'],
                                    'w_numbering'=>$w_numbering,
                                    'w_id'=>$w_id,
                                    'aid'=>$val['aid'],
                                    'add_time'=>time(),
                                ]);
                            }
                        }


                        break;
                    case '2':

                        break;

                }


            }

        }
    }

    //同步工作站
    public function up_workbench(){

        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');
        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_workbench'],"POST",$data);
            $ret = json_decode($ret,true);
            write_log(var_export($ret,true),'up_station_');
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){

                //机器人类型
                switch ($this->robot_type){
                    case '1'://海柔

                        foreach ($ret['data'] as $val1){

                            $check = WorkbenchModel::where(['real_station'=>$val1['station_code'],'cid'=>$val['cid']])->find();
                            // write_log(var_export($check,true),'up_robot2_');


                            if(!empty($check)){
                                //更新
                                WorkbenchModel::update([
                                    'name'=>$val1['station_code'],
                                    'numbering'=>$val1['station_code'],
                                    'up_aid'=>$val['aid'],
                                    'up_time'=>time(),

                                ],[
                                    'real_station'=>$val1['station_code'],
                                    'cid'=>$val['cid'],
                                ]);
                            }else{
                                //写入
                                WorkbenchModel::insert([
                                    'real_station'=>$val1['station_code'],
                                    'cid'=>$val['cid'],
                                    'name'=>$val1['station_code'],
                                    'numbering'=>$val1['station_code'],
                                    'aid'=>$val['aid'],
                                    'add_time'=>time(),
                                ]);
                            }
                        }

                        break;
                    case '2':
                        break;
                }


            }


        }
    }

    //同步工作位
    public function up_location(){
        $iot = IotConfigModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');
        foreach ($iot as $val) {
            $data = [
                'lfb_key' => $val['lfb_key'],
            ];
            $ret = go_curl($iot_api_url. $this->api_name['up_location'],"POST",$data);
            $ret = json_decode($ret,true);
            //write_log(var_export($ret,true),'up_location_');
            if(!empty($ret) && is_array($ret) && $ret['code']=='1'){

                //机器人类型
                switch ($this->robot_type) {
                    case '1'://海柔

                        foreach ($ret['data'] as $val1){

                            $check = WarehouseLocationModel::where(['real_location'=>$val1['location_code'],'cid'=>$val['cid']])->find();
                            // write_log(var_export($check,true),'up_robot2_');

                            $wh_l_id = '';//库位id

                            if(!empty($check)){

                                $wh_l_id = $check['id'];
                                //$status =  $check['status'];
                                //更新库位状态 为可用 6分钟复位一次库位状态-锁定可以作为起点，无法作为终点
                                /*if(($check['up_time']+360)<time()){
                                    $status = 1;
                                }*/
                                //$w_c = WarehouseContainerModel::where(['wh_l_numbering'=>$val1['location_code'],'cid'=>$val['cid']])->find();
                               // if($val1['status']==3 || !empty($w_c)){
                                /*if($val1['status']==3){
                                    $status = $val1['status'];
                                }else{
                                    $status = 1;
                                }*/

                                //更新
                                WarehouseLocationModel::update([
                                    'name'=>$val1['location_code'],
                                    'numbering'=>$val1['location_code'],
                                    'up_aid'=>$val['aid'],
                                    'status'=>$val1['status'],
                                    'up_time'=>time(),

                                ],[
                                    'real_location'=>$val1['location_code'],
                                    'cid'=>$val['cid'],
                                ]);


                            }else{


                                //写入
                                $wh_l_id = WarehouseLocationModel::insertGetId([
                                    'name'=>$val1['location_code'],
                                    'numbering'=>$val1['location_code'],
                                    'real_location'=>$val1['location_code'],

                                    'cid'=>$val['cid'],
                                    'aid'=>$val['aid'],
                                    'add_time'=>time(),
                                ]);
                            }
                           // write_log(var_export($wh_l_id,true),'up_location_id_');
                            if(!empty($wh_l_id)){
                                $wh_l_info = WarehouseLocationModel::where(['id'=>$wh_l_id,'cid'=>$val['cid']])->find();
                                //库位类型
                                switch ($val1['location_type_code']){

                                    case 'LT_SHELF_STORAGE'://标准库位

                                        break;
                                    case 'LT_CACHE_SHELF_STORAGE'://缓存货位
                                        $this->add_workbench_bit($wh_l_id,$val['cid'],$val1);
                                        /*//工作站
                                        $wb = WorkbenchModel::where([ 'real_station'=>$val1['station_code'],])->find();
                                        if(!empty($wb)){
                                            //更新或添加工作站
                                            $wp_check = WorkbenchBitModel::where(['real_location'=>$val1['location_code']])->find();
                                            if(!empty($wp_check)){
                                                WorkbenchBitModel::update([
                                                    'wh_l_numbering'=>$wh_l_info['numbering'],
                                                    'wh_l_id'=>$wh_l_id,
                                                    'w_id'=>$wb['id'],
                                                    'w_numbering'=>$wb['numbering'],
                                                    'up_aid'=>$wp_check['aid'],
                                                    'up_time'=>time(),
                                                    'cid'=>$wp_check['cid'],
                                                ],[
                                                    'real_location'=>$val1['location_code'],
                                                ]);

                                            }else{

                                                WorkbenchBitModel::insert([
                                                    'wh_l_numbering'=>$wh_l_info['numbering'],
                                                    'wh_l_id'=>$wh_l_id,
                                                    'w_id'=>$wb['id'],
                                                    'w_numbering'=>$wb['numbering'],
                                                    'real_location'=>$val1['location_code'],
                                                    'aid'=>$wb['aid'],
                                                    'add_time'=>time(),
                                                    'cid'=>$wb['cid'],
                                                ]);
                                            }



                                        }*/





                                        break;
                                    case 'LT_CACHE_SHELF_ENTRY'://缓存货位进入点

                                        break;
                                    case 'LT_CONVEYOR_INPUT'://输送带进入

                                        break;
                                    case 'LT_CONVEYOR_OUTPUT'://输送带出去

                                        break;
                                    case 'LT_HAIFLEX_SHELF_STORAGE'://顶升机器人工作位
                                        $this->add_workbench_bit($wh_l_id,$val['cid'],$val1);
                                        break;
                                    case 'LT_CHARGE'://未知

                                        break;
                                    case 'LT_MAINTAIN'://未知1

                                        break;
                                }

                                //工作站类型
                                switch ($val1['station_model_code']){
                                    case 'STORAGE_LOCATION'://货架

                                        break;
                                    case 'RACK_BUFFER_STATION'://缓存货站
                                        $this->add_workbench_bit($wh_l_id,$val['cid'],$val1);
                                        break;
                                    case 'NOT_SET':

                                        break;
                                    case 'CONVEYOR_STATION'://输送站
                                        $this->add_workbench_bit($wh_l_id,$val['cid'],$val1);
                                        $this->add_conveyor($wh_l_id,$val['cid'],$val1);
                                        break;

                                }
                            }

                        }


                        break;
                    case '2':
                        break;
                }


            }
        }
    }

    //根据工作站类型添加 工作站位
    public function add_workbench_bit($wh_l_id,$cid,$data){

        $wh_l_info = WarehouseLocationModel::where(['id'=>$wh_l_id,'cid'=>$cid])->find();

        //工作站
        $wb = WorkbenchModel::where(['real_station'=>$data['station_code']])->find();
        if(!empty($wb)){
            //更新或添加工作站
            $wp_check = WorkbenchBitModel::where(['real_location'=>$data['location_code']])->find();
            if(!empty($wp_check)){
                //write_log($wh_l_info['numbering'].'|'.$data['status'],'add_workbench_bit_');
                WorkbenchBitModel::update([
                    'wh_l_numbering'=>$wh_l_info['numbering'],
                    'wh_l_id'=>$wh_l_id,
                    'w_id'=>$wb['id'],
                    'w_numbering'=>$wb['numbering'],
                    'up_aid'=>$wp_check['aid'],
                   // 'status'=>$data['status'],
                    'up_time'=>time(),
                    'cid'=>$cid,
                ],[
                    'real_location'=>$data['location_code'],
                ]);

            }else{

                WorkbenchBitModel::insert([
                    'wh_l_numbering'=>$wh_l_info['numbering'],
                    'wh_l_id'=>$wh_l_id,
                    'w_id'=>$wb['id'],
                    'w_numbering'=>$wb['numbering'],
                    'real_location'=>$data['location_code'],
                    'aid'=>$wb['aid'],
                    'status'=>$data['status'],
                    'add_time'=>time(),
                    'cid'=>$cid,
                ]);
            }

            //不用海柔播种墙 更新播种墙库位信息
            /* if($this->is_hai_wall=='2'){

                 PickWallModel::where([''])->find();



                 //workbench 更新工作位
                 WorkbenchModel::update([
                     'wall_numbering'=>'',
                     'wall_id'=>'',
                 ],[
                     'real_station'=>$val1['station_code']
                 ]);

             }else{
                 //其他 再开发......

             }*/

        }
    }
    //更新输送线
    public function add_conveyor($wh_l_id,$cid,$data){

        $wh_l_info = WarehouseLocationModel::where(['id'=>$wh_l_id,'cid'=>$cid])->find();
        //输送线
        $wb = ConveyorModel::where(['real_station'=>$data['station_code']])->find();
        $real_location_arr[] = $data['location_code'];
        $wh_l_id_arr[] = $wh_l_id;
        if(!empty($wb)){
            //更新或添加输送线
            $location = array_unique(array_merge(json_decode($wb['real_location'],true),$real_location_arr));
            $wh_l_id= array_unique(array_merge(json_decode($wb['wh_l_id'],true),$wh_l_id_arr));

            ConveyorModel::update([
                    'name'=>$data['station_code'],
                    'wh_l_id'=>json_encode($wh_l_id),
                    'numbering'=>$data['station_code'],
                    'real_location'=>json_encode($location),
                    'up_time'=>time(),
                    'cid'=>$cid,
                ],[
                    'real_station'=>$data['station_code'],
                ]);

            }else{

            ConveyorModel::insert([
                'name'=>$data['station_code'],
                'wh_l_id'=>json_encode($wh_l_id_arr),
                'numbering'=>$data['station_code'],
                'real_location'=>json_encode($real_location_arr),
                'real_station'=>$data['station_code'],
                'up_time'=>time(),
                'cid'=>$cid,
                ]);


        }

    }


    //更新机器人
    /*public function up_robot(){

        $iot = ProjectModel::where(['status'=>'1'])->select();
        $iot_api_url = Config::get('iot_api_url');
        foreach ($iot as $val){
            $data =[
                'h_q_id'=>'7',//获取机器人列表
                'lfb_key'=>$val['key'],
            ];
            $ret = go_curl($iot_api_url.'hai/get_hai_q',"POST",$data);
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

    }*/



}