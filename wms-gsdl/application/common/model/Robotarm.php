<?php

namespace app\common\model;

use think\Model;

class Robotarm extends Model
{
    protected $name = 'robotarm';
    protected $pk = 'id';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
}
