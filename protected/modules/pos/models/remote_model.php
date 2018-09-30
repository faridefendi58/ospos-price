<?php
namespace Model;

require_once __DIR__ . '/../../../models/base_remote.php';

class RemoteModel extends \Model\BaseRemoteModel
{
    protected $tableName;

    public function __construct($outlet_id = 1, $table_name = 'items', $table_pk = 'id')
    {
        $outlet = \Model\OutletsModel::model()->findByPk($outlet_id);
        if (!empty($outlet->configs)) {
            $configs = json_decode($outlet->configs, true);
            $cfg_params = [];
            $cfg_params['settings']['db']['connectionString'] = 'mysql:host='.$configs['db']['host'].';dbname='.$configs['db']['dbname'];
            $cfg_params['settings']['db']['username'] = $configs['db']['dbuser'];
            $cfg_params['settings']['db']['password'] = $configs['db']['dbpassword'];
            $cfg_params['settings']['db']['tableName'] = $table_name;
            $cfg_params['settings']['db']['tablePrefix'] = $configs['db']['tablePrefix'];
            $cfg_params['settings']['db']['dbKey'] = 'DB'.$outlet_id;
            //$this->tableName = $table_name;
            $this->dbKey = 'DB'.$outlet_id;

            $this->connectionString = $cfg_params['settings']['db']['connectionString'];
            $this->username = $cfg_params['settings']['db']['username'];
            $this->password = $cfg_params['settings']['db']['password'];
            $this->tableName = $cfg_params['settings']['db']['tablePrefix'].$table_name;
            $this->_tbl_prefix = $cfg_params['settings']['db']['tablePrefix'];
            $this->tablePk = $table_pk;

            if (!$this->is_connected) {
                $this->setup($cfg_params['settings']['db']['dbKey']);
            }
        }
    }

    public static function model($outlet_id = 1, $table_name = 'items', $table_pk = 'id', $className=__CLASS__)
    {
        return parent::model($outlet_id, $table_name, $table_pk, $className);
    }

    public function tableName()
    {
        return $this->tableName;
    }

    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [];
    }

    public function getTableRows($data = null)
    {
        $sql = 'SELECT t.*    
            FROM {tableName} t 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
        }

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tableName}'], [$this->tableName], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function doExec($sql, $params = null)
    {
        $exec = R::exec($sql, $params);
        return $exec;
    }
}
