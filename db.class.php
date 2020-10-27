<?php
/*
    Build By Romm Hui 2020
    CopyRight © 2019-2020 RommHui. All rights reserved.
*/
class db{

    var $path = null;
    var $name = null;
    var $filetype = null;
    var $startquery = 0;

    function __construct($name){
        $name = strtolower($name);
        $root = "./data/";
        $this->filetype = ".db";
        $this->name = $name;
        $path = $root.$name."/";
        $this->path = $path;
        if(!file_exists($path)&&!is_dir($path)){

            // 创建文件夹
            $this->create("database",$name,$root);

        }

    }

    function query($rql){
        $this->startquery = $this->getMilliseconds();
        $rql = trim(preg_replace("/[\r\n\t]/","",$rql));
        if(preg_match("/^(create|insert|delete|update|select|exist)/i",$rql,$data)){
            $type = strtolower($data[0]);
            switch($type){
                case "create":
                    return $this->createHandler($rql);
                    break;
                case "insert":
                    return $this->insertHandler($rql);
                    break;
                case "delete":
                    return $this->deleteHandler($rql);
                    break;
                case "exist":
                    return $this->existHandler($rql);
                    break;
                case "update":
                    return $this->updateHandler($rql);
                    break;
                case "select":
                    return $this->selectHandler($rql);
                    break;
            }
        }
    }

    function existHandler($rql){

        if(preg_match("/^exist +`([^`]+)` *$/",$rql,$match)){

            $name = $match[1];
            $dbfile = $this->path.$name.$this->filetype;

            if(file_exists($dbfile)){
                return $this->getMsg(true,"OK",1);
            }else{
                return $this->getMsg(false,"table `{$name}` file is not existed.",0);
            }

        }

        return $this->getMsg(false,"RQL have something error, check your RQL please.",0);

    }

    function deleteHandler($rql){

        if(preg_match("/^delete +from +`(\w+)` +where +`row` += +`(\d+)` *$/i",$rql,$data)){

            $name = $data[1];
            $row = intval($data[2]);

            $path = $this->path.$name.".fmt";
            $dbfile = $this->path.$name.$this->filetype;
            if(file_exists($path)){
                
                $fmt = json_decode(file_get_contents($path),true);
                $row_length = $fmt["row_length"];

                if(file_exists($dbfile)){

                    $fp = fopen($dbfile,"r+");
                    $finfo = fstat($fp);
                    if($finfo["size"]==0||$finfo["size"]<($row+1)*$row_length){
                        return $this->getMsg(false,"row is overflow.",0);
                    }
                    $lastpos = $finfo["size"]-$row_length;
                    $aftersize = $finfo["size"]-$row_length;
                    if($lastpos<0){
                        $lastpos = 0;
                    }
                    
                    $editpos = $row*$row_length;
                    for($i = 0;$i<$row_length;$i+=1024){
                        fseek($fp,$lastpos);
                        $tmp = fread($fp,1024);
                        $tmplen = strlen($tmp);
                        $lastpos += $tmplen;
                        fseek($fp,$editpos);
                        fwrite($fp,$tmp);
                        $editpos += $tmplen;
                    }
                    ftruncate($fp,$aftersize);
                    fclose($fp);

                    return $this->getMsg(true,"OK",$finfo["size"] == 0?0:1);

                }else{
                    return $this->getMsg(false,"table `{$name}` file is not existed.",0);

                }


            }else{

                return $this->getMsg(false,"unknown table `{$name}`.",0);

            }

        }else{

            return $this->getMsg(false,"RQL have something error, check your RQL please.",0);

        }

    }

    function updateHandler($rql){
        
        if(preg_match("/^update +`(\w+)` +set +(.*) +where +`row` += +`(\d+)` *$/i",$rql,$data)){
            
            $name = $data[1];
            $row = intval($data[3]);
            $newdata = array();
            if(preg_match_all("/(`(\w+)` += +`([^`]+)`)+/",$data[2],$values)){
                foreach($values[2] as $k => $v){
                    $newdata[$v] = $values[3][$k];
                }
            }else{
                return $this->getMsg(false,"RQL have something error, check your RQL please.",0);
            }

            $path = $this->path.$name.".fmt";
            $dbfile = $this->path.$name.$this->filetype;

            if(file_exists($path)){

                $fmt = json_decode(file_get_contents($path),true);
                $row_length = $fmt["row_length"];

                if(file_exists($dbfile)){

                    $fp = fopen($dbfile,"r+");
                    $finfo = fstat($fp);
                    if($finfo["size"]==0||$finfo["size"]<$row*$row_length){
                        return $this->getMsg(false,"row is overflow.",0);
                    }

                    $start = $row * $row_length;

                    foreach($fmt["structure"] as $k => $v){
                        $l = $v["length"];
                        if(isset($newdata[$k])){

                            if($l<$this->getDataLength($newdata[$k])){
                                return $this->getMsg(false,"new data length of the column `{$k}` is overflow.",0);
                            }
                            $reg = "";
                            
                            switch($v["type"]){
                                case "int":
                                    $reg .= " *`([\d\-]+)` *,";
                                    break;
                                case "string":
                                    $reg .= " *`([^`]*)` *,";
                                    break;
                            }
                            if(!preg_match("/".$reg."/",$newdata[$k])){
                                $wait = str_pad($newdata[$k],$l,"\0",STR_PAD_LEFT);
                                fseek($fp,$start);
                                fwrite($fp,$wait);
                            }else{
                                return $this->getMsg(false,"new data of column `{$k}` data type is error.",0);
                            }

                        }
                        $start += $l;

                    }
                    fclose($fp);

                    return $this->getMsg(true,"OK",1);

                }else{
                    return $this->getMsg(false,"table `{$name}` file is not existed.",0);

                }


            }else{

                return $this->getMsg(false,"unknown table `{$name}`.",0);

            }

        }else{

            return $this->getMsg(false,"RQL have something error, check your RQL please.",0);

        }
        
    } 

    private function getDataLength($data){
        $c = 0;
        for($i = 0;$i<strlen($data);$i++){
            $c += 1;
        }
        return $c;
    }

    function createHandler($rql){
        
        if(preg_match("/^create +(table) +`(\w+)` *\( *(.+)\) *$/i",$rql,$data)){
            $type = $data[1];
            $name = $data[2];
            $struct = $data[3];
            $arr_struct = array();
            $countlength = 0;

            $path = $this->path.$name.".fmt";
            if(file_exists($path)){

                return $this->getMsg(false,"table `{$name}` is existed.",0);

            }

            if(preg_match_all("/(`(\w+)` +(int|string)\((\d+)\))+/",$struct,$data1)){
                foreach($data1[2] as $k => $v){
                    $arr_struct[$v] = array(
                        "type"=>strtolower($data1[3][$k]),
                        "length"=>$data1[4][$k]
                    );
                    $countlength += intval($data1[4][$k]);
                }
            }
            $this->create("table",$name,$this->path);
            $fp = fopen($path,"w");
            fwrite($fp,json_encode(array(
                "name"=>$name,
                "row_length"=>$countlength,
                "structure"=>$arr_struct
            )));
            fclose($fp);
            return $this->getMsg(true,"OK",0);

        }else{

            return $this->getMsg(false,"RQL have something error, check your RQL please.",0);

        }
    }
    function selectHandler($rql){

        if(preg_match("/^select +\* +from +`(\w+)` *(limit ((\d+),)?(\d+))? *$/i",$rql,$data)){

            $name = $data[1];
            $islmit = isset($data[2]);
            $path = $this->path.$name.".fmt";
            $dbfile = $this->path.$name.$this->filetype;
            if(file_exists($path)){
                $fmt = json_decode(file_get_contents($path),true);
                $row_length = $fmt["row_length"];
                $offset = -1;
                $end = -1;
                if($islmit){
                    $offset = intval($data[4]);
                    $end = intval($data[5]);
                }
                if(file_exists($dbfile)){

                    $fp = fopen($dbfile,"r");
                    $result = array();
                    $times = 0;
                    fseek($fp,$offset*$row_length);
                    while(($tmp = fread($fp,$row_length))){
                        
                        if($offset!=-1&&$end!=-1){
                            if($end<=$times){
                                break;
                            }
                        }
                        $s = 0;
                        $tmp_arr = array();
                        $tmp_arr["rows"] = $times + ($offset==-1?0:$offset);
                        foreach($fmt["structure"] as $k => $v){
                            
                            $tmpd = substr($tmp,$s,$v["length"]);
                            $s += $v["length"];
                            $tmpdata = ltrim($tmpd,"\0");
                            switch($v["type"]){
                                case "int":
                                    $tmpdata = intval($tmpdata);
                                    break;
                            }
                            $tmp_arr[$k] = $tmpdata;

                        }
                        $result[] = $tmp_arr;
                        $times += 1;
                    }
                    fclose($fp);
                    $r = $this->getMsg(true,"OK",0);
                    $r["dataset"] = $result;
                    return $r;

                }else{
                    return $this->getMsg(false,"table `{$name}` file is not existed.",0);

                }
            }else{
                return $this->getMsg(false,"unknown table `{$name}`.",0);
            }

        }else{
            return $this->getMsg(false,"RQL have something error, check your RQL please.",0);
        }

    }
    function insertHandler($rql){

        if(preg_match("/^insert +into +`(\w+)` *\( *(.+)\) *$/i",$rql,$data)){
            $name = $data[1];
            $data_rql = $data[2];
            $path = $this->path.$name.".fmt";
            $dbfile = $this->path.$name.$this->filetype;
            if(file_exists($path)){
                $fmt = json_decode(file_get_contents($path),true);
                $reg = "";
                $lens = array();
                foreach($fmt["structure"] as $k => $v){
                    $l = intval($v["length"]);
                    
                    $lens[] = $l;
                    switch($v["type"]){
                        case "int":
                            $reg .= " *`([\d\-]+)` *,";
                            break;
                        case "string":
                            $reg .= " *`([^`]{0,})` *,";
                            break;
                    }
                    

                }
                $reg = substr($reg,0,strlen($reg)-1);
                
                if(preg_match_all("/".$reg."/",$data_rql,$data1)){
                    $fp = fopen($dbfile,"a+");
                    $wait = "";
                    foreach($lens as $k => $v){
                        
                        if($v<$this->getDataLength($data1[$k+1][0])){
                            return $this->getMsg(false,"new data length of the {$K} column is overflow.",0);
                        }

                        $wait .= str_pad($data1[$k+1][0],$v,"\0",STR_PAD_LEFT);

                    }

                    fwrite($fp,$wait);

                    fclose($fp);

                    return $this->getMsg(true,"OK",1);

                }else{

                    return $this->getMsg(false,"your insert data type have some error.",0);

                }

            }else{

                return $this->getMsg(false,"unknown table `{$name}`.",0);

            }

        }else{

            return $this->getMsg(false,"RQL have something error, check your RQL please.",0);

        }

    }

    private function create($type,$name,$parent){

        switch($type){
            case "table":
                $f = fopen($this->path.$name.$this->filetype,"x");
                fclose($f);
                break;
            case "database":
                mkdir($parent.$name,0777,true);
                break;
        }

    }

    private function getMsg($status,$msg,$affected_rows){

        return array(
            "status"=>$status,
            "msg"=>$msg,
            "affected_rows"=>$affected_rows,
            "timestamp"=>$this->getMilliseconds(),
            "time_consuming"=>$this->getMilliseconds() - $this->startquery
        );

    }

    function getMilliseconds(){
        return intval((microtime(true)*1000)."");
    }
}
?>
