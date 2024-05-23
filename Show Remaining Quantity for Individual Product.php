ini_set('display_errors', 0);


add_action('woocommerce_after_add_to_cart_form', 'display_product_stock_info', 20);

function get_item_id_by_item_code($itemCode) {
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>
            <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                <apikey>Test@apikloz</apikey>
            </KlozHeader>
        </soap:Header>
        <soap:Body>
            <GetItemByItemCode xmlns="http://klozinc.exocloud.ca/">
				<itemCode>'.$itemCode.'</itemCode>
			</GetItemByItemCode>
        </soap:Body>
    </soap:Envelope>';

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://sandbox.klozinc.exocloud.ca/api/exowebservice.asmx',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $soap_request,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://klozinc.exocloud.ca/GetItemByItemCode"'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    // Load the XML response
    $xml = simplexml_load_string($response);

    // Register the namespaces
    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xml->registerXPathNamespace('ns', 'http://klozinc.exocloud.ca/');

    // Extract currencies
    $item_id = $xml->xpath('//ns:id')[0];

	return $item_id;
}

function get_item_stock_by_item_id($itemId) {
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>
            <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                <apikey>Test@apikloz</apikey>
            </KlozHeader>
        </soap:Header>
        <soap:Body>
            <GetStockByItemId xmlns="http://klozinc.exocloud.ca/">
				<itemId>'.$itemId.'</itemId>
			</GetStockByItemId>
        </soap:Body>
    </soap:Envelope>';

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://sandbox.klozinc.exocloud.ca/api/exowebservice.asmx',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $soap_request,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://klozinc.exocloud.ca/GetStockByItemId"'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    // Load the XML response
    $xml = simplexml_load_string($response);

    // Register the namespaces
    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xml->registerXPathNamespace('ns', 'http://klozinc.exocloud.ca/');

    // Extract stock items
    $stock_items = [];
    foreach ($xml->xpath('//ns:Stock') as $stock) {
        $stock_items[] = [
            'Item' => (string)$stock->Item,
            'Color' => (string)$stock->Color,
            'Size' => (string)$stock->Size,
            'Qty' => (float)$stock->Qty,
            'RemQty' => (float)$stock->RemQty
        ];
    }

    return $stock_items;
}

function display_product_stock_info() {
    global $product;

    if ($product) {
        $item_code = $product->get_sku();
        $item_id = get_item_id_by_item_code($item_code);
        $item_stocks = get_item_stock_by_item_id($item_id);
        
        if (empty($item_id)) {
            return;
        }
        
        echo "<div class='prodInfo'>";
        echo "<p>Item ID: <b>$item_id</b></p>";
        echo "<p>Item SKU: <b>$item_code</b></p>";
        echo "<div id='stock_info'></div>";
        echo "</div>";

        // Check if the product has variations
        if ($product->is_type('variable')) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#color').change(function() {
                    var selectedColor = $(this).val();
                    var itemStocks = <?php echo json_encode($item_stocks); ?>;
                    
                    var stockInfo = '';
                    var stockFound = false;
                    
                    for (var i = 0; i < itemStocks.length; i++) {
                        var stock = itemStocks[i];
                        if ((selectedColor == 'Red' && stock.Color == '17') || (selectedColor == 'Blue' && stock.Color == '126')) {
                            stockInfo = "<p>Color: <b>" + stock.Color + "</b>, Size: <b>" + stock.Size + "</b>, Quantity: <b>" + stock.Qty + "</b>, Remaining Quantity: <b>" + stock.RemQty + "</b></p>";
                            stockFound = true;
                            break;
                        }
                    }
                    
                    if (stockFound) {
                        $('#stock_info').html(stockInfo);
                        if (stock.RemQty == 0) {
                            $('.single_add_to_cart_button').attr('data-backorder', 'true');
                        } else {
                            $('.single_add_to_cart_button').removeAttr('data-backorder');
                        }
                    } else {
                        $('#stock_info').html('<p>No stock information available for the selected color.</p>');
                    }
                });
            });
            </script>
            <?php
        } else {
            // Single product logic
            if (!empty($item_stocks) && isset($item_stocks[0]['RemQty'])) {
                echo "<script type='text/javascript'>
                jQuery(document).ready(function($) {
                    var stockInfo = '<p>Remaining Quantity: <b>" . $item_stocks[0]['RemQty'] . "</b></p>';
                    $('#stock_info').html(stockInfo);
                    if (" . $item_stocks[0]['RemQty'] . " == 0) {
                        $('.single_add_to_cart_button').attr('data-backorder', 'true');
                    } else {
                        $('.single_add_to_cart_button').removeAttr('data-backorder');
                    }
                });
                </script>";
            } else {
                echo "<script type='text/javascript'>
                jQuery(document).ready(function($) {
                    $('#stock_info').html('<p>No stock information available.</p>');
                });
                </script>";
            }
        }
    }
}