<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\api\controller\v1\MoveCabinets as MoveCabinetsController;
use app\common\model\WarehouseContainerItem as WarehouseContainerItemModel;//容器关联物料
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;
use app\common\model\WarehouseShelves as WarehouseShelvesModel; //货架
use app\api\controller\v1\WarehouseApi as WarehouseApiController;
use app\api\controller\v1\LfbIot as LfbIotController;
use app\api\controller\v1\Hik as HikController; //海康api控制器
use think\Session;


/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    protected $api =[];

    public function _initialize()
    {
        parent::_initialize();
        $this->api  = new WarehouseApiController();

    }
    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        $w_s_c  = Session::get("w_s_c");//工作站设置
        $w_id  = Session::get("w_id");//工作站id
        $w_b_id  = Session::get("w_b_id");//工作位id
        

        $this->view->assign([
            'totaluser'         => User::count(),
            'totaladdon'        => $totaladdon,
            'totaladmin'        => Admin::count(),
            'totalcategory'     => \app\common\model\Category::count(),
            'todayusersignup'   => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'    => User::whereTime('logintime', 'today')->count(),
            'sevendau'          => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'         => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'          => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'          => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'       => count($dbTableList),
            'dbsize'            => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'totalworkingaddon' => $totalworkingaddon,
            'attachmentnums'    => Attachment::count(),
            'attachmentsize'    => Attachment::sum('filesize'),
            'picturenums'       => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'       => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
            'w_s_c'=>$w_s_c,
            'w_id'=>$w_id,
            'w_b_id'=>$w_b_id,
            'cid'=>$this->auth->cid,//渠道id必传
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));



        return $this->view->fetch();
    }

    //获取扫码枪内容
    public function get_scan_code(){
        $type = input('type');
        $scan_code = input('scan_code');
        //$api = $this->api;
        //$ret =$api->get_code_scan($type,$scan_code,$this->auth->cid);
        $api = new WarehouseApiController();
        $api->get_code_scan($type,$scan_code,$this->auth->cid);
        /*write_log(var_export($ret,true),'get_scan_code_');
        if($type=='1'){
            $data = json_decode($ret,true);
            write_log($type.'|'.$data['data']['content'],'666');
            Session::set("w_s_c", $data['data']['content']);
        }*/
        //return json_decode($ret,true);
    }

    //扫码枪上报
    public function up_scan_code(){
        $code = input('code');
        $anchor = input('anchor');//锚点 或 页面标识
        //code过滤
        $code = str_replace("","",$code );
        $code = str_replace("½","-",$code );

        write_log($code,'code_');
        $api = new WarehouseApiController();
        $api->up_scan_code($code,$anchor,$this->auth->cid);

    }

    //编号获取内容
    public function code_to_info(){
        $code = input('code');
        $type = input('type');
        $api = new WarehouseApiController();
        $api->code_to_info($code,$this->auth->cid,$type);

    }

    //获取工作位详情
    public function get_workbench_bit(){
        $location_code = input('location_code');
        $api = new WarehouseApiController();
        $api->get_workbench_bit($location_code,$this->auth->cid);
    }

    //扫码枪工作位缓存
    public function set_scan_code(){
        $content = input('content');
        $w_b_id = input('w_b_id');
        $w_id = input('w_id');
        Session::set("w_s_c", $content);
        Session::set("w_b_id", $w_b_id);
        Session::set("w_id", $w_id);
  

    }
    //获取物料信息
    public function get_product_info(){
        $product_name = input('product_name');
        $api = new WarehouseApiController();
        $api->get_product_info($product_name,$this->auth->cid);
    }

    //添加物料信息
    public function add_product(){

        $product_name = input('product_name');
        $api = new WarehouseApiController();
        $api->add_product($product_name,$this->auth->cid);
    }

    //获取容器物料列表
    public function get_container_item_list(){
        $wh_c_numbering = input('wh_c_numbering');
        $api = new WarehouseApiController();
        $api->get_container_item_list($wh_c_numbering,$this->auth->cid);
        //return json_decode($ret,true);

    }
    //根据容器物料id获取信息
    public function get_container_item(){
        $container_item_id = input('container_item_id');
        $api = new WarehouseApiController();
        $api->get_container_item($container_item_id,$this->auth->id,$this->auth->cid);
    }


    //容器物料解绑 删除
    public function container_out_item(){
        $container_item_id = input('container_item_id');
        $api = new WarehouseApiController();
        $api->container_out_item($container_item_id,$this->auth->id,$this->auth->cid);
    }


    //容器绑定物料
    public function container_in_item(){
        $wh_c_numbering = input('wh_c_numbering');
        $product_id = input('product_id');
        $proportion = input('proportion');
        $product_num = input('product_num');
        $api = new WarehouseApiController();
        $api->container_in_item($wh_c_numbering,'',$product_id,$product_num,$proportion,$this->auth->id,$this->auth->cid);

    }

    //物料转移
    public function move_item(){
        $from_wh_c_numbering = input('from_wh_c_numbering');
        $to_wh_c_numbering = input('to_wh_c_numbering');
        $item_list = input('item_list');
        $w_id = input('w_id');//工作站
        $w_b_id = input('w_b_id');//工作位
        $wh_a_id = input('wh_a_id','1');//库区id 默认先1
        $api = new WarehouseApiController();
        $api->move_item($from_wh_c_numbering,$to_wh_c_numbering,$item_list,$w_id,$w_b_id,$wh_a_id,$this->auth->id,$this->auth->cid);
        //return json_decode($ret,true);
    }


    //工作位列表
    public function get_workbench_bit_list(){

        $status = input('status','1');//默认可用
        $api = new WarehouseApiController();
        $api->get_workbench_bit_list($this->auth->cid,$status);
        //return json_decode($ret,true);
    }

    //容器列表
    public function get_container_list(){

        $status = input('status','2');//默认可用
        $product_name = input('product_name','');//商品名称
        $number = input('num_location_code','');//工作位编码
        $type = input('type','');
        $api = new WarehouseApiController();
        $api->get_container_list($this->auth->cid,$status,$product_name,$number,$type);
        //return json_decode($ret,true);
    }

    //物料获取容器--未完成
    public function get_product_container(){
        $container_code= input('container_code');//容器编号
        $product_name = input('product_name');//物品名称
        $api = new WarehouseApiController();
        $api->get_product_container($this->auth->cid,$product_name);
    }


    //密集柜关闭
    public function move_to_off(){

        $lot = new MoveCabinetsController();
        $lot->to_off();
    }

    //容器出库
    public function container_tote_outbound(){

        $container_code = input('container_code');//容器编号
        $to_location_code = input('to_location_code');//目标库位
        $w_id = input('w_id');//工作站
        $w_b_id = input('w_b_id');//工作位
        $api = new WarehouseApiController();
        $api->tote_outbound($container_code,$to_location_code,'',$w_id,$w_b_id,'',$this->auth->cid,$this->auth->id);
    }

    //获取可入库货位
    public function get_location_code(){
        $w_id = input('w_id');//工作站
        $w_b_id = input('w_b_id');//工作位
        $api = new WarehouseApiController();
        $api->get_location_code($w_id,$w_b_id,$this->auth->cid);

    }

    //容器回库
    public function container_tote_inbound(){
        $container_code = input('container_code');//容器编号
        $from_location_code = input('from_location_code');//起始货位
        $location_code = input('location_code');//目标库位
        $w_id = input('w_id');//工作站
        $w_b_id = input('w_b_id');//工作位
        $type = Db::name('warehouse_container')->where('numbering',$container_code)->value('type');//容器类型
        $api = new WarehouseApiController();
        $api->tote_inbound($container_code,$type,$from_location_code,$location_code,$w_id,$w_b_id,$this->auth->cid,$this->auth->id);

    }
    //分拣添加
    public function add_sortation(){
        $container_code = input('container_code');//容器编号
        $container_item_id = input('container_item_id');//容器物料id
        $num= input('num');//数量
        $w_b_id = input('w_b_id');//工作位
        $proportion = input('proportion');
        $api = new WarehouseApiController();
        $api->add_sortation($container_code,$container_item_id,$num,$proportion,$w_b_id,$this->auth->id,$this->auth->cid);

    }
    //分拣删除
    public function del_sortation()
    {
        $sortation_task_item_id = input('sortation_task_item_id');
        $api = new WarehouseApiController();
        $api->del_sortation($sortation_task_item_id,$this->auth->id,$this->auth->cid);
    }


    //分拣状态设置
    public function status_sortation(){

        $sortation_task_id = input('sortation_task_id');
        $status = input('status');
        $api = new WarehouseApiController();
        $api->status_sortation($sortation_task_id,$status,$this->auth->id,$this->auth->cid);
    }

    //获取分拣列表
    public function get_sortation_list(){

        $sortation_task_id = input('sortation_task_id');//容器物料id
        $w_b_id = input('w_b_id');//工作位id
        $task_status = input('status');//任务状态

        $api = new WarehouseApiController();
        $api->get_sortation_list($sortation_task_id,$w_b_id,$task_status,$this->auth->id,$this->auth->cid);

    }

    //容器添加
    public function add_container(){
        $container_code = input('container_code');//容器编号
        $api = new LfbIotController();
        $api->add_container($container_code,'',$this->auth->cid,$this->auth->id);

    }
    //容器删除
    public function remove_container(){
        $container_code = input('container_code');//容器编号
        $api = new LfbIotController();
        $api->remove_container($container_code,$this->auth->cid);
    }
    //容器绑定
    public function move_in_container(){
        $container_code = input('container_code');//容器编号
        $location_code = input('location_code');//库位编码
        if(empty($container_code)) {
            $this->error('容器编号不能为空');
        }
        
        if(empty($location_code)) {
            $this->error('库位编码不能为空');
        }
        $find = Db::name('warehouse_container')->where('numbering',$container_code)->find();//容器类型
        if(empty($find)) {
            $this->error('容器不存在');
        }
        $type = $find['type'];
        
        $location = Db::name('warehouse_location')->where('real_location',$location_code)->find();
        $arr = [$container_code,$location_code,$type,$location['roadway'],$location];
        if($find['wh_l_numbering'] != '' && $find['wh_l_numbering'] != 0){
            $this->success('该容器已绑定过库位');
        }
        write_log(var_export('容器绑定:'.json_encode($arr),true),'bind_error_');
        $api = new HikController();
        $res = $api->bind_container($container_code,$location_code,$type,'BIND');
        // dump($res);exit;
        if(isset($res['code']) && $res['code'] == 'SUCCESS'){
                if($location['wh_a_id'] == 1){
                    Db::name('warehouse_container')
                    ->where('numbering',$container_code)
                    ->update([
                        'wh_l_id' => $location['id'],
                        'wh_l_real_location'=> $location['real_location'],
                        'wh_l_numbering'=> $location['real_location'],
                        'wh_a_id' => $location['wh_a_id'],
                        'up_time'=>time()
                    ]);
                }else{
                    Db::name('warehouse_container')
                    ->where('numbering',$container_code)
                    ->update([
                        'wh_l_id' => $location['id'],
                        'wh_l_real_location'=> $location['real_location'],
                        'wh_l_numbering'=> $location['real_location'],
                        'wh_l_roadway'=> $location['roadway'],
                        'wh_s_id' => $location['shelves_id'],
                        'wh_a_id' => $location['wh_a_id'],
                        'up_time'=>time()
                    ]);
                }
                
            
            
            $this->success('容器绑定成功');
        }else{
            $this->success('容器绑定失败');
            // $this->error($res['data'][0]['message']);
        }
        
    }
    //容器移除
    public function move_out_container(){
        $container_code = input('container_code');//容器编号
        $location_code = input('location_code');//库位编码
        if(empty($container_code)) {
            $this->error('容器编号不能为空');
        }
        
        if(empty($location_code)) {
            $this->error('库位编码不能为空');
        }
        $find = Db::name('warehouse_container')->where('numbering',$container_code)->find();//容器类型
        if(empty($find)) {
            $this->error('容器不存在');
        }
        $type = $find['type'] ?? 1;
        if($find['wh_l_numbering'] === ''){
            $this->success('该容器未绑定过库位');
        }
        
        $api = new HikController();
        $res = $api->bind_container($container_code,$location_code,$type,'UNBIND');
        // dump($res);exit;
        if( isset($res['code']) && $res['code'] == 'SUCCESS' ){
            Db::name('warehouse_container')
            ->where('numbering',$container_code)
            ->update([
                'wh_l_id' => 0,
                'wh_l_real_location'=>0,
                'wh_l_numbering'=>0,
                'wh_l_roadway'=>0,
                'wh_s_id' => 0,
                'wh_a_id' => 0,
                'up_time'=>time()
            ]);
            $this->success('容器解绑成功');
        }else{
            $this->success('容器解绑失败');
        }
    }

    public function rotate_three(){
        $type = input('param.type');
        // type == 1 ? 'B面' : 'A面';
        // b [-90,1]  a [90,2]
        $ten_shelves = WarehouseShelvesModel::where('id',10)->find();
        if(empty($ten_shelves['rotate']) || $ten_shelves['rotate'] > 0){
            $rotate = 1;
        }else{
            $rotate = 2;
        }
        if($type != $rotate){
            $this->error('当前面已是目标面，无需旋转');
        }
        $api = new HikController();
        $res = $api->rotate_three($type);
        if($res['code'] == 'failed'){
            $this->error($res['message']);
        }
        if($res['message'] == 'SUCCESS'){
            $rotate_value = $type == 1 ? -90 : 90;
            WarehouseShelvesModel::where('id',10)->update(['rotate'=>$rotate_value]);
            $this->success('旋转任务提交成功');
        }
        $this->error($res['message']);
    }

    public function move_agv_three(){ 
        $type = input('param.type');
        
        $api = new HikController();
        $res = $api->move_agv_three($type);
        if($res['code'] == 'failed'){
            $this->error($res['message']);
        }
        if($res['message'] == 'SUCCESS'){
            $this->success($res['message']);
        }
        $this->error($res['message']);
    }

    public function move_agv_four_shelf(){
        $type = input('param.type');
        
        $api = new HikController();
        $res = $api->move_agv_four_shelf($type);
        if($res['code'] == 'failed'){
            $this->error($res['message']);
        }
        if($res['message'] == 'SUCCESS'){
            $this->success($res['message']);
        }
        $this->error($res['message']);
    }

    public function get_product_warn_list(){
        $nextWeekTimestamp = strtotime('+1 week');
        $data = Db::name('product')->where('warrnty_parts','<',$nextWeekTimestamp)->select();
        $list = Db::name('move_product')->where('warn_time','<',$nextWeekTimestamp)->select();
        foreach($data as &$v){
            
            if($v['warrnty_parts'] < time()){
                $v['tips'] = '已过期';
            }else{
                $v['tips'] = '即将过期';
            }
            // 添加剩余天数
            $remainingDays = ceil(($v['warrnty_parts'] - time()) / (24 * 60 * 60));
            $v['remaining_days'] = max(0, $remainingDays);
            $v['warrnty_parts'] = date('Y-m-d H:i:s', $v['warrnty_parts']);
        }
        foreach($list as &$v){
            
            if($v['warn_time'] < time()){
                $v['tips'] = '已过期';
            }else{
                $v['tips'] = '即将过期';
            }
            // 添加剩余天数
            $remainingDays = ceil(($v['warn_time'] - time()) / (24 * 60 * 60));
            $v['remaining_days'] = max(0, $remainingDays);
            $v['warn_time'] = date('Y-m-d H:i:s', $v['warn_time']);
        }

        return json(['code'=>1,'data'=>$data,'list'=>$list]);
    }

    public function get_move_product_list(){
        $page = input('param.page');
        $limit = input('param.page_size');
        $name = input('param.product_name');
        $where = [];
        if(!empty($name)){
            $where['name'] = ['like','%'.$name.'%']; 
        } 
        // 计算偏移量
        $offset = ($page - 1) * $limit;
        $data = Db::name('move_product')->where($where)->limit($offset,$limit)->select();
        foreach($data as &$v){
            $v['warn_time'] = $v['warn_time'] >0 ? date('Y-m-d H:i:s',$v['warn_time']) : '-';
        }
        $page = Db::name('move_product')->where($where)->count();
        return json(['code'=>1,'data'=>$data,'count'=>$page]);
    }
    public function get_move_code(){
        $data = Db::name('move_code')->select();
        foreach ($data as $key=>$value){
            $list = Db::name('move_item')->where('m_id',$value['id'])->select();
            $product_list = '';
            foreach ($list as $k => $v) {
                $product = Db::name('move_product')->where('id',$v['p_id'])->find();
                if(empty($product)){
                    // $product_list = '';
                }else{
                    $product_list = $product_list.'名称:'.$product['name'].'|库存:'.$v['num'].' |  '; 
                }
                
            }
            if(!$product_list) $product_list = '空';
            $data[$key]['product_list'] = $product_list;
        }
        return json(['code'=>1,'data'=>$data]);
    }

    public function get_move_item_list(){
        $code = input('param.code');
        if(empty($code)) return json(['code'=>0,'msg'=>'库位为空']);
        $check = Db::name('move_code')->where('code',$code)->find();
        if(!$check)return json(['code'=>0,'msg'=>'该库位错误']);
        $data = Db::name('move_item')->where('m_id',$check['id'])->select();
        foreach($data as &$v){
            $product = Db::name('move_product')->where('id',$v['p_id'])->find();
            $v['code'] = $product['code'] ?? '';
            $v['images'] = $product['images'] ?? '';
            $v['location_code'] = $check['code'] ?? '';
            $v['name'] = $product['name'] ?? '';
            $v['warn_time'] = $product['warn_time'] >0 ? date('Y-m-d H:i:s',$product['warn_time']) : '-';
        }
        return json(['code'=>1,'data'=>$data]);
    }
    public function choose_move_item(){
        $id = input('param.id');
        if(empty($id)) return json(['code'=>0,'msg'=>'数据错误']);
        $data = Db::name('move_item')->where('id',$id)->find();
        $product = Db::name('move_product')->where('id',$data['p_id'])->find();
        $move_code = Db::name('move_code')->where('id',$data['m_id'])->find();
        $data['code'] = $product['code'] ?? '';
        $data['images'] = $product['images'] ?? '';
        $data['location_code'] = $move_code['code'] ?? '';
        $data['name'] = $product['name'] ?? '';
        $data['warn_time'] = date('Y-m-d H:i:s',$product['warn_time']) ?? '';
        if(empty($data)) return json(['code'=>0,'msg'=>'数据为空']);
        return json(['code'=>1,'data'=>$data]);
    }
    public function del_move_item(){
        $id = input('param.id');
        $code = input('param.code');
        $res = Db::name('move_item')->where('id',$id)->delete();
        if($res){
            return json(['code'=>1,'msg'=>'删除成功']);
        }else{
            return json(['code'=>0,'msg'=>'删除失败']);
        }
        
    }
    public function move_product_change(){
        $code = input('param.code');
        $move_code = input('param.move_code'); // 库位code
        $num = input('param.num');
        $name = input('param.name');
        $type = input('param.type'); //1放 2取
        if(empty($code))return json(['code'=>0,'msg'=>'物料编码未输入']);
        // 找对应商品
        $find = Db::name('move_product')->where('code',$code)->find();
        // 找对应库位
        $check = Db::name('move_code')->where('code',$move_code)->find();
        if(!$find){
            return json(['code'=>0,'msg'=>'该物料不存在']);
        }
        if(!$check){
            return json(['code'=>0,'msg'=>'该库位不存在']);
        }
        // 先找有没有
        $find_my = Db::name('move_item')->where('p_id',$find['id'])->where('m_id',$check['id'])->find();
        if($type == 1 && empty($find_my)){
            // 添加
            $res = Db::name('move_item')->insert([
                'p_id'=>$find['id'],
                'num'=>$num,
                'm_id'=>$check['id'],
            ]);
            if($res){
                return json(['code'=>1,'msg'=>'添加成功']);
            }else{
                return json(['code'=>0,'msg'=>'添加失败']);
            }
        }
        if(empty($find) && $type != 1){
            return json(['code'=>0,'msg'=>'数据错误，该数据不存在']);     
        }
        if($type == 1){
            $change_num = $find_my['num'] + $num;
        }else{
            $change_num = $find_my['num'] - $num;
            if($change_num < 0){
                return json(['code'=>0,'msg'=>'超过库存数量']);
            }
        }
        $res = Db::name('move_item')->where('p_id',$find['id'])->where('m_id',$check['id'])->update(['num'=>$change_num]);
        if($res){
            return json(['code'=>1,'msg'=>'更新成功']);
        }else{
            return json(['code'=>0,'msg'=>'更新失败']);
        }
    }

    public function del_sortation_container_item(){
        $id = input('param.container_item_id');
        $res = WarehouseContainerItemModel::where(['id'=>$id])->delete();
        if($res){
            return json(['code'=>1,'msg'=>'更新成功']);
        }else{
            return json(['code'=>0,'msg'=>'更新失败']);
        }
    }

}
