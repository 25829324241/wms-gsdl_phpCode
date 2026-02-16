define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_location/index' + location.search,
                    add_url: 'warehouse_location/add',
                    edit_url: 'warehouse_location/edit',
                  /*  del_url: 'warehouse_location/del',*/
                    multi_url: 'warehouse_location/multi',
                    import_url: 'warehouse_location/import',
                    table: 'warehouse_location',
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
                      /*  {field: 'name', title: __('Name'), operate: 'LIKE'},*/
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                       /* {field: 'location_name', title: __('Location_name'), operate: 'LIKE'},
                        {field: 'location_flag', title: __('Location_flag'), operate: 'LIKE', formatter: Table.api.formatter.flag},
                        {field: 'real_location', title: __('Real_location'), operate: 'LIKE'},
                        {field: 'py', title: __('Py'), operate: 'LIKE'},*/
                       /* {field: 'wh_id', title: __('Wh_id')},
                        {field: 'wh_a_id', title: __('Wh_a_id')},
                        {field: 'shelves_id', title: __('Shelves_id')},*/
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                       /* {field: 'type_name', title: __('Type_name'), operate: 'LIKE'},*/
                        // {field: 'max_store_num', title: __('Max_store_num')},
                        // {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        /* {field: 'roadway', title: __('Roadway')},
                       {field: 'row', title: __('Row')},
                        {field: 'floor', title: __('Floor')},
                        {field: 'col', title: __('Col')},
                        {field: 'position', title: __('Position'), operate: 'LIKE'},
                        {field: 'priority', title: __('Priority')},
                        {field: 'depth', title: __('Depth')},
                        {field: 'depth_flag', title: __('Depth_flag'), formatter: Table.api.formatter.flag},
                        {field: 'beat', title: __('Beat'), operate: 'LIKE'},
                        {field: 'aid', title: __('Aid')},*/
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                      /*  {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'task_unlock', title: __('Task_unlock'), searchList: {"1":__('Task_unlock 1'),"2":__('Task_unlock 2')}, formatter: Table.api.formatter.normal},
                        {field: 'use_unlock', title: __('Use_unlock'), searchList: {"1":__('Use_unlock 1'),"2":__('Use_unlock 2')}, formatter: Table.api.formatter.normal},
                        {field: 'cid', title: __('Cid')},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        {field: 'warehouse.name', title: __('Warehouse.name'), operate: 'LIKE'},
                        {field: 'area.name', title: __('Area.name'), operate: 'LIKE'},
                        {field: 'shelves.name', title: __('Shelves.name'), operate: 'LIKE'},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},*/
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
