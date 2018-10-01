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
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{name}]', [$this, 'delete']);
        $app->map(['POST'], '/price-info/[{id}]', [$this, 'price_info']);
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
                'actions' => ['update'],
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
        //$rmodel = new \Model\RemoteModel($outlets[$ids[0]]->id, 'items', 'item_id');
        //$product_items = $rmodel->getRows();

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

        $model = new \Model\ProductsModel();
        if (isset($_POST['Products'])) {
            $model->title = $_POST['Products']['title'];
            $model->product_category_id = $_POST['Products']['product_category_id'];
            if (!empty($_POST['Products']['unit']))
                $model->unit = $_POST['Products']['unit'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\ProductsModel::model()->save($model);
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

        if (isset($_POST['Products'])) {
            $_POST['Products']['cost_price'] = $this->money_unformat($_POST['Products']['cost_price']);
            $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['unit_price']);

            $model->item_number = $_POST['Products']['item_number'];
            $model->name = $_POST['Products']['name'];
            $model->cost_price = $_POST['Products']['cost_price'];
            $model->unit_price = $_POST['Products']['unit_price'];
            $model->description = $_POST['Products']['description'];

            $update = $item_model->update($model, false, ['item_number', 'name', 'cost_price', 'unit_price', 'description']);
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
            'outlet' => $outlet
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

        $model = \Model\ProductsModel::model()->findByPk($args['name']);
        $delete = \Model\ProductsModel::model()->delete($model);
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

        $rmodel = new \Model\RemoteModel($_POST['id'], 'items', 'item_id');
        $product_items = $rmodel->getRows();

        return $this->_container->module->render($response, 'products/_price.html', [
            'product_items' => $product_items,
            'outlet_id' => $_POST['id']
        ]);
    }
}