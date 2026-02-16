<?php

namespace app\common\model;

use think\Model;


class WarehouseShelves extends Model
{

    

    

    // 表名
    protected $name = 'warehouse_shelves';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'left_right_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getLeftRightList()
    {
        return ['1' => __('Left_right 1'), '2' => __('Left_right 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getLeftRightTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['left_right']) ? $data['left_right'] : '');
        $list = $this->getLeftRightList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
