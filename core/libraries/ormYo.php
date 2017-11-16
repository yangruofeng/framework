<?php
/*
 * 限制字段名：table\children
 *dsn格式：array(
		"db_host"=>"127.0.0.1",
		"db_user"=>"root",
		"db_pwd"=>"z",
		"db_name"=>"db_tmd",
		"db_port"=>3388)
 * 
 * */
interface IormObj{
    public function getName();
}
interface IormTrigger{
    const BEFORE_INSERT='BEFORE_INSERT';
    const BEFORE_UPDATE='BEFORE_UPDATE';
    const BEFORE_DELETE='BEFORE_DELETE';
    const AFTER_INSERT='AFTER_INSERT';
    const AFTER_UPDATE='AFTER_UPDATE';
    const AFTER_DELETE='AFTER_DELETE';

    public function after_insert(ormDataRow $old,ormDataRow $current);
    public function after_delete(ormDataRow $old,ormDataRow $current);
    public function after_update(ormDataRow $old,ormDataRow $current);
    public function before_insert(ormDataRow $old,ormDataRow $current);
    public function before_delete(ormDataRow $old,ormDataRow $current);
    public function before_update(ormDataRow $old,ormDataRow $current);
}
class ormYo{
    static $IDField="uid";//自增列字段
    static $schema_path="";//schema的存放路径
    static $log_path="";//log写入路径
    static $freez=false;//数据库双向开关，ture则不反向修改数据库
    static $cascade_delete=false;//是否级联删除
    static $lang_a=array('en','zh_cn','zh_tw');
    static $lang_current="en";
    static $lang_MESSAGE;//todo:格式化错误消息
    static $dsnPool;//连接配置池
    static $tablePool;//table缓存池
    static $connPool;//连接池
    static $table_schema_pool=array();
    static $default_db_key="";//默认的数据库配置项
    static $table_lang_pool=array();
    static $table_source_pool=array();
    static $driver_name=ormDriver::MYSQLI;
    public static function wlog($log_type,$content){
        if(is_object($content) || is_array($content)){
            $content=json_encode($content);
        }
        $fn="";
        $suffix="\n";//for all
        if(self::$log_path){
            $fn=self::$log_path .'/'.$log_type."-".date('Ymd').".log";
        }else{
            $fn=$log_type."-".date('Ymd').".log";
        }
        file_put_contents($fn, $content.$suffix, FILE_APPEND);
        return $fn;
    }
    static function setup($links){
        $is_pool=false;
        if(is_array($links)){
            foreach($links as $v){
                if(is_array($v)){
                    $is_pool=true;break;
                }
            }
        }
        if(!$is_pool){
            self::$dsnPool['default']=$links;
        }else{
            self::$dsnPool=$links;
        }
    }
    static function obj2array($obj,$is_deep=true){
        $vars = is_object($obj) ? get_object_vars($obj) : $obj;
        //	if(!is_array($vars)) return $vars;
        //$arr=array();
        foreach ($vars as $k => $v){
            if(is_numeric(stripos($k,'parent_obj'))) continue;
            $new_v = $is_deep&&(is_array($v) || is_object($v)) ? self::obj2array($v) : $v;
            $arr[$k] = $new_v;
        }
        return $arr;
    }
    static function array2obj($arr, $obj,$create=false) {
        $vars=get_class_vars(get_class($obj));
        foreach($vars as $pn=>$pv){
            if (is_array($pv)) { continue;
            }
            $obj->$pn=$arr[$pn];
        }
        if($create){
            foreach($arr as $k=>$v){
                if(!is_numeric($k)){
                    $obj->$k=$v;
                }
            }
        }
    }
    //findRelation=true,自动从配置去找关系
    public static function addTable($tableName,$schema,$dsn){
        if(self::$tablePool[$tableName]) return self::$tablePool[$tableName];
        $c=self::Conn($dsn);
        $t=new ormDataTable($tableName,$c,$schema);
        self::$tablePool[$tableName]=$t;
        return $t;
    }
    public static function Table($tableName,$schema,$dsn){
        return self::addTable($tableName,$schema,$dsn);
    }
    public static function View(){//准备弃用

    }
    public static function Reader($dsn){
        $c=self::Conn($dsn);
        return new ormReader($c);
    }
    public static function Conn($dsn=null){
        if(!$dsn && self::$default_db_key){
            $dsn=self::$default_db_key;
        }
        if(!$dsn){
            foreach(self::$dsnPool as $k=>$v){
                $dsn_key=$k;
                $dsn_value=$v;
                break;
            }
        }else{
            $dsn_key=$dsn;
            $dsn_value=self::$dsnPool[$dsn];
        }
        if(!$dsn_key){
            throw new Exception('ormYo must to setup with connection');
        }
        if(self::$connPool[$dsn_key]){
            return self::$connPool[$dsn_key];
        }else{
            $c=new ormConnection($dsn_key,$dsn_value,ormYo::$driver_name);

            self::$connPool[$dsn_key]=$c;
            return $c;
        }
    }
    /*
     * @return dbMysqlI
     * */
    public static function dbI($dsn=null){
        if(!$dsn){
            foreach(self::$dsnPool as $k=>$v){
                $dsn_key=$k;
                $dsn_value=$v;
                break;
            }
        }else{
            $dsn_key=$dsn;
            $dsn_value=self::$dsnPool[$dsn];
        }
        if(!$dsn_key){
            throw new Exception('ormYo must to setup with connection');
        }
        $db_key_i=$dsn_key."_i";
        if(self::$connPool[$db_key_i]){
            return self::$connPool[$db_key_i];
        }else{
            $c=new dbMysqlI($dsn_value['db_host'],$dsn_value['db_port'],$dsn_value['db_name'],$dsn_value['db_user'],$dsn_value['db_pwd']);
            self::$connPool[$db_key_i]=$c;
            return $c;
        }
    }

}
class ormCollection extends ArrayObject{
    public function __construct(array $array = array()){
        if($array){
            foreach($array as $obj){
                if(is_array($obj)){
                    $obj=new ormDataRow($obj);
                    $this->add($obj);continue;
                }
                if($obj instanceof IormObj){
                    $this->add($obj);
                }
            }
        }
        //parent::__construct($array);
    }
    public function add(IormObj $obj){
        $key=$obj->getName();
        if($key){
            $this[$key]=$obj;
        }else{
            $this[]=$obj;
        }
    }
    public function remove($obj){

    }
    public function indexOf($index){
        if($index>=count($this)) return null;
        return $this[$index];
    }
    public function find(array $arr=array()){
        //好像不能用array_filter
        $c=new self();
        foreach($this as $r){
            $is_find=true;
            foreach($arr as $k=>$v){
                if($r->$k!=$v){
                    $is_find=false;
                    break;
                }
            }
            if($is_find){
                $c->add($r);
            }
        }
        return $c;
    }

    public function __get($index){
        return $this->offsetGet($index);
    }
    public function __set($index, $value){
        $this->offsetSet($index, $value);
    }
    public function __isset($index){
        return $this->offsetExists($index);
    }
    public function __unset($index){
        $this->offsetUnset($index);
    }
    public function toArray(){
        $array = $this->getArrayCopy();
        foreach ($array as $k=>$value)
            ($value instanceof self) && $array[$k] = $value->toArray();

        return $array;
    }
    public function __toString(){
        return var_export($this->toArray(), true);
    }

}
class ormConnection{//这里要去掉对AppormYo的继承
    private $_db_dsn = "";  //just for convenience
    public $last_command_text='';
    public $last_error='';
    private $last_result;
    public $inserted_id=0;
    public $queryID;//原始的数据查询结果指针
    private $_db;
    private $_db_dsn_key="";
    public $db_name="";

    public function __construct($db_dsn_key,$db_dsn,$driver_name) {
        $this->_db_dsn = $db_dsn;
        $this->_db_dsn_key=$db_dsn_key;
        $this->db_name=$db_dsn['db_name'];
        $this->_db=ormDriver::getDriver($db_dsn,$driver_name);
    }

    public function getDsn(){
        return $this->_db_dsn_key;
    }
    function execute($sql){
        $this->last_command_text=$sql;
        $rs=$this->last_result =$this->_db->execute($sql);
        $this->queryID=$rs->queryID;
        $rt= new ormResult();
        $rt->STS=$rs->errorNo?false:true;
        $rt->ERROR_NO=$rs->errorNo;
        $rt->MSG=$this->last_error=$rs->errorMsg;
        $rt->COUNT=$rs->recordCount;
        $rt->FIELDS=$rs->fields;

        if($rt->STS){
            if(!$rs->recordCount){
                $rt->RESULT=array();
            }else{
                $rt->RESULT=$rs->result;
                $rt->FIRST_ROW=current($rs->result);
                $rt->FIRST_VALUE=current($rt->FIRST_ROW);
            }
        }
        $rt->AUTO_ID=$rs->insertedID;
        $rt->SRC_SQL=$rs->sql;
        return $rt;
    }
    public function executePage($sql,$pageSize,$page){
        return $this->_db->executePage($sql,$pageSize,$page);
    }
    public function startTransaction(){
        $this->_db->startTransaction();
    }
    public function submitTransaction(){
        $this->_db->submitTransaction();
    }
    public function rollback(){
        $this->_db->rollback();
    }
}
class ormDataColumn implements IormObj{
    const DATATYPE_VARCHAR='varchar';
    const DATATYPE_INT='int';
    const DATATYPE_DECIMAL='decimal';
    const DATATYPE_TIME='time';
    const DATATYPE_FLOAT='float';
    const DATATYPE_DATE='date';
    const DATATYPE_DATETIME='datetime';
    const DATATYPE_TIMESTAMP='timestamp';
    const DATATYPE_TINYINT='tinyint';
    const DATATYPE_LONGBLOB='longblob';
    const DATATYPE_TEXT='text';
    const DATATYPE_ENUM='enum';

    public $name;
    public $dataType="varchar";
    public $dataLen;
    public $is_prikey=false;
    public $is_notnull=false;
    public $is_autoid=false;
    public $is_readonly=false;
    public $is_hide=false;
    public $caption ='';
    public $index=0;
    public $default;
    public $enum=array();


    function __construct($_name='',$_caption =''){
        $this->name=$_name;
        if(!$_caption){
            $_caption=$this->name;
        }
        $this->caption=$_caption;
    }
    public function getName(){
        return $this->name;
    }
}
class ormDataRow extends ArrayObject implements IormObj{
    const ROWSTATE_DETACHED='detached';//不是任何 DataRowCollection 的一部分。DataRow 在已經建立後、加入至集合前，或如果已經從集合移除後，會立即處在這個狀態中
    const ROWSTATE_UNCHANGED='unchanged';//上次呼叫 AcceptChanges 之後，資料列尚未變更。
    const ROWSTATE_ADDED='added';//資料列已經加入至 DataRowCollection，並且尚未呼叫 AcceptChanges。
    const ROWSTATE_DELETED='deleted';//使用 DataRow 的 Delete 方法來刪除資料列。
    const ROWSTATE_MODIFIED='modified';//已經修改資料列，並且尚未呼叫 AcceptChanges。
    private $_rowstate;
    private $_oldrow;
    private $_is_clone=false;

    public $children; //不预定义，在__get里做特殊处理
    public $table;

    public function __construct(array $array = array()){
        foreach ($array as $k=>$value)
            is_array($value) && $array[$k] = new self($value);
        parent::__construct($array);
        $this->children=array();
        $this->_rowstate=self::ROWSTATE_DETACHED;
    }
    public function __get($index){
        $rt=$this->offsetGet($index);
        /*
        if($index=="children"){
            if(!$rt) $rt=array();
            if($this->table && $this->table->children){
                foreach($this->table->children as $c){
                    if(!is_array($rt[$c->name])){
                        $rt[$c->name]=array();
                    }
                }
            }
        }*/
        return $rt;
    }
    public function __set($index, $value){
        $this->offsetSet($index, $value);
        if($this->_rowstate==self::ROWSTATE_UNCHANGED){
            //$this->_rowstate=self::ROWSTATE_MODIFIED;
            $this->setRowState(self::ROWSTATE_MODIFIED);
        }
    }
    public function __isset($index){
        return $this->offsetExists($index);
    }
    public function __unset($index){
        $this->offsetUnset($index);
        if($this->_rowstate==self::ROWSTATE_UNCHANGED){
            $this->_rowstate=self::ROWSTATE_MODIFIED;
        }
    }
    public function toArray(){
        $array = $this->getArrayCopy();
        foreach ($array as $k=>$value)
            ($value instanceof self && !$vlaue->_is_clone) && $array[$k] = $value->toArray();
        foreach($this->children as $chd){
            $array['children'][]=$chd->toArray();
        }

        return $array;
    }
    public function __toString(){
        return var_export($this->toArray(), true);
    }
    public function delete(){
        if($this->table){
            $this->setRowState(ormDataRow::ROWSTATE_DELETED);
            return $this->table->deleteRow($this);
        }
        return new ormResult(false,ormDataRow::ERROR_INVALID_OBJECT,'NOT FOUND table');
    }
    public function save(){
        if($this->table){
            return $this->table->store($this);
        }
        return new ormResult(false,ormDataRow::ERROR_INVALID_OBJECT,'NOT FOUND table');
    }
    public function insert(){

        if($this->table){
            return $this->table->insertRow($this);
        }
        return new ormResult(false,ormDataRow::ERROR_INVALID_OBJECT,'NOT FOUND table');
    }
    public function update(){
        if($this->table){
            return $this->table->updateRow($this);
        }
        return new ormResult(false,ormDataRow::ERROR_INVALID_OBJECT,'NOT FOUND table');
    }

    public function getName(){
        return NULL;
    }
    public function getRowState(){
        return $this->_rowstate;
    }
    public function setRowState($state){
        $this->_rowstate=$state;
        if($state==self::ROWSTATE_UNCHANGED){
            $this->_oldrow=clone $this;
            $this->_oldrow->_is_clone=true;
        }
        /*
        if($state==self::ROWSTATE_DELETED){
            foreach($this->getChildRows() as $chd_row){
                $chd_row->setRowState(self::ROWSTATE_DELETED);
            }
        }
        */
    }
    public function getOldRow(){
        return $this->_oldrow;
    }
    public function matchData(array $arr){
        if($this->_rowstate==self::ROWSTATE_UNCHANGED) $this->_rowstate=self::ROWSTATE_MODIFIED;
        foreach($arr as $k=>$v){
            if(isset($this->$k)){
                // if($k==ormYo::$IDField) continue;
                $colT=$this->table->columns[$k]->dataType;
                if($v==='' and ($colT!=ormDataColumn::DATATYPE_TEXT || $colT!=ormDataColumn::DATATYPE_VARCHAR)) continue;
                $this->$k=$v;
            }
        }
    }
    public function setTable(ormDataTable $obj){
        $this->table=$obj;
    }
    public function getTable(){return $this->table;}

    //************************************************处理children
    public function getChild($chdName){
        if(!$this->table) return;
        if(!$this->table->children) return;
        foreach($this->table->children as $m=>$c){
            if($c->name==$chdName){
                $chd=$c;
                break;
            }
        }
        return $chd;
    }
    public function getChildTable($chdName){
        $chd=$this->getChild($chdName);
        if($chd) return $chd->table;
    }
    public function loadChildRows(){
        if(!$this->table) return;
        if(!$this->table->children) return;
        foreach($this->table->children as $m=>$c){
            $this->loadChildRowsOf($c->name);
        }
        return $this->children;
    }
    public function loadChildRowsOf($chdName){
        $chd=$this->getChild($chdName);
        if(!$chd) return;
        $chdt=$chd->table;
        $_rows=$chdt->getRows($chd->join_a($this),true);
        $this->children[$chdName]=$_rows;
        return $_rows;
    }
    public function newChildRow($chdName){
        $t=$this->getChildTable($chdName);
        if(!$t) return;
        return $t->newRow();
    }
    public function appendChildRows($rows){
        //$this->children=$rows;
        foreach($rows as $r){
            if(!$r->table) continue;
            $this->children[$r->tbale->getName()]=$r;
        }
        return $rows;
    }
    public function appendChildRow($r){
        if(!$r->table) return;
        $tn=$r->table->getName();
        $this->children[$tn][]=$r;
        return $r;
    }

    public function getChildrenOf($chdName){
        //return $this->_childRows;
        return $this->children[$chdName];
    }
    public function resetChildren($crows){
        if(!$this->table) return new ormResult(false);
        if(!$this->table->children) return new ormResult(false);
        if(is_array($crows)){//如果没有就使用children
            $this->children=array();
        }
        $msg=array();
        foreach($crows as $i=>$r){
            if(!$r->table){
                $msg[]="dataRow must bind table:$i";continue;
            }else{
                $chd=$this->getChild($r->table->getName());
                if(!$chd){
                    $msg[]="invalid table of children:$i";continue;
                }else{
                    $this->appendChildRow($r);
                }
            }
        }
        //
        if(count($msg)){
            $rt=new ormResult();
            $rt->STS=false;
            $rt->MSG=implode(";",$msg);
            return $rt;
        }
        foreach($this->table->children as $chd){
            $rt=$this->resetChildrenOf($chd->name,$this->children[$chd->name]);
        }
        return $rt;
    }
    public function clearChildren(){
        if(!$this->table) return;
        if(!$this->table->children) return;
        foreach($this->table->children as $m=>$c){
            $rt=$this->removeChildrenOf($c->name);
        }
        return $rt;
    }
    public function resetChildrenOf($chdName,$crows){
        if(!$crows){
            return new ormResult(false);
        }
        $chd=$this->getChild($chdName);
        if(!$chd){
            return new ormResult(false);
        }
        $tmp=$this->removeChildrenOf($chdName);
        if(!$tmp->STS) return $tmp;
        $tmp=$this->insertChildRows($chdName,$crows);
        $this->loadChildRowsOf($chdName);
        return $tmp;
    }
    private function insertChildRows($chdName,$crows){
        $chd=$this->getChild($chdName);
        if(!$chd){
            return new ormResult(false);
        }
        foreach($crows as $r){
            if($r->table->getName()!=$chdName){
                return new ormResult(false);
            }
        }
        foreach($crows as $r){
            $r=$chd->formatRow($this,$r);
            $rt=$r->insert();
        }
        return $rt;
    }

    public function removeChildrenOf($chdName){
        $rt=new ormResult();
        $rt->STS=false;
        if($this->_rowstate!==self::ROWSTATE_UNCHANGED){
            $rt->ERROR_NO=ormResult::ERROR_INVALID_ROWSTATE;
            $rt->MSG="DataRow State is Error";return $rt;
        }
        if(!$this->table || !$this->table->children){
            $rt->ERROR_NO=ormResult::ERROR_INVALID_OBJECT;
            $rt->MSG="Not Set The Table To Row";return $rt;
        }
        $chd=$this->getChild($chdName);
        if(!$chd){
            $rt->ERROR_NO=ormResult::ERROR_INVALID_OBJECT;
            $rt->MSG="Error Child Table Object";return $rt;
        }
        $ct=$chd->table;

        $rt=$ct->delete($chd->join_a($this));
        $this->children[$chdName]=array();
        return $rt;
    }

}
class ormChild implements IormObj{
    public $name;//子表名称
    public $pkeys;
    public $ckeys;
    public $table;
    public $parentTable;
    public function __construct($_pcols,$_ccols,$schema,$_name,$dsn){
        if(!$_pcols || !$_ccols || count(explode(",",$_pcols))!=count(explode(",",$_ccols))){
            throw new Exception("Invalid join fields");
        }
        $this->pkeys=$_pcols;
        $this->ckeys=$_ccols;
        if($schema instanceof ormDataTable){
            $this->table=$schema;
            $this->name=$this->table->getName();
        }else{
            $this->name=$_name;
            $this->table=new ormDataTable($this->name,$dsn,$schema);
        }
    }
    public function getName(){
        return $this->name;
    }
    public function join_a($row){
        $_a=array();
        $p_a=explode(",",$this->pkeys);
        $f_a=explode(",",$this->ckeys);
        foreach($p_a as $i=>$v){
            $_a[$f_a[$i]]=$row[$v];
        }
        return $_a;
    }
    public function formatRow($pr,$cr){
        $p_a=explode(",",$this->pkeys);
        $f_a=explode(",",$this->ckeys);
        foreach($p_a as $i=>$v){
            $cr[$f_a[$i]]=$pr[$v];
        }
        return $cr;
    }


}
class ormDataTable implements IormObj{
    use ormTrait;
    public $name;
    public $namespace;
    public $rows;//ormCollection
    public $columns;//ormCollection
    public $primaryKeys;
    public $autoKeys;
    public $reader;
    public $conn;
    public $formulas;//ormCollection
    public $trigger;
    public $totalCount=0;//记录总数
    public $currentPage=0;//当前页
    public $schema;
    public $append_schema;//在原来的结构基础上追加新的结构
    public $ListCols="*";

    public $children;//处理子表
    public function __construct($_tbname=null,$_dsn=null,$_schema=null){
        $this->name=$_tbname;
        //if($_namespace) $_namespace=$_tbname;
        //$this->namespace=$_namespace;
        $this->rows=new ormCollection();
        $this->columns=new ormCollection();
        $this->primaryKeys=new ormCollection();
        $this->autoKeys=new ormCollection();
        if(is_array($_dsn)){
            $this->conn=ormYo::Conn($_dsn);
        }else{
            if($_dsn instanceof ormConnection){
                $this->conn=$_dsn;
            }else{
                throw new Exception("Connection is not Defined");
            }
        }
        $this->reader=new ormReader($this->conn);
        if($_schema){
            $this->schema=$_schema;
        }
        $this->initSchema();
    }
    public function addChild(ormChild $chd){
        if(!$this->children) $this->children=array();
        $this->children[$chd->name]=$chd;
        return $chd;
    }
    private function inspectFile(){
        $fn=ormYo::$schema_path."schema_".$this->name.".php";
        $_tmp=$this->inspect();
        $rt=array();
        foreach($_tmp as $r){
            $rt[]=$r->toArray();
        }
        //存成文件
        $file_s=var_export($rt,true);
        $file_s="\$static_schema=$file_s;";
        file_put_contents($fn,"<"."?php\n$file_s");

        //同时编译语言包
        $fn_lang=ormYo::$schema_path."lang_".$this->name.".php";
        if(file_exists($fn_lang)){
            include($fn_lang);
        }
        if(!is_array($static_lang)) $static_lang=array();
        foreach(ormYo::$lang_a as $l){
            if(!$static_lang[$l]) $static_lang[$l]=array();
            foreach($rt as $r){
                if(!$static_lang[$l][$r['Field']]){
                    $static_lang[$l][$r['Field']]=ucwords(strtolower(str_replace("_"," ",$r['Field'])));
                }
            }
            foreach($static_lang[$l] as $k=>$v){
                $has=false;
                foreach($rt as $r){
                    if($r['Field']==$k){
                        $has=true;
                        break;
                    }
                }
                if(!$has){
                    unset($static_lang[$l][$k]);
                }
            }
        }
        $file_s=var_export($static_lang,true);
        $file_s="\$static_lang=$file_s;";
        file_put_contents($fn_lang,"<"."?php\n$file_s");

        return $rt;
    }
    public function getLangAll(){
        if(ormYo::$table_lang_pool[$this->name]) return ormYo::$table_lang_pool[$this->name];
        $fn_lang=ormYo::$schema_path."lang_".$this->name.".php";
        if(file_exists($fn_lang)){
            ormYo::$table_lang_pool[$this->name]=$fn_lang;
            include($fn_lang);
            return $static_lang?:array();
        }else{
            return array();
        }
    }
    public function getLang($lang){
        $a=$this->getLangAll();
        return $a[$lang]?:array();
    }
    public function initSchema(){
        $fn=ormYo::$schema_path."schema_".$this->name.".php";
        if(ormYo::$freez || !$this->schema){//已经冻结，认为需要抛弃传入的schema
            if(ormYo::$table_schema_pool[$this->name]){
                $this->schema=ormYo::$table_schema_pool[$this->name];
            }else{
                if(!ormYo::$freez){
                    $this->schema=$this->inspectFile();//重新生成
                }else{
                    if(file_exists($fn)){
                        include_once($fn);
                        $this->schema=$static_schema;
                        ormYo::$table_schema_pool[$this->name]=$this->schema;
                    }else{
                        if(!self::hasTable($this->name,$this->conn->getDsn())){
                            throw new Exception("Table ".$this->name." not Exists");
                        }
                        $this->schema=$this->inspectFile();//重新生成
                    }
                }
            }
        }else{

            //format-schema
            if(is_string($this->schema) && strlen(trim($this->schema))){
                $this->schema=explode(",",$this->schema);	//todo:处理更复杂的字符串格式
            }
            if(is_array($this->schema)){//满足最简单的定义方式
                $tmp=array();
                foreach($this->schema as $k=>$r){
                    if(is_string($r)){
                        $tmp[]=array('Field'=>$r);
                    }else{
                        $tmp[]=$r;
                    }
                }
                $this->schema=$tmp;
            }

            if(is_array($this->schema)){
                $_rt=self::mapTable($this->name,$this->schema,$this->conn->getDsn());
                if(!$_rt->STS){
                    ormYo::wlog("ORM-MAPPING-TABLE",$_rt);
                    throw new Exception("Incorrect Table-schema!");
                }else{
                    unset(ormYo::$table_source_pool[$this->name]);
                    $this->schema=$this->inspectFile();
                }

            }else{
                throw new Exception("Incorrect Table-schema!");
            }
        }

        //处理追加的结构
        if(!ormYo::$freez && $this->append_schema){
            $ext_cnt=0;
            if(is_array($this->append_schema)){
                foreach($this->append_schema as $ext_fld){
                    $is_find_ext=false;
                    foreach($this->schema as $fld){
                        if($fld['Field']==$ext_fld["Field"]){
                            break;
                        }
                    }
                    if(!$is_find_ext){
                        $ext_cnt+=1;
                        $this->schema[]=$ext_fld;
                    }
                }
            }
            if($ext_cnt){
                //说明需要重新map
                $_rt=self::mapTable($this->name,$this->schema,$this->conn->getDsn());
                if(!$_rt->STS){
                    ormYo::wlog("ORM-MAPPING-TABLE",$_rt);
                    throw new Exception("Incorrect Table-schema!");
                }else{
                    $this->schema=$this->inspectFile();
                }
            }

        }

        if(is_array($this->schema)){
            $this->schema=new ormCollection($this->schema);//转化成对象
        }
        $lang_arr=$this->getLang(ormYo::$lang_current);

        foreach($this->schema as $r){
            $col=new ormDataColumn($r->Field);
            $col->dataType=strtolower(preg_replace('/\(.*/', '', $r->Type));
            if($col->dataType==ormDataColumn::DATATYPE_ENUM){
                $str_enum=preg_replace(array('/.*\(+/i','/\)*/i'),array("",""),$r->Type);//去掉括号
                $str_enum=substr($str_enum,1,strlen($str_enum)-2);//去掉两边的单引号
                $col->enum=explode("','",$str_enum);
            }else{
                $tmp_len=preg_replace(array('/.*\(+/i','/\)*/i'),array("",""),$r->Type);
                $col->dataLen=intval($tmp_len);
            }
            $col->caption=($r->Caption?:$lang_arr[$r->Field])?:$col->name;
            $col->default=$r->Default;
            if($r->Key=='PRI') $col->is_prikey=true;
            if($r->Extra=='auto_increment') $col->is_autoid=true;
            if($r->Null=='NO') $col->is_notnull=true;
            $this->columns->add($col);
            if($col->is_prikey) $this->primaryKeys->add($col);
            if($col->is_autoid) $this->autoKeys->add($col);
        }

    }
    public function inspect(){
        $tmp=new ormTableSchema($this->name,$this->conn);//从数据库获取
        return $tmp->schema;
    }
    public function newRow(array $arr=array()){
        $row=new ormDataRow();

        foreach($this->columns as $col){
            if(preg_match("/DATE|DATETIME|TIMESTAMP/i",$col->dataType)){//日期字段
                $k=$col->name;
                $row->$k=null;
            }else{
                if(preg_match("/VARCHAR|CHAR|TEXT/i",$col->dataType)) {//字符串字段
                    if(!$col->default){
                        $col->default="";//非空
                    }
                }
                $k=$col->name;
                $row->$k=$col->default;
            }
        }
        if($arr) $row->matchData($arr);
        $row->setTable($this);
        $row->setRowState(ormDataRow::ROWSTATE_ADDED);
        return $row;
    }
    private function getRowByValue($value){
        if(count($this->primaryKeys)>1){
            $p=new ormParameter($this->name,current($this->autoKeys)->name,$value);
        }else{
            $k_c=current($this->primaryKeys);
            if(is_numeric($value)){
                if($k_c->dataType==ormDataColumn::DATATYPE_INT){
                    $p=new ormParameter($this->name,current($this->primaryKeys)->name,$value);
                }else{
                    $p=new ormParameter($this->name,current($this->autoKeys)->name,$value);
                }
            }else{
                $p=new ormParameter($this->name,current($this->primaryKeys)->name,$value);
            }

        }
        $plist=new ormCollection(array($p));
        $rows=$this->fill($plist);
        return current($rows);
    }
    private function getRowByUID($value){

        $p=new ormParameter($this->name,current($this->autoKeys)->name,$value);
        $plist=new ormCollection(array($p));
        $rows=$this->fill($plist);
        return current($rows);
    }
    private function getRowByArr(array $arr=array()){//多主健模式
        $plist=new ormCollection();
        foreach($this->primaryKeys as $col){
            if(isset($arr[$col->name])){
                $p=new ormParameter($this->name,$col->name,$arr[$col->name]);
                $plist->add($p);
            }else{
                return;//说明必须设置
            }
        }
        if(count($plist)==0){
            return;
        }
        $rows=$this->fill($plist);
        return current($rows);
    }
    public function select($item,$sql=null){
        $ret=$this->getRows($item,$sql);
        $new_ret=array();
        //自动转成数组
        if(count($ret)){
            foreach($ret as $k=>$v){
                if($v[ormYo::$IDField]){
                    $new_ret[$v[ormYo::$IDField]]=$v->toArray();
                }else{
                    $new_ret[]=$v->toArray();
                }
            }
        }
        return $new_ret;

    }
    public function find($item){
        $ret= $this->getRow($item);
        if(count($ret)){
            if(count($ret)==1){
                return current($ret);
            }else{
                return $ret->toArray();
            }
        }else{
            return false;
        }
    }

    public function getRow($item){//不考虑where条件搜索,这是只以主健搜索的模式
        /*
        if(is_numeric($item)){
            return $this->getRowByUID($item);
        }*/
        if(!$item && $this->_call_where){
            $rows=$this->getRows(array());
            return current($rows);
        }

        if(is_string($item) || is_numeric($item)){
            return $this->getRowByValue($item);
        }
        if(is_array($item)){
            $rows=$this->getRows($item);
            return current($rows);
            // return $this->getRowByArr($item);
        }
        throw new Exception("getRow:Invalid arguments!");
    }
    public function getRows($item,$sql=null){//setRows=false就不加载到table，save的时候会忽略,且不考虑分页
        if(!$item){
            if($this->_call_where){
                $item=array();
            }else{
                return array();
            }
        }
        $rt=array();
        if(is_string($item)){
            $rt=$this->fill($item,0,0,$sql);
        }else{
            $rt=$this->load($item,0,0,$sql);
        }
        return $rt;
    }
    public function getAll(){
        return $this->fill();
    }
    public function load(array $arr=array(),$page,$pageSize,$sql){
        //$plist=new ormCollection();
        /*
        foreach($arr as $k=>$v){
            if($v instanceof ormParameter){
                $plist[]=$v;
                continue;
            }
            if(isset($this->columns[$k])){
                $p=new ormParameter($this->name,$k,$v);
                $plist->add($p);
            }else{
                //直接返回空
                return new ormCollection();
            }
        }*/
        return $this->fill($arr,$page,$pageSize,$sql);
    }
    private $_call_group_by="";
    private $_call_having="";
    private $_call_order_by="";
    private $_call_where="";
    private $_call_limit_start=0;
    private $_call_limit_size=0;
    private $_call_field="";
    function __call($name,$args){
        if(method_exists($this,$name) && is_callable($name)){
            return $this->$name($args);
        }
        switch($name){
            case "groupBy":
                if(is_array($args) && count($args)){
                    $this->_call_group_by=$args[0];
                }
                return $this;
            case "having":
                if(is_array($args)  && count($args)){
                    $this->_call_having=$args[0];
                }
                return $this;
            case "orderBy":
                if(is_array($args)  && count($args)){
                    $this->_call_order_by=$args[0];
                }
                return $this;
            case "limit":
                if(is_array($args)  && count($args)){
                    $this->_call_limit_start=intval($args[0])?:1;
                    $this->_call_limit_size=intval($args[1])?:20;
                }
                return $this;
            case "where":
                if(is_array($args)  && count($args)){
                    $this->_call_where=$args[0];
                }
                return $this;
            case "field":
                if(is_array($args) && count($args)){
                    $this->_call_field=$args[0];
                }
                return $this;
            default:
                break;
        }
        throw new Exception("Invalid function!");

    }
    public function fill($condition='',$page=null,$pageSize=null,$sql_spec=null,$group_by='',$having='',$order_by=''){
        if(!$this->conn) throw new Exception('No Data Connection！');
        if(!$this->reader) $this->reader=new ormReader($this->conn);
        $rt=new ormCollection();
        if(!$condition){
            $str_where="";
        }else{
            if(is_string($condition) && $condition){
                /*if(!preg_match("@^where.*@i",$condition)){
                    $str_where=" where ".$condition;
                }else{
                    $str_where=$condition;
                }*/
                $str_where=$condition;
            }else{
                $str_where=$this->_formatCondition($condition);
            }
        }

        if(!$sql_spec){
            if($this->_call_field){
                $sql_spec="select ".$this->_call_field ." from ".$this->name;
            }else{
                $sql_spec="select ".($this->ListCols?:"*")." from ".$this->name;
            }
        }
        if($str_where && !preg_match("@^where.*@i",$str_where)){
            $str_where=" where ".$str_where;
        }else{
            if(!$str_where){
                if($this->_call_where){
                    if(is_array($this->_call_where)){
                        $str_where=" where ".$this->_formatCondition($this->_call_where);
                    }else{
                        $str_where=" where ".$this->_call_where;
                    }
                }
            }
        }

        $sql=$sql_spec." ".$str_where;
        $by_group=$group_by?:$this->_call_group_by;
        $by_order=$order_by?:$this->_call_order_by;
        $by_having=$having?:$this->_call_having;




        if($by_group) $sql=$sql." group by ".$by_group;
        if($by_having) $sql=$sql. " having ".$by_having;
        if($by_order) $sql=$sql." order by ".$by_order;


        if($pageSize>0){
            $rd=$this->reader->getPage($sql,$page,$pageSize);
            $rows=$rd->rows;
            $this->totalCount=$rd->count;
            $this->currentPage=$page;
        }else{
            if($this->_call_limit_start>0){
                $rd=$this->reader->getPage($sql,$this->_call_limit_start,$this->_call_limit_size);
                $rows=$rd->rows;
                $this->totalCount=$rd->count;
                $this->currentPage=$this->_call_limit_start;
            }else{
                $rows=$this->reader->getRecord($sql);
                $this->totalCount=count($rows);
            }
        }

        foreach($rows as $row){
            $datarow=new ormDataRow($row);
            $datarow->setRowState(ormDataRow::ROWSTATE_UNCHANGED);
            $datarow->setTable($this);
            $rt->add($datarow);
        }
        //不主动加载从表

        //if($setRows) $this->rows=$rt; //暂时关闭这种做法
        $this->rows=$rows;
        $this->_call_where="";
        $this->_call_field="";
        $this->_call_group_by="";
        $this->_call_having="";
        $this->_call_limit_start="";
        $this->_call_limit_size="";
        $this->_call_order_by="";
        return $this->formatRows($rt);
    }
    protected function formatRows($items){//override by sub class
        return $items;
    }

    private function _isDate($d){//这个很不严谨，如果是now这样的字符串也行
        if(!$d && $d!='0') return true;
        if($d=='0000-00-00 00:00:00') return true;
        if($d=='') return true;
        if(is_numeric($d)) return false;
        if(!is_numeric(strtotime($d))) return false;
        return true;
    }
    public function store($arr){
        //找row，如果存在就update,否则insert
        if($arr instanceof ormDataRow){
            if($arr->getRowState()==ormDataRow::ROWSTATE_ADDED){
                return $this->insertRow($arr);
            }
            if($arr->getRowState()==ormDataRow::ROWSTATE_MODIFIED){
                return $this->updateRow($arr);
            }
            $arr=$arr->toArray();
        }
        if(is_array($arr)){
            $row=$this->getRowByArr($arr);
            if(!$row){
                //insert
                $row=$this->newRow($arr);
                return $this->insertRow($row);
            }else{
                $row->matchData($arr);
                return $this->updateRow($row);
            }
        }
        return new ormResult(false,ormResult::ERROR_EMPTY,"Data row object is empty");
    }
    public function insert($item){
        if($item instanceof ormDataRow){
            return $this->insertRow($item);
        }else{
            if(is_array($item)){
                return $this->insertArr($item);
            }else{
                return new ormResult(false,ormResult::ERROR_INVALID_OBJECT,"Invalid Data");
            }
        }
    }
    public function insertMass($arr){
        $rt=new ormResult(false,ormResult::ERROR_INVALID_OBJECT,"Invalid Data");
        if(!is_array($arr)) return $rt;
        if(!count($arr)) return $rt;
        foreach($arr as $r){
            if(!is_array($r)){
                return $rt;
            }
        }
        foreach($arr as $r){
            $rt=$this->insert($r);
        }
        return $rt;
    }
    public function insertArr($arr){

        $row=$this->newRow($arr);
        return $this->insertRow($row);
    }
    public function insertRow(ormDataRow $row){

        if(!$row) return new ormResult(false,ormResult::ERROR_EMPTY,"Data row object is empty");
        //$row->insert_time=date('Y-m-d H:i:s');
        if($row->getRowState()!=ormDataRow::ROWSTATE_ADDED)
            return new ormResult(false,ormResult::ERROR_INVALID_ROWSTATE,"Row State is Error");

        $str_key='';
        $str_value='';
        $key_a=array();
        $value_a=array();
        $_chk=$this->_checkRow($row);
        if(!$_chk->STS) return $_chk;

        $clone_row=clone $row;
        $this->callTrigger(IormTrigger::BEFORE_INSERT,$clone_row,$row);

        $extend_rt=$this->before_insert($clone_row,$row);
        if(!$extend_rt) return  new ormResult(false,ormResult::ERROR_INVALID_ROWSTATE,"No Response from Befor Insert");

        if(!$extend_rt->STS) return $extend_rt;

        foreach($this->columns as $col){
            $curV=$row[$col->name];
            if($col->is_autoid) continue;
            if($curV===NULL) continue;
            $curV=$this->_formatStr($curV);
            $key_a[]="`".$col->name."`";
            $value_a[]=$curV;
        }
        $str_key=implode(",",$key_a);
        $str_value=implode(",",$value_a);
        $sql="insert ".$this->name."(".$str_key.") values(".$str_value.")";
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        if($rt->STS){
            if(count($this->autoKeys)){
                $row[current($this->autoKeys)->name]=$rt->AUTO_ID;
            }
            //$row->insertChildRows();
            $row->resetChildren();
        }
        if($rt->STS) $row->setRowState(ormDataRow::ROWSTATE_UNCHANGED);
        $this->callTrigger(IormTrigger::AFTER_INSERT,$clone_row,$row);
        $extend_rt=$this->after_insert($clone_row,$row);
        if(!$extend_rt->STS) return $extend_rt;

        if($rt->ERROR_NO==1062){
            $rt->ERROR_NO=ormResult::ERROR_EXIST;
        }elseif($rt->ERROR_NO>0){
            $rt->ERROR_NO=ormResult::ERROR_NOTCATCH;
        }

        $rt->SOURCE_ROW=$row->toArray();
        return $rt;

    }
    private function _checkRow(ormDataRow $row){

        if($row->getRowState()==ormDataRow::ROWSTATE_ADDED){
            $isAdd=true;
        }


        foreach($this->columns as $col){

            if($col->is_autoid) continue;
            /*if(!isset($row[$col->name])){
                return new ormResult(false,ormResult::ERROR_INVALID_OBJECT,"错误的数据对象:没设置列",$col->name);
            }*/
            //检查空值(包含了主健)
            //自动时间戳,也许应该用数据库时间
            if($isAdd){
                if(preg_match("/^(TIMESTAMP)$/i",$col->dataType)){
                    $row[$col->name]=date('Y-m-d H:i:s');
                }
            }

            if($col->is_notnull && $row[$col->name]===NULL){
                return new ormResult(false,ormResult::ERROR_NOTNULL,"Empty values are not allowed",$col->name);
            }
            /* 这里可能有些人对空的理解不一样。
            //字符串认为0长度的字符串也是空
            if(preg_match('@^(VARCHAR|CHAR|TEXT)$@i',$col->dataType)){
                if($col->is_notnull && !trim($row[$col->name])){
                    return new ormResult(false,ormResult::ERROR_NOTNULL,"Empty values are not allowed",$col->name);
                }
            }*/
            //检查数字类型
            if(preg_match("/^INT|FLOAT|DOUBLE|DECIMAL|BIGINT$/i",$col->dataType)){

                if(!$row[$col->name] && $col->default===NULL && $isAdd){
                    $row[$col->name]=0;
                }else{
                    if($row[$col->name] && !is_numeric($row[$col->name])){
                        return new ormResult(false,ormResult::ERROR_INVALID_TYPE,"Data type does not match:".$col->dataType.",value:".$row[$col->name],$col->name);
                    }
                }
            }

            //检查长度
            if(preg_match('@^(VARCHAR|CHAR)$@i',$col->dataType) && is_numeric($col->dataLen) && mb_strlen($row[$col->name],'utf8')>$col->dataLen){
                return new ormResult(false,ormResult::ERROR_MAXLEN,"Out of length:".$col->dataLen." of ".$col->name,$col->name);
            }
            //检查日期字段
            if(preg_match("/^(DATE|DATETIME|TIMESTAMP)$/i",$col->dataType)){//日期字段
                if($row[$col->name]){
                    if(!$this->_isDate($row[$col->name])){
                        return new ormResult(false,ormResult::ERROR_INVALID_TYPE,"Data type does not match:".$col->dataType.",value:".$row[$col->name],$col->name);
                    }
                }
            }

            //检查enum
            if($col->dataType==ormDataColumn::DATATYPE_ENUM && $row[$col->name]){
                if(is_numeric($row[$col->name])){
                    if(!in_array($row[$col->name],$col->enum)){
                        return new ormResult(false,ormResult::ERROR_ENUM_LIMIT,"Values are not within the enumeration range",$col->name);
                    }
                }else{
                    if(!in_array($this->_formatStr($row[$col->name]),$col->enum)){
                        return new ormResult(false,ormResult::ERROR_ENUM_LIMIT,"Values are not within the enumeration range",$col->name);
                    }
                }
            }

            $curV=$row[$col->name];
            if($curV===NULL) continue;
            //处理bool型,其实不一定是bool型
            if(preg_match("/TINYINT/i",$col->dataType)){
                if($curV==='0' || $curV===0 || $curV===NULL || $curV==false || strtolower($curV)=="false"){
                    $row[$col->name]='0';
                }else{
                    if(!is_numeric($curV)){
                        $row[$col->name]='1';
                    }
                }
            }

        }
        return new ormResult(true);
    }
    public function delete($item){
        if(!$item)
            return new ormResult(false,NULL,"No Delete Object",NULL,NULL);
        if($item instanceof ormDataRow){
            return $this->deleteRow($item);
        }
        if(is_numeric($item) || is_string($item)){
            return $this->deleteKey($item);
        }
        if(is_array($item)){
            $_where=$this->_formatCondition($item);
            return $this->deleteWhere($_where);
        }

    }
    public function deleteKey($key){
        if(is_numeric($key)){
            $row=$this->getRowByUID($key);
        }elseif(is_string($key)){
            $row=$this->getRowByValue($key);
        }
        if(!$row) return new ormResult(false,ormResult::ERROR_EMPTY,"DataRow is Empty");
        $row->setRowState(ormDataRow::ROWSTATE_DELETED);
        return $this->deleteRow($row);
    }
    public function clear(){
        return $this->deleteWhere();
    }
    public function deleteWhere($w){
        if($w){
            if(!is_numeric(stripos($w,"where"))){
                $sql="delete from ".$this->name." where ".$w;
            }else{
                $sql="delete from ".$this->name." ".$w;
            }
        }else{
            $sql="delete from ".$this->name;
        }
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        return $rt;
    }
    public function deleteRow(ormDataRow $row){
        if(!$row) return new ormResult(false,ormResult::ERROR_EMPTY,"DataRow is Empty");
        if($row->getRowState()!=ormDataRow::ROWSTATE_DELETED)
            return new ormResult(false,ormResult::ERROR_INVALID_ROWSTATE,"Error RowState:Can't Find Update Object");
        $clone_row=clone $row;
        $this->callTrigger(IormTrigger::BEFORE_DELETE,$clone_row,$row);
        $extend_rt=$this->before_delete($clone_row,$row);
        if(!$extend_rt->STS) return $extend_rt;

        $str_where;
        foreach($this->primaryKeys as $col){
            if(!isset($row[$col->name])){
                return new ormResult(false,ormResult::ERROR_INVALID_PRIMARYKEYS,"No PirmaryKey，Can't Delete");
            }
            if(!$str_where){
                $str_where="`".$col->name."`=".$this->_formatStr($row[$col->name]);
            }else{
                $str_where.=" and `".$col->name."`=".$this->_formatStr($row[$col->name]);
            }
        }
        $sql="delete from ".$this->name." where ".$str_where;
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        if($rt->STS){
            //级联删除子表,逻辑上应该先删除子表,移动到row了
            $row->clearChildren();
            $row->setRowState(ormDataRow::ROWSTATE_DETACHED);
        }
        $this->callTrigger(IormTrigger::AFTER_DELETE,$clone_row,$row);
        $rt->SOURCE_ROW=$row->toArray();
        $extend_rt=$this->after_delete($clone_row,$row);
        if(!$extend_rt->STS) return $extend_rt;

        return $rt;
    }
    public function updateArr(array $arr=array()){//其实这个模式应该抛弃的，不科学
        $pkarr=array();
        foreach($this->primaryKeys as $col){
            if(!isset($arr[$col->name])) return new ormResult(false,ormResult::ERROR_INVALID_PRIMARYKEYS,"No PrimaryKey，Can't Update");
            $pkarr[$col->name]=$arr[$col->name];
        }

        $row=$this->getRowByArr($pkarr);
        if(!$row) return new ormResult(false,ormResult::ERROR_EMPTY,"Empty DataRow");
        $row->setRowState(ormDataRow::ROWSTATE_MODIFIED);
        $row->matchData($arr);
        return $this->updateRow($row);
    }
    public function updateRow(ormDataRow $row){
        if(!$row) return new ormResult(false,ormResult::ERROR_EMPTY,"Empty DataRow");
        if($row->getRowState()!=ormDataRow::ROWSTATE_MODIFIED)
            return new ormResult(false,ormResult::ERROR_INVALID_ROWSTATE,"Error RowState");
        if(!count($this->primaryKeys)){
            return new ormResult(false,ormResult::ERROR_INVALID_PRIMARYKEYS,"No PrimaryKey，Can't Update");
        }
        //获取变化列表
        $oldrow=$row->getOldRow();
        $_chk=$this->_checkRow($row);

        if(!$_chk->STS) return $_chk;


        $this->callTrigger(IormTrigger::BEFORE_UPDATE,$oldrow,$row);
        $extend_rt=$this->before_update($oldrow,$row);
        if(!$extend_rt->STS) return $extend_rt;

        $_changes=array();
        $aaa = '';
        foreach($this->columns as $col){
            /*if(!isset($row[$col->name])){
                return new ormResult(false,ormResult::ERROR_INVALID_OBJECT,"错误的数据对象:没设置列",$col->name);
            }*/

            if($oldrow[$col->name]!==$row[$col->name]){
                $_changes[$col->name]=$row[$col->name];
            }
        }

        if(!count($_changes)){
            return new ormResult(false,ormResult::ERROR_INVALID_ROWSTATE,"Error RowState:");
        }
        $str_set_a=array();
        foreach($_changes as $k=>$v){
            if($v===NULL){
                $str_set_a[]="`".$k."`=NULL";
            }else{
                $str_set_a[]="`".$k."`=".$this->_formatStr($v);
            }
            $str_set=implode(",",$str_set_a);
            if($str_set){
                $str_set=" SET ".$str_set;
            }
        }

        foreach($this->primaryKeys as $pk){
            if(!$row[$pk->name]){
                return new ormResult(false,ormResult::ERROR_INVALID_PRIMARYKEYS,"No PrimaryKey");
            }
            if(!$str_where){
                $str_where=" `".$pk->name."`=".$this->_formatStr($row[$pk->name]);
            }else{
                $str_where.=" and `".$pk->name."`=".$this->_formatStr($row[$pk->name]);
            }
        }
        $sql="update ".$this->name." ".$str_set." where ".$str_where;
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        if($rt->STS) $row->setRowState(ormDataRow::ROWSTATE_UNCHANGED);
        $this->callTrigger(IormTrigger::AFTER_UPDATE,$oldrow,$row);
        $extend_rt=$this->after_update($oldrow,$row);
        if(!$extend_rt->STS) return $extend_rt;
        if($rt->ERROR_NO==1062){
            $rt->ERROR_NO=ormResult::ERROR_EXIST;
        }elseif($rt->ERROR_NO>0){
            $rt->ERROR_NO=ormResult::ERROR_NOTCATCH;
        }
        $rt->SOURCE_ROW=$row->toArray();

        return $rt;
    }
    public function update($item){
        if($item instanceof ormDataRow){
            return $this->updateRow($item);
        }else{
            if(is_array($item)){
                return $this->updateArr($item);
            }else{
                return new ormResult(false,ormResult::ERROR_INVALID_OBJECT,"Invalid Upadate Object");
            }
        }
    }

    public function save(){//很巧妙地实现事务,或许应该摈弃
        if(count($this->rows)==0){
            return new ormResult(false,ormResult::ERROR_EMPTY,"Empty Object");
        }
        $rt=new ormCollection();
        $rt_rollback=new ormCollection();
        $rollback=new ormCollection();
        foreach($this->rows as $row){
            if($row->getRowState()==ormDataRow::ROWSTATE_MODIFIED){
                $old_row=clone $row;
                $old_row->matchData($row->getOldRow()->toArray());
                $rt_tmp=$this->updateRow($row);
                $rt->add($rt_tmp);
                if($rt_tmp->STS){
                    $rollback->add($old_row);
                }else{
                    break;
                }
            }
            if($row->getRowState()==ormDataRow::ROWSTATE_DELETED){
                $old_row=clone $row;
                $old_row->setRowState(ormDataRow::ROWSTATE_ADDED);
                $rt_tmp=$this->deleteRow($row);
                $rt->add($rt_tmp);
                if($rt_tmp->STS){
                    $rollback->add($old_row);
                }else{
                    break;
                }
            }
            if($row->getRowState()==ormDataRow::ROWSTATE_ADDED){
                $rt_tmp=$this->insertRow($row);
                $rt->add($rt_tmp);
                if($rt_tmp->STS){
                    $old_row=clone $row;
                    $old_row->setRowState(ormDataRow::ROWSTATE_DELETED);
                    $rollback->add($old_row);
                }else{
                    break;
                }
            }
        }
        $rs=new ormResult();
        $rs->STS=true;
        foreach($rt as $r){
            if(!$r->STS) {
                $rs->STS=false;
                $rs->MSG=$r->MSG;
                break;
            }
        }
        if(!$rs->STS){
            if(count($rollback)){
                foreach($rollback as $row){
                    if($row->getRowState()==ormDataRow::ROWSTATE_ADDED) $rt_rollback->add($this->insertRow($row));
                    if($row->getRowState()==ormDataRow::ROWSTATE_DELETED) $rt_rollback->add($this->deleteRow($row));
                    if($row->getRowState()==ormDataRow::ROWSTATE_MODIFIED) $rt_rollback->add($this->updateRow($row));
                }
            }
        }
        $rs->RESULT=array('result'=>$rt,'result_rollback'=>$rt_rollback);
        return $rs;
    }
    public function getName(){
        return $this->name;
    }
    public function setTrigger($obj){
        if(!is_object($obj)){
            throw new Exception('Add Failed: the Trigger is not a object!');
        }
        $rc=new ReflectionClass($obj);
        if(!$rc->implementsInterface("IormTrigger")){
            throw new Exception('Must Implements IormTrigger!');
        }
        $this->trigger=$obj;
    }
    //override by sub-class
    public function before_insert(ormDataRow $clone,ormDataRow $curent){

        return new ormResult(true);
    }
    public function after_insert(ormDataRow $clone,ormDataRow $curent){
        return new ormResult(true);
    }
    public function before_update(ormDataRow $clone,ormDataRow $curent){
        return new ormResult(true);
    }
    public function after_update(ormDataRow $clone,ormDataRow $curent){
        return new ormResult(true);
    }
    public function before_delete(ormDataRow $clone,ormDataRow $curent){
        return new ormResult(true);
    }
    public function after_delete(ormDataRow $clone,ormDataRow $curent){
        return new ormResult(true);
    }
    //触发器不考虑返回
    private function callTrigger($sts,ormDataRow $clone,ormDataRow $current){
        $tg=$this->trigger;
        if(!$tg) return;
        switch($sts){
            case Itrigger::BEFORE_INSERT:
                $tg->before_insert($clone,$current);
                break;
            case Itrigger::BEFORE_UPDATE:
                $tg->before_update($clone,$current);
                break;
            case Itrigger::BEFORE_DELETE:
                $tg->before_delete($clone,$current);
                break;
            case Itrigger::AFTER_INSERT:
                $tg->after_insert($clone,$current);
                break;
            case Itrigger::AFTER_UPDATE:
                $tg->after_update($clone,$current);
                break;
            case Itrigger::AFTER_DELETE:
                $tg->after_delete($clone,$current);
                break;
            default:
                break;
        }
    }
    public function defaultView(){
        $view=new ormDataView($this->conn);
        $view->matchTable($this);
        return $view;
    }
    public function getPage($page,$pageSize,$arr,$sql){
        if(is_array($arr)){
            return $this->load($arr,$page,$pageSize,$sql);
        }
        return $this->fill($arr,$page,$pageSize,$sql);
    }

    /********************操作数据库结构****************************/
    static function hasTable($tbName,$dsn){
        $sql="SHOW TABLES LIKE '".$tbName."'";
        $c=ormYo::Conn($dsn);
        $rt=$c->execute($sql);
        if($rt->FIRST_VALUE){
            return true;
        }else{
            return false;
        }
    }
    static function mapTable($tbName,$sa,$dsn){
        if(ormYo::$freez){

        }
        if(!preg_match("@^[a-zA-Z][a-zA-Z0-9_]*$@i",$tbName)){//必须符合字段名命名规
            throw new Exception("Incorrect Name of Table:".$tbName);
        }
        if(self::hasTable($tbName,$dsn)){
            return self::alterTable($tbName,$sa,$dsn);
        }else{
            return self::generateTable($tbName,$sa,$dsn);
        }
    }
    static function alterTable($tbName,$sa,$dsn){
        list($sa,$pris,$idFld)=self::checksa($tbName,$sa,true);
        //获取原始表结构
        $c=ormYo::Conn($dsn);
        $tmp=new ormTableSchema($tbName,$c);//从数据库获取
        $old=array();
        foreach($tmp->schema as $old_r){
            $old[]=$old_r->toArray();
        }
        //比较新旧结构
        $add=array();
        $change=array();
        $drop=array();
        foreach($sa as $i=>$r){
            if($r['Field']==ormYo::$IDField) continue;
            $isFind=false;
            foreach($old as $j=>$v){
                if($r['Field']==$v['Field']){
                    list($new_r,$new_col)=self::generateColumn($r);
                    list($old_r,$old_col)=self::generateColumn($v);
                    if($new_col!==$old_col){
                        $change[]=$r;
                    }
                    $sa[$i]=$new_r;
                    $isFind=true;
                    break;
                }
            }
            if(!$isFind){
                if($i>0){
                    $r['After']=$sa[$i-1]['Field'];
                }
                list($tmp_r,$tmp_col)=self::generateColumn($r);
                $add[]=$r;
                $sa[$i]=$tmp_r;
            }
        }
        $old_pris=array();
        foreach($old as $j=>$v){
            if($v['Key']=="PRI"){
                $old_pris[]=$v;
            }
            if($v['Field']==ormYo::$IDField) continue;
            $isFind=false;
            foreach($sa as $i=>$r){
                if($v['Field']==$r['Field']){
                    $isFind=true;
                    break;
                }
            }
            if(!$isFind){
                $drop[]=$v;
            }
        }
        $sql="ALTER TABLE `".$tbName."` ";
        $cols=array();
        foreach($drop as $r){
            $cols[]=" DROP COLUMN ".self::backquote($r['Field']);
        }
        foreach($add as $r){
            list($tmp,$col)=self::generateColumn($r);
            if($col){
                if($r['After']){
                    $col.=" AFTER ".self::backquote($r['After']);//这里有个问题就是前面的字段没有创建成功会爆错
                }
                $cols[]=" ADD COLUMN ".$col;
            }
        }
        foreach($change as $r){
            list($tmp,$col)=self::generateColumn($r);
            if($col){
                $cols[]=" CHANGE ".self::backquote($r['Field'])." ".$col;
            }
        }
        if($pris && $old_pris){
            $str_pri_a=array();
            foreach($pris as $p){
                $str_pri_a[]=self::backquote($p['Field']);
            }
            $str_pri_b=array();
            foreach($old_pris as $p){
                $str_pri_b[]=self::backquote($p['Field']);
            }
            if(implode(",",$str_pri_a)!==implode(",",$str_pri_b)){
                $str_pri=implode(",",$str_pri_a);
            }

        }

        if(!count($cols) && !$str_pri){
            $rt=new ormResult(true);
        }else{
            if(count($cols)){
                $sql.=implode(",\n",$cols);
            }
            if($str_pri){
                $sql.=count($cols)?",":"";
                $sql.=" DROP PRIMARY KEY,ADD PRIMARY KEY(".$str_pri.")";
            }
            $rt=$c->execute($sql);
        }
        $rt->schema=$sa;
        return $rt;

    }
    static function checksa($tbName,$sa,$is_alter=true){
        if(!is_array($sa)){
            throw new Exception("Table-Schema is not defined!");
        }
        if(!preg_match("@^[a-zA-Z][a-zA-Z0-9_]*$@i",$tbName)){//必须符合字段名命名规
            throw new Exception("Incorrect Name of Table:".$tbName);
        }
        //判断$sa有没有id列
        $idFld=array("Field"=>ormYo::$IDField,"Type"=>"int","Null"=>"NO","Key"=>"","Default"=>"","Extra"=>"auto_increment");
        $hasId=false;
        $hasKey=false;
        $toDel=array();
        $msg=array();
        foreach($sa as $i=>$r){
            if($r['Field']==ormYo::$IDField){
                $hasId=true;
                $idFld=$r;
            }
            if($r['Extra']=="auto_increment" && $r['Field']!==ormYo::$IDField && !$is_alter){
                //已经有自增列
                $toDel[]=$i;
                $msg[]=$r['Field']." Can't set auto_increment";
            }
            if(!preg_match("@^[a-zA-Z][a-zA-Z0-9_]*$@i",$r['Field'])){//必须符合字段名命名规则
                $toDel[]=$i;
                $msg[]="Incorrect Filed:".$r['Field'];
            }
            if($r['Key']=="PRI"){
                $hasKey=true;
            }
            $test_type=preg_replace('/\(.*/', '', $r['Type']);
            if($test_type){
                if(!preg_match(
                    '@^(DATE|DATETIME|TIME|TINYBLOB|TINYTEXT|BLOB|TEXT|'
                    .'VARCHAR|CHAR|FLOAT|DECIMAL|INT|BIGINT|TINYINT|ENUM|TIMESTAMP|'
                    . 'MEDIUMBLOB|MEDIUMTEXT|LONGBLOB|LONGTEXT|SERIAL|BOOLEAN|BOOL|UUID)$@i',
                    $test_type
                )){
                    $msg[]="Filed-> ".$r['Field'].":incorrect type definition as ".$test_type;
                }
            }
        }
        if(!$hasId){
            if(!$hasKey){
                $idFld['Key']="PRI";
            }
            array_unshift($sa,$idFld);
        }
        $idFld['Extra']="auto_increment";
        $idFld['Type']="int(10)";
        $idFld['Null']="NO";

        foreach($toDel as $k=>$i){
            $di=$i;
            if(!$hasId){
                $di=$i+1;
            }
            if(isset($sa[$di])){
                unset($sa[$di]);
            }
        }
        if(count($sa)==0){
            throw new Exception("Incorrect Table-Schema");
        }
        $pris=array();
        foreach($sa as $i=>$r){
            if($r['Key']=="PRI"){
                $sa[$i]['NULL']="NO";
                $pris[]=$r;
            }
        }
        if(count($msg)){
            throw new Exception("Incorrect Table-Schema:".json_encode($msg));
        }

        return array($sa,$pris,$idFld);
    }
    static function generateTable($tbName,$sa,$dsn){
        list($sa,$pris,$idFld)=self::checksa($tbName,$sa,false);
        $sql="CREATE TABLE  IF NOT EXISTS `".$tbName."` (";
        $cols=array();
        foreach($sa as $i=>$r){
            list($new_r,$newCol)=self::generateColumn($r);//可能是错误的配置
            if($newCol){
                $cols[]=$newCol;
            }
            $sa[$i]=$new_r;
        }
        if(!count($cols)){
            throw new Exception("Incorrect Table-Schema");
        }
        $sql.=implode(",",$cols);

        if(count($pris)){
            $sql.=",PRIMARY KEY(";
            $p_a=array();
            foreach($pris as $r){
                $p_a[]=self::backquote($r['Field']);
            }
            $sql.=implode(",\n",$p_a);
            $sql.=")";
            if($idFld['Key']!=="PRI"){
                $sql.=",UNIQUE KEY ".self::backquote($idFld['Field'])."(".self::backquote($idFld['Field']).")";
            }
        }
        $sql.=") ENGINE=INNODB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
        $c=ormYo::Conn($dsn);
        $rt=$c->execute($sql);
        $rt->MSG.="\n".implode(";\n",$msg);
        $rt->schema=$sa;
        return $rt;
    }
    static function generateColumn($r){
        //结构array("Field"=>"user_code","Type"=>"int","Null"=>"NO","Key"=>"","Default"=>"","Extra"=>"auto_increment","Comment"=>'测试');
        if(!preg_match("@^[a-zA-Z][a-zA-Z0-9_]*$@i",$r['Field'])){//必须符合字段名命名规则
            return array($r,"");
        }
        if($r['Field']==ormYo::$IDField){
            return array($r,self::backquote(ormYo::$IDField)." INT(10) unsigned NOT NULL AUTO_INCREMENT");
        }
        if($r['Extra']=="auto_increment"){
            return array($r,self::backquote($r['Field'])." INT(10) unsigned NOT NULL AUTO_INCREMENT");
        }

        $sql=self::backquote($r['Field']);
        //处理type
        if(!$r['Type']){
            if($r['Extra']=="auto_increment"){
                $r['Type']="INT(11)";
            }else{
                $r['Type']="VARCHAR(100)";
            }
        }
        if(strtoupper($r['Type'])=='BOOLEAN' || strtoupper($r['Type'])=='BOOL' || strtoupper($r['Type'])=='TINYINT'){
            $r['Type']="TINYINT(1)";
        }
        //分割type & length
        if (preg_match('/^(\w+)(?:\s*\(([^\)]*)\))?(\s+.+)?$/', $r["Type"], $m)) {
            $dtype=$m[1];
            $dlen=$m[2];
            $dprop = $m[3];
        } else {
            $dtype = null;
            $dlen = null;
            $dprop = null;
        }

        if($dtype===$dlen){
            $dlen="";
        }else{
            if(preg_match(
                '@^(DATE|DATETIME|TIME|TINYBLOB|TINYTEXT|BLOB|TEXT|'
                . 'MEDIUMBLOB|MEDIUMTEXT|LONGBLOB|LONGTEXT|SERIAL|BOOLEAN|UUID)$@i',
                strtoupper($dtype)
            )){
                $dlen="";
            }
        }
        $dtype=strtoupper($dtype);
        //preg_match('@^()$@i'
        if(preg_match('@^(VARCHAR|CHAR)$@i',$dtype)){
            if(!$dlen){
                $dlen="100";//默认字符串长度
            }else{
                $dlen=intval($dlen);
            }
            $r['Type']=$dtype."(".$dlen.")".$dprop;
        }
        if(preg_match('@^(FLOAT|INT)$@i',$dtype)){
            if(!$dlen){
                $dlen="11";//默认字符串长度
            }
            //这里可能抛异常，因为有小数处理
            $r['Type']=$dtype."(".$dlen.")".$dprop;
        }
        if($dtype=="DECIMAL"){
            $dlen=$dlen?:"10,2";
            //这里可能抛异常，因为有小数处理
            $r['Type']=$dtype."(".$dlen.")".$dprop;
        }

        $sql.=" ".$dtype.($dlen?"(".$dlen.")":"").$dprop;

        //处理null和default
        $allowNull=true;
        if($r['Key']=='PRI' || preg_match('@^(FAlse|NO|1)$@i',strtoupper($r['Null'])) || preg_match('@^(TIMESTAMP)$@i',$dtype)){
            $sql.=" NOT NULL";
            $allowNull=false;
            $r['Null']="NO";
        }
        if(preg_match('@^(TIMESTAMP)$@i',$dtype)){
            $sql.=" DEFAULT CURRENT_TIMESTAMP";
        }elseif($dtype=="TINYINT"){
            if(preg_match('/^1|T|TRUE|YES$/i', strtoupper($r['Default']))){
                $sql.=" DEFAULT '1'";
                $r['Default']='1';
            }else{
                $sql.=" DEFAULT '0'";
                $r['Default']="0";
            }
        }elseif(preg_match('@^(DATE|DATETIME)$@i',$dtype)){
            $sql.=" DEFAULT NULL";
        }elseif(preg_match('@^(FLOAT|DECIMAL|INT)$@i',$dtype)){
            $sql.=" DEFAULT '".intval($r['Default'])."'";//数字类型无论怎样都设置一个默认值
            $r['Default']=intval($r['Default']);
        }else{
            if($r['Default']){
                $sql.=' DEFAULT \'' . self::sqlAddSlashes($r['Default']) . '\'';
            }else{
                if($allowNull){
                    $sql.=" DEFAULT NULL";
                }else{
                    $sql.=" DEFAULT ''";
                }
            }
        }

        //处理comment
        if($r['Comment']){
            $sql.=' COMMENT \'' . self::sqlAddSlashes($r['Comment']) . '\'';
        }
        return array($r,$sql);
    }
}
trait ormTrait{

    public  function _formatCondition($condition) {
        if (!$condition || !count($condition))
            return "";
        foreach ($condition as $k => $kv) {
            if($kv instanceof ormParameter){
                $v=$kv->toArray();
            }else{
                if(is_array($kv)){
                    //$kv的可能格式是array(">","100")
                    //$kv的可能格式是array("in",array())
                    //$kv的可能格式是array("like","test")
                    if(count($kv)<2) continue;
                    switch(strtolower($kv[0])){
                        case "in":
                            $v=(new ormParameter($this->name,$k,$k." in ('".implode("','",$kv[1])."')","sql"))->toArray();
                            break;
                        case "not in";
                            $v=(new ormParameter($this->name,$k,$k." not in ('".implode("','",$kv[1])."')","sql"))->toArray();
                            break;
                        case "between":
                            $v=(new ormParameter($this->name,$k,$kv[1],"between",$kv[2]))->toArray();
                            break;
                        case "number-between":
                            $v=(new ormParameter($this->name,$k,$kv[1],"number-between",$kv[2]))->toArray();
                            break;
                        case "or":
                            $v=(new ormParameter($this->name,$k,"or (".self::_formatCondition($kv[1]).")","sql"))->toArray();
                            break;
                        case "eq":
                        case "=":
                            $v=(new ormParameter($this->name,$k,$kv[1],"="))->toArray();
                            break;
                        case "neq": case "!=":
                        $v=(new ormParameter($this->name,$k,$kv[1],"!="))->toArray();
                        break;
                        case "gt": case ">":
                        $v=(new ormParameter($this->name,$k,$kv[1],">"))->toArray();
                        break;
                        case "egt": case ">=":
                        $v=(new ormParameter($this->name,$k,$kv[1],">="))->toArray();
                        break;
                        case "lt":case "<":
                        $v=(new ormParameter($this->name,$k,$kv[1],"<"))->toArray();
                        break;
                        case "elt":case "<=":
                        $v=(new ormParameter($this->name,$k,$kv[1],"<="))->toArray();
                        break;
                        case "time":
                            if($kv[1] && $kv[2]){
                                $kv[1]=strtotime("Y-m-d 00:00:00",$kv[1]);
                                $kv[2]=strtotime($kv[2])+86400;
                                $v=(new ormParameter($this->name,$k,$kv[1],"between",date("Y-m-d 00:00:00",$kv[2])))->toArray();
                            }else{
                                if($kv[1]){
                                    $kv[1]=strtotime("Y-m-d 00:00:00",$kv[1]);
                                    $v=(new ormParameter($this->name,$k,$kv[1],">="))->toArray();
                                }else{
                                    if($kv[2]){
                                        $kv[2]=strtotime("Y-m-d 00:00:00",$kv[2]);
                                        $v=(new ormParameter($this->name,$k,$kv[2],"<="))->toArray();
                                    }
                                }
                            }
                            break;
                        default:
                            $v=(new ormParameter($this->name,$k,$kv[1],$kv[0]))->toArray();
                    }


                }else{
                    $v=(new ormParameter($this->name,$k,$kv))->toArray();
                }
            }
            //if($v['value']===null || $v['value']==='') continue;
            if (!$v['field'])
                continue;
            if (!$v['relative'])
                $v['relative'] = "=";

            if(!$v['table']) $v['table']=$this->name;

            if($v['field']=='PHPSESSID') continue;
            //if(!isset($cols[$v['field']])) continue;
            $item = '';

            if(!strpos($v['field'],".")){
                $v['field']=$v['table'].".`".$v['field']."`";
            }


            switch ($v['relative']) {
                case "between":
                    $item =  $v['field'] . " " . $v['relative'] . " " . self::_formatStr(date('Y-m-d 00:00:00',strtotime($v['value']))) . " and " . self::_formatStr(date('Y-m-d 23:59:59',strtotime($v['value2'])));
                    break;
                case "like":
                    $item =  $v['field'] . " " . $v['relative'] . " " . self::_formatStr($v['value'], "%", "%");
                    break;
                case "number-between":
                    $item =  $v['field'] . ">=" . self::_formatStr($v['value']) . " and `" .  $v['field'] . "`<=" . self::_formatStr($v['value2']);
                    break;
                case "prelike":
                    $item =  $v['field'] . " like " . self::_formatStr($v['value'], "%");
                    break;
                case "endlike":
                    $item =  $v['field'] . " like " . self::_formatStr($v['value'], "", "%");
                    break;
                case 'sql':
                    $item = $v['value'];
                    break;
                case 'eq':
                case '=':
                    $item =  $v['field'] . " = " . self::_formatStr($v['value']);
                    break;
                default:
                    //TODO 这里未完善的
                    $item =  $v['field'] . " " . $v['relative'] . " " . self::_formatStr($v['value']);
                    break;
            }
            if (!$where) {
                $where = " " . $item;
            } else {
                $where.=" and " . $item;
            }
        }
        return $where ? $where : "";
    }
    private static function _formatStr($s, $prefix='', $endfix='') {
        if (!$endfix)
            $endfix = "";
        if (!$prefix)
            $prefix = "";
        if ($s == 'null')
            return $s;
        $x = "'" . $prefix . str_replace("'", "''", $s) . $endfix . "'";
        $x = addcslashes($x, "\\"); //这个是一定要的吗？待测试...TODO
        return $x;
    }
    public static function sqlAddSlashes(
        $a_string = '', $is_like = false, $crlf = false, $php_code = false
    ) {
        if ($is_like) {
            $a_string = str_replace('\\', '\\\\\\\\', $a_string);
        } else {
            $a_string = str_replace('\\', '\\\\', $a_string);
        }

        if ($crlf) {
            $a_string = strtr(
                $a_string,
                array("\n" => '\n', "\r" => '\r', "\t" => '\t')
            );
        }

        if ($php_code) {
            $a_string = str_replace('\'', '\\\'', $a_string);
        } else {
            $a_string = str_replace('\'', '\'\'', $a_string);
        }

        return $a_string;
    } // end of the 'sqlAddSlashes()' function
    public static function backquote($a_name, $do_it = true)
    {
        if (is_array($a_name)) {
            foreach ($a_name as $k=>$data) {
                $a_name[$k] = self::backquote($data, $do_it);
            }
            return $a_name;
        }

        if (! $do_it) {
            global $PMA_SQPdata_forbidden_word;
            if (! in_array(strtoupper($a_name), $PMA_SQPdata_forbidden_word)) {
                return $a_name;
            }
        }

        // '0' is also empty for php :-(
        if (strlen($a_name) && $a_name !== '*') {
            return '`' . str_replace('`', '``', $a_name) . '`';
        } else {
            return $a_name;
        }
    } // end of the 'backquote()' function

}
class ormDataView{
    use ormTrait;
//忽然觉得dataview（我打算做数据选择器）没什么意义，最方便最熟悉的还是sql语句
//只对查询后的集合做些简单操作吧
    public $rows;//collection
    public $columns;//collection
    public $conn;//
    public $reader;
    public $pageCount=0;
    public $pageSize=0;
    public $currentPage=0;
    public $totalCount=0;
    public $ListCols="*";

    public function __construct($_dsn=null){
        $this->rows=new ormCollection();
        if(is_array($_dsn)){
            $this->conn=ormYo::Conn($_dsn);
        }else{
            if($_dsn instanceof ormConnection){
                $this->conn=$_dsn;
            }else{
                throw new Exception("Connection is not Defined");
            }
        }

    }
    public static function order($rows,array $rule){
        if(!is_array($rows)) return $rows;
        if(!$rule) return $rows;
        $keys=array();
        $keys_by=array();
        foreach($rule as $r){
            $arr=explode(" ",$r);
            $key=trim($arr[0]);
            $by=trim($arr[1]);
            $new_arr=array();
            foreach($rows as $k=>$v){
                $new_arr[$k]=$v[$key];
            }
            $keys[]=$new_arr;
            if(strtolower($by)!='desc'){
                $keys_by[]='SORT_ASC';
            }else{
                $keys_by[]='SORT_DESC';
            }
        }
        $str="";
        foreach($keys as $k=>$v){
            if(!$str){
                if($keys_by[$k]=='SORT_ASC'){
                    $str='array_multisort($keys['.$k.'],SORT_ASC';
                }else{
                    $str='array_multisort($keys['.$k.'],SORT_DESC';
                }
            }else{
                if($keys_by[$k]=='SORT_ASC'){
                    $str.=',$keys['.$k.'],SORT_ASC';
                }else{
                    $str.=',$keys['.$k.'],SORT_DESC';
                }
            }
        }
        $str.=",\$rows);";
        eval($str);
        return $rows;
    }
    public function formatColumn($src_column,array $values=array(),$new_column=NULL){
        if(!count($this->rows)) return;
        foreach($this->rows as $row){
            if(!isset($row[$src_column])) return;
            $new_value=$values[$row[$src_column]];
            if($new_column){
                $row[$new_column]=$new_value;
            }else{
                $row[$src_column]=$new_value;
            }
        }
    }
    private $_call_group_by="";
    private $_call_having="";
    private $_call_order_by="";
    private $_call_where="";
    private $_call_limit=0;
    private $_call_field="";
    function __call($name,$args){

        if(function_exists($name)){
            return $this->$name($args);
        }
        if(count($args) && !$args[0]){
            return $this;
        }
        switch($name){
            case "groupBy":
                if(is_array($args) && count($args)){
                    $this->_call_group_by=$args[0];
                }
                return $this;
            case "having":
                if(is_array($args)  && count($args)){
                    $this->_call_having=$args[0];
                }
                return $this;
            case "orderBy":
                if(is_array($args)  && count($args)){
                    $this->_call_order_by=$args[0];
                }
                return $this;
            case "limit":
                if(is_array($args)  && count($args)){
                    $this->_call_limit=$args[0];
                }
                return $this;
            case "where":
                if(is_array($args)  && count($args)){
                    $this->_call_where=$args[0];
                }
                return $this;
            case "field":
                if(is_array($args) && count($args)){
                    $this->_call_field=$args[0];
                }
                return $this;
            default:
                break;
        }
        throw new Exception("Invalid function!");

    }
    public function fill($condition,$page,$pageSize,$sql_spec,$group_by,$having,$order_by){
        if(!$this->conn) throw new Exception('No Data Connection！');
        if(!$this->reader) $this->reader=new ormReader($this->conn);
        $rt=new ormCollection();
        if(!$condition){
            $str_where="";
        }else{
            if(is_string($condition) && $condition){
                /*if(!preg_match("@^where.*@i",$condition)){
                    $str_where=" where ".$condition;
                }else{
                    $str_where=$condition;
                }*/
                $str_where=$condition;
            }else{
                $str_where=$this->_formatCondition($condition);
            }
        }

        if(!$sql_spec){
            if($this->_call_select){
                $sql_spec=$this->_call_select;
            }else{
                throw new Exception("Invalid SQL.");
            }
        }
        if($str_where && !preg_match("@^where.*@i",$str_where)){
            $str_where=" where ".$str_where;
        }else{
            if(!$str_where){
                if($this->_call_where){
                    $str_where=" where ".$this->_call_where;
                }
            }
        }

        $sql=$sql_spec." ".$str_where;
        $by_group=$group_by?:$this->_call_group_by;
        $by_order=$order_by?:$this->_call_order_by;
        $by_having=$having?:$this->_call_having;


        if($by_group) $sql=$sql." group by ".$by_group;
        if($by_having) $sql=$sql. " having ".$by_having;
        if($by_order) $sql=$sql." order by ".$by_order;



        if($pageSize>0){
            $rd=$this->reader->getPage($sql,$page,$pageSize);
            $rows=$rd->rows;
            $this->totalCount=$rd->count;
            $this->currentPage=$page;
        }else{
            if($this->_call_limit){
                $rd=$this->reader->getPage($sql,1,$this->_call_limit);
                $rows=$rd->rows;
                $this->totalCount=$rd->count;
                $this->currentPage=1;
            }else{
                $rows=$this->reader->getRecord($sql);
                $this->totalCount=count($rows);
            }
        }
        $this->rows=$rows;
        $this->_call_where="";
        $this->_call_field="";
        $this->_call_group_by="";
        $this->_call_having="";
        $this->_call_limit="";
        $this->_call_order_by="";


        return $this->formatRows($rows);
    }
    protected function formatRows($items){//override by sub class
        return $items;
    }
}

class ormParameter implements IormObj{
    public $table;
    public $field;
    public $value;
    public $relative;
    public $value2;
    function __construct($_table,$_column,$_value,$_relative='=',$_value2=null){
        $this->table=$_table;
        $this->field=$_column;
        $this->value=$_value;
        $this->relative=$_relative;
        $this->value2=$_value2;
    }
    public function getName(){
        return $this->field;
    }
    public function toArray(){
        return ormYo::obj2array($this);
    }
}
class ormReader{
    use ormTrait;
    public $conn;
    public $name="";
    function __construct(ormConnection $_conn=null){
        if(!$_conn) $_conn=ormYo::Conn();
        $this->conn=$_conn;
    }
    //*********************************************************************封装查询
    public function getRecord($sql) {
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        return $rt->RESULT;
    }
    public function getRows($sql,$instance=false){
        $rs=$this->getRecord($sql);
        if($instance){
            $rt=new ormCollection();
            if(is_array($rs)){
                foreach($rs as $item){
                    if(is_array($item)){
                        $rt->add(new ormDataRow($item));
                    }
                }
            }
            return $rt;
        }else{
            return $rs;
        }
    }
    public function getRow($sql,$instance=false) {
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        $rs=$rt->FIRST_ROW;
        if($instance){
            if(is_array($rs)){
                return new ormDataRow($rs);
            }else{
                return new ormDataRow();
            }
        }else{
            return $rs;
        }
    }
    public function getOne($sql,$flag=1) {
        $cmd=new ormSqlCommand($this->conn,$sql);
        $rt=$cmd->run();
        return $rt->FIRST_VALUE;
    }
    /*
     * ormPageResult @return
     * */
    public function getPage($sql,$page,$pagesize){
        $res=$this->conn->executePage($sql,$pagesize,$page);
        $rt=new ormPageResult();
        $rt->rows=$res->result;
        $rt->count=$rowcount=$res->maxRecordCount;
        $rt->pageCount=ceil($rowcount/$pagesize);
        $rt->pageIndex=$page;
        $rt->pageSize=$pagesize;
        $rt->sql=$sql;
        return $rt;

    }
}
class ormResult implements IormObj{
    const ERROR_NONE=0;//没有错误
    const ERROR_NOTNULL=1;//不能为空
    const ERROR_INVALID_TYPE=2;//错误的数据类型
    const ERROR_MAXLEN=3;//超出长度
    const ERROR_EXIST=4;//已经存在
    const ERROR_ENUM_LIMIT=5;//不符合枚举
    const ERROR_RELATION_LIMIT=6;//约束限制
    const ERROR_INVALID_OBJECT=90;//错误的对象
    const ERROR_INVALID_ROWSTATE=91;//错误的行状态
    const ERROR_INVALID_PRIMARYKEYS=92;//错误的主健或者值
    const ERROR_NOTCATCH=98;//无法捕获的错误
    const ERROR_EMPTY=99;//数据对象为空
    public $STS;
    public $MSG;
    public $AUTO_ID;
    public $RESULT;
    public $COUNT;
    public $SRC_COLUMN;
    public $ERROR_NO;
    public $FIRST_ROW;
    public $FIRST_VALUE;
    public $SRC_ROW;
    public $SRC_SQL;
    public $FIELDS;

    function __construct($_sts=true,$_error_no=0,$_msg='',$_src_col='',$_auto_id='',$_count='',$_result=''){
        $this->STS=$_sts;
        $this->ERROR_NO=$_error_no;
        if(!$this->ERROR_NO) $this->ERROR_NO=self::ERROR_NONE;
        $this->MSG=$_msg;
        $this->AUTO_ID=$_auto_id;
        $this->SRC_COLUMN=$_src_col;
        $this->COUNT=$_count;
        $this->RESULT=$_result;
    }
    public function getName(){
        return NULL;
    }
    public function toArray(){
        return ormYo::obj2array($this);
    }
}
class ormSqlCommand{
    private $conn;
    private $commandText;
    function __construct($_conn,$commandText){
        $this->conn=$_conn;
        $this->commandText=$commandText;
    }
    public function run(){
        if(!$this->commandText) return;
        return $this->conn->execute($this->commandText);
    }
    public function runSql($sql){
        $this->commandText=$sql;
        return $this->run();
    }
}
class ormTableSchema{
    public $tableName;
    public $conn;
    public $primaryKeys;//ormCollection
    public $autoKeys;//ormCollection
    public $schema;//ormCollection
    function __construct($tbname,$_conn){
        $this->tableName=$tbname;
        $this->conn=$_conn;
        $this->setinit();
    }
    private function setinit(){
        if(ormYo::$table_source_pool[$this->tableName]){
            $this->schema=ormYo::$table_source_pool[$this->tableName];
        }else{
            $schema_sql="select COLUMN_NAME `Field`,COLUMN_TYPE `Type`,IS_NULLABLE `Null`,COLUMN_KEY `Key`,COLUMN_DEFAULT `Default`,EXTRA `Extra`,COLUMN_COMMENT `Comment` from information_schema.COLUMNS where TABLE_SCHEMA='".$this->conn->db_name ."' and TABLE_NAME='".$this->tableName."'";
            //$schema_sql="DESCRIBE ".$this->tableName;
            $command=new ormSqlCommand($this->conn,$schema_sql);
            $rt=$command->run();
            $rows=$rt->RESULT;
            $this->schema=new ormCollection($rows);
            ormYo::$table_source_pool[$this->tableName]=$this->schema;
        }

        $this->autoKeys=$this->schema->find(array('Extra'=>'auto_increment'));
        $this->primaryKeys=$this->schema->find(array('Key'=>'PRI'));
    }
}
class dbMysql implements IormDriver{
    private $host,$port,$dbname,$user_name,$pwd;
    public $inserted_id;
    public $ErrorMsg;
    public $ErrorNo;
    public $conn;
    public $last_queryID;
    private $transaction=-1;
    function __construct($_host,$_port,$_dbname,$_user_name,$_pwd){
        $this->host=$_host;
        $this->port=$_port;
        $this->dbname=$_dbname;
        $this->user_name=$_user_name;
        $this->pwd=$_pwd;
    }
    private function open(){
        if(!$this->conn){
            $this->conn=mysql_connect($this->host.":".$this->port,$this->user_name,$this->pwd);
            if(!$this->conn){
                die("Connect DataBase Failed:".	mysql_error());
            }else{
                $r=mysql_select_db($this->dbname,$this->conn);
                if(!$r){
                    die("Not Found DataBase".$this->dbname);
                }else{
                    mysql_query("SET CHARACTER SET utf8");
                }
            }//end if($this->conn
        }
    }
    private function close(){
        if($this->conn){
            mysql_close($this->conn);
        }
    }
    public function execute($sql){
        debug($sql);
        $this->open();
        $rt=new dbResult();
        $rt->sql=$sql;
        $rt->queryID=$this->last_queryID=mysql_query($sql,$this->conn);//也许以后应该使用mysql_unbuffered_query()
        $rt->errorNo=mysql_errno();
        $rt->errorMsg=mysql_error();
        if($rt->errorNo>0){//
            if($this->transaction===-1){ //如果是事务则不跳出。
                throw new Exception($rt->errorMsg.",SOURCE-SQL:".$sql,$rt->errorNo);
            }else{
                $this->transaction+=1;
                return $rt;
            }
        }
        if($rt->queryID){
            $rt->insertedID=mysql_insert_id();
            $rt->result=array();
            $rt->affectedRows=mysql_affected_rows($this->conn);
            $rt->recordCount=mysql_num_rows($rt->queryID);
            $rt->fieldCount=mysql_num_fields($rt->queryID);
            $rt->maxRecordCount=$rt->recordCount;
            while($row=mysql_fetch_assoc($rt->queryID)){
                $rt->result[]=$row;
            }

            $rt->fields=array();
            $cnt=$rt->fieldCount;
            for($i=0;$i<$cnt;$i++){
                $fld=mysql_field_name($this->queryID,$i);
                $fld_type=mysql_field_type($this->queryID,$i);
                if($fld_type=='real') $fld_type="decimal";
                if($fld_type=='string') $fld_type="varchar";
                $col=new ormDataColumn($fld);
                $col->dataType=$fld_type;
                $rt->fields[]=$col;
            }
        }
        return $rt;
    }
    public function executePage($sql,$pageSize,$page){
        if(!$page) $page=1;
        if(!$pageSize) return $this->execute($sql);
        $rt=new dbResult();
        $rt->sql=$sql;
        //要架设$sql本身没有limit
        $sql_new="select count(*) total from (".$sql.") z000000000000000001";//总觉得这样取总数的效率好低，貌似没更好的方法，除非把$sql分类
        $rt_1=$this->execute($sql_new);
        $rs_1=$rt_1->result;
        $total=$rs_1[0]['total'];
        if($total==0){
            return $rt;
        }
        if($pageSize==0) return $rt;
        $page_cnt=ceil($total/$pageSize);//总的page数
        if($page>$page_cnt) return $rt;
        $limit_start=($page-1)*$pageSize;
        $sql_new=$sql." limit ".$limit_start.",".$pageSize;
        $rt=$this->execute($sql_new);
        $rt->maxRecordCount=$total;
        return $rt;
    }
    public function startTransaction(){
        $this->open();
        mysql_query('START TRANSACTION');
        $this->transaction=0;
    }
    public function submitTransaction(){
        if($this->transaction>0){
            mysql_query('ROLLBACK');
        }else{
            mysql_query('COMMIT');
        }
        $this->transaction=-1;
    }
    public function rollback(){
        mysql_query('ROLLBACK');
        $this->transaction=-1;
    }
}
class dbMysqlI implements IormDriver{
    private $host,$port,$dbname,$user_name,$pwd;
    public $inserted_id;
    public $ErrorMsg;
    public $ErrorNo;
    public $conn;
    public $last_queryID;
    private $transaction=-1;
    function __construct($_host,$_port,$_dbname,$_user_name,$_pwd){
        $this->host=$_host;
        $this->port=$_port;
        $this->dbname=$_dbname;
        $this->user_name=$_user_name;
        $this->pwd=$_pwd;
    }
    private function open(){
        if(!$this->conn){
            $this->conn=new mysqli($this->host,$this->user_name,$this->pwd,$this->dbname,$this->port);
            if($this->conn->connect_error){
                die("Connect DataBase Failed:".	$this->conn->connect_error."(".$this->conn->connect_errno.")");
            }else{
//                $r=$this->conn->select_db($this->dbname);DataBasextask
                $this->conn->set_charset("utf8");
                $this->conn->autocommit(true);
                /*                if(!$r){
                                    die("Not Found DataBase".$this->dbname);
                                }else{
                                    $this->conn->set_charset("utf8");
                                    $this->conn->autocommit(true);
                                }
                */
            }//end if($this->conn
        }



    }
    private function close(){
        if($this->conn && !$this->conn->connect_error){
            $this->conn->close();
        }
    }
    public function execute($sql){
        $this->open();
        $rt=new dbResult();
        $rt->sql=$sql;
        $rt->queryID=$this->last_queryID=$this->conn->query($sql);
        $rt->errorNo=$this->conn->errno;
        $rt->errorMsg=$this->conn->error;
        if($rt->errorNo>0){//todo:更多处理
            if($this->transaction===-1){ //如果是事务则不跳出。
                throw new Exception($rt->errorMsg.",SOURCE-SQL:".$sql,$rt->errorNo);
            }else{
                $this->transaction+=1;
                return $rt;
            }
        }
        if($rt->queryID){
            $rt->insertedID=$this->conn->insert_id;
            $rt->result=array();
            $rt->fields=array();
            $rt->affectedRows=$this->conn->affected_rows;
            if(is_bool($rt->queryID)){
                $rt->result=true;
            }else{
                $rt->recordCount=$rt->queryID->num_rows;
                $rt->fieldCount= $rt->queryID->field_count;
                $rt->maxRecordCount=$rt->recordCount;
                while($row=$rt->queryID->fetch_assoc()){
                    $rt->result[]=$row;
                }
                $cols=$rt->queryID->fetch_fields();
                foreach($cols as $val){
                    $col=new ormDataColumn($val->name,$val->name);
                    $col->dataType=$val->type;
                    $col->dataLen=$val->length;
                    $col->enum=$val->flags;
                    $rt->fields[]=$col;
                }

            }
        }

        return $rt;
    }
    public function executePage($sql,$pageSize,$page){
        if(!$page) $page=1;
        if(!$pageSize) return $this->execute($sql);
        $rt=new dbResult();
        $rt->sql=$sql;
        //要架设$sql本身没有limit
        $sql_new="select count(*) total from (".$sql.") z000000000000000001";//总觉得这样取总数的效率好低，貌似没更好的方法，除非把$sql分类
        $rt_1=$this->execute($sql_new);
        $rs_1=$rt_1->result;
        $total=$rs_1[0]['total'];
        if($total==0){
            return $rt;
        }
        if($pageSize==0) return $rt;
        $page_cnt=ceil($total/$pageSize);//总的page数
        if($page>$page_cnt) return $rt;
        $limit_start=($page-1)*$pageSize;
        $sql_new=$sql." limit ".$limit_start.",".$pageSize;
        $rt=$this->execute($sql_new);
        $rt->maxRecordCount=$total;
        return $rt;
    }
    public function startTransaction(){
        if($this->transaction===0){
            //说明已经启动事务，抛异常提醒程序员
            throw new Exception("Transaction Has been Started Before");
        }else{
            $this->open();
            $this->conn->autocommit(false);
            $this->transaction=0;
        }
    }
    public function submitTransaction(){
        if($this->transaction===-1){
            throw new Exception("No Start Transaction");
        }
        if($this->transaction>0){
            $this->conn->rollback();
        }else{
            $this->conn->commit();
        }
        $this->conn->autocommit(true);
        $this->transaction=-1;
    }
    public function rollback(){
        $this->conn->rollback();
        $this->conn->autocommit(true);
        $this->transaction=-1;
    }
}
class dbResult{
    public $queryID;//查询指针
    public $errorNo;
    public $insertedID;
    public $errorMsg;
    public $maxRecordCount=0;
    public $recordCount=0;
    public $fieldCount=0;
    public $affectedRows=-1;
    public $result;
    public $fields;
    public $sql;
    public function toArray(){
        return array(
            "errorNo"=>$this->errorNo,
            "errorMsg"=>$this->errorMsg,
            "insertedID"=>$this->insertedID,
            "recordCount"=>$this->recordCount,
            "fieldCount"=>$this->fieldCount,
            "affectedROws"=>$this->affectedRows,
            "result"=>$this->result,
            "sql"=>$this->sql,
            "fields"=>$this->fields
        );
    }
}
class ormPageResult{
    public $rows=array();
    public $count=0;
    public $pageSize=0;
    public $pageIndex=0;
    public $pageCount=0;
    public $sql="";
}
class ormDriver{
    const MYSQL="mysql";
    const MYSQLI="mysqli";
    /*
     * @return IormDriver
     * */
    static function getDriver($db_dsn,$driver_name){
        switch($driver_name){
            case self::MYSQL:
                return new dbMysql($db_dsn['db_host'],$db_dsn['db_port'],$db_dsn['db_name'],$db_dsn['db_user'],$db_dsn['db_pwd']);
            case self::MYSQLI:
                return new dbMysqlI($db_dsn['db_host'],$db_dsn['db_port'],$db_dsn['db_name'],$db_dsn['db_user'],$db_dsn['db_pwd']);
            default:
                return new dbMysqlI($db_dsn['db_host'],$db_dsn['db_port'],$db_dsn['db_name'],$db_dsn['db_user'],$db_dsn['db_pwd']);
        }
    }
}
interface IormDriver{
    public  function execute($sql);
    public function executePage($sql,$pageSize,$page);
    public function startTransaction();
    public function submitTransaction();
    public function rollback();
}