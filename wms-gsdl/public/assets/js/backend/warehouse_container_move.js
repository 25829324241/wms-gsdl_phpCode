define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_container_move/index' + location.search,
                    add_url: 'warehouse_container_move/add',
                    edit_url: 'warehouse_container_move/edit',
                    del_url: 'warehouse_container_move/del',
                    multi_url: 'warehouse_container_move/multi',
                    import_url: 'warehouse_container_move/import',
                    table: 'warehouse_container_move',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'wh_c_id', title: __('Wh_c_id')},
                        {field: 'from_wh_l_id', title: __('From_wh_l_id')},
                        {field: 'from_w_id', title: __('From_w_id')},
                        {field: 'target_wh_l_id', title: __('Target_wh_l_id')},
                        {field: 'target_w_id', title: __('Target_w_id')},
                        {field: 'task_id', title: __('Task_id'), operate: 'LIKE'},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},
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
