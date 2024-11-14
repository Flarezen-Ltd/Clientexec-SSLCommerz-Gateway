<?php
require_once 'modules/admin/models/GatewayPlugin.php';
class PluginSSLCommerz extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array(
            lang("Plugin Name") => array(
                "type"          => "hidden",
                "description"   => "",
                "value"         => "SSLCommerz"
            ),
            lang('Signup Name') => array(
                'type'        => 'text',
                'description' => lang('Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card.'),
                'value'       => 'SSLCommerz'
            ),
            lang("Store ID") => array(
                "type"          => "text",
                "description"   => "Your SSLCOMMERZ Store ID is the integration credential which can be collected through our managers",
                "value"         => ""
            ),
            lang("Store Password") => array(
                "type"          => "text",
                "description"   => "Your SSLCOMMERZ Store Password is the integration credential which can be collected through our managers",
                "value"         => ""
            ),
            lang("Transaction Prefix") => array(
                "type"          => "text",
                "description"   => "Enter you Transaction Prefix Here",
                "value"         => ""
            ),
            lang("Test Mode") => array(
                "type"          => "yesno",
                "description"   => "Enable Test Mode",
                "value"         => ""
            ),
        );
        return $variables;
    }
    function singlepayment($params)
    {
        $query = "SELECT * FROM currency WHERE abrv = '" . $params['userCurrency'] . "'";
        $result = $this->db->query($query);
        $row = $result->fetch();
        $prefix = $row['symbol'];

        $invoiceId = $params['invoiceNumber'];
        $description = $params['invoiceDescription'];
        $amount = sprintf("%01.2f", round($params["invoiceTotal"], 2));
        $systemUrl = $params['companyURL'];
        $firstname = $params['userFirstName'];
        $lastname = $params['userLastName'];
        $email = $params['userEmail'];

        $bar = "/";
        if (substr(CE_Lib::getSoftwareURL(), -1) == "/") {
            $bar = "";
        }
        $baseURL = CE_Lib::getSoftwareURL() . $bar;
        $CallbackURL = $baseURL . "plugins/gateways/sslcommerz/callback.php";

        $currencyCode = $params['userCurrency'];

        $StoreID = $params['plugin_sslcommerz_Store ID'];
        $StorePassword = $params['plugin_sslcommerz_Store Password'];
        $TransactionPrefix = $params['plugin_sslcommerz_Transaction Prefix'];
        $TestMode = $params['plugin_sslcommerz_Test Mode'];

        $sanbox_url  = 'https://sandbox.sslcommerz.com/';
        $live_url    = 'https://securepay.sslcommerz.com/';

        if ($TestMode == 1) {
            $payment_url = $sanbox_url;
        } else {
            $payment_url = $live_url;
        }

        // $token_url = $payment_url . "api/get_token";
        $payment_url = $payment_url . "gwprocess/v4/api.php";

        $createpaybody = [
            'store_id' => $StoreID,
            'store_passwd' => $StorePassword,
            'cus_name' => $firstname . " " . $lastname,
            'cus_email' => $params['userEmail'],
            'cus_phone' => $params['userPhone'],
            'cus_add1' => $params['userAddress'],
            'cus_city' => $params['userCity'],
            'cus_state' => $params['userState'],
            'cus_postcode' => $params['userZipcode'],
            'cus_country' => $params['userCountry'],
            'product_name' => $description,
            'product_category' => "Domain/Hosting",
            'product_profile' => "non-physical-goods",
            'shipping_method' => "NO",
            'total_amount' => $amount,
            'currency' => $currencyCode,
            'tran_id' => "SSL" . $invoiceId . "" . time(),
            'desc' => "Payment for Invoice " . $invoiceId,
            'success_url' => $CallbackURL,
            'fail_url' => $CallbackURL,
            'cancel_url' => $CallbackURL,
            'value_a' => $invoiceId,
            'value_b' => $TransactionPrefix
        ];
        
        // Convert the payload array to a URL-encoded query string
        $postFields = http_build_query($createpaybody);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
          CURLOPT_URL => $payment_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $postFields,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
          ),
        ));
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }
        $response = curl_exec($ch);

        if ($response === false) {
            echo json_encode(curl_error($ch));
        }

        $urlData = json_decode($response);
        curl_close($ch);

        if (isset($urlData->GatewayPageURL) && !empty($urlData->GatewayPageURL)) {
            header('Location: ' . $urlData->GatewayPageURL);
            exit;
        } else {
            $errors = $urlData->failedreason;
            return $errors;
        }
    }
    function credit($params) {}
    function get_client_ip()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }
}
