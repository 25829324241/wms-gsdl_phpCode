define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'workbench_bit/index' + location.search,
                    add_url: 'workbench_bit/add',
                    edit_url: 'workbench_bit/edit',
                  /*  del_url: 'workbench_bit/del',*/
                    multi_url: 'workbench_bit/multi',
                    import_url: 'workbench_bit/import',
                    table: 'workbench_bit',
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
                      /*  {field: 'w_id', title: __('W_id')},*/
                        {field: 'w_numbering', title: __('W_numbering'), operate: 'LIKE'},
                      /*  {field: 'wh_l_id', title: __('Wh_l_id')},*/
                        {field: 'wh_l_numbering', title: __('Wh_l_numbering'), operate: 'LIKE'},
                      /*  {field: 'real_location', title: __('Real_location'), operate: 'LIKE'},*/
                       /* {field: 'aid', title: __('Aid')},*/
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                      /*  {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},*/
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
