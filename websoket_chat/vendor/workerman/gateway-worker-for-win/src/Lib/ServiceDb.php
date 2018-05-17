<?php
namespace GatewayWorker\Lib;

/*
	获得客服信息
*/

class ServiceDb{
	
	private static $mysqli = null;

	private static function start(){
		
		if(self::$mysqli == null){
			self::$mysqli = new \mysqli('10.21.30.204','root','123456','smartservice');
			if(self::$mysqli->errno){
				var_dump(self::$mysqli->error);
				return;
			}
			self::$mysqli->set_charset("utf8");
		}
	}

	public static function getInfo($username){

		self::start();
		$query = " SELECT `group`,`cap` FROM `user` WHERE nickname = ?";
		$stmt = self::$mysqli->stmt_init();
		$stmt->prepare($query);
		$stmt->bind_param('s',$username);
		$stmt->execute();
		$stmt->bind_result($group,$cap);
		$stmt->fetch();
		$stmt->close();
		self::$mysqli->close();
		//var_dump($info);
		return array('group' => $group,
			'cap' => $cap,
			);
	}

	public static function getEvaluate($servername){
		self::start();
		$sql = " SELECT rank,mytime FROM `evaluate` WHERE servername = '$servername' ORDER BY id DESC ; ";
		$res = self::$mysqli->query($sql);
		// $row[] = "无";
		// $row[] = "无";
		$row = $res->fetch_array(MYSQLI_NUM);
		if(isset($row[0])){
			return array(
				'rank'  =>$row[0],
				'mytime'=>$row[1],
			);
		}else{
			return array(
				'rank'  =>'无',
				'mytime'=>'无',
			);
		}
		
	}

}

/*
	测试
*/

//ServiceDb::getInfo('419734356@qq.com');