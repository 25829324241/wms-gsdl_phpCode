define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_container_item_move/index' + location.search,
                    add_url: 'warehouse_container_item_move/add',
                    edit_url: 'warehouse_container_item_move/edit',
                    del_url: 'warehouse_container_item_move/del',
                    multi_url: 'warehouse_container_item_move/multi',
                    import_url: 'warehouse_container_item_move/import',
                    table: 'warehouse_container_item_move',
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
                        {field: 'wh_c_i_id', title: __('Wh_c_i_id')},
                        {field: 'f_wh_c_id', title: __('F_wh_c_id')},
                        {field: 't_wh_c_id', title: __('T_wh_c_id')},
                        {field: 'from_wh_c_numbering', title: __('From_wh_c_numbering'), operate: 'LIKE'},
                        {field: 'to_wh_c_numbering', title: __('To_wh_c_numbering'), operate: 'LIKE'},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
