<?php

namespace app\common\model;

use think\Model;


class WarehouseLocation extends Model
{

    

    

    // 表名
    protected $name = 'warehouse_location';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'add_time_text',
        'up_time_text',
        'task_unlock_text',
        'use_unlock_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getTaskUnlockList()
    {
        return ['1' => __('Task_unlock 1'), '2' => __('Task_unlock 2')];
    }

    public function getUseUnlockList()
    {
        return ['1' => __('Use_unlock 1'), '2' => __('Use_unlock 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
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


    public function getTaskUnlockTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['task_unlock']) ? $data['task_unlock'] : '');
        $list = $this->getTaskUnlockList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getUseUnlockTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['use_unlock']) ? $data['use_unlock'] : '');
        $list = $this->getUseUnlockList();
        return isset($list[$value]) ? $list[$value] : '';
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


    public function warehouse()
    {
        return $this->belongsTo('Warehouse', 'wh_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function area()
    {
        return $this->belongsTo('app\common\model\warehouse\Area', 'wh_a_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function shelves()
    {
        return $this->belongsTo('app\common\model\warehouse\Shelves', 'shelves_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
