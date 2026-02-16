define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'pick_wall_bit/index' + location.search,
                    add_url: 'pick_wall_bit/add',
                    edit_url: 'pick_wall_bit/edit',
                    del_url: 'pick_wall_bit/del',
                    multi_url: 'pick_wall_bit/multi',
                    import_url: 'pick_wall_bit/import',
                    table: 'pick_wall_bit',
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
                        {field: 'wall_id', title: __('Wall_id')},
                        {field: 'wall_numbering', title: __('Wall_numbering'), operate: 'LIKE'},
                        {field: 'wh_l_id', title: __('Wh_l_id')},
                        {field: 'wh_l_numbering', title: __('Wh_l_numbering'), operate: 'LIKE'},
                        {field: 'real_location', title: __('Real_location'), operate: 'LIKE'},
                        {field: 'sort', title: __('Sort')},
                        {field: 'col', title: __('Col')},
                        {field: 'row', title: __('Row')},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'sorting_no', title: __('Sorting_no'), operate: 'LIKE'},
                        {field: 'advice_no', title: __('Advice_no'), operate: 'LIKE'},
                        {field: 'tray_no', title: __('Tray_no'), operate: 'LIKE'},
                        {field: 'cid', title: __('Cid'), operate: 'LIKE'},
                        {field: 'wall.name', title: __('Wall.name'), operate: 'LIKE'},
                        {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
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
