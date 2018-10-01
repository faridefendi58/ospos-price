<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class OutletsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET'], '/group/view', [$this, 'view_group']);
        $app->map(['POST'], '/group/create', [$this, 'create_group']);
        $app->map(['GET', 'POST'], '/group/update/[{id}]', [$this, 'update_group']);
        $app->map(['POST'], '/group/delete/[{id}]', [$this, 'delete_group']);
        $app->map(['GET'], '/staff/view/[{id}]', [$this, 'view_staff']);
        $app->map(['GET', 'POST'], '/staff/create/[{id}]', [$this, 'create_staff']);
        $app->map(['POST'], '/staff/delete/[{id}]', [$this, 'delete_staff']);
        $app->map(['POST'], '/role/create', [$this, 'create_role']);
        $app->map(['GET', 'POST'], '/role/update/[{id}]', [$this, 'update_role']);
        $app->map(['POST'], '/role/delete/[{id}]', [$this, 'delete_role']);
        $app->map(['POST'], '/update-resource/[{id}]', [$this, 'update_resource']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete',
                    'group/view', 'group/create', 'group/update', 'group/delete',
                    'staff/view', 'staff/create', 'staff/delete',
                    'role/create'
                    ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'group/view', 'staff/view'],
                'expression' => $this->hasAccess('pos/outlets/read'),
            ],
            ['allow',
                'actions' => ['create', 'group/create', 'staff/create', 'role/create'],
                'expression' => $this->hasAccess('pos/outlets/create'),
            ],
            ['allow',
                'actions' => ['update', 'group/update', 'update-resource'],
                'expression' => $this->hasAccess('pos/outlets/update'),
            ],
            ['allow',
                'actions' => ['delete', 'group/delete', 'staff/delete'],
                'expression' => $this->hasAccess('pos/outlets/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }
        
        $model = new \Model\OutletsModel();
        $outlets = $model->getData();

        // wh group
        $wgmodel = new \Model\OutletGroupsModel();
        $groups = $wgmodel->getData();

        return $this->_container->module->render(
            $response, 
            'outlets/view.html',
            [
                'outlets' => $outlets,
                'groups' => $groups
            ]
        );
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\OutletsModel();
        if (isset($_POST['Outlets'])) {
            $model->title = $_POST['Outlets']['title'];
            $model->phone = $_POST['Outlets']['phone'];
            $model->address = $_POST['Outlets']['address'];
            $model->notes = $_POST['Outlets']['notes'];
            if (isset($_POST['Outlets']['group_id']))
                $model->group_id = $_POST['Outlets']['group_id'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\OutletsModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\OutletsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\OutletsModel();
        $detail = $wmodel->getDetail($args['id']);

        // wh group
        $wgmodel = new \Model\OutletGroupsModel();
        $groups = $wgmodel->getData();

        if (isset($_POST['Outlets'])){
            $model->title = $_POST['Outlets']['title'];
            $model->phone = $_POST['Outlets']['phone'];
            $model->address = $_POST['Outlets']['address'];
            $model->notes = $_POST['Outlets']['notes'];
            if (isset($_POST['Outlets']['group_id']))
                $model->group_id = $_POST['Outlets']['group_id'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\OutletsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\OutletsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        $product_items = [];
        if (!empty($model->configs)) {
            $rmodel = new \Model\RemoteModel($model->id, 'items', 'item_id');
            $product_items = $rmodel->getRows();
        }

        return $this->_container->module->render($response, 'outlets/update.html', [
            'model' => $model,
            'detail' => $detail,
            'groups' => $groups,
            'product_items' => $product_items
        ]);
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletsModel::model()->findByPk($args['id']);
        $delete = \Model\OutletsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function view_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $wgmodel = new \Model\OutletGroupsModel();
        $groups = $wgmodel->getData();

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        return $this->_container->module->render(
            $response,
            'outlets/group_view.html',
            [
                'groups' => $groups,
                'admins' => $admins
            ]
        );
    }

    public function create_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\OutletGroupsModel();
        if (isset($_POST['OutletGroups'])) {
            $model->title = $_POST['OutletGroups']['title'];
            $model->description = $_POST['OutletGroups']['description'];
            if (isset($_POST['OutletGroups']['pic'])) {
                $pic = [];
                if (is_array($_POST['OutletGroups']['pic'])) {
                    foreach ($_POST['OutletGroups']['pic'] as $i => $admin_id) {
                        $amodel = \Model\AdminModel::model()->findByPk($admin_id);
                        if ($amodel instanceof \RedBeanPHP\OODBBean) {
                            $pic[$amodel->id] = [
                                    'name' => $amodel->name,
                                    'email' => $amodel->email
                                ];
                        }
                    }
                }
                $model->pic = json_encode($pic);
            }
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\OutletGroupsModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\OutletGroupsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\OutletGroupsModel();
        $detail = $wmodel->getDetail($args['id']);

        // admin list
        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        if (isset($_POST['OutletGroups'])){
            $model->title = $_POST['OutletGroups']['title'];
            $model->description = $_POST['OutletGroups']['description'];
            if (isset($_POST['OutletGroups']['pic'])) {
                $pic = [];
                if (is_array($_POST['OutletGroups']['pic'])) {
                    foreach ($_POST['OutletGroups']['pic'] as $i => $admin_id) {
                        $amodel = \Model\AdminModel::model()->findByPk($admin_id);
                        if ($amodel instanceof \RedBeanPHP\OODBBean) {
                            $pic[$amodel->id] = [
                                'name' => $amodel->name,
                                'email' => $amodel->email
                            ];
                        }
                    }
                }
                $model->pic = json_encode($pic);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\OutletGroupsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\OutletGroupsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'outlets/group_update.html', [
            'model' => $model,
            'detail' => $detail,
            'admins' => $admins
        ]);
    }

    public function delete_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletGroupsModel::model()->findByPk($args['id']);
        $delete = \Model\OutletGroupsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function view_staff($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $wsmodel = new \Model\OutletStaffsModel();
        $staffs = $wsmodel->getData($args['id']);

        $wmodel = \Model\OutletsModel::model()->findByPk($args['id']);

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        $rmodel = new \Model\OutletStaffRolesModel();
        $roles = $rmodel->getData();
        $rules = $rmodel->getRules();

        return $this->_container->module->render(
            $response,
            'outlets/staff_view.html',
            [
                'staffs' => $staffs,
                'admins' => $admins,
                'roles' => $roles,
                'outlet' => $wmodel,
                'rules' => $rules
            ]
        );
    }

    public function create_staff($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);
        $rmodel = new \Model\OutletStaffRolesModel();
        $roles = $rmodel->getData();

        if (isset($_POST['OutletStaffs'])) {
            $success = 0;
            foreach ($_POST['OutletStaffs']['admin_id'] as $i => $admin_id) {
                if (empty($_POST['OutletStaffs']['id'][$i])) { //create new record
                    $model[$i] = new \Model\OutletStaffsModel();
                    $model[$i]->outlet_id = $args['id'];
                    $model[$i]->admin_id = $admin_id;
                    $model[$i]->role_id = $_POST['OutletStaffs']['role_id'][$i];
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    $save = \Model\OutletStaffsModel::model()->save($model[$i]);
                    if ($save) {
                        $success = $success + 1;
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\OutletStaffsModel::model()->findByPk($_POST['OutletStaffs']['id'][$i]);
                    $pmodel[$i]->admin_id = $admin_id;
                    $pmodel[$i]->role_id = $_POST['OutletStaffs']['role_id'][$i];
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    try {
                        $update = \Model\OutletStaffsModel::model()->update($pmodel[$i]);
                        if ($update) {
                            $success = $success + 1;
                        }
                    } catch (\Exception $e) {
                        var_dump($e->getMessage()); exit;
                    }
                }
            }

            if ($success > 0)  {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => 'Tidak ada data yang berhasil disimpan.',
                    ], 201);
            }
        } else {
            return $this->_container->module->render(
                $response,
                'outlets/_form_staff_items.html',
                [
                    'show_delete_btn' => true,
                    'admins' => $admins,
                    'roles' => $roles
                ]);
        }
    }

    public function delete_staff($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletStaffsModel::model()->findByPk($_POST['id']);
        $delete = \Model\OutletStaffsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => \Model\OutletStaffsModel::model()->getErrors(),
                ], 201);
        }
    }

    public function create_role($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\OutletStaffRolesModel();
        if (isset($_POST['OutletStaffRoles'])) {
            $model->title = $_POST['OutletStaffRoles']['title'];
            $model->description = $_POST['OutletStaffRoles']['description'];
            if (isset($_POST['OutletStaffRoles']['roles'])) {
                $roles = [];
                foreach ($_POST['OutletStaffRoles']['roles'] as $role_id => $role_name) {
                    $roles[$role_id] = array_keys($role_name);
                }
                $model->roles = json_encode($roles);
            }
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\OutletStaffRolesModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update_role($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletStaffRolesModel::model()->findByPk($args['id']);
        $rmodel = new \Model\OutletStaffRolesModel();
        $rules = $rmodel->getRules();

        if (isset($_POST['OutletStaffRoles'])) {
            $model->title = $_POST['OutletStaffRoles']['title'];
            $model->description = $_POST['OutletStaffRoles']['description'];
            if (isset($_POST['OutletStaffRoles']['roles'])) {
                $roles = [];
                foreach ($_POST['OutletStaffRoles']['roles'] as $role_id => $role_name) {
                    $roles[$role_id] = array_keys($role_name);
                }
                $model->roles = json_encode($roles);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            try {
                $save = \Model\OutletStaffRolesModel::model()->update($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }

        return $this->_container->module->render(
            $response,
            'outlets/_role_form.html',
            [
                'model' => $model,
                'rules' => $rules
            ]);
    }

    public function delete_role($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletStaffRolesModel::model()->findByPk($_POST['id']);
        $delete = \Model\OutletStaffRolesModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => \Model\OutletStaffRolesModel::model()->getErrors(),
                ], 201);
        }
    }

    public function update_resource($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\OutletsModel::model()->findByPk($args['id']);

        if (isset($_POST['Outlets'])) {
            $configs = [];
            if (!empty($model->configs)) {
                $configs = json_decode($model->configs, true);
            }
            $configs['db'] = $_POST['Outlets'];
            $model->configs = json_encode($configs);
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            try {
                $save = \Model\OutletsModel::model()->update($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }
}