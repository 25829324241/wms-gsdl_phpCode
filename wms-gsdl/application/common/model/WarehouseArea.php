<?php

namespace app\common\model;

use think\Model;


class WarehouseArea extends Model
{

    

    

    // 表名
    protected $name = 'warehouse_area';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'class_text',
        'codedisk_model_text',
        'control_leve_text',
        'queue_point_text',
        'status_text',
        'priority_status_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getClassList()
    {
        return ['1' => __('Class 1'), '2' => __('Class 2')];
    }

    public function getCodediskModelList()
    {
        return ['1' => __('Codedisk_model 1'), '2' => __('Codedisk_model 2')];
    }

    public function getControlLeveList()
    {
        return ['1' => __('Control_leve 1'), '2' => __('Control_leve 2')];
    }

    public function getQueuePointList()
    {
        return ['1' => __('Queue_point 1'), '2' => __('Queue_point 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getPriorityStatusList()
    {
        return ['1' => __('Priority_status 1'), '2' => __('Priority_status 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getClassTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['class']) ? $data['class'] : '');
        $list = $this->getClassList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCodediskModelTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['codedisk_model']) ? $data['codedisk_model'] : '');
        $list = $this->getCodediskModelList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getControlLeveTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['control_leve']) ? $data['control_leve'] : '');
        $list = $this->getControlLeveList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getQueuePointTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['queue_point']) ? $data['queue_point'] : '');
        $list = $this->getQueuePointList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPriorityStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['priority_status']) ? $data['priority_status'] : '');
        $list = $this->getPriorityStatusList();
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


    public function warehouse()
    {
        return $this->belongsTo('Warehouse', 'wh_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'aid', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function log()
    {
        return $this->belongsTo('app\common\model\admin\Log', 'custodian_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
