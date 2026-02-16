define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'sys_numbering/index' + location.search,
                    add_url: 'sys_numbering/add',
                    edit_url: 'sys_numbering/edit',
                    del_url: 'sys_numbering/del',
                    multi_url: 'sys_numbering/multi',
                    import_url: 'sys_numbering/import',
                    table: 'sys_numbering',
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
                        {field: 'numbering_name', title: __('Numbering_name'), operate: 'LIKE'},
                        {field: 'numbering_prefix', title: __('Numbering_prefix'), operate: 'LIKE'},
                        {field: 'numbering_num', title: __('Numbering_num')},
                        {field: 'year_switch', title: __('Year_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        {field: 'month_switch', title: __('Month_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        {field: 'day_switch', title: __('Day_switch'), searchList: {"1":__('Yes'),"0":__('No')}, table: table, formatter: Table.api.formatter.toggle},
                        {field: 'is_orgflag', title: __('Is_orgflag'), searchList: {"1":__('Is_orgflag 1'),"2":__('Is_orgflag 2')}, formatter: Table.api.formatter.flag},
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
