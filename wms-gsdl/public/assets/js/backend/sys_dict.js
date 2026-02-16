define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys_dict/index' + location.search,
                    add_url: 'sys_dict/add',
                    edit_url: 'sys_dict/edit',
                    del_url: 'sys_dict/del',
                    multi_url: 'sys_dict/multi',
                    import_url: 'sys_dict/import',
                    table: 'sys_dict',
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
                        {field: 'numbering', title: __('Numbering'), operate: 'LIKE'},
                        {field: 'pid', title: __('Pid')},
                        {field: 'parent_numbering', title: __('Parent_numbering'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'type', title: __('Type')},
                        {field: 'identify', title: __('Identify'), operate: 'LIKE'},
                        {field: 'sort', title: __('Sort')},
                        {field: 'is_default', title: __('Is_default'), searchList: {"1":__('Is_default 1'),"2":__('Is_default 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_enable', title: __('Is_enable'), searchList: {"1":__('Is_enable 1'),"2":__('Is_enable 2')}, formatter: Table.api.formatter.normal},
                        {field: 'add_aid', title: __('Add_aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'up_aid', title: __('Up_aid'), operate: 'LIKE'},
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
