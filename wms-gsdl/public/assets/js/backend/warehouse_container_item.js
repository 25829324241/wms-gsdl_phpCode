define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'warehouse_container_item/index' + location.search,
                    add_url: 'warehouse_container_item/add',
                    edit_url: 'warehouse_container_item/edit',
                    /*del_url: 'warehouse_container_item/del',*/
                    multi_url: 'warehouse_container_item/multi',
                    import_url: 'warehouse_container_item/import',
                    table: 'warehouse_container_item',
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
                       /* {field: 'wh_c_id', title: __('Wh_c_id')},*/
                        {field: 'product.name', title: __('Product.name'), operate: 'LIKE'},
                        {field: 'wh_c_numbering', title: __('Wh_c_numbering'), operate: 'LIKE'},
                      /*  {field: 'product_id', title: __('Product_id')},*/
                        {field: 'product_num', title: __('Product_num')},
                        {field: 'batch', title: __('Batch')},
                      /*  {field: 'aid', title: __('Aid')},*/
                        {field: 'proportion', title: __('Proportion'), searchList: {"1":__('Proportion 1'),"2":__('Proportion 2'),"3":__('Proportion 3'),"4":__('Proportion 4'),"5":__('Proportion 5')}, formatter: Table.api.formatter.normal},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                       /* {field: 'up_aid', title: __('Up_aid')},
                        {field: 'up_time', title: __('Up_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cid', title: __('Cid')},*/

                      /*  {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        {field: 'log.username', title: __('Log.username'), operate: 'LIKE'},*/
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
