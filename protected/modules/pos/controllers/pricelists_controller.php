<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class PricelistsController extends BaseController
{
    protected $_login_url = '/pos/default/login';

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['GET', 'POST'], '/price-list-data/[{id}]', [$this, 'price_list_data']);
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
                'expression' => $this->hasAccess('pos/pricelists/read'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('pos/pricelists/update'),
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

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $outlets = \Model\OutletsModel::model()->findAll();
        $keys = array_keys($outlets);

        $price_model = new \Model\RemoteModel( $keys[0], 'price_lists' );
        $pricelists = $price_model->getRows();

        return $this->_container->module->render(
            $response,
            'pricelists/view.html',
            [
                'outlets' => $outlets,
                'pricelists' => $pricelists
            ]
        );
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $price_list_id = $args['id'];
        $outlet_id = $request->getParams()['outlet'];

        if (empty($price_list_id) || empty($outlet_id)) {
            return $this->_container['response']
                ->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write('Product or Outlet not found !');
        }

        $outlet = \Model\OutletsModel::model()->findByPk($outlet_id);
        $outlets = \Model\OutletsModel::model()->findAll();

        $price_model = new \Model\RemoteModel( $outlet->id, 'price_lists' );
        $pricelist = $price_model->findByAttributes(['id' => $price_list_id]);

        $price_list_model = new \Model\RemoteModel( $outlet->id, 'price_list_items' );
        $pricelist_items = [];//$price_list_model->getRows(['price_list_id' => $price_list_id]);

        return $this->_container->module->render(
            $response,
            'pricelists/update.html',
            [
                'outlet' => $outlet,
                'outlets' => $outlets,
                'pricelist' => $pricelist,
                'pricelist_items' => $pricelist_items
            ]
        );
    }

    public function price_list_data($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $outlet_id = $request->getParams()['outlet'];

        $rmodel = new \Model\RemoteModel($args['id'], 'price_list_items');
        $product_items = $rmodel->getPriceListItems(['outlet_id' => $outlet_id, 'price_list_id' => $args['id']]); //$rmodel->getProducts(['deleted' => 0]);

        return $response->withJson($product_items, 201);
    }
}