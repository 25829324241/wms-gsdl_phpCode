<?php
namespace app\api\controller\v1;

use app\common\controller\BaseApi;
use app\common\model\Channel as ChannelModel;
use app\common\model\IotConfig as IotConfigModel;
//LOT内容
use app\common\model\Project as ProjectModel;
use app\common\model\ExecuteTask as ExecuteTaskModel;
use app\common\model\ExecuteTaskReturnLog as ExecuteTaskReturnLogModel;
//LOT内容
use think\Cache;
use think\Config;

class Base extends BaseApi
{

    protected $base_api_key = '96b0b7fbde387c08dc61b699995f4e79';//内部通讯key
    protected $base_cache_time_day = '86400';//1天

    protected $whitelist_ip =[];
    protected $whitelist_url =[];

    protected $channel =[];//渠道
    protected $cid ='';//渠道

    public $msg = [
        '1'=>'成功',
        '2'=>'失败',
        '3'=>'内容不存在',
    ];


    public function _initialize()
    {
        parent::_initialize();

        $this->base_api_key = Config::get('base_api_key');
        $this->whitelist_ip = Config::get('whitelist_ip');//白名单ip
        $this->whitelist_url = Config::get('whitelist_url');//白名单ip



    }

    /**
     * @ApiInternal
     */
    //key校验
    public function check_key($api_key=''){
        $api_key = $api_key?$api_key:$this->_get_param('api_key');
        if($api_key != $this->base_api_key){
            $this->error('key off');
        }
    }
    /**
     * @ApiInternal
     */
    //渠道校验
    public function check_cid($cid=''){
        $cid = $cid?$cid:$this->_get_param('cid');

        if(empty(Cache::get('channel_'.$cid))) {
            $info = ChannelModel::where(['id'=>$cid,'status'=>'1'])->find();
            if(!empty($info)){
                Cache::set('channel_'.$cid,$info,$this->base_cache_time_day);//缓存1天
            }else{
                $this->error('cid off');
            }
        }
        $channel = Cache::get('channel_'.$cid);
        if(empty($channel)){
            $this->error('channel status off');
        }
        return $channel;

    }
    /**
     * @ApiInternal
     */
    //合并校验
    public function h_check(){
        $this->check_key();
        $this->channel = $this->check_cid();
    }
    /**
     * @ApiInternal
     */
    //获取iot配置
    public function get_iot_config($cid=''){
        $cid = $cid?$cid:$this->_get_param('cid');
        if(empty(Cache::get('iot_config_'.$cid))) {
            $info  = IotConfigModel::where(['cid'=>$cid,'status'=>1])->find();
            if(!empty($info)){
                Cache::set('iot_config_'.$cid,$info,$this->base_cache_time_day);//缓存1天
            }else{
                $this->error('cid off');
            }

        }
        $iot = Cache::get('iot_config_'.$cid);
        if(empty($iot)){
            $this->error('iot status off');
        }
        return $iot;
    }

    /**
     * @ApiInternal
     * 获取毫秒级别的时间戳
     */
    public static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode ( " ", microtime () );
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode( ".", $time );
        $time = $time2[0];
        return $time;
    }


    //===========================================LOT====================================================================
    /**
     * @ApiInternal
     */
    //项目信息
    public function get_project(){
        //获取项目信息
        $key = $this->_get_param('lfb_key');

        if(empty(Cache::get('project_'.$key))) {
            $info  = ProjectModel::where(['key'=>$key,'status'=>1])->find();
            if(!empty($info)){
                Cache::set('project_'.$key,$info,$this->base_cache_time_day);//缓存1天
            }else{
                $this->error('key2 off');
            }

        }
        $project = Cache::get('project_'.$key);
        if(empty($project)){
            $this->error('project off');
        }
        return $project;
    }


    /**
     * 执行任务记录
     * @ApiInternal
     * @param int $project_id 项目编号
     * @param string $name 任务名称
     * @param int $is_api 是否api 1是 2不是
     * @param string $api_type api类型 1=haiq,2=ess,3=sow,4=其他
     * @param string $api_url api地址
     * @param array $api_data api提交数据
     * @param bool $content 内容
     * @param int $is_timing 是否定时任务 1是 2不是
     * @param int $order 执行优先级
     * @param int $sort 执行顺序
     * @param int $status 执行状态:1=未执行,2=执行中,3=执行成功,4=执行失败
     * @return int|string
     */

    public function e_task_add($project_id,$name,$is_api,$api_type='',$api_url='',$api_data=array(),$content=false,$is_timing=2,$order=99,$sort=1,$status=2){

        $data = [
            'name'=>$name,
            'task_code'=>$name,
            'project_id'=>$project_id,
            'is_api'=>$is_api,
            'is_timing'=>$is_timing,
            'sort'=>$sort,
            'order'=>$order,
            'status'=>$status,
            'ip'=>request()->ip(),
            'up_time'=>time(),
            'add_time'=>time(),
        ];

        if($is_api=='1'){//是api
            $data['api_type']=$api_type;
            $data['api_url']=$api_url;
            $data['api_data']=serialize($api_data);
            $data['content']=json_encode($content);
        }else{
            $data['content']=base64_decode($content);
        }

        if(!empty($_SERVER)){
            $data['url']=isset($_SERVER["REQUEST_URI"])?$_SERVER["REQUEST_URI"]:'';
            $data['user_agent']=isset($_SERVER["HTTP_USER_AGENT"])?$_SERVER["HTTP_USER_AGENT"]:'';
        }

        if($status==2){
            $data['ok_time']=time();
        }
        if($status==3){
            $data['fail_time']=time();
        }


        $task_id = ExecuteTaskModel::insertGetId($data);

        return $task_id;

    }
    /**
     * @ApiInternal
     */
    //任务反馈接口
    public function ret_task_log($project_id,$name,$task_code,$type,$content){


        $data =[
            'project_id'=>$project_id,
            'task_code'=>$task_code,
            'name'=>$name,
            'type'=>$type,
            'content'=>json_encode($content),
            'ip'=>request()->ip(),
            'up_time'=>time(),
        ];

        if(!empty($_SERVER)){
            $data['url']=isset($_SERVER["REQUEST_URI"])?$_SERVER["REQUEST_URI"]:'';
            $data['user_agent']=isset($_SERVER["HTTP_USER_AGENT"])?$_SERVER["HTTP_USER_AGENT"]:'';
        }

        //海柔hai_q 反馈日志预留处理
        if(!empty($content) && isset($content['taskCode']) && isset($content['status'])){
            $data['task_code'] =$content['taskCode'];
            $data['status'] =$content['status'];
        }


        $task_id = ExecuteTaskReturnLogModel::insertGetId($data);

        return $task_id;


    }


}