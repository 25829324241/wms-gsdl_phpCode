<?php

namespace app\common\model;

use think\Model;

class AgvTask extends Model
{
    protected $name = 'agv_task';
    protected $pk = 'id';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
}
