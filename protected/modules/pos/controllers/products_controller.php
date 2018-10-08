<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class ProductsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{name}]', [$this, 'delete']);
        $app->map(['GET', 'POST'], '/price-info/[{id}]', [$this, 'price_info']);
        $app->map(['POST'], '/add-stock/[{id}]', [$this, 'add_stock']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/products/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/products/create'),
            ],
            ['allow',
                'actions' => ['update', 'add-stock'],
                'expression' => $this->hasAccess('pos/products/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/products/delete'),
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

        $outlets = \Model\OutletsModel::model()->findAll();
        $ids = array_keys($outlets);
        $product_items = [];

        return $this->_container->module->render(
            $response, 
            'products/view.html', 
            [
                'outlets' => $outlets,
                'product_items' => $product_items
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

        $omodel = new \Model\OutletsModel();
        $outlets = $omodel->getRows(['active' => 1]);
        if (isset($_POST['Products'])) {
            $_POST['Products']['cost_price'] = $this->money_unformat($_POST['Products']['cost_price']);
            $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['unit_price']);

            $item_model = new \Model\RemoteModel($_POST['Products']['outlet_id'], 'items', 'item_id' );
            $item_model->item_number = $_POST['Products']['item_number'];
            $item_model->name = $_POST['Products']['name'];
            $item_model->cost_price = $_POST['Products']['cost_price'];
            $item_model->unit_price = $_POST['Products']['unit_price'];
            $item_model->description = $_POST['Products']['description'];

            $save = $item_model->save();
            if ($save) {
                if ($_POST['Products']['quantity'] > 0) {
                    $qty_model = new \Model\RemoteModel($_POST['Products']['outlet_id'], 'item_quantities', 'item_id' );
                    $item_data = $item_model->findByAttributes(['item_number' => $_POST['Products']['item_number']]);
                    $qty_model->item_id = $item_data['item_id'];
                    $qty_model->location_id = 1;
                    $qty_model->quantity = $_POST['Products']['quantity'];
                    $save2 = $qty_model->save();
                    if ($save2) {
                        $inv_model = new \Model\RemoteModel($_POST['Products']['outlet_id'], 'inventory', 'trans_id' );
                        $inv_model->trans_items = $item_data['item_id'];
                        $inv_model->trans_user = 1;
                        $inv_model->trans_date = date("Y-m-d H:i:s");
                        $inv_model->trans_comment = 'Input dari integrator system.';
                        $inv_model->trans_location = 1;
                        $inv_model->trans_inventory = $_POST['Products']['quantity'];
                        $save3 = $inv_model->save();
                    }
                }

                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(
                    [
                        'status' => 'failed'
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'products/create.html', [
            'outlets' => $outlets
        ]);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $outlets = \Model\OutletsModel::model()->findAll();

        $product_id = $args['id'];
        $outlet_id = $request->getParams()['outlet'];

        if (empty($product_id) || empty($outlet_id)) {
            return $this->_container['response']
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Product or Outlet not found !');
        }

        $outlet = \Model\OutletsModel::model()->findByPk($outlet_id);
        $item_model = \Model\RemoteModel::model($outlet_id, 'items', 'item_id');
        $model = $item_model->findByPk($product_id);
        if (!$model instanceof \RedBeanPHP\OODBBean) {
            return $this->_container['response']
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Product or Outlet not found !');
        }

        $stok_model = \Model\RemoteModel::model($outlet_id, 'item_quantities', 'item_id');
        $smodel = $stok_model->findByAttributes(['item_id' => $product_id]);
        // stocking history
        $invent_model = \Model\RemoteModel::model($outlet_id, 'inventory', 'trans_id');
        $histories = $invent_model->getTableRows(['limit' => 5]);

        if (isset($_POST['Products'])) {
            $_POST['Products']['cost_price'] = $this->money_unformat($_POST['Products']['cost_price']);
            $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['unit_price']);

            $model->item_number = $_POST['Products']['item_number'];
            $model->name = $_POST['Products']['name'];
            $model->cost_price = $_POST['Products']['cost_price'];
            $model->unit_price = $_POST['Products']['unit_price'];
            $model->description = $_POST['Products']['description'];
            $item_model3 = \Model\RemoteModel::model($outlet_id, 'items', 'item_id');

            $update = $item_model3->update($model, false, ['item_number', 'name', 'cost_price', 'unit_price', 'description']);
            if ($update) {
                if (isset($_POST['Products']['update_others'])) {
                    foreach ($outlets as $o => $ot) {
                        if ($ot->id != $outlet_id) {
                            $item_model2 = \Model\RemoteModel::model($ot->id, 'items', 'item_id');
                            $model2 = $item_model2->findByPk($product_id);

                            if ($model2 instanceof \RedBeanPHP\OODBBean) {
                                $model2->item_number = $_POST['Products']['item_number'];
                                $model2->name = $_POST['Products']['name'];
                                $model2->cost_price = $_POST['Products']['cost_price'];
                                $model2->unit_price = $_POST['Products']['unit_price'];
                                $model2->description = $_POST['Products']['description'];

                                $update2 = $item_model2->update($model2, false, ['item_number', 'name', 'cost_price', 'unit_price', 'description']);
                            }
                        }
                    }
                }

                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(
                    [
                        'status' => 'failed'
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'products/update.html', [
            'model' => $model,
            'outlet' => $outlet,
            'outlets' => $outlets,
            'smodel' => $smodel,
            'histories' => $histories
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

        if (!isset($args['name'])) {
            return false;
        }

        $delete = false;
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function price_info($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $rmodel = new \Model\RemoteModel($args['id'], 'items', 'item_id');
        $product_items = $rmodel->getProducts(['deleted' => 0, 'outlet_id' => $args['id']]);

        return $response->withJson($product_items, 201);
    }

    public function add_stock($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $outlets = \Model\OutletsModel::model()->findAll();

        $product_id = $args['id'];
        $outlet_id = $request->getParams()['outlet'];

        $result = ['status' => 'failed'];
        if (isset($_POST['Products'])) {
            if (!empty($_POST['Products']['title']) && !empty($_POST['Products']['quantity'])) {
                $qty_model = \Model\RemoteModel::model($outlet_id, 'item_quantities', 'item_id');
                $model = $qty_model->findByPk($product_id);

                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $model->quantity = $model->quantity + $_POST['Products']['quantity'];

                    $update = $qty_model->update($model, false, ['quantity']);
                    if ($update) {
                        $model2 = new \Model\RemoteModel($outlet_id, 'inventory', 'trans_id' );
                        $model2->trans_items = $product_id;
                        $model2->trans_user = 1;
                        $model2->trans_date = date("Y-m-d H:i:s");
                        $model2->trans_comment = $_POST['Products']['title'];
                        $model2->trans_location = 1;
                        $model2->trans_inventory = $_POST['Products']['quantity'];
                        $save = $model2->save();

                        $result['status'] = 'success';
                        $result['message'] = 'Data berhasil disimpan.';
                    }
                }
            } else {
                $result['status'] = 'failed';
                $result['message'] = 'Judul dan jumlah barang tidak boleh kosong.';
            }
        }

        return $response->withJson( $result, 201 );
    }
}