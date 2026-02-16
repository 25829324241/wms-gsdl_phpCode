define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_shelves/index' + location.search,
                    add_url: 'warehouse_shelves/add',
                    edit_url: 'warehouse_shelves/edit',
                    // del_url: 'warehouse_shelves/del',
                    multi_url: 'warehouse_shelves/multi',
                    import_url: 'warehouse_shelves/import',
                    table: 'warehouse_shelves',
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
                        {field: 'wh_id', title: __('Wh_id')},
                        {field: 'wh_a_id', title: __('Wh_a_id')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'channel', title: __('Channel'), operate: 'LIKE'},
                        {field: 'left_right', title: __('Left_right'), searchList: {"1":__('Left_right 1'),"2":__('Left_right 2')}, formatter: Table.api.formatter.normal},
                        {field: 'plaid_num', title: __('Plaid_num')},
                        {field: 'layer_num', title: __('Layer_num')},
                        {field: 'up_time', title: __('Up_time')},
                        {field: 'cid', title: __('Cid')},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},
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
