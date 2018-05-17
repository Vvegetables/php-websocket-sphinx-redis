<?php
use \GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;
use GatewayWorker\Lib\RedisQueue;
use GatewayWorker\Lib\MysqlKefu;
use GatewayWorker\Lib\RobotResponse;
use GatewayWorker\Lib\GovernChat;
use GatewayWorker\Lib\ServiceDb;

class Events{
    /**
        array['kefu']['room_id']['client_name']=('client_id'=>'','client_name'=>'','is_busy'=>'','group'=>'')
        array['client']->临时存储对应组的用户信息
    **/
    protected static $kefu_arr = array('kefu' => array('room_id'=>null,),'client' => array());
    protected static $govern = array();
    private static $rooms_count = 1;    //房间号统计
    private static $clients_count = 0;  //用户在线人数统计
    private static $close_time = 300;    //机器人定时关闭连接时间设置
    private static $record;             //用于存储会话消息的定时器
    private static $record_time = 2;
    //private static $mysqli = null;

    public static function onWorkerStop($businessWorker){
       MysqlKefu::flushDB();
       RedisQueue::flushDB();
    }
	
    public static function onWorkerStart($businessWorker){
        
       
        //启动用户等待队列
        RedisQueue::getEntry();
        //启动会话聊天记录功能
        MysqlKefu::getEntry();

        MysqlKefu::flushDB();
        RedisQueue::flushDB();

        // self::$mysqli  =  mysqli_connect( '127.0.0.1' , 'root' ,'','communication' ) or die ('Connect Error:'.mysqli_connect_error());
        // mysqli_set_charset(self::$mysqli, "utf8");
                //全局定时器设置
        self::$record = Timer::add(self::$record_time,function(){
            // MysqlKefu::getRedis();
            // $mysqli  =  mysqli_connect( '127.0.0.1' , 'root' ,'','communication' ) or die ('Connect Error:'.mysqli_connect_error());
            // mysqli_set_charset($mysqli, "utf8");
            //     $sql = "INSERT INTO users (client,rank,isfinish) VALUES('aaa',5,1)";
            // //self::$mysqli->query($sql);
            // mysqli_query($mysqli,$sql);
            MysqlKefu::getRedis();
            // mysqli_close($mysqli);
        });
        // $sql = "INSERT INTO users (client,rank,isfinish) VALUES('aaa',5,1)";
        // //self::$mysqli->query($sql);
        // mysqli_query(self::$mysqli,$sql);

        //$db = Db::instance('db');
    }
    
    public static function onMessage($client_id, $message){

        $message_data = json_decode($message, true);


        if(!$message_data){
            return ;
        }
        if(isset($message_data['content']))
            $content = nl2br(htmlspecialchars($message_data['content']));
        
        /*
            管理员进行实时监控---信息实时发送回来
        */

        if(isset($message_data['govern'])){
            $_SESSION['supervise_kefu'] = $message_data['client_name'];
            //监控的对象
            self::$govern[$message_data['client_name']] = $client_id;
        }
        if(!empty(self::$govern))
        if(isset($_SESSION['client_name']) && isset($content)){
            //客服的回话
            $govern_id = "";
            foreach (self::$govern as $key => $value) {
                if($key == $_SESSION['client_name']){
                    $govern_id = $value;
                }
            }   //  找到govern_id
            
            if("" == $govern_id){   //表示是客服的用户
                foreach (self::$govern as $key => $value) {
                    if($key == $message_data['to_client_name']){
                        $govern_id = $value;
                    }   
                }   // 找到govern_id

                if("" == $govern_id){   //表示不是管理员监听的对象
                    return;
                }

                $new_message = array(
                    'type'=>'say',
                    'from_client_id'=>$client_id,   //用户id
                    'from_client_name' =>$_SESSION['client_name'],  //用户名字
                    'to_client_id'=>$govern_id,  //govern_id
                    'content'=>$content,    //聊天内容
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                );
                //将消息转发给指定的监听者
                Gateway::sendToClient($govern_id,json_encode($new_message));


            }else{  //表示是监听的客服
                $new_message = array(
                    'type'=>'response',
                    'from_client_id'=>$client_id,   //客服id
                    'from_client_name' =>$_SESSION['client_name'],  //客服名字
                    'to_client_id'=>$govern_id,  //govern_id
                    'to_client_name'=>$message_data['to_client_name'],
                    'content'=>$content,    //聊天内容
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                );
                //将消息转发给指定的监听者
                Gateway::sendToClient($govern_id,json_encode($new_message));
            }
        }


        // 根据类型执行不同的业务
        switch($message_data['type']){
        	// 客户端回应服务端的心跳
            case 'pong':
                //Gateway::sendToClient($client_id,json_encode(array('type'=>'ping','test'=>'test')));
                return;

            /*
                给管理员客服当前聊天的消息
            */

            //第一次登录把今天的历史对话发过来
            case 'govern':

                $_SESSION['client_name'] = $message_data['govern'];
                //返回客户信息
                // 获取房间内所有用户列表
                foreach (self::$kefu_arr['kefu']['room_id'] as $key => $value) {
                    if($key == $message_data['client_name']){
                        $room_id = $value['group'];
                    }   //获得房间号
                } 
                $clients_list = Gateway::getClientSessionsByGroup($room_id);

                $temp_client = array();

                if($clients_list){
                    //client_id 对应 client_name
                    foreach($clients_list as $tmp_client_id=>$item){
                        $clients_list[$tmp_client_id] = $item['client_name'];
                        $temp_client[] = $item['client_name'];
                    }
                }
                //回传信息
                $new_message = array(
                    'type'=>$message_data['type'], 
                    'client_id'=>$client_id, 
                    'client_name'=>$message_data['govern'], 
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    'client_list' => $clients_list,
                    'group'=>$room_id,
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));

                /*
                    取出历史消息
                */
                GovernChat::start();
                $records = GovernChat::getHistory($message_data['client_name'],$temp_client);
                // if($records){
                //     return;
                // }
                //回传信息
                $new_message = array(
                    'type'=>'history', 
                    'client_id'=>$client_id, 
                    'client_name'=>$message_data['govern'], 
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    'result'=>$records,
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));
                //var_dump("govern_id");
                return;

            case 'check':

                $_SESSION['room_id'] = $message_data['room_id'];
                //$_SESSION['to_client_name'] = $message_data['to_client_name'];
                return;

            case 'hotproblem':

                $res = RobotResponse::getHotProblem($message_data['product']);
                $new_message = array(
                    'type' => 'hotproblem', 
                    'hotproblem'=> $res,
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;

            //-----------------------这是开始机器人处理代码---------------------------//
            case 'robot':
                $temp_client_count = self::$clients_count++;

                $client_name = htmlspecialchars($message_data['client_name']).$temp_client_count;
                //保存当前对话
                //改个名字
                $_SESSION['client_name'] = $client_name;

                //存一个产品的session
                $_SESSION['product'] = 0;

                //--------------------------设置定时器自动关闭--------------------------//
                
                $_SESSION['auth_timer_id'] = Timer::add(self::$close_time, function($client_id){

                    Gateway::closeClient($client_id);
                    
                }, array($client_id), false);

                
                //--------------------------机器人自动回复语句--------------------------//

                //获得热词
                //$res = RobotResponse::getHotProblem();

                //获得产品列表，给用户选择。

                if(!isset($message_data['product'])){    //如果没有附加产品名
                    $res = RobotResponse::getProduct();
                    $temp = "";
                    foreach ($res as $key => $value) {
                        $temp .= ($key + 1).".".$value."\n";
                    }

                //nl2br() 函数在字符串中的每个新行（\n）之前插入 HTML 换行符（<br> 或 <br />）。
                //htmlspecialchars() 函数把预定义的字符转换为 HTML 实体。
                    //预定义的字符是：
                    //& （和号）成为 &
                    //" （双引号）成为 "
                    //' （单引号）成为 '
                    //< （小于）成为 <
                    //> （大于）成为 >
                    $new_message = array(
                        'type'=>'robot', 
                        'from_client_id'=>'robot',
                        'from_client_name' =>'robot',
                        'to_client_id'=>$client_id,
                        'content'=>nl2br(htmlspecialchars('欢迎'.$client_name."\n")."请先选择需要咨询的产品：\n".$temp),
                        'time'=>date('Y-m-d H:i:s',time()+8*3600),
                        );
                    Gateway::sendToCurrentClient(json_encode($new_message));
                }else{
                    $new_message = array(
                        'type'=>'robot', 
                        'from_client_id'=>'robot',
                        'from_client_name' =>'robot',
                        'to_client_id'=>$client_id,
                        'content'=>nl2br(htmlspecialchars('欢迎'.$client_name."~\n")),
                        'time'=>date('Y-m-d H:i:s',time()+8*3600),
                        );
                    $_SESSION['product'] = $message_data['product'];
                    Gateway::sendToCurrentClient(json_encode($new_message));
                }


                return;

            //-----------------------------机器人解决问题------------------------------//
            case 'auto_say':
                
            //--------------------------用户有响应，删除定时器-------------------------//

                Timer::del($_SESSION['auth_timer_id']);
                
                //重新设置定时器
                $_SESSION['auth_timer_id'] = Timer::add(self::$close_time, function($client_id){                  

                    Gateway::closeClient($client_id);
                   
                }, array($client_id), false);

                $client_name = $_SESSION['client_name'];
                
                $res = RobotResponse::getProduct();

                if(!$_SESSION['product']){
                    if(!in_array($content, $res)){
                       //将用户的话返回给用户
                        $new_message = array(
                            'type'=>'auto_say', 
                            'from_client_id'=>$client_id,
                            'from_client_name' =>$client_name,
                            'to_client_id'=>$client_id,
                            'content'=>$content,
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        $new_message = array(
                        'type'=>'robot', 
                        'from_client_id'=>'robot',
                        'from_client_name' =>'robot',
                        'to_client_id'=>$client_id,
                        'content'=>htmlspecialchars("请您再核对一下，是否输错了产品型号！"),
                        'time'=>date('Y-m-d H:i:s',time()+8*3600),
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;  
                    }else{
                        $_SESSION['product'] = $content;

                        //将用户的话返回给用户
                        $new_message = array(
                            'type'=>'auto_say', 
                            'from_client_id'=>$client_id,
                            'from_client_name' =>$client_name,
                            'to_client_id'=>$client_id,
                            'content'=>$content,
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));

                        //将机器人的回复返回给当前用户

                        $new_message = array(
                            'type'=>'robot', 
                            'from_client_id'=>'robot',
                            'from_client_name' =>'robot',
                            'to_client_id'=>$client_id,
                            'content'=>nl2br(htmlspecialchars("请问需要咨询什么呢？")),
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));

                        $res = RobotResponse::getHotProblem(htmlspecialchars($content));
                        $new_message = array(
                            'type' => 'hotproblem', 
                            'hotproblem'=> $res,
                            'product' => htmlspecialchars($content),
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    
                }

                //将用户的话返回给用户
                $new_message = array(
                    'type'=>'auto_say', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>$client_id,
                    'content'=>$content,
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));

                //用户status = 1
                MysqlKefu::addRedis(array(
                    'server_name'=>'robot', //--------------->存在单独的表
                    'mytime' => time()+8*3600,
                    'client_name'   => $client_name,
                    'status' => 1,
                    'content'=> $content,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    ));

                //将机器人的回复返回给当前用户

                $new_content = RobotResponse::response($content,
                    $_SESSION['product']
                    );   //从知识库中获得回复

                 $new_message = array(
                    'type'=>'robot', 
                    'from_client_id'=>'robot',
                    'from_client_name' =>'robot',
                    'to_client_id'=>$client_id,
                    'content'=>nl2br(htmlspecialchars($new_content)),
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));               

                MysqlKefu::addRedis(array(
                    'server_name'=>'robot',
                    'mytime' => time()+8*3600,
                    'client_name'   => $client_name,
                    'status' => 0,
                    'content'=> nl2br(htmlspecialchars($new_content)),
                    ));
                return;

            // 客户端登录 message格式: {type:login, name:xx} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':

                if(isset($_SESSION['auth_timer_id'])){
                    Timer::del($_SESSION['auth_timer_id']);
                    unset($_SESSION['auth_timer_id']);
                }
                //$flag = false;  //是否成功分配客服

                $client_name = $_SESSION['client_name'];
                //回传可用客服列表，随机连接。-------------------------------------->目前还没有重定位
                
                //数组指针重定位
                if(!empty(self::$kefu_arr['kefu']['room_id'])){

                    reset(self::$kefu_arr['kefu']['room_id']);
                }

                //查找空闲的客服
                $kefu_list = self::$kefu_arr['kefu']['room_id'];
                
                if(empty($kefu_list)){  //如果客服列表是空的，就是客服都不在线


                //-----------------------此处需要新增一个用户等待队列-------------------------//    
                    /*实现*/
                    //加入等待队列
                    RedisQueue::addRedis($client_id,array(
                        'client_id' => $client_id, 
                        'client_name' => $client_name ));
                    //测试
                    //$test = RedisQueue::getTotals();

                //----------------------------------------------------------------------------//

                //因为客服都不在，所以回复消息给用户，请他等待
                    $new_message = array(
                        'type'=>'login', 
                        'client_id'=>$client_id, 
                        'client_name'=>htmlspecialchars($client_name), 
                        'time'=>date('Y-m-d H:i:s',time()+8*3600),
                        'server_name'=>'客服',
                        'content'=>'请稍等片刻，马上就来~',
                        'to_client_id'=>0,
                        );
                    Gateway::sendToCurrentClient(json_encode($new_message));
                    return;

                }else{  //此处为客服列表不为空，可以给用户分配客服，所以查找可用客服
                    //测试
                    echo __LINE__,PHP_EOL;
                    var_dump($kefu_list);
                    //添加 $_SESSION['room_id'];
                    //先选好room_id

                    //$kefu_room = self::$kefu_arr['kefu']['room_id'];

                    //尽管客服都在线，但是每个客服的处理人数都已经满了，所以需要将用户加入等待队列
                    //--------------所有客服都在忙则需要等待，不忙则接用户-------------------------//
                    $index = RedisQueue::averageDispatch(self::$kefu_arr['kefu']['room_id']);
                    if($index){  //$flag == true 则表示客服还可以接待用户
                        
                        //分配房间信息
                        $_SESSION['room_id'] = self::$kefu_arr['kefu']['room_id'][$index]['group'];
                        $room_id = $_SESSION['room_id'];
                        Gateway::joinGroup($client_id,$room_id);
                        
                        //给用户发送与他聊天的客服信息，为了之后进行点对点的交流
                        $new_message = array(
                            'type'=>'login',
                            'client_id'=>$client_id, 
                            'client_name'=>htmlspecialchars($client_name), 
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            'server_name'=>'客服',
                            'content'=>"很高兴为你服务",
                            'to_client_id'=>self::$kefu_arr['kefu']['room_id'][$index]['client_id'],  //客服的id
                            'to_client_name'=>$index,  //客服的名字
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                    
                        //------------------给对应的客服发送在线用户刷新请求------------------------------//
                        $new_message = array('type'=>'flush', 
                            'client_id'=>$client_id, 
                            'client_name'=>htmlspecialchars($client_name), 
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            'to_client_id'=>self::$kefu_arr['kefu']['room_id'][$index]['client_id'],
                            'to_client_name'=>$index,
                            );
                        $govern_id = "";
                        if(!empty(self::$govern)){
                            foreach (self::$govern as $key => $value) {
                                if($key == self::$kefu_arr['kefu']['room_id'][$index]['client_name']){
                                    $govern_id = $value;
                                }
                            }
                        }
                        if("" != $govern_id){
                            Gateway::sendToClient($govern_id,json_encode($new_message));
                        }

                        Gateway::sendToClient(self::$kefu_arr['kefu']['room_id'][$index]['client_id'],json_encode($new_message));
                        return; 
                    }else{  //$flag == false 表示客服目前没有空位，用户需要等待

                        //给用户发送提示消息，目前没有可用客服，请等待
                        $new_message = array(
                            'type'=>'login', 
                            'client_id'=>$client_id, 
                            'client_name'=>htmlspecialchars($client_name), 
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            'server_name'=>'客服',
                            'to_client_id'=>0,
                            );
                        Gateway::sendToCurrentClient(json_encode($new_message));

                        //--------------------------等待队列---------------------------------//
                        RedisQueue::addRedis($client_id,array(
                            'client_id' => $client_id, 
                            'client_name' => $client_name 
                            ));
                        //------------------------------------------------------------------//
                    }

                }
            	return;
             //-----------客户端发言 message: {type:say, to_client_id:xx, content:xx}---------//
            case 'say':
                //从保存的对话中找到用户名

                $client_name = $_SESSION['client_name'];
               
                
                //?------这里有个问题，== 0 会出错//
                //----------------如果所有客服都不在线------------------------------//
                if("0" == $message_data['to_client_id']){

                    $new_message = array(
                        'type'=>'response', 
                        'from_client_id'=>$client_id, 
                        'from_client_name'=>htmlspecialchars($client_name), 
                        'time'=>date('Y-m-d H:i:s',time()+8*3600),
                        'server_name'=>'系统',
                        'content'=>'请稍等片刻，马上就来~',
                        'to_client_id'=>0,
                        );
                    Gateway::sendToCurrentClient(json_encode($new_message));
                    return;
                    
                }
                
                
                $new_message = array(
                    'type'=>'say',
                    'from_client_id'=>$client_id, 
                    'from_client_name' =>$client_name,
                    'to_client_id'=>$message_data['to_client_id'],
                    'content'=>$content,
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                );
                //将消息转发给指定的客服
                Gateway::sendToClient($message_data['to_client_id'],json_encode($new_message));
                
                //用户status = 1
                MysqlKefu::addRedis(array(
                    'server_name'=>$message_data['to_client_name'],
                    'mytime' => time()+8*3600,
                    'client_name'   => $client_name,
                    'status' => 1,
                    'content'=> $content,
                    ));

                //将消息返回给当前客户端
                $new_message['content'] = $content;

                return Gateway::sendToCurrentClient(json_encode($new_message));
             
             //客服登录 message格式：{type：server，name：xx}

            case 'server':
                $client_name = htmlspecialchars($message_data['client_name']);
                //保存当前对话
                $_SESSION['client_name'] = $client_name;
                //保存客服的状态,以便用户连接的时候查找与连接
                $room_id = self::$rooms_count++;
                
                /*
                    获得最近的一次评价
                */

                $eva = ServiceDb::getEvaluate($client_name);
                // var_dump($eva);


                /*
                    登录的时候，更新客服的状态
                */

                RobotResponse::updateStatus($_SESSION['client_name'],1);

                /*
                    is_busy从数据库中取出
                */

                //客服信息存储格式
                // self::$kefu_arr['kefu']['room_id'] = array();
                self::$kefu_arr['kefu']['room_id'][$client_name] = array('client_id' => $client_id,'client_name' => $client_name,'is_busy' => 5,'group' => $room_id,'count'=>0);
                $_SESSION['room_id'] = $room_id;
                //加入自己的房间
                //Gateway::joinGroup($client_id,$room_id);
                
                //测试

                while(self::$kefu_arr['kefu']['room_id'][$client_name]['is_busy'] > 0){
                    // $user_count = RedisQueue::getTotals();
                    $user_data;
                    //分配用户
                    if(RedisQueue::getTotals() > 0){
                        
                        $user_data = RedisQueue::getRedis();
                        $to_client_id = $user_data['client_id'];
                        $to_client_name = $user_data['client_name'];
                    
                    //给客户端发送消息，提醒已经连接上了。
                        $new_message = array(
                            'type'=>'prompt',
                            'from_client_id'=>$client_id,
                            'from_client_name'=>$client_name,
                            'to_client_id'=>$to_client_id,
                            'to_client_name'=>$to_client_name,
                            'content'=>"很高兴为你服务！",
                            'room_id'=>$_SESSION['room_id'],
                            'time'=>date('Y-m-d H:i:s',time()+8*3600)
                            );
                        Gateway::sendToClient($to_client_id,json_encode($new_message));
                       
                    //并让他加入客服组
                        Gateway::joinGroup($to_client_id,$room_id);
                    
                    //客服服务人数+1
                        self::$kefu_arr['kefu']['room_id'][$client_name]['is_busy']--;
                        self::$kefu_arr['kefu']['room_id'][$client_name]['count']++;
                        echo __LINE__,PHP_EOL;
                        var_dump(self::$kefu_arr['kefu']['room_id']);
                        echo __LINE__,PHP_EOL;
                        var_dump(self::$kefu_arr['kefu']['room_id'][$client_name]['is_busy']);
                    }else{
                        break;
                    }
                }

                //返回客户信息
                // 获取房间内所有用户列表 
                self::$kefu_arr['client'] = Gateway::getClientSessionsByGroup($room_id);
                $clients_list = self::$kefu_arr['client'];

                if($clients_list){
                    //client_id 对应 client_name
                    foreach($clients_list as $tmp_client_id=>$item){
                        $clients_list[$tmp_client_id] = $item['client_name'];
                    }
                }

                //回传信息
                //$mytime = '';
                if("无" != $eva['mytime']){
                    $eva['mytime'] = date('Y-m-d H:i:s',$eva['mytime']);
                }
                $new_message = array(
                    'type'=>$message_data['type'], 
                    'client_id'=>self::$kefu_arr['kefu']['room_id'][$client_name]['client_id'], 
                    'client_name'=>$client_name, 
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    'client_list' => $clients_list,
                    'group'=>$room_id,
                    'rank'=>$eva['rank'],
                    'mytime'=>$eva['mytime'],
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;
            case 'response':
                //----------------客服对全员发送消息--------------------------------//
                $client_name = $_SESSION['client_name'];

                if($message_data['to_client_id'] == 'all'){
                    $new_message = array(
                    'type'=>'response', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>$content,
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                    );

                    //发送消息给所有人
                    $room_id = self::$kefu_arr['kefu']['room_id'][$client_name]['group'];
                    Gateway::sendToGroup($room_id,json_encode($new_message));
                    
                    // 获取房间内所有用户列表 
                    self::$kefu_arr['client'] = Gateway::getClientSessionsByGroup($_SESSION['room_id']);
                    $clients_list = self::$kefu_arr['client'];

                    if($clients_list){
                        //client_id 对应 client_name
                        foreach($clients_list as $tmp_client_id=>$item){

                            MysqlKefu::addRedis(array(
                                'server_name'=>$client_name,
                                'mytime' => time()+8*3600,
                                'client_name' => $item['client_name'],
                                'status' => 2,
                                'content'=> $content,
                                ));
                        }
                    }

                    

                    //发送消息给自己
                    //这里有bug----“所有人”出不来
                    $new_message['content'] = $content;
                    Gateway::sendToCurrentClient(json_encode($new_message));
                    return;
                }
                //回传的消息
                $new_message = array(
                    'type'=>'response',
                    'from_client_id'=>$client_id, 
                    'from_client_name' =>$client_name,
                    'to_client_id'=>$message_data['to_client_id'],
                    'content'=>$content,
                    'time'=>date('Y-m-d H:i:s',time()+8*3600),
                );
                //将消息转发给指定的客户
                Gateway::sendToClient($message_data['to_client_id'],json_encode($new_message));

                //客服status = 2
                MysqlKefu::addRedis(array(
                    'server_name'=>$client_name,
                    'mytime' => time()+8*3600,
                    'client_name'   => $message_data['to_client_name'],
                    'status' => 2,
                    'content'=> $content,
                    ));

                //将消息返回给当前说话的客服
                $new_message['content'] = $content;
                return Gateway::sendToCurrentClient(json_encode($new_message));
            case 'evaluate':
                $to_client_name = $message_data['to_client_name'];
                $client_name = $_SESSION['client_name'];
                $message = $message_data['message'];
                $value = $message_data['evaluate'];

                /*
                    存储数据库
                */
                MysqlKefu::evaluate($client_name,$to_client_name,$value,$message);
                // var_dump($value);
                $new_message = array(
                    "to_client_id" => $client_id,
                    "time" => date('Y-m-d H:i:s',time()+8*3600),
                    'type' => "evaluate",
                    "content" => "感谢您的评价！",
                    );
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;
        }
	}

    //-----------------------这里是用户发给对应客服的提示-------------------//
     /**
    * 当客户端断开连接时，进行一些参数的设置
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id){
       // // debug
       // echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
       
       // 从房间的客户端列表中删除
        $to_client_id = "";
        if(isset($_SESSION['room_id'])){
            


            //---//
            if(empty(self::$kefu_arr['kefu']['room_id'])){
                /*
                    插入会话结束表----没有客服连入，是机器人
                */
                MysqlKefu::endSession($_SESSION['client_name'],
                    'robot',
                    null,
                    null,
                    true,
                    null);
                return;
            } 
            foreach (self::$kefu_arr['kefu']['room_id'] as $server => $value) {
                
                if($value['client_id'] == $client_id){
            
            //---------------------------------客服断线------------------------------// 
                    $new_message = array(
                    'type'=>'response', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$value['client_name'],
                    'to_client_id'=>'all',
                    'content'=>"<b>系统对你说</b>:对不起，跟你对话的客服下线了，请重新连接！",
                    'time'=>date('Y-m-d H:i:s',time()+3600*8),
                    );

                    /*
                        登录的时候，更新客服的状态
                    */

                    RobotResponse::updateStatus($_SESSION['client_name'],0);

                    //发送消息给所有人
                    $room_id = $value['group'];
                    Gateway::sendToGroup($room_id,json_encode($new_message));

                    //对self::$kefu_arr数组进行操作
                    unset(self::$kefu_arr['kefu']['room_id'][$server]);
                    return;
                }
            //---------------------------------用户断线------------------------------//
                if($value['group'] == $_SESSION['room_id']){
                    $to_client_id = $value['client_id'];
                    $to_client_name = $value['client_name'];
                    $room_id = $_SESSION['room_id'];
                    $new_message = array(
                        'type'=>'logout', 
                        'from_client_id'=>$client_id, 
                        'from_client_name'=>$_SESSION['client_name'], 
                        'time'=>date('Y-m-d H:i:s',time()+8*3600)
                        );

                    /*
                        插入会话结束表----客服介入模式
                    */
                    MysqlKefu::endSession($_SESSION['client_name'],
                        $to_client_name,
                        null,
                        null,
                        true,
                        null);

                    Gateway::sendToClient($to_client_id,json_encode($new_message));
                    

                    //发给监看者
                    $govern_id = "";
                    if(!empty(self::$govern)){
                        foreach (self::$govern as $key => $value) {
                            if($key == $to_client_name){
                                $govern_id = $value;
                            }
                        }
                    }

                    if("" != $govern_id){
                        Gateway::sendToClient($govern_id,json_encode($new_message));  
                    }


                    //self::$clients_count--;
                    if(self::$clients_count > 1000000)
                        self::$clients_count = 0;
                    self::$kefu_arr['kefu']['room_id'][$server]['is_busy']++;
                    self::$kefu_arr['kefu']['room_id'][$server]['count']--;
                    //测试
                    var_dump(self::$kefu_arr['kefu']['room_id'][$server]);
                    //因为少了一个用户，所以需要查看服务队列，是否有等待？
                    //有等，则进行服务
                    if(RedisQueue::getTotals() > 0){

                        //从队列里取出信息
                        $user_data = RedisQueue::getRedis();
                        $user_id = $user_data['client_id'];
                        $user_name = $user_data['client_name'];
                    
                    //给客户端发送消息，提醒已经连接上了。
                        $new_message = array(
                            'type'=>'prompt',
                            'from_client_id'=>$to_client_id,
                            'from_client_name'=>$to_client_name,
                            'to_client_id'=>$user_id,
                            'to_client_name'=>$user_name,
                            'room_id'=>$_SESSION['room_id'],
                            'content'=>"很高兴为你服务！",
                            'time'=>date('Y-m-d H:i:s',time()+8*3600)
                            );
                        Gateway::sendToClient($user_id,json_encode($new_message));
                       
                    //并让他加入客服组
                        Gateway::joinGroup($user_id,$_SESSION['room_id']);
                    
                    //客服服务人数+1
                        self::$kefu_arr['kefu']['room_id'][$to_client_name]['is_busy']--;
                        self::$kefu_arr['kefu']['room_id'][$to_client_name]['count']++;

                    //------------------给对应的客服发送在线用户刷新请求------------------------------//
                        $new_message = array(
                            'type'=>'flush', 
                            'client_id'=>$user_id, 
                            'client_name'=>$user_name, 
                            'time'=>date('Y-m-d H:i:s',time()+8*3600),
                            'to_client_id'=>$to_client_id,
                            'to_client_name'=>$to_client_name
                            );
                        Gateway::sendToClient($to_client_id,json_encode($new_message));
                        
                        $govern_id = "";
                        if(!empty(self::$govern)){
                            foreach (self::$govern as $key => $value) {
                                if($key == $to_client_name){
                                    $govern_id = $value;
                                }
                            }
                        }
                        if("" != $govern_id){
                            Gateway::sendToClient($govern_id,json_encode($new_message));
                        }

                    }
                    return;
                }
                
            }
            return;
       }
       //由于未设置用户服务队列，所以手动删除用户
       //self::$clients_count--;
   }
}