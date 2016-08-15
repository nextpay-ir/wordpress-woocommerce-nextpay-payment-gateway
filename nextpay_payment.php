<?php

/**
 * Created by NextPay.ir
 * author: FreezeMan
 * ID: @FreezeMan
 * Date: 7/29/16
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: freezeman.0098@gmail.com
 * @copyright 2016
 */
class Nextpay_Payment
{
    //----- payment properties
    public $api_key = "";
    public $amount = 0;
    public $trans_id = "";
    public $params = array();
    public $server_soap = "http://api.nextpay.org/gateway/token.wsdl";
    //public $server_soap = "http://api.nextpay.org/gateway/token?wsdl";
    public $server_http = "http://api.nextpay.org/gateway/token.http";
    public $request_http = "http://api.nextpay.org/gateway/payment";
    public $request_verify_soap = "http://api.nextpay.org/gateway/verify.wsdl";
    //public $request_verify_soap = "http://api.nextpay.org/gateway/verify?wsdl";
    public $request_verify_http = "http://api.nextpay.org/gateway/verify.http";
    public $callback_uri = "http://example.com";
    private $keys_for_verify = array("api_key","amount","callback_uri");
    private $keys_for_check = array("api_key","amount","trans_id");

    //----- controller properties
    public $default_verify = Type_Verify::SoapClient;

    /**
     * Nextpay_Payment constructor.
     * @param array|bool $params
     * @param string|bool $api_key
     * @param string|bool $url
     * @param int|bool $amount
     */
    public function __construct($params=false, $api_key=false, $amount=false, $url=false)
    {
        if(is_array($params))
        {
            foreach ($this->keys_for_verify as $key )
            {
                if(!array_key_exists($key,$params))
                {
                    $error = "<h2>آرایه ارسالی دارای مشکل میباشد.</h2>";
                    $error .= "<h4>نمونه مثال برای آرایه ارسالی.</h4>";
                    $error .= /** @lang text */
                        "<pre>
                            array(\"api_key\"=>\"شناسه api\",
                                    \"amount\"=>\"مبلغ\",
                                    \"callback_uri\"=>\"مسیر باگشت\")

                        </pre>";
                    $this->show_error($error);
                }
            }
            $this->params = $params;
            $this->api_key = $params['api_key'];
            $this->amount = $params['amount'];
            $this->callback_uri = $params['callback_uri'];
        }
        else
        {
            if($api_key)
                $this->api_key = $api_key;
            //else
            //    $this->show_error("شناسه مربوط به api مقدار دهی نشده است");

            if($amount)
                $this->amount = $amount;
            //else
            //    $this->show_error("مبلغ تعیین نشده است");

            if($url)
                $this->callback_uri = $url;
            //else
            //    $this->show_error("مسیر بازگشت تعیین نشده است");

            $this->params = array(
                "api_key"=>$this->api_key,
                "amount"=>$this->amount,
                "callback_uri"=>$this->callback_uri);
        }
    }

    /**
     * @return string
     * return trans_id
     */
    public function token()
    {
        $res = "";
        switch ($this->default_verify)
        {
            case Type_Verify::SoapClient:
                try
                {
                    $soap_client = new SoapClient($this->server_soap, array('encoding' => 'UTF-8'));
                    $res = $soap_client->TokenGenerator($this->params);

                    $res = $res->TokenGeneratorResult;

                    if ($res != "" && $res != NULL && is_object($res)) {
                        if (intval($res->code) == -1)
                            $this->trans_id = $res->trans_id;
                        /*else
                            $this->code_error($res->code);*/
                    }
                    else
                        $this->show_error("خطا در پاسخ دهی به درخواست با SoapClinet");
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            case Type_Verify::NuSoap:
                try
                {
                    include_once ("include/nusoap/nusoap.php");

                    $client = new nusoap_client($this->server_soap,'wsdl');

                    $error = $client->getError();

                    if ($error)
                        $this->show_error($error);

                    $res = $client->call('TokenGenerator',array($this->params));

                    if ($client->fault)
                    {
                        echo "<h2>Fault</h2><pre>";
                        print_r ($res);
                        echo "</pre>";
                        exit(0);
                    }
                    else
                    {
                        $error = $client->getError();

                        if ($error)
                            $this->show_error($error);

                        $res = $res['TokenGeneratorResult'];

                        if ($res != "" && $res != NULL && is_array($res)) {
                            if (intval($res['code']) == -1) {
                                $this->trans_id = $res['trans_id'];
                                $res = (object)$res;
                            }/*else
                                $this->code_error($res['code']);*/
                        }
                        else
                            $this->show_error("خطا در پاسخ دهی به درخواست با NuSoap_Client");
                    }
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            case Type_Verify::Http:
                try
                {
                    if( !$this->cURLcheckBasicFunctions() ) $this->show_error("UNAVAILABLE: cURL Basic Functions");
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $this->server_http);
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_POSTFIELDS,
                        "api_key=".$this->api_key."&amount=".$this->amount."&callback_uri=".$this->callback_uri);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    /** @var int | string $server_output */
                    $res = json_decode(curl_exec ($curl));
                    curl_close ($curl);

                    if ($res != "" && $res != NULL && is_object($res)) {

                        if (intval($res->code) == -1)
                            $this->trans_id = $res->trans_id;
                        /*else
                            $this->code_error($res->code);*/
                    }
                    /*else
                        $this->show_error("خطا در پاسخ دهی به درخواست با Curl");*/
                }
                catch (Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            default:
                try
                {
                    $soap_client = new SoapClient($this->server_soap, array('encoding' => 'UTF-8'));
                    $res = $soap_client->TokenGenerator($this->params);

                    $res = $res->TokenGeneratorResult;

                    if ($res != "" && $res != NULL && is_object($res)) {
                        if (intval($res->code) == -1)
                            $this->trans_id = $res->trans_id;
                        /*else
                            $this->code_error($res->code);*/
                    }
                    else
                        $this->show_error("خطا در پاسخ دهی به درخواست با SoapClinet");
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
        }
        return $res;
    }

    /**
     * @param string $trans_id
     */
    public function send($trans_id)
    {
        if(isset($trans_id))
        {
            header('Location: '.$this->request_http."/$trans_id");
            exit(0);
        }
        else
        {
            $this->show_error("empty trans_id param send");
        }
    }

    /**
     * @param array|bool $params
     * @param string|bool $api_key
     * @param string|bool $trans_id
     * @param int|bool $amount
     * @return int|mixed
     */
    public function verify_request($params=false, $api_key=false, $trans_id=false, $amount=false)
    {
        $res = 0;
        if(is_array($params))
        {
            foreach ($this->keys_for_check as $key )
            {
                if(!array_key_exists($key,$params))
                {
                    $error = "<h2>آرایه ارسالی دارای مشکل میباشد.</h2>";
                    $error .= "<h4>نمونه مثال برای آرایه ارسالی.</h4>";
                    $error .= /** @lang text */
                        "<pre>
                            array(\"api_key\"=>\"شناسه api\",
                                    \"amount\"=>\"مبلغ\",
                                    \"trans_id\"=>\"شماره تراکنش\")

                        </pre>";
                    $this->show_error($error);
                }
            }

            $this->trans_id = $params['trans_id'];
            $this->api_key = $params['api_key'];
            $this->amount = $params['amount'];

        }

        if($api_key)
            $this->api_key = $api_key;
        elseif ($this->api_key)
            $this->params['api_key'] = $this->api_key;
        //else
        //    $this->show_error("شناسه مربوط به api مقدار دهی نشده است");

        if($amount)
            $this->amount = $amount;
        elseif ($this->amount)
            $this->params['amount'] = $this->amount;
        //else
        //    $this->show_error("مبلغ تعیین نشده است");

        if($trans_id)
            $this->trans_id = $trans_id;
        elseif ($this->trans_id)
            $this->params['trans_id'] = $this->trans_id;
        //else
        //    $this->show_error("شماره نراکنش تعیین نشده است");


        switch ($this->default_verify)
        {
            case Type_Verify::SoapClient:
                try
                {
                    $soap_client = new SoapClient($this->request_verify_soap, array('encoding' => 'UTF-8'));
                    $res = $soap_client->PaymentVerifection($this->params);

                    $res = $res->PaymentVerifectionResult;

                    if ($res != "" && $res != NULL && is_object($res)) {
                        //$this->code_error($res->code);
                        $res = $res->code;
                    }
                    else
                        $this->show_error("خطا در پاسخ دهی به درخواست با SoapClinet");
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            case Type_Verify::NuSoap:
                try
                {
                    include_once ("include/nusoap/nusoap.php");

                    $client = new nusoap_client($this->server_soap,'wsdl');

                    $error = $client->getError();

                    if ($error)
                        $this->show_error($error);

                    $res = $client->call('PaymentVerifection',array($this->params));

                    if ($client->fault)
                    {
                        echo "<h2>Fault</h2><pre>";
                        print_r ($res);
                        echo "</pre>";
                        exit(0);
                    }
                    else
                    {
                        $error = $client->getError();

                        if ($error)
                            $this->show_error($error);

                        $res = $res['PaymentVerifectionResult'];

                        if ($res != "" && $res != NULL && is_array($res)) {
                            /*if (intval($res['code']) == -1)
                                $this->trans_id = $res['trans_id'];
                            else
                                $this->code_error($res['code']);*/
                            $res = $res['code'];
                        }
                        else
                            $this->show_error("خطا در پاسخ دهی به درخواست با NuSoap_Client");
                    }
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            case Type_Verify::Http:
                try
                {
                    if( !$this->cURLcheckBasicFunctions() ) $this->show_error("UNAVAILABLE: cURL Basic Functions");
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $this->request_verify_http);
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_POSTFIELDS,
                        "api_key=".$this->api_key."&amount=".$this->amount."&trans_id=".$this->trans_id);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    /** @var int | string $server_output */
                    $res = json_decode(curl_exec ($curl));
                    curl_close ($curl);

                    if ($res != "" && $res != NULL && is_object($res)) {

                        /*if (intval($res->code) == -1)
                            $this->trans_id = $res->trans_id;
                        else
                            $this->code_error($res->code);*/
                        $res = $res->code;
                    }
                    else
                        $this->show_error("خطا در پاسخ دهی به درخواست با Curl");
                }
                catch (Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
            default:
                try
                {
                    $soap_client = new SoapClient($this->request_verify_soap, array('encoding' => 'UTF-8'));
                    $res = $soap_client->PaymentVerifection($this->params);

                    $res = $res->PaymentVerifectionResult;

                    if ($res != "" && $res != NULL && is_object($res)) {
                        //$this->code_error($res->code);
                        $res = $res->code;
                    }
                    else
                        $this->show_error("خطا در پاسخ دهی به درخواست با SoapClinet");
                }
                catch(Exception $e){
                    $this->show_error($e->getMessage());
                }
                break;
        }
        return $res;
    }

    /**
     * @param string | string $error
     */
    public function show_error($error)
    {
        echo "<h1>وقوع خطا !!!</h1>";
        echo "<h4>{$error}</h4>";
    }

    /**
     * @param int | string $error_code
     */
    public function code_error($error_code)
    {
        $error_code = intval($error_code);
        $error_array = array(
            0 => "time ago request payment is complete transaction,please check status only.",
            2 => "Bank not response status.",
            3 => "pending to payment request.",
            4 => "Cancel status trans_id.",
            20 => "api key is not send",
            21 => "empty trans_id param send",
            22 => "amount in not send",
            23 => "callback in not send",
            30 => "amount less 100 toman",
            33 => "api key incorrect type or not exist",
            34 => "not exist or not valid transaction",
            35 => "api key incorrect type for this request.",
            36 => "ResNum from bank not valid to send",
            40 => "not active or invalid api key",
            41 => "Bank gateway is deactivated",
            42 => "system payment has been problem.",
            43 => "gateway selection not exist,please reselect bank gateway",
            45 => "payment system deactivate temporary",
            46 => "No result,wrong request",
            55 => "empty trans_id param send"
        );

        echo "<h2>code error : {$error_code}</h2>";
        echo "<h3>description error : {$error_array[$error_code]}</h3>";
    }

    /**
     * @return bool
     */
    public function cURLcheckBasicFunctions()
    {
        if( !function_exists("curl_init") &&
            !function_exists("curl_setopt") &&
            !function_exists("curl_exec") &&
            !function_exists("curl_close") ) return false;
        else return true;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @return string
     */
    public function getCallbackUri()
    {
        return $this->callback_uri;
    }

    /**
     * @return string
     */
    public function getTransId()
    {
        return $this->trans_id;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param int|int $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        $this->params['amount'] = $this->amount;
    }

    /**
     * @param bool|string $api_key
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
        $this->params['api_key'] = $this->api_key;
    }

    /**
     * @param string $trans_id
     */
    public function setTransId($trans_id)
    {
        $this->trans_id = $trans_id;
    }

    /**
     * @param string|string $callback_uri
     */
    public function setCallbackUri($callback_uri)
    {
        $this->callback_uri = $callback_uri;
        $this->params['callback_uri'] = $this->callback_uri;
    }

    /**
     * @param array|array $params
     */
    public function setParams($params)
    {
        if(is_array($params))
        {
            foreach ($this->keys_param as $key )
            {
                if(!array_key_exists($key,$params))
                {
                    $error = "<h2>آرایه ارسالی دارای مشکل میباشد.</h2>";
                    $error .= "<h4>نمونه مثال برای آرایه ارسالی.</h4>";
                    $error .= /** @lang text */
                        "<pre>
                            array(\"api_key\"=>\"شناسه api\",
                                    \"amount\"=>\"مبلغ\",
                                    \"callback_uri\"=>\"مسیر باگشت\")

                        </pre>";
                    $this->show_error($error);
                }
            }
            $this->params = $params;
        }
        else
            $this->show_error("برای مقدارهی پارامتر ها باید بصورت آرایه اقدام نمایید");
    }

    /**
     * @param int $default_verify
     */
    public function setDefaultVerify($default_verify)
    {
        $this->default_verify = $default_verify;
    }
}

class Type_Verify
{
    const NuSoap = 0;
    const SoapClient = 1;
    const Http = 2;
}