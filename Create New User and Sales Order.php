// add_action('woocommerce_order_status_completed', 'custom_order_completed', 10, 1);
// add_action('woocommerce_before_checkout_form', 'custom_order_completed');
// add_action('woocommerce_order_status_completed', 'custom_order_completed');
add_action('woocommerce_thankyou', 'auto_register_guest', 10, 1);
add_action('woocommerce_thankyou', 'custom_order_completed', 20, 1);


function auto_register_guest($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    // If the user is already registered, do nothing
    if ($user_id > 0) {
        return;
    }

    $email = $order->get_billing_email();
    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();

    // Check if the email is already registered
    if (email_exists($email)) {
        return;
    }

    // Generate a random password
    $password = wp_generate_password();

    // Create the user
    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        // There was an error creating the user
        return;
    }

    // Update user meta
    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
    ]);

    // Set the user role
    $user = new WP_User($user_id);
    $user->set_role('customer');

    // Link the order to the new user
    $order->set_customer_id($user_id);
    $order->save();

    // Send the email
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $subject = sprintf(__('Your account on %s'), $blogname);
    $message = sprintf(__('Hi %s,'), $first_name) . "\r\n\r\n";
    $message .= sprintf(__('Thank you for your purchase on %s.'), $blogname) . "\r\n\r\n";
    $message .= sprintf(__('You can log in to your account using the following credentials:')) . "\r\n\r\n";
    $message .= sprintf(__('Username: %s'), $email) . "\r\n";
    $message .= sprintf(__('Password: %s'), $password) . "\r\n\r\n";
    $message .= wp_login_url() . "\r\n";

    wp_mail($email, $subject, $message);
}

function get_currency_id_from_order($order) {
    $order_currency = $order->get_currency();
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>
            <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                <apikey>Test@apikloz</apikey>
            </KlozHeader>
        </soap:Header>
        <soap:Body>
            <GetAllCurrency xmlns="http://klozinc.exocloud.ca/" />
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
            'SOAPAction: "http://klozinc.exocloud.ca/GetAllCurrency"'
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
    $currencies = $xml->xpath('//ns:Currency');

    $currency_id = null;
    foreach ($currencies as $currency) {
        $currency_code = (string)$currency->CurrencyCode;
        if ($currency_code === $order_currency) {
            $currency_id = (int)$currency->id;
            break;
        }
    }

    // If no match is found, default to USD
    if ($currency_id === null) {
        foreach ($currencies as $currency) {
            $currency_code = (string)$currency->CurrencyCode;
            if ($currency_code === 'USD') {
                $currency_id = (int)$currency->id;
                break;
            }
        }
    }

    return $currency_id;
}

function create_erp_user_from_order($order) {
    if (!$order) {
        return;
    }

    // Extract billing information from the order
    $fullname = $order->get_formatted_billing_full_name();
    $billing_address_1 = $order->get_billing_address_1();
    $billing_address_2 = $order->get_billing_address_2();
    $billing_city = $order->get_billing_city();
    $billing_state = $order->get_billing_state();
    $billing_phone = $order->get_billing_phone();
    $user_email = $order->get_billing_email();
    $billing_country = $order->get_billing_country();
    $billing_postcode = $order->get_billing_postcode();
	$currency_id = get_currency_id_from_order($order);


    // Create User API call
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
     <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
         <soap:Header>
             <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                 <apikey>Test@apikloz</apikey>
             </KlozHeader>
         </soap:Header>
         <soap:Body>
             <createUser xmlns="http://klozinc.exocloud.ca/">
                 <name>'.$fullname.'</name>
                 <address>'.$billing_address_1.'</address>
                 <city>'.$billing_city.'</city>
                 <state>'.$billing_state.'</state>
                 <phone></phone>
                 <mobile>'.$billing_phone.'</mobile>
                 <email>'.$user_email.'</email>
                 <country>'.$billing_country.'</country>
                 <fax></fax>
                 <address2>'.$billing_address_2.'</address2>
                 <postalcode>'.$billing_postcode.'</postalcode>
                 <currencyid>'.$currency_id.'</currencyid>
             </createUser>
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
             'SOAPAction: "http://klozinc.exocloud.ca/createUser"'
         ),
     ));

     $response = curl_exec($curl);

     curl_close($curl);

     // Load the XML response
     $xml = simplexml_load_string($response);

     // Register the soap namespace
     $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
     $xml->registerXPathNamespace('ns', 'http://klozinc.exocloud.ca/');

     $id = $xml->xpath('//ns:createUserResult');
     $createUserResultValue = (string)$id[0];

     // Update the user meta with the ERP customer ID
     $user_id = $order->get_user_id();
     if ($user_id) {
         update_user_meta($user_id, 'customerid_erp', $createUserResultValue);
     }
}

function update_erp_user_from_order($order) {
    // Extract billing information from the order
    $fullname = $order->get_formatted_billing_full_name();
    $billing_address_1 = $order->get_billing_address_1();
    $billing_address_2 = $order->get_billing_address_2();
    $billing_city = $order->get_billing_city();
    $billing_state = $order->get_billing_state();
    $billing_phone = $order->get_billing_phone();
    $user_email = $order->get_billing_email();
    $billing_country = $order->get_billing_country();
    $billing_postcode = $order->get_billing_postcode();
    
    // Get the ERP customer ID from user meta
    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    $customerid_erp = get_user_meta($user_id, 'customerid_erp', true);
    if (empty($customerid_erp)) {
        return; // If there's no ERP customer ID, we can't update the user
    }

    // Create User API call
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>
            <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                <apikey>Test@apikloz</apikey>
            </KlozHeader>
        </soap:Header>
        <soap:Body>
            <updateUser xmlns="http://klozinc.exocloud.ca/">
                <id>'.$customerid_erp.'</id>
                <name>'.$fullname.'</name>
                <address>'.$billing_address_1.'</address>
                <city>'.$billing_city.'</city>
                <state>'.$billing_state.'</state>
                <phone></phone>
                <mobile>'.$billing_phone.'</mobile>
                <email>'.$user_email.'</email>
                <country>'.$billing_country.'</country>
                <fax></fax>
                <address2>'.$billing_address_2.'</address2>
                <postalcode>'.$billing_postcode.'</postalcode>
            </updateUser>
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
            'SOAPAction: "http://klozinc.exocloud.ca/updateUser"'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    // For debugging purposes (optional)
    echo $response;
}

function create_erp_sales_order_from_order($order) {
    // Extract order information
    $order_number = $order->get_order_number();
    $order_total = $order->get_total();
    $shipping_total = $order->get_shipping_total();
    $tax_total = $order->get_total_tax();
    $order_currency = $order->get_currency();
    $order_date = $order->get_date_created();
    $order_date_finalformate = $order_date->date('F j, Y');
    $fullname = $order->get_formatted_billing_full_name();
    $billing_address_1 = $order->get_billing_address_1();
    $billing_address_2 = $order->get_billing_address_2();
    $billing_city = $order->get_billing_city();
    $billing_state = $order->get_billing_state();
    $billing_phone = $order->get_billing_phone();
    $user_email = $order->get_billing_email();
    $billing_country = $order->get_billing_country();
    $billing_postcode = $order->get_billing_postcode();

    // Get the ERP customer ID from user meta
    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    $customerid_erp = get_user_meta($user_id, 'customerid_erp', true);
    if (empty($customerid_erp)) {
        return; // If there's no ERP customer ID, we can't create the sales order
    }

    // Get order items
    $items = $order->get_items();
    $items_xml = '';
    foreach ($items as $item) {
        $product = $item->get_product();
        $item_id = $product->get_id();
        $item_code = $product->get_sku();
        $item_description = $product->get_name();
        $item_quantity = $item->get_quantity();
        $item_price = $item->get_total();
        $item_discount_percentage = 0; // Assuming no discount percentage, customize as needed
        $item_discount_amount = 0; // Assuming no discount amount, customize as needed
        $item_net_value = $item_price - $item_discount_amount;

        $items_xml .= '<SOItems>
            <itemid>'.$item_id.'</itemid>
            <itemcode>'.$item_code.'</itemcode>
            <itemDescription>'.$item_description.'</itemDescription>
            <itemColorid></itemColorid>
            <itemSizeid></itemSizeid>
            <itemQuantity>'.$item_quantity.'</itemQuantity>
            <Price>'.$item_price.'</Price>
            <DiscountPercentage>'.$item_discount_percentage.'</DiscountPercentage>
            <DiscountAmount>'.$item_discount_amount.'</DiscountAmount>
            <NetValue>'.$item_net_value.'</NetValue>
        </SOItems>';
    }

    // Create Sales Order API call
    $soap_request = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Header>
            <KlozHeader xmlns="http://klozinc.exocloud.ca/">
                <apikey>Test@apikloz</apikey>
            </KlozHeader>
        </soap:Header>
        <soap:Body>
            <createSO xmlns="http://klozinc.exocloud.ca/">
                <customerid>'.$customerid_erp.'</customerid>
                <orderbasicamount>'.$order_total.'</orderbasicamount>
                <taxamount>'.$tax_total.'</taxamount>
                <discount>0</discount>
                <totalwithouttax>'.($order_total - $tax_total).'</totalwithouttax>
                <grandtotal>'.$order_total.'</grandtotal>
                <orderDate>'.$order_date_finalformate.'</orderDate>
                <terms>NET 30</terms> <!-- Example terms, customize as needed -->
                <itemarray>
                    '.$items_xml.'
                </itemarray>
                <invoice>'.$order_number.'</invoice>
                <otherdiscount>0</otherdiscount>
                <customerpo>'.$order_number.'</customerpo>
                <localShippingCharge>'.$shipping_total.'</localShippingCharge>
                <overseasShippingCharge>0</overseasShippingCharge> <!-- Assuming no overseas shipping charge, customize as needed -->
            </createSO>
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
            'SOAPAction: "http://klozinc.exocloud.ca/createSO"'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    // For debugging purposes (optional)
    echo "Create Sales Order: $response";
}


function custom_order_completed($order_id) {
    // Get the order object
    $order = wc_get_order($order_id);
	
    // Get user ID associated with the order
    $user_id = $order->get_customer_id();

    // Get user details
    $user = get_user_by('ID', $user_id);
	
	// get custom id in usermeta
	$customerid_erp = get_user_meta($user->ID, 'customerid_erp', true);

	if (empty($customerid_erp)) {
		create_erp_user_from_order($order);
		update_erp_user_from_order($order);
 		$customerid_erp = get_user_meta($user->ID, 'customerid_erp', true);
	}

	echo "Customer ID ERP: $customerid_erp<br>";

	create_erp_sales_order_from_order($order);


}