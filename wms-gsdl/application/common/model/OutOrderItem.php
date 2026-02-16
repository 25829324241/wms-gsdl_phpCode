<?php

namespace app\common\model;

use think\Model;


class OutOrderItem extends Model
{

    

    

    // 表名
    protected $name = 'out_order_item';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'produce_time_text'
    ];
    

    



    public function getProduceTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['produce_time']) ? $data['produce_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setProduceTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
