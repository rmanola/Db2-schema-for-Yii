<?php
 /**
 * CIbmTableSchema class file represents the metadata for a DB2 table.
 *
 * @author Renan Manola <rmanola@gmail.com>
 * @link https://github.com/rmanola/Db2-schema-for-Yii
 * @copyright Copyright &copy; 2008-2012 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
class CIbmTableSchema extends CDbTableSchema
{
	/**
	 * @var string name of the schema that this table belongs to.
	 */
	public $schemaName;
}
