<?php
//第三方外通讯接口

namespace app\api\controller\v1;

class OpenApi extends Base
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];


    public function _initialize()
    {

        parent::_initialize();
        // 校验API_KEY
        $this->check_key();

    }

    //到货单

    //发货单
}