<?php
//仓管api

namespace app\api\controller\v1;

use app\admin\controller\WarehouseContainerItem;
use app\api\controller\v1\LfbIot as LfbIotController; //Lot api控制器
use app\api\controller\v1\MoveCabinets as MoveCabinetsController; //密集柜 api控制器

use app\common\model\IotTask as IotTaskModel;//iot任务
use app\common\model\PickWallBit;
use app\common\model\TallyingOrder as TallyingOrderModel;//理货订单

use app\common\model\WarehouseContainerMove as WarehouseContainerMoveModel;//容器移动日志
use app\common\model\WarehouseContainer as WarehouseContainerModel;//容器
use app\common\model\WarehouseContainerItem as WarehouseContainerItemModel;//容器关联物料
use app\common\model\WarehouseLocation as WarehouseLocationModel; //库位
use app\common\model\Workbench as WorkbenchModel; //工作台
use app\common\model\WorkbenchBit as WorkbenchBitModel; //工作台工作位
use app\common\model\ScanCodeLog as ScanCodeLogModel; //扫码日志
use app\common\model\Product as ProductModel; //物料
use app\common\model\WarehouseContainerItemMove as WarehouseContainerItemMoveModel; //物料转移日志

use app\common\model\OutSortationTask as OutSortationTaskModel; //分拣任务
use app\common\model\OutSortationTaskItem as OutSortationTaskItemModel; //分拣任务物料明细

use app\common\model\OutOrder as OutOrderModel;//出库订单
use app\common\model\OutOrderItem as OutOrderItemModel;//出库订单明细
use app\common\model\InOrder as InOrderModel;//入库订单
use app\common\model\InOrderItem as InOrderItemModel;//入库订单明细

use app\common\model\ProductBatch as ProductBatchModel;//入库批次
use app\api\controller\v1\Hik as HikController; //海康api控制器
use app\common\model\AgvTask as AgvTaskModel;   //AGV任务
use app\common\model\WarehouseShelves as WarehouseShelvesModel; //货架
use app\common\model\Robotarm  as RobotarmModel; //机械臂任务


use think\Config;
use think\Db;
use think\Exception;
use fast\Pinyin;

class WarehouseApi extends Base
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    //protected $iot_api_url ='';//Lot通讯地址
    protected $base_api_url ='';//内部通讯api地址

    //不需要key的控制器
    protected $no_key_action =[
        'callback_task',//机器人任务回调
        'bz_callback_task',//播种墙回调

    ];

    //库位测试开关
    protected $test_location =2;//1 开启 2关闭
    //测试库位范围
    protected $test_location_code_arr = [
        'HAI-002-001-02',
        'HAI-002-001-03',
        'HAI-002-001-04',
        'HAI-002-001-05',
        'HAI-002-002-01',
        'HAI-002-002-02',
        'HAI-002-002-03',
        'HAI-002-002-04',
        'HAI-002-002-05',
    ];

    //默认管理员 内部调用使用
    protected $default_aid =1;
    //工作位前缀
    protected $workbench_prefix ='CS';
    //容器前缀
    protected $container_prefix ='A0000';
    //物料前缀
    protected $product_prefix ='';

    public function _initialize()
    {

        parent::_initialize();
        $url1 = isset($_SERVER["REQUEST_URI"])?$_SERVER["REQUEST_URI"]:'';
        $url = isset($_SERVER['SCRIPT_NAME'])?$_SERVER['SCRIPT_NAME']:'';
        write_log('WAI:'.$url.'|'.$url1.'|'.request()->action().'|'.request()->controller(),'api_req_url_');
        if(!in_array(request()->action(),$this->no_key_action) && !in_array($url,$this->whitelist_url) && !in_array($url1,$this->whitelist_url)) {
            $this->h_check();
        }

        $this->base_api_url = Config::get('base_api_url');
    }

    //测试
    public function test(){
        $test =new \app\api\controller\v1\Test;
        $test->test1('666');
    }

    //入库流程

    //出库流程

    //理货流程
    // 1 界面分别选中待理货和待放入容器 并分表呼叫容器 创建“出库任务”（2个） 任务状态 创建 创建出库订单
    // 2 出库任务下发后 返回成功 任务状态 执行中 容器状态 出入库中（禁止下发任务）
    // 3 容器到达理货工作位 接口回调 任务状态 执行成功 容器状态 可用 更新绑定库位（计划任务还会再校验） 容器移动日志记录
    // 4 左边选中物料 点 转移物料 提示是否保存 选是 创建理货订单
    // 5 容器回库 根据容器最后移动日志 创建入库任务 和 入库订单
    // 6 容器回收 更新容器状态 为锁定 并清空 绑定库位

    //入库
    public function tote_inbound($container_code='',$container_type='',$from_location_code='',$location_code='',$w_id='',$w_b_id='',$cid='',$aid=''){
        
        $container_code = $container_code?$container_code:$this->_get_param('container_code',''); //容器号
        $from_location_code = $from_location_code?$from_location_code:$this->_get_param('from_location_code',''); //起始工作位
        $container_type = $container_type?$container_type:$this->_get_param('container_type','1'); //容器类型-需要扩展 暂时默认
        $location_code= $location_code?$location_code:$this->_get_param('location_code'); //目标库位
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //操作人id
        $w_id = $w_id?$w_id:$this->_get_param('w_id','');//工作台id
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id','');//工作位id

        $hik = new HikController();
        //入库前校验库位是否可用
        $check = WarehouseLocationModel::where(['real_location'=>$location_code,'status'=>1])->find();
        if(empty($check)){
            $this->error('库位使用中，不可移入容器');
        }
        /** 
         * 
        *   $container_code =>  "00000002,00000003"
        *   $from_location_code =>  "0003440XX0005000"
        *   $location_code =>  "P000009B01021"
        */
        if($check['wh_a_id'] == 2){
            // 先检测container_code
            $container_code_array = explode(',',$container_code);
            $check_container_code = WarehouseContainerModel::where('numbering','in',$container_code_array)->where('wh_a_id',1)->find();
            if($check_container_code){
               $this->error('料箱对应库位错误'); 
            }
            $check_three = $hik->check_three_status();
            if($check_three['code'] != 1){
                $this->error($check_three['message']);
            }
            $use_list = $this->count_container_inbound($container_code,$cid,$aid,$w_id,$w_b_id,$container_type);
            write_log(var_export($use_list,true),'tote_inbound_ret_');
            if($use_list['status'] != 1){
                $this->error($use_list['msg']);
            }
            
           
            
        }else{
            $container_code_array = explode(',',$container_code);
            $check_container_code = WarehouseContainerModel::where('numbering','in',$container_code_array)->where('wh_a_id',2)->find();
            if($check_container_code){
               $this->error('料箱对应库位错误'); 
            }
        }

        
        //密集柜状态判断
        //HAI-002-005-03
        // $lot = new MoveCabinetsController();
        // $lot->get_status(substr($location_code , 6,1));

        //更新入库订单
        InOrderModel::update([
            'status'=>3,
        ],[
            'wh_c_numbering'=>$container_code,
            'status'=>'1'
        ]);
        
        //  hik调度入库
        
        $task_code_time = $this->getMillisecond();
        if($check['wh_a_id'] == 1){
            $task_code = 'task_tote_inbound_ctu_'.$task_code_time;
            //创建任务
            $task_data =[
                'task_code'=>$task_code,
                'container_code'=>$container_code,
                'to_location_code'=>$location_code,
                'from_location_code'=>$from_location_code,
                'container_type'=>$container_type,
            ];
            $task_id = [];
            $task_id[] = $this->add_task($cid,$aid,'tote_inbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$container_code,$location_code,$from_location_code);
            // 1 入库  料箱号 库位
            $ret = $hik->send_ctu_task('1',$task_code,$container_code,$location_code);
            $location_code = [$location_code];
            $container_code = [$container_code];
        }else{
            $task_code = $use_list['data']['task_code_arr'][0];
            $task_code_shelf = $use_list['data']['task_code_arr'][1];
            $shelf_number = $use_list['data']['shelf_number'];
            $task_id = $use_list['data']['task_ids'];
            $location_code = $use_list['data']['to_location_list'];
            $container_code = explode(',',$container_code);
            $ret = $hik->agv_task(1,$task_code,$task_code_shelf,$shelf_number);
            
        }
        


        //创建入库订单--回头写

        //下发指令
        // $lot = new LfbIotController();
        // $ret = $lot->post_container_task('tote_inbound',$task_code,$container_code,'',$from_location_code,'','',$location_code,'','data');
        //$ret = json_decode($ret,true);
        write_log(var_export($ret,true),'tote_inbound_ret_');
        if(!empty($ret) && is_array($ret) && $ret['code']=='SUCCESS'){
            Db::startTrans();
            try{
                //更新任务过程
                IotTaskModel::update([
                    'status'=>2, //任务状态:1=创建,2=执行中,3=执行成功,4=执行失败
                    'up_time'=>time(),
                ],[    
                    'id'=>['in',$task_id],
                ]);
                //修改容器状态 和 库位状态
                WarehouseContainerModel::update([
                    'status'=>3, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                    'up_time'=>time(),
                ],[
                    'numbering'=>['in',$container_code],
                    'cid'=>$cid,
                ]);
                
                //目标库位 出入库中
                WarehouseLocationModel::update([
                    'status'=>2, //状态:1=可用,2=出入库中,3=锁定
                    'up_time'=>time(),
                ],[
                    'real_location'=>['in',$location_code],
                    'cid'=>$cid,
                ]);
                //原库位要出入口中-不处理
                /*WarehouseLocationModel::update([
                    'status'=>2, //状态:1=可用,2=出入库中,3=锁定
                    'up_time'=>time(),
                ],[
                    'real_location'=>$from_location_code,
                    'cid'=>$cid,
                ]);*/

                /*$container =  WarehouseContainerModel::where([
                    'numbering'=>$container_code,
                    'cid'=>$cid,
                ])->find();
                if(!empty($container)){
                    WarehouseLocationModel::update([
                        'status'=>2, //状态:1=可用,2=出入库中,3=锁定
                        'up_time'=>time(),
                    ],[
                        'id'=>$container['wh_l_id'],
                        'cid'=>$cid,
                    ]);
                }*/

            Db::commit();
                $this->success('ok',$ret['data']);
            }catch (Exception $e) {
            Db::rollback();
            write_log(var_export($e->getMessage(),true),'tote_inbound_err_up_');
            $this->error('容器库位 出库状态 更新失败');
        }
        }else{
            write_log(var_export($ret,true),'tote_inbound_err_');
            $this->error('入库失败');
        }

    }


    //出库
    public function tote_outbound($container_code='',$to_location_code='',$from_location_code='',$w_id='',$w_b_id='',$real_location='',$cid='',$aid=''){
    
        
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //操作人id

        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器号
        $to_location_code = $to_location_code?$to_location_code:$this->_get_param('to_location_code');
        $from_location_code = $from_location_code?$from_location_code:$this->_get_param('from_location_code','');

        $w_id = $w_id?$w_id:$this->_get_param('w_id','');//工作台id
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id','');//工作位id

        $real_location = $real_location?$real_location:$this->_get_param('real_location','');//工作台绑定编号
        
        $hik = new HikController();

        if(empty($real_location)){
            $real_location = $to_location_code;
        }
        if($to_location_code == '0003440XX0005000'){
            $container_code_array = explode(',',$container_code);
            $check_container_code = WarehouseContainerModel::where('numbering','in',$container_code_array)->where('wh_a_id',1)->find();
            if($check_container_code){
               $this->error('料箱对应库位错误'); 
            }
            $check_three = $hik->check_three_status_outbound();
            if($check_three['code'] != 1){
                $this->error($check_three['message']);
            }
            //校验容器和目标工作位是否可用
            $container_code_arr = explode(',',$container_code);
            $c_check = WarehouseContainerModel::where(['numbering'=>['in',$container_code_arr],'status'=>['elt','2']])->find();
            if(empty($c_check)){
                $this->error('容器使用中，不可操作出库动作');
            }
        }else{
            $container_code_array = explode(',',$container_code);
            $check_container_code = WarehouseContainerModel::where('numbering','in',$container_code_array)->where('wh_a_id',2)->find();
            if($check_container_code){
               $this->error('料箱对应库位错误'); 
            }
            //校验容器和目标工作位是否可用
            $c_check = WarehouseContainerModel::where(['numbering'=>['in',$container_code_array],'status'=>['elt','2']])->find();
            if(empty($c_check)){
                $this->error('容器使用中，不可操作出库动作');
            }
        }
        

        //单库位工作台限制 如果是输送线 可控制最大容器数量
        $l_check = WarehouseLocationModel::where(['real_location'=>$to_location_code,'status'=>1])->find();
        if(empty($l_check)){
            $this->error('目标工作台使用中，不可操作出库动作');
        }
        $from_location = WarehouseLocationModel::where(['id'=>$c_check['wh_l_id']])->find();
        $from_location_code = $from_location['numbering'];
        
        if($l_check['wh_a_id'] == 2){
            $use_list = $this->count_container_outbound($container_code,$cid,$aid,$w_id,$w_b_id);
            if($use_list['status'] != 1){
                $this->error($use_list['msg']);
            } 
        }else{
            $use_list = $this->ctu_outbound($container_code,$to_location_code,$cid,$aid,$w_id,$w_b_id);
        }

        
        //密集柜状态判断
        // $lot = new MoveCabinetsController();
        // $container_code_l = WarehouseContainerModel::where(['numbering'=>$container_code])->find();
        // if(empty($container_code_l)){
        //     $this->error('请等候密集柜调整出库位置，稍后重试出库动作');
        // }
        // $lot->get_status(substr($container_code_l['wh_l_numbering'] , 6,1));


        
        //  hik调度出库
        
        if($l_check['wh_a_id'] == 1){
            
            $task_id = $use_list['data']['task_ids'];
            $container_code = explode(',',$container_code);
            $task_code = $use_list['data']['task_code_arr'][0];

            // dump($use_list);exit;
            // 2 出库  料箱号  目标库位
            $ret = $hik->send_ctu_task('2',$task_code,$container_code[0],$to_location_code);
        }else{

            $task_code = $use_list['data']['task_code_arr'][0];
            $task_code_shelf = $use_list['data']['task_code_arr'][1];
            $shelf_number = $use_list['data']['shelf_number'];
            $task_id = $use_list['data']['task_ids'];
            $location_code = $use_list['data']['to_location_list'];
            $container_code = explode(',',$container_code);
            $ret = $hik->agv_task_outbound(1,$task_code,$task_code_shelf,$shelf_number);
            $three_location = $hik->get_container_status('000010');
            if($three_location['data']['siteCode'] == '0007040XX0010890'){
                // 在机械臂点了
                $change_task_id = array_shift($task_id);
                IotTaskModel::update([
                    'status'=>3, //任务状态:1=创建,2=执行中,3=执行成功,4=执行失败
                    'up_time'=>time(),
                ],[
                    
                    'id'=>$change_task_id,
                ]);
                write_log(var_export('处于机械臂点'.json_encode($change_task_id),true),'change_task_id_');
            }
        }
        
        //创建出库订单--回头写


        // //下发指令
        // $lot = new LfbIotController();
        // $ret = $lot->post_container_task('tote_outbound',$task_code,$container_code,$to_location_code,$from_location_code,'','','','','data');
        //$ret = json_decode($ret,true);
        write_log(var_export($ret,true),'tote_outbound_ret_');
        // if(true){
        if(!empty($ret) && is_array($ret) && $ret['code']=='SUCCESS'){
            Db::startTrans();
            try{
                //更新任务过程
                IotTaskModel::update([
                    'status'=>2, //任务状态:1=创建,2=执行中,3=执行成功,4=执行失败
                    'up_time'=>time(),
                ],[
                    
                    'id'=>['in',$task_id],
                ]);
                write_log(var_export('IotTaskModel更新成功',true),'tote_outbound_ret_');
                //修改容器状态 和 库位状态
                WarehouseContainerModel::update([
                    'status'=>3, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                    'up_time'=>time(),
                ],[
                    'numbering'=>['in',$container_code],
                    'cid'=>$cid,
                ]);
                
                // $container =  WarehouseContainerModel::where([
                //     'numbering'=>['in',$container_code],
                //     'cid'=>$cid,
                // ])->column('wh_l_id');
                // write_log(var_export('container找到成功',true),'tote_outbound_ret_');
                // if(!empty($container)){
                //     WarehouseLocationModel::update([
                //         'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                //         'up_time'=>time(),
                //     ],[
                //         'id'=>['in',$container],
                //         'cid'=>$cid,
                //     ]);
                // }
                
                Db::commit();
                $this->success('ok',$ret['data']);
            }catch (Exception $e) {
                Db::rollback();
                write_log(var_export($e->getMessage(),true),'tote_outbound_err_up_');
                $this->error('容器库位 出库状态 更新失败');
            }


        }else{
            write_log(var_export($ret,true),'tote_outbound_err_');
            $this->error('off');
        }



    }

    //创建任务
    public function add_task($cid,$aid,$type,$data,$task_code,$name='',$w_id='',$w_b_id='',$container_code='',$real_location='',$from_location_code=''){
        //任务类型:1=入库,2=出库,3=理货入库,4=理货出库,5=移库,6=播种墙操作,7=其他任务
        $type_int = $this->select_task_type($type);
        $id = IotTaskModel::insertGetId([
            'name'=>$name,
            'type'=>$type_int,
            'task_code'=>$task_code,
            'w_id'=>$w_id,
            'w_b_id'=>$w_b_id,
            'container_code'=>$container_code,
            'to_location_code'=>$real_location,
            'from_location_code'=>$from_location_code,
            'content'=>serialize($data),
            'aid'=>$aid,
            'add_time'=>time(),
            'cid'=>$cid,

        ]);
        return $id;

    }

    //任务类型
    public function select_task_type($name){
        $type_int ='7';
        switch ($name){
            case 'tote_outbound'://出库
                $type_int =2;
                break;
            case 'tote_inbound'://入库
                $type_int =1;
                break;
            case 'bz_task'://播种墙
            $type_int =6;
                break;
            default:
                break;
        }
        return $type_int;
    }

    //理货订单-未用
    public function add_tallying_order($w_id='',$w_b_id='',$wh_a_id='',$from_wh_c_id='',$from_wh_c_i_id='',$target_wh_c_id='',$num='',$cid='',$aid=''){

        $from_wh_c_id = $from_wh_c_id?$from_wh_c_id:$this->_get_param('from_wh_c_id'); //来源容器id
        $from_wh_c_i_id = $from_wh_c_i_id?$from_wh_c_i_id:$this->_get_param('from_wh_c_i_id'); //来源物料id 1|2|3
        $target_wh_c_id = $target_wh_c_id?$target_wh_c_id:$this->_get_param('target_wh_c_id'); //目标容器id
        $w_id = $w_id?$w_id:$this->_get_param('w_id','');//工作台id
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id','');//工作位id
        $wh_a_id = $wh_a_id?$wh_a_id:$this->_get_param('wh_a_id','');//库区id
        $num = $num?$num:$this->_get_param('num');//库区id

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //操作人id

        $from_wh_c_i_id = is_array($from_wh_c_i_id)?implode('|',$from_wh_c_i_id):$from_wh_c_i_id;
        //
        $ret = TallyingOrderModel::insertGetId([
            'w_id'=>$w_id,
            'w_b_id'=>$w_b_id,
            'wh_a_id'=>$wh_a_id,
            'from_wh_c_id'=>$from_wh_c_id,
            'from_wh_c_i_id'=>$from_wh_c_i_id,
            'target_wh_c_id'=>$target_wh_c_id,
            'num'=>$num,
            'cid'=>$cid,
            'aid'=>$aid,
            'add_time'=>time(),
        ]);

        if ($ret){
            //更新物料关系
            $from_wh_c_i_id = explode("|",$from_wh_c_i_id);
            $container = WarehouseContainerModel::where(['id'=>$target_wh_c_id])->find();
            foreach ($from_wh_c_i_id as $val){


                WarehouseContainerItemModel::update([
                    'wh_c_id'=>$container['id'],
                    'wh_c_numbering'=>$container['numbering'],
                    'up_time'=>time(),
                    'up_aid'=>$aid,
                ],[
                    'id'=>$val
                ]);
            }
            $this->success('ok');
        }else{
            $this->error('off');
        }

    }

    //获取扫码枪内容
    public function get_code_scan($type='',$scan_code='',$cid=''){
        $type = $type?$type:$this->_get_param('type'); //类型 1 工作位绑定 2容器获取
        $scan_code = $scan_code?$scan_code:$this->_get_param('scan_code'); //扫码枪编号
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id


        $ret = ScanCodeLogModel::where(['type'=>$type,'scan_code'=>$scan_code,'cid'=>$cid])->find();
        if($type==1){
           $wb =  WorkbenchBitModel::where(['real_location'=>$ret['content']])->find();
           if(!empty($wb)){
               $ret['w_id'] = $wb['w_id'];//工作台id
               $ret['w_b_id'] = $wb['id'];//工作位id
           }
        }

        $this->success('ok',$ret);

    }
    //扫码枪上传内容解析
    public function up_scan_code($code='',$anchor='',$cid=''){
        $anchor = $anchor?$anchor:$this->_get_param('anchor'); //锚点 或 页面标识
        $code = $code?$code:$this->_get_param('code'); //扫码内容
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        ScanCodeLogModel::insertGetId([
            'anchor'=>$anchor,
            'content'=>$code,
            'cid'=>$cid,
        ]);
        //判断工作位\容器\物料 返回id
        $code_type = 'product';//默认扫码类型 是物料
        $data =[];
        //工作位判断
        // if(strpos($code,$this->workbench_prefix) !== false){
        //     $code_type = 'workbench';
        //     $data = WorkbenchBitModel::where(['wh_l_numbering'=>$code])->find();
        //     $data['w_b_id'] =$data['id'];

        // }
        // //容器判断
        // if(strpos($code,$this->container_prefix) !== false){
        //     $code_type = 'container';
        //     $data = WarehouseContainerModel::where(['name'=>$code])->find();
        // }

        // if($code_type == 'product'){
        //     $data = ProductModel::where(['numbering'=>$code])->find();

        // }
        $product = ProductModel::where(['numbering'=>$code])->find();
        if($product){
            $code_type = 'workbench';
            $data = $product;
        } 
        $move_product = Db::name('move_product')->where('code',$code)->find();
        if($move_product){
            $code_type = 'move_product';
            $data = $move_product;
        } 
        $move_code = Db::name('move_code')->where('code',$code)->find();
        if($move_code){
            $code_type = 'move_code';
            $data = $move_code;
        } 
        $container = WarehouseContainerModel::where(['name'=>$code])->find();
        if($container){
            $code_type = 'container';
            $data = $container;
        } 
        $workbench = WorkbenchBitModel::where(['wh_l_numbering'=>$code])->find();
        if($workbench){
            $code_type = 'workbench';
            $data = $workbench;
        }
        $ret_data =[
            'type'=>$code_type,
            'info'=>$data,
        ];

        if(!empty($data)){
            $this->success('ok',$ret_data);
        }else{
            $this->error('扫码内容无法解析');
        }

    }

    //编号判断内容
    public function code_to_info($code='',$cid='',$type=''){
        $code = $code?$code:$this->_get_param('code'); //扫码内容
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $type = $type?$type:$this->_get_param('type'); //类型 
        //判断工作位\容器\物料 返回id
        $code_type = 'product';//默认扫码类型 是物料
        $data =[];
        //工作位判断
        if(strpos($code,$this->workbench_prefix) !== false){
            $code_type = 'workbench';
            $data = WorkbenchBitModel::where(['wh_l_numbering'=>$code])->find();
            $data['w_b_id'] =$data['id'];

        }
        if($type=='workbench'){
            $code_type = 'workbench';
            $data = WorkbenchBitModel::where(['wh_l_numbering'=>$code])->find();
            $data['w_b_id'] =$data['id'];
        }
        //容器判断
        // if(strpos($code,$this->container_prefix) !== false){
        //     $code_type = 'container';
        //     $data = WarehouseContainerModel::where(['name'=>$code])->find();
        // }
        if($type=='container'){
            $code_type = 'container';
            $data = WarehouseContainerModel::where(['name'=>$code])->find();
        }

        if($code_type == 'product'){
            $data = ProductModel::where(['numbering'=>$code])->find();

        }
        $ret_data =[
            'type'=>$code_type,
            'info'=>$data,
        ];

        if(!empty($data)){
            $this->success('ok',$ret_data);
        }else{
            $this->error('扫码内容无法解析');
        }
    }


    //获取工作位信息
    public function get_workbench_bit($location_code='',$cid=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $location_code = $location_code?$location_code:$this->_get_param('location_code'); //工作位编号
        $wb =  WorkbenchBitModel::where(['real_location'=>$location_code,'cid'=>$cid])->find();
        if(!empty($wb)){
            $this->success('ok',$wb);
        }else{
            $this->error('工作位不存在');
        }



    }

    //添加物料信息-先默认
    public function add_product($product_name='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $product_name = $product_name?$product_name:$this->_get_param('product_name'); //商品名称
        
        $map['id']  = array('gt',0);
        $last_p = ProductModel::where($map)->order('id DESC')->find();
        if(!empty($last_p)){
            // $numbering = 'P'.str_pad((int)str_replace("0","",substr($last_p['numbering'], 1))+1,4,"0",STR_PAD_LEFT);
            // 取出编号数字部分，去掉前导P
            $num = intval(substr($last_p['numbering'], 1)) + 1;
            // 补齐7位，不足左侧补0
            $numbering = 'P' . str_pad($num, 7, '0', STR_PAD_LEFT);
        }else{
            $numbering = 'P0000001';
            // $numbering = 'P0001';
        }
        $data =[
            'name'=>$product_name,
            'numbering'=>$numbering,
            'py'=>Pinyin::get($product_name),
            'spec'=>'件',
            'cate_id'=>'2',
            'brand_id'=>'1',
            'measure_unit_id'=>'1',
            'measure_unit'=>'件',
            'aid'=>'1',
            'add_time'=>time(),
        ];
        $data['id'] = ProductModel::insertGetId($data);

        $this->success('ok',$data);


    }

    //获取物料详情
    public function get_product_info($numbering='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $numbering = $numbering?$numbering:$this->_get_param('numbering'); //商品编号/商品名称两用

        $product = ProductModel::where(['numbering'=>$numbering])->find();
        if(!empty($product)){

            $this->success('ok',$product);
        }else{
            $product = ProductModel::where(['name'=>$numbering])->find();
            if(!empty($product)){
                $this->success('ok',$product);
            }
            $this->error('商品不存在');
        }



    }


    //获取容器物料内容
    public function get_container_item_list($wh_c_numbering='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $wh_c_numbering = $wh_c_numbering?$wh_c_numbering:$this->_get_param('wh_c_numbering'); //容器编号

        $ret = WarehouseContainerItemModel::where(['wh_c_numbering'=>$wh_c_numbering,'cid'=>$cid])->select();
        $list =[];
        if(!empty($ret)){
            foreach ($ret as $val){
                $product = ProductModel::where(['id'=>$val['product_id']])->find();
                if(empty($product)){
                    continue;
                }
                $data = [
                    'id'=>$val['id'],
                    'name'=>$product['name'],
                    'numbering'=>$product['numbering'],
                    'num'=>$val['product_num'],
                    'measure_unit'=>$product['measure_unit'],
                    'status'=>'1',
                    'plaid'=>'1',

                ];
                $list[] = $data;
            }
        }

        $this->success('ok',$list);

    }

    //物料id获取详细信息
    public function get_container_item($container_item_id='',$aid='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $container_item_id = $container_item_id?$container_item_id:$this->_get_param('container_item_id'); //容器物料绑定id

        $w_c_info = WarehouseContainerItemModel::where(['id'=>$container_item_id])->find();
        if(!empty($w_c_info)){

            $p_info = ProductModel::where(['id'=>$w_c_info['product_id']])->find();
            if(empty($p_info))$this->error('该商品不存在');
            $w_c_info['product_name']=$p_info['name'];
            $w_c_info['spec']=$p_info['spec'];
            $this->success('ok',$w_c_info);
        }else{
            $this->error('货品库存不存在');
        }

    }

    //物料容器解绑
    public function container_out_item($container_item_id='',$aid='',$cid=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $container_item_id = $container_item_id?$container_item_id:$this->_get_param('container_item_id'); //容器物料绑定id

        $w_c_item = WarehouseContainerItemModel::where(['id'=>$container_item_id])->find();
        if(!empty($w_c_item)){
            //占比换算
            $proportion_num = $this->get_product_proportion($w_c_item['proportion']);
            $container =WarehouseContainerModel::where(['numbering'=>$w_c_item['wh_c_numbering']])->find();
            $proportion_num_c = $this->get_product_proportion($container['proportion']);

            //更新容器占比
            WarehouseContainerModel::update([
                'proportion'=>$this->get_product_proportion('',$proportion_num_c-$proportion_num),
            ],[
                'id'=>$container['id'],
            ]);


            //删除入库订单绑定信息
            $i_o_info = InOrderModel::where(['wh_c_numbering'=>$container['numbering'],'status'=>'1'])->find();
            if(!empty($i_o_info) && !empty($i_o_info['items'])){
                $item_arr = json_decode($i_o_info['items'],true);
                $item_arr_new = [];
                if(is_array($item_arr)){
                    foreach ($item_arr as $val){
                        $i_o_i_info = InOrderItemModel::where(['product_id'=>$w_c_item['product_id'],'in_order'=>$i_o_info['id'],'num'=>$w_c_item['product_num']])->find();
                        if(!empty($i_o_i_info)){
                            InOrderItemModel::where(['product_id'=>$w_c_item['product_id'],'in_order'=>$i_o_info['id'],'num'=>$w_c_item['product_num']])->delete();
                        }else{
                            $item_arr_new[] = $val;
                        }

                    }
                    if(!empty($item_arr_new)){
                        InOrderModel::update([
                            'items'=>json_encode($item_arr_new),
                        ],[
                            'wh_c_numbering'=>$container['numbering'],
                            'status'=>'1'
                        ]);
                    }


                }

            }



            WarehouseContainerItemModel::where(['id'=>$container_item_id])->delete();

            $this->success('容器物料关联删除成功',['numbering'=>$w_c_item['wh_c_numbering']]);
        }

    }


    //物料绑定容器
    public function container_in_item($wh_c_numbering='',$wh_c_id='',$product_id='',$product_num='',$proportion='',$aid='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $wh_c_numbering = $wh_c_numbering?$wh_c_numbering:$this->_get_param('wh_c_numbering'); //容器
        $wh_c_id = $wh_c_id?$wh_c_id:$this->_get_param('wh_c_id',''); //容器id
        $proportion = $proportion?$proportion:$this->_get_param('proportion',1); //物料占比


        $product_id = $product_id?$product_id:$this->_get_param('product_id'); //物料id
        $product_num = $product_num?$product_num:$this->_get_param('product_num','1'); //物料数量


        $product = ProductModel::where(['id'=>$product_id])->find();

        //占比换算
        $proportion_num = $this->get_product_proportion($proportion);




        if(empty($product)){
            $this->error('组盘失败，物料不存在，请添加物料信息');
        }

        $id ='';
        //容器判断
        if(empty($wh_c_numbering)){
            $container =WarehouseContainerModel::where(['id'=>$wh_c_id])->find();
        }

        if(empty($wh_c_id)){
            $container =WarehouseContainerModel::where(['numbering'=>$wh_c_numbering])->find();
        }

        if(empty($container)){
            $this->error('容器不存在');
        }else{

            $proportion_num_c = $this->get_product_proportion($container['proportion']);

            if(($proportion_num_c+$proportion_num)>100){

                $this->error('组盘失败，容器占比已经超过100%');

            }
            // 11-10 组盘 查找是否已存在，存在添加数量
            $existing_item = WarehouseContainerItemModel::where([
                'wh_c_numbering'=>$wh_c_numbering,
                'product_id'=>$product_id,
                'wh_c_id'=>$container['id'],
                'cid'=>$cid,
            ])->find();
            if(!empty($existing_item)){
                //更新数量
                $new_product_num = $existing_item['product_num'] + $product_num;
                WarehouseContainerItemModel::update([
                    'product_num'=>$new_product_num,
                    'up_time'=>time(),
                    'up_aid'=>$aid,
                ],[
                    'id'=>$existing_item['id'],
                ]);
                $this->success('ok',$wh_c_numbering);
            }



            //物料绑定容器
            $id = WarehouseContainerItemModel::insertGetId([
                'wh_c_numbering'=>$wh_c_numbering,
                'cid'=>$cid,
                'product_id'=>$product_id,
                'product_num'=>$product_num,
                'proportion'=>$proportion,
                'wh_c_id'=>$container['id'],
                'aid'=>$aid,
                'add_time'=>time(),
            ]);

            //更新容器占比
            WarehouseContainerModel::update([
                'proportion'=>$this->get_product_proportion('',$proportion_num_c+$proportion_num),
            ],[
                'id'=>$container['id'],
            ]);
            //搜索容器和入库订单关系

            $p_batch = $this->get_product_batch($product_id,$wh_c_id,$aid,$cid);

            $i_o_info = InOrderModel::where(['wh_c_id'=>$container['id']])->find();
            $items[] = [
                'product_name'=>$product['name'],
                'num'=>$product_num,
                'batch'=>$p_batch,
            ];
            if(empty($i_o_info)){

                //添加入库订单
                Db::startTrans();
                try{
                    $id = InOrderModel::insertGetId([
                        'order_sn'=>get_sys_order_sn('RKDH'),
                        'wh_id'=>1,
                        'wh_c_id'=>$container['id'],
                        'wh_c_numbering'=>$wh_c_numbering,
                        'status'=>1,//状态:1=新建,2=已经审核,3=已经完成,4=审核拒绝
                        'business_type_id'=>30,//成品入库
                        'items'=>json_encode($items),
                        'aid'=>$aid,
                        'add_time'=>time(),
                        'cid'=>$cid,
                    ]);
                    if($id){
                        InOrderItemModel::insertGetId([
                            'in_order'=>$id,
                            'product_name'=>$product['name'],
                            'product_id'=>$product['id'],
                            'product_numbering'=>$product['numbering'],
                            'product_spec'=>$product['spec'],
                            'sys_dict_id'=>5,//合格
                            'num'=>$product_num,//数量
                            'unit_id'=>1,//件
                            'batch'=>$p_batch,
                            'aid'=>$aid,
                            'add_time'=>time(),
                            'cid'=>$cid,
                        ]);

                    }


                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    write_log(var_export($e->getMessage(),true),'add_in_order_err1_');
                    $this->error('添加入库订单失败');
                }

            }else{

                $items_arr  = json_decode($i_o_info['items'],true);
                //if(!empty($items_arr) && is_array($items_arr)){

                    $items_arr[] = [
                        'product_name'=>$product['name'],
                        'num'=>$product_num,
                        'batch'=>$p_batch,
                    ];

                    InOrderModel::update([
                        'items'=>json_encode($items_arr),
                    ],[
                        'id'=>$i_o_info['id'],
                        'status'=>'1'
                    ]);
                //}

                InOrderItemModel::insertGetId([
                    'in_order'=>$i_o_info['id'],
                    'product_name'=>$product['name'],
                    'product_id'=>$product['id'],
                    'product_numbering'=>$product['numbering'],
                    'product_spec'=>$product['spec'],
                    'sys_dict_id'=>5,//合格
                    'num'=>$product_num,//数量
                    'unit_id'=>1,//件
                    'batch'=>$p_batch,
                    'aid'=>$aid,
                    'add_time'=>time(),
                    'cid'=>$cid,
                ]);


            }


        }



        if($id){
            $this->success('ok',$wh_c_numbering);
        }else{
            $this->error('组盘失败');
        }


    }
    //获取物料批次
    public function get_product_batch($product_id,$wh_c_id='',$aid='',$cid =''){

        $batch =1;
        $p_b_info = ProductBatchModel::where(['product_id'=>$product_id,'wh_c_id'=>$wh_c_id])->order('id DESC')->find();
        if(!empty($p_b_info)){
            $batch=  $p_b_info['batch'];
        }else{


            $p_b_info1 = ProductBatchModel::where(['product_id'=>$product_id])->order('id DESC')->find();
            if(!empty($p_b_info1)){
                ProductBatchModel::insertGetId([
                    'product_id'=>$product_id,
                    'batch'=>$p_b_info1['batch']+1,
                    'wh_c_id'=>$wh_c_id,
                    'aid'=>$aid,
                    'cid'=>$cid,
                    'add_time'=>time(),
                ]);
                $batch= $p_b_info1['batch']+1;

            }else{
                ProductBatchModel::insertGetId([
                    'product_id'=>$product_id,
                    'batch'=>1,
                    'wh_c_id'=>$wh_c_id,
                    'aid'=>$aid,
                    'cid'=>$cid,
                    'add_time'=>time(),
                ]);
            }
        }

        return $batch;
    }

    //获取物料占比量
    public function get_product_proportion($proportion='',$proportion_id=''){

        if ($proportion){
            $bl =0;
            switch ($proportion){
                case 1:
                    $bl =0;
                    break;
                case 2:
                    $bl =25;
                    break;
                case 3:
                    $bl =50;
                    break;
                case 4:
                    $bl =75;
                    break;
                case 5:
                    $bl =100;
                    break;

            }
        }else{
            $bl =0;
            switch ($proportion_id){
                case 0:
                    $bl =0;
                    break;
                case 25:
                    $bl =2;
                    break;
                case 50:
                    $bl =3;
                    break;
                case 75:
                    $bl =4;
                    break;
                case 100:
                    $bl =5;
                    break;

            }
        }


        return $bl;
    }


    //物料转移
    public function move_item($from_wh_c_numbering='',$to_wh_c_numbering='',$item_list='',$w_id='',$w_b_id='',$wh_a_id='',$aid='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $from_wh_c_numbering = $from_wh_c_numbering?$from_wh_c_numbering:$this->_get_param('from_wh_c_numbering'); //来源容器
        $to_wh_c_numbering = $to_wh_c_numbering?$to_wh_c_numbering:$this->_get_param('to_wh_c_numbering'); //目标容器
        $item_list = $item_list?$item_list:$this->_get_param('item_list'); //物料列表

        $w_id = $w_id?$w_id:$this->_get_param('w_id','');//工作台id
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id','');//工作位id
        $wh_a_id = $wh_a_id?$wh_a_id:$this->_get_param('wh_a_id','');//库区id

        $to_container = WarehouseContainerModel::where(['numbering'=>$to_wh_c_numbering])->find();
        $from_container = WarehouseContainerModel::where(['numbering'=>$from_wh_c_numbering])->find();

        //理货订单
        $id = TallyingOrderModel::insertGetId([
            'w_id'=>$w_id,
            'w_b_id'=>$w_b_id,
            'wh_a_id'=>$wh_a_id,
            'from_wh_c_id'=>$from_container['id'],
            'from_wh_c_i_id'=>$item_list,
            'target_wh_c_id'=>$to_container['id'],
            //'num'=>$num,
            'cid'=>$cid,
            'aid'=>$aid,
            'add_time'=>time(),
        ]);

        if(!empty($id)){
            $item_arr = explode(",",$item_list);
            $ids =[];
            if(is_array($item_arr)){

                Db::startTrans();
                try{
                    foreach ($item_arr as $val){
                        //转移
                        WarehouseContainerItemModel::update([
                            'wh_c_numbering'=>$to_wh_c_numbering,
                            'wh_c_id'=>$to_container['id'],
                            'up_aid'=>$aid,
                            'up_time'=>time(),
                        ],[
                            'wh_c_numbering'=>$from_wh_c_numbering,
                            'cid'=>$cid,
                            'id'=>$val,

                        ]);

                        //转移日志
                        $ids[] =  WarehouseContainerItemMoveModel::insertGetId([
                            'wh_c_i_id'=>$val,
                            'f_wh_c_id'=>$from_container['id'],
                            't_wh_c_id'=>$to_container['id'],
                            'from_wh_c_numbering'=>$from_container['numbering'],
                            'to_wh_c_numbering'=>$to_container['numbering'],
                            'cid'=>$cid,
                            'aid'=>$aid,
                            'add_time'=>time(),
                        ]);

                    }
                    Db::commit();
                    $this->success('ok',$ids);
                }catch (\think\Exception\DbException $e) {
                    Db::rollback();
                    write_log(var_export($e->getMessage(),true),'tote_outbound_err_up_');
                    $this->error('数据存储错误，请重试');
                }

            }else{
                $this->error('转移失败，请重试');
            }
        }else{
            $this->error('理货订单创建失败');
        }





    }

    //工作位列表
    public function get_workbench_bit_list($cid='',$status=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $status = $status?$status:$this->_get_param('status'); //状态

        $where =[
            'cid'=>$cid,
            'status'=>$status,
        ];

        $list = WorkbenchBitModel::where($where)->select();
        if(!empty($list)){
            $this->success('ok',$list);
        }else{
            $this->error('无可操作工作位');
        }
    }

    //容器列表
    public function get_container_list($cid='',$status='',$product_name='',$number='',$type=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $status = $status?$status:$this->_get_param('status'); //状态
        $product_name = $product_name?$product_name:$this->_get_param('product_name',''); //商品名称
        //需要关联库区 后面弄

        //搜索可能 按库区 按状态 按编号 按仓库 后面加

        $where =[
            'cid'=>$cid,
            'status'=>$status,
            // 'wh_l_id'=>['gt',0],//绑定库位大于0

        ];

        // number 目标库位,并且库位是 R602A01011 或 R601A01011
        if( $number == 'R601A01011' || $number == 'R602A01011' ){
            $where['wh_a_id'] = 1;
            
        }else{
            $where['wh_a_id'] = 2;
            if($type == 'outbound'){
                $where['wh_s_id'] = ['<>',10];
            }else{
                $where['wh_s_id'] = 10;
            }
            
        }

        
        //状态 1 和 2 都可用
        if($status==2){
            $where['status'] =['in','1,2'];
        }
        

        $list = WarehouseContainerModel::where($where)->select();
        if(!empty($list)){
            //容器内物品

            foreach ($list as $key=>$val){
                $list[$key]['product_list'] = '空';
            $w_c_i_list = WarehouseContainerItemModel::where(['wh_c_numbering'=>$val['numbering']])->select();
            if(!empty($w_c_i_list)){
                $product_list ='';
                foreach ($w_c_i_list as $key1=>$val1){
                    $product_info =  ProductModel::where(['id'=>$val1['product_id']])->find();
                    if(!empty($product_info)){
                        $product_list = $product_list.' '.($key1+1).'. 名称:'.$product_info['name'].'|库存:'.$val1['product_num'].'|批次:'.$val1['batch'];
                    }
                }
                $list[$key]['product_list'] = $product_list;
            }

            }
            //商品名称筛洗
            if(!empty($product_name)){
                foreach ($list as $key3=>$val2){
                   if (!mb_strpos($val2['product_list'],$product_name)){
                       unset($list[$key3]);
                   }
                }
            }

            $this->success('ok',$list);
        }else{
            $this->error('无可操作容器');
        }

    }
    //货品查容器
    public function get_product_container($cid='',$product_name='',$container_code=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $product_name = $product_name?$product_name:$this->_get_param('product_name'); //物料名称
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //物料名称
       // $w_c_i_list = WarehouseContainerItemModel::where(['wh_c_numbering'=>['like','%'.$product_name.'%']])->select();
        //搜索物料
        $p_info = ProductModel::where(['name'=>$product_name])->find();
        if(!empty($p_info)){

            //if(!empty($container_code)){
                $w_c_info = WarehouseContainerItemModel::where(['product_id'=>$p_info['id'],'wh_c_numbering'=>$container_code])->find();
                //$w_c_info_list = WarehouseContainerItemModel::where(['product_id'=>$p_info['id'],'wh_c_numbering'=>$container_code])->select();



           /* }else{
                $w_c_info = WarehouseContainerItemModel::where(['product_id'=>$p_info['id']])->find();
                $w_c_info_list = WarehouseContainerItemModel::where(['product_id'=>$p_info['id']])->select();
            }*/

            if(!empty($w_c_info)){
                $w_c_info['spec'] = $p_info['spec'];
                $this->success('ok',$w_c_info);

            }else{
                $this->error('容器内不存在所填货品1');
            }


        }else{
            $this->error('容器内不存在所填货品2');
        }



    }

    //添加分拣
    public function add_sortation($container_code='',$container_item_id='',$num='',$proportion='',$w_b_id='',$aid='',$cid=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $container_item_id = $container_item_id?$container_item_id:$this->_get_param('container_item_id'); //容器物料id
        $container_code = $container_code?$container_code:$this->_get_param('container_code'); //容器名称
        $num = $num?$num:$this->_get_param('num'); //数量
        $proportion = $proportion?$proportion:$this->_get_param('proportion'); //占比
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id'); //工作位id

        $w_b_info = WorkbenchBitModel::where(['id'=>$w_b_id])->find();

        if(empty($w_b_id)){
            $this->error('工作位不存在，请选择工作位');
        }



        $o_s_t = OutSortationTaskModel::where(['w_b_id'=>$w_b_id,'status'=>['in','1,5']])->find(); //状态:1=新建,2=已经审核,3=已完成,4=审核拒绝,5=进行中
        $w_h_c_i = WarehouseContainerItemModel::where(['id'=>$container_item_id])->find();
        if(!$w_h_c_i){
            $this->error('容器物料不存在');
        }
        $o_s_t_id =0;
        //分拣任务判断
        if(empty($o_s_t)){

            if($num>$w_h_c_i['product_num']){
                $this->error('分拣数量超过了库存');
            }


            Db::startTrans();
            try{
                //添加分拣
                $id = OutSortationTaskModel::insertGetId([
                    's_order'=>get_sys_order_sn('FJ'),
                    'w_b_id'=>$w_b_id,
                    'wh_l_id'=>$w_b_info['wh_l_id'],
                    'wh_l_numbering'=>$w_b_info['wh_l_numbering'],
                    'real_location'=>$w_b_info['real_location'],
                    'status'=>5,
                    'aid'=>$aid,
                    'add_time'=>time(),
                    'cid'=>$cid,
                ]);
                //添加分拣列表
                if($id){
                     OutSortationTaskItemModel::insertGetId([
                        'sortation_task_id'=>$id,
                        'wh_c_id'=>$w_h_c_i['wh_c_id'],
                        'wh_c_numbering'=>$w_h_c_i['wh_c_numbering'],
                        'wh_c_product_id'=>$w_h_c_i['id'],
                        'product_id'=>$w_h_c_i['product_id'],
                        'product_num'=>$num,
                        'batch'=>$w_h_c_i['batch'],
                        'proportion'=>$proportion,
                        'aid'=>$aid,
                        'add_time'=>time(),
                        'cid'=>$cid,
                    ]);
                }
                $o_s_t_id = $id;
                Db::commit();
            }catch (Exception $e) {
                Db::rollback();
                write_log(var_export($e->getMessage(),true),'add_sortation_err1_');
                $this->error('添加分拣任务失败');
            }


        }else{
            $o_s_t_id = $o_s_t['id'];
            $sum_product_num = OutSortationTaskItemModel::where(['wh_c_product_id'=>$w_h_c_i['id'],'sortation_task_id'=>$o_s_t['id'],'batch'=>$w_h_c_i['batch'],'product_id'=>$w_h_c_i['product_id']])->sum('product_num');

            if($sum_product_num+$num>$w_h_c_i['product_num']){
                $this->error('分拣合计数量超过了库存');
            }
            //添加分拣内容
             OutSortationTaskItemModel::insertGetId([
                'sortation_task_id'=>$o_s_t['id'],
                'wh_c_id'=>$w_h_c_i['wh_c_id'],
                'wh_c_numbering'=>$w_h_c_i['wh_c_numbering'],
                'wh_c_product_id'=>$w_h_c_i['id'],
                'product_id'=>$w_h_c_i['product_id'],
                'product_num'=>$num,
                'batch'=>$w_h_c_i['batch'],
                'proportion'=>$proportion,
                'aid'=>$aid,
                'add_time'=>time(),
                'cid'=>$cid,
            ]);
            //任务状态
           /* OutSortationTaskModel::update(
                [
                    'status'=>5
                ],[
                    'id'=>$o_s_t['id']
                ]
            );*/
        }

        $this->success('ok',['id'=>$o_s_t_id,'status_str'=>'进行中','status'=>5]);

    }


    //删除分拣
    public function del_sortation($sortation_task_item_id='',$aid='',$cid=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $sortation_task_item_id = $sortation_task_item_id?$sortation_task_item_id:$this->_get_param('sortation_task_item_id'); //分拣物料id

        $o_s_t_item = OutSortationTaskItemModel::where(['id'=>$sortation_task_item_id])->find();

        if(!empty($o_s_t_item)){

            $o_s_t_info = OutSortationTaskModel::where(['id'=>$o_s_t_item['sortation_task_id'],'status'=>['in','1,5']])->find();
            //限定任务进行中，可删除 status 5
            if(!empty($o_s_t_info)){

                OutSortationTaskItemModel::where(['id'=>$sortation_task_item_id])->delete();

                $this->success('分拣任务物料删除成功',['sortation_task_id'=>$o_s_t_item['sortation_task_id']]);
            }


        }

        $this->error('分拣任务物料删除失败');

    }

    //分拣状态设置
    public function status_sortation($sortation_task_id='',$status='',$aid='',$cid=''){

        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $sortation_task_id = $sortation_task_id?$sortation_task_id:$this->_get_param('sortation_task_id'); //分拣任务id
        $status = $status?$status:$this->_get_param('status'); //状态


        OutSortationTaskModel::update([
            'status'=>$status,
        ],[
            'id'=>$sortation_task_id

        ]);

        if($status==3){ //分拣任务完成 计算
            $o_s_t_i_info = OutSortationTaskItemModel::where(['sortation_task_id'=>$sortation_task_id])->select();
            //创建出库订单
            $items =[];
            if(!empty($o_s_t_i_info)){
                foreach ($o_s_t_i_info as $val){
                    $p_info = ProductModel::where(['id'=>$val['product_id']])->find();
                    $items[] = [
                        'product_name'=>$p_info['name'],
                        'num'=>$val['product_num'],
                        'batch'=>$val['batch'],
                    ];
                }

                Db::startTrans();
                try{

                    //出库订单
                    $id = OutOrderModel::insertGetId([
                        'order_sn'=>get_sys_order_sn('CKDD'),
                        'wh_id'=>1,//单仓库
                        'status'=>3,//完成
                        'business_type_id'=>73,//其他出库
                        'items'=>json_encode($items),
                        'aid'=>$aid,
                        'add_time'=>time(),
                        'sortation_task_id'=>$sortation_task_id,
                        'cid'=>$cid,
                    ]);
                    if($id){
                        //出库订单明细
                        foreach ($o_s_t_i_info as $val){
                            //出库单明细
                            $p_info = ProductModel::where(['id'=>$val['product_id']])->find();
                            OutOrderItemModel::insertGetId([
                                'out_order'=>$id,
                                'product_name'=>$p_info['name'],
                                'product_id'=>$val['product_id'],
                                'product_numbering'=>$p_info['numbering'],
                                'product_spec'=>$p_info['spec'],
                                'sys_dict_id'=>5,//合格
                                'num'=>$val['product_num'],
                                'unit_id'=>1,//件
                                'batch'=>$val['batch'],
                                'add_time'=>time(),
                                'cid'=>$cid,
                            ]);
                            //分拣容器结算
                            $w_h_c_i_info = WarehouseContainerItemModel::where([
                                'wh_c_id'=>$val['wh_c_id'],
                                'product_id'=>$val['product_id'],
                                'batch'=>$val['batch'],

                            ])->find();
                            WarehouseContainerItemModel::update([
                                'product_num'=>$w_h_c_i_info['product_num']-$val['product_num'],
                                'up_time'=>time(),
                                'aid'=>$aid,
                            ],[
                                'wh_c_id'=>$val['wh_c_id'],
                                'product_id'=>$val['product_id'],
                                'batch'=>$val['batch'],

                            ]);
                            //占比扣除-暂不做
                            $proportion_num = $this->get_product_proportion($val['proportion']);
                            $container =WarehouseContainerModel::where(['numbering'=>$val['wh_c_numbering']])->find();
                            $proportion_num_c = $this->get_product_proportion($container['proportion']);

                            //更新容器占比
                            WarehouseContainerModel::update([
                                'proportion'=>$this->get_product_proportion('',$proportion_num_c-$proportion_num),
                            ],[
                                'id'=>$container['id'],
                            ]);
                        }

                    }
                    

                    Db::commit();
                }catch (Exception $e) {
                    Db::rollback();
                    write_log(var_export($e->getMessage(),true),'status_sortation_err_');
                    $this->error('创建分拣订单 失败');
                }


            }

            //

        }
        $this->success('分拣任务状态更新成功',['id'=>$sortation_task_id,'status_str'=>'已完成','status'=>3]);

    }


    //获取分拣列表
    public function get_sortation_list($sortation_task_id='',$w_b_id='',$task_status='',$aid='',$cid=''){
        $cid = $cid?$cid:$this->_get_param('cid'); //渠道id
        $sortation_task_id = $sortation_task_id?$sortation_task_id:$this->_get_param('sortation_task_id',''); //分拣任务id
        $aid = $aid?$aid:$this->_get_param('aid'); //管理员id
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id',''); //工作位id
        $task_status = $task_status?$task_status:$this->_get_param('task_status',''); //任务状态


        $o_s_t_i_list=[];

        if(!empty($sortation_task_id)){
            $o_s_t_i_list = OutSortationTaskItemModel::where(['sortation_task_id'=>$sortation_task_id])->select();
        }elseif(!empty($w_b_id)){
            $where =[
                'w_b_id'=>$w_b_id,
            ];
            if(!empty($task_status)){
                $where['status'] =$task_status;
            }
            $o_s_t_info = OutSortationTaskModel::where($where)->find();
            if(!empty($o_s_t_info)){
                $o_s_t_i_list = OutSortationTaskItemModel::where(['sortation_task_id'=>$o_s_t_info['id']])->select();
            }
        }



        if(!empty($o_s_t_i_list)){
            foreach ($o_s_t_i_list as $key=>$val){
                $p_info = ProductModel::where(['id'=>$val['product_id']])->find();
                if($p_info)$o_s_t_i_list[$key]['product_name'] = $p_info['name'];
                
            }
            $this->success('ok',$o_s_t_i_list);
        }else{
            $this->error('当前工作位无进行中分拣列表');
        }

    }


    //获取可入库库位
    public function get_location_code($w_id='',$w_b_id='',$cid='',$ret_type='api'){
        $where = [];
        $where['cid'] = $cid?$cid:$this->_get_param('cid'); //渠道id
        $where['status'] = 1; //状态:1=可用,2=出入库中,3=锁定
        // 排除工作位
        $workbench = WorkbenchBitModel::where('cid',$cid)->column('wh_l_id');
        // 排出已使用货架
        $container = WarehouseContainerModel::where('cid',$cid)->where('wh_l_id','>',0)->column('wh_l_id');
        $workbench = array_merge($workbench,$container);
        if(!empty($workbench)){
            $where['id'] = ['not in',$workbench];
        }
        // 检查库区
        $w_b_id = $w_b_id?$w_b_id:$this->_get_param('w_b_id','');//工作位id
        $wh_l_id = WorkbenchBitModel::where(['id'=>$w_b_id])->value('wh_l_id');
        $wh_a_id = WarehouseLocationModel::where(['id'=>$wh_l_id])->value('wh_a_id');
        // ctu
        if(!empty($wh_a_id) && $wh_a_id == 1){
            $where['wh_a_id'] = 1;
        }
        // agv
        if(!empty($wh_a_id) && $wh_a_id == 2){
            $where['wh_a_id'] = 2;
            // 限制为一个面
            // $where['depth'] = 1;

            $where['type'] = 1;
            $where['shelves_id'] = ['NEQ',10]; // 货架10移动货架
            // 确定4层货架存在料箱
            // $used = WarehouseContainerModel::where(['wh_a_id'=>2,'wh_s_id'=>['NEQ',10]])->column('wh_l_id');
            // $where['id'] = ['not in',$used];
            

        }
        
        $location = WarehouseLocationModel::where($where)->order('shelves_id desc')->find();

        if($ret_type=='api'){
            $this->success('ok',$location);
        }else{
            return $location;
        }

    }



    //任务回调
    public function callback_task(){

        $notifyParams = input();
        $req_data = file_get_contents("php://input");
        $lot = new LfbIotController();

        write_log(var_export($notifyParams,true),'callback_task_arr_');
        write_log(var_export($req_data,true),'callback_task_1');

        if(!empty($notifyParams['taskCode']) && !empty($notifyParams['status'])){
            //获取任务
            $task = IotTaskModel::where(['task_code'=>$notifyParams['taskCode'],'status'=>['in','2,5']])->find(); //执行中任务
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
                    case 'suspend': //暂停 hai_q 接口返回3 时任务完成
                        //$this->up_task_ok_info($task);
                        $task_data =[
                            'status'=>5,
                            'up_time'=>time(),
                        ];
                        //取库位信息-目标库位
                        /*$location = WarehouseLocationModel::where(['real_location'=>$task['to_location_code'],'status'=>'2'])->find();//出入库中
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
                                        //修改容器状态 出库
                                        WarehouseContainerModel::update([
                                            'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                                            'wh_l_numbering'=>$location['numbering'],
                                            'wh_l_real_location'=>$location['real_location'],
                                            'wh_l_id'=>$location['id'],
                                            'w_id'=>$w_b['w_id'],
                                            'w_numbering'=>$w_b['w_numbering'],
                                            'w_real_station'=>$w_b['w_numbering'],//设置和工作台编号一致 有问题再改
                                            'w_b_id'=>$w_b['id'],//工作位id
                                            'w_b_real_location'=>$w_b['real_location'],//工作位编码
                                            'up_time'=>time(),
                                        ],[
                                            'id'=>$container['id'],
                                        ]);
                                        //容器移动日志---这个没加进去？
                                        WarehouseContainerMoveModel::insertGetId([
                                            'wh_c_id'=>$container['id'],
                                            'from_wh_l_id'=>$container['wh_l_id'],
                                            'from_w_id'=>$container['w_id'],
                                            'target_wh_l_id'=>$location['id'],
                                            'target_w_id'=>$w_b['w_id'],
                                            'target_w_b_id'=>$w_b['id'],
                                            'task_id'=>$task['id'],
                                            'cid'=>$task['cid'],
                                            'up_time'=>time(),
                                        ]);


                                    }else{ //入库
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
                                            'cid'=>$task['cid'],
                                            'up_time'=>time(),
                                        ]);
                                        //目标库位锁定
                                        WarehouseLocationModel::update([
                                            'status'=>3, //状态:1=可用,2=出入库中,3=锁定
                                            'up_time'=>time(),
                                        ],[
                                            'id'=>$location['id'],
                                        ]);
                                    }
                                    Db::commit();
                                   // $this->error('ok');
                                }catch (Exception $e) {
                                    Db::rollback();
                                    write_log(var_export($e->getMessage(),true),'callback_task_err_');
                                    $this->error('任务回调 容器库位 出库状态 更新失败');
                                }

                            }




                        }*/
                        

                        break;
                    case 'success': //成功
                        $task_data =[
                            'status'=>3,
                            'up_time'=>time(),
                        ];
                       $this->up_task_ok_info($task);
                        $lot->query_location($task['from_location_code'].'|'.$task['to_location_code'],'','',$task['cid'],'','data');
                        //$lot->query_location($task['to_location_code'],'','',$task['cid'],'','data');



                        break;
                }

                IotTaskModel::update($task_data,[
                    'task_code'=>$notifyParams['taskCode'],
                ]);

            }

        }

        $this->success('ok');

    }
    
    //出入口任务完成时处理
    public function up_task_ok_info($task){

        

        Db::startTrans();
        try{


            //取库位信息-原库位 出入库成功时 原库位都要可用
            $location1 = WarehouseLocationModel::where(['real_location'=>$task['from_location_code']])->find();//出入库中
            if(!empty($location1)){
                //原库位恢复可用
                WarehouseLocationModel::update([
                    'status'=>1, //状态:1=可用,2=出入库中,3=锁定
                    'up_time'=>time(),
                ],[
                    'id'=>$location1['id'],
                ]);

            }

            $w_b =[];
            $w_c_up_data = [];
            //入库
            if($task['type']==1 || $task['type']==3){
                //目标库位
                $location = WarehouseLocationModel::where(['real_location'=>$task['to_location_code']])->find();//出入库中
                if(!empty($location)){
                    //目标库位锁定
                    WarehouseLocationModel::update([
                        'status'=>2, //状态:1=可用,2=出入库中,3=锁定
                        'up_time'=>time(),
                    ],[
                        'id'=>$location['id'],
                    ]);
                }
                $w_b = WorkbenchBitModel::where(['real_location'=>$task['from_location_code']])->find();
                $w_c_up_data['numbering'] = $location['numbering'];
                $w_c_up_data['real_location'] = $location['real_location'];
                $w_c_up_data['id'] = $location['id'];

            }
            //出库
            if($task['type']==2 || $task['type']==4){
                $w_b = WorkbenchBitModel::where(['real_location'=>$task['to_location_code']])->find();
                $w_c_up_data['numbering'] = $w_b['real_location'];
                $w_c_up_data['real_location'] = $w_b['real_location'];
                $w_c_up_data['id'] = $w_b['wh_l_id'];
            }
            write_log(var_export($w_b,true),'callback_task_w_b_');
            write_log(var_export($w_c_up_data,true),'callback_task_w_c_up_');

            if(!empty($w_b) && !empty($w_c_up_data)){

                $container =  WarehouseContainerModel::where([
                    'numbering'=>$task['container_code'],
                ])->find();
                //移动日志
                WarehouseContainerMoveModel::insertGetId([
                    'wh_c_id'=>$container['id'],
                    'from_wh_l_id'=>$container['wh_l_id'],
                    'from_w_id'=>$container['w_id'],
                    'target_wh_l_id'=>$w_c_up_data['id'],
                    'target_w_id'=>$w_b['w_id'],
                    'target_w_b_id'=>$w_b['id'],
                    'task_id'=>$task['id'],
                    'cid'=>$task['cid'],
                    'up_time'=>time(),
                ]);

                //修改容器状态
                WarehouseContainerModel::update([
                    'status'=>2, //容器状态:1=创建,2=可用,3=出入库中,4=锁定
                    'wh_l_numbering'=>$task['to_location_code'],
                    'wh_l_real_location'=>$task['to_location_code'],
                    'wh_l_id'=>$w_c_up_data['id'] ,
                    'w_id'=>$w_b['w_id'],
                    'w_numbering'=>$w_b['w_numbering'],
                    'w_real_station'=>$w_b['w_numbering'],//设置和工作台编号一致 有问题再改
                    'w_b_id'=>$w_b['id'],//工作位id
                    'w_b_real_location'=>$w_b['real_location'],//工作位编码
                    'up_time'=>time(),
                ],[
                    'numbering'=>$task['container_code'],
                ]);

            }
            Db::commit();
        }catch (Exception $e) {
            Db::rollback();
            write_log(var_export($e->getMessage(),true),'callback_task_err1_');
            $this->error('任务回调 容器库位 出库状态 更新失败');
        }

        //关联播种墙 需要亮灯
        /*if($task['type']==2 || $task['type']==4){
            //亮灯任务下发
            $pb = PickWallBit::where(['wh_l_numbering'=>$task['to_location_code']])->find();
            $task_code = 'c2s'.$this->getMillisecond();
            //创建任务
            $task_data1 =[
                'task_code'=>$task_code,
                'dev_code'=>$pb['wall_numbering'],
                'index'=>$pb['sort'],
                'type'=>'set_win_led',
            ];
            $this->add_task($task['cid'],$task['aid'],'bz_task',$task_data1,$task_code,$task_code,$task['w_id'],$task['w_b_id'],$task['container_code'],$task['to_location_code'],$task['from_location_code']);

            $lot = new LfbIotController();
            $ret = $lot->bz_set($task_code,$pb['wall_numbering'],$pb['sort'],'set_win_led','',$task['cid'],'data');
            //$ret = json_decode($ret,true);
            write_log(var_export($ret,true),'bz_set_ret1_');


        }*/
    }


    //播种墙按键回调
    public function bz_callback_task(){

        $notifyParams = input();
        $req_data = file_get_contents("php://input");

        write_log(var_export($notifyParams,true),'bz_callback_task_arr_');
        write_log(var_export($req_data,true),'bz_callback_task_1');

        if(!empty($notifyParams['pid']) && !empty($notifyParams['index']) && !empty($notifyParams['dev_code'])){
            //入库
            $pb = PickWallBit::where(['real_location'=>$notifyParams['dev_code'],'sort'=>$notifyParams['index']])->find();
            if(!empty($pb)){

                //$task = IotTaskModel::where(['task_code'=>$notifyParams['pid']])->find();
                //获取目标库位 暂时媛库位回去 
                /*$container =  WarehouseContainerModel::where([
                    'numbering'=>$task['container_code'],
                ])->find();
                $wcm = WarehouseContainerMoveModel::where(['wh_c_id'=>$container['id']])->order('up_time DESC')->find();
                $wjl = WarehouseLocationModel::where(['from_wh_l_id'=>$wcm['id']])->find();*/
                $wb = WorkbenchBitModel::where(['real_location'=>$pb['wh_l_numbering']])->find();
                //取目标工作位 可用
                $location = $this->get_location_code('','',$wb['cid'],'data');
                $wc = WarehouseContainerModel::where(['w_b_real_location'=>$pb['wh_l_numbering']])->find(); //要考虑 容器要跟播种墙 进行绑定 另建表 后面处理

                $ret = $this->tote_inbound($wc['numbering'],'',$pb['wh_l_numbering'],$location['real_location'],$wb['w_id'],$wb['id'],$wb['cid'],$wb['aid']);
                write_log(var_export(json_decode($ret,true),true),'bz_callback_task_');
            }
            $this->success('ok');
        }else{
            $this->error('off');
        }
    }

    // agv计算入库库位与剩余库位
    public function count_container_inbound($container_code,$cid,$aid,$w_id,$w_b_id,$container_type=1){
        $container_code_array = explode(',',$container_code);
        $num = count($container_code_array);
        
        //  计算库内剩余库位
        $count_location = WarehouseLocationModel::where('wh_a_id',2)->where('type',1)->where('status',1)->where('shelves_id','<>',10)->count();
        $used_location = WarehouseContainerModel::where('wh_a_id',2)->where('wh_s_id','<>',10)->count();
        $use = $count_location - $used_location;
        if($use == 0){
            return ['status'=>0,'msg'=>'库内无可用库位'];
        }
        if($num > $use){
            return ['status'=>0,'msg'=>'库内可用库位不足'];
        }
        // 计算每个货架有几个空的
        $count = 24; // 每个货架24个库位
        // 已使用的货架的库位数
        $use_container = WarehouseContainerModel::field('wh_s_id,COUNT(*) as used_num')->where('wh_a_id',2)->where('wh_s_id','<>',10)->group('wh_s_id')->select();
        $shelves_list = WarehouseShelvesModel::where('id','<>',10)->column('id');
        $used_map = array_column($use_container, 'used_num', 'wh_s_id');
        $shelf_array = [];
        foreach ($shelves_list as $shelf_id) {
            // 如果该货架有使用记录，则计算剩余空位；否则使用默认值
            $used_num = $used_map[$shelf_id] ?? 0;
            if($used_num == $count){
                continue; // 该货架已满
            }
            $shelf_array[$shelf_id] = $count - $used_num;
        }
        // 看多少个货架与货架内的仓位
        asort($shelf_array);
        $use_shelves = [];
        foreach($shelf_array as $k => $v){
            if($v - $num >0){
                $use_shelves[$k] = $num;
                break;
            }else if($v - $num ==0){
                $use_shelves[$k] = $num;
                break;
            }else{
                $num -= $v;
                $use_shelves[$k] = $v;
            }
        }
        $array = [];
        foreach ($use_shelves as $key => $value) {
            // 本货架上有料箱的库位
            $not_in_ids = WarehouseContainerModel::where('wh_s_id',$key)->where('wh_l_id','>',0)->column('wh_l_id');
            // 去除有料箱的库位
            $find_a = WarehouseLocationModel::where('id','not in',$not_in_ids)->where('status',1)->where('type',1)->where('shelves_id',$key)->where('depth',1)->limit($value)->select();
            if(count($find_a) > 0 && count($find_a) == $value){
                $array[$key] = $find_a;
                continue;
            }
            $find_num = $value-count($find_a);
            if($find_num >0){
                $find_b = WarehouseLocationModel::where('id','not in',$not_in_ids)->where('status',1)->where('type',1)->where('shelves_id',$key)->where('depth',2)->limit($find_num)->select();     
                $array[$key] = array_merge($find_a,$find_b);
            }
        }
        $container_code_ids = WarehouseContainerModel::where('numbering','in',$container_code_array)->column('wh_l_id');
        // $container_location_list = WarehouseLocationModel::where('id','in',$container_code_ids)->order('depth asc')->select();
        // 找三层货架目前的方向
        $from_shelves = WarehouseShelvesModel::where('id',10)->find();
        if(empty($from_shelves['rotate']) || $from_shelves['rotate'] > 0){
            $rotate = 1;
            $ten_sort = 'depth desc';
        }else{
            $rotate = 2;
            $ten_sort = 'depth asc';
        }



        $container_location_list = Db::name('warehouse_location')->alias('l')
            ->join('warehouse_container c','l.id = c.wh_l_id')
            ->field('l.*,c.numbering as container_code')
            ->where('c.numbering','in',$container_code_array)
            ->where('l.shelves_id',10)->where('l.type',1)
            ->order($ten_sort)->select();
        // 3层分割
        $split_location_list = $this->splitArrayBySizes($container_location_list, $use_shelves);
        $add_arr = []; //机械臂任务添加
        $task_code_arr = []; //小车任务号
        $task_ids = []; //小车任务id
        $shelf_number = ''; //货架号
        $location_list = []; //入库库位列表
        // $task_code,$task_code_shelf,$shelf_number
        $sort = 1;  //机械臂任务排序
        
        foreach($split_location_list as $k => $v){
            // $vv里面是3层货架的a，b面混的
            $to_shelves = WarehouseShelvesModel::where('id',$k)->find();
            $container_task_code = array_column($v,'container_code');
            $container_task_code = implode(',',$container_task_code);
            if($shelf_number == ''){
                $shelf_number = $to_shelves['name'];
            }
            if(count($task_code_arr) == 0){
                $all_container_code = $container_code;
                $task_code = 'task_tote_inbound_agv_'.$this->getMillisecond();
                $task_data =[
                    'task_code'=>$task_code,
                    'container_code'=>$all_container_code,
                    'to_location_code'=>$to_shelves['name'],
                    'from_location_code'=>$from_shelves['name'],
                    'container_type'=>$container_type,
                ];
                // 添加小车任务
                $task_id = $this->add_task($cid,$aid,'tote_inbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$all_container_code,$to_shelves['name'],$from_shelves['name']);
                $task_code_arr[] = $task_code;
                $task_ids[] = $task_id;
            }
            $task_code = 'task_tote_inbound_agv_shelf_'.$this->getMillisecond();
            
            $task_data =[
                'task_code'=>$task_code,
                'container_code'=>$container_task_code,
                'to_location_code'=>$to_shelves['name'],
                'from_location_code'=>$from_shelves['name'],
                'container_type'=>$container_type,
            ];
            // 添加小车任务
            $task_id = $this->add_task($cid,$aid,'tote_inbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$container_task_code,$to_shelves['name'],$from_shelves['name']);
            $task_code_arr[] = $task_code;
            $task_ids[] = $task_id;
            // 机械臂明细
            foreach($v as $kk => $vv){
                $location_list[] = $array[$k][$kk]['real_location'];
                if ($rotate == 1) {
                    $status = ($vv['depth'] == 2 && $array[$k][$kk]['depth'] == 1) ? 1 : 6;
                }
                if ($rotate == 2) {
                    $status = ($vv['depth'] == 1 && $array[$k][$kk]['depth'] == 1) ? 1 : 6;
                }
                  
                $add_arr[] = [
                    'to_location_code'=>$array[$k][$kk]['real_location'],
                    'from_location_code'=>$vv['real_location'],
                    'from_depth'=>$vv['depth'],
                    'to_depth' => $array[$k][$kk]['depth'],
                    'sort'=>$sort++,
                    'num'=>$vv['roadway'],
                    'layer'=>1,
                    'status' => $status,
                    'type' => 1,
                    'iot_id' => $task_id,
                    'container_code' => $vv['container_code'],
                    'addtime' => time(),
                ];
                $add_arr[] = [
                    'to_location_code'=>$array[$k][$kk]['real_location'],
                    'from_location_code'=>$vv['real_location'],
                    'from_depth'=>$vv['depth'],
                    'to_depth' => $array[$k][$kk]['depth'],
                    'sort'=>$sort++,
                    'num'=>$array[$k][$kk]['roadway'],
                    'layer'=>4,
                    'status' => $status,
                    'type' => 1,
                    'iot_id' => $task_id,
                    'container_code' => $vv['container_code'],
                    'addtime' => time(),
                ];   
            }   
        }
       
        // 添加机械臂任务
        $add_res = RobotarmModel::insertAll($add_arr);
        return ['status'=>1,'data'=>[
            'task_code_arr' => $task_code_arr,
            'task_ids' => $task_ids,
            'shelf_number' => $shelf_number,
            'add_arr' => $add_res,
            'to_location_list' => $location_list,
        ]];
    }



    public function count_container_outbound($container_code,$cid,$aid,$w_id,$w_b_id,$container_type=1){
        $container_code_array = explode(',',$container_code);
        $num = count($container_code_array);
        
        //  计算库内剩余库位
        $count_location = WarehouseLocationModel::where('wh_a_id',2)->where('type',1)->where('status',1)->where('shelves_id',10)->count();
        $used_location = WarehouseContainerModel::where('wh_a_id',2)->where('wh_s_id',10)->count();
        $use = $count_location - $used_location;
        if($use == 0){
            return ['status'=>0,'msg'=>'库内无可用库位'];
        }
        if($num > $use){
            return ['status'=>0,'msg'=>'库内可用库位不足'];
        }
        // 已使用的货架的库位数
        $use_container = WarehouseContainerModel::field('wh_s_id,COUNT(*) as used_num')->where('numbering','in',$container_code_array)->where('wh_a_id',2)->where('wh_s_id','<>',10)->group('wh_s_id')->select();
        
        $used_map = array_column($use_container, 'used_num', 'wh_s_id');
       
        $array = [];
        foreach ($used_map as $key => $value) {
            // 本货架要出库的料箱的库位
            $not_in_ids = WarehouseContainerModel::where('wh_s_id',$key)->where('numbering','in',$container_code_array)->where('wh_l_id','>',0)->column('wh_l_id');
            // 找没料箱的库位
            $find_a = WarehouseLocationModel::where('id','in',$not_in_ids)->where('status',1)->where('type',1)->where('shelves_id',$key)->where('depth',1)->limit($value)->select();
            foreach($find_a as $k=>$v){
                $container_code_find = Db::name('warehouse_container')->where('wh_l_id',$v['id'])->find();
                $find_a[$k]['container_code'] = $container_code_find['numbering'];
                
            }
            if(count($find_a) > 0 && count($find_a) == $value){
                $array[$key] = $find_a;
                continue;
            }
            $find_num = $value-count($find_a);
            if($find_num >0){
                $find_b = WarehouseLocationModel::where('id','in',$not_in_ids)->where('status',1)->where('type',1)->where('shelves_id',$key)->where('depth',2)->limit($find_num)->select();     
                foreach($find_b as $k=>$v){
                    $container_code_find = Db::name('warehouse_container')->where('wh_l_id',$v['id'])->find();
                    $find_b[$k]['container_code'] = $container_code_find['numbering'];
                    
                }
                $array[$key] = array_merge($find_a,$find_b);
            }
        }
        
        $container_code_ids = WarehouseContainerModel::where('wh_s_id',10)->where('wh_l_id','>',0)->column('wh_l_id');
        // $container_location_list = WarehouseLocationModel::where('id','in',$container_code_ids)->order('depth asc')->select();
        
        // 找三层货架目前的方向
        $ten_shelves = WarehouseShelvesModel::where('id',10)->find();
        if(empty($ten_shelves['rotate']) || $ten_shelves['rotate'] > 0){
            $rotate = 1;
            $ten_sort = 'depth desc';
        }else{
            $rotate = 2;
            $ten_sort = 'depth asc';
        }
        
        
        $container_location_list = Db::name('warehouse_location')
        ->where('status',1)->where('shelves_id',10)
        ->where('type',1)->where('id','not in',$container_code_ids)
        ->order($ten_sort)->select();
        // 3层分割
        $split_location_list = $this->splitArrayBySizes($container_location_list, $used_map);
        $add_arr = []; //机械臂任务添加
        $task_code_arr = []; //小车任务号
        $task_ids = []; //小车任务id
        $shelf_number = ''; //货架号
        $location_list = []; //入库库位列表
        // $task_code,$task_code_shelf,$shelf_number
        $sort = 1;  //机械臂任务排序
        
        
        foreach($array as $k => $v){
            // $vv里面是3层货架的a，b面混的
            $from_shelves = WarehouseShelvesModel::where('id',$k)->find();
            $container_task_code = array_column($v,'container_code');
            
            
            $container_task_code = implode(',',$container_task_code);
            if($shelf_number == ''){
                $to_shelves = WarehouseShelvesModel::where('id',$k)->find();
                $shelf_number = $to_shelves['name'];
            }
            if(count($task_code_arr) == 0){
                $all_container_code = $container_code;
                $task_code = 'task_tote_outbound_agv_'.$this->getMillisecond();
                $task_data =[
                    'task_code'=>$task_code,
                    'container_code'=>$all_container_code,
                    'to_location_code'=>$to_shelves['name'],
                    'from_location_code'=>$from_shelves['name'],
                    'container_type'=>$container_type,
                ];
                // 添加小车任务
                $task_id = $this->add_task($cid,$aid,'tote_outbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$all_container_code,$to_shelves['name'],$from_shelves['name']);
                $task_code_arr[] = $task_code;
                $task_ids[] = $task_id;
            }
            $task_code = 'task_tote_outbound_agv_shelf_'.$this->getMillisecond();
            
            $task_data =[
                'task_code'=>$task_code,
                'container_code'=>$container_task_code,
                'to_location_code'=>$to_shelves['name'],
                'from_location_code'=>$from_shelves['name'],
                'container_type'=>$container_type,
            ];
            // 添加小车任务
            $task_id = $this->add_task($cid,$aid,'tote_outbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$container_task_code,$to_shelves['name'],$from_shelves['name']);
            $task_code_arr[] = $task_code;
            $task_ids[] = $task_id;
            // 机械臂明细
            foreach($v as $kk => $vv){
                $location_list[] = $vv['real_location'];
                if ($rotate == 1) {
                    $status = ($vv['depth'] == 1 && $split_location_list[$k][$kk]['depth'] == 2) ? 1 : 6;
                }
                if ($rotate == 2) {
                    $status = ($vv['depth'] == 1 && $split_location_list[$k][$kk]['depth'] == 1) ? 1 : 6;
                }  
                $add_arr[] = [
                    'to_location_code'=>$split_location_list[$k][$kk]['real_location'],
                    'from_location_code'=>$vv['real_location'],
                    'from_depth'=>$vv['depth'],
                    'to_depth' => $split_location_list[$k][$kk]['depth'],
                    'sort'=>$sort++,
                    'num'=>$vv['roadway'],
                    'layer'=>3,
                    'status' => $status,
                    'type' => 2,
                    'iot_id' => $task_id,
                    'container_code' => $vv['container_code'],
                    'addtime' => time(),
                ];
                $add_arr[] = [
                    'to_location_code'=>$split_location_list[$k][$kk]['real_location'],
                    'from_location_code'=>$vv['real_location'],
                    'from_depth'=>$vv['depth'],
                    'to_depth' => $split_location_list[$k][$kk]['depth'],
                    'sort'=>$sort++,
                    'num'=>$split_location_list[$k][$kk]['roadway'],
                    'layer'=>2,
                    'status' => $status,
                    'type' => 2,
                    'iot_id' => $task_id,
                    'container_code' => $vv['container_code'],
                    'addtime' => time(),
                ];   
            }   
        }
        
        // 添加机械臂任务
        $add_res = RobotarmModel::insertAll($add_arr);
        return ['status'=>1,'data'=>[
            'task_code_arr' => $task_code_arr,
            'task_ids' => $task_ids,
            'shelf_number' => $shelf_number,
            'add_arr' => $add_res,
            'to_location_list' => $location_list,
        ]];
    }

    // 分割数组
    public function splitArrayBySizes($mainArray, $chunkSizes) {
        $result = [];
        $startIndex = 0;
        
        foreach ($chunkSizes as $key => $size) {
            // 确保大小是有效的正整数
            $validSize = max(0, intval($size));
            
            if ($validSize > 0) {
                // 切割指定大小的分块
                $chunk = array_slice($mainArray, $startIndex, $validSize);
                $result[$key] = $chunk;
                $startIndex += $validSize;
            } else {
                // 如果大小为0，添加空数组
                $result[$key] = [];
            }
            
            // 如果已经切完所有元素，跳出循环
            if ($startIndex >= count($mainArray)) {
                break;
            }
        }
 
        return $result;
    }

    // ctu批量出库
    public function ctu_outbound($container,$to_location_code,$cid,$aid,$w_id,$w_b_id,$container_type=1){
        // 找库位
        $container_arr = explode(',',$container);
        $count = count($container_arr);
        $task_id = [];
        $task_code_arr = [];
        foreach($container_arr as $k => $v){
            $task_code = 'task_tote_outbound_ctu_'.$this->getMillisecond();
            $task_code_arr[] = $task_code;
            $from_location_code = WarehouseContainerModel::where(['numbering'=>$v])->find();
            if($count > 1){
                $task_data =[
                    'task_code'=>$task_code,
                    'container_code'=>$v,
                    'to_location_code'=>$to_location_code,
                    'from_location_code'=>$from_location_code['wh_l_numbering'],
                    'sort' => $k+1,
                    'count' => $count,
                ];
            }else{
                $task_data =[
                    'task_code'=>$task_code,
                    'container_code'=>$v,
                    'to_location_code'=>$to_location_code,
                    'from_location_code'=>$from_location_code['wh_l_numbering'],    
                ];
            }
            //创建任务
            $task_id[] = $this->add_task($cid,$aid,'tote_outbound',$task_data,$task_code,$task_code,$w_id,$w_b_id,$v,$to_location_code,$from_location_code['wh_l_numbering']);  
        }
        return ['status'=>1,'data'=>[
            'task_code_arr' => $task_code_arr,
            'task_ids' => $task_id,
        ]];

    }

}