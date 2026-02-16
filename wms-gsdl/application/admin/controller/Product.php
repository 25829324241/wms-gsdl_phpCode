<?php

namespace app\admin\controller;
use think\Db;
use fast\Pinyin;
use app\common\controller\Backend;
use app\common\ZplPrinter;
use app\common\HanYinPrinter;
use app\common\HanYinPrinterOld;
/**
 * 商品管理
 *
 * @icon fa fa-circle-o
 */
class Product extends Backend
{

    /**
     * Product模型对象
     * @var \app\common\model\Product
     */
    protected $model = null;

    protected $selectpageFields = "id,name,numbering,spec";//

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\Product;
        $this->view->assign("isWarrntyPartsList", $this->model->getIsWarrntyPartsList());
        $this->view->assign("isLotOutList", $this->model->getIsLotOutList());
        $this->view->assign("inStockList", $this->model->getInStockList());
        $this->view->assign("isOnepieceList", $this->model->getIsOnepieceList());
        $this->view->assign("inControlList", $this->model->getInControlList());
        $this->view->assign("mpTypeList", $this->model->getMpTypeList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['channel'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row['warrnty_parts'] =$row['warrnty_parts']>0 ? date('Y-m-d H:i:s', $row['warrnty_parts']) : '';
                $row->getRelation('channel')->visible(['name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $params['warrnty_parts'] = $params['warrnty_parts'] ? strtotime($params['warrnty_parts']) : '';
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        // 检测name唯一性
        $exist = $this->model->where('name', $params['name'])->find();
        if ($exist) {
            $this->error('产品名称已存在');
        }
        $last_p = $this->model->order('id DESC')->find();
        if($last_p){
            // 取出编号数字部分，去掉前导P
            $num = intval(substr($last_p['numbering'], 1)) + 1;
            // 补齐7位，不足左侧补0
            $params['numbering'] = 'P' . str_pad($num, 7, '0', STR_PAD_LEFT);
        }else{
            $params['numbering'] = 'P0000001';
        }
        $data = [
            'py'=>$params['name'],
            'spec'=>'件',
            'cate_id'=>'2',
            'brand_id'=>'1',
            'measure_unit_id'=>'1',
            'measure_unit'=>'件',
            'aid'=>'1',
            'add_time'=>time(),
        ];
        $params = array_merge($params,$data);
        

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $row['warrnty_parts'] =$row['warrnty_parts']>0 ? date('Y-m-d H:i:s', $row['warrnty_parts']) : date('Y-m-d H:i:s', time());
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $params['warrnty_parts'] = $params['warrnty_parts'] ? strtotime($params['warrnty_parts']) : '';
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }


    public function move_edit($ids = null)
    {
        $row = Db::name('move_product')->where('id',$ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (false === $this->request->isPost()) {
            $row['warn_time'] =$row['warn_time']>0 ? date('Y-m-d H:i:s', $row['warn_time']) : date('Y-m-d H:i:s', time());
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = input('param.row/a');
        $params['warn_time'] = $params['warn_time'] ? strtotime($params['warn_time']) : '';
        $params['update_time'] = time();
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $res = Db::name('move_product')->where('id',$ids)->update($params);
        
        
        if($res){
            $this->success('成功',null,'121');
        }else{
            $this->error('失败',null,'121');
        }
    }
    public function move_add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $params['warn_time'] = $params['warn_time'] ? strtotime($params['warn_time']) : '';
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params['addtime'] = time();
        // 检测name唯一性
        $exist_name = Db::name('move_product')->where('name',$params['name'])->find();
        if ($exist_name) {
            $this->error('物料名称已存在');
        }
        $last_p = Db::name('move_product')->order('id DESC')->find();
        if($last_p){
            // 取出编号数字部分，去掉前导P
            $num = intval(substr($last_p['code'], 1)) + 1;
            // 补齐7位，不足左侧补0
            $params['code'] = 'M' . str_pad($num, 7, '0', STR_PAD_LEFT);
        }else{
            $params['code'] = 'M0000001';
        }
        $res = Db::name('move_product')->insert($params);
        if($res){
            $this->success('成功',null,'121');
        }else{
            $this->error('失败',null,'121');
        }
        
    }


    public function printer()
    {
        // if (!extension_loaded('printer')) {
        //     throw new \Exception("请启用php_printer扩展");
        // }
        $id = $this->request->param('id', 0, 'intval');
        if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) ){
            $url = 'https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']);
        }else{
            $url = 'http://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']);
        }
        
        $data = model('Product')->where(['id'=>$id])->field('name,numbering')->find() ?? ['name'=>'Test Product','numbering'=>'SN123456'];
        $data['url'] = $url.'/index/product/detail/id/'.$id;
        $printer_msg = $this->print_qr_code($data);
        // $data['zpl_msg'] = $this->printProductLabel();
        // $data['zpl_msg'] = $this->printProductLabelOld();
        if(isset($printer_msg['msg']) && $printer_msg['msg'] == '打印成功'){
            $code = 1;
            $msg = '打印成功';

        }else{
           $code = 0;
           $msg = '打印失败';
        }
        $data['printer'] = $printer_msg;
        return json(['data'=>$data,'code'=>$code,'msg'=>$msg]);
    }

    // 打印
    public function move_product_printer(){
        $id = input('param.id');
        $product = Db::name('move_product')->where('id',$id)->find();
        $data = [];
        $data['numbering'] = $product['code'];
        $data['name'] = $product['name'];
        if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) ){
            $url = 'https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']);
        }else{
            $url = 'http://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']);
        }
        $data['url'] = $url.'/index/product/move_detail/id/'.$id;
        
        $printer_msg = $this->print_qr_code($data);
        if(isset($printer_msg['msg']) && $printer_msg['msg'] == '打印成功'){
            $code = 1;
            $msg = '打印成功';

        }else{
           $code = 0;
           $msg = '打印失败';
        }
        $data['printer'] = $printer_msg;
        return json(['data'=>$data,'code'=>$code,'msg'=>$msg]);
    }



    // 打印机内网ip：192.168.1.221
    // TP5 测试类：
    //二维码打印
    public function print_qr_code($arr = ['url'=>'http://example.com','numbering'=>'SN123456','name'=>'Test Product']){

        try {
            // 初始化时设置标签尺寸(单位:dot) 203dpi
            // 1mm = 7.992dot;
            // 40mm = 319.7dot
            // 75mm = 599.4dot
            $printer = new ZplPrinter('192.168.31.136', 599, 319);
            // 打印前先校准
            $printer->calibrate(); 
            // 20
            $printer->resetPosition();

            $elements = [
                // 添加额外间距
                // $printer->addSpacing(15),

                // 标题文本（自动计算位置）*1.5
                $printer->generateText($arr['name'], ['size' => 30, 'x' => 'C']),

                // 添加额外间距
                // $printer->addSpacing(5),

                // 一维码（自动计算位置）+10边距
                $printer->generateBarcode($arr['numbering'], ['height' => 50]),

                // 添加额外间距
                $printer->addSpacing(20),

                // 二维码（自动计算位置）*25+10边距
                $printer->generateQRCode($arr['url'], ['size' => 4]),
                
                // 添加额外间距
                $printer->addSpacing(65),
            ];

            $zplCommand = $printer->buildZplCommand($elements);
			// 查看高度
			$height = $printer->currentY;
			
            // return $printer->printLabel($zplCommand)
            //     ? '打印成功' : '打印失败';
			
			$msg =  $printer->printLabel($zplCommand) ? '打印成功' : '打印失败';
			return array('msg'=>$msg,'height'=>$height,'labelHeight'=>$printer->labelHeight, 'labelWidth'=>$printer->labelWidth);
            
        } catch (\Error $e) {
            return '错误: '.$e->getMessage();
			
		} catch (\Exception $e) {
            return '错误: '.$e->getMessage();
        }
    }

    public function printProductLabel()
    {
        try {
            $printer = new HanYinPrinter('汉印E3plus');
            $printer->setLabelSize(80, 120); // 80mm宽 120mm高

            $contents = [
                [
                    'type' => 'text',
                    'content' => '产品检验合格证',
                    'options' => [
                        'fontSize' => 40,
                        'bold' => true,
                        'align' => 'center',
                        'font' => 'N' // 使用打印机内置字体
                    ]
                ],
                [
                    'type' => 'line',
                    'width' => 600,
                    'thickness' => 3
                ],
                [
                    'type' => 'text',
                    'content' => '产品名称：汉印打印机E3 Plus',
                    'options' => ['align' => 'left']
                ],
                [
                    'type' => 'text',
                    'content' => '型号：E3-2025',
                    'options' => ['align' => 'left']
                ],
                [
                    'type' => 'barcode',
                    'content' => 'SN20250612001',
                    'height' => 60,
                    'options' => [
                        'type' => '128',
                        'width' => 2,
                        'ratio' => 3.0
                    ]
                ],
                [
                    'type' => 'qrcode',
                    'content' => 'https://trace.com/p/20250612001',
                    'size' => 6
                ],
                [
                    'type' => 'text',
                    'content' => '生产日期：2025-06-12',
                    'options' => ['align' => 'right']
                ]
            ];

            $printer->printMixedContent($contents);
            return array('status' => 1, 'msg' => '打印成功');
        } catch (\Exception $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function printProductLabelOld()
    {
        try {
            // 192.168.1.150
            $printer = new HanYinPrinterOld(['ip'=>'192.168.1.150']);
            $printer->setLabelSize(80, 60); // 80mm×60mm标签

            $zpl = '';
            $zpl .= $printer->addText('产品标签', 0, 30, [
                'fontSize'=>50, 
                'center'=>true
            ]);
            $zpl .= $printer->addText('型号：E3 Plus', 0, 90, [
                'center'=>true
            ]);
            $zpl .= $printer->addChineseText('生产日期：2025年06月', 0, 150, [
                'fontSize'=>30,
                'center'=>true
            ]);
            $zpl .= $printer->addBarcode('SN20250612001', 100, 220);
            $zpl .= $printer->addQrcode('https://example.com/p/20250612001', 400, 200);

            $printer->print($zpl);
            return ['status'=>1];
        } catch (\Exception $e) {
            return json(['status'=>0, 'msg'=>$e->getMessage()]);
        }
    }


}
