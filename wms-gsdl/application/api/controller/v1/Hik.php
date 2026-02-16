<?php
namespace app\api\controller\v1;

use iot\Hik as HikApi;
use think\Cache;
use think\Log;
class Hik extends Base
{
    
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];    
    // 接口IP
    protected $api_ip = 'https://192.168.31.85/';
    protected $api_url_before = 'rcs/rtas';

    public function _initialize()
    {

        parent::_initialize();
    
    }
    public function list(){
        return [
            'ctu_out_site' => 'R602A01011',
            'ctu_in_site' => 'R601A01011', 
            'agv_site' => '0003440XX0005000', //agv工作站
            'agv_move_site' => '0007040XX0010890', // agv移动货架机械臂点
            'agv_shelf_site' => '0012040XX0010890', // agv普通货架机械臂点
            'ctu_robot' => '11372',
            'agv_robot' => ['17338','17337'],
        ];
    }


    // 查询机器人状态
    public function get_robot_status(){
        $api_url = '/api/robot/controller/robot/query';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $data = [
            'singleRobotCode' => '17338',
            'robotTaskCode' => '17338',
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // AGV任务下发-移动货架移动
    public function send_agv_task($type,$task_code){
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $all = $this->list();
        $targetRoute = [
            [
                "type" => "SITE",
                "code" => $type == 1 ? $all['agv_site'] : $all['agv_move_site'],
                "operation" => "COLLECT",        
            ],
            [
                "type" => "SITE",
                "code" => $type == 1 ? $all['agv_move_site'] : $all['agv_site'],
                    // 行为   COLLECT 取货  DELIVERY 送货 ROTATE 旋转
                "operation" => "DELIVERY",        
            ],
            
        ];
        $data = [];
        $data['taskType'] = "PF-LMR-COMMON";
        $data['targetRoute'] = $targetRoute;
        if($task_code && !empty($task_code)){
            $data['robotTaskCode'] = $task_code;
        }
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;   
    }

    // AGV任务下发--库内货架
    public function send_agv_task_shelf($type,$task_code_shelf,$sitecode){
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $all = $this->list();
        $targetRoute = [
            [
                "type" => "SITE",
                "code" => $type == 1 ? $sitecode : $all['agv_shelf_site'],
                "operation" => "COLLECT",        
            ],
            [
                "type" => "SITE",
                "code" => $type == 1 ? $all['agv_shelf_site'] : $sitecode,
                    // 行为   COLLECT 取货  DELIVERY 送货 ROTATE 旋转
                "operation" => "DELIVERY",        
            ],
        ];
        $data = []; 
        $data['taskType'] = "PF-LMR-COMMON";
        $data['targetRoute'] = $targetRoute;
        // $data['robotType'] = "GROUPS";
        // $data['robotCode'] = $all['agv_robot'];
        if($task_code_shelf && !empty($task_code_shelf)){
            $data['robotTaskCode'] = $task_code_shelf;
        }
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // agv
    public function agv_task($type,$task_code,$task_code_shelf,$shelf_number){
        // return ['code'=>'SUCCESS','data'=>"123",'message'=>'操作成功'];
        // 查询
        $find_shelf = $this->get_container_status($shelf_number);
        $find = $this->get_container_status('000010');
        $all = $this->list();
        $a = $this->send_agv_task_shelf($type,$task_code_shelf,$find_shelf['data']['siteCode']);
        $b = $this->send_agv_task($type,$task_code);
        return ['code'=>'SUCCESS','data'=>['find'=>[$find_shelf,$find],$a,$b]];
    }
    public function agv_task_outbound($type,$task_code,$task_code_shelf,$shelf_number){
        // return ['code'=>'SUCCESS','data'=>"123",'message'=>'操作成功'];
        // 查询
        $find_shelf = $this->get_container_status($shelf_number);
        $find = $this->get_container_status('000010');
        $all = $this->list();
        $a = $this->send_agv_task_shelf($type,$task_code_shelf,$find_shelf['data']['siteCode']);
        
        if($find['data']['siteCode'] == '0003440XX0005000'){
            $b = $this->send_agv_task($type,$task_code);
        }else{
            $b = ['不用移动'];
        }
        return ['code'=>'SUCCESS','data'=>['find'=>[$find_shelf,$find],$a,$b]];
    }
    // agv回家
    public function agv_task_back($type,$task_code,$task_code_shelf,$shelf_number){
        $all = $this->list();
        $a = $this->send_agv_task_shelf($type,$task_code_shelf,$shelf_number);
        $b = $this->send_agv_task($type,$task_code);
        return ['code'=>'SUCCESS','data'=>[$a,$b]];
    }

    public function agv_back($task_code){
        // return ['code'=>'SUCCESS','data'=>123];
        $a = $this->send_agv_task(2,$task_code);
        return ['code'=>'SUCCESS','data'=>$a];
    }
    public function agv_shelf_back($task_code_shelf,$shelf_number){
        // return ['code'=>'SUCCESS','data'=>123];
        $a = $this->send_agv_task_shelf(2,$task_code_shelf,$shelf_number);
        return ['code'=>'SUCCESS','data'=>$a];
    }
     public function agv_shelf($task_code_shelf,$shelf_number){
        // return ['code'=>'SUCCESS','data'=>123];
        $a = $this->send_agv_task_shelf(1,$task_code_shelf,$shelf_number);
        return ['code'=>'SUCCESS','data'=>$a];
    }

    // 机械臂
    public function send_robot_arm($layer=1,$num=1){
        // return ['code'=>'SUCCESS','data'=>123];
        $api_url = '/wcs/api/outer/rest/deviceControl';
        $hik = new HikApi('https://192.168.31.84');
        $data = [
            'data'=>[
                ["deviceIndex" => "702",
			    "deviceType" => "cargo",
			    "controlMsg" => "sendData",
                "controlMethod" => "write",
                "customParam" => json_encode([
                    "[data1]" => $layer,
                    "[data2]" => $num
                ])
                ]
            ]
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // CTU任务下发
    public function send_ctu_task($type,$task_code,$container_code,$code){
        $all = $this->list();
        $out_site = $all['ctu_out_site'];  
        $in_site = $all['ctu_in_site'];
        // $bind_status = $this->bind_container($container_code,$in_site,$container_type,'BIND');
        // 1 入库
        $targetRoute = [
            [
                "type" => "CARRIER",
                "code" => $container_code,
                "operation" => "COLLECT",
            ],
            [
                "type" => "STORAGE",
                "code" => $code,
                "operation" => "DELIVERY",
            ],
        ];
        
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $data = [];
        $data['taskType'] = "PF-CTU-COMMON";
        $data['targetRoute'] = $targetRoute;
        $data['robotType'] = "ROBOTS";
        $data['robotCode'] = [$all['ctu_robot']];
        $data['robotTaskCode'] = $task_code;
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }
    
    // 查询料箱状态
    public function get_container_status($code=''){
        $api_url = '/api/robot/controller/carrier/query';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $data = [
            'carrierCode' => $code,
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // 绑定料箱
    public function bind_container($code='',$sitcode='',$type=1,$bind='BIND'){
        // return ['code'=>'SUCCESS','data'=>"$bind-料箱-$code",'message'=>'操作成功'];
        $api_url = '/api/robot/controller/site/bind';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $data = [
            'slotCategory' => 'BIN',
            'slotCode' => $sitcode,
            'carrierCode' => $code,
            'carrierType' => $type,
            'invoke' => $bind,
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    public function agv_rotate($type,$task_code,$a){
        // return ['code'=>'SUCCESS','data'=>"aaaaa--routate"];
        if($a == 1){
            $task_type1 = 'CSCS-4R90';
            $task_type2 = 'CSCS-3R90';
        }else{
            $task_type1 = 'CSCS-4R-90';
            $task_type2 = 'CSCS-3R-90';
        }
        $taskType = $type == 1 ? $task_type1 : $task_type2;
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $all = $this->list();
        $targetRoute = [
            [
                "seq" => 0,
                "type" => "SITE",
                "code" => $type == 1 ? '0012040XX0010890' : '0007040XX0010890',
                "operation" => "COLLECT",
                "autoStart" => 1,
            ],
            [
                "seq" => 1,
                "type" => "SITE",
                "code" => $type == 1 ? '0011340XX0010890' : '0007400XX0010890',
                "operation" => "DELIVERY",
                "autoStart" => 1,
            ],

        ];
        $data = [];
        $data['taskType'] = $taskType;
        $data['initPriority'] = 50;
        $data['interrupt'] = 0;
        $data['targetRoute'] = $targetRoute;
        // $data['robotType'] = "GROUPS";
        // $data['robotCode'] = $all['agv_robot'];
        if($task_code && !empty($task_code)){
            $data['robotTaskCode'] = $task_code;
        }
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // 区域暂停
    public function stop_area($type){
        $api_url = '/api/robot/controller/zone/pause';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $data = [
            'zoneCode' => '001',
            'invoke' => $type == 1 ? 'FREEZE' : 'RUN',
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }
    // 机械臂暂停
    public function stop_robot_arm($type){
        //机械臂暂停：1    机械臂继续：0
        $api_url = '/wcs/api/outer/rest/deviceControl';
        $hik = new HikApi('https://192.168.31.84');
        $data = [
            'data'=>[
                ["deviceIndex" => "701",
			    "deviceType" => "cargo",
			    "controlMsg" => "sendData",
                "controlMethod" => "write",
                "customParam" => json_encode([
                    "[data100]" => $type,
                ])
                ]
            ]
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }
    // 机械臂回复
    public function robot_arm_reply(){
        // return ['code'=>'SUCCESS','data'=>"123",'message'=>'操作成功'];
        $api_url = '/wcs/api/outer/rest/deviceControl';
        $hik = new HikApi('https://192.168.31.84');
        $data = [
            'data'=>[
                ["deviceIndex" => "702",
			    "deviceType" => "cargo",
			    "controlMsg" => "sendData",
                "controlMethod" => "write",
                "customParam" => json_encode([
                    "[data1]" => 5,
                    "[data2]" => 5
                ])
                ]
            ]
        ];
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }

    // 3层货架移动到机械臂旁
    // 3层移动到工作点
    public function move_agv_three($type){
        $find = $this->get_container_status('000010');
        // 1 机械臂  2 工作站
        if($type == 1 && $find['data']['siteCode'] == '0007040XX0010890'){
            return ['code'=>'failed','message'=>'已在暂停点'];
        }
        if($type == 2 && $find['data']['siteCode'] == '0003440XX0005000'){
            return ['code'=>'failed','message'=>'已在工作站'];
        }
        $all = $this->list();
        $to_site = $type == 1 ? $all['agv_move_site'] : $all['agv_site'];
        $res = $this->send_agv_move($find['data']['siteCode'],$to_site);
        return $res;
        // 'agv_site' => '0003440XX0005000', //agv工作站
        // 'agv_move_site' => '0007040XX0010890', // agv移动货架机械臂点
        
    }
    // 4号货架移动
    public function move_agv_four_shelf($type){
        $find = $this->get_container_status('000004');
        // 1 储位  2 暂停点
        if($type == 1 && $find['data']['siteCode'] == '0006996XX0023070'){
            return ['code'=>'failed','message'=>'已在储位'];
        }
        if($type == 2 && $find['data']['siteCode'] == '0008546XX0021060'){
            return ['code'=>'failed','message'=>'已在暂停点'];
        }
        $to_site = $type == 1 ? '0006996XX0023070' : '0008546XX0021060';
        $res = $this->send_agv_move($find['data']['siteCode'],$to_site);
        return $res;
    } 
    public function send_agv_move($site,$to_site){
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $all = $this->list();
        $targetRoute = [
            [
                "type" => "SITE",
                "code" => $site,
                "operation" => "COLLECT",        
            ],
            [
                "type" => "SITE",
                "code" => $to_site,
                    // 行为   COLLECT 取货  DELIVERY 送货 ROTATE 旋转
                "operation" => "DELIVERY",        
            ],
            
        ];
        $data = [];
        $data['taskType'] = "PF-LMR-COMMON";
        $data['targetRoute'] = $targetRoute;
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;   
    }
    // 3层货架工作点旋转
    public function rotate_three($type){
        $find = $this->get_container_status('000010');
        if($find['data']['siteCode'] != '0003440XX0005000'){
            return ['code'=>'failed','message'=>'位置错误，无法旋转，请移动到工作点'];
        }
        $taskType = $type == 1 ? 'CSCS-3R90-work' : 'CSCS-3R-90-work';
        $api_url = '/api/robot/controller/task/submit';
        $hik = new HikApi($this->api_ip,$this->api_url_before);
        $all = $this->list();
        $targetRoute = [
            [
                "seq" => 0,
                "type" => "SITE",
                "code" => '0003440XX0005000',
                "operation" => "COLLECT",
                "autoStart" => 1,
            ],
            [
                "seq" => 1,
                "type" => "SITE",
                "code" => '0003440XX0005420',
                "operation" => "DELIVERY",
                "autoStart" => 1,
            ],

        ];
        $data = [];
        $data['taskType'] = $taskType;
        $data['initPriority'] = 50;
        $data['interrupt'] = 0;
        $data['targetRoute'] = $targetRoute;
        $res = $hik->hik_rcs_query($api_url,$data);
        return $res;
    }
    // 检测三层货架位置
    public function check_three_status(){
        // return ['code'=>'1','data'=>"123",'message'=>'操作成功'];
        $find = $this->get_container_status('000010');
        $find2 = $this->get_container_status('000004');
        // 位置
        if($find['data']['siteCode'] != '0003440XX0005000'){
            return ['code'=>'0','message'=>'3层货架位置错误，无法进行任务，请移动到工作点'];
        }
        if($find2['data']['siteCode'] != '0006996XX0023070'){
            return ['code'=>'0','message'=>'4号货架位置错误，无法进行任务，请移动到储位'];
        }
        
        // 检测4层货架位置

        return ['code'=>'1','message'=>'成功'];
    }
    public function check_three_status_outbound(){
        // return ['code'=>'1','data'=>"123",'message'=>'操作成功'];
        $find = $this->get_container_status('000010');
        $find2 = $this->get_container_status('000004');
        // 位置
        // if($find['data']['siteCode'] != '0003440XX0005000'){
        //     return ['code'=>'0','message'=>'3层货架位置错误，无法进行任务，请移动到工作点'];
        // }
        if($find2['data']['siteCode'] != '0006996XX0023070'){
            return ['code'=>'0','message'=>'4号货架位置错误，无法进行任务，请移动到储位'];
        }
        
        // 检测4层货架位置

        return ['code'=>'1','message'=>'成功'];
    }

}