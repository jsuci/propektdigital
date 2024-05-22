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

    // Extract currencies
    $item_stocks = $xml->xpath('//ns:RemQty')[0];

	return intval($item_stocks);
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
		echo "<p>Stocks Left: <b>$item_stocks</b></p>";
		echo "</div>";

        if ($item_stocks == 0) {
            echo "<script type='text/javascript'>
                    jQuery(document).ready(function($) {
                        $('.single_add_to_cart_button').attr('data-backorder', 'true');
                    });
                  </script>";
        }

	}
}