<?php
/* PGI CLASS */
class pgi {
	
	//public $affected_rows = 0;
	public $client_info = '';
	public $client_version = 0;
	public $connect_errno = 0;
	public $connect_error;
	public $errno = 0;
	public $error = ""; //* text of last error, set manually or automatically
	public $error_list = Array();
	public $field_count = 0;
	public $host_info = '';
	public $info;
	public $insert_id = 0; //* last id of insert sentance
	public $server_info = '';
	public $server_version = '';
	public $stat = '';
	public $sqlstate = '';
	public $protocol_version = '';
	public $thread_id = '';
	public $warning_count = '';
	public $used_names = Array();
	private $indx = 0;
	
	public $conection;
	
	function __construct($host, $username, $password, $database) {
		//print "In constructor\n";
		$this->conection = pg_connect("host=$host dbname=$database user=$username password=$password options='--client_encoding=UTF8'");
		if($this->conection === false) $this->connect_errno = 1; //mark connection unsuccesfull
		
		//return $this;
	}
	
	public function set_charset($string){
		return true; //stay empty 
	}
	
	//set last inserted id
	public function set_inserted($idi){
		$this->insert_id = (int) $idi;
	}
	
	//set error string
	public function set_error($typ = "def"){
		$this->error = $typ === "def" ? pg_last_error($this->conection) : $typ;
	}
	
	
	//before prepare statement, generate unique name
	private function namestring($ii){
		return "pgStmt".$ii;
	}
	
	private function makeNewName(){
		$ii = $this->indx;
		$is_found = false;
		while(!$is_found){
			if(in_array($this->namestring($ii), $this->used_names)) $ii++;
			else $is_found = true;
		}
		$this->used_names[] = $this->namestring($ii);
		$this->indx = $ii +1;
		return $this->namestring($ii);
	}
	
	//prepare statement
	public function prepare($string){
		$b = new pgi_stmt($this->makeNewName(), trim($string), $this);
		return $b->error < 1 ? $b : false; //return false if error occured
	}
	
	public function closeStmt($name){
		$key = array_search($name, $this->used_names);
		if($key !== false) unset($this->used_names[$key]);
	}
	
	//escape illegal characters 
	public function real_escape_string($txt){
		return pg_escape_literal($txt); // The default connection is the last connection made by pg_connect() or pg_pconnect(). When there is no default connection, it raises E_WARNING and returns FALSE
	}
	
	/* !!! */
	//TODO - DO WE IMPLEMENT IT AUTO?
	public function check_connection(){
		return pg_connection_status($this->conection) === PGSQL_CONNECTION_OK;
	}
	
	public function reset_connection(){
		return pg_connection_reset($this->conection);
	}
}

class pgi_stmt {
	
	public $affected_rows = 0; //*
	//public $insert_id = 0; //important
	public $num_rows = 0; //*
	public $errno = 0; //important
	public $error; //*
	public $error_list = Array();
	public $sqlstate = '';
	public $id = 0;
	public $stmt_name = ''; //* unique string identificator of the statement - since all of statements use the same connection, this is needed to separate between them
	
	private $param_count = 0; //* number of parameters binded in prepare statement
	private $field_count = 0; //important
	private $row_id = 0; //* index of current row to fetch
	private $bind_list = Array(); //* all binded parameters in bind_param statement
	private $results; //* resource for results
	private $parentClass; //* resource of pgi class
	private $sql_type = ''; //* frist word of sql sentance - type of sentance
	private $buffer = array(); //* buffer for prefetched results
	private $buffered = false; //* buffer boolean
	private $final_binds = array(); //* array containing pointers to variables, which will hold values
	private $affected_types = array("update", "replace", "delete"); //* sql statements, that use affected rows param
	private $binded_check = false; //* boolean if number of binded variables is the same as number of result columns
	private $col_types = array(); //* types of columns
	//private $namespace;
	
	function __construct($name, $string, $conection) {
		
		//save variables
		$this->parentClass = $conection;
		$this->stmt_name = $name;
		$this->sql_type = strtolower(substr($string, 0, strpos($string, " ")));
		
		//prepare string
		$final_string = str_ireplace("rand()", " random()", $string);
		$nrs = substr_count($string, '?');
		for($j=0; $j<$nrs; $j++){ //rewrite mysqli ? to pg $1
			$final_string = substr($final_string, 0, strpos($final_string, "?")) ."$". ($j + 1) . substr($final_string, strpos($final_string, "?") +1);
		}
		
		
		if($this->sql_type === "insert") $final_string .= " RETURNING id"; //postgre speciality, RETURNING clause is the safest for getting "auto increment" value - IMPORTANT: asuming that id column is always named "id"!
		
	
		//sql prepare
		if(pg_prepare($this->parentClass->conection, $this->stmt_name, $final_string)) $this->param_count = $nrs;
		else {
			$this->parentClass->set_error();
			$this->error = 1;
		}
	}
	
	//DESTRUCTOR
	public function __destruct(){ $this->close(); }
	
	//bind_params to prepared statement - postgresql lacks this function, so this only stores variables
	//notice: variables aren't passed by refference (otherwise binded variable in brackets fails ->bind_param('i', (10)))
	public function bind_param($var1, ...$params){
		
		//check for validity
		$err = "";
		$this->bind_list = array();
		if($this->results) $this->free_result();
		
		if(strlen($var1) !== count($params)) $err = "Number of variables doesn't match declared variables in bind_statement";
		else if(count($params) !== $this->param_count) $err = "Number of variables doesn't match variables in prepared statement";
		else if(preg_match("/^[isdb]+$/", $var1) < 1) $err = "Prepared statement contains illegal characters";
		
		if($err == ""){
			$casts = str_split($var1);
			for($j=0; $j<count($casts); $j++){ //based on parameter type, reconvert data (later it's converted to string, but here we already replace it)
				switch($casts[$j]){
					case "b":
					case "s":
						$this->bind_list[] = (string) $params[$j];
						break;
					case "i":
						$this->bind_list[] = (int) $params[$j];
					break;
					case "d":
						$this->bind_list[] = (float) $params[$j];
					break;
				}
			}
			return true;
		}
		else {
			$this->parentClass->set_error($err);
			return false;
			//throw new Exception($err);
		}
		
	}
	
	//execute prepared statement. Works binded and unbinded statements
	public function execute(){
		
		$this->results = pg_execute($this->parentClass->conection, $this->stmt_name, $this->bind_list);
		//TODO: pg_result_status() return status of results -> check for errors
		
		//affected_rows, insert_id
		if($this->sql_type === "insert"){
			
			//save last inserted id
			$lastval = pg_fetch_row($this->results, 0, PGSQL_NUM)[0];
			$this->parentClass->set_inserted($lastval);
		}
		else if(in_array($this->sql_type, $this->affected_types)){
			//save affected_rows
			$this->affected_rows = pg_affected_rows($this->results);
		}
		
		if(!$this->results) $this->parentClass->set_error();
		
		return $this->results; //check if works!!!!
	}
	
	//original function stores data into buffer. This is upgrade and doesn't write buffer, if results number is to big
	public function store_result($with_buffer = false){

		if($this->results){ //only works for succesfully prepared statements
			
			if($this->sql_type === "select"){ //for select save num_rows and save to buffer if not too big
				
				$this->num_rows = pg_num_rows($this->results);
				
				if($this->num_rows < 0) return false; //error has occured
				else if($this->num_rows < 60000 && $with_buffer){ //store buffer
					
					$this->buffered = true;
					$this->buffer = array();
					for($i=0; $i<$this->num_rows; $i++){
						$this->buffer[$i] = pg_fetch_row($this->results, $i, PGSQL_NUM);
					}
					return true;
				} else return true;
				
			} else return false;
		} else return false;
	}
	
	//bind results to set variables
	public function bind_result( &...$i){
		if($this->results && $this->sql_type === "select"){ //only works for succesfully prepared SELECT statements
			
			//get col_types
			$this->col_types = array();
			for($jj=0; $jj<pg_num_fields($this->results); $jj++){ //for all fields in result, save data type
				$this->col_types[$jj] = pg_field_type($this->results, $jj);
			}
			
			if(count($this->col_types) !== count($i)){
				$this->parentClass->set_error("There was an error fetching col_types");
				return false;
			}
			else {
				$this->final_binds = $i;
				return true;
			}
		} else return false;
	}
	
	//bind results to set variables - asocciative way
	public function bind_asocciative_result(&$row_name){
		if($this->results && $this->sql_type === "select"){ //only works for succesfully prepared SELECT statements
			
			foreach($this->result_metadata() as $col_name){ //fetch column names and bind them to asocciative array
				$asoc_params[] = &$row_name[$col_name]; 
			}
			call_user_func_array(array($this, 'bind_result'), $asoc_params);
			
		} else return false;
	}
	
	//save current row, to specified variables
	public function fetch(){
		if($this->results && $this->sql_type === "select"){ //only works for succesfully prepared SELECT statements
			if($this->row_id >= $this->num_rows) {
				$this->free_result();
				return false; //end of fetching
			}
			else {
				
				if($this->buffered){  //transfer buffered results
					if(!isset($this->buffer[$this->row_id])) { $this->free_result(); return false; } //dbl check that buffer exists
					else if(!$this->compareBindedNrs(count($this->buffer[$this->row_id]))){ //prevent process if number of binded doesn't match
						
						$this->parentClass->set_error("Number of binded parameters doesn't match with number of selected columns");
						$this->free_result();
						return false;
					}
					else {
						for($j=0; $j<count($this->final_binds); $j++){
							$this->final_binds[$j] = $this->correct_type($this->buffer[$this->row_id][$j], $this->col_types[$j]);
						}
					}
				}
				else { //fetch results
					
					$only_this_row = pg_fetch_array($this->results, $this->row_id, PGSQL_NUM);
					if(!$this->compareBindedNrs(count($only_this_row))) { //prevent process if number of binded doesn't match
						
						$this->parentClass->set_error("Number of binded parameters doesn't match with number of selected columns");
						$this->free_result();
						return false;
					}
					for($j=0; $j<count($this->final_binds); $j++){
						$this->final_binds[$j] = $this->correct_type($only_this_row[$j], $this->col_types[$j]);
					}
				}
				
				$this->row_id++; //increase row_id
				return true;
			}
		} else return false;
	}
	
	//if not checked, check for binded numbers
	private function compareBindedNrs($array_vals){
		if(!$this->binded_check){
			if(count($this->final_binds) === $array_vals){
				$this->binded_check = true;
				return true;
			}
			else return false;
		} else return true;
	}
	
	//transform data types to correct
	private function correct_type($val, $type){
		$ret;
		switch($type){
			case "numeric":
			case "int2":
			case "int4":
			case "int8":
			case "smallint":
			case "bigint":
			case "bigserial":
				$ret = $val !== null ? (int) $val : 0;
				break;
			case "real":
			case "double precision":
			case "decimal":
			case "float4":
			case "float8":
				$ret = $val !== null ? (float) $val : 0;
				break;
			default: 
				$ret = $val !== null ? $val : "";
		}
		return $ret;
	}
	
	//for returning associative array
	public function result_metadata(){
		$return = array();
		if($this->results && $this->sql_type === "select"){ //only works for succesfully prepared SELECT statements
			for($jj=0; $jj<pg_num_fields($this->results); $jj++){ //for all fields in result, save column name
				$return[] = pg_field_name($this->results, $jj);
			}
		}
		return $return;
	}
	
	//clear all results and binds
	public function free_result(){
		$this->final_binds = array();
		$this->buffer = array();
		$this->buffered = false;
		$this->row_id = 0;
		//$this->num_rows = 0;
		$this->binded_check = false;
		if(is_resource($this->results)) pg_free_result($this->results);
	}
	
	//close class instance
	public function close(){
		$this->free_result();
		if($this->parentClass !== null) {
			pg_query($this->parentClass->conection, 'DEALLOCATE "'. $this->stmt_name .'"'); //prevent duplicated closing
			$this->parentClass->closeStmt($this->stmt_name);
		}
		$this->results = null;
		$this->parentClass = null;
	}
}
?>