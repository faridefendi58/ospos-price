<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class ReportsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/stock', [$this, 'stock']);
        $app->map(['GET'], '/activity', [$this, 'activity']);
        $app->map(['GET', 'POST'], '/stock-info/[{id}]', [$this, 'stock_info']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['stock'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['stock'],
                'expression' => $this->hasAccess('pos/reports/read'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function stock($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $params = $request->getParams();
        $data = ['date_start' => date("Y-m-01"), 'date_end' => date("Y-m-d")];
        if (isset($params['start']))
            $data['date_start'] = date("Y-m-d", $params['start']/1000);
        if (isset($params['end']))
            $data['date_end'] = date("Y-m-d", $params['end']/1000);

        $outlets = \Model\OutletsModel::model()->findAll();
        if (isset($params['outlet'])) {
            $outlet = \Model\OutletsModel::model()->findByPk($params['outlet']);
        } else {
            $outlet = \Model\OutletsModel::model()->findByAttributes(['active' => 1]);
        }

        return $this->_container->module->render(
            $response, 
            'reports/stock.html',
            [
                'outlets' => $outlets,
                'outlet' => $outlet
            ]
        );
    }

    public function stock_info($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $rmodel = new \Model\RemoteModel($args['id'], 'item_quantities', 'item_id');
        $stocks = $rmodel->getItemStocks(['outlet_id' => $args['id']]);

        return $response->withJson($stocks, 201);
    }
}