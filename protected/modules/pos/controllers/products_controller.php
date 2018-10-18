<?php

namespace Pos\Controllers;

use Complex\Exception;
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
        $app->map(['GET'], '/view-items', [$this, 'view_items']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET', 'POST'], '/price-info/[{id}]', [$this, 'price_info']);
        $app->map(['GET', 'POST'], '/price-info-detail/[{id}]', [$this, 'price_info_detail']);
        $app->map(['POST'], '/add-stock/[{id}]', [$this, 'add_stock']);
        $app->map(['POST'], '/excel-import', [$this, 'excel_import']);
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
                'actions' => ['view', 'view-items', 'price-info', 'price-info-detail'],
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
            if (!isset($_POST['Products']['unit_price'])) {
                $_POST['Products']['unit_price'] = $_POST['Products']['cost_price'];
            } else {
                $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['unit_price']);
            }
            if (isset($_POST['Products']['HJU'])) {
                $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['HJU']);
                $_POST['Products']['HJU'] = $this->money_unformat($_POST['Products']['HJU']);
            }
            if (isset($_POST['Products']['HJD'])) {
                $_POST['Products']['HJD'] = $this->money_unformat($_POST['Products']['HJD']);
            }
            if (isset($_POST['Products']['HJR'])) {
                $_POST['Products']['HJR'] = $this->money_unformat($_POST['Products']['HJR']);
            }

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

                // update the price list, HJD, HJR
                try {
                    $item_model2 = new \Model\RemoteModel($_POST['Products']['outlet_id'], 'items', 'item_id' );
                    $item_data = $item_model2->findByAttributes(['item_number' => $_POST['Products']['item_number']]);
                    if ($item_data instanceof \RedBeanPHP\OODBBean) {
                        $update_price = $this->update_price_list_item($_POST, $_POST['Products']['outlet_id'], $item_data->item_id);
                    }
                } catch (Exception $e) {}

                if (isset($_POST['Products']['create_others'])) {
                    foreach ($outlets as $o => $ot) {
                        if ($ot['id'] != $_POST['Products']['outlet_id']) {
                            $item_model2 = new \Model\RemoteModel($ot['id'], 'items', 'item_id' );
                            $item_model2->item_number = $_POST['Products']['item_number'];
                            $item_model2->name = $_POST['Products']['name'];
                            $item_model2->cost_price = $_POST['Products']['cost_price'];
                            $item_model2->unit_price = $_POST['Products']['unit_price'];
                            $item_model2->description = $_POST['Products']['description'];

                            $save2 = $item_model2->save();
                            if ($save2) {
                                if ($_POST['Products']['quantity'] > 0) {
                                    $qty_model = new \Model\RemoteModel($ot['id'], 'item_quantities', 'item_id' );
                                    $item_data = $item_model->findByAttributes(['item_number' => $_POST['Products']['item_number']]);
                                    $qty_model->item_id = $item_data['item_id'];
                                    $qty_model->location_id = 1;
                                    $qty_model->quantity = $_POST['Products']['quantity'];
                                    $save2 = $qty_model->save();
                                    if ($save2) {
                                        $inv_model = new \Model\RemoteModel($ot['id'], 'inventory', 'trans_id' );
                                        $inv_model->trans_items = $item_data['item_id'];
                                        $inv_model->trans_user = 1;
                                        $inv_model->trans_date = date("Y-m-d H:i:s");
                                        $inv_model->trans_comment = 'Input dari integrator system.';
                                        $inv_model->trans_location = 1;
                                        $inv_model->trans_inventory = $_POST['Products']['quantity'];
                                        $save3 = $inv_model->save();
                                    }
                                }

                                // update the price list, HJD, HJR
                                try {
                                    $item_model2 = new \Model\RemoteModel($_POST['Products']['outlet_id'], 'items', 'item_id' );
                                    $item_data = $item_model2->findByAttributes(['item_number' => $_POST['Products']['item_number']]);
                                    if ($item_data instanceof \RedBeanPHP\OODBBean) {
                                        $update_price = $this->update_price_list_item($_POST, $ot['id'], $item_data->item_id);
                                    }
                                } catch (Exception $e) {}
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

        $prices = ['HJU' => 0, 'HJR' => 0, 'HJD' => 0];

        return $this->_container->module->render($response, 'products/create.html', [
            'outlets' => $outlets,
            'prices' => $prices
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
            if (isset($_POST['Products']['HJU'])) {
                $_POST['Products']['unit_price'] = $this->money_unformat($_POST['Products']['HJU']);
                $_POST['Products']['HJU'] = $this->money_unformat($_POST['Products']['HJU']);
            }
            if (isset($_POST['Products']['HJD'])) {
                $_POST['Products']['HJD'] = $this->money_unformat($_POST['Products']['HJD']);
            }
            if (isset($_POST['Products']['HJR'])) {
                $_POST['Products']['HJR'] = $this->money_unformat($_POST['Products']['HJR']);
            }


            $model->item_number = $_POST['Products']['item_number'];
            $model->name = $_POST['Products']['name'];
            $model->cost_price = $_POST['Products']['cost_price'];
            $model->unit_price = $_POST['Products']['unit_price'];
            $model->description = $_POST['Products']['description'];
            $item_model3 = \Model\RemoteModel::model($outlet_id, 'items', 'item_id');

            $update = $item_model3->update($model, false, ['item_number', 'name', 'cost_price', 'unit_price', 'description']);
            if (!empty($model->name) && !empty($model->item_number) && !empty($model->cost_price)) {

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

                // update the price list, HJD, HJR
                try {
                    if (isset($_POST['Products']['update_others'])) {
                        foreach ($outlets as $o => $ot) {
                            $update_price = $this->update_price_list_item($_POST, $ot->id, $product_id);
                        }
                    } else {
                        $update_price = $this->update_price_list_item($_POST, $outlet_id, $product_id);
                    }
                } catch (Exception $e) {}

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

        $pli_model = new \Model\RemoteModel($outlet_id, 'price_list_items', 'item_id');
        $prices = $pli_model->getProductPrices(['item_id' => $product_id]);

        if (is_array($prices[$product_id]) && in_array($product_id, array_keys($prices))) {
            $prices = $prices[$product_id];
        } else {
            $prices = ['HJU' => $model->unit_price, 'HJR' => $model->unit_price, 'HJD' => $model->unit_price];
        }

        return $this->_container->module->render($response, 'products/update.html', [
            'model' => $model,
            'outlet' => $outlet,
            'outlets' => $outlets,
            'smodel' => $smodel,
            'histories' => $histories,
            'prices' => $prices,
            'hide_outlet' => (isset($request->getParams()['hide_outlet']))? $request->getParams()['hide_outlet'] : 0
        ]);
    }

    private function update_price_list_item($post, $outlet_id, $product_id)
    {
        $it_model = \Model\RemoteModel::model($outlet_id, 'items', 'item_id');
        $the_it_model = $it_model->findByPk($product_id);
        if (!$the_it_model instanceof \RedBeanPHP\OODBBean) {
            return false;
        }

        if (isset($post['Products']['HJD']) || isset($post['Products']['HJR'])) {
            // also update the HJD and HJR
            if (!empty($post['Products']['HJD'])) {
                $pl_model = \Model\RemoteModel::model($outlet_id, 'price_lists', 'id');
                $hjd_model = $pl_model->findByAttributes(['code' => 'HJD']);
                if ($hjd_model instanceof \RedBeanPHP\OODBBean) {
                    $pli_model = \Model\RemoteModel::model($outlet_id, 'price_list_items', 'id');
                    $hjdi_model = $pli_model->findByAttributes(['price_list_id' => $hjd_model->id, 'item_id' => $product_id]);
                    if ($hjdi_model instanceof \RedBeanPHP\OODBBean) {
                        $hjdi_model->unit_price = $post['Products']['HJD'];
                        $hjdi_model->updated_at = date("Y-m-d H:i:s");
                        $update_hjdi = $pli_model->update($hjdi_model, false, ['unit_price', 'updated_at']);
                    } else {
                        $new_pli_model = new \Model\RemoteModel($outlet_id, 'price_list_items', 'id' );
                        $new_pli_model->price_list_id = $hjd_model->id;
                        $new_pli_model->item_id = $product_id;
                        $new_pli_model->unit_price = $_POST['Products']['HJD'];
                        $new_pli_model->created_at = date("Y-m-d H:i:s");
                        $new_pli_model->updated_at = date("Y-m-d H:i:s");

                        $save_new_pli = $new_pli_model->save();
                    }
                }
            }
            if (!empty($post['Products']['HJR'])) {
                $pl_model = \Model\RemoteModel::model($outlet_id, 'price_lists', 'id');
                $hjr_model = $pl_model->findByAttributes(['code' => 'HJR']);
                if ($hjr_model instanceof \RedBeanPHP\OODBBean) {
                    $pli_model = \Model\RemoteModel::model($outlet_id, 'price_list_items', 'id');
                    $hjri_model = $pli_model->findByAttributes(['price_list_id' => $hjr_model->id, 'item_id' => $product_id]);
                    if ($hjri_model instanceof \RedBeanPHP\OODBBean) {
                        $hjri_model->unit_price = $post['Products']['HJR'];
                        $hjri_model->updated_at = date("Y-m-d H:i:s");
                        $update_hjri = $pli_model->update($hjri_model, false, ['unit_price', 'updated_at']);
                    } else {
                        $new_pli_model = new \Model\RemoteModel($outlet_id, 'price_list_items', 'id' );
                        $new_pli_model->price_list_id = $hjr_model->id;
                        $new_pli_model->item_id = $product_id;
                        $new_pli_model->unit_price = $post['Products']['HJR'];
                        $new_pli_model->created_at = date("Y-m-d H:i:s");
                        $new_pli_model->updated_at = date("Y-m-d H:i:s");

                        $save_new_pli = $new_pli_model->save();
                    }
                }
            }
        }

        return true;
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $product_id = $args['id'];
        $outlet_id = $request->getParams()['outlet'];

        $model = \Model\RemoteModel::model($outlet_id, 'items', 'item_id');
        $item = $model->findByPk($product_id);
        $deleted = false;
        if ($item instanceof \RedBeanPHP\OODBBean) {
            $item->deleted = 1;
            $deleted = $model->update($item, false, ['deleted']);
        }

        if ($deleted) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => 'Data gagal dihapus.',
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

    public function price_info_detail($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $pli_model = new \Model\RemoteModel($args['id'], 'price_list_items', 'item_id');
        $prices = $pli_model->getProductPrices();

        $rmodel = new \Model\RemoteModel($args['id'], 'items', 'item_id');
        $product_items = $rmodel->getProducts(['deleted' => 0, 'outlet_id' => $args['id']]);
        $items = [];
        foreach ($product_items as $i => $product_item) {
            $item_data = $product_item;
            if (is_array($prices[$product_item['item_id']])) {
                $item_data = array_merge($item_data, $prices[$product_item['item_id']]);
            } else {
                $prcs = [
                    'HJU' => $product_item['unit_price'],
                    'HJD' => $product_item['unit_price'],
                    'HJR' => $product_item['unit_price'],
                    ];
                $item_data = array_merge($item_data, $prcs);
            }
            $items[$i] = $item_data;
        }

        return $response->withJson($items, 201);
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

    public function view_items($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $outlet = \Model\OutletsModel::model()->findByAttributes(['active' => 1]);

        return $this->_container->module->render(
            $response,
            'products/view_items.html',
            [
                'outlet' => $outlet
            ]
        );
    }

    public function excel_import($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $result = ['status' => 'failed', 'preview' => '', 'reset_form' => 0];

        $uploadedFiles = $request->getUploadedFiles();
        if (isset($uploadedFiles['Pricelists'])) {
            $file = $uploadedFiles['Pricelists']['file_path'];
            //getting file name = $file->getClientFilename()
            $renderType = 'Xlsx';
            if ($file->getClientMediaType() == 'application/vnd.oasis.opendocument.spreadsheet') {
                $renderType = 'Ods';
            } elseif ($file->getClientMediaType() == 'application/vnd.ms-excel') {
                $renderType = 'Xls';
            } elseif ($file->getClientMediaType() == 'text/csv') {
                $renderType = 'Csv';
            }
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($renderType);
            $reader->setReadDataOnly(TRUE);
            $spreadsheet = $reader->load($file->file);

            $worksheet = $spreadsheet->getActiveSheet();

            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $table_header = '';
            if ($_POST['Pricelists']['total_rows'] == 0) {
                $table_header .= '<table id="table-preview" data-pagination="true" data-search="true"><thead>' . "\n";
                $table_header .= '<tr>' . PHP_EOL;
                for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                    $value = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
                    $table_header .= '<th data-field="'.$col.'">' . $value . '</th>' . PHP_EOL;
                }
                $table_header .= '</tr>' . PHP_EOL;
                $table_header .= '</thead></table>' . PHP_EOL;
            }

            $rows = [];
            $preview = '<table>' . "\n";
            for ($row = 1; $row <= $highestRow; ++$row) {
                $preview .= '<tr>' . PHP_EOL;
                for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                    $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                    $preview .= '<td>' . $value . '</td>' . PHP_EOL;
                    if ($row > 1)
                        $rows[$row][$col] = $value;
                }
                $preview .= '</tr>' . PHP_EOL;
            }
            $preview .= '</table>' . PHP_EOL;

            if (count($rows) > 0) {
                $result['status'] = 'success';
                $result['message'] = "Ditemukan ".count($rows)." baris data pada dokumen ". $file->getClientFilename().
                    ". Selanjutnya klik tombol <b>Simpan Sekarang</b> agar data dapat disimpan ke dalam database.";
                if (!empty($table_header))
                    $result['preview'] = $table_header;
            } else {
                $result['status'] = 'failed';
                $result['message'] = "Tidak ditemukan data pada dokumen ". $file->getClientFilename() .".";
            }
            $result['reset_form'] = 0;

            $result['rows'] = array_values($rows);
            if ($_POST['Pricelists']['total_rows'] > 0) {
                $outlet_id = $request->getParams()['outlet'];
                $omodel = new \Model\OutletsModel();
                $outlets = $omodel->getRows(['active' => 1]);

                $i_model = new \Model\RemoteModel($outlet_id, 'items');

                $save_execution = 0;
                foreach ($result['rows'] as $i => $rdata) {
                    if (!empty($rdata[1]) && $rdata[5] > 0) {
                        $item = $i_model->findByAttributes(['item_number' => $rdata[1]]);
                        // just save to all active outlet
                        foreach ($outlets as $oi => $ot) {
                            if ($item instanceof \RedBeanPHP\OODBBean) {
                                try {
                                    $this->save_the_price_list_items($rdata, $ot['id'], $item->item_id);
                                    $save_execution = $save_execution + 1;
                                } catch (Exception $e) {}
                            } else { //create new items
                                try {
                                    $new_item_id = $this->save_the_items($rdata, $ot['id']);
                                    if ($new_item_id > 0) {
                                        $this->save_the_price_list_items($rdata, $ot['id'], $new_item_id);
                                        $save_execution = $save_execution + 1;
                                    }
                                } catch (Exception $e) {}
                            }
                        }
                    }
                }
                $result['status'] = ($save_execution>0)? 'success' : 'failed';
                $result['message'] = $_POST['Pricelists']['total_rows'].' data obat berhasil disimpan';
                $result['reset_form'] = 1;
            }

            return $response->withJson( $result, 201 );
        }
    }

    private function save_the_price_list_items($data, $outlet_id, $product_id)
    {
        if (!empty($data[7])) {
            $pl_model = \Model\RemoteModel::model($outlet_id, 'price_lists', 'id');
            $hjd_model = $pl_model->findByAttributes(['code' => 'HJD']);
            if ($hjd_model instanceof \RedBeanPHP\OODBBean) {
                $pli_model = \Model\RemoteModel::model($outlet_id, 'price_list_items', 'id');
                $hjdi_model = $pli_model->findByAttributes(['price_list_id' => $hjd_model->id, 'item_id' => $product_id]);
                if ($hjdi_model instanceof \RedBeanPHP\OODBBean) {
                    $hjdi_model->unit_price = $this->money_unformat($data[7]);
                    $hjdi_model->updated_at = date("Y-m-d H:i:s");
                    $update_hjdi = $pli_model->update($hjdi_model, false, ['unit_price', 'updated_at']);
                } else {
                    $new_pli_model = new \Model\RemoteModel($outlet_id, 'price_list_items', 'id' );
                    $new_pli_model->price_list_id = $hjd_model->id;
                    $new_pli_model->item_id = $product_id;
                    $new_pli_model->unit_price = $this->money_unformat($data[7]);
                    $new_pli_model->created_at = date("Y-m-d H:i:s");
                    $new_pli_model->updated_at = date("Y-m-d H:i:s");

                    $save_new_pli = $new_pli_model->save();
                }
            }
        }
        if (!empty($data[8])) {
            $pl_model = \Model\RemoteModel::model($outlet_id, 'price_lists', 'id');
            $hjd_model = $pl_model->findByAttributes(['code' => 'HJD']);
            if ($hjd_model instanceof \RedBeanPHP\OODBBean) {
                $pli_model = \Model\RemoteModel::model($outlet_id, 'price_list_items', 'id');
                $hjdi_model = $pli_model->findByAttributes(['price_list_id' => $hjd_model->id, 'item_id' => $product_id]);
                if ($hjdi_model instanceof \RedBeanPHP\OODBBean) {
                    $hjdi_model->unit_price = $this->money_unformat($data[8]);
                    $hjdi_model->updated_at = date("Y-m-d H:i:s");
                    $update_hjdi = $pli_model->update($hjdi_model, false, ['unit_price', 'updated_at']);
                } else {
                    $new_pli_model = new \Model\RemoteModel($outlet_id, 'price_list_items', 'id' );
                    $new_pli_model->price_list_id = $hjd_model->id;
                    $new_pli_model->item_id = $product_id;
                    $new_pli_model->unit_price = $this->money_unformat($data[8]);
                    $new_pli_model->created_at = date("Y-m-d H:i:s");
                    $new_pli_model->updated_at = date("Y-m-d H:i:s");

                    $save_new_pli = $new_pli_model->save();
                }
            }
        }
    }

    private function save_the_items($data, $outlet_id)
    {
        $item_model = new \Model\RemoteModel($outlet_id, 'items', 'item_id' );
        $item_model->item_number = $data[1];
        $item_model->name = $data[2];
        $item_model->cost_price = $this->money_unformat($data[5]);
        $item_model->unit_price = $this->money_unformat($data[6]);

        $save = $item_model->save();
        if ($save) {
            $qty_model = new \Model\RemoteModel($outlet_id, 'item_quantities', 'item_id' );
            $item_data = $item_model->findByAttributes(['item_number' => $data[1]]);
            if ($data[3] > 0) {
                $qty_model->item_id = $item_data['item_id'];
                $qty_model->location_id = 1;
                $qty_model->quantity = $data[3];
                $save2 = $qty_model->save();
                if ($save2) {
                    $inv_model = new \Model\RemoteModel($outlet_id, 'inventory', 'trans_id' );
                    $inv_model->trans_items = $item_data['item_id'];
                    $inv_model->trans_user = 1;
                    $inv_model->trans_date = date("Y-m-d H:i:s");
                    $inv_model->trans_comment = 'Input dari integrator system.';
                    $inv_model->trans_location = 1;
                    $inv_model->trans_inventory = $data[3];
                    $save3 = $inv_model->save();
                }
            }

            return $item_data['item_id'];
        }

        return 0;
    }
}