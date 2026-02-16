define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'business_type/index' + location.search,
                    add_url: 'business_type/add',
                    edit_url: 'business_type/edit',
                    del_url: 'business_type/del',
                    multi_url: 'business_type/multi',
                    import_url: 'business_type/import',
                    table: 'business_type',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                escape: false,
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'pid', title: __('Pid')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'is_internal', title: __('Is_internal'), searchList: {"1":__('Is_internal 1'),"2":__('Is_internal 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_callback', title: __('Is_callback'), searchList: {"1":__('Is_callback 1'),"2":__('Is_callback 2')}, formatter: Table.api.formatter.normal},
                        {field: 'source_id', title: __('Source_id')},
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
