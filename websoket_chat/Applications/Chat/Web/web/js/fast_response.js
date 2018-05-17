 layui.use(['element', 'laypage', 'layer', 'form'], function() {
        $ = layui.jquery; //jquery
        lement = layui.element(); //面包导航
        laypage = layui.laypage; //分页
        layer = layui.layer; //弹出层
        form = layui.form(); //弹出层

         laypage({
                cont: 'page'
                ,pages: 100
                ,first: 1
                ,last: 100
                ,prev: '<em><</em>'
                ,next: '<em>></em>'
              });

        //监听提交
        form.on('submit(*)', function(data) {
            console.log(data);
            //发异步，把数据提交给php
            layer.alert("增加成功", {
                icon: 6
            });
            $('#x-link').prepend('<tr><td><input type="checkbox"value="1"name=""></td><td>1</td><td>' + data.field.name + '</td><td class="td-manage"><a title="编辑"href="javascript:;"onclick="cate_edit(\'编辑\',\'link-edit.html\',\'4\',\'\',\'510\')"class="ml-5"style="text-decoration:none"><i class="layui-icon">&#xe642;</i></a><a title="删除"href="javascript:;"onclick="cate_del(this,\'1\')"style="text-decoration:none"><i class="layui-icon">&#xe640;</i></a></td></tr>');
            return false;
        });
    })

    //以上模块根据需要引入

    //批量删除提交
    function delAll() {
        layer.confirm('确认要删除吗？', function(index) {
            //捉到所有被选中的，发异步进行删除
            layer.msg('删除成功', {
                icon: 1
            });
        });
    }
    /*删除*/
    function cate_del(obj, id) {
        layer.confirm('确认要删除吗？', function(index) {
            //发异步删除数据
            $(obj).parents("tr").remove();
            layer.msg('已删除!', {
                icon: 1,
                time: 1000
            });
        });
    }

    //修改关键词
    $(document).on('click','.modified',function(){   
        $(this).parents('tr').first().find('input').attr('disabled', false);
        $(this).parents('tr').first().find('input').css('border', "1px solid #ccc");  
        $(this).parents('tr').first().find('input').eq(0).focus();
        $(this).parents('tr').first().find('input').blur(function(){
            $(this).attr('disabled', true);
             $(this).css('border', "0");
        });

    })


    $(".add_fastword").click(function(){
        $("#x-link").prepend('<tr><td>#<input type="text" name="title" required lay-verify="required" '+
            'autocomplete="off" class="layui-input modified_input"> </td><td>'+
                        '<input type="text" name="title" required lay-verify="required"  autocomplete="off"'+
                     'class="layui-input modified_input" value="">'+ 
                            '</td><td class="td-manage">'+
                         '<a title="编辑" href="javascript:;" class="ml-5 modified" style="text-decoration:none">'+
                                '<i class="layui-icon">&#xe642;</i></a>'+
                    '<a title="删除" href="javascript:;" onclick="cate_del(this,\'1\')" style="text-decoration:none">'+
                                '<i class="layui-icon">&#xe640;</i></a></td></tr>')
        $(".layui-table td input").eq(0).focus();
        $(".layui-table td input").eq(0).css('border', "1px solid #ccc"); 
        $(".layui-table td input").eq(1).css('border', "1px solid #ccc"); 
        $(".layui-table td input").blur(function(){
            $(this).attr('disabled', true)
             $(this).css('border', "0")
        })
    })