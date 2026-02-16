<?php
    
    namespace app\index\controller;

    use app\common\controller\Frontend;
    use think\Db;
    class Product extends Frontend
    {
        // protected $layout = 'default';
        protected $noNeedLogin = ['*'];
        protected $noNeedRight = ['*'];

        public function detail()
        {
            $id = $this->request->param('id', 0, 'intval');
            $arr = model('Product')->get($id);
            $data = $arr ? $arr->toArray() : [];  
            $this->view->assign('data', $data);
            return $this->view->fetch('detail');   
        }
        public function move_detail()
        {
            $id = $this->request->param('id', 0, 'intval');
            $data = Db::name('move_product')->where('id',$id)->find();
            $this->view->assign('data', $data);
            return $this->view->fetch('move_detail');   
        }


    }    
