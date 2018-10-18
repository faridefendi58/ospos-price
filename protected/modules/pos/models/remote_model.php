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
            $this->outletId = $outlet_id;

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
            $field = array();
            foreach ($params as $attr => $val){
                $field[] = $attr. '= :'. $attr;
            }

            if (count($field) > 0)
                $sql .= ' AND '.implode(" AND ", $field);
        }

        $sql .= ' ORDER BY t.'. $this->tablePk .' DESC';

        if (is_array($data) && isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tableName}'], [$this->tableName], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function doExec($sql, $params = null)
    {
        $exec = R::exec($sql, $params);
        return $exec;
    }

    /**
     * Geting the product list
     * @param null $data
     * @return array
     */
    public function getProducts($data = null)
    {
        $sql = 'SELECT t.item_id, t.item_number, t.name, t.category, 
            t.description, t.cost_price, t.unit_price, t.deleted';

        if (isset($data['outlet_id'])) {
            $sql .= ', '.$data['outlet_id'].' AS outlet_id';
        }
        $sql .= ' FROM {tableName} t WHERE 1';

        $params = [];
        if (isset($data['deleted'])) {
            $sql .= ' AND t.deleted = 0';
        }

        $sql .= ' ORDER BY t.'. $this->tablePk .' DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tableName}'], [$this->tableName], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getPriceListItems($data)
    {
        $sql = 'SELECT t.id, t.item_id AS item_id, p.name AS pricelist_name, p.code AS price_list_code,
            t.unit_price AS pricelist_unit_price, i.name, i.item_number, i.category, i.cost_price, i.unit_price';

        if (isset($data['outlet_id'])) {
            $sql .= ', '.$data['outlet_id'].' AS outlet_id';
        }
        $sql .= ' FROM {tableName} t';
        $sql .= ' LEFT JOIN {tablePrefix}price_lists p ON p.id = t.price_list_id';
        $sql .= ' LEFT JOIN {tablePrefix}items i ON i.item_id = t.item_id';
        $sql .= ' WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['price_list_id'])) {
                $sql .= ' AND t.price_list_id =:price_list_id';
                $params['price_list_id'] = $data['price_list_id'];
            }
        }

        $sql .= ' ORDER BY t.'. $this->tablePk .' DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tableName}', '{tablePrefix}'], [$this->tableName, $this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getRevenue($data = array())
    {
        $sql = 'SELECT SUM(t.item_unit_price*t.quantity_purchased) AS total, 
            UNIX_TIMESTAMP(DATE_FORMAT(s.sale_time, "%Y-%m-%d"))*1000 AS sale_date';

        $sql .= ' FROM {tableName} t';
        $sql .= ' LEFT JOIN {tablePrefix}sales s ON s.sale_id = t.sale_id';
        $sql .= ' WHERE 1';

        $params = [];
        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND s.sale_time BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
            $sql .= ' GROUP BY sale_date ASC';
        }

        $sql = str_replace(['{tableName}', '{tablePrefix}'], [$this->tableName, $this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $items[] = [ (int)$row['sale_date'], (int)$row['total']];
            }
        }

        return $items;
    }

    public function getTotalTransaction($data = array())
    {
        $sql = 'SELECT t.sale_type, COUNT(t.sale_id) AS count';

        $sql .= ' FROM {tableName} t';
        $sql .= ' WHERE 1';

        $params = [];
        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND t.sale_time BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' GROUP BY t.sale_type';

        $sql = str_replace(['{tableName}', '{tablePrefix}'], [$this->tableName, $this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        if (count($rows) > 0) {
            $tot = 0;
            foreach ($rows as $row) {
                $type = 'penjualan';
                if ($row['sale_type'] == 0) {
                    $type = 'penjualan';
                } elseif ($row['sale_type'] == 4) {
                    $type = 'retur';
                }
                $items[$row['sale_type']] = ['type' => $type, 'count' => $row['count']];
                $tot = $tot + $row['count'];
            }
        }

        return ['total' => $tot, 'detail' => $items];
    }

    public function getTransactionItems($data = array())
    {
        $sql = 'SELECT t.sale_id, t.quantity_purchased, t.item_cost_price, t.item_unit_price, s.sale_time';
        $sql .= ', i.item_number, i.name AS item_name, (t.item_unit_price - t.item_cost_price) AS product_revenue';

        $sql .= ' FROM {tablePrefix}sales_items t';
        $sql .= ' LEFT JOIN {tablePrefix}sales s ON s.sale_id = t.sale_id';
        $sql .= ' LEFT JOIN {tablePrefix}items i ON i.item_id = t.item_id';
        $sql .= ' WHERE 1';

        $params = [];
        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND s.sale_time BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        if (isset($data['product_only'])) {
            $sql .= ' GROUP BY t.item_id';
        }

        $sql .= ' ORDER BY s.sale_time DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getItemStocks($data = array())
    {
        $sql = 'SELECT t.item_id, t.quantity, i.name AS item_name';
        if (isset($data['outlet_id'])) {
            $sql .= ', '.$data['outlet_id'].' AS outlet_id';
        }

        $sql .= ' FROM {tablePrefix}item_quantities t';
        $sql .= ' LEFT JOIN {tablePrefix}items i ON i.item_id = t.item_id';
        $sql .= ' WHERE i.deleted = 0';

        $params = [];
        if (isset($data['date_start']) && isset($data['date_end'])) {

        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getProductPrices($data = null)
    {
        $sql = 'SELECT t.item_id, t.unit_price, li.code, i.unit_price AS HJU';

        $sql .= ' FROM {tableName} t';

        $sql .= ' LEFT JOIN {tablePrefix}price_lists li ON li.id = t.price_list_id';
        $sql .= ' LEFT JOIN {tablePrefix}items i ON i.item_id = t.item_id';

        $sql .= ' WHERE 1';

        $params = [];
        if (isset($data['item_id'])) {
            $sql .= ' AND t.item_id =:item_id';
            $params['item_id'] = $data['item_id'];
        }

        $sql .= ' AND i.deleted = 0';

        $sql .= ' ORDER BY t.'. $this->tablePk .' DESC';

        if (isset($data['limit'])) {
            $sql .= ' LIMIT '. $data['limit'];
        }

        $sql = str_replace(['{tableName}', '{tablePrefix}'], [$this->tableName, $this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        if (count($rows) > 0) {
            foreach ($rows as $i => $row) {
                $items[$row['item_id']]['HJU'] = $row['HJU'];
                $items[$row['item_id']][$row['code']] = $row['unit_price'];
            }
        }

        return $items;
    }
}
