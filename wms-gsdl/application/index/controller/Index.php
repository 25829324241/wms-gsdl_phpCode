<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        //$this->redirect('/api.html');
        //return $this->view->fetch();
        $this->redirect('/GHKLkFhjsA.php');
    }

}
