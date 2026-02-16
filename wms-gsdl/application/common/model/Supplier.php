<?php

namespace app\common\model;

use think\Model;


class Supplier extends Model
{

    

    

    // 表名
    protected $name = 'supplier';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function channel()
    {
        return $this->belongsTo('Channel', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
