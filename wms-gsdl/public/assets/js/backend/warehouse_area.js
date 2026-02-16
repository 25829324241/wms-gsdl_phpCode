define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_area/index' + location.search,
                    // add_url: 'warehouse_area/add',
                    edit_url: 'warehouse_area/edit',
                    // del_url: 'warehouse_area/del',
                    multi_url: 'warehouse_area/multi',
                    import_url: 'warehouse_area/import',
                    table: 'warehouse_area',
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
                        {field: 'py', title: __('Py'), operate: 'LIKE'},
                        // {field: 'wh_id', title: __('Wh_id')},
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'class', title: __('Class'), searchList: {"1":__('Class 1'),"2":__('Class 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'codedisk_model', title: __('Codedisk_model'), searchList: {"1":__('Codedisk_model 1'),"2":__('Codedisk_model 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'control_leve', title: __('Control_leve'), searchList: {"1":__('Control_leve 1'),"2":__('Control_leve 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'struct', title: __('Struct')},
                        // {field: 'is_auto_switch', title: __('Is_auto_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_checktray_switch', title: __('Is_checktray_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_control_qty_switch', title: __('Is_control_qty_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_tray_switch', title: __('Is_tray_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_merge_switch', title: __('Is_merge_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_outarea_switch', title: __('Is_outarea_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_inout_model_switch', title: __('Is_inout_model_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'is_inventory_switch', title: __('Is_inventory_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'allow_pick_switch', title: __('Allow_pick_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'overstep_switch', title: __('Overstep_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'need_lock_state_switch', title: __('Need_lock_state_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'fulltray_operate_switch', title: __('Fulltray_operate_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        // {field: 'queque_mix_max', title: __('Queque_mix_max')},
                        // {field: 'sort', title: __('Sort')},
                        // {field: 'row_prefix', title: __('Row_prefix')},
                        // {field: 'col_prefix', title: __('Col_prefix')},
                        // {field: 'floor_prefix', title: __('Floor_prefix')},
                        // {field: 'area_prefix', title: __('Area_prefix')},
                        // {field: 'warehouse_prefix', title: __('Warehouse_prefix')},
                        // {field: 'queue_point', title: __('Queue_point'), searchList: {"1":__('Queue_point 1'),"2":__('Queue_point 2')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        // {field: 'priority', title: __('Priority')},
                        // {field: 'priority_status', title: __('Priority_status'), searchList: {"1":__('Priority_status 1'),"2":__('Priority_status 2')}, formatter: Table.api.formatter.status},
                        // {field: 'custodian_id', title: __('Custodian_id')},
                        // {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'cid', title: __('Cid')},
                        {field: 'warehouse.name', title: __('Warehouse.name'), operate: 'LIKE'},
                        // {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        // {field: 'log.username', title: __('Log.username'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
