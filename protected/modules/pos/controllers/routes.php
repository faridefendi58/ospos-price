<?php
// pos routes
$app->get('/pos', function ($request, $response, $args) use ($user) {
	if ($user->isGuest()){
        return $response->withRedirect('/pos/default/login');
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

    $rmodel = new \Model\RemoteModel($outlet->id, 'sales_items', 'sale_id');
    $revenue = [ 'name' => $outlet->name, 'data' => $rmodel->getRevenue($data) ];

    $smodel = new \Model\RemoteModel($outlet->id, 'sales', 'sale_id');
    $tot_transaction = $smodel->getTotalTransaction($data);
    $transactions = $smodel->getTransactionItems(array_merge($data, ['limit' => 10, 'product_only' => true]));

	return $this->module->render($response, 'default/index.html', [
	    'params' => $data,
        'name' => $args['name'],
        'revenue' => $revenue,
        'tot_transaction' => $tot_transaction,
        'transactions' => $transactions,
        'outlets' => $outlets,
        'outlet' => $outlet,
    ]);
});

foreach (glob(__DIR__.'/*_controller.php') as $controller) {
	$cname = basename($controller, '.php');
	if (!empty($cname)) {
		require_once $controller;
	}
}

foreach (glob(__DIR__.'/../components/*.php') as $component) {
    $cname = basename($component, '.php');
    if (!empty($cname)) {
        require_once $component;
    }
}

$app->group('/pos', function () use ($user) {
    $this->group('/default', function() use ($user) {
        new Pos\Controllers\DefaultController($this, $user);
    });
    $this->group('/users', function() use ($user) {
        new Pos\Controllers\UsersController($this, $user);
    });
    $this->group('/params', function() use ($user) {
        new Pos\Controllers\ParamsController($this, $user);
    });
    $this->group('/extensions', function() use ($user) {
        new Pos\Controllers\ExtensionsController($this, $user);
    });
    $this->group('/products', function() use ($user) {
        new Pos\Controllers\ProductsController($this, $user);
    });
    $this->group('/outlets', function() use ($user) {
        new Pos\Controllers\OutletsController($this, $user);
    });
    $this->group('/reports', function() use ($user) {
        new Pos\Controllers\ReportsController($this, $user);
    });
    $this->group('/pricelists', function() use ($user) {
        new Pos\Controllers\PricelistsController($this, $user);
    });
});

?>
