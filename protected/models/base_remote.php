<?php
namespace Model;

use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../components/rb.php';

class BaseRemoteModel extends \RedBeanPHP\SimpleModel
{
    protected $connectionString;
    protected $username;
    protected $password;
    protected $frozen = true;
    protected $is_connected = false;
    protected $bean_type = 'default';
    protected $tableName;
    protected $_errors;
    protected $_scenario;
    protected $_tbl_prefix;
    protected $dbKey;
    protected $tablePk;
    protected $outletId;

    private static $_models = array(); // class name => model

    public function setup($dbKey = null)
    {
        R::addDatabase( $dbKey, $this->connectionString, $this->username, $this->password, $this->frozen );
        R::selectDatabase( $dbKey );

        $this->is_connected = true;
        return true;
    }

    public static function model($outlet_id = 1, $table_name = 'items', $table_pk = 'id', $className=__CLASS__)
    {
        if(isset(self::$_models[$className])) {
            $model = self::$_models[$className];
            if ((int)$model->getOutletId() != (int)$outlet_id) {
                $model = self::$_models[$className] = new $className($outlet_id, $table_name, $table_pk);
            }

            return $model;
        } else
        {
            $model = self::$_models[$className] = new $className($outlet_id, $table_name, $table_pk);
            return $model;
        }
    }

    /**
     * Usage : $rb = \Model\AdminModel::model()->getRb();
     * @return string
     */
    public function getRb()
    {
        return R::getVersion();
    }

    public function getOutletId()
    {
        return $this->outletId;
    }

    public function findByAttributes($params)
    {
        $field = array();
        foreach ($params as $attr => $val){
            $field[] = $attr. '= :'. $attr;
        }

        $sql = implode(" AND ", $field);

        return R::findOne($this->tableName, $sql, $params);
    }

    public function findAllByAttributes($params)
    {
        $sql = 'SELECT * FROM '.$this->tableName.' WHERE 1';
        $field = array();
        foreach ($params as $attr => $val){
            $field[] = $attr. '= :'. $attr;
        }

        $where = implode(" AND ", $field);
        $sql .= ' AND '. $where;

        return R::getAll($sql, $params);
    }

    public function findAll()
    {
        $sql = 'SELECT * FROM '.$this->tableName.' WHERE 1';
        return R::getAll( $sql );
    }

    public function findByPk($id)
    {
        return R::findOne($this->tableName, ' '. $this->tablePk .' = ?', [$id]);
    }

    public function getRows($params)
    {
        $sql = 'SELECT * FROM '.$this->tableName.' WHERE 1';

        $field = array();
        foreach ($params as $attr => $val){
            $field[] = $attr. '= :'. $attr;
        }

        if (count($field) > 0)
            $sql .= ' AND '.implode(" AND ", $field);

        return R::getAll($sql, $params);
    }

    /**
     * Ex : $model = new \Model\RemoteModel(1, 'items', 'item_id' );
     * $model->name = 'Items 1';
     * $save = $model->save();
     * @return bool
     */
    public function save()
    {
        $bean = $this;
        $validate = $this->validate($bean);
        if ( is_array($validate) ){
            $this->_errors = $validate;
            return false;
        }

        $attributes = get_object_vars($bean->bean);
        $columns = []; $values = [];
        foreach ($attributes as $attr => $val) {
            array_push( $columns, '`'.$attr.'`' );
            array_push( $values, ':'.$attr);
        }
        $columns = implode(", ", $columns);
        $values = implode(", ", $values);

        $sql = "INSERT INTO ".$this->tableName." (". $columns .") VALUES (". $values .")";

        $save = R::exec( $sql, $attributes );

        return ($save > 0)? true : false;
    }

    /**
     * Ex :
     * $item_model = \Model\RemoteModel::model(1, 'items', 'item_id');
     * $model = $item_model->findByPk(10);
     * $model->supplier_id = 1;
     * $update = $item_model->update($model, false, ['supplier_id']);
     * or $update = $item_model->update($model); // will update all column
     * @param $bean
     * @param bool $validate
     * @param null $attributes
     * @return bool
     */
    public function update($bean, $validate = true, $attributes = null)
    {
        if ($validate) {
            $validate = $this->validate($bean);
            if (is_array($validate)) {
                $this->_errors = $validate;
                return false;
            }
        }

        $properties = $bean->getProperties();
        $values = []; $params = [];
        if (is_array($attributes)) {
            foreach ($attributes as $i => $attr) {
                $values[] = $attr .'=:'. $attr;
                $params[$attr] = $properties[$attr];
            }
        } else {
            foreach ($properties as $name => $val) {
                if ($name != $this->tablePk) {
                    $values[] = $name .'=:'. $name;
                    $params[$name] = $val;
                }
            }
        }

        if (count($values) == 0) {
            return false;
        }

        $bindings = implode(", ", $values);

        $sql = "UPDATE ".$this->tableName." SET ". $bindings ." WHERE ". $this->tablePk ." = ". $bean->{$this->tablePk};

        $update = R::exec( $sql, $params );

        return ($update > 0)? true : false;
    }

    /**
     * Usage :
     * $item_model = \Model\RemoteModel::model(1, 'items', 'item_id');
     * $model = $item_model->findByPk(5354);
     * $delete = $item_model->delete($model);
     * @param $bean
     * @return bool
     */
    public function delete($bean)
    {
        if (!is_object($bean))
            return false;

        $sql = "DELETE FROM ".$this->tableName." WHERE ". $this->tablePk ." = ". $bean->{$this->tablePk};

        $delete = R::exec( $sql, $params );

        return ($delete > 0)? true : false;
    }

    public function deleteAllByAttributes($params)
    {
        $field = array();
        foreach ($params as $attr => $val){
            $field[] = $attr. '= :'. $attr;
        }

        $where = implode(" AND ", $field);

        $sql = "DELETE FROM ".$this->tableName." WHERE ". $where;

        $delete = R::exec( $sql, $params );

        return ($delete > 0)? true : false;
    }

    public function validate($bean)
    {
        if (is_array($this->rules())) {
            require_once __DIR__ . '/../components/validator.php';
            $validator = new \Components\Validator($bean);
            $errors = [];
            foreach ($this->rules() as $i => $rule){
                $val = $validator->execute($rule);
                if (is_array($val) && count($val)>0)
                    array_push($errors, $val);
            }

            return (count($errors) > 0)? $errors : true;
        }

        return true;
    }

    public function getErrors($in_array = true, $per_attribute = false)
    {
        if ($in_array) {
            if ($per_attribute){
                $errs = [];
                foreach ($this->_errors as $i => $error){
                    foreach ($error as $j => $err_detail) {
                        $errs[$j] = $err_detail;
                    }
                }
                return $errs;
            }

            return $this->_errors;
        } else {
            $msg = "<ul>Silakan periksa kembali beberapa kesalahan berikut :";
            foreach ($this->_errors as $i => $error){
                foreach (array_values($error) as $j => $err_detail) {
                    $msg .= "<li>" . $err_detail . "</li>";
                }
            }
            $msg .= "</ul>";
            return $msg;
        }
    }

    public function getScenario()
    {
        return $this->_scenario;
    }
}