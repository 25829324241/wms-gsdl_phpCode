<?php
//测试

namespace app\api\controller\v1;

use app\admin\model\Admin as AdminModel;
use app\admin\model\AuthGroup as AuthGroupModel;
use app\admin\model\AuthGroupAccess as AuthGroupAccessModel;
use app\admin\model\AuthRule as AuthRuleModel;
use app\common\model\Attachment as AttachmentModel;
use app\common\model\Config as ConfigModel;

use app\api\controller\v1\WarehouseApi as WarehouseApiController;

use fast\Pinyin;
use iot\Hik as HikApi;

class Test extends Base
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
	
    public function get_hik(){

        $api_ip = 'https://192.168.31.66/';
        $api_url = 'rcs/rtas';
        $api_url1 = '/api/robot/controller/robot/query';
        $hik = new HikApi($api_ip,$api_url);

        $data = [
            'singleRobotCode' => '17338',
            'robotTaskCode' => '17338',
        ];
        $ret = $hik->hik_rcs_query($api_url1,$data);

        $this->success('ok',$ret);

    }


    public function get_pinyin(){
        $this->success('ok',Pinyin::get('药品'));
    }

    public function get_order_sn(){
        $this->success('ok',get_sys_order_sn('DHD'));
    }

    public function test1($task_type=''){
        $task_type = $task_type ?$task_type : $this->_get_param('task_type');
        var_dump($task_type);
        return $task_type;
    }

    public function action_test(){
        //$ret = action('test1',['777']);
        $api = new WarehouseApiController();
        echo $api->tote_inbound();

    }

    //添加渠道
    public function add_channel(){
        exit();
        //1.添加管理员
        $m_cid =1;//权限模板渠道id
        $admin = AdminModel::where('id>0')->order('cid desc')->find();
        //$z_cid = $admin['cid'];//最后的cid
        //$z_id = $admin['id'];//最后的管理员id
        $cid = $admin['cid']+1;
        $z_id= AdminModel::insertGetId(
            [    //admin admin6688
                'username'=>$admin['username'].$cid,
                'nickname'=>$admin['nickname'].$cid,
                'password'=>$admin['password'],
                'salt'=>$admin['salt'],
                'avatar'=>$admin['avatar'],
                'email'=>$admin['email'],
                'mobile'=>$admin['mobile'],
                'cid'=>$cid,

            ]
        );
        //2.添加管理员权限
        $a_r = AuthRuleModel::where('id>0 and cid='.$m_cid)->order('id desc')->find();
        $a_r_all = AuthRuleModel::where('id>0 and cid='.$m_cid)->order('id asc')->select();
        $a_r_id = $a_r['id'];//最后的权限列表id-用户组权限会用到
        foreach ($a_r_all as $val){
            if($val['pid']>0){
                $val['pid'] = $val['pid']+$a_r_id;
            }
            AuthRuleModel::insert([
                'type'=>$val['type'],
                'pid'=>$val['pid'],
                'name'=>$val['name'],//name 需要去掉唯一索引
                'title'=>$val['title'],
                'icon'=>$val['icon'],
                'remark'=>$val['remark'],
                'ismenu'=>$val['ismenu'],
                'weigh'=>$val['weigh'],
                'status'=>$val['status'],
                'cid'=>$cid,
            ]);
        }
        $a_g = AuthGroupModel::where('id>0 and cid='.$m_cid)->order('id desc')->find();
        $a_g_all = AuthGroupModel::where('id>0 and cid='.$m_cid)->order('id asc')->select();
        $a_g_id = $a_g['id'];//最后用户组id
        foreach ($a_g_all as $val){
            if($val['pid']>0){
                $val['pid'] = $val['pid']+$a_g_id;
            }
            $rules_arr = explode(",",$val['rules']);
            $rules_arr_tmp =[];
            //rite_log(var_export($rules_arr,true).'|'.$a_r_id,'arr_1_');
            if(!empty($rules_arr) && is_array($rules_arr) && $val['rules']!='*'){
                foreach ($rules_arr as $val1){
                    $rules_arr_tmp[] = $val1+$a_r_id;
                }
                $val['rules'] = implode(',',$rules_arr_tmp);
            }
            AuthGroupModel::insert([
                'pid'=>$val['pid'],
                'name'=>$val['name'],
                'rules'=>$val['rules'],
                'status'=>$val['status'],
                'cid'=>$cid,
            ]);
        }

        AuthGroupAccessModel::insert([
            'group_id'=>$a_g_id+1,
            'cid'=>$cid,
            'uid'=>$z_id,
        ]);
        //3.添加系统设置

        $c_all = ConfigModel::where('id>0 and cid='.$m_cid)->select();
        foreach ($c_all as $val){
            ConfigModel::insert([
                'name'=>$val['name'], //唯一索引 需要删除
                'group'=>$val['group'],
                'title'=>$val['title'],
                'tip'=>$val['tip'],
                'type'=>$val['type'],
                'value'=>$val['value'],
                'content'=>$val['content'],
                'rule'=>$val['rule'],
                'extend'=>$val['extend'],
                'setting'=>$val['setting'],
                'cid'=>$cid,
            ]);
        }




        $this->success('ok',$admin['id']);
    }


}