<?php

namespace app\common\model;

use think\Model;


class PickWallBit extends Model
{

    

    

    // 表名
    protected $name = 'pick_wall_bit';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'add_time_text'
    ];
    

    



    public function getAddTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['add_time']) ? $data['add_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAddTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function wall()
    {
        return $this->belongsTo('app\common\model\pick\Wall', 'wall_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'aid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
