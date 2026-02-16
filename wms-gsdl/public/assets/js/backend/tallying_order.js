define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'tallying_order/index' + location.search,
                    add_url: 'tallying_order/add',
                    edit_url: 'tallying_order/edit',
                    del_url: 'tallying_order/del',
                    multi_url: 'tallying_order/multi',
                    import_url: 'tallying_order/import',
                    table: 'tallying_order',
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
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'w_id', title: __('W_id')},
                        {field: 'w_b_id', title: __('W_b_id')},
                        {field: 'wh_a_id', title: __('Wh_a_id')},
                        {field: 'from_wh_c_id', title: __('From_wh_c_id')},
                        {field: 'from_wh_c_i_id', title: __('From_wh_c_i_id'), operate: 'LIKE'},
                        {field: 'target_wh_c_id', title: __('Target_wh_c_id')},
                        {field: 'num', title: __('Num')},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
