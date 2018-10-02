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
        $app->map(['POST'], '/excel-import', [$this, 'exel_import']);
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

        $rmodel = new \Model\RemoteModel($args['id'], 'price_list_items');
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

        $result = ['status' => 'failed'];

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

            $result['rows'] = array_values($rows);

            return $response->withJson( $result, 201 );
        }
        exit;

        $failCodes = [];
        foreach ($rows as $i => $data) {
            if (!empty($data[6])) {
                $data[6] = $this->tofloat($data[6]);
            }
            if (!empty($data[7])) {
                $data[7] = $this->tofloat($data[7]);
            }
            $sup = $this->Supplier->find_one_by(['company_name' => $data[5]]);
            $supplier_id = (int) $data[5];
            if (is_object($sup)) {
                $supplier_id = $sup->person_id;
            }
            $item_data = array(
                'name'					=> $data[3],
                'description'			=> '',
                'category'				=> $data[4],
                'cost_price'			=> $data[6],
                'unit_price'			=> $data[7],
                'reorder_level'			=> '',
                'supplier_id'			=> $supplier_id,
                'allow_alt_description'	=> '0',
                'is_serialized'			=> '0',
                'custom1'				=> '',
                'custom2'				=> '',
                'custom3'				=> '',
                'custom4'				=> '',
                'custom5'				=> '',
                'custom6'				=> '',
                'custom7'				=> '',
                'custom8'				=> '',
                'custom9'				=> '',
                'custom10'				=> '',
                'pic_filename'			=> $data[10]
            );

            $item_number = $data[2];
            $invalidated = FALSE;
            if($item_number != '')
            {
                $item_data['item_number'] = $item_number;
                $invalidated = $this->Item->item_number_exists($item_number);
            }

            if(!$invalidated && $this->Item->save($item_data)) {
                $items_taxes_data = NULL;
                //tax
                if (!empty($data[9])) {
                    if (strpos($data[9], ', ') !== false) {
                        $exps = explode(", ", $data[9]);
                    }
                }

                // quantities & inventory Info
                $employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
                $emp_info = $this->Employee->get_info($employee_id);
                $comment ='Qty '.$renderType.' Imported';

                // array to store information if location got a quantity
                $allowed_locations = $this->Stock_location->get_allowed_locations();
                $location_id = 1;
                // 12 is location
                if (!empty($data[12]) && array_key_exists($data[12], $allowed_locations)) {
                    $location_id = (int)$data[12];
                }

                $item_quantity_data = array(
                    'item_id' => $item_data['item_id'],
                    'location_id' => $location_id,
                    'quantity' => $data[8],
                );
                $this->Item_quantity->save($item_quantity_data, $item_data['item_id'], $location_id);

                $excel_data = array(
                    'trans_items' => $item_data['item_id'],
                    'trans_user' => $employee_id,
                    'trans_comment' => $comment,
                    'trans_location' => $location_id,
                    'trans_inventory' => $data[8]
                );

                $this->Inventory->insert($excel_data);
                unset($allowed_locations[$location_id]);

                /*
                 * now iterate through the array and check for which location_id no entry into item_quantities was made yet
                 * those get an entry with quantity as 0.
                 * unfortunately a bit duplicate code from above...
                 */
                foreach($allowed_locations as $location_id2 => $location_name)
                {
                    $item_quantity_data = array(
                        'item_id' => $item_data['item_id'],
                        'location_id' => $location_id2,
                        'quantity' => 0,
                    );
                    $this->Item_quantity->save($item_quantity_data, $item_data['item_id'], $location_id);

                    $excel_data = array(
                        'trans_items' => $item_data['item_id'],
                        'trans_user' => $employee_id,
                        'trans_comment' => $comment,
                        'trans_location' => $location_id2,
                        'trans_inventory' => 0
                    );

                    $this->Inventory->insert($excel_data);
                }
            }
            else //insert or update item failure
            {
                $failCodes[] = $i;
            }
        }

        if(count($failCodes) > 0) {
            $message = $this->lang->line('items_excel_import_partially_failed') . ' (' . count($failCodes) . '): ' . implode(', ', $failCodes);

            return json_encode(array('success' => FALSE, 'message' => $message));
        } else {
            return json_encode(array('success' => TRUE, 'message' => $this->lang->line('items_excel_import_success')));
        }
    }
}