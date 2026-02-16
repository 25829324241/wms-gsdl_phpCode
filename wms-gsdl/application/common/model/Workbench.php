<?php

namespace app\common\model;

use think\Model;


class Workbench extends Model
{

    

    

    // 表名
    protected $name = 'workbench';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'model_num_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getModelNumList()
    {
        return ['0' => __('Model_num 0'), '1' => __('Model_num 1'), '2' => __('Model_num 2'), '3' => __('Model_num 3')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getModelNumTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['model_num']) ? $data['model_num'] : '');
        $list = $this->getModelNumList();
        return isset($list[$value]) ? $list[$value] : '';
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

    protected function setAddTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'aid', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function area()
    {
        return $this->belongsTo('app\common\model\warehouse\Area', 'wh_a_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
