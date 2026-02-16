<?php


namespace app\common\controller;


class BaseApi extends Api
{

    protected $module = 'api';
    // 必须使用post
    protected $must_post = false;


    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取参数
     * @param      $name
     * @param      $default_value
     * @param bool $must
     * @return null
     */
    protected function _get_param($name, $default_value = null, $must = true) {
        $data = $this->request->post($name, null);
        if (!$this->must_post && $data === null) {
            $data = $this->request->param($name, $default_value);
        }
        else {
            if ($data === null) {
                $default_value === null OR $data = $default_value;
            }
        }

        $must AND $data === null AND $this->error('参数错误！');

        return $data;
    }

}