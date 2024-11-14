<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice.php';

class PluginSSLCommerzCallback extends PluginCallback
{
    function processCallback()
    {
        if (isset($_REQUEST['tran_id']) && !empty($_REQUEST['tran_id'])) {
            $cPlugin = new Plugin('', 'sslcommerz', $this->user);
            $StoreID = $cPlugin->GetPluginVariable('plugin_sslcommerz_Store ID');
            $StorePassword = $cPlugin->GetPluginVariable('plugin_sslcommerz_Store Password');
            $TestMode = trim($cPlugin->GetPluginVariable("plugin_sslcommerz_Test Mode"));

            $sanbox_url  = 'https://sandbox.sslcommerz.com/';
            $live_url    = 'https://securepay.sslcommerz.com/';

            if ($TestMode == 1) {
                $payment_url = $sanbox_url;
            } else {
                $payment_url = $live_url;
            }

            $verification_url = $payment_url . "validator/api/merchantTransIDvalidationAPI.php";

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $verification_url . '?tran_id=' . $_REQUEST['tran_id'] . '&store_id=' . $StoreID . '&store_passwd=' . $StorePassword . '&format=json',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            curl_close($curl);

            $response = json_decode($response, true);
            $response = $response['element'][0];

            $amount = trim($response['currency_amount']);
            $order_id = trim($response['tran_id']);
            $invoiceId = trim($response['value_a']);
            $currencyCode = $response['currency_type'];
            $price = $amount . " " . $currencyCode;

            $cPlugin = new Plugin($invoiceId, 'sslcommerz', $this->user);
            $cPlugin->setAmount($amount);
            $cPlugin->setAction('charge');

            if ($response['status'] == "VALID" || $response['status'] == "VALIDATED") {
                //Create plug in class to interact with CE
                if ($cPlugin->IsUnpaid() == 1) {
                    $transaction = " SSLCommerz payment of $price Successful (Order ID: " . $order_id . ")";
                    $cPlugin->PaymentAccepted($amount, $transaction);
                    $returnURL = CE_Lib::getSoftwareURL() . "/index.php?fuse=billing&paid=1&controller=invoice&view=invoice&id=" . $invoiceId;
                    header("Location: " . $returnURL);
                    exit;
                } else {
                    return;
                }
            } else {
                $transaction = " SSLCommerz payment of $price Failed (Order ID: " . $order_id . ")";
                $cPlugin->PaymentRejected($transaction);
                $returnURL = CE_Lib::getSoftwareURL() . "/index.php?fuse=billing&cancel=1&controller=invoice&view=invoice&id=" . $invoiceId;
                header("Location: " . $returnURL);
                exit;
            }
            return;
        }
        return;
    }
}
