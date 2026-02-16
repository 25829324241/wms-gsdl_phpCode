<?php

namespace app\common\model;

use think\Model;


class WarehouseContainerItem extends Model
{

    

    

    // 表名
    protected $name = 'warehouse_container_item';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'proportion_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getProportionList()
    {
        return ['1' => __('Proportion 1'), '2' => __('Proportion 2'), '3' => __('Proportion 3'), '4' => __('Proportion 4'), '5' => __('Proportion 5')];
    }


    public function getProportionTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['proportion']) ? $data['proportion'] : '');
        $list = $this->getProportionList();
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


    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'aid', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function log()
    {
        return $this->belongsTo('app\common\model\admin\Log', 'up_aid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
