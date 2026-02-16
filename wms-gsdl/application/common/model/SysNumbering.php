<?php

namespace app\common\model;

use think\Model;


class SysNumbering extends Model
{

    

    

    // 表名
    protected $name = 'sys_numbering';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_orgflag_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getIsOrgflagList()
    {
        return ['1' => __('Is_orgflag 1'), '2' => __('Is_orgflag 2')];
    }


    public function getIsOrgflagTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_orgflag']) ? $data['is_orgflag'] : '');
        $list = $this->getIsOrgflagList();
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
