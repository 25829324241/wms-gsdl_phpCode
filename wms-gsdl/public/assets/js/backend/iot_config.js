define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'iot_config/index' + location.search,
                    add_url: 'iot_config/add',
                    edit_url: 'iot_config/edit',
                    del_url: 'iot_config/del',
                    multi_url: 'iot_config/multi',
                    import_url: 'iot_config/import',
                    table: 'iot_config',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'api_url', title: __('Api_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'lfb_key', title: __('Lfb_key'), operate: 'LIKE'},
                        {field: 'cid', title: __('Cid')},
                        {field: 'channel.id', title: __('Channel.id')},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},
                        {field: 'channel.aid', title: __('Channel.aid')},
                        {field: 'channel.uid', title: __('Channel.uid')},
                        {field: 'channel.status', title: __('Channel.status'), formatter: Table.api.formatter.status},
                        {field: 'channel.up_time', title: __('Channel.up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
