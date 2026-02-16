define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'product/index' + location.search,
                    add_url: 'product/add',
                    edit_url: 'product/edit',
                    del_url: 'product/del',
                    multi_url: 'product/multi',
                    import_url: 'product/import',
                    table: 'product',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                         /* {field: 'py', title: __('Py'), operate: 'LIKE'},*/
                        /* {field: 'spec', title: __('Spec'), operate: 'LIKE'},
                        {field: 'cate_id', title: __('Cate_id')},
                        {field: 'brand_id', title: __('Brand_id')},
                        {field: 'measure_unit_id', title: __('Measure_unit_id')},
                        {field: 'measure_unit', title: __('Measure_unit'), operate: 'LIKE'},
                        {field: 'weight_unit_id', title: __('Weight_unit_id')},
                        {field: 'weight_unit', title: __('Weight_unit'), operate: 'LIKE'},
                        {field: 'volume_unit_id', title: __('Volume_unit_id')},
                        {field: 'volume_unit', title: __('Volume_unit'), operate: 'LIKE'},
                        {field: 'fw', title: __('Fw'), operate:'BETWEEN'},
                        {field: 'nw', title: __('Nw'), operate:'BETWEEN'},
                        {field: 'volume_size', title: __('Volume_size'), operate: 'LIKE'},
                        {field: 'pack_unit', title: __('Pack_unit')},
                        {field: 'min_pack_qty', title: __('Min_pack_qty')},
                        {field: 'is_warrnty_parts', title: __('Is_warrnty_parts'), searchList: {"1":__('Is_warrnty_parts 1'),"2":__('Is_warrnty_parts 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_lot_out', title: __('Is_lot_out'), searchList: {"1":__('Is_lot_out 1'),"2":__('Is_lot_out 2')}, formatter: Table.api.formatter.normal},
                        {field: 'in_stock', title: __('In_stock'), searchList: {"1":__('In_stock 1'),"2":__('In_stock 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_onepiece', title: __('Is_onepiece'), searchList: {"1":__('Is_onepiece 1'),"2":__('Is_onepiece 2')}, formatter: Table.api.formatter.normal},
                        {field: 'in_control', title: __('In_control'), searchList: {"1":__('In_control 1'),"2":__('In_control 2')}, formatter: Table.api.formatter.normal},
                        {field: 'mp_type', title: __('Mp_type'), searchList: {"1":__('Mp_type 1'),"2":__('Mp_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'pick_type_id', title: __('Pick_type_id')},
                        {field: 'pick_type', title: __('Pick_type'), operate: 'LIKE'},
                        {field: 'sale_type_id', title: __('Sale_type_id')},
                        {field: 'sale_type', title: __('Sale_type'), operate: 'LIKE'},
                        {field: 'bom_type_id', title: __('Bom_type_id')},
                        {field: 'bom_type', title: __('Bom_type'), operate: 'LIKE'},
                        {field: 'serial_no', title: __('Serial_no'), operate: 'LIKE'},*/
                        {field: 'warrnty_parts', title: __('Warrnty_parts')},
                        /*{field: 'packing_type_id', title: __('Packing_type_id')},
                        {field: 'packing_type', title: __('Packing_type'), operate: 'LIKE'},
                        {field: 'buy', title: __('Buy'), operate:'BETWEEN'},
                        {field: 'sell', title: __('Sell'), operate:'BETWEEN'},
                        {field: 'retail', title: __('Retail'), operate:'BETWEEN'},
                        {field: 'integral', title: __('Integral'), operate:'BETWEEN'},
                        {field: 'code', title: __('Code'), operate: 'LIKE'},
                        {field: 'wh_id', title: __('Wh_id')},
                        {field: 'wh_l_id', title: __('Wh_l_id'), operate: 'LIKE'},
                        {field: 'stocktip', title: __('Stocktip'), operate:'BETWEEN'},
                        {field: 'retail_name', title: __('Retail_name'), operate: 'LIKE'},
                        {field: 'info', title: __('Info'), operate: 'LIKE'},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},*/
                        {field: 'operate', title: __('Operate'),
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('打印'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-print',
                                    url: 'product/printer/id/{id}',
                                    confirm: '确认打印吗？',
                                    success: function (data, ret) {
                                        console.log(data,ret);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        // return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data,ret);
                                        
                                        //如果需要阻止失败提示，则必须使用return false;
                                        // return false;
                                    }
                                    
                            }], 
                            table: table, 
                            events: Table.api.events.operate, 
                            formatter: Table.api.formatter.operate
                        }

                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        move_edit: function () {
            Controller.api.bindevent();
            Form.api.bindevent($("form[role=form]"), function(data, ret){
                //这里是表单提交处理成功后的回调函数，接收来自php的返回数据
                Fast.api.close(data);//这里是重点
                Toastr.success("成功");//这个可有可无
            }, function(data, ret){
                Toastr.success("失败");
            });
        },
        move_add: function () {
            Controller.api.bindevent();
            Form.api.bindevent($("form[role=form]"), function(data, ret){
                //这里是表单提交处理成功后的回调函数，接收来自php的返回数据
                Fast.api.close(data);//这里是重点
                Toastr.success("成功");//这个可有可无
            }, function(data, ret){
                Toastr.success("失败");
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
