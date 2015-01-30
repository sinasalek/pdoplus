<?php
namespace pdoplus;

/**
 * DB - MySQL database library
 *
 * @author Sina Salek
 *
 */
class PDO extends \sorskod\database\DB
{

    /**
     * <code>
     * $query="
     *        SELECT
     *            O.*,
     *            SUM( {orders.finalTotal} ) as 'finalTotal',
     *            {customers.companyTitle},
     *            (SELECT SUM({receipts.amount}) FROM {receipts} WHERE {receipts.orderNumber}={orders.id}) as 'receipt'
     *        FROM {orders} as O
     *            INNER JOIN {customers} as C ON {customers.id}={orders.customer}
     *        $whereClause [fdsafa]
     *        GROUP BY {orders.orderNumber}
     *        [x]
     *    ";
     *    echo $query=Database::parseQuery($query,array(
     *        'tablesAlias'=>array(
     *            'orders'=>'O',
     *            'customers'=>'C'
     *        ),
     *        'parameters'=>array(
     *            'x'=>5,
     *            'fdsafa'=>90
     *        )
     *    ));
     * </code>
     * Result :
     * <code>
     * SELECT
     *    O.*,
     *    SUM( O.`final_total` ) as 'finalTotal',
     *    C.`company_title`,
     *    (SELECT SUM(`zdbd2_receipts`.`amount`)
     *        FROM `zdbd2_receipts`
     *        WHERE `zdbd2_receipts`.`orderNumber`=O.`id`
     *    ) as 'receipt'
     *    FROM `zdbd2_orders` as O
     *        INNER JOIN `zdbd2_customers` as C ON C.`id`=O.`customer`
     *    WHERE C.currency = '1111111121' AND O.paid = 1 90
     *    GROUP BY O.`order_number` 5
     *
     *
     * @param $query
     * @param array $options
     * @return mixed
     */
    public function parseQuery($query, $options = array()) {
        $tablesInfo =& $this->tablesInfo;

        if (preg_match_all('/\{([^"\'.{}]+)(\.([^"\'.{}]+))?\}/sim', $query, $regs, PREG_SET_ORDER)) {
            foreach ($regs as $reg) {
                $name = '';
                if (isset($reg[1])) { //table name
                    if (isset($tablesInfo[$reg[1]])) {
                        $name = '`' . $tablesInfo[$reg[1]]['tableName'] . '`';
                    }
                }
                if (isset($reg[1]) and isset($reg[3]) and isset($options['tablesAlias'][$reg[1]])) { //table name replace with alias
                    $name = $options['tablesAlias'][$reg[1]];
                }
                if (isset($reg[3])) {//field name
                    if (isset($tablesInfo[$reg[1]]['columns'][$reg[3]])) {
                        $name = $name . '.' . '`' . $tablesInfo[$reg[1]]['columns'][$reg[3]] . '`';
                    }
                }
                if (!empty($name)) {
                    $query = str_replace($reg[0], $name, $query);
                }
            }
        }

        if (isset($options['parameters'])) {
            if (preg_match_all('/(\[([^[\]\'"]+)\])/sim', $query, $regs, PREG_SET_ORDER)) {
                foreach ($regs as $reg) {
                    $name = '';
                    if (isset($options['parameters'][$reg[2]])) { //parameter value
                        $name = $options['parameters'][$reg[2]];
                    }
                    if (!empty($name) || $name == 0) {
                        $query = str_replace($reg[0], $name, $query);
                    }
                }

            }
        }
        return $query;
    }

    /**
     * @desc
     * @previousNames db_get_row_number
     * @todo SET @row = 0;
     *        SELECT @row := @row + 1 AS row_number FROM `photos` WHERE `gallery_id`='6'   ORDER BY `order_number`,id DESC
     * @param $sqlQuery
     * @param $keyColumnName
     * @param $keyColumnValue
     * @return bool
     */
    public function getRowNumber($sqlQuery, $keyColumnName, $keyColumnValue) {
        $sqlQuery = preg_replace('/(.*)( +\* +)(.*)/si', '$1 \'__uuuuuuuuuuuuuuuuu_\' $3', $sqlQuery);
        $sqlQuery = preg_replace('/^(SELECT)(.*)(FROM)(.*)/si', "SELECT `row_position_90901000` FROM (\$1 @rownum:=@rownum+1 `row_position_90901000` , `$keyColumnName` , \$2 $3 (SELECT @rownum:=0) r, $4) AS subquery \r\nWHERE `$keyColumnName` = '$keyColumnValue'\r\nLIMIT 1;", $sqlQuery);
        $rowNumber = $this->getColumnValueCustom($sqlQuery, 'row_position_90901000');
        return $rowNumber;
    }


    /**
     * @param $sqlQuery
     * @param $rowNumber
     * @return array|bool
     */
    public function getRowByRowNumber($sqlQuery, $rowNumber) {
        $rowNumber = $rowNumber - 1;
        $sqlQuery .= " LIMIT {$rowNumber},1 ";
        return $this->loadCustom($sqlQuery);
    }


    /**
     * @desc
     * @example
     * <code>
     *    $this->getLimitedQuery('SELECT * FROM table',1,3)
     *    -->"SELECT * FROM table LIMIT 1,3"
     * </code>
     * @previousNames get_limited_query
     *
     * @param $sqlQuery
     * @param $from
     * @param $length
     * @return mixed|string
     */
    public function getLimitedQuery($sqlQuery, $from, $length) {
        //$sqlQuery=strtolower($sqlQuery);
        //	if (strpos($sqlQuery,'limit')>-1) {

        //	$sqlQuery=substr_replace($sqlQuery,'limit',strpos($sqlQuery,'limit')-strlen('limit'),strlen($sqlQuery));
        //	}
        $sqlQuery = preg_replace('/LIMIT[1-9, ]*$/si', '', $sqlQuery);
        $sqlQuery .= " LIMIT $from,$length";
        return $sqlQuery;
    }

    /**
     * optimize select query on large tables (table should have at least one indexed column)
     *
     * @param $sqlQuery
     * @param $indexColumnName
     * @param $limitFrom
     * @param $limitLength
     * @return mixed|string
     */
    public function getLimitedQueryOptimized($sqlQuery, $indexColumnName, $limitFrom, $limitLength) {
        if ($limitFrom < 1000) {
            //$sqlQueryL = $sqlQuery;
            //$sqlQueryL = preg_replace('/(SELECT)( .* )(FROM .*)/i', '$1 ' . $indexColumnName . ' $3', $sqlQueryL);
            $sqlQueryL = preg_replace('/LIMIT[1-9, ]*$/si', '', $sqlQuery);
            $sqlQueryL .= " LIMIT $limitFrom,$limitLength";
            $rows = $this->getRowsCustom($sqlQueryL);
            $ids = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $ids[] = $row[$indexColumnName];
                }
            }
            $ids = implode(',', $ids);

            $sqlQueryP = $sqlQuery;
            $sqlQuery = preg_replace('/(.* FROM [^ ]*)( WHERE )(.*)/i', '$1 WHERE ' . $indexColumnName . ' IN (' . $ids . ') AND $3', $sqlQuery);
            if ($sqlQueryP == $sqlQuery) {
                $sqlQuery = preg_replace('/(.* FROM [^ ]*)( )(.*)/i', '$1 WHERE ' . $indexColumnName . ' IN (' . $ids . ') $3', $sqlQuery);
            }
        }
        else {
            $sqlQuery = $this->getLimitedQuery($sqlQuery, $limitFrom, $limitLength);
        }

        return $sqlQuery;
    }

    /**
     * Enter description here...
     *
     * @param string $sqlQuery
     * @return array ->sample:array(0=>'table1',1=>'table2')
     * @previousNames  fetch_tables_name_from_sql_query
     */
    public function fetchTablesNameFromSqlQuery($sqlQuery) {
        //$pattern='/.*[^`\'"]?from[^`\'"]? +([`"\' ]?[,]?([^`"\',]+)[`"\' $]?[,]?)+/i';
        //$pattern='/from *(.*) *(where|order|limit|$)/i';
        //$sqlQuery="select * from `table1`,`table2`";
        //$sqlQuery="from `table1`,`table2`";
        if (preg_match_all('/from *(.*) *(where|order|limit|$)/i', $sqlQuery, $matches, PREG_PATTERN_ORDER)) {
            if (preg_match_all('/([,]?[`"\' ]?([^`"\',]+)[`"\' $]?[,]?)/i', $matches[1][0], $matches, PREG_PATTERN_ORDER)) {
                return $matches[2];
            }
        }
        return NULL;
    }


    /**
     * @param $tableName
     * @param null $filter_column_name
     * @param null $filter_column_value
     * @param null $limit
     * @param null $sortByColumnName
     * @param null $sortType
     * @return array|bool
     */
    public function getRows($tableName, $filter_column_name = NULL, $filter_column_value = NULL, $limit = NULL, $sortByColumnName = NULL, $sortType = NULL) {
        $sqlQuery = "SELECT * FROM `$tableName`";
        if (!is_null($filter_column_name)) {
            $sqlQuery .= " WHERE `$filter_column_name`='$filter_column_value' ";
        }
        if (!is_null($sortByColumnName)) {
            $sqlQuery .= " ORDER BY `$sortByColumnName` ";
        }
        if (!is_null($sortType)) {
            $sqlQuery .= " $sortType ";
        }
        if (!is_null($limit)) {
            $sqlQuery .= " LIMIT $limit";
        }

        return $this->getRowsCustom($sqlQuery);
    }

    /**
     * @author akbar nasr abadi
     *
     * @param $tableName
     * @param null $filter_column_name
     * @param null $filter_column_value
     * @param null $keyColumnName
     * @param bool $multiMode
     * @return array|bool
     */
    public function getRowsWithCustomIndex($tableName, $filter_column_name = NULL, $filter_column_value = NULL, $keyColumnName = NULL, $multiMode = FALSE) {
        $sqlQuery = "SELECT * FROM `$tableName`";
        if (!is_null($filter_column_name)) {
            $sqlQuery .= " WHERE `$filter_column_name`='$filter_column_value' ";
        }
        $sqlQueryResult = $this->query($sqlQuery);

        $result = FALSE;
        if ($sqlQueryResult !== FALSE) {
            while ($row = $this->fetchArray($sqlQueryResult, MYSQL_ASSOC)) {
                if (!is_null($keyColumnName)) {
                    if ($multiMode) {
                        $result[$row[$keyColumnName]][] = $row;
                    }
                    else {
                        $result[$row[$keyColumnName]] = $row;
                    }
                }
                else {
                    $result[] = $row;
                }
            }
        }
        return $result;
    }


    /**
     * like get rows but accept single dimensional array of columns and values as condition
     * @param $tableName string
     * @param $keyColumnsValues array
     * @param $sort //array('columnName'=>'id','type'=>'asc')
     * @return array|bool
     */
    public function getRowsWithMultiKeys($tableName, $keyColumnsValues, $sort = NULL) {
        $sqlQuery = "SELECT * FROM `$tableName` WHERE (1=1) ";
        if (is_array($keyColumnsValues)) {
            foreach ($keyColumnsValues as $keyColumnName => $keyColumnValue ) {
                $comma = ' AND ';
                if ($this->isStatement($keyColumnValue)) {
                    /* @var $keyColumnValue string|DatabaseStatement */
                    $sqlQuery .= "$comma `$keyColumnName`=" . $keyColumnValue->getValue() . "";
                }
                else {
                    $keyColumnValue = $this->smartEscapeString($keyColumnValue);
                    $sqlQuery .= "$comma `$keyColumnName`='$keyColumnValue' ";
                }
            }
        }
        if (!is_null($sort) and is_array($sort)) {
            $sqlQuery .= " ORDER BY `{$sort['columnName']}` {$sort['type']} ";
        }
        return $this->getRowsCustom($sqlQuery);
    }

    /**
     * execute give query and return it's result as array
     * @param string
     * @return array|boolean
     */
    public function getRowsCustom($sqlQuery) {
        $sqlQueryResult = $this->query($sqlQuery);

        $result = FALSE;
        if ($sqlQueryResult !== FALSE) {
            while ($row = $this->fetchArray($sqlQueryResult, MYSQL_ASSOC)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * if $multiMode was true, each item may contains muliply rows with same $keyColumn value
     * otherwise each item only contains one row and if more than one rows have same $keyColumn value
     * the last will override the others
     *
     * @todo should be remove in favor of getRowsAsArrayCustom as of CML 3
     * @see $this->getRowsCustomAsCustomIndexedArray
     * @param string
     * @param string
     * @param boolean
     * @return array
     */
    public function getRowsAsArrayCustom($sqlQuery, $keyColumnName = NULL, $multiMode = FALSE) {
        return $this->getRowsCustomWithCustomIndex($sqlQuery, $keyColumnName, $multiMode);
    }

    /**
     * if $multiMode was true, each item may contains muliply rows with same $keyColumn value
     * otherwise each item only contains one row and if more than one rows have same $keyColumn value
     * the last will override the others
     * @previousNames getRowsAsArrayCustom
     * @param string
     * @param string
     * @param boolean
     * @return array
     */
    public function getRowsCustomWithCustomIndex($sqlQuery, $keyColumnName = NULL, $multiMode = FALSE) {
        $sqlQueryResult = $this->query($sqlQuery);

        $result = FALSE;
        if ($sqlQueryResult !== FALSE) {
            while ($row = $this->fetchArray($sqlQueryResult, MYSQL_ASSOC)) {
                if (!is_null($keyColumnName)) {
                    if ($multiMode) {
                        $result[$row[$keyColumnName]][] = $row;
                    }
                    else {
                        $result[$row[$keyColumnName]] = $row;
                    }
                }
                else {
                    $result[] = $row;
                }
            }
        }
        return $result;
    }


    public function getColumnValueCustom($sqlQuery, $valueFieldName) {
        $row = $this->loadCustom($sqlQuery);
        if (is_array($row)) {
            return $row[$valueFieldName];
        }
        else {
            return FALSE;
        }
    }

    /**
     * @changes
     *    - issue with using reserved words as field name in $keyFieldName value fixed (patch by Akbar NasrAbadi)
     * @param $keyFieldValue
     * @param $tableName
     * @param $keyFieldName
     * @param $valueFieldName
     * @return bool
     */
    public function getColumnValue($keyFieldValue, $tableName, $keyFieldName, $valueFieldName) {
        $sqlQuery = "SELECT `$valueFieldName`,`$keyFieldName` FROM `$tableName` WHERE `$keyFieldName`='$keyFieldValue' LIMIT 1";
        return $this->getColumnValueCustom($sqlQuery, $valueFieldName);
    }

    /**
     * $this->getColumnValueLike('id','camera_sub','name',$exif['IFD0']['Model']);
     * @previousNames get_value_from_database_like
     *
     * @param $keyFieldValue
     * @param $tableName
     * @param $keyFieldName
     * @param $valueFieldName
     * @return mixed
     */
    public function getColumnValueLike($keyFieldValue, $tableName, $keyFieldName, $valueFieldName) {
        $sqlQuery = "SELECT $valueFieldName,$keyFieldName FROM `$tableName` WHERE $keyFieldName LIKE '$keyFieldValue' LIMIT 1";
        return $this->getColumnValueCustom($sqlQuery, $valueFieldName);
    }

    /**
     * @param $table
     * @param $fieldName
     * @param $fieldValue
     * @param bool $caseSensitive
     * @param bool $binary
     * @return bool
     */
    public function checkRowExistenceByValue($table, $fieldName, $fieldValue, $caseSensitive = TRUE, $binary = TRUE) {
        if ($binary) {
            $binary = " BINARY";
        }
        if ($caseSensitive) {
            $sqlQuery = "SELECT `$fieldName` FROM `$table` WHERE $binary `$fieldName`='$fieldValue'";
        }
        else {
            $sqlQuery = "SELECT `$fieldName` FROM `$table` WHERE $binary `$fieldName` LIKE '$fieldValue'";
        }
        $sqlQueryResult = $this->query($sqlQuery);
        $total = $this->numRows($sqlQueryResult);
        if ($total > 0) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * @param $sqlQuery
     * @return bool
     */
    public function checkRowExistenceCustom($sqlQuery) {
        $sqlQuery = $this->query($sqlQuery);
        $total = $this->numRows($sqlQuery);
        if ($total > 0) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * when your database isn't normalize completely and you want to use something like ",1,5,6," instead of
     * a middle table, this function will help you to fetch value of id(s) from table and combine them in
     * a string or array.
     * @example
     * <code>
     *    $users_email=multi_id_value_to_value(',1,4,5','user','uid','name',',');
     *    //result is an array -> array("reza","asiyeh","sonia")
     * </code>
     *
     * @param $value
     * @param $table
     * @param $keyFieldName
     * @param $valueFieldName
     * @param string $separator
     * @return array
     */
    public function multiIdValueToValue($value, $table, $keyFieldName, $valueFieldName, $separator = ',') {
        $values = explode($separator, $value);
        $result = array();
        foreach ($values as $itemKey) {
            if (!empty($itemKey)) {
                $itemValue = $this->getColumnValue($itemKey, $table, $keyFieldName, $valueFieldName);
                if (!empty($itemValue)) {
                    $result[$itemKey] = $itemValue;
                }
            }
        }
        return $result;
    }

    /**
     * @param $value
     * @param $keyColumnName
     * @param string $separator
     * @return bool|string
     * @return string|boolean # id IN (1,5,47,95,9) or false
     */
    public function multiIdValueToSqlCondition($value, $keyColumnName, $separator = ',') {
        $values = explode($separator, $value);
        $listIds = '';
        $comma = "";
        foreach ($values as $itemKey) {
            if (!empty($itemKey)) {
                $listIds .= $comma . $itemKey;
                if (empty($comma)) {
                    $comma = ',';
                }
            }
        }
        if (!empty($listIds)) {
            return "$keyColumnName IN ($listIds)";
        }
        return FALSE;
    }

    /**
     * @param $tableName
     * @param null $sqlWhere
     * @param null $sqlOrderBy
     * @return mixed
     */
    public function simpleQuery($tableName, $sqlWhere = NULL, $sqlOrderBy = NULL) {
        $sqlQuery = "SELECT * FROM `$tableName`";
        if (!is_null($sqlWhere)) {
            $sqlQuery .= " WHERE $sqlWhere";
        }
        if (!is_null($sqlOrderBy)) {
            $sqlQuery .= " ORDER BY $sqlOrderBy";
        }
        return $this->query($sqlQuery);
    }

    public function simpleWhereQuery($tableName, $fieldName, $fieldValue, $sqlOrderBy = NULL) {
        return $this->simpleQuery($tableName, "`$fieldName`='$fieldValue'", $sqlOrderBy);
    }

    /**
     * @example
     * <code>
     * define('SSG_COMPARISON_SYMBOL','SSG_COMPARISON_SYMBOL');
     * define('SSG_VALUE','SSG_VALUE');
     * /
     *    $where_string_or_array :
     *    ['field_name']=[SSG_VALUE]='5'
     *    ['field_name']=[SSG_COMPARISON_SYMBOL]='>='
     * /
     * function simple_search_query($tableName,$where_string_or_array,$sqlOrderBy=null)
     * {
     *    $sqlQuery="SELECT * FROM $tableName WHERE ";
     *    foreach ($where_string_or_array)
     * }
     * </code>
     *
     * @param $sqlQueryOrResult
     * @param $start
     * @param $limit
     * @param $link
     * @param string $nextWord
     * @param string $previousWord
     * @return mixed
     */
    public function simpleNavigationBar($sqlQueryOrResult, $start, $limit, $link, $nextWord = "Next", $previousWord = "Previous") {
        $num = NULL;
        $next_link = '';
        $prev_link = '';
        $st_next = $start + $limit;
        //$limit2 = $limit + 1;

        if (is_string($sqlQueryOrResult)) {
            //$sqlQuery = "SELECT * FROM `$table` $where ORDER BY $by DESC LIMIT $start,$limit";
            $sqlQueryOrResult = $this->query($sqlQueryOrResult) or die($this->error());
        }
        $total = $this->numRows($sqlQueryOrResult);

        if ($total >= $limit) {
            $next_link = '<a href="' . $link . $st_next . '">' . $nextWord . '&gt;</a>';
        }
        $a = $start - $limit;
        if ($a > -1) {
            $prev_link = '<a href="' . $link . $a . '">&lt;' . $previousWord . '</a>';
        }
        $aa['prev'] = $prev_link;
        $aa['next'] = $next_link;
        $aa['num'] = $num;
        return $aa;
    }

    /**
     * @param $tableName
     * @param $keyColumnName
     * @param $columnsValues
     * @param $keyColumnValue
     * @return string
     */
    public function getUpdateSql($tableName, $keyColumnName, $columnsValues, $keyColumnValue) {
        $sqlQuery = "UPDATE `$tableName` SET ";
        $comma = "";
        foreach ($columnsValues as $columnName => $columnValue) {
            if ($this->isStatement($columnValue)) {
                /* @var $columnValue string|DatabaseStatement */
                $sqlQuery .= "$comma `$columnName`=" . $columnValue->getValue() . "";
            }
            else {
                $columnValue = $this->smartEscapeString($columnValue);
                if ($columnValue === NULL) {
                    $columnValue = 'NULL';
                }
                else {
                    $columnValue = "'$columnValue'";
                }
                $sqlQuery .= "$comma `$columnName`=$columnValue";
            }

            if (empty($comma)) {
                $comma = ',';
            }
        }

        $sqlQuery .= " WHERE `$keyColumnName`='$keyColumnValue'";
        return $sqlQuery;
    }

    /**
     * @desc
     * @param string $sqlWhere //contains sql where condition including WHERE keyword itself
     *
     * @param $tableName
     * @param $columnsValues
     * @param string $sqlWhere
     * @return string
     */
    public function getUpdateSqlCustom($tableName, $columnsValues, $sqlWhere = NULL) {
        $sqlQuery = "UPDATE `$tableName` SET ";
        $comma = "";
        foreach ($columnsValues as $columnName => $columnValue) {
            if ($this->isStatement($columnValue)) {
                /** @var $columnValue string|DatabaseStatement */
                $sqlQuery .= "$comma `$columnName`=" . $columnValue->getValue() . "";
            }
            else {
                $columnValue = $this->smartEscapeString($columnValue);
                if ($columnValue === NULL) {
                    $columnValue = 'NULL';
                }
                else {
                    $columnValue = "'$columnValue'";
                }
                $sqlQuery .= "$comma `$columnName`=$columnValue";
            }

            if (empty($comma)) {
                $comma = ',';
            }
        }

        $sqlQuery .= " $sqlWhere";
        return $sqlQuery;
    }

    /**
     * Sample :
     * <code>
     * Database::updateCustom('tableName',array('age'=>'5'),"WHERE id=5");
     * </code>
     * @desc
     * @param string $sqlWhere //contains sql where condition including WHERE keyword itself
     *
     * @param $tableName
     * @param $columnsValues
     * @param string $sqlWhere
     * @return bool
     */
    public function updateCustom($tableName, $columnsValues, $sqlWhere = NULL) {
        $sqlQuery = $this->getUpdateSqlCustom($tableName, $columnsValues, $sqlWhere);

        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * Sample :
     * <code>
     * Database::update('tableName','id',array('age'=>'5'),5);
     * </code>
     *
     * @param $tableName
     * @param $keyColumnName
     * @param $columnsValues
     * @param $keyColumnValue
     * @return bool
     */
    public function update($tableName, $keyColumnName, $columnsValues, $keyColumnValue) {
        $sqlQuery = $this->getUpdateSql($tableName, $keyColumnName, $columnsValues, $keyColumnValue);

        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * @param $tableName
     * @param $keyColumnName
     * @param $keyColumnValue
     * @return string
     */
    public function getLoadSql($tableName, $keyColumnName, $keyColumnValue) {
        $sqlQuery = "SELECT * FROM `$tableName` WHERE ";

        if ($this->isStatement($keyColumnValue)) {
            /* @var $keyColumnValue string|DatabaseStatement */
            $sqlQuery .= "`$keyColumnName`=" . $keyColumnValue->getValue() . " ";
        }
        else {
            $keyColumnValue = $this->smartEscapeString($keyColumnValue);
            $sqlQuery .= "`$keyColumnName`='$keyColumnValue' ";
        }

        $sqlQuery .= "LIMIT 1";
        return $sqlQuery;
    }

    /**
     * @param $tableName
     * @param $keyColumnName
     * @param $keyColumnValue
     * @return bool
     */
    public function load($tableName, $keyColumnName, $keyColumnValue) {
        $sqlQuery = $this->getLoadSql($tableName, $keyColumnName, $keyColumnValue);
        //if ($tableName=='email_templates') echo $sqlQuery;
        $sqlResult = $this->query($sqlQuery);
        if ($sqlResult) {
            if ($this->numRows($sqlResult) > 0) {
                $row = $this->fetchArray($sqlResult, MYSQL_ASSOC);
                return $row;
            }
        }
        return FALSE;
    }


    /**
     * @desc
     * @example
     * <code>
     *   $keyColumnsValues = array(
     *      "username"=>$_POST['forumUsername'],
     *      "password"=>Database::asStatement("MD5(CONCAT(MD5('".$_POST['forumPassword']."'),`salt`))")
     *   );
     *   $columnsValues = array(
     *      "username"=>$userSystem->cvUsername,
     *      "password"=>$vb->getInsertablePassword($_POST['sitePassword']),
     *      "salt"=>$vb->salt
     *   );
     *   Database::updateWithMultiKeys($vb->userTableName,$keyColumnsValues,$columnsValues);
     * </code>
     *
     * @param $tableName
     * @param $keyColumnsValues
     * @param $columnsValues
     * @return bool
     */
    public function updateWithMultiKeys($tableName, $keyColumnsValues, $columnsValues) {
        $sqlQuery = "UPDATE `$tableName` SET ";
        $sqlWhere = ' WHERE ';
        $comma = '';

        foreach ($keyColumnsValues as $keyColumnName => $keyColumnValue) {
            //unset($columnsValues[$keyColumnName]);

            if ($this->isStatement($keyColumnValue)) {
                /** @var $keyColumnValue string|DatabaseStatement */
                $sqlWhere .= "$comma `$keyColumnName`=" . $keyColumnValue->getValue() . "";
            }
            else {
                $keyColumnValue = $this->smartEscapeString($keyColumnValue);
                $sqlWhere .= "$comma `$keyColumnName`='$keyColumnValue' ";
            }

            $comma = ' AND ';
        }
        $comma = '';
        foreach ($columnsValues as $columnName => $columnValue) {
            if ($this->isStatement($columnValue)) {
                /* @var $columnValue string|DatabaseStatement */
                $sqlQuery .= "$comma `$columnName`=" . $columnValue->getValue() . "";
            }
            else {
                $columnValue = $this->smartEscapeString($columnValue);
                if ($columnValue === NULL) {
                    $columnValue = 'NULL';
                }
                else {
                    $columnValue = "'$columnValue'";
                }
                $sqlQuery .= "$comma `$columnName`=$columnValue";
            }
            if (empty($comma)) {
                $comma = ',';
            }
        }
        $sqlQuery .= $sqlWhere;
        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }


    /**
     * @desc
     * @example
     * <code>
     *   $keyColumnsValues = array(
     *      "username"=>$_POST['forumUsername'],
     *      "password"=>Database::asStatement("MD5(CONCAT(MD5('".$_POST['forumPassword']."'),`salt`))")
     *   );
     *   Database::deleteWithMultiKeys($vb->userTableName,$keyColumnsValues,$columnsValues);
     * </code>
     *
     * @param $tableName
     * @param $keyColumnsValues
     * @return bool
     */
    public function deleteWithMultiKeys($tableName, $keyColumnsValues) {
        $sqlQuery = "DELETE FROM `$tableName` ";
        $sqlWhere = ' WHERE ';
        $comma = '';

        foreach ($keyColumnsValues as $keyColumnName => $keyColumnValue) {
            //unset($columnsValues[$keyColumnName]);

            if ($this->isStatement($keyColumnValue)) {
                /* @var $keyColumnValue string|DatabaseStatement */
                $sqlWhere .= "$comma `$keyColumnName`=" . $keyColumnValue->getValue() . "";
            }
            else {
                $keyColumnValue = $this->smartEscapeString($keyColumnValue);
                $sqlWhere .= "$comma `$keyColumnName`='$keyColumnValue' ";
            }

            $comma = ' AND ';
        }
        $sqlQuery .= $sqlWhere;
        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * @param $tableName
     * @param $keyColumnsValues
     * @return bool
     */
    public function loadWithMultiKeys($tableName, $keyColumnsValues) {
        $sqlQuery = "SELECT * FROM `$tableName` WHERE ";
        $comma = "";
        foreach ($keyColumnsValues as $keyColumnName => $keyColumnValue) {

            if ($this->isStatement($keyColumnValue)) {
                /* @var $keyColumnValue string|DatabaseStatement */
                $sqlQuery .= "$comma `$keyColumnName`=" . $keyColumnValue->getValue() . "";
            }
            else {
                $keyColumnValue = $this->smartEscapeString($keyColumnValue);
                $sqlQuery .= "$comma `$keyColumnName`='$keyColumnValue' ";
            }

            $comma = ' AND ';
        }

        $sqlResult = $this->query($sqlQuery);
        if ($sqlResult) {
            if ($this->numRows($sqlResult) > 0) {
                $row = $this->fetchArray($sqlResult, MYSQL_ASSOC);
                return $row;
            }
        }
        return FALSE;
    }

    /**
     * @param $sqlQuery
     * @return bool
     */
    public function loadCustom($sqlQuery) {
        $sqlResult = $this->query($sqlQuery);
        if ($sqlResult) {
            if ($this->numRows($sqlResult) > 0) {
                $row = $this->fetchArray($sqlResult, MYSQL_ASSOC);
                return $row;
            }
        }
        return FALSE;
    }

    /**
     * @param $tableName
     * @param $columnsValues
     * @return string
     */
    public function getInsertSql($tableName, $columnsValues) {
        $sqlQuery = "INSERT INTO `$tableName` SET ";
        $comma = "";
        foreach ($columnsValues as $columnName => $columnValue) {
            if ($this->isStatement($columnValue)) {
                /* @var $columnValue string|DatabaseStatement */
                $sqlQuery .= "$comma `$columnName`=" . $columnValue->getValue() . "";
            }
            else {
                $columnValue = $this->smartEscapeString($columnValue);
                if ($columnValue === NULL) {
                    $columnValue = 'NULL';
                }
                else {
                    $columnValue = "'$columnValue'";
                }
                $sqlQuery .= "$comma `$columnName`=$columnValue";
            }
            if (empty($comma)) {
                $comma = ',';
            }
        }
        return $sqlQuery;
    }

    /**
     * @param $tableName
     * @param $columnsValues
     * @return bool
     */
    public function insert($tableName, $columnsValues) {
        $sqlQuery = $this->getInsertSql($tableName, $columnsValues);

        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * @param $tableName
     * @param $keyColumnName
     * @param $keyColumnValue
     * @param int $limit
     * @return string
     */
    public function getDeleteSql($tableName, $keyColumnName, $keyColumnValue, $limit = 1) {
        if (!is_null($limit)) {
            $limitClause = "LIMIT $limit";
        }
        else {
            $limitClause = '';
        }
        $keyColumnValue = $this->smartEscapeString($keyColumnValue);
        if ($this->isStatement($keyColumnValue)) {
            /* @var $keyColumnValue string|DatabaseStatement */
            $sqlQuery = "DELETE FROM `$tableName` WHERE `$keyColumnName`=" . $keyColumnValue->getValue() . " $limitClause";
        }
        else {
            $sqlQuery = "DELETE FROM `$tableName` WHERE `$keyColumnName`='$keyColumnValue' $limitClause";
        }
        return $sqlQuery;
    }


    /**
     * @param $tableName
     * @param $keyColumnName
     * @param $keyColumnValue
     * @param int $limit
     * @return bool
     */
    public function delete($tableName, $keyColumnName, $keyColumnValue, $limit = 1) {
        $sqlQuery = $this->getDeleteSql($tableName, $keyColumnName, $keyColumnValue, $limit);
        $sqlQueryResult = $this->exec($sqlQuery);

        if ($sqlQueryResult !== FALSE) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }

    /**
     * @param $tableName
     * @param $sortType
     * @param $sortByColumnName
     * @param $sortByColumnValue
     * @param $sqlWhere
     * @param int $limit
     * @return mixed
     */
    public function getNextRow($tableName, $sortType, $sortByColumnName, $sortByColumnValue, $sqlWhere, $limit = 1) {
        if (strtolower($sortType) == 'desc') {
            $sortType = 'asc';
            $operator = '>';
        }
        else {
            $sortType = 'desc';
            $operator = '<';
        }
        if (!is_null($sqlWhere)) {
            $sqlWhere = "AND ($sqlWhere)";
        }
        $sqlQuery = "SELECT * FROM `$tableName`
					WHERE `$sortByColumnName` $operator '$sortByColumnValue' $sqlWhere
					ORDER BY `$sortByColumnName` $sortType LIMIT $limit";
        if ($limit > 1) {
            return $this->getRowsAsArrayCustom($sqlQuery, NULL, FALSE);
        }
        else {
            return $this->getRowsAsArrayCustom($sqlQuery);
        }
    }

    /**
     * @param $tableName
     * @param $sortType
     * @param $sortByColumnName
     * @param $sortByColumnValue
     * @param $sqlWhere
     * @param int $limit
     * @return mixed
     */
    public function getPrevRow($tableName, $sortType, $sortByColumnName, $sortByColumnValue, $sqlWhere, $limit = 1) {
        if (strtolower($sortType) == 'desc') {
            $operator = '<';
        }
        else {
            $operator = '>';
        }
        if (!is_null($sqlWhere)) {
            $sqlWhere = "AND ($sqlWhere)";
        }
        $sqlQuery = "SELECT * FROM `$tableName`
					WHERE `$sortByColumnName` $operator '$sortByColumnValue' $sqlWhere
					ORDER BY `$sortByColumnName` $sortType LIMIT $limit";
        if ($limit > 1) {
            return $this->getRowsAsArrayCustom($sqlQuery, NULL, FALSE);
        }
        else {
            return $this->getRowsAsArrayCustom($sqlQuery);
        }
    }

    /**
     * @param $tableName
     * @param $sortType
     * @param $sortByColumnName
     * @param $sqlWhere
     * @param int $limit
     * @return mixed
     */
    public function getLatestRow($tableName, $sortType, $sortByColumnName, $sqlWhere, $limit = 1) {
        if (!is_null($sqlWhere)) {
            $sqlWhere = "WHERE ($sqlWhere)";
        }
        $sqlQuery = "SELECT * FROM `$tableName` $sqlWhere
					ORDER BY `$sortByColumnName` $sortType LIMIT $limit";
        if ($limit > 1) {
            return $this->getRowsAsArrayCustom($sqlQuery, NULL, FALSE);
        }
        else {
            return $this->getRowsAsArrayCustom($sqlQuery);
        }
    }

    /**
     * Find the next non used number in the table,
     * For example if you want to add 10 records and let them to only use the numbers between 1-10 and keep this
     * condition even when one of the deleted and new one added. so new record can automatically have the deleted record
     * number
     * There are two algorithem :
     *    - incremental : fast
     *    - division : very fast but not implemented. it divies rows to 2 then the one which my have the missing number to 2 again an so on
     *
     * @param $tableName string
     * @param $numberColumnName string
     * @param $defaultNumber integer
     */
    public function getNextNumber($tableName, $numberColumnName, $defaultNumber) {
        $link = NULL;
        $found = NULL;
        $sqlQuery = "SELECT COUNT(*) AS 'totalRows', max(`$numberColumnName`) AS 'maxCode' FROM `$tableName`";
        $row = Database::loadCustom($sqlQuery);

        if ($row['maxCode'] < $defaultNumber) {
            $row['maxCode'] = $defaultNumber;

        }
        elseif ($row['totalRows'] == $row['maxCode']) {
            //$i = $row['maxCode'] + 1;

        }
        else {
            $limitLength = 500;
            $steps = round($row['maxCode'] / $limitLength);
            //echo '<pre style="direction:ltr;text-align:left">';
            $i = $defaultNumber;
            for ($step = 0; $step <= $steps; $step++) {

                $limitStart = $step * $limitLength;
                $limitEnd = $limitStart + $limitLength;

                if ($limitStart >= $i) {
                    //echo "select doctorCode as 'maxCode' from doctor ORDER By doctorCode ASC LIMIT $limitStart,$limitLength";
                    $sqlQuery = "SELECT COUNT(`$numberColumnName`) AS 'totalRows' FROM `$tableName` WHERE `$numberColumnName`>=$limitStart AND `$numberColumnName`<$limitEnd";
                    $__row = Database::loadCustom($sqlQuery);
                    //echo " | {$step}-($limitStart to $limitEnd)-{$__row['totalRows']}-".($limitEnd-$limitStart);

                    if ($__row['totalRows'] != $limitEnd - $limitStart) {
                        $__result = Database::query("SELECT `$numberColumnName` FROM `$tableName` WHERE `$numberColumnName`>=$limitStart AND `$numberColumnName`<$limitEnd ORDER BY `$numberColumnName` ASC");
                        $i = $limitStart - 1;
                        while ($__row = Database::fetchArray($__result, MYSQL_ASSOC)) {
                            $i++;
                            //echo "<br />$i={$__row['doctorCode']}";
                            if ($i != $__row['doctorCode']) {
                                $found = TRUE;
                                break;
                            }
                        }
                    }
                    if ($found == TRUE) {
                        break;
                    }
                    //echo '<br />';
                }

            }
            //echo '</pre>';
        }


        $this->close($link);

        //return $i;
    }

    /**
     * @param $nodeId
     * @param bool $rowInfo
     * @param $tableName
     * @param $idColumnName
     * @param $parentIdColumnName
     * @return array
     */
    public function getChildPath($nodeId, $rowInfo = FALSE, $tableName, $idColumnName, $parentIdColumnName) {
        // look up the parent of this node
        $sqlQuery = "SELECT * FROM `$tableName` " . "WHERE `$idColumnName`='$nodeId'";
        $result = $this->query($sqlQuery);
        $row = $this->fetchArray($result);

        // save the path in this array [5]
        $path = array();

        // only continue if this $node isn't the root node
        // (that's the node with no parent)
        if ($row[$parentIdColumnName] != '') {
            // the last part of the path to $node, is the name
            // of the parent of $node
            if ($rowInfo == FALSE) {
                $path[] = $row[$parentIdColumnName];
            }
            else {
                $path[] = $row;
            }
            // we should add the path to the parent of this node
            // to the path
            $path = array_merge_recursive($this->getChildPath($row[$parentIdColumnName], $rowInfo, $tableName, $idColumnName, $parentIdColumnName), $path);
        }
        else {

        }

        // return the path
        return $path;
    }


    public function getRowNumberCustom($tableName, $uniqueColumnName, $uniqueColumnValue, $sqlOrder = NULL, $sqlWhere = NULL) {
        $sortType = NULL;
        $sortByColumnName = NULL;
        $sortByColumnValue = NULL;

        if (strtolower($sortType) == 'desc') {
            //$sortType = 'asc';
            //$operator = '>';
        }
        else {
            //$sortType = 'desc';
            //$operator = '<';
        }
        if (!is_null($sqlWhere)) {
            $sqlWhere = "AND ($sqlWhere)";
        }
        /*
        $sqlQuery = "SELECT count(*) as 'row_number' FROM `$tableName`
                        WHERE `$sortByColumnName` $operator '$sortByColumnValue' $sqlWhere
                        ORDER BY $sqlOrder";
        $rowNumber = $this->getColumnValueCustom($sqlQuery, 'row_number');
        */
        $sqlQuery = "SELECT count(*) as 'row_number' FROM `$tableName`
					WHERE `$sortByColumnName` = '$sortByColumnValue' AND
					`$uniqueColumnName`<='$uniqueColumnValue'
					$sqlWhere
					ORDER BY `$uniqueColumnName` asc";
        $rowNumber = $this->getColumnValueCustom($sqlQuery, 'row_number');

        return $rowNumber;
    }


    /**
     * make sql query sortable via adding ORDER BY to appropiriate place
     * below regex will match signle qoutes stings
     * '('{2})*([^']*)('{2})*([^']*)('{2})*'
     * @example
     *    <code>
     *        $this->getSortedQuery('SELECT * FROM table','name','DESC')
     *    </code>
     *    result whould be "SELECT * FROM table ORDER BY `name` DESC"
     * @todo
     *    + accepts multi fields for sorting
     *    - using regexp for adding "Order By"
     * @previousNames get_sorted_query
     * @param $sqlQuery string
     * @param $byFieldName array|string
     * @param $sortType array|string
     * @return string
     */
    public function getSortedQuery($sqlQuery, $byFieldName, $sortType = 'ASC') {
        //$this=&Database::getInstance();
        $orderByQuery = " ORDER BY ";
        if (is_array($byFieldName)) {
            $comma = '';
            foreach ($byFieldName as $key => $myFieldName) {
                if (is_array($sortType)) {
                    $mySortType = $sortType[$key];
                }
                else {
                    $mySortType = $sortType;
                }
                $orderByQuery .= " $comma `$myFieldName` $mySortType";
                $comma = ',';
            }
        }
        else {
            $orderByQuery .= "`$byFieldName` $sortType";
        }
        $__sqlQuery = preg_replace('/(.*)(LIMIT [0-9,]* *$)/i', '$1 ' . $orderByQuery . ' $2', $sqlQuery);
        if ($__sqlQuery != $sqlQuery) {
            $sqlQuery = $__sqlQuery;
        }
        else {
            $sqlQuery .= $orderByQuery;
        }

        return $sqlQuery;
    }


    /**
     * @previousNames get_count_sql_query
     *
     * @param $sqlQuery
     */
    public function getCountSqlQuery($sqlQuery) {
        //SELECT *(,?([^,]*),?)* *FROM.*
    }


    /**
     * @previousNames get_total_rows_number_via_sql_query, countSqlQueryRows
     *
     * @param $sqlQuery
     * @return bool
     */
    public function getTotalRowsNumberViaSqlQuery($sqlQuery) {
        if (preg_match('/^[\t ]*select[\n\r\t ](.*)[\n\r\t ]from[\n\r\t ].*/sim', $sqlQuery, $matches, PREG_OFFSET_CAPTURE)) {
            $sqlQuery = substr_replace($sqlQuery, 'count(*) as "rrrrrrrrtotalrrrrrrrrrr"', $matches[1][1], strlen($matches[1][0]));
            $r = $this->getColumnValueCustom($sqlQuery, 'rrrrrrrrtotalrrrrrrrrrr');
            return $r;
        }
        else {
            $sqlQueryResult = $this->query($sqlQuery);
            return @$this->numRows($sqlQueryResult);
        }
    }


    /**
     * Enter description here...
     *
     * @previousNames get_given_language_column_name, DatabaseGetGivenLanguageColumnName
     * @param $tableName
     * @param $defaultColumnName
     * @param $languageName
     * @param string $separator
     * @return string
     */
    public function getGivenLanguageColumnName($tableName, $defaultColumnName, $languageName = LN_ENGLISH, $separator = "|") {
        if (empty($languageName)) {
            $languageName = LN_ENGLISH;
        }
        if (!empty($defaultColumnName) and !empty($tableName) and !empty($db_name) and !empty($languageName)) {

            $columnName = $defaultColumnName;
            if ($languageName != '' and $languageName != LN_ENGLISH) {
                $columnName = $defaultColumnName . $separator . $languageName;
                if ($this->isColumnExist($db_name, $tableName, $columnName) != $columnName) {
                    $columnName = $defaultColumnName;
                };
            }
            return $columnName;
        }
        return NULL;
    }

    /**
     * @previousNames change_table
     *
     * @param $name
     * @param $newName
     * @param null $newType
     * @return mixed
     */
    /*
    public function changeTable($name, $newName, $newType = NULL) {
      $database_name = $this->currentDatabaseName();
      $sqlQuery = "ALTER TABLE '$database_name'.'$name' RENAME TO '$newName';";
      return $this->exec($sqlQuery);
    }
    */

    /**
     * Check and see if value of specific column is unique or not, it also
     * accept index column value to check uniqueness in edit mode
     * @todo IsCellUnique or IsValueUnique are better names for this function
     * @param string $tableName
     * @param string $columnName
     * @param string $columnValue
     * @param string $idColumnName
     * @param string $idColumnValue
     * @return boolean
     * @previousNames is_row_column_unique
     */
    public function isRowColumnUnique($tableName, $columnName, $columnValue, $idColumnName, $idColumnValue) {
        $sqlQuery = "SELECT `$columnName` FROM `$tableName` WHERE (`$columnName`='$columnValue') ";
        if ($idColumnValue != NULL or $idColumnValue != '') {
            $sqlQuery = $sqlQuery . "AND (`$idColumnName`<>'$idColumnValue')";
        }
        if ($this->numRows($this->query($sqlQuery)) > 0) {
            return FALSE;
        }
        else {
            return TRUE;
        }
    }


    /**
     * @param null $sqlQueryResult
     * @param null $columnIndex
     * @param null $columnName
     * @param null $tableName
     * @return array|string
     */
    public function getColumnsMetaBySqlResult($sqlQueryResult = NULL, $columnIndex = NULL, $columnName = NULL, $tableName = NULL) {
        if (!is_null($tableName) and empty($sqlQueryResult)) {
            $sqlQueryResult = $this->exec("SELECT * FROM `$tableName`");
        }
        $result = '';
        $columnsMeta = array();
        $tableColumnsMeta = array();
        $num_columns = $this->numFields($sqlQueryResult);
        $i = -1;
        while ($i < $num_columns - 1) {
            $i++;
            //if ($columnIndex!=$i and is_null($columnIndex)) {continue;}
            $columnMeta = array();
            $columnObject = $this->fetchField($sqlQueryResult, $i);
            //echo $column_flags=mysql_field_flags($sqlQueryResult,$i);
            //if ($columnObject->name!=$columnName and is_null($columnName)) {continue;}

            //if (strpos($column_flags,'auto_increment')!=false) { $columnMeta['auto_increment']=true;} else {$columnMeta['auto_increment']=false;}
            $columnMeta['name'] = $columnObject->name;
            $columnMeta['table'] = $columnObject->table;
            $columnMeta['type'] = $this->typeToStandardType($columnObject->type);
            $columnMeta['length'] = $columnObject->max_length;
            $columnMeta['null'] = !$columnObject->not_null;
            $columnMeta['primary_key'] = $columnObject->primary_key;
            $columnMeta['unique_key'] = $columnObject->unique_key;
            $columnMeta['multiple_key'] = $columnObject->multiple_key;
            $columnMeta['unsigned'] = $columnObject->unsigned;
            $columnMeta['zerofill'] = $columnObject->zerofill;
            $columnMeta['numeric'] = $columnObject->numeric;
            $columnMeta['multiple_key'] = $columnObject->multiple_key;
            $columnMeta['unique_key'] = $columnObject->unique_key;
            $columnMeta['blob'] = $columnObject->blob;
            $columnMeta['binary'] = NULL;
            $columnMeta['enum'] = NULL;
            $columnMeta['timestamp'] = NULL;

            if (!empty($columnObject->table)) {
                if (!isset($tableColumnsMeta[$columnObject->table])) {
                    $tableColumnsMeta[$columnObject->table] = $this->getColumnsMeta($columnObject->table);
                }
            }

            if (isset($tableColumnsMeta[$columnObject->table])) {
                if (isset($tableColumnsMeta[$columnObject->table][$columnObject->name])) {
                    $columnMeta = array_merge_recursive($columnMeta, $tableColumnsMeta[$columnObject->table][$columnObject->name]);
                }
            }

            $columnsMeta[$columnMeta['name']] = $columnMeta;
            if ($i == $columnIndex or $columnMeta['name'] == $columnName) {
                $result = $columnsMeta[$columnMeta['name']];
            };
        }
        if (is_null($columnName) and is_null($columnIndex)) {
            return $columnsMeta;
        }
        else {
            return $result;
        }
    }


    /**
     * @desc
     * @previousNames mysql_type_to_standard_type
     *
     * @param $type
     * @return mixed
     */
    public function typeToStandardType($type) {
        return $this->adaptor->typeToStandardType($type);
    }


    /**
     * Enter description here...
     *
     * @param string $tableName
     * @param string $columnName
     * @return array
     * @previousNames get_columns_meta
     */
    public function getColumnsMeta($tableName, $columnName = NULL) {
        $columnsMeta = array();
        $columnMeta = NULL;
        //--(BEGIN)-->when i use UNION sql keyword some of column details like "primary key","auto_increment" doesn't detect by above code!
        $sqlQuery = "SHOW COLUMNS FROM `$tableName`";
        if (!is_null($columnName)) {
            $sqlQuery .= " LIKE `$columnName`";
        }
        $array = $this->getRowsCustomWithCustomIndex($sqlQuery, TRUE, NULL, FALSE, TRUE);
        if (is_array($array)) {
            foreach ($array as $row) {
                $columnMeta['default'] = $row['Default'];
                $columnMeta['table'] = $tableName;
                $columnMeta['name'] = $row['Field'];

                preg_match("/([^() ]*)(\(([0-9]+)\))?([^() ]*)/i", $row['Type'], $matches);
                $columnMeta['type'] = $matches[1];
                $columnMeta['length'] = $matches[3];

                if ($row['Null'] == 'YES') {
                    $columnMeta['null'] = TRUE;
                }
                else {
                    $columnMeta['null'] = FALSE;
                }
                if (strpos($row['Key'], 'PRI') !== FALSE) {
                    $columnMeta['primary_key'] = TRUE;
                }
                else {
                    $columnMeta['primary_key'] = FALSE;
                }
                if (strpos($row['Extra'], 'auto_increment') !== FALSE) {
                    $columnMeta['auto_increment'] = TRUE;
                }
                else {
                    $columnMeta['auto_increment'] = FALSE;
                }
                if (strpos($row['Type'], 'zerofill') !== FALSE) {
                    $columnMeta['zerofill'] = TRUE;
                }
                else {
                    $columnMeta['zerofill'] = FALSE;
                }
                if (strpos($row['Type'], 'unsigned') !== FALSE) {
                    $columnMeta['unsigned'] = TRUE;
                }
                else {
                    $columnMeta['unsigned'] = FALSE;
                }

                $columnMeta['type'] = $this->typeToStandardType($columnMeta['type']);
                $columnsMeta[$columnMeta['name']] = $columnMeta;
            }
            if (is_null($columnName)) {
                return $columnsMeta;
            }
            else {
                return $columnMeta;
            }
        }
        else {
            return FALSE;
        }
        //--(END)-->when i using UNION sql keyword some of column details like primary keys doesn't detect by above code!


        //SHOW KEYS FROM test
        /*
        if (0) {
          $row = $this->executeSqlScriptAsArray("SHOW COLUMNS FROM `$tableName` LIKE `$columnName`", FALSE, FALSE, FALSE, TRUE);
          $result = array();
          if (!empty($row)) {
            $string_create_table = $row[1];
            $pattern = '/CREATE *TABLE *`[^`]*` *\((.*)\)(?!CREATE *TABLE *`[^`]*` *)(.*)/is';
            if (preg_match_all($pattern, $string_create_table, $matches)) {
              $string_columns_meta = $matches[1];
              $pattern = '/([^\r\n]*)/is';
              if (preg_match_all($pattern, $string_columns_meta, $matches)) {
                foreach ($matches[1] as $string_column_meta) {

                }
              }
            }
          }
        }
       */
        /*
            ([^,\"'`\(\\)]+(?=,|$))|\"[^\"]*\"|\([^\(]*\)|'[^']*'|`[^`]*`
            matches : sdafasd,'',(,,),",fasf",`fdsa,`,

            "([^"](?:\\.|[^\\"]*)*)"
            escape string match : "This is a \"string\"."
            */
    }

    /**
     * add "COUNT" keyword to desired sql query
     *
     * @param string $sqlQuery
     * @return string
     * @previousNames add_count_to_sql_query
     */
    public function addCountToSqlQuery($sqlQuery = "SELECT * FROM `table_name`") {
        //$this=&Database::getInstance();
        $result = FALSE;
        if (preg_match('/[ \n\r]*SELECT[ \n\r]*.*[ \n\r]*?FROM/si', $sqlQuery)) {
            $sqlQuery = preg_replace('/(\*\.\*)|([, ]\*[ ,])/s', ' \'nonfield\' ', $sqlQuery);
            $result = preg_replace('/[ \n\r]*(SELECT)[ \n\r]+/si', '$1 count(*),', $sqlQuery);
        }
        return $result;
    }


    /**
     * Enter description here...
     * @param string $sqlQuery
     * @return integer
     * @previousNames count_sql_query_rows,
     * @depricated getTotalRowsNumberViaSqlQuery
     */
    public function countSqlQueryRows($sqlQuery) {
        return $this->getTotalRowsNumberViaSqlQuery($sqlQuery);
    }


    /**
     * Enter description here...
     *
     * @param string $db_name
     * @param string $tb_name
     * @param string $co_name
     * @return boolean
     * @previousNames is_column_exist
     */
    public function isColumnExist($db_name, $tb_name, $co_name) {

        //i test this code and it worked but i comment it for some reasons
        /*
            $qr_query="select $fd_name from $tb_name";
            $qr_result=$this->query($qr_query);
            if ($qr_result)
            if (mysql_num_columns($qr_result)>=0)
            if (mysql_column_name($qr_result,0)==$fd_name)
            { return $fd_name; }
            */
        $currentDatabaseConnection = $this->connectionLink;
        //$db_name=$this->connectionContainer[$this->currentAlias]['database'];
        if ($this->isTableExist($db_name, $tb_name)) {
            if (!empty($db_name) and !empty($tb_name) and !empty($currentDatabaseConnection)) {
                $columns = $this->listFields($db_name, $tb_name, $currentDatabaseConnection);
                $total = $this->numFields($columns);

                for ($i = 0; $i < $total; $i++) {
                    if ($this->fieldName($columns, $i) == $co_name) {
                        return $co_name;
                    }
                }
            }
        }
        else {
            $this->raiseError("table '$tb_name' does not exist in database '$db_name'");
        }
        return FALSE;
    }


    /**
     * i tested this code and worked but i comment it for some reasons
     *
     * @param string $db_name
     * @param string $tb_name
     * @return boolean
     * @previousNames is_table_exist
     */
    public function isTableExist($db_name, $tb_name) {
        //$currentDatabaseConnection=$this->currentDatabaseConnection;
        //$db_name=$this->connectionContainer[$this->currentAlias]['database'];
        if (!empty($db_name) and !empty($tb_name) and !empty($currentDatabaseConnection)) {
            $tables = $this->listTables($db_name, $currentDatabaseConnection);
            $total = $this->numRows($tables);
            for ($i = 0; $i < $total; $i++) {
                if ($this->tableName($tables, $i) == $tb_name) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    /**
     * @param null $myDbName
     * @param null $prefix
     * @return array
     */
    public function getTablesList($myDbName = NULL, $prefix = NULL) {
        $sqlQuery = "SHOW TABLES";
        if (!is_null($myDbName)) {
            $sqlQuery .= " FROM $myDbName ";
        }
        if (!is_null($prefix)) {
            $sqlQuery .= ' LIKE "' . $prefix . '%"';
        }
        $patternName = " ($prefix%)";

        $result = $this->getRowsCustom($sqlQuery);
        $mresult = array();

        if (is_array($result)) {
            foreach ($result as $tableInfo) {
                foreach ($tableInfo as $dbName => $tableName) {
                    $dbName = str_replace('Tables_in_', '', $dbName);
                    $dbName = str_replace($patternName, '', $dbName);
                    if (!is_null($myDbName) and $myDbName == $dbName) {
                        $mresult[] = $tableName;
                    }
                    else {
                        $mresult[$dbName][] = $tableName;
                    }
                }
            }
        }
        return $mresult;
    }

    /**
     * @param null $myTableName
     * @param null $myDbName
     * @return array
     */
    public function getColumnsList($myTableName = NULL, $myDbName = NULL) {
        $sqlQuery = "SHOW COLUMNS";
        if (!is_null($myTableName)) {
            $sqlQuery .= " FROM $myTableName ";
        }
        if (!is_null($myDbName)) {
            $sqlQuery .= " FROM $myDbName ";
        }
        /*
            if (!is_null($prefix))
                $sqlQuery.=' LIKE "'.$prefix.'%"';

            $patternName=" ($prefix%)";
            */
        $result = $this->getRowsCustom($sqlQuery);
        $mresult = array();
        if (is_array($result)) {
            foreach ($result as $columnInfo) {
                $mresult[] = array(
                  'name' => $columnInfo['Field']
                );
            }
        }
        return $mresult;
    }

    /**
     * convert query row result physical column name to virtual column names and vise versa
     * @example
     * <code>
     * $columnsValues=array(
     *    'id'=>5
     *    'internal_name'=>12
     * )
     * $columnsNames=array(
     *    'id'=>'id',
     *    'internalName'=>'internal_name'
     * )
     * $columnsValues=$$this->convertColumnNames($columnsValues,$columnsNames);
     * </code>
     * result would be :
     * <code>
     * $columnsValues=array(
     *    'id'=>5
     *    'internalName'=>12
     * )
     * </code>
     *
     * @param $columnsValues
     * @param $columnsNames
     * @return bool|string
     */
    public function convertColumnNames($columnsValues, $columnsNames) {
        //$this=&Database::getInstance();
        if (is_array($columnsValues) && is_array($columnsNames)) {
            $convertedColumns = '';
            foreach ($columnsNames as $columnName => $columnPhysicalName) {
                if (array_key_exists($columnPhysicalName, $columnsValues)) {
                    $convertedColumns[$columnName] = $columnsValues[$columnPhysicalName];
                }
                elseif (array_key_exists($columnName, $columnsValues)) {
                    $convertedColumns[$columnPhysicalName] = $columnsValues[$columnName];
                }
            }
            return $convertedColumns;
        }
        return FALSE;
    }


    /**
     * Safely explode sql queries by the defined delimiter and returns
     * an array containing the queries
     * @notice This function is slow, only use it when performance is not important
     * or when there is no other way
     * <code>
     * $sql="
     *    SELECT * FROM table2 WHERE id=5;
     *    SELECT * FROM table2 WHERE id='5';
     *    SELECT (SELECT b FROM test WHEN content=\"ali;dali\") FROM table2 WHERE id='5';
     *    UPDATE test2 SET `desc`=';;asdfdsaf;\;asdf'
     *    SELECT * FROM table2 WHERE id=5;
     * ";
     * $r=multyQuery($sql);
     * cmfcHtml::printr($r);
     * </code>
     * @todo
     * - Fast mode , only works when there is no comment inside the sql or when delimited is unique
     *
     * @param $queryBlock
     * @param string $delimiter
     * @param array $options
     * @return array
     */
    public function explodeSqlQueries($queryBlock, $delimiter = ';') {
        $inString = FALSE;
        $inStringType = NULL;
        $inComment = FALSE;
        $commentType = NULL;
        //$escaped = FALSE;
        //$notYet = FALSE;
        $sqlBlockLen = strlen($queryBlock);
        //$sqlBlockLen=1000;
        $queries = array();
        //$endOfQuery = FALSE;
        $query = '';
        $queryComment = '';
        //$previousQueryPos = array();
        for ($i = 0; $i < $sqlBlockLen; $i++) {
            $notYet = FALSE;
            $charCurrent = $queryBlock[$i];
            if ($i > 0) {
                $charBehind = $queryBlock[$i - 1];
            }
            else {
                $charBehind = NULL;
            }
            if ($i < $sqlBlockLen) {
                $charForward = $queryBlock[$i + 1];
            }
            else {
                $charForward = NULL;
            }
            if ($i < $sqlBlockLen - 1) {
                $charDblForward = $queryBlock[$i + 2];
            }
            else {
                $charDblForward = NULL;
            }

            if ($charBehind == '\\' and !$inComment) {
                $escaped = TRUE;
            }
            else {
                $escaped = FALSE;
            }

            if (($inString != TRUE or $inStringType != '\'') and $charCurrent == '"' and !$escaped and !$inComment) {
                if ($inString == TRUE) {
                    $inString = FALSE;
                    //$inStringType != NULL;
                }
                else {
                    $inString = TRUE;
                    $inStringType = '"';
                }
            }
            if (($inString != TRUE or $inStringType != '"') and $charCurrent == '\'' and !$escaped and !$inComment) {
                if ($inString == TRUE) {
                    $inString = FALSE;
                    //$inStringType != NULL;
                }
                else {
                    $inString = TRUE;
                    $inStringType = '\'';
                }
            }


            if (($inComment != TRUE or $commentType != '-- ') and !$inString) {
                if ($charCurrent == '/' and $charForward == '*') {
                    $commentType = '/**/';
                    $inComment = TRUE;
                }
                if ($inComment == TRUE and $charBehind == '*' and $charCurrent == '/') {
                    $commentType = NULL;
                    $inComment = FALSE;
                    $queryComment = '';
                    $notYet = TRUE;
                }
            }

            if (($inComment != TRUE or $commentType != '/**/') and !$inString) {
                if ($charCurrent == '-' and $charForward == '-' and $charDblForward == ' ') {
                    $commentType = '-- ';
                    $inComment = TRUE;
                }
                if ($inComment == TRUE and ($charBehind == "\r" or $charBehind == "\n") and ($charCurrent == "\n" or $charCurrent == "\r")) {
                    $commentType = NULL;
                    $inComment = FALSE;
                    $queryComment = '';
                    $notYet = TRUE;
                }
            }

            if ($inComment != TRUE and $notYet != TRUE) {
                $query .= $charCurrent;
            }
            else {
                $queryComment .= $charCurrent;
            }

            if ($charCurrent == $delimiter and $inString != TRUE and $escaped != TRUE and $inComment != TRUE) {
                $endOfQuery = TRUE;
            }
            else {
                $endOfQuery = FALSE;
            }
            /*
                  $debug=array(
                      '$charNumber'=>$i,
                      '$charBehind'=>$charBehind,
                      '$charCurrent'=>$charCurrent,
                      '$charForward'=>$charForward,
                      '$charDblForward'=>$charDblForward,
                      '$inString'=>$inString,
                      '$inStringType'=>$inStringType,
                      '$inComment'=>$inComment,
                      '$commentType'=>$commentType,
                      '$escaped'=>$escaped,
                      '$endOfQuery'=>$endOfQuery,
                      '$queryComment'=>$queryComment,
                      '$query'=>$query,
                  );
                  cmfcHtml::printr($debug);
                  */

            if ($endOfQuery) {
                $queries[] = $query;
                $query = '';
            }
        }
        //exit;
        return $queries;
    }

    /**
     * Safely explode sql queries by the defined delimiter and returns
     * an array containing the queries
     * @notice This function is slow, only use it when performance is not important
     * or when there is no other way
     * <code>
     * $sql="
     *    SELECT * FROM table2 WHERE id=5;
     *    SELECT * FROM table2 WHERE id='5';
     *    SELECT (SELECT b FROM test WHEN content=\"ali;dali\") FROM table2 WHERE id='5';
     *    UPDATE test2 SET `desc`=';;asdfdsaf;\;asdf'
     *    SELECT * FROM table2 WHERE id=5;
     * ";
     * $r=multyQuery($sql);
     * cmfcHtml::printr($r);
     * </code>
     * @author http://php4every1.com/tutorials/multi-query-function/
     * @param $queryBlock
     * @param $delimiter
     * @return array
     */
    public function explodeSqlQueriesComplete($queryBlock, $delimiter = ';') {
        $inString = FALSE;
        $escChar = FALSE;
        $sql = '';
        $stringChar = '';
        $queryLine = array();
        $sqlRows = str_split("\n", $queryBlock);
        $delimiterLen = strlen($delimiter);
        do {
            $sqlRow = current($sqlRows) . "\n";
            $sqlRowLen = strlen($sqlRow);
            for ($i = 0; $i < $sqlRowLen; $i++) {
                if ((substr(ltrim($sqlRow), $i, 2) === '--' || substr(ltrim($sqlRow), $i, 1) === '#') && !$inString) {
                    break;
                }
                $znak = substr($sqlRow, $i, 1);
                if ($znak === '\'' || $znak === '"') {
                    if ($inString) {
                        if (!$escChar && $znak === $stringChar) {
                            $inString = FALSE;
                        }
                    }
                    else {
                        $stringChar = $znak;
                        $inString = TRUE;
                    }
                }
                if ($znak === '\\' && substr($sqlRow, $i - 1, 2) !== '\\\\') {
                    $escChar = !$escChar;
                }
                else {
                    $escChar = FALSE;
                }
                if (substr($sqlRow, $i, $delimiterLen) === $delimiter) {
                    if (!$inString) {
                        $sql = trim($sql);
                        $delimiterMatch = array();
                        if (preg_match('/^DELIMITER[[:space:]]*([^[:space:]]+)$/i', $sql, $delimiterMatch)) {
                            $delimiter = $delimiterMatch [1];
                            $delimiterLen = strlen($delimiter);
                        }
                        else {
                            $queryLine [] = $sql;
                        }
                        $sql = '';
                        continue;
                    }
                }
                $sql .= $znak;
            }
        } while (next($sqlRows) !== FALSE);

        return $queryLine;
    }

    /**
     * @param $sqlQuery
     * @param null $linkIdentifier
     * @return bool
     */
    public function query($sqlQuery, $linkIdentifier = NULL) {
        $this->__startTimer();

        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }

        if ($this->noQueryExecution) {
            return TRUE;
        }

        if ($this->autoParseQueriesEnabled === TRUE) {
            $sqlQuery = $this->parseQuery($sqlQuery);
        }

        $invalidKeyWords = array(
          'INSERT',
          'UPDATE',
          'DELETE',
          'DROP',
          'ALTER',
          'CREATE',
          'INDEX',
          'REFERENCES'
        );
        foreach ($invalidKeyWords as $invalidKeyWord) {
            if (preg_match('/^ *' . $invalidKeyWord . ' .*/si', $sqlQuery)) {
                $this->raiseError('Using $this->query for INSERT or UPDATE or DELETE or any executable STATEMENT does not allowed');
            }
        }

        if (is_null($linkIdentifier)) {
            $result = $this->adaptor->query($sqlQuery);
            $this->registerQuery($sqlQuery, $result, $this->error($linkIdentifier));
        }
        else {
            $result = $this->adaptor->query($sqlQuery, $linkIdentifier);
            $this->registerQuery($sqlQuery, $result, $this->error($linkIdentifier));
        }
        return $result;
    }

    /**
     * @param $sqlQuery
     * @param null $linkIdentifier
     * @return bool
     */
    public function exec($sqlQuery, $linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }

        $this->__startTimer();

        if ($this->noQueryExecution) {
            return TRUE;
        }

        if ($this->autoParseQueriesEnabled === TRUE) {
            $sqlQuery = $this->parseQuery($sqlQuery);
        }
        if (1 == 0) {
            $invalidKeyWords = array(
              'SELECT'
            );
            foreach ($invalidKeyWords as $invalidKeyWord) {
                if (preg_match('/^ *' . $invalidKeyWord . ' .*/si', $sqlQuery)) {
                    $this->raiseError('Using $this->exec for SELECT does not allowed');
                }
            }
        }
        if (is_null($linkIdentifier)) {
            $result = $this->adaptor->exec($sqlQuery);
            $this->registerQuery($sqlQuery, $result, $this->error($linkIdentifier));
        }
        else {
            $result = $this->adaptor->exec($sqlQuery, $linkIdentifier);
            $this->registerQuery($sqlQuery, $result, $this->error($linkIdentifier));
        }
        return $result;
    }

    /**
     * @param null $linkIdentifier
     * @return mixed
     */
    public function insertId($linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->adaptor->insertId();
        }
        else {
            return $this->adaptor->insertId($linkIdentifier);
        }
    }

    /**
     * @param null $linkIdentifier
     * @return mixed
     */
    public function error($linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->adaptor->error();
        }
        else {
            return $this->adaptor->error($linkIdentifier);
        }
    }

    /**
     * @param null $linkIdentifier
     * @return mixed
     */
    public function errorNo($linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->adaptor->errorNo();
        }
        else {
            return $this->adaptor->errorNo($linkIdentifier);
        }
    }

    /**
     * @param $dbName
     * @param $tableName
     * @param null $linkIdentifier
     * @return mixed
     */
    public function listFields($dbName, $tableName, $linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->adaptor->listFields($dbName, $tableName);
        }

        else {
            return $this->adaptor->listFields($dbName, $tableName, $linkIdentifier);
        }
    }

    /**
     * @param $dbName
     * @param null $linkIdentifier
     * @return mixed
     */
    function listTables($dbName, $linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->adaptor->listTables($dbName);
        }
        else {
            return $this->adaptor->listTables($dbName, $linkIdentifier);
        }
    }

    /**
     * @param $result
     * @return mixed
     */
    public function numRows($result) {
        return $this->adaptor->numRows($result);
    }

    /**
     * @param $result
     * @param int $resultType
     * @return array|null
     */
    public function fetchArray($result, $resultType = MYSQL_BOTH) {
        return $this->adaptor->fetchArray($result, $resultType);
    }

    /**
     * @param $result
     * @return array|null
     */
    public function fetchRow($result) {
        return $this->adaptor->fetchRow($result);
    }

    /**
     * @param $result
     * @return array|null
     */
    public function fetchAssoc($result) {
        return $this->adaptor->fetchAssoc($result);
    }

    /**
     * @param $result
     * @return int
     */
    public function numFields($result) {
        return $this->adaptor->numFields($result);
    }

    /**
     * @param $result
     * @param int $field_offset
     * @return bool|object
     */
    public function fetchField($result, $field_offset = 0) {
        return $this->adaptor->fetchField($result, $field_offset);
    }

    /**
     * @throws Exception
     * @param $result
     * @param int $field_offset
     * @return null
     * @throws Exception
     */
    public function fieldName($result, $field_offset = 0) {
        return $this->adaptor->fieldName($result, $field_offset);
    }

    /**
     * @param $result
     * @param $i
     * @return null
     * @throws Exception
     */
    public function tableName($result, $i) {
        return $this->adaptor->tableName($result, $i);
    }

    /**
     * @param $msg
     */
    public function raiseError($msg) {
        trigger_error($msg);
    }

    /**
     * @param $linkIdentifier
     * @return int
     */
    public function affectedRows($linkIdentifier) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        return $this->adaptor->affectedRows($linkIdentifier);
    }

    /**
     * @param $tableName
     * @param null $linkIdentifier
     * @return array|bool
     */
    public function getTableColumns($tableName, $linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            return $this->getRowsCustom("SHOW COLUMNS FROM $tableName");
        }
        else {
            return $this->getRowsCustom("SHOW COLUMNS FROM $tableName");
        }
    }

    /**
     * @param $tableName
     * @param null $linkIdentifier
     * @return array
     */
    public function getTableColumnsName($tableName, $linkIdentifier = NULL) {
        if (is_null($linkIdentifier)) {
            $linkIdentifier = $this->connectionLink;
        }
        if (is_null($linkIdentifier)) {
            $columns = $this->getRowsCustom("SHOW COLUMNS FROM $tableName");
        }
        else {
            $columns = $this->getRowsCustom("SHOW COLUMNS FROM $tableName");
        }

        $columnsName = array();
        if (is_array($columns)) {
            foreach ($columns as $columnInfo) {
                $columnsName[] = $columnInfo['Field'];
            }
        }

        return $columnsName;
    }

    /**
     * @param $string
     * @return string
     */
    public function smartEscapeString($string) {
        if ($string === NULL) {
            return $string;
        }

        if (get_magic_quotes_gpc() == 1) {
            $string = stripcslashes($string);
        }
        $string = $this->adaptor->smartEscapeString($string);
        return $string;
    }

    /**
     * @param $string
     * @return string
     */
    public function realEscapeString($string) {
        if (get_magic_quotes_gpc() == 1) {
            $string = stripcslashes($string);
        }
        $string = $this->adaptor->realEscapeString($string);
        return $string;
    }

    /**
     * @todo should remove or convert to something useful as of CML 3
     *
     * @param $tableName
     * @param $sortType
     * @param $sortByColumnName
     * @param $sortByColumnValue
     * @param $uniqueColumnName
     * @param $uniqueColumnValue
     * @param null $sqlWhere
     * @return bool
     */
    public function __getRowNumber($tableName,
                                   $sortType, $sortByColumnName, $sortByColumnValue,
                                   $uniqueColumnName, $uniqueColumnValue, $sqlWhere = NULL) {

        if (strtolower($sortType) == 'desc') {
            $sortType = 'asc';
            $operator = '>';
        }
        else {
            $sortType = 'desc';
            $operator = '<';
        }
        if (!is_null($sqlWhere)) {
            $sqlWhere = "AND ($sqlWhere)";
        }
        $sqlQuery = "SELECT count(*) as 'row_number' FROM `$tableName`
					WHERE `$sortByColumnName` $operator '$sortByColumnValue' $sqlWhere
					ORDER BY `$sortByColumnName` $sortType";
        $rowNumber = $this->getColumnValueCustom($sqlQuery, 'row_number');

        $sqlQuery = "SELECT count(*) as 'row_number' FROM `$tableName`
					WHERE `$sortByColumnName` = '$sortByColumnValue' AND
					`$uniqueColumnName`<='$uniqueColumnValue'
					$sqlWhere
					ORDER BY `$uniqueColumnName` asc";
        $rowNumber += $this->getColumnValueCustom($sqlQuery, 'row_number');

        return $rowNumber;
    }


    /**
     * this function helps to avoid SQL-Injections
     * the passed string will be cleaned up. this is
     * meant for the following values:
     * - input from users
     * - parameters from URLs
     * - Values from cookies
     *
     * @access public
     * @param string $value
     * @return string
     * @previousNames cleanup_value
     */
    public function cleanupValue($value) {
        return (preg_replace("/[\'\"\/\\\;\`\n\r\n]/", "", $value));
    }
}
