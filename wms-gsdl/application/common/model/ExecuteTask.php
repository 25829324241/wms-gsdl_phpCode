<?php

namespace app\common\model;

use think\Model;


class ExecuteTask extends Model
{

    

    

    // 表名
    protected $name = 'execute_task';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_api_text',
        'is_timing_text',
        'api_type_text',
        'status_text',
        'ok_time_text',
        'fail_time_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getIsApiList()
    {
        return ['1' => __('Is_api 1'), '2' => __('Is_api 2')];
    }

    public function getIsTimingList()
    {
        return ['1' => __('Is_timing 1'), '2' => __('Is_timing 2')];
    }

    public function getApiTypeList()
    {
        return ['1' => __('Api_type 1'), '2' => __('Api_type 2'), '3' => __('Api_type 3'), '4' => __('Api_type 4')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }


    public function getIsApiTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_api']) ? $data['is_api'] : '');
        $list = $this->getIsApiList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsTimingTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_timing']) ? $data['is_timing'] : '');
        $list = $this->getIsTimingList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getApiTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['api_type']) ? $data['api_type'] : '');
        $list = $this->getApiTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOkTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ok_time']) ? $data['ok_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getFailTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['fail_time']) ? $data['fail_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getAddTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['add_time']) ? $data['add_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['up_time']) ? $data['up_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setOkTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setFailTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setAddTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function project()
    {
        return $this->belongsTo('Project', 'project_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
