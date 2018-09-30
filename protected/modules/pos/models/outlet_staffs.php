<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class OutletStaffsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_outlet_staff';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['outlet_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, a.name AS admin_name, w.title AS warehouse_name, 
            ab.name AS admin_creator_name, r.title AS role_name, r.roles AS roles, 
            g.id AS wh_group_id, g.title AS outlet_group_name, g.pic AS outlet_group_pic     
            FROM {tablePrefix}ext_outlet_staff t 
            LEFT JOIN {tablePrefix}ext_outlet w ON w.id = t.outlet_id 
            LEFT JOIN {tablePrefix}ext_outlet_staff_role r ON r.id = t.role_id 
            LEFT JOIN {tablePrefix}ext_outlet_group g ON g.id = w.group_id  
            LEFT JOIN {tablePrefix}admin a ON a.id = t.admin_id  
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.created_by   
            WHERE 1';

        $params = [];
        if (isset($data['outlet_id'])) {
            $sql .= ' AND t.outlet_id =:outlet_id';
            $params['outlet_id'] = $data['outlet_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name, 
            r.title AS role_name  
            FROM {tablePrefix}ext_outlet_staff t 
            LEFT JOIN {tablePrefix}ext_outlet_staff_role r ON r.id = t.role_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
