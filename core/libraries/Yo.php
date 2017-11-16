<?php

trait modelBase{
    //不在里面定义常量，以免冲突
    private function _getSqlSelectTb($str){
        if(!preg_match("@^select@i",trim($str))){
            $str=trim($str);
        }else{
            preg_match_all("@(?<=SELECT\s)(?:(?!select|\sfrom\s)[\s\S])*@i",$str,$a);
            if(count($a) && count($a[0])){
                $str=$a[0][0];
            }
        }

        preg_match_all("@\(([^()]|(?R))*\)@i",$str,$spec_a);
        $str_1=preg_replace("@\(([^()]|(?R))*\)@i","=",$str);
        $b=explode(",",$str_1);
        if(count(!$spec_a)) $spec_a=$spec_a[0];
        $a=array();
        $b_i=0;
        $rt=array();
        foreach($b as $r){
            $r=preg_replace("@\s+as\s+@i"," ",$r);
            $r=preg_replace("@\s+\s+@i"," ",$r);
            if(preg_match("@\=@i",$r)){
                $tmp_fld=$spec_a[$b_i];
                $tmp_as=trim(str_replace("=","",$r));
                $rt[$tmp_fld]=array('is_spec'=>true,"as"=>trim($tmp_as));
                $b_i+=1;
            }else{
                $r_a=explode(" ",$r);
                $col=NULL;$col_as=NULL;$tb=NULL;
                $col=$r_a[0];
                if(count($r_a)==2){
                    $col_as=$r_a[1];
                }
                $col_a=explode(".",$col);
                if(count($col_a)==2){
                    $fld=$col_a[1];
                    $tb=$col_a[0];
                }else{
                    $fld=$col_a[0];
                }
                $rt[$fld]=array("table"=>$tb,"as"=>$col_as);
            }
        }
        return $rt;
    }
    private function _getSqlFromTb($str){//获取sql语句中表及其别名
        $str=preg_replace("@\s+@"," ",$str);
        $t_a=array();
        preg_match_all("@(?<=from)\s+\w+(?=\s|\))@i",$str,$a);
        if(count($a)){
            $a=$a[0];
            $t_a=array_merge($t_a,$a);
        }
        preg_match_all("@(?<=from)\s+\w+$@i",$str,$a);
        if(count($a)){
            $a=$a[0];
            $t_a=array_merge($t_a,$a);
        }

        $t2=preg_match_all("@(?<=join)\s+\w+(?=\s)@i",$str,$b);
        if(count($b)){
            $b=$b[0];
            $t_a=array_merge($t_a,$b);
        }
        $t_a=array_unique($t_a);
        //求as: form x as y| from x y| join x as y |join x y | from x) as y| from x) y
        $as_a=array();
        preg_match_all("@(?<=from)\s+\w+(\)|\s)+(as\s){0,1}[^(left)(right)(inner)(outer)(where)(join)(on)]+@i",$str,$c);
        if(count($c) && count($c[0])){
            $tmp_a=$c[0];
            foreach($tmp_a as $r){
                $r=preg_replace("@(\)|\s)+(as\s){0,1}@i","=",trim($r));
                $r=explode("=",$r);
                $as_a[$r[0]]=$r[1];
            }
        }
        preg_match_all("@(?<=join)\s+\w+(\)|\s)+(as\s){0,1}[^(left)(right)(inner)(outer)(where)(join)(on)]+@i",$str,$c);
        if(count($c) && count($c[0])){
            $tmp_a=$c[0];
            foreach($tmp_a as $r){
                $r=preg_replace("@(\)|\s)+(as\s){0,1}@i","=",trim($r));
                $r=explode("=",$r);
                $as_a[$r[0]]=$r[1];
            }
        }
        $rt=array();
        foreach($t_a as $k){
            $k=preg_replace("@\s+@i","",$k);
            if(isset($as_a[$k])){
                $rt[$k]=$as_a[$k];
            }else{
                $rt[$k]=$k;
            }
        }
        return $rt;
    }
    private function _pre_ListSqlSchema(){
        if(!$this->ListSql || $this->ListSql=="*"){
            $this->ListSql="*";
            $f_a=array("*"=>array("table"=>null,"as"=>null));
            $t_a[$this->name]=$this->name;
        }else{
           // $this->ListSql=str_replace("\n","",$this->ListSql);
            $this->ListSql=str_replace(array("\r\n", "\n", "\r"),'',$this->ListSql);
            $f_a=$this->_getSqlSelectTb($this->ListSql);
            $t_a=$this->_getSqlFromTb($this->ListSql);
        }

        $table_a=array();
        if($this instanceof ormDataTable){
            $table_a[$this->name]=$this;
        }else{
            if(!count($t_a)){
                throw new Exception('ListSql not defined Table');
            }else{
                $this->name=current(array_keys($t_a));
                $table_a[$this->name]=yo::t($this->name);
            }
        }

        //比较f-a中的table是否在$t_a中，否则异常
        $new_f=array();
        foreach($f_a as $k=>$r){
            if($r['is_spec']){
                $new_f[$k]=$r;continue;
            }
            if($r['table']){
                if(!in_array($r['table'],array_keys($t_a)) && !in_array($r['table'],array_values($t_a))){
                    throw new Exception("Unknow table ".$r['table']);
                }
                if(count($t_a)){
                    foreach($t_a as $y=>$z){
                        if($z==$r['table']){
                            $r['table_src']=$y;
                            break;
                        }
                    }
                }
            }else{
                //需要用别名
                if(count($t_a) && $t_a[$this->name]){
                    $r['table']=$t_a[$this->name];
                }else{
                    $r['table']=$this->name;
                }
                $r['table_src']=$this->name;
            }

            if($k=="*"){
                $tmp_t=$r['table_src']==$this->name?$table_a[$this->name]:yo::t($r['table_src']);
                //$table_a[$r['table_src']]=$tmp_t;
                if(!$tmp_t){
                    throw new Exception("Unknow tbale ".$r['table']);
                }
                $tmp_cols=array_keys($tmp_t->columns->toArray());

                $tmp_cols_ex=$tmp_t->ListExclude;
                if(!is_array($tmp_cols_ex)){
                    $tmp_cols_ex=explode(",",$tmp_cols_ex);
                }else{
                    $tmp_cols_ex=$tmp_cols_ex?:array();
                }
                $tmp_cols=array_diff($tmp_cols,$tmp_cols_ex);
                foreach($tmp_cols as $i=>$x){
                    if(!preg_match("@^MEDIUMBLOB|MEDIUMTEXT|LONGBLOB|LONGTEXT|TINYBLOB|TINYTEXT|BLOB|TEXT$@i",$x)){
                        $new_f[$x]=array("table"=>$r['table'],"table_src"=>$r['table_src']);
                    }
                }
            }else{
                $new_f[$k]=$r;
            }
        }
        //处理enum格式化
        $col_a=array();

        foreach($new_f as $k=>$f){
            $k=str_replace("`","",$k);
            if(count($this->ListEnum) && $this->ListEnum[$k]){
                $lst=$this->ListEnum[$k];
                if(!is_array($lst) || !count($lst)){
                    $col_a[$k]=$f;
                }else{
                    $sql_item="";
                    foreach($lst as $l_k=>$l_v){
                        $sql_item.=" when ".$f['table'].".$k='".$l_k."' then '".$l_v."'";
                    }
                    $sql_item="(case ".$sql_item." else '' end)";
                    $f['is_enum_value']=true;
                    $col_a[$k]=$f;
                    $col_a[$sql_item]=array("as"=>$k."_text",'is_enum_text'=>true,"enum_src"=>$k);
                }

            }else{
                $col_a[$k]=$f;
            }
        }
        $tb_a=array();
        foreach($t_a as $k=>$v){
            $tb_a[$k]=array("as"=>$v,"instance"=>$table_a[$k]);
        }
        return array($col_a,$tb_a);
    }
    public function getListSql(){//can override
        /*
            format前:
            (1)$ListSchema="*";
            (3)$ListSchema="a1,a3";//只取a表的特定列
            (4)$ListSchema="select a.a1,a.a2 from a left join b on a.x=b.y"; //自动分析出有表a\b

            formate后：
            select a.a1,a.a2,a.a3,b.col1,b.col2,c.col3 from a left join b on a.code=b.code left join c on a.code=c.code

            list自动排除MEDIUMBLOB|MEDIUMTEXT|LONGBLOB|LONGTEXT|TINYBLOB|TINYTEXT|BLOB|TEXT

        */

        list($col_a,$tb_a)=$this->_pre_ListSqlSchema();

        //重新设置flds
        $sql="";
        $sql_a=array();
        foreach($col_a as $k=>$f){

            $sql_a[]=($f['table']?$f['table'].".":"").$k.($f['as']?" ".$f['as']:"");
        }

        preg_match("@(?=\sfrom\s).*@i",$this->ListSql,$from_a);

        if(count($from_a)){
            $from= $from_a[0];
        }else{
            $from=" from ".$this->name;
        }
        $sql="select ".implode(",",$sql_a).$from;
        return $sql;
    }
    protected function _getListHeader(){//can override
        list($col_a,$tb_a)=$this->_pre_ListSqlSchema();

        $header_a=array();
        $table_a=array();
        foreach($tb_a as $k=>$v){
            if($table_a[$k]) continue;
            if($v['instance']){
                $table_a[$k]=$v['instance'];
            }else{
                $table_a[$k]=yo::t($k);
            }
        }

        foreach($col_a as $k=>$r){
            $r['as']=$r['as']?:$k;
            $k=str_replace("`","",$k);
            $r['as']=str_replace("`","",$r['as']);
            if($r['is_spec']){
                $tmp_col=new ormDataColumn($r['as']);
                $tmp_col->is_readonly=true;
                //todo:处理特殊的caption
            }elseif($r['is_enum_text']){
                $tmp_col=new ormDataColumn($r['as']);
                $tmp_col->is_readonly=true;
                //认为src列一定出现在前面
                $src_col_name=$col_a[$r['enum_src']]['as']?:$r['enum_src'];
                $tmp_col->caption=$header_a[$src_col_name]->caption;
            }else{
                $t=$table_a[$r['table_src']];
                $tmp_col=$t->columns[$k];
                if($r['as'] && $r['as']!=$tmp_col->name){
                    $tmp_col->caption=$r['as'];
                    $tmp_col->name=$r['as'];
                }
            }
            if($r['is_enum_value']){
                $tmp_col->is_hide=true;
            }
            if($r['is_enum_text']) $tmp_col->is_enum_text=true;
            if($r['is_enum_value']) $tmp_col->is_enum_value=true;
            $header_a[$r['as']]=$tmp_col;
        }
        return $header_a;
    }
}
class viewModelBase extends ormDataView{
    use modelBase;
    public $ListSql="*";//我要取全部列,要求：如果存在计算列，需要用（）并取别名
    public $ListExclude="";//我要排除这几个column
    public $ListEnum=array();//我要格式化这个enum-Field
    public $ListDisplayCols="";//我要在grid里只显示这些列，也可以数组，也可以字符串
    public $ListGroupBy="";
    public $ListOrderBy="";
    public $ListHaving="";

    //适用于报表，作为picker\report的super-class
    public function __construct($sql=null,$_dsn=null){
        parent::__construct(ormYo::Conn($_dsn));
        if($sql){
            $this->ListSql=$sql;
        }
    }
    public function getListData($condition,$page,$pageSize){
        $sql=$this->getListSql();
        $items= $this->groupBy($this->ListGroupBy)->having($this->ListHaving)->orderBy($this->ListOrderBy)->fill($condition,$page,$pageSize,$sql);
        return $items;
        //return $this->formatRows($items);
    }

    public function getListHeader(){
        $cols=$this->_getListHeader();
        return $this->formatColumns($cols);
    }
    protected function formatColumns($items){//override by sub class
        return $items;
    }

}
class tableModelBase extends ormDataTable{
    use modelBase;
    //=viewModel+actions+editModel
    public $ListSql="*";//我要取全部列,要求：如果存在计算列、函数列，需要用（）并取别名
    public $ListExclude="";//我要排除这几个column，指的是从数据库读取的列
    public $ListEnum=array();//我要格式化这个enum-Field
    public $ListDisplayCols="";//我要在grid里只显示这些列，也可以数组，也可以字符串
    public $ListGroupBy="";
    public $ListOrderBy="";
    public $ListHaving="";
    public function __construct($_name,$_dsn=null){
        if(!$_name) $_name=get_class($this);
        parent::__construct($_name,ormYo::Conn($_dsn));
        $this->setChildren();
    }
    public function setChildren(){//can override

    }

    public function getListData($condition,$page,$pageSize){
        $sql=$this->getListSql();
        if($this->ListOrderBy){
            $this->orderBy($this->ListOrderBy);
        }
        if($this->ListGroupBy){
            $this->groupBy($this->ListGroupBy);
        }
        if($this->ListHaving) {
            $this->having($this->ListHaving);
        }
        $items=$this->fill($condition,$page,$pageSize,$sql);
        return $items;
        //$items = $this->groupBy($this->ListGroupBy)->having($this->ListHaving)->orderBy($this->ListOrderBy)->fill($condition,$page,$pageSize,$sql);
        //return $this->formatRows($items);
    }

    public function getListHeader(){
        $cols=$this->_getListHeader();
        if($this->join_attach_cols){
            foreach($this->join_attach_cols as $k=>$col){
                $cols[$k]=$col;
            }
        }
        return $this->formatColumns($cols);
    }
    public function getListHeaderToShow(){
        $cols=$this->getListHeader();

        $display_cols=$this->ListDisplayCols;//model设置的显示列
        if($display_cols){
            if(!is_array($display_cols)){
                $display_cols=explode(",",$display_cols);
            }
        }
        $show_cols=array();
        if($display_cols){ //先满足定义的显示列和顺序
            foreach($display_cols as $k){
                foreach($cols as $col){
                    if($col->name==$k){
                        $show_cols[]=$col;
                    }
                }
            }
        }else{
            foreach($cols as $col){
                if($col->is_autoid) continue;
                if($col->is_enum_value) continue;
                $show_cols[]=$col;
            }
        }
        return $show_cols;
    }
    private  $join_rows=array();
    private  $join_cols=array();
    private $join_on="";
    private $join_by="";
    /*
     * 主要处理join表
     * */
    function __call($name,$args){
        if(function_exists($this,$name)){
            return $this->$name($args);
        }
        if(count($args) && !$args[0]){
            return $this;
        }
        switch($name){
            case "joinRows": //
                $this->join_rows=$args[0];
                return $this;
            case "joinOn":
                $this->join_on=$args[0];
                return $this;
            case "joinBy":
                $this->join_by=$args[0];
                return $this;
            case "joinCols":
                $this->join_cols=$args[0];
                return $this;
            default:
              return  parent::__call($name,$args);
        }
    }
    private $join_attach_cols=array();
    public function join($join_table_name){
        if(!$join_table_name){
            return $this->join_rows;
        }
        if(!$this->join_rows) return null;
        if(!$this->join_cols) return $this->join_rows;
        if(!$this->join_on) return $this->join_rows;
        if(!$this->join_by) return $this->join_rows;
        $m=yo::t($join_table_name);
        $attach_cols=$m->getListHeader();
        $col_arr=explode(",",$this->join_cols);
        foreach($col_arr as $v){
            if($attach_cols[$v]){
                $this->join_attach_cols[$v]=$attach_cols[$v];
            }
        }

        //获取join的值
        $ids=array();
        foreach($this->join_rows as $row){
           // if($row[$this->join_by]) return $this->join_rows;//说明错误的join字段
            if($row[$this->join_by]){
                $ids[]=$row[$this->join_by];
            }

        }
        if(!count($ids)){
            return $this->join_rows;
        }
        $ids=implode("','",$ids);
        $where= $this->join_on." in ('".$ids."')";
        $items=$m->getListData($where);
        $old_rows=$this->join_rows;
        foreach($old_rows as $k=>$r){
            foreach($col_arr as $c){
                $old_rows[$k][$c]=null;
                foreach($items as $item){
                    if($item[$this->join_on]==$r[$this->join_by]){
                        $old_rows[$k][$c]=$item[$c];
                        break;
                    }
                }

            }
        }
        return $old_rows;
    }
    protected function formatRows($items){//override by sub class
        return $items;
    }
    protected function formatColumns($items){//override by sub class
        return $items;
    }
}

class yo extends ormYo{
    static function t($model){//获取一个table的实例
        static $_cache = array();
        if (!is_null($model) && isset($_cache[$model])) return $_cache[$model];
        $file_name = _DATA_MODEL_.'/'.$model.'.model.php';
        $class_name = $model.'Model';
        if (!file_exists($file_name)){
            return $_cache[$model] = new tableModelBase($model);  //new Model($model);
        }else{
            require_once($file_name);
            if (!class_exists($class_name)){
                $error = 'Model Error:  Class '.$class_name.' is not exists!';
                throw new Exception($error);
            }else{
                return $_cache[$model] = new $class_name();
            }
        }
    }
    static function v($m){//获取一个view的实例
        if(preg_match("@^[a-zA-Z][a-zA-Z0-9_]*$@i",$m)){//必须符合字段名命名规
            if(class_exists($m,false)){
                return new $m();
            }else{
                $m=$GLOBALS['config']['tablepre'].$m;
                $m="select * from $m";
            }
        }
        $v=new viewModelBase();
        $v->ListSql=$m;
        return $v;
    }
    static function r($_dsn){//获取一个reader
        return new ormReader(ormYo::Conn($_dsn));
    }
    static function findOne($sql,$_dsn){
        $r=self::r($_dsn);
        return $r->getOne($sql);
    }
    static function findRow($sql,$_dsn){
        $r=self::r($_dsn);
        return $r->getRow($sql);
    }
    static function findRows($sql,$_dsn){
        $r=self::r($_dsn);
        return $r->getRows($sql);
    }

}

