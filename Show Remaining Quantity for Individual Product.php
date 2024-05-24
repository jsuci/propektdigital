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
        // Function to get stock information by item code
        $item_code = $product->get_sku();
        $item_id = get_item_id_by_item_code($item_code);
        $item_stocks = get_item_stock_by_item_id($item_id);

        // Display information for single products
        if (!empty($item_id)) {
            echo "<div class='prodInfo'>";
            echo "<p>Item ID: <b>$item_id</b></p>";
            echo "<p>Item SKU: <b>$item_code</b></p>";
            echo "<div id='stock_info'></div>";
            echo "</div>";

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

        // Check if the product is a bundle
		if ($product->is_type('bundle')) {
			$bundled_items = $product->get_bundled_items();
			if ($bundled_items) {
				foreach ($bundled_items as $bundled_item) {
					$bundled_product = $bundled_item->get_product();
					$bundled_sku = $bundled_product->get_sku();
					$bundled_product_name = $bundled_product->get_name();
					$bundled_item_id = get_item_id_by_item_code($bundled_sku);
					$bundled_item_stocks = get_item_stock_by_item_id($bundled_item_id);

					echo "<div class='bundledProdInfo'>";
					echo "<p>Bundled Product ID: <b>$bundled_item_id</b></p>";
					echo "<p>Bundled Product Name: <b>$bundled_product_name</b></p>";
					echo "<div id='bundled_stock_info_$bundled_item_id'></div>";
					echo "</div>";

					if (!empty($bundled_item_stocks) && isset($bundled_item_stocks[0]['RemQty'])) {
						echo "<script type='text/javascript'>
						jQuery(document).ready(function($) {
							var bundledStockInfo = '<p>Remaining Quantity: <b>" . $bundled_item_stocks[0]['RemQty'] . "</b></p>';
							$('#bundled_stock_info_$bundled_item_id').html(bundledStockInfo);
							if (" . $bundled_item_stocks[0]['RemQty'] . " == 0) {
								$('.single_add_to_cart_button').attr('data-backorder', 'true');
								$('.single_add_to_cart_button').attr('data-out-of-stock-items', function(i, val) {
									return (val ? val + ', ' : '') + '$bundled_product_name';
								});
							}
						});
						</script>";
					} else {
						echo "<script type='text/javascript'>
						jQuery(document).ready(function($) {
							$('#bundled_stock_info_$bundled_item_id').html('<p>No stock information available.</p>');
						});
						</script>";
					}
				}
			}
		}
		
        // Check if the product is a variation
        if ($product->is_type('variable')) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var itemStocks = <?php echo json_encode($item_stocks); ?>;
                var backorderRequired = false;

                for (var i = 0; i < itemStocks.length; i++) {
                    if (itemStocks[i].RemQty == 0) {
                        backorderRequired = true;
                        break;
                    }
                }

                if (backorderRequired) {
                    $('.single_add_to_cart_button').attr('data-backorder', 'true');
                } else {
                    $('.single_add_to_cart_button').removeAttr('data-backorder');
                }

                // Event listener to update stock info when a variation is selected
                $('form.variations_form').on('change', '.variations select', function() {
                    var stockInfo = '';
                    var stockFound = false;

                    for (var i = 0; i < itemStocks.length; i++) {
                        var stock = itemStocks[i];
                        if (stock.RemQty == 0) {
                            stockFound = true;
                            stockInfo = "<p>Color: <b>" + stock.Color + "</b>, Size: <b>" + stock.Size + "</b>, Quantity: <b>" + stock.Qty + "</b>, Remaining Quantity: <b>" + stock.RemQty + "</b></p>";
                            break;
                        }
                    }

                    if (stockFound) {
                        $('#stock_info').html(stockInfo);
                        $('.single_add_to_cart_button').attr('data-backorder', 'true');
                    } else {
                        $('#stock_info').html('<p>No stock information available for the selected attributes.</p>');
                        $('.single_add_to_cart_button').removeAttr('data-backorder');
                    }
                });
            });
            </script>
            <?php
        }
    }
}
