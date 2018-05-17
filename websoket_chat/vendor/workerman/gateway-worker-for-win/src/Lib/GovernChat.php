<?php
namespace GatewayWorker\Lib;

/*
	管理员监管客服实时会话
*/

class GovernChat{

	private static $connect = null;

	public static function start(){
		
		if(self::$connect == null){
			self::$connect = new \mysqli('10.21.30.204','root','123456','smartservice');
			if(self::$connect->errno){
				var_dump(self::$connect->error);
				return;
			}
			self::$connect->set_charset("utf8");
		}
	}

	public static function getHistory($kefu_name,$client_name){
		//回传的数据
		$result = array();
		$nums = count($client_name);
		//没有用户
		if($nums == 0){
			return false;
		}

		//只有一个用户
		if($nums == 1){
			$sql = " SELECT client,service,mytime,status,content FROM `sessionsrecords` WHERE client = '{$client_name[0]}' AND service = '$kefu_name' ORDER BY mytime; ";
			$res = self::$connect->query($sql);
			while($row = $res->fetch_array(MYSQLI_ASSOC)){
				$result[] = array(
					'client'  => $row['client'],
					'service' => $row['service'],
					'mytime'  => date("Y-m-d H:i:s",$row['mytime']),
					'status'  => $row['status'],
					'content' => $row['content']
					);
			}
			self::$connect->close();
			self::$connect = null;
			return $result;
		}
		//不止一个用户
		$sql = "SELECT client,service,mytime,status,content FROM `sessionsrecords` WHERE client IN (";
		for($i = 0; $i < $nums - 1; $i++){
			$sql .= "'$client_name[$i]',";
		}
		$sql .= "'$client_name[$i]'".") AND service = "."'$kefu_name' ".'ORDER BY mytime;';
		$res = self::$connect->query($sql);
		while($row = $res->fetch_array(MYSQLI_ASSOC)){
			$result[] = array(
				'client'  => $row['client'],
				'service' => $row['service'],
				'mytime'  => date("Y-m-d H:i:s",$row['mytime']),
				'status'  => $row['status'],
				'content' => $row['content']
				);
		}
		self::$connect->close();
		self::$connect = null;
		return $result;
	}
}