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
        $app->map(['POST'], '/excel-import/[{id}]', [$this, 'exel_import']);
        $app->map(['GET', 'POST'], '/update-item/[{id}]', [$this, 'update_item']);
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
                'actions' => ['update', 'excel-import', 'update-item'],
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
        $pricelist_items = [];

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

        $rmodel = new \Model\RemoteModel($outlet_id, 'price_list_items');
        $product_items = $rmodel->getPriceListItems(['outlet_id' => $outlet_id, 'price_list_id' => $args['id']]); //$rmodel->getProducts(['deleted' => 0]);

        return $response->withJson($product_items, 201);
    }

    public function exel_import($request, $response, $args)
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
                $price_list_id = $args['id'];
                $i_model = new \Model\RemoteModel($outlet_id, 'items');
                $price_list_model = new \Model\RemoteModel( $outlet_id, 'price_list_items' );
                $save_execution = 0;
                foreach ($result['rows'] as $i => $rdata) {
                    if (!empty($rdata[3]) && $rdata[5] > 0) {
                        $item = $i_model->findByAttributes(['item_number' => $rdata[3]]);
                        if ($item instanceof \RedBeanPHP\OODBBean) {
                            $pi_model = $price_list_model->findByAttributes(['price_list_id' => $price_list_id, 'item_id' => $item->item_id]);
                            if ($pi_model instanceof \RedBeanPHP\OODBBean) {
                                $pi_model->unit_price = $this->money_unformat($rdata[5]);
                                $pi_model->updated_at = date("Y-m-d H:i:s");
                                $update = $price_list_model->update($pi_model, false, ['unit_price', 'updated_at']);
                                if ($update) {
                                    $save_execution = $save_execution + 1;
                                }
                            }
                        }
                    }
                }
                $result['status'] = ($save_execution>0)? 'success' : 'failed';
                $result['message'] = $save_execution.' baris dari total '.$_POST['Pricelists']['total_rows'].' berhasil disimpan';
                $result['reset_form'] = 1;
            }

            return $response->withJson( $result, 201 );
        }
    }

    public function update_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $outlet_id = $request->getParams()['outlet'];
        $price_list_id = $args['id'];

        $outlet = \Model\OutletsModel::model()->findByPk($outlet_id);
        $li_model = new \Model\RemoteModel($outlet_id, 'price_list_items');
        $price_list_item = $li_model->findByPk($price_list_id);
        if (!$price_list_item instanceof \RedBeanPHP\OODBBean) {

        }

        $updated = false;
        if (isset($_POST['PricelistItems'])) {
            $price_list_item->unit_price = $this->money_unformat($_POST['PricelistItems']['unit_price']);
            $price_list_item->updated_at = date("Y-m-d H:i:s");
            $updated = $li_model->update($price_list_item, ['unit_price', 'updated_at']);
        }

        $pl_model = new \Model\RemoteModel($outlet_id, 'price_lists');
        $price_list = $pl_model->findByPk($price_list_item->price_list_id);

        $i_model = new \Model\RemoteModel($outlet_id, 'items', 'item_id');
        $item = $i_model->findByPk($price_list_item->item_id);

        return $this->_container->module->render(
            $response,
            'pricelists/update_item.html',
            [
                'outlet' => $outlet,
                'price_list_item' => $price_list_item,
                'price_list' => $price_list,
                'item' => $item,
                'updated' => $updated
            ]
        );
    }
}