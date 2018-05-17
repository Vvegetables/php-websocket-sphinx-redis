<?php

namespace app\controllers;
use yii\web\Controller;
use app\models\UploadForm;
use app\models\Customer;
use yii\web\UploadedFile;
class ExcelController extends Controller{

    public function actionWrite(){

        $model = new UploadForm();

        if (\Yii::$app->request->isPost) {
            $model->file = UploadedFile::getInstance($model, 'file');
            if ($model->upload()) {
                // 文件上传成功
                // //数据库实例
                $customer = new Customer;
                if($model->getExtension() == "xlsx"){
                    $objReader = new PHPExcel_Reader_Excel2007();
                }else{
                    $objReader = new PHPExcel_Reader_Excel5();
                }
         
                $objPHPExcel = $objReader->load("./uploads/".$model->name); //$filename可以是上传的文件，或者是指定的文件
                $sheet = $objPHPExcel->getSheet(0); 
                $highestRow = $sheet->getHighestRow(); // 取得总行数 
                $highestColumn = $sheet->getHighestColumn(); // 取得总列数
                $k = 0; 
                //循环读取excel文件,读取一条,插入一条
                for($j=2;$j<=$highestRow;$j++){
 
                    //$a = $objPHPExcel->getActiveSheet()->getCell("A".$j)->getValue();//获取A列的值
                    $b = $objPHPExcel->getActiveSheet()->getCell("B".$j)->getValue();//获取B列的值
                    $c = $objPHPExcel->getActiveSheet()->getCell("C".$j)->getValue();//获取C列的值
                    // $d = $objPHPExcel->getActiveSheet()->getCell("D".$j)->getValue();//获取D列的值
                
                    //插入数据到数据库
                    $customer->name = $b;
                    $customer->age = $c;
                    //启用rules验证
                    $customer->validate();
                    //判断验证结果
                    if($customer->hasErrors()){
                        echo 'data is error';
                    die;
                    }
                    //没有错误就插入数据
                    $customer->save();
                    $k++;
                }
                var_dump($model->type);
                return;
            }else{
                print_r($model->getErrors());
                var_dump("failed");
            }
        }else{
            var_dump("not post");
        }
        
    }

    public function actionDd(){
        
        header("Content-Type: text/html; charset=utf-8");
        $up = new FileUpload;
        //设置属性(上传的位置， 大小， 类型， 名是是否要随机生成)
        $up -> set("path", "./uploads/");
        $up -> set("maxsize", 2000000);
        $up -> set("allowtype", array("xlsx","xls"));
        $up -> set("israndname", false);

        //使用对象中的upload方法， 就可以上传文件， 方法需要传一个上传表单的名子 pic, 如果成功返回true, 失败返回false
        if($up -> upload("file")) {
    
            if($up->getFileType() == "xlsx"){
                $objReader = new \PHPExcel_Reader_Excel2007();
            }else{
                $objReader = new \PHPExcel_Reader_Excel5();
            }
         
            $objPHPExcel = $objReader->load("./uploads/".$up->getFileName()); //$filename可以是上传的文件，或者是指定的文件
            $sheet = $objPHPExcel->getSheet(0); 
            $highestRow = $sheet->getHighestRow(); // 取得总行数 
            $highestColumn = $sheet->getHighestColumn(); // 取得总列数
            //循环读取excel文件,读取一条,插入一条
            for($j=2;$j<=$highestRow;$j++){
 
                //$a = $objPHPExcel->getActiveSheet()->getCell("A".$j)->getValue();//获取A列的值
                $b = $objPHPExcel->getActiveSheet()->getCell("B".$j)->getValue();//获取B列的值
                $c = $objPHPExcel->getActiveSheet()->getCell("C".$j)->getValue();//获取C列的值
                //$d = $objPHPExcel->getActiveSheet()->getCell("D".$j)->getValue();//获取D列的值
                //插入数据到数据库
                $customer = new Customer();
                $customer->name = $b;
                $customer->age = $c;
                $customer->save();
            }

        } 
    }
    
    public function actionIndex(){

        $sql = "SELECT * FROM customer";
        // $result = \Yii::$app->db->createCommand($sql);
        // $query = $result->queryAll();
        $query = Customer::find()->all();
        //var_dump($query);

        $objReader = new PHPExcel();
        if($objReader){
            echo "successful";
        }

        $headerArr = ['编号','用户名','年龄'];

        $fileName = "zcx.xlsx";
        $objPHPExcel = new PHPExcel();
        $objProps = $objPHPExcel->getProperties();
        
        //合并单元格
        $objPHPExcel->getActiveSheet()->mergeCells('A1:E1');
        //设置字体垂直居中  
        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);  
        //设置字体水平居中  
        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);  
  
        //设置标题
        //设置填充的样式和背景色
        $objPHPExcel->getActiveSheet()->getStyle( 'A1:E1')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1:E1')->getFill()->getStartColor()->setARGB('55ff5500');
        
        $key = ord('A');    //将ascll码转换成数字
        //设置字段
        foreach($headerArr as $v){

            $colum = chr($key); //将数字转换成ascll码
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.'2',$v);
            $key += 1;
        }
        $i = 3;
        $key = ord('A');
        foreach ($query as $kk => $value) {
            $colum = chr($key); //将数字转换成ascll码
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.$i,$value['id']);
            $colum = chr($key + 1);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.$i,$value['name']);
            $colum = chr($key + 2);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.$i,$value['age']);
            $i++;
        }




        $objPHPExcel->getActiveSheet()->setTitle('Simple');
        $objPHPExcel->setActiveSheetIndex(0);

        ob_end_clean();//清除缓冲区,避免乱码

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Cache-Control: max-age=0');

        $writer = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $writer->save('php://output');
    }

}

/** 
        file: fileupload.class.php  文件上传类FileUpload
        本类的实例对象用于处理上传文件，可以上传一个文件，也可同时处理多个文件上传
    */
    class FileUpload {  
        private $path = "./uploads";                    //上传文件保存的路径
        private $allowtype = array('jpg','gif','png');  //设置限制上传文件的类型
        private $maxsize = 1000000;                     //限制文件上传大小（字节）
        private $israndname = true;                     //设置是否随机重命名文件， false不随机
        
        private $originName;                            //源文件名
        private $tmpFileName;                           //临时文件名
        private $fileType;                              //文件类型(文件后缀)
        private $fileSize;                              //文件大小
        private $newFileName;                           //新文件名
        private $errorNum = 0;                          //错误号
        private $errorMess="";                          //错误报告消息
        
        /**
         * 用于设置成员属性（$path, $allowtype,$maxsize, $israndname）
         * 可以通过连贯操作一次设置多个属性值
         *@param    string  $key    成员属性名(不区分大小写)
         *@param    mixed   $val    为成员属性设置的值
         *@return   object          返回自己对象$this，可以用于连贯操作
         */
        function set($key, $val){
            $key = strtolower($key); 
            if( array_key_exists( $key, get_class_vars(get_class($this) ) ) ){
                $this->setOption($key, $val);
            }
            return $this;
        }

        /**
         * 调用该方法上传文件
         * @param   string  $fileFile   上传文件的表单名称 
         * @return  bool                如果上传成功返回数true 
         */
        
        function upload($fileField) {
            $return = true;
            /* 检查文件路径是滞合法 */
            if( !$this->checkFilePath() ) {             
                $this->errorMess = $this->getError();
                return false;
            }
            /* 将文件上传的信息取出赋给变量 */
            $name = $_FILES[$fileField]['name'];
            $tmp_name = $_FILES[$fileField]['tmp_name'];
            $size = $_FILES[$fileField]['size'];
            $error = $_FILES[$fileField]['error'];

            /* 如果是多个文件上传则$file["name"]会是一个数组 */
            if(is_Array($name)){        
                $errors=array();
                /*多个文件上传则循环处理 ， 这个循环只有检查上传文件的作用，并没有真正上传 */
                for($i = 0; $i < count($name); $i++){ 
                    /*设置文件信息 */
                    if($this->setFiles($name[$i],$tmp_name[$i],$size[$i],$error[$i] )) {
                        if(!$this->checkFileSize() || !$this->checkFileType()){
                            $errors[] = $this->getError();
                            $return=false;  
                        }
                    }else{
                        $errors[] = $this->getError();
                        $return=false;
                    }
                    /* 如果有问题，则重新初使化属性 */
                    if(!$return)                    
                        $this->setFiles();
                }
            
                if($return){
                    /* 存放所有上传后文件名的变量数组 */
                    $fileNames = array();            
                    /* 如果上传的多个文件都是合法的，则通过销魂循环向服务器上传文件 */
                    for($i = 0; $i < count($name);  $i++){ 
                        if($this->setFiles($name[$i], $tmp_name[$i], $size[$i], $error[$i] )) {
                            $this->setNewFileName(); 
                            if(!$this->copyFile()){
                                $errors[] = $this->getError();
                                $return = false;
                            }
                            $fileNames[] = $this->newFileName;  
                        }                   
                    }
                    $this->newFileName = $fileNames;
                }
                $this->errorMess = $errors;
                return $return;
            /*上传单个文件处理方法*/
            } else {
                /* 设置文件信息 */
                if($this->setFiles($name,$tmp_name,$size,$error)) {
                    /* 上传之前先检查一下大小和类型 */
                    if($this->checkFileSize() && $this->checkFileType()){   
                        /* 为上传文件设置新文件名 */
                        $this->setNewFileName(); 
                        /* 上传文件   返回0为成功， 小于0都为错误 */
                        if($this->copyFile()){ 
                            return true;
                        }else{
                            $return=false;
                        }
                    }else{
                        $return=false;
                    }
                } else {
                    $return=false;  
                }
                //如果$return为false, 则出错，将错误信息保存在属性errorMess中
                if(!$return)
                    $this->errorMess=$this->getError();   

                return $return;
            }
        }

        /** 
         * 获取上传后的文件名称
         * @param   void     没有参数
         * @return  string  上传后，新文件的名称， 如果是多文件上传返回数组
         */
        public function getFileName(){
            return $this->newFileName;
        }
        public function getFileType(){
            return $this->fileType;
        }
        /**
         * 上传失败后，调用该方法则返回，上传出错信息
         * @param   void     没有参数
         * @return  string   返回上传文件出错的信息报告，如果是多文件上传返回数组
         */
        public function getErrorMsg(){
            return $this->errorMess;
        }
        
        /* 设置上传出错信息 */
        private function getError() {
            $str = "上传文件<font color='red'>{$this->originName}</font>时出错 : ";
            switch ($this->errorNum) {
                case 4: $str .= "没有文件被上传"; break;
                case 3: $str .= "文件只有部分被上传"; break;
                case 2: $str .= "上传文件的大小超过了HTML表单中MAX_FILE_SIZE选项指定的值"; break;
                case 1: $str .= "上传的文件超过了php.ini中upload_max_filesize选项限制的值"; break;
                case -1: $str .= "未允许类型"; break;
                case -2: $str .= "文件过大,上传的文件不能超过{$this->maxsize}个字节"; break;
                case -3: $str .= "上传失败"; break;
                case -4: $str .= "建立存放上传文件目录失败，请重新指定上传目录"; break;
                case -5: $str .= "必须指定上传文件的路径"; break;
                default: $str .= "未知错误";
            }
            return $str.'<br>';
        }

        /* 设置和$_FILES有关的内容 */
        private function setFiles($name="", $tmp_name="", $size=0, $error=0) {
            $this->setOption('errorNum', $error);
            if($error)
                return false;
            $this->setOption('originName', $name);
            $this->setOption('tmpFileName',$tmp_name);
            $aryStr = explode(".", $name);
            $this->setOption('fileType', strtolower($aryStr[count($aryStr)-1]));
            $this->setOption('fileSize', $size);
            return true;
        }
    
        /* 为单个成员属性设置值 */
        private function setOption($key, $val) {
            $this->$key = $val;
        }

        /* 设置上传后的文件名称 */
        private function setNewFileName() {
            if ($this->israndname) {
                $this->setOption('newFileName', $this->proRandName());  
            } else{ 
                $this->setOption('newFileName', $this->originName);
            } 
        }
        
        /* 检查上传的文件是否是合法的类型 */
        private function checkFileType() {
            if (in_array(strtolower($this->fileType), $this->allowtype)) {
                return true;
            }else {
                $this->setOption('errorNum', -1);
                return false;
            }
        }
        
        /* 检查上传的文件是否是允许的大小 */
        private function checkFileSize() {
            if ($this->fileSize > $this->maxsize) {
                $this->setOption('errorNum', -2);
                return false;
            }else{
                return true;
            }
        }

        /* 检查是否有存放上传文件的目录 */
        private function checkFilePath() {
            if(empty($this->path)){
                $this->setOption('errorNum', -5);
                return false;
            }
            if (!file_exists($this->path) || !is_writable($this->path)) {
                if (!@mkdir($this->path, 0755)) {
                    $this->setOption('errorNum', -4);
                    return false;
                }
            }
            return true;
        }
        
        /* 设置随机文件名 */
        private function proRandName() {        
            $fileName = date('YmdHis')."_".rand(100,999);       
            return $fileName.'.'.$this->fileType; 
        }
        
        /* 复制上传文件到指定的位置 */
        private function copyFile() {
            if(!$this->errorNum) {
                $path = rtrim($this->path, '/').'/';
                $path .= $this->newFileName;
                if (@move_uploaded_file($this->tmpFileName, $path)) {
                    return true;
                }else{
                    $this->setOption('errorNum', -3);
                    return false;
                }
            } else {
                return false;
            }
        }
    }
