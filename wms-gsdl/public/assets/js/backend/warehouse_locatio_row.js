define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_locatio_row/index' + location.search,
                    add_url: 'warehouse_locatio_row/add',
                    edit_url: 'warehouse_locatio_row/edit',
                    del_url: 'warehouse_locatio_row/del',
                    multi_url: 'warehouse_locatio_row/multi',
                    import_url: 'warehouse_locatio_row/import',
                    table: 'warehouse_location_row',
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
                        {field: 'wh_l_id', title: __('Wh_l_id')},
                        {field: 'col', title: __('Col')},
                        {field: 'floor', title: __('Floor')},
                        {field: 'wh_a_id', title: __('Wh_a_id')},
                        {field: 'wh_a_numbering', title: __('Wh_a_numbering'), operate: 'LIKE'},
                        {field: 'wh_a_name', title: __('Wh_a_name'), operate: 'LIKE'},
                        {field: 'depth', title: __('Depth')},
                        {field: 'roadway', title: __('Roadway'), operate: 'LIKE'},
                        {field: 'row', title: __('Row'), operate: 'LIKE'},
                        {field: 'wh_id', title: __('Wh_id')},
                        {field: 'wh_numbering', title: __('Wh_numbering'), operate: 'LIKE'},
                        {field: 'wh_name', title: __('Wh_name'), operate: 'LIKE'},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'depth_flag', title: __('Depth_flag'), operate: 'LIKE', formatter: Table.api.formatter.flag},
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
