<?php
namespace GatewayWorker\Lib;

/*
	服务队列----->用户队列，客服满员的时候，依靠先进先出的队列顺序排队
*/

class RedisQueue{
	private  static $instance = null;
	private  static $list = 'list';	//	列表名字
	private  static $ip = '127.0.0.1';
	private  static $port = 6379;
	//首先执行这个函数
	public  static function getEntry(){
		if(self::$instance == null){
			self::$instance = new \Redis();
			self::$instance->connect(self::$ip,self::$port);
		}
		//return self::$redis;
	}
	
	//加入等待队列
	//list
	//lPush()的语法格式是：$redis->lPush(key, value)，作用是将value添加到链表key的左边（头部）
	//rPop()的语法格式是：$redis->rPop(key)，作用是将链表key的右边（尾部）元素删除。  
	//lSize()的语法格式是：$redis->lSize(key)，作用是返回链表key中有多少个元素。
	//lGet()的语法格式是：$redis->lGet(key, index)，作用是返回链表key的index位置的元素值。 
	//lSet()的语法格式是：$redis->lSet(key, index, value)，作用是将链表key的index位置的元素值设为value。 
	//hash
	//hSet()给哈希表中某个 key 设置值.如果值已经存在, 返回 false
	//hSetNx()当哈希表中不存在某 key 时，给该 key 设置一个值  
	//hGet()获得某哈希 key 的值.如果 hash 表不存在或对应的 key 不存在，返回 false  
	//hLen()LONG 哈表中 key 的数量.如果 hash 表不存在，或者对应的 key 的值不是 hash 类型，返回 false 
	//hDel()删除一个哈希 key.如果 hash 表不存在或对应的 key 不存在，返回 false  
	//hKeys()获得哈希表中所有的 key  
	//hVals()获得哈希表中所有的值
	//hGetAll()获得一个哈希表中所有的 key 和 value  
	//hExists()检查哈希 key是否存在  
	//hMSet()给哈希表设置多个 key 的值 
	//hMGet()获得哈希表中多个 key 的值
	public static function addRedis($client_id,$array_attr){
		$temp = self::$list;
		self::$instance->lPush($temp,$client_id);	//用户队列，头进尾出
		self::$instance->hMSet($client_id,$array_attr);	//与用户队列相对应的数据哈希
	}
	//取出队列，进行服务
	public static function getRedis(){
		$temp = self::$list;
		if(self::$instance->lSize($temp)<=0){
			return false;
		}

		//队列里面还有用户
		while(self::$instance->lSize($temp) > 0){
			//从尾部取出一个用户
			$client_id = self::$instance->rPop(self::$list);

			if(self::$instance->exists($client_id)){	//如果为真那么用户还没有下线
				
				$array_attr = self::$instance->hGetAll($client_id);
				//删除用户
				self::$instance->del($client_id);
				return $array_attr;
			}
		}
		return false;	
	}
	//删除队列中的用户信息
	public static function delRedis($client_id){
		self::$instance->del($client_id);
	}
	public static function getTotals(){
		return self::$instance->lLen(self::$list);
	}

	public static function flushDB(){
		//清空数据
		self::$instance->flushAll();
	}

	//客服席位管理
	public static function randDispatch(&$kefu_list){
		$to_client_name="";
        $to_client_id="";
        $flag = false;
        foreach ($kefu_list as $key => $value) {
        	$temp_arr[] = $value;
        }

        //随机分配
        for($i = 0; $i < 10; $i++){
            $index = mt_rand(0,20) % (count($temp_arr));
            if($temp_arr[$index]['is_busy'] > 0){
                //$_SESSION['room_id'] = $temp_arr[$index]['group'];
                $flag = true;
                $to_client_id = $temp_arr[$index]['client_id'];
                $to_client_name = $temp_arr[$index]['client_name'];
               	$kefu_list[$temp_arr[$index]['client_name']]['is_busy']--;
                $kefu_list[$temp_arr[$index]['client_name']]['count']++;

                //测试
                // echo __LINE__,PHP_EOL;
                // var_dump($kefu_list[$temp_arr[$index]['client_name']]);
                break;
            }
        }

        if($flag == false){
        //顺序分配
        	foreach($kefu_list as $key => $value){
                if($value['is_busy'] > 0){
                    //$_SESSION['room_id'] = $value['group'];
                    $room_id = $value['group'];
                    $flag = true;
                    $to_client_id = $value['client_id'];
                    $to_client_name = $value['client_name'];
                    //测试
                    // echo __LINE__,PHP_EOL;
                    // var_dump($kefu_list[$key]['is_busy']);
                    $kefu_list[$key]['is_busy']--;
                    $kefu_list[$key]['count']++;
                    break;
                }
            }                        
        }
        return $to_client_name;	//返回值为true，为$_SESSION['room_id']设值
        //false则不设值。
	}
	public static function averageDispatch(&$kefu_list){

		$cusor = array('min' => 1000,
			'index' => '',
			);
		$nums = count($kefu_list);
		foreach($kefu_list as $key => $value){
			if($value['is_busy'] > 0 && $value['count'] < $cusor['min']){
				$cusor['min'] = $value['count'];
				$cusor['index'] = $key;
			}
		}
		// 遍历完之后，如果index不是空值，那么久可以分配。
		
		if($cusor['index'] != ''){
			$kefu_list[$cusor['index']]['is_busy']--;
			$kefu_list[$cusor['index']]['count']++;
		}
		return $cusor['index'];	//返回索引

	}


}

//测试
// RedisQueue::getEntry();
// RedisQueue::addRedis('test',array('hello'=>'world','key'=>'value'));
// $getValue = RedisQueue::getRedis();
// var_dump($getValue);

// function test($temp){
// 	++$temp;
// 	var_dump($temp);
// }
// test(RedisQueue::$port);
// var_dump(RedisQueue::$port);
