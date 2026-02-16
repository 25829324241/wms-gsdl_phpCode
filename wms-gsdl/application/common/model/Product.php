<?php

namespace app\common\model;

use think\Model;


class Product extends Model
{

    

    

    // 表名
    protected $name = 'product';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_warrnty_parts_text',
        'is_lot_out_text',
        'in_stock_text',
        'is_onepiece_text',
        'in_control_text',
        'mp_type_text',
        'add_time_text',
        'up_time_text'
    ];
    

    
    public function getIsWarrntyPartsList()
    {
        return ['1' => __('Is_warrnty_parts 1'), '2' => __('Is_warrnty_parts 2')];
    }

    public function getIsLotOutList()
    {
        return ['1' => __('Is_lot_out 1'), '2' => __('Is_lot_out 2')];
    }

    public function getInStockList()
    {
        return ['1' => __('In_stock 1'), '2' => __('In_stock 2')];
    }

    public function getIsOnepieceList()
    {
        return ['1' => __('Is_onepiece 1'), '2' => __('Is_onepiece 2')];
    }

    public function getInControlList()
    {
        return ['1' => __('In_control 1'), '2' => __('In_control 2')];
    }

    public function getMpTypeList()
    {
        return ['1' => __('Mp_type 1'), '2' => __('Mp_type 2')];
    }


    public function getIsWarrntyPartsTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_warrnty_parts']) ? $data['is_warrnty_parts'] : '');
        $list = $this->getIsWarrntyPartsList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsLotOutTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_lot_out']) ? $data['is_lot_out'] : '');
        $list = $this->getIsLotOutList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getInStockTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['in_stock']) ? $data['in_stock'] : '');
        $list = $this->getInStockList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsOnepieceTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_onepiece']) ? $data['is_onepiece'] : '');
        $list = $this->getIsOnepieceList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getInControlTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['in_control']) ? $data['in_control'] : '');
        $list = $this->getInControlList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getMpTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['mp_type']) ? $data['mp_type'] : '');
        $list = $this->getMpTypeList();
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
