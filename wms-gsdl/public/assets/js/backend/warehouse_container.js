define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_container/index' + location.search,
                    add_url: 'warehouse_container/add',
                    edit_url: 'warehouse_container/edit',
                   /* del_url: 'warehouse_container/del',*/
                    multi_url: 'warehouse_container/multi',
                    import_url: 'warehouse_container/import',
                    table: 'warehouse_container',
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
                       /* {field: 'name', title: __('Name'), operate: 'LIKE'},*/
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                       /* {field: 'wh_l_id', title: __('Wh_l_id')},*/
                        {field: 'wh_l_numbering', title: __('Wh_l_numbering'), operate: 'LIKE'},
                      /*  {field: 'wh_l_real_location', title: __('Wh_l_real_location'), operate: 'LIKE'},*/
                       /* {field: 'w_id', title: __('W_id')},*/
                        {field: 'w_numbering', title: __('W_numbering'), operate: 'LIKE'},
                       /* {field: 'aid', title: __('Aid')},*/
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                      /*  {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        {field: 'location.name', title: __('Location.name'), operate: 'LIKE'},*/
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
