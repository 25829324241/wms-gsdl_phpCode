define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'in_order/index' + location.search,
                    add_url: 'in_order/add',
                    edit_url: 'in_order/edit',
                   /* del_url: 'in_order/del',*/
                    multi_url: 'in_order/multi',
                    import_url: 'in_order/import',
                    table: 'in_order',
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
                        {field: 'order_sn', title: __('Order_sn'), operate: 'LIKE'},
                        {field: 'wh_id', title: __('Wh_id')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4')}, formatter: Table.api.formatter.status},
                        {field: 'business_type_id', title: __('Business_type_id')},
                        {field: 'aid', title: __('Aid')},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                       /* {field: 'cid', title: __('Cid')},*/
                      /*  {field: 'channel.name', title: __('Channel.name'), operate: 'LIKE'},*/
                       /* {field: 'type.name', title: __('Type.name'), operate: 'LIKE'},*/
                        {field: 'warehouse.name', title: __('Warehouse.name'), operate: 'LIKE'},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            console.log('add');
            $(document).on("fa.event.appendfieldlist", ".btn-append", function(){
                Form.events.selectpage($("#setmeal_json"));
                Form.events.datetimepicker($("form"));
            });
            Controller.api.bindevent();
        },
        edit: function () {
            console.log('edit');
            $(document).on("fa.event.appendfieldlist", ".btn-append", function(){
                Form.events.selectpage($("#setmeal_json"));
                Form.events.datetimepicker($("form"));
            });
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
