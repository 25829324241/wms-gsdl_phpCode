<?php

namespace app\common\model;

use think\Model;


class BusinessType extends Model
{

    

    

    // 表名
    protected $name = 'business_type';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'is_internal_text',
        'is_callback_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getIsInternalList()
    {
        return ['1' => __('Is_internal 1'), '2' => __('Is_internal 2')];
    }

    public function getIsCallbackList()
    {
        return ['1' => __('Is_callback 1'), '2' => __('Is_callback 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsInternalTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_internal']) ? $data['is_internal'] : '');
        $list = $this->getIsInternalList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsCallbackTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_callback']) ? $data['is_callback'] : '');
        $list = $this->getIsCallbackList();
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


    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
