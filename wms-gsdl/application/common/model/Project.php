<?php

namespace app\common\model;

use think\Model;


class Project extends Model
{

    

    

    // 表名
    protected $name = 'project';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'wall_type_text',
        'robot_type_text',
        'status_text',
        'up_time_text'
    ];
    

    
    public function getWallTypeList()
    {
        return ['1' => __('Wall_type 1'), '2' => __('Wall_type 2')];
    }

    public function getRobotTypeList()
    {
        return ['1' => __('Robot_type 1'), '2' => __('Robot_type 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getWallTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['wall_type']) ? $data['wall_type'] : '');
        $list = $this->getWallTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getRobotTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['robot_type']) ? $data['robot_type'] : '');
        $list = $this->getRobotTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getUpTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['up_time']) ? $data['up_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setUpTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
