define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function() {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'import/log/index' + location.search,
                    add_url: 'import/log/add',
                    del_url: 'import/log/del',
                    multi_url: 'import/log/multi',
                    table: 'import_log',
                    import_url: 'import/log/import',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id') },
                        { field: 'table', title: __('Table') },
                        { field: 'row', title: __('Row') },
                        {
                            field: 'head_type',
                            title: __('head_type'),
                            searchList: { "comment": __('comment'), "name": __('name') },
                            formatter: Table.api.formatter.status
                        },
                        { field: 'path', title: __('Path'), formatter: Table.api.formatter.url },
                        //                        {field: 'admin_id', title: __('Admin_id')},
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'updatetime',
                            title: __('Updatetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: { "normal": __('Normal'), "hidden": __('Hidden') },
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'detail',
                                text: __('查看'),
                                title: __('查看'),
                                classname: 'btn btn-xs btn-primary btn-dialog',
                                icon: 'fa fa-eye',
                                extend: 'data-area=\'["1000px","800px"]\'',
                                url: 'import/log/edit',
                                callback: function(data) {
                                    Layer.alert("接收到回传数据：" + JSON.stringify(data), { title: "回传数据" });
                                },
                                visible: function(row) {
                                    //返回true时按钮显示,返回false隐藏
                                    return true;
                                }
                            }]
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function() {
            function reset() {
                $("#step").val(0);
                $("#import").addClass("disabled");
                //  $("#createtable").hide();
                // $("#updatetable").hide();
                if ($("#c-row").val() && $("#selecthead_type select").val() && $("#selectable select").val() && $("#c-rowpath").val()) {
                    Toastr.success('开始预览结果');
                    // $("#submit").trigger("click");

                    Fast.api.ajax({
                        url: 'import/log/preview',
                        data: $("form[role=form]").serialize(),
                        dataType: 'json',
                    }, function(data) {
                        console.log(data)
                        let columns = data.field
                        let xlsdata = data.data
                        let tableField = data.fieldArr
                        var select = ""
                        if (xlsdata.length) {
                            $("#step").val(1);
                            $("#import").removeClass("disabled");
                            //    Toastr.success("与狼");
                        }
                        $.each(tableField, function(i, item) {
                            select += "<option  class=>" + i + "</option>"
                        });
                        html = "<tr>"
                        $.each(columns, function(i, item) {
                            html += "<th class=" + item.class + ">" + item.field + "</th>"

                        });
                        html += "</tr>"
                        html += "<tr>"
                        $.each(columns, function(i, item) {
                            if (item.class != "success") html += "<td><select id=setop class='form-control' name=row[set][" + i + "]><option class=>请选择</option>" + select + "</select></td>"
                            else html += "<td class=success></td>"
                        });
                        html += "</tr>"
                        $.each(xlsdata, function(i, item) {
                            html += "<tr>"
                            $.each(columns, function(i, f) {
                                html += "<td class=" + f.class + ">" + item[f.field] + "</td>"

                            });

                            html += "</tr>"

                        });

                        $("#tableset").html(html)
                    }, function() {
                        return false;
                    });
                }
            }

            $("#selectable select").on("change", function() {
                $("#c-newtable").val('')
                reset()
            })
            $("#c-newtable").on("change", function() {
                $("#selectable select").val("");
                reset()
            })
            $("#c-row").on("change", function() {
                reset()
            })
            $("#c-rowpath").on("change", function() {
                reset()
            })
            $("#selecthead_type select").on("change", function() {
                reset()
            })
            $("#import").on("click", function() {
                $("#submit").trigger("click");
            })
            Controller.api.bindevent();
        },
        edit: function() {
            Controller.api.bindevent();
        },

        api: {
            bindevent: function() {
                // Form.api.bindevent($("form[role=form]"));
                Form.api.bindevent($("form[role=form]"), function(data, ret) {
                        console.log(ret)
                        if (ret.data.count) {
                            $("#step").val(1);
                            $("#import").show();
                            Toastr.success(ret.msg);
                        } else {
                            Toastr.error(ret.msg);
                        }
                        if (ret.data.params) {
                            // 初始化表格参数配置
                            var params = ret.data.params

                            return false;
                        }
                        if (ret.url) {
                            window.location.href = ret.url;
                            return false;
                        }
                    },
                    function(data) {

                    },
                    function(data) {
                        //Form.api.submit(this);
                        //  return false;

                        $("#xxxx").val($("#setop").val())
                        console.log($("#setop").val())
                    },


                );
            }
        }
    };
    return Controller;
});