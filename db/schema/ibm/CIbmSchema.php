<?php
/**
 * Db2-schema-for-Yii Notes:
 *   This file was a copy-paste from the PgsqlChema with modifications,
 *   therefore, lots of functions need to be rewrited specially the ones
 *   with the string "TODO" on the beginning.
 */

/**
 * CIbmSchema class file.
 *
 * @author Renan Manola <rmanola@gmail.com>
 * @link https://github.com/rmanola/Db2-schema-for-Yii
 * @copyright Copyright &copy; 2008-2012 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CIbmSchema is the class for retrieving metadata information from a DB2 database.
 *
 * @author Renan Manola <rmanola@gmail.com>
 */
class CIbmSchema extends CDbSchema
{
	const DEFAULT_SCHEMA='public';

	/**
	 * @var array the abstract column types mapped to physical column types.
	 * @since 1.1.6
	 */
	 // TODO !!
    public $columnTypes=array(
        'pk' => 'serial NOT NULL PRIMARY KEY',
        'string' => 'character varying (255)',
        'text' => 'text',
        'integer' => 'integer',
        'float' => 'double precision',
        'decimal' => 'numeric',
        'datetime' => 'time',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'date' => 'date',
        'binary' => 'bytea',
        'boolean' => 'boolean',
		'money' => 'decimal(19,4)',
    );

	private $_sequences=array();

	/**
	 * TODO - This is most likely to dissapear.
	 * 
	 * Quotes a table name for use in a query.
	 * A simple table name does not schema prefix.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 * @since 1.1.6
	 */
	public function quoteSimpleTableName($name)
	{
		return '"'.$name.'"';
	}

	/**
	 * TODO !
	 * 
	 * Resets the sequence value of a table's primary key.
	 * The sequence will be reset such that the primary key of the next new row inserted
	 * will have the specified value or 1.
	 * @param CDbTableSchema $table the table schema whose primary key sequence will be reset
	 * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
	 * the next new row's primary key will have a value 1.
	 * @since 1.1
	 */
	public function resetSequence($table,$value=null)
	{
		if($table->sequenceName!==null)
		{
			$seq='"'.$table->sequenceName.'"';
			if(strpos($seq,'.')!==false)
				$seq=str_replace('.','"."',$seq);
			if($value===null)
				$value="(SELECT COALESCE(MAX(\"{$table->primaryKey}\"),0) FROM {$table->rawName}) + 1";
			else
				$value=(int)$value;
			$this->getDbConnection()->createCommand("SELECT SETVAL('$seq', $value, false)")->execute();
		}
	}

	/**
	 * TODO !
	 * 
	 * Enables or disables integrity check.
	 * @param boolean $check whether to turn on or off the integrity check.
	 * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
	 * @since 1.1
	 */
	public function checkIntegrity($check=true,$schema='')
	{
		$enable=$check ? 'ENABLE' : 'DISABLE';
		$tableNames=$this->getTableNames($schema);
		$db=$this->getDbConnection();
		foreach($tableNames as $tableName)
		{
			$tableName='"'.$tableName.'"';
			if(strpos($tableName,'.')!==false)
				$tableName=str_replace('.','"."',$tableName);
			$db->createCommand("ALTER TABLE $tableName $enable TRIGGER ALL")->execute();
		}
	}

	/**
	 * TODO 
	 * 
	 * Loads the metadata for the specified table.
	 * @param string $name table name
	 * @return CDbTableSchema driver dependent table metadata.
	 */
	protected function loadTable($name)
	{
		$table=new CIbmTableSchema;
		$this->resolveTableNames($table,$name);
		if(!$this->findColumns($table))
			return null;
		$this->findConstraints($table);

		if(is_string($table->primaryKey) && isset($this->_sequences[$table->rawName.'.'.$table->primaryKey]))
			$table->sequenceName=$this->_sequences[$table->rawName.'.'.$table->primaryKey];
		else if(is_array($table->primaryKey))
		{
			foreach($table->primaryKey as $pk)
			{
				if(isset($this->_sequences[$table->rawName.'.'.$pk]))
				{
					$table->sequenceName=$this->_sequences[$table->rawName.'.'.$pk];
					break;
				}
			}
		}

		return $table;
	}

	/**
	 * DONE !
	 * Popula os campos name e schemaName do objeto Table que é CibmTableSchema
	 * 
	 * Generates various kinds of table names.
	 * @param CPgsqlTableSchema $table the table instance
	 * @param string $name the unquoted table name
	 */
	protected function resolveTableNames($table,$name)
	{
		//$this->debuga($table->schemaName." - ".$name);
		$parts=explode('.',str_replace('"','',$name));
		if(isset($parts[1]))
		{
			$schemaName=$parts[0];
			$tableName=$parts[1];
			//$this->debuga($schemaName." - ".$tableName);
		}
		else
		{
			$schemaName=self::DEFAULT_SCHEMA;
			$tableName=$parts[0];
		}

		$table->name=$tableName;
		$table->schemaName=$schemaName;
		if($schemaName===self::DEFAULT_SCHEMA)
			$table->rawName=$tableName;
		else
			$table->rawName=$schemaName.'.'.$tableName;
			
		//$this->debuga("Nomes da tabela sao: ".$table->rawName.", ".$table->name.", ".$table->schemaName);
	}

	/**
	 * DONE
	 * 
	 * Collects the table column metadata.
	 * @param CPgsqlTableSchema $table the table metadata
	 * @return boolean whether the table exists in the database
	 */
	protected function findColumns($table)
	{
		$sql="SELECT COLNAME, TYPENAME, NULLS, DEFAULT".
			" FROM syscat.columns WHERE tabname='".$table->name."'";

		$command=$this->getDbConnection()->createCommand($sql);

		if(($columns=$command->queryAll())===array()) {
			//$this->debuga("C");
			return false;
		}

	//	$this->debuga("B");
		foreach($columns as $column)
		{
			$column2["attname"] = $column["COLNAME"];
			$column2["type"] = $column["TYPENAME"];
			$column2["attnotnull"] = $column["NULLS"];
			if($column["NULLS"] == "Y")
				$column2["attnotnull"] = "";
			else
				$column2["attnotnull"] = 1;		
			$column2["adsrc"] = $column["DEFAULT"];
			if($column["DEFAULT"])
				$column2["atthasdef"] = 1;
			else
				$column2["atthasdef"] = "";
		
			$c=$this->createColumn($column2);
			$table->columns[$c->name]=$c;

			if(stripos($column2['adsrc'],'nextval')===0 && preg_match('/nextval\([^\']*\'([^\']+)\'[^\)]*\)/i',$column2['adsrc'],$matches))
			{
				if(strpos($matches[1],'.')!==false || $table->schemaName===self::DEFAULT_SCHEMA)
					$this->_sequences[$table->rawName.'.'.$c->name]=$matches[1];
				else
					$this->_sequences[$table->rawName.'.'.$c->name]=$table->schemaName.'.'.$matches[1];
				$c->autoIncrement=true;
			}
		}
		return true;
	}

	/**
	 * TODO !!
	 * 
	 * Creates a table column.
	 * @param array $column column metadata
	 * @return CDbColumnSchema normalized column metadata
	 */
	protected function createColumn($column)
	{
		$c=new CPgsqlColumnSchema;
		$c->name=$column['attname'];
		$c->rawName=$this->quoteColumnName($c->name);
		$c->allowNull=!$column['attnotnull'];
		$c->isPrimaryKey=false;
		$c->isForeignKey=false;

		$c->init($column['type'],$column['atthasdef'] ? $column['adsrc'] : null);

		return $c;
	}

	/**
	 * !! TODO !!
	 * Collects the primary and foreign key column details for the given table.
	 * @param CPgsqlTableSchema $table the table metadata
	 */
	protected function findConstraints($table)
	{
		//TODO
	}

	/**
	 * TODO !!
	 * 
	 * Collects primary key information.
	 * @param CPgsqlTableSchema $table the table metadata
	 * @param string $indices pgsql primary key index list
	 */
	protected function findPrimaryKey($table,$indices)
	{
		$indices=implode(', ',preg_split('/\s+/',$indices));
		$sql=<<<EOD
SELECT attnum, attname FROM pg_catalog.pg_attribute WHERE
	attrelid=(
		SELECT oid FROM pg_catalog.pg_class WHERE relname=:table AND relnamespace=(
			SELECT oid FROM pg_catalog.pg_namespace WHERE nspname=:schema
		)
	)
    AND attnum IN ({$indices})
EOD;
		$command=$this->getDbConnection()->createCommand($sql);
		$command->bindValue(':table',$table->name);
		$command->bindValue(':schema',$table->schemaName);
		foreach($command->queryAll() as $row)
		{
			$name=$row['attname'];
			if(isset($table->columns[$name]))
			{
				$table->columns[$name]->isPrimaryKey=true;
				if($table->primaryKey===null)
					$table->primaryKey=$name;
				else if(is_string($table->primaryKey))
					$table->primaryKey=array($table->primaryKey,$name);
				else
					$table->primaryKey[]=$name;
			}
		}
	}

	/**
	 * TODO !!
	 * 
	 * Collects foreign key information.
	 * @param CPgsqlTableSchema $table the table metadata
	 * @param string $src pgsql foreign key definition
	 */
	protected function findForeignKey($table,$src)
	{
		$matches=array();
		$brackets='\(([^\)]+)\)';
		$pattern="/FOREIGN\s+KEY\s+{$brackets}\s+REFERENCES\s+([^\(]+){$brackets}/i";
		if(preg_match($pattern,str_replace('"','',$src),$matches))
		{
			$keys=preg_split('/,\s+/', $matches[1]);
			$tableName=$matches[2];
			$fkeys=preg_split('/,\s+/', $matches[3]);
			foreach($keys as $i=>$key)
			{
				$table->foreignKeys[$key]=array($tableName,$fkeys[$i]);
				if(isset($table->columns[$key]))
					$table->columns[$key]->isForeignKey=true;
			}
		}
	}

	/**
	 * DONE !
	 * 
	 * Returns all table names in the database.
	 * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
	 * If not empty, the returned table names will be prefixed with the schema name.
	 * @return array all table names in the database.
	 */
	protected function findTableNames($schema='')
	{
		if($schema==='')
			$schema=self::DEFAULT_SCHEMA;
		$sql=<<<EOD
SELECT table_name, table_schema FROM information_schema.tables
WHERE table_schema=:schema AND table_type='BASE TABLE'
EOD;
		$command=$this->getDbConnection()->createCommand($sql);
		$command->bindParam(':schema',$schema);
		$rows=$command->queryAll();
		$names=array();
		foreach($rows as $row)
		{
			if($schema===self::DEFAULT_SCHEMA)
				$names[]=$row['table_name'];
			else
				$names[]=$row['table_schema'].'.'.$row['table_name'];
		}
		return $names;
	}

	/**
	 * TODO !
	 * 
	 * Builds a SQL statement for renaming a DB table.
	 * @param string $table the table to be renamed. The name will be properly quoted by the method.
	 * @param string $newName the new table name. The name will be properly quoted by the method.
	 * @return string the SQL statement for renaming a DB table.
	 * @since 1.1.6
	 */
	public function renameTable($table, $newName)
	{
		return 'ALTER TABLE ' . $this->quoteTableName($table) . ' RENAME TO ' . $this->quoteTableName($newName);
	}

	/**
	 * TODO !
	 * 
	 * Builds a SQL statement for adding a new DB column.
	 * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
	 * @param string $column the name of the new column. The name will be properly quoted by the method.
	 * @param string $type the column type. The {@link getColumnType} method will be invoked to convert abstract column type (if any)
	 * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
	 * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
	 * @return string the SQL statement for adding a new column.
	 * @since 1.1.6
	 */
	public function addColumn($table, $column, $type)
	{
		$type=$this->getColumnType($type);
		$sql='ALTER TABLE ' . $this->quoteTableName($table)
			. ' ADD COLUMN ' . $this->quoteColumnName($column) . ' '
			. $this->getColumnType($type);
		return $sql;
	}

	/**
	 * TODO !
	 * 
	 * Builds a SQL statement for changing the definition of a column.
	 * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
	 * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
	 * @param string $type the new column type. The {@link getColumnType} method will be invoked to convert abstract column type (if any)
	 * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
	 * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
	 * @return string the SQL statement for changing the definition of a column.
	 * @since 1.1.6
	 */
	public function alterColumn($table, $column, $type)
	{
		$type=$this->getColumnType($type);
		$sql='ALTER TABLE ' . $this->quoteTableName($table) . ' ALTER COLUMN '
			. $this->quoteColumnName($column) . ' TYPE ' . $this->getColumnType($type);
		return $sql;
	}

	/**
	 * TODO !
	 * 
	 * Builds a SQL statement for dropping an index.
	 * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
	 * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping an index.
	 * @since 1.1.6
	 */
	public function dropIndex($name, $table)
	{
		return 'DROP INDEX '.$this->quoteTableName($name);
	}
	
	
	/*
	 * Função simples de debug
	 */
	public function debuga($opt = "") {
			$fd = fopen("/tmp/nadica2.txt","a");
			
			fwrite($fd,"\nENTROU\n");
			if ($opt)
				fwrite($fd, $opt."\n");
				
			fclose($fd);	
	}
}
