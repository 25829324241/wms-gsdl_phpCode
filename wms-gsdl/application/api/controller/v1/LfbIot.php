<?php
//量飞变iot接口通讯

namespace app\api\controller\v1;


use app\common\model\Channel as ChannelModel;
use app\common\model\IotConfig as IotConfigModel;
use app\common\model\IotTask as IotTaskModel;
use app\common\model\WarehouseContainerMove as WarehouseContainerMoveModel;//容器移动日志
use app\common\model\WarehouseContainer as WarehouseContainerModel;//容器
use app\common\model\WarehouseContainerItem as WarehouseContainerItemModel;//容器关联物料
use app\common\model\WarehouseLocation as WarehouseLocationModel; //库位
use app\common\model\WorkbenchBit as WorkbenchBitModel; //工作位
use app\common\model\Workbench as WorkbenchModel; //工作站
use app\common\model\PickWallBit as PickWallBitModel; //播种墙工作位
use app\common\model\WorkbenchBitScan as WorkbenchBitScanModel; //工作位和扫码枪关联-暂时无用




use think\Config;
use think\Db;
use think\Exception;

class LfbIot extends Base
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    protected $iot_api_url ='';//Lot通讯地址
    protected $lfb_key='';//Lot通讯key


    protected $iot_config=[];//iot配置

    //默认参数
    protected $container_type = 'CT_KUBOT_STANDARD';



    //不需要key的来源控制器
    protected $no_key_action =[
        'callback_task',
        'callback_code_scan',//扫码枪回调函数
        'bz_callback_task',//播种墙任务回调
        'bz_set',//播种墙设置
    ];

    public function _initialize()
    {

        parent::_initialize();
        $url1 = isset($_SERVER["REQUEST_URI"])?$_SERVER["REQUEST_URI"]:'';
        $url = isset($_SERVER['SCRIPT_NAME'])?$_SERVER['SCRIPT_NAME']:'';
        write_log('LOT:'.$url.'|'.$url1.'|'.request()->action().'|'.request()->controller().'|'.request()->method().'|'.request()->ip(),'api_req_url_');
        //var_dump(in_array($url,$this->whitelist_url));
        if(!in_array(request()->action(),$this->no_key_action) && !in_array($url,$this->whitelist_url) && !in_array($url1,$this->whitelist_url)) {
            $this->h_check();
        }
        //write_log('777','api_req_url_');
        $this->iot_config = $this->get_iot_config();
        $this->iot_api_url = $this->iot_config['api_url'];
        $this->lfb_key = $this->iot_config['lfb_key'];
    }

    //容器操作
    public function post_container_task($task_type='',$task_code='',$container_code='',$to_location_code='',$from_location_code='',$container_type='',$storage_tag='',$location_code='',$to_station_code='',$ret_type='api'){
        //write_log($task_type,'api_req_url1_');
        $task_type = $task_type ?$task_type : $this->_get_param('task_type');

        //$task_code = 'task_'.$task_type.'_'.$this->getMillisecond();
        $task_code = $task_code?$task_code:$this->_get_param('task_code');
        $from_location_code = $from_location_code?$from_location_code:$this->_get_param('from_location_code','');

        $data =[];
        switch ($task_type){
            case 'tote_outbound': //出库
                $container_code = $container_code?$container_code:$this->_get_param('container_code');
                $to_location_code = $to_location_code?$to_location_code:$this->_get_param('to_location_code');
                //$to_station_code = $to_station_code?$to_station_code:$this->_get_param('to_station_code','CS01');

                //发送命令
                $data =[
                    'h_q_id'=>'5',//下发任务
                    'task_code'=>$task_code,//任务编号
                    'container_code'=>$container_code,//容器编号
                    //'to_station_code'=>$to_station_code,//目标工作站
                    'to_location_code'=>$to_location_code,//目标工作位置
                ];
                //$from_location_code ;//起始工作位
                if(!empty($from_location_code)){
                    $data['from_location_code'] =$from_location_code;
                }
                break;
            case 'tote_inbound': //入库
                $container_code = $container_code?$container_code:$this->_get_param('container_code','');
                $container_type = $container_type?$container_type:$this->_get_param('container_type',$this->container_type);
                $storage_tag = $storage_tag?$storage_tag:$this->_get_param('storage_tag','');
                $location_code = $location_code?$location_code:$this->_get_param('location_code');
                $data =[
                    'h_q_id'=>'5',//下发任务
                    'task_code'=>$task_code,//任务编号
                    'container_code'=>$container_code,
                    'container_type'=>$container_type,
                    'storage_tag'=>$storage_tag,
                    'location_code'=>$location_code,
                ];
                //$from_location_code ;//起始工作位
                if(!empty($from_location_code)){
                    $data['from_location_code'] =$from_location_code;
                }

                break;
            case 'tote_relocation': //移库

                break;
            case 'tote_check': //盘库

                break;

        }
        $data['lfb_key'] = $this->iot_config['lfb_key'];
        //$data['task_type'] = $this->iot_config['task_type'];
        $data['task_type'] = $task_type;
        $ret = go_curl($this->iot_api_url.'/hai/get_hai_q',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'post_container_task_ret_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            $ret['data']['task_code'] = $task_code;//添加任务号返回
            if($ret_type=='api'){
                $this->success('ok',$ret['data']);
            }else{
                return $ret;
            }


        }else{
            write_log(var_export($ret,true),'post_container_task_err_');
            if($ret_type=='api'){
                $this->error('off');
            }else{
                return false;
            }

        }



    }


    /*//任务回调
    public function callback_task(){

        $notifyParams = input();
        $req_data = file_get_contents("php://input");

        write_log(var_export($notifyParams,true),'callback_task_arr_');
        write_log(var_export($req_data,true),'callback_task_1');

        if(!empty($notifyParams['taskCode']) && !empty($content['status'])){
            //获取任务
            $task = IotTaskModel::where(['task_code'=>$notifyParams['taskCode'],'status'=>2])->find(); //执行中任务
            if(!empty($task)){

            $task_data =[];
            //更新计划任务
            switch ($notifyParams['status']){

                case 'fail'://错误
                    $task_data =[
                        'status'=>4,
                        'up_time'=>time(),
                    ];
                    break;
                case 'suspend': //暂停
                    $task_data =[
                        'status'=>4,
                        'up_time'=>time(),
                    ];
                    break;
                case 'success': //成功
                    $task_data =[
                        'status'=>3,
                        'up_time'=>time(),
                    ];

                    //取库位信息
                    $location = WarehouseLocationModel::where(['real_location'=>$task['to_location_code'],'status'=>'2'])->find();//出入库中
                    if(!empty($location)){
                        $container =  WarehouseContainerModel::where([
                            'numbering'=>$task['container_code'],
                        ])->find();

                        //释放库位状态
                        if(!empty($container)){
                            //
                            Db::startTrans();
                            try{
                                WarehouseLocationModel::update([
                                    'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                                    'up_time'=>time(),
                                ],[
                                    'id'=>$container['wh_l_id'],
                                ]);

                                //工作位变更
                                $w_b = WorkbenchBitModel::where(['real_location'=>$task['to_location_code']])->find();
                                if(!empty($w_b)){//出库有目标工作台 入库没有
                                    //修改容器状态
                                    WarehouseContainerModel::update([
                                        'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                                        'wh_l_numbering'=>$location['numbering'],
                                        'wh_l_real_location'=>$location['real_location'],
                                        'wh_l_id'=>$location['id'],
                                        'w_id'=>$w_b['w_id'],
                                        'w_numbering'=>$w_b['w_numbering'],
                                        'w_real_station'=>$w_b['w_numbering'],//设置和工作台编号一致 有问题再改
                                        'up_time'=>time(),
                                    ],[
                                        'id'=>$container['id'],
                                    ]);
                                    //容器移动日志
                                    WarehouseContainerMoveModel::insertGetId([
                                        'wh_c_id'=>$container['id'],
                                        'from_wh_l_id'=>$container['wh_l_id'],
                                        'from_w_id'=>$container['w_id'],
                                        'target_wh_l_id'=>$location['id'],
                                        'target_w_id'=>$w_b['w_id'],
                                        'task_id'=>$task['id'],
                                        'cid'=>$task['aid'],
                                        'up_time'=>time(),
                                    ]);
                                }else{
                                    //修改容器状态
                                    WarehouseContainerModel::update([
                                        'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                                        'wh_l_numbering'=>$location['numbering'],
                                        'wh_l_real_location'=>$location['real_location'],
                                        'wh_l_id'=>$location['id'],
                                        'up_time'=>time(),
                                    ],[
                                        'id'=>$container['id'],
                                    ]);
                                    //容器移动日志
                                    WarehouseContainerMoveModel::insertGetId([
                                        'wh_c_id'=>$container['id'],
                                        'from_wh_l_id'=>$container['wh_l_id'],
                                        'target_wh_l_id'=>$location['id'],
                                        'task_id'=>$task['id'],
                                        'cid'=>$task['aid'],
                                        'up_time'=>time(),
                                    ]);
                                }
                                Db::commit();
                                $this->error('ok');
                            }catch (Exception $e) {
                                Db::rollback();
                                write_log(var_export($e->getMessage(),true),'callback_task_err_');
                                $this->error('任务回调 容器库位 出库状态 更新失败');
                            }

                        }




                    }
                    //关联播种墙 需要亮灯
                    if($task['type']==2 || $task['type']==4){
                        //亮灯任务下发

                    }


                    break;
            }

            IotTaskModel::update($task_data,[
                'task_code'=>$notifyParams['taskCode'],
            ]);

            }

        }

        $this->success('ok');

    }*/

    //播种墙操作
    public function bz_set($task_code,$real_location='',$sort='',$type='',$wh_l_numbering='',$cid='',$ret_type='api'){
        $wh_l_numbering = $wh_l_numbering ?$wh_l_numbering : $this->_get_param('wh_l_numbering','');//工作位编号
        $real_location = $real_location ?$real_location : $this->_get_param('real_location');//播种墙编号
        $sort = $sort ?$sort : $this->_get_param('sort');//播种墙编号
        $cid = $cid ?$cid : $this->_get_param('cid');//项目id
        $type = $type ?$type : $this->_get_param('type','set_win_led');//执行类型 set_win_led 灯光 set_win_num2 数显

        $task_code = $task_code ?$task_code : $this->_get_param('task_code');//任务编号
        //$task_code = 'c2s'.$this->getMillisecond();

        $data =[
            'type'=>$type,
            'index'=>$sort,
            'dev_code'=>$real_location,
            'task_code'=>$task_code,
        ];
        if($type=='set_win_led'){
            $data['rgb2'] = 20;//亮灯
        }
        $info = IotConfigModel::where(['cid'=>$cid,'status'=>'1'])->find();
        //$data['lfb_key'] = $this->iot_config['lfb_key'];
        $data['lfb_key'] = $info['lfb_key'];

        $ret = go_curl($this->iot_api_url.'/sow/bz_out_info',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'bz_set_ret_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            $ret['data']['task_code'] = $task_code;//添加任务号返回
            if($ret_type=='api'){
                $this->success('ok',$ret['data']);
            }else{
                return $ret;
            }
        }else{
            write_log(var_export($ret,true),'bz_set_err_');
            if($ret_type=='api'){
                $this->error('off');
            }else{
                return false;
            }
        }




    }

    //播种墙按键回调
    /*public function bz_callback_task(){
        //入库

        $this->success('ok');
    }*/

    //扫码枪回调函数
    public function callback_code_scan(){

    }


//工作位查询
    public function query_location($location_codes='',$container_codes='',$direction='',$cid='',$aid='',$ret_type='api'){

        $location_codes = $location_codes ?$location_codes : $this->_get_param('location_codes','');//工作位编号
        $container_codes = $container_codes ?$container_codes : $this->_get_param('container_codes','');//工作位编号
        $direction = $direction ?$direction : $this->_get_param('direction','');//工作位编号
        $cid =$cid?$cid: $this->_get_param('cid'); //容器编号
        $aid =$aid?$aid: $this->_get_param('aid',''); //管理员id

        $data =[
            'location_codes'=>$location_codes,
            'container_codes'=>$container_codes,
            'direction'=>$direction,
            'lfb_key'=>$this->lfb_key,
            'h_q_id'=>3,
        ];

        $data['lfb_key'] = $this->iot_config['lfb_key'];
        $ret = go_curl($this->iot_api_url.'/hai/get_hai_q',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'query_location_ret_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            foreach ($ret['data']['locations'] as $val1){

                if(!empty($val1['loadContainerCode'])){

                    WarehouseContainerModel::update([
                        'wh_l_numbering'=>$val1['locationCode'],
                        'wh_l_real_location'=>$val1['locationCode'],
                    ],[
                        'numbering'=>$val1['loadContainerCode'],
                    ]);
                    $status =2; //1 可用 2 使用中 3 锁定
                }else{

                    $w_c = WarehouseContainerModel::where([
                        'wh_l_numbering'=>$val1['locationCode'],
                        'wh_l_real_location'=>$val1['locationCode']
                    ])->find();
                    if(!empty($w_c)){
                        WarehouseContainerModel::update([
                            'wh_l_numbering'=>'',
                            'wh_l_real_location'=>'',
                        ],[
                            'id'=>$w_c['id'],

                        ]);
                    }

                    $status=1;
                }

                if($val1['isLocked']==true){
                    $status=3;
                }

                WarehouseLocationModel::update([
                    'status'=>$status,
                ],[
                    'real_location'=>$val1['locationCode']
                ]);
                /*WorkbenchBitModel::update([
                    'status'=>$status,
                ],[
                    'real_location'=>$val1['locationCode']
                ]);*/

            }
            if($ret_type=='api'){
                $this->success('ok',$ret['data']['locations']);
            }else{
                return $ret['data']['locations'];
            }


        }

        if($ret_type=='api'){
            $this->error('off');
        }else{
            return false;
        }



    }


    //获取容器/列表
    public function get_container(){


        $get_type = $this->_get_param('get_type','list'); //single 单个 list 列表
        $numbering = $this->_get_param('numbering',''); //容器编号
        $wh_l_id= $this->_get_param('wh_l_id',''); //库位id
        $wh_l_numbering = $this->_get_param('wh_l_numbering',''); //库位编号
        $wh_l_real_location = $this->_get_param('wh_l_real_location',''); //容器绑定编号
        $w_id = $this->_get_param('w_id',''); //工作台id
        $w_numbering= $this->_get_param('w_numbering',''); //工作台编号
        $w_real_station = $this->_get_param('w_real_station',''); //工作台绑定编号
        $status = $this->_get_param('status',''); //1=创建,2=可用,3=锁定,4=废弃

        $where =[];

        if(!empty($numbering)){
            $where['numbering']=$numbering;
        }




    }
    //容器添加
    public function add_container($container_code='',$container_type='',$cid='',$aid=''){
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器编号
        $cid =$cid?$cid: $this->_get_param('cid'); //容器编号
        $aid =$aid?$aid: $this->_get_param('aid'); //管理员id
        $container_type =$container_type?$container_type: $this->_get_param('container_type',$this->container_type); //容器类型

        $data =[
            'container_code'=>$container_code,
            'container_type_code'=>$container_type,
            'lfb_key'=>$this->lfb_key,
            'cid'=>$cid,
        ];

        $ret = go_curl($this->iot_api_url.'/hai/ess_api_add_container',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'add_container_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            //添加容器
            WarehouseContainerModel::insertGetId([
                'name'=>$container_code,
                'numbering'=>$container_code,
                'cid'=>$cid,
                'wh_l_id'=>0,
                'status'=>2,
                /*'w_numbering'=>'LA_SHELF_STORAGE',
                'w_real_station'=>'LA_SHELF_STORAGE',*/
                'w_id'=>0,
                'aid'=>$aid,
                'add_time'=>time(),
            ]);
            $this->success('ok',$ret['data']);
        }else{
            $this->error('off');
        }



    }
    //容器删除
    public function remove_container($container_code='',$cid=''){
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器编号
        $cid =$cid?$cid: $this->_get_param('cid'); //容器编号

        $data =[
            'container_code'=>$container_code,
            'lfb_key'=>$this->lfb_key,
            'cid'=>$cid,
        ];

        $ret = go_curl($this->iot_api_url.'/hai/ess_api_remove_container',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'remove_container_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            //删除容器
            WarehouseContainerModel::where([
               'numbering'=>$container_code,
               'cid'=>$cid,
            ])->delete();
            /*HaiContainerModel::where([
                'numbering'=>$container_code,
            ])->delete();*/

            $this->success('ok',$ret['data']);
        }else{
            $this->error('off');
        }
    }

    //容器绑定
    public function move_in_container($container_code='',$location_code='',$cid='',$aid='',$ret_type='api'){
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器编号
        $location_code = $location_code?$location_code:$this->_get_param('location_code'); //库位编码
        $cid =$cid?$cid: $this->_get_param('cid'); //容器编号
        $aid =$aid?$aid: $this->_get_param('aid'); //管理员id

        $data =[
            'container_code'=>$container_code,
            'location_code'=>$location_code,
            'lfb_key'=>$this->lfb_key,
            'cid'=>$cid,
        ];

        $ret = go_curl($this->iot_api_url.'/hai/ess_api_move_in_container',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'move_in_container_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            //容器绑定库位
            //库位
            $location = WarehouseLocationModel::where(['real_location'=>$location_code])->find();
            //工作站
            $w_b = WorkbenchBitModel::where(['real_location'=>$location_code])->find();
            if(!empty($location)){
                WarehouseContainerModel::update([
                     'wh_l_numbering'=>$location_code,
                     'wh_l_real_location'=>$location_code,
                     'wh_l_id'=>$location['id'],
                     'w_id'=>$w_b['w_id'],
                    'up_aid'=>$aid,
                    'up_time'=>time(),
            ],[
                    'numbering'=>$container_code,
                    'cid'=>$cid,
                ]);
                //库位状态改变
                WarehouseLocationModel::update([
                    'status'=>2,//锁定
                    'up_aid'=>$aid,
                    'up_time'=>time(),
                ],[
                    'real_location'=>$location_code,
                    'cid'=>$cid,
                ]);
            }

            if($ret_type=='api'){
                $this->success('ok',$ret['data']);
            }else{
                return $ret;
            }
            //$this->success('ok',$ret['data']);
        }else{
            if($ret_type=='api'){
                $this->error('off');
            }else{
                return false;
            }

        }
    }

    //容器移除
    public function move_out_container($container_code='',$location_code='',$cid='',$aid='',$ret_type='api'){
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器编号
        $location_code = $location_code?$location_code:$this->_get_param('location_code'); //库位编码
        $cid =$cid?$cid: $this->_get_param('cid'); //容器编号
        $aid =$aid?$aid: $this->_get_param('aid'); //管理员id

        $data =[
            'container_code'=>$container_code,
            'location_code'=>$location_code,
            'lfb_key'=>$this->lfb_key,
            'cid'=>$cid,
        ];

        $ret = go_curl($this->iot_api_url.'/hai/ess_api_move_out_container',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'move_out_container_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            WarehouseContainerModel::update([
                'wh_l_numbering'=>'',
                'wh_l_real_location'=>'',
                'wh_l_id'=>0,
                'w_id'=>0,
                'up_aid'=>$aid,
                'up_time'=>time(),
            ],[
                'numbering'=>$container_code,
                'cid'=>$cid,
            ]);
            //库位状态改变
            WarehouseLocationModel::update([
                'status'=>1,//可用
                'up_aid'=>$aid,
                'up_time'=>time(),
            ],[
                'real_location'=>$location_code,
                'cid'=>$cid,
            ]);

            if($ret_type=='api'){
                $this->success('ok',$ret['data']);
            }else{
                return $ret;
            }
            //$this->success('ok',$ret['data']);
        }else{
            if($ret_type=='api'){
                $this->error('off');
            }else{
                return false;
            }
        }
    }

    //容器查询
    public function get_container_info($container_code='',$container_types='',$cid='',$aid='',$ret_type='api'){
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器编号
        $container_types = $container_types?$container_types:$this->_get_param('container_types',''); //容器类型
        $cid =$cid?$cid: $this->_get_param('cid'); //项目编码
        $aid =$aid?$aid: $this->_get_param('aid'); //管理员id

        $data =[
            'h_q_id'=>10,//容器查询
            'container_codes'=>$container_code,//容器列表
            'container_types'=>$container_types,//获取机器人列表
        ];


        $data['lfb_key'] = $this->iot_config['lfb_key'];
        $ret = go_curl($this->iot_api_url.'/hai/get_hai_q',"POST",$data);
        $ret = json_decode($ret,true);
        write_log(var_export($ret,true),'get_container_info_ret_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='1'){
            // $ret['data']['task_code'] = $task_code;//添加任务号返回

            foreach ($ret['data']['containers'] as $val1){

                if(!empty($val1['positionCode'])){

                    WarehouseContainerModel::update(
                        [
                            'wh_l_numbering'=>$val1['positionCode'],
                            'wh_l_real_location'=>$val1['positionCode'],
                            'up_time'=>time(),
                        ],[
                            'numbering'=>$val1['containerCode'],
                        ]
                    );
                }

            }

            if($ret_type=='api'){
                $this->success('ok',$ret['data']['containers'] );
            }else{
                return $ret;
            }
        }else{
            write_log(var_export($ret,true),'get_container_info_err_');
            if($ret_type=='api'){
                $this->error('off');
            }else{
                return false;
            }

        }


    }
}