define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'pick_wall/index' + location.search,
                    add_url: 'pick_wall/add',
                    edit_url: 'pick_wall/edit',
                    del_url: 'pick_wall/del',
                    multi_url: 'pick_wall/multi',
                    import_url: 'pick_wall/import',
                    table: 'pick_wall',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'bit_num', title: __('Bit_num')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'wave_numbering', title: __('Wave_numbering'), operate: 'LIKE'},
                        {field: 'advice_numbering', title: __('Advice_numbering'), operate: 'LIKE'},
                        {field: 'wh_id', title: __('Wh_id')},
                        {field: 'wh_numbering', title: __('Wh_numbering'), operate: 'LIKE'},
                        {field: 'wh_name', title: __('Wh_name'), operate: 'LIKE'},
                        {field: 'wh_a_id', title: __('Wh_a_id')},
                        {field: 'wh_a_numbering', title: __('Wh_a_numbering'), operate: 'LIKE'},
                        {field: 'wh_a_name', title: __('Wh_a_name'), operate: 'LIKE'},
                        {field: 'w_id', title: __('W_id')},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
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
