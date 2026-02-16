<?php
//海柔api
namespace app\api\controller\v1;

use app\common\model\ExecuteTaskReturnLog as ExecuteTaskReturnLogModel;
use app\common\model\HaiRobot as HaiRobotModel;//海柔机器人
use app\common\model\Project as ProjectModel;//项目关联
use app\common\model\HaiLocation as HaiLocationModel;//工作位
use app\common\model\HaiStation as HaiStationModel;//工作站
use app\common\model\HaiContainer as HaiContainerModel;//容器

use app\common\model\ExecuteTask as ExecuteTaskModel; //任务

use iot\Hai as HaiApi;
use think\Cache;
use think\Log;

class Hai extends Base
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    protected $server_url;

    protected $project_key = '';//项目通讯key
    protected $hai_haiq_url = '';//haiq通讯地址
    protected $hai_ess_url = '';//ess通讯地址
    protected $project = [];//项目信息

    //不需要key的控制器
    protected $no_key_action =[
        'callback_hai_q'
    ];

    //任务类型
    protected $sys_task_type =[
        'tote_outbound'=>'carry',//出库
        'tote_inbound'=>'tote_inbound',//入库
        'tote_relocation'=>'carry',//移库
        'tote_check'=>'tote_check',//盘库
        'tote_carry'=>'tote_carry',//搬运
        'tote_action'=>'tote_action',//指令集
    ];

    public function _initialize()
    {

        parent::_initialize();
        //write_log(var_export(request()->action(),true),'model_');

        if(!empty($_SERVER['SERVER_NAME'])){
            $this->server_url = $_SERVER['SERVER_NAME']?"https://".$_SERVER['SERVER_NAME']:"http://".$_SERVER['HTTP_HOST'];
        }else{
            //计划任务兼容
            $this->server_url ='http://127.0.0.1/';
        }

        if(!in_array(request()->action(),$this->no_key_action)){
            /*//获取项目信息
            $key = $this->_get_param('lfb_key');

            if(empty(Cache::get('project_'.$key))) {
                $info =  $p_info = ProjectModel::where(['key'=>$key,'status'=>1])->find();
                if(!empty($info)){
                    Cache::set('project_'.$key,$info,$this->base_cache_time_day);//缓存1天
                }else{
                    $this->error('key off');
                }

            }
            $project = Cache::get('project_'.$key);
            if(empty($project)){
                $this->error('project off');
            }*/
            $this->project = $this->get_project();
            //write_log(var_export($project,true),'project_');
            $this->hai_haiq_url = $this->project['hai_haiq_url'];
            $this->hai_ess_url = $this->project['hai_ess_api_url'];

        }




    }


    //hai_q接口操作
    public function get_hai_q(){

        $h_q_url = $this->_get_param('h_q_url','');
        $h_q_id = $this->_get_param('h_q_id','7'); // 1容器到达 2容器流动 3工作位查询 4工作站查询 5下发任务 6任务取消 7机器人查询

        $task_code = $this->_get_param('task_code','');
        $is_timing = $this->_get_param('is_timing','2');
        $sort = $this->_get_param('sort','1');
        $order = $this->_get_param('order','99');

        if(empty($task_code)){
            $task_code = 'hai_q_task_'.$h_q_id.'_'.time();
        }

        $url = $this->hai_haiq_url;
        $hai = new HaiApi($url);
        $data =[];


            $post_data = $this->hai_q_select_api_url($h_q_id);
           // $data = $post_data['data'];
            $data = array_filter($post_data['data']);//删除空数组 未测试
            write_log(var_export(json_encode($data,true),true),'get_hai_q_filter_data_');
            $h_q_url = $post_data['url'];



        $ret = $hai->hai_q_query($h_q_url,$data);



        if(!empty($ret) && $ret['code']=='0'){

            //写任务日志
            $this->e_task_add($this->project['id'],$task_code,1,1,$url.$h_q_url,$data,$data,$is_timing,$order,$sort);


            $this->success($ret['msg'],$ret['data']);
        }else{
            write_log(var_export($ret,true),'get_hai_q_err');
            //写任务日志
            $this->e_task_add($this->project['id'],$task_code,1,1,$url.$h_q_url,$data,$data,$is_timing,$order,$sort,2);

            if(!empty($ret['msg'])){
                $this->error($ret['msg']);
            }else{
                $this->error('err?');
            }

        }

    }

    //命令选择
    public function hai_q_select_api_url($id){

        /**
         * conveyor/containerArrived 容器到达 1
         * conveyor/moveContainer 容器流动 2
         * location/query 工作位查询 3
         * station/query 工作站查询 4
         * task/create 下发任务 5
         * task/cancel 任务取消 6
         * robot/query 机器人查询 7
         *
         */
        $ret =[];
        switch ($id){
            case '1'://容器到达


                $ret =[
                    'data'=>[
                        'slotCode'=>$this->_get_param('slot_code',''), //库位编码
                        'containerCode'=>$this->_get_param('container_code',''),//容器编码
                    ],
                    'url'=>'/conveyor/containerArrived',
                ];
                break;
            case '2'://容器流动
                $ret =[
                    'data'=>[
                        'slotCode'=>$this->_get_param('slot_code',''), //库位编码
                        'containerCode'=>$this->_get_param('container_code',''),//容器编码
                        'direction'=>$this->_get_param('direction',''),//流动方向
                    ],
                    'url'=>'/conveyor/moveContainer',
                ];
                break;
            case '3'://工作位查询

                $location_codes =$this->_get_param('location_codes','');
                if(!empty($location_codes)){
                    $location_codes_arr = explode("|",$location_codes);
                }else{
                    $location_codes_arr =[];
                }

                $direction = $this->_get_param('direction','');
                if(!empty($direction)){
                    $direction_arr = explode("|",$direction);
                }else{
                    $direction_arr =[];
                }

                $ret =[
                    'data'=>[
                        'locationCodes'=>$location_codes_arr, //工作位数组
                        'containerCode'=>$this->_get_param('container_code',''),//容器编码
                        'locationTypeCodes'=>$direction_arr,//工作位类型数组
                    ],
                    'url'=>'/location/query',
                ];
                break;
            case '4'://工作站查询

                $station_codes =$this->_get_param('station_codes','');
                if(!empty($station_codes)){
                    $station_codes_arr = explode("|",$station_codes);
                }else{
                    $station_codes_arr =[];
                }

                $station_model_codes = $this->_get_param('station_model_codes','');
                if(!empty($station_model_codes)){
                    $station_model_codes_arr = explode("|",$station_model_codes);
                }else{
                    $station_model_codes_arr =[];
                }

                $ret =[
                    'data'=>[
                        'stationCodes'=>$station_codes_arr, //工作站编码数组
                        'stationModelCodes'=>$station_model_codes_arr,//工作站类型数组
                    ],
                    'url'=>'/station/query',
                ];

                break;
            case '5'://下发任务

                $task_type = $this->_get_param('task_type');
                write_log(var_export($task_type,true),'task_type_');
                $tasks_list = [];
                switch ($task_type){
                    case 'tote_outbound'://容器出库


                        $tmp_data = [
                            'taskCode'=>$this->_get_param('task_code'),//任务编号
                            'taskDescribe'=>[
                                //'deadline'=>$this->_get_param('deadline',''),//时间戳，精确到毫秒（ms）。若临近截单时间 N（N 可配置），则任务优先级会提高，优先执行
                                'containerCode'=>$this->_get_param('container_code'),//容器编号
                                //'fromLocationCode'=>$this->_get_param('from_location_code',''),//起始工作位
                                //'toStationCode'=>$from_location_list,//目标工作站 list 可多个
                                'containerType'=>$this->_get_param('container_type','CT_KUBOT_STANDARD'),
                                'toLocationCode'=>$this->_get_param('to_location_code',''),//目标工作位置  目前用于缓存货架出库场景，优先级比toStationCode 高
                            ],

                        ];

                        $deadline = $this->_get_param('deadline','');
                        if(!empty($deadline)){
                            $tmp_data['taskDescribe']['deadline'] = $deadline;
                        }

                        $from_location_code = $this->_get_param('from_location_code','');
                        if(!empty($from_location_code)){
                            $tmp_data['taskDescribe']['fromLocationCode'] = $from_location_code;
                        }

                        $to_station_code_list = [];
                        $to_station_code = $this->_get_param('to_station_code','CS01');
                        if(!empty($to_station_code)){
                            /* $to_station_code_arr = explode(",",$to_station_code);
                             if(!empty($to_station_code_arr) && is_array($to_station_code_arr)){
                                 foreach ($to_station_code_arr as $val){
                                     $to_station_code_list[]=$val;
                                 }
                             }
                             $tmp_data['taskDescribe']['toStationCode'] = $to_station_code_list;*/
                            $tmp_data['taskDescribe']['toStationCode'] = $to_station_code;
                        }

                        $tasks_list[] =$tmp_data;


                        break;
                    case 'tote_relocation'://容器移库
                        $tasks_list[] =[
                            'taskCode'=>$this->_get_param('task_code'),//任务编号
                            'taskDescribe'=>[
                                'containerCode'=>$this->_get_param('container_code',''),//容器编号
                                'fromLocationCode'=>$this->_get_param('from_location_code',''),//起始工作位
                                'toLocationCode'=>$this->_get_param('to_location_code'),//目标工作位置  目前用于缓存货架出库场景，优先级比toStationCode 高
                            ],

                        ];

                        break;
                    case 'tote_inbound'://容器入库
                        $tasks_list[] =[
                            'taskCode'=>$this->_get_param('task_code'),//任务编号
                            'taskDescribe'=>[
                                'containerCode'=>$this->_get_param('container_code'),//容器编号
                                'containerType'=>$this->_get_param('container_type'),//容器型号
                                'storageTag'=>$this->_get_param('storage_tag',''),//工作位标签
                                'fromLocationCode'=>$this->_get_param('from_location_code',''),//起始工作位 适用于缓存货架场景，若填写，系统可以校验容器和库位是否匹配
                                'locationCode'=>$this->_get_param('location_code'), //目标工作位
                            ],


                        ];

                        break;
                    case 'tote_check'://容器盘库
                        $tasks_list[] =[
                            'taskCode'=>$this->_get_param('task_code'),//任务编号
                            'taskDescribe'=>[
                                'containerCode'=>$this->_get_param('container_code',''),//容器编号
                                'locationCode '=>$this->_get_param('location_code'), //目标工作位
                                'checkType'=>$this->_get_param('check_type'), //盘库类型scan weight rfid
                            ],


                        ];
                        break;
                }

                $ret =[
                    'data'=>[
                        'taskType'=>$this->sys_task_type[$task_type],//任务类型：tote_outbound：容器出库|tote_relocation：容器移库|tote_inbound：容器入库| tote_check：容器盘库
                        'businessType'=> $this->_get_param('business_type',''),//工作站类型数组
                        'taskGroupCode'=> $this->_get_param('task_group_code',''),//任务分组名称
                        'groupPriority'=>'0',//组优先级
                        'robotCode'=>$this->_get_param('robot_code',''),//指定机器人
                        'tasks'=>$tasks_list,//任务列表
                    ],
                    'url'=>'/task/create',
                ];

                break;
            case '6'://任务取消

                break;
            case '7'://机器人查询
                $ret =[
                    'data'=>[],
                    'url'=>'/robot/query',
                ];
                break;
            default: //默认
                $ret =[
                    'data'=>[],
                    'url'=>'/robot/query',
                ];
                break;

        }
        //========================特殊条件===============================================================================
        //自动创建容器
        if($id=='5'){
            //autoCreateContainer
            $task_type = $this->_get_param('task_type');
            if($task_type =='tote_inbound'){//容器入库
                $ret['data']['autoCreateContainer']='true';//自动创建容器
            }


        }
        write_log(var_export($ret,true),'hai_q_select_api_url_');
        return $ret;
    }

    //hai_q 反馈接口
    public function callback_hai_q(){

        $notifyParams = input();
        $req_data = file_get_contents("php://input");

        write_log(var_export($notifyParams,true),'callback_hai_q_arr');
        write_log(var_export($req_data,true),'callback_hai_q_1');

        //更新任务日志
        if(!empty($notifyParams['taskCode']) && !empty($notifyParams['status'])){
            //$task_status =1;
            $task_up_data =[];
            switch ($notifyParams['status']){

                case 'fail'://错误
                    $task_up_data = [
                        'status'=> '4',
                        'fail_time'=> time(),
                        'up_time'=> time(),
                        ];
                    break;
                    /*2024-03-07 16:31:28  array (
                    'taskCode' => 'task_tote_outbound_1709800245953',
                    'eventType' => 'tote_load',
                    'status' => 'success',
                    'containerCode' => 'A000004062',
                    'locationCode' => 'HAI-002-002-02',
                    'robotCode' => 'kubot-1',
                    'stationCode' => 'LA_SHELF_STORAGE',
                    'cbt' => 'task',
                )
2024-03-07 16:31:45  array (
                    'taskCode' => 'task_tote_outbound_1709800245953',
                    'eventType' => 'task',
                    'status' => 'fail',
                    'containerCode' => 'A000004062',
                    'locationCode' => 'CS-001-01-02',
                    'robotCode' => 'kubot-1',
                    'stationCode' => '',
                    'message' => '',
                    'sysTaskCode' => '',
                    'cbt' => 'task',
                )
2024-03-07 16:31:45  array (
                    'taskCode' => 'task_tote_outbound_1709800245953',
                    'eventType' => 'task',
                    'status' => 'suspend',
                    'containerCode' => 'A000004062',
                    'locationCode' => 'CS-001-01-02',
                    'robotCode' => 'kubot-1',
                    'stationCode' => 'CS-001',
                    'message' => '',
                    'sysTaskCode' => '',
                    'cbt' => 'task',
                )*/
                case 'suspend': //暂停
                    $task_up_data = [
                        'status'=> '3', //机器停了 也成功放好货物了？这个也是成功？？？
                        //'fail_time'=> time(),
                        'ok_time'=> time(),
                        'up_time'=> time(),
                    ];
                    break;
                case 'success': //成功
                    $task_up_data = [
                        'status'=> '3',
                        'ok_time'=> time(),
                        'up_time'=> time(),
                    ];
                    break;
            }




            ExecuteTaskModel::update($task_up_data,[
                'task_code'=>$notifyParams['taskCode'],
            ]);

            if($notifyParams['status']=='suspend' || $notifyParams['status']=='success'){// suspend 的状态 成功了?
                $task = ExecuteTaskModel::where(['task_code'=>$notifyParams['taskCode']])->find();
                if(!empty($task)){
                    $project = ProjectModel::where(['id'=>$task['project_id'],'status'=>1])->find();
                    //写反馈日志
                    $this->ret_task_log($task['project_id'],'callback_hai_q_'.time(),$notifyParams['taskCode'],'1',$notifyParams);
                    //推送给wms
                    //$ret = go_curl($this->project['wms_ret_url'].'lfb_iot/callback_task',"POST",$notifyParams);

                    $notifyParams['cid'] = $project['wms_cid'];//推送渠道id

                    $ret = go_curl($project['wms_ret_url'].'warehouse_api/callback_task',"POST",$notifyParams);
                    $ret =json_decode($ret,true);
                    write_log(var_export($ret,true),'callback_task_ret_');
                    if(!empty($ret) && $ret['code']==1){
                        $data['push_status'] = 2;
                    }else{
                        $data['push_status'] = 3;
                    }
                    if(!empty($data)){
                        ExecuteTaskReturnLogModel::update($data,[
                            'task_code'=>$notifyParams['taskCode'],
                        ]);
                    }
                }
                
            }

        }

        $this->success('ok');


    }


    //ess-api 获取容器列表
    public function ess_api_container(){

        //http://192.168.1.105:9000/ess-api/container/query 容器列表
        /*{

                    "containerTypeCode": "CT_KUBOT_STANDARD",
          "page": 1,
          "limit": 20
        }*/
        $url = $this->hai_ess_url;
        $hai = new HaiApi('',$url);

        $page = $this->_get_param('page','1');
        $limit = $this->_get_param('limit','50');


        $data =[
            'page'=>$page,
            'limit'=>$limit,
        ];

        //"code": "string",
        //根据容器号搜索
        $container_code = $this->_get_param('container_code','');
        if(!empty($container_code)){
            $data['code']=$container_code;
        }

        $is_timing = $this->_get_param('is_timing','2');
        $sort = $this->_get_param('sort','1');
        $order = $this->_get_param('order','99');

        $task_code = $this->_get_param('task_code','');

        if(empty($task_code)){
            $task_code = 'ess_api_container_'.time();
        }


        $ret = $hai->hai_ess_query('/container/query',$data);
        //write_log(var_export($url,true),'ess_1_');
        //write_log(var_export($url,true),'ess_1_');
        if(!empty($ret) && $ret['code']=='0'){

            //添加容器
            if($ret['data']['total']=='0' && !empty($container_code)){

                $container_type_code = $this->_get_param('container_type_code','CT_KUBOT_STANDARD');
                //创建容器
                $data1['containerAdds'][] = [
                        'containerCode'=>$container_code,
                        'containerTypeCode'=>$container_type_code,
                ];
                $add_c = $hai->hai_ess_query('/container/add',$data1);
               // write_log(var_export($add_c,true),'123');

                if(!empty($add_c) && $add_c['code']=='0'){
                    $this->e_task_add($this->project['id'],$task_code,1,2,$url.'/container/add',$data1,$data1,$is_timing,$order,$sort);
                    $ret1 = $hai->hai_ess_query('/container/query',$data);
                    if(!empty($ret1) && $ret1['code']=='0'){
                        $this->success($ret1['msg'],$ret1['data']);
                    }
                }


            }

            $this->e_task_add($this->project['id'],$task_code,1,2,$url.'/container/query',$data,$data,$is_timing,$order,$sort);
            $this->success($ret['msg'],$ret['data']);
        }else{
            write_log(var_export($ret,true),'ess_api_container_');
            $this->e_task_add($this->project['id'],$task_code,1,2,$url.'/container/query',$data,$data,$is_timing,$order,$sort,2);
            if(!empty($ret['msg'])){
                $this->error($ret['msg']);
            }else{
                $this->error('err?');
            }

        }


    }

    //呼叫机器人
    public function ess_api_callRobot(){

        ///ess-api/station/callRobot


    }

    //创建容器
    public function ess_api_add_container(){
        $container_code = $this->_get_param('container_code');
        $container_type_code = $this->_get_param('container_type_code');
        $cid = $this->_get_param('cid');
        $data['containerAdds'][] =[
            'containerCode'=>$container_code,
            'containerTypeCode'=>$container_type_code,
        ];
        $url = $this->hai_ess_url;
        $hai = new HaiApi('',$url);
        $ret = $hai->hai_ess_query('/container/add',$data);
        if(!empty($ret)){
            $project = ProjectModel::where(['wms_cid'=>$cid])->find();

            HaiContainerModel::insert([
                'code'=>$container_code,
                'container_type_code'=>$container_type_code,
                'up_time'=>time(),
                'project_id'=>$project['id'],
            ]);
        }
        $this->success('ok',$ret);
    }

    //容器删除
    public function ess_api_remove_container(){
        $container_code = $this->_get_param('container_code');
        $cid = $this->_get_param('cid');
        $data['containerMoveIns'] =[$container_code];
        $url = $this->hai_ess_url;
        $hai = new HaiApi('',$url);
        $ret = $hai->hai_ess_query('/container/remove',$data);
        if(!empty($ret)){
            $project = ProjectModel::where(['wms_cid'=>$cid])->find();

            HaiContainerModel::where(['code'=>$container_code,'project_id'=>$project['id']])->delete();
        }
        $this->success('ok',$ret);
    }

    //容器绑定库位或机器人
    public function ess_api_move_in_container(){
        $container_code = $this->_get_param('container_code');
        $location_code = $this->_get_param('location_code');
        $data['containerMoveIns'][] =[
            'containerCode'=>$container_code,
            'positionCode'=>$location_code,
        ];
        $url = $this->hai_ess_url;
        $hai = new HaiApi('',$url);
        $ret = $hai->hai_ess_query('/container/moveIn',$data);
        $this->success('ok',$ret);
    }

    //容器解绑
    public function ess_api_move_out_container(){
        $container_code = $this->_get_param('container_code');
        $location_code = $this->_get_param('location_code');
        $data['containerMoveOuts'][] =[
            'containerCode'=>$container_code,
            'positionCode'=>$location_code,
        ];
        $url = $this->hai_ess_url;
        $hai = new HaiApi('',$url);
        $ret = $hai->hai_ess_query('/container/moveOut',$data);
        $this->success('ok',$ret);
    }


    //获取库位信息
    public function get_location(){

        $list = HaiLocationModel::where(['project_id'=>$this->project['id']])->select();
        $data =[];
        foreach ($list as $val){
            $val['status'] =1;//默认值
            $val['load_container_code'] ='';//默认值
            $content = json_decode($val['content'],true);
            if(!empty($content) && is_array($content)){
                if($content['isLocked']==true){
                    $val['status'] =3;
                }
                if(!empty($content['loadContainerCode'])){
                    $val['load_container_code'] =$content['loadContainerCode'];
                    $val['status'] =2;
                }

            }

            unset($val['content']);
            $data[] =$val;
        }
        $this->success('ok',$data);

    }

    //获取工作台
    public function get_station(){

        $list = HaiStationModel::where(['project_id'=>$this->project['id']])->select();
        $this->success('ok',$list);
    }

    //获取容器
    public function get_container(){


        $list = HaiContainerModel::where(['project_id'=>$this->project['id']])->select();
        $this->success('ok',$list);
    }

    //获取机器人
    public function get_robot(){
        $list = HaiRobotModel::where(['project_id'=>$this->project['id']])->select();
        $this->success('ok',$list);

    }

}