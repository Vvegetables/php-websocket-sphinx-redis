<?php
namespace GatewayWorker\Lib;

/*
	会话记录的存储
	1.客服
	2.机器人
*/

class MysqlKefu{
	
	private static $mysqli = null;
	private static $m_port = 3306;
	private static $m_user = 'root';
	private static $m_pass = '123456';
	private static $m_db   = 'smartservice';
	

	private static $redis  = null;
	private static $ip 	   = '127.0.0.1';
	private static $port   = 6379;
	
	//首先执行这个函数
	public  static function getEntry(){
		if(self::$redis == null){
			
			self::$redis = new \Redis();
			self::$redis->connect(self::$ip,self::$port);

			self::$mysqli = new \mysqli("10.21.30.204",self::$m_user,self::$m_pass,self::$m_db);
			//设置字符集
			self::$mysqli->set_charset('utf8');
		}
		//return self::$redis;
	}
	
	public static function addRedis($array_attr){
		self::$redis->lPush('server_name',$array_attr['server_name']);
		self::$redis->lPush('mytime',$array_attr['mytime']);
		self::$redis->lPush('status',$array_attr['status']);
		self::$redis->lPush('client_name',$array_attr['client_name']);
		self::$redis->lPush('content',$array_attr['content']);
		if(isset($array_attr['ip']))
			self::$redis->lPush('ip',$array_attr['ip']);
		self::$redis->sAdd('kefus',$array_attr['server_name']);
		
	}
	//取出数据，进行数据存储
	public static function getRedis(){
		// if(self::$mysqli == null){
		// 	self::$mysqli = new \mysqli(self::$ip,self::$m_user,self::$m_pass,self::$m_db);
		// 	self::$mysqli->set_charset('utf8');
		// }
		$count = self::$redis->lLen('server_name');
		while($count > 0){


			$server_name  = self::$redis->rPop('server_name');
			$mytime 	 = self::$redis->rPop('mytime');
			$status  = self::$redis->rPop('status') ;
			$client_name 	 = self::$redis->rPop('client_name');
			$content = self::$redis->rPop('content');
			if(self::$redis->exists('ip'))
				$ip = self::$redis->rPop('ip');

			/*
				客服对话是否解决和等级评分以 用户为主题重新设立一张表
			*/

			if(self::$redis->sIsMember('kefus',$server_name)){
				self::$redis->sRem('kefus',$server_name);

				$sql = " SELECT nickname FROM `user` WHERE nickname = '$server_name'; ";
				self::$mysqli->query($sql);
				// if(self::$mysqli->affected_rows == 0 && $server_name != 'robot'){
				// 	$sql = " INSERT INTO kefus (servername,nickname,status) VALUES('$server_name','$server_name',1); ";
				// 	self::$mysqli->query($sql);							
				// }
			}

			if($server_name == 'robot'){
				$sql = " INSERT INTO `robot_contents` (clientname,mytime,content,status,ip) VALUES('$client_name','$mytime','$content','$status','$ip'); ";
				self::$mysqli->query($sql);
				continue;
			}

			// $sql = " INSERT INTO users(client,rank,isfinish) 
			// 	VALUES('client',1,1); ";
			// self::$mysqli->query($sql);

			/*
				一个客服一张会话表---取消，现在全部存在一张表中
			*/

			// $sql = " CREATE TABLE IF NOT EXISTS `$server_name` (
			// 		id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
			// 		mytime VARCHAR(12) NOT NULL,
			// 		status INT(2) NOT NULL,
			// 		clientname VARCHAR(30) NOT NULL,
			// 		content VARCHAR(255) NOT NULL
			// 	); ";
			// self::$mysqli->query($sql);

			$sql = " INSERT INTO `sessionsrecords` (`client`,`service`,`mytime`,`way`,`status`,`content`) VALUES ('$client_name','$server_name','$mytime','','$status','$content'); ";
			self::$mysqli->query($sql);
			$count--;
		}
		// self::$mysqli->close();
		// self::$mysqli = null;
		return false;
	}
	//删除队列中的用户信息
	public static function delRedis(){

	}
	public static function endSession($client,$user,$way,$rank,$finished,$trans){
		$connect = new \mysqli("10.21.30.204",self::$m_user,self::$m_pass,self::$m_db);
		if($connect->errno){
			var_dump($connect->error);
			return;
		}
		$time = time()+8*3600;
		$sql = " INSERT INTO `sessions`(`client`,`user`,`time`,`way`,`rank`,`finished`,`trans`) VALUES('$client','$user','$time','$way','$rank','$finished','$trans'); ";
		$connect->query($sql);
		$connect->close();
		$connect = null;

	}

	public static function evaluate($client_name,$server_name,$value,$content){
		self::getEntry();
		$mytime = time() + 8*3600;
		$sql = " INSERT INTO `evaluate`(`clientname`,`servername`,`rank`,`content`,`mytime`) VALUES('$client_name','$server_name','$value','$content','$mytime');";
		self::$mysqli->query($sql);
	}

	public static function flushDB(){
		//清空数据
		self::$redis->flushAll();
	}
}

//测试
// RedisQueue::getEntry();
// RedisQueue::addRedis('test',array('hello'=>'world','key'=>'value'));
// $getValue = RedisQueue::getRedis();
// var_dump($getValue);
