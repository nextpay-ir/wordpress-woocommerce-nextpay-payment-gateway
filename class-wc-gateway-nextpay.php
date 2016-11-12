<?php

if (!defined('ABSPATH'))
    exit;

function Load_Nextpay_Gateway() {

    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Nextpay') && !function_exists('Woocommerce_Add_Nextpay_Gateway')) {

        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Nextpay_Gateway');

        function Woocommerce_Add_Nextpay_Gateway($methods) {
            $methods[] = 'WC_Nextpay';
            return $methods;
        }

        class WC_Nextpay extends WC_Payment_Gateway {

            public function __construct() {


                $this->id = 'WC_Nextpay';
                $this->method_title = __('پرداخت امن نکست پی', 'woocommerce');
                $this->method_description = __('تنظمیات درگاه نکست پی woocommerce', 'woocommerce');
                $this->icon = apply_filters('WC_Nextpay_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->api_key = $this->settings['api_key'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                else
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Nextpay_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Nextpay_Gateway'));
            }

            public function admin_options() {


                parent::admin_options();
            }

            public function init_form_fields() {
                $this->form_fields = apply_filters('WC_Nextpay_Config', array(
                        'base_confing' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه نکست پی', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت نکست پی باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت امن نکست پی', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه نکست پی', 'woocommerce')
                        ),
                        'account_confing' => array(
                            'title' => __('تنظیمات حساب نکست پی', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'api_key' => array(
                            'title' => __('کلید مجوزدهی', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('کلید مجوزدهی درگاه نکست پی', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_confing' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) نکست پی استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت نکست پی ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id) {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            public function Send_to_Nextpay_Gateway($order_id) {


                include_once dirname(__FILE__).'/nextpay_payment.php';
                global $woocommerce;
                $woocommerce->session->order_id_nextpay = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_order_currency();
                $currency = apply_filters('WC_Nextpay_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="nextpay-checkout-form" id="nextpay-checkout-form">
						<input type="submit" name="nextpay_submit" class="button alt" id="nextpay-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_Nextpay_Form', $form, $order_id, $woocommerce);

                do_action('WC_Nextpay_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_Nextpay_Gateway_After_Form', $order_id, $woocommerce);





                $Amount = intval($order->order_total);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                )
                    $Amount = $Amount * 1;
                else if (strtolower($currency) == strtolower('IRHT'))
                    $Amount = $Amount * 1000;
                else if (strtolower($currency) == strtolower('IRHR'))
                    $Amount = $Amount * 100;
                else if (strtolower($currency) == strtolower('IRR'))
                    $Amount = $Amount / 10;


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Nextpay_gateway', $Amount, $currency);

                $Api_Key = $this->api_key;
                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Nextpay'));

                $products = array();
                $order_items = $order->get_items();
                foreach ((array) $order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';


                //Hooks for iranian developer
                $Description = apply_filters('WC_Nextpay_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Nextpay_Mobile', $Mobile, $order_id);
                do_action('WC_Nextpay_Gateway_Payment', $order_id, $Description, $Mobile);

                try {
                    $parameters = array
                    (
                        "api_key"=>$Api_Key,
                        "order_id"=> $order->get_order_number(),
                        "amount"=>$Amount,
                        "callback_uri"=>$CallbackUrl
                    );


                    $nextpay = new Nextpay_Payment($parameters);
                    $result = $nextpay->token();

                    if(intval($result->code) == -1){
                        $Payment_URL = 'http://api.nextpay.org/gateway/payment/' . $result->trans_id;
                        echo 'در حال انتقال به درگاه بانکی  ....';
                        echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . $Payment_URL . "'; };</script>";
                        exit;
                    } else {
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result->code ;
                        $Fault = '';
                    }
                } catch (Exception $ex) {
                    $Message = $ex->getMessage();
                    $Fault = '';
                }


                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Nextpay_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Nextpay_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Nextpay_Send_to_Gateway_Failed', $order_id, $Fault);
                }

            }

            public function Return_from_Nextpay_Gateway() {

                include_once dirname(__FILE__).'/nextpay_payment.php';

                $InvoiceNumber = isset($_POST['order_id']) ? $_POST['order_id'] : '';

                global $woocommerce;

                if (isset($_GET['wc_order']))
                    $order_id = $_GET['wc_order'];
                else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_nextpay;
                    unset($woocommerce->session->order_id_nextpay);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_order_currency();
                    $currency = apply_filters('WC_Nextpay_Currency', $currency, $order_id);

                    if ($order->status != 'completed') {


                        $Api_Key = $this->api_key;
                        $Amount = intval($order->order_total);
                        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                        if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                        )
                            $Amount = $Amount * 1;
                        else if (strtolower($currency) == strtolower('IRHT'))
                            $Amount = $Amount * 1000;
                        else if (strtolower($currency) == strtolower('IRHR'))
                            $Amount = $Amount * 100;
                        else if (strtolower($currency) == strtolower('IRR'))
                            $Amount = $Amount / 10;

                        $Trans_ID = $_POST['trans_id'];

                        $parameters = array
                        (
                            'api_key'	=> $Api_Key,
                            'order_id'	=> $order->get_order_number(),
                            'trans_id' 	=> $Trans_ID,
                            'amount'	=> $Amount,
                        );

                        $nextpay = new Nextpay_Payment();
                        $result = $nextpay->verify_request($parameters);

                        if (intval($result) == 0) {
                            $Status = 'completed';
                            $Transaction_ID = $Trans_ID;
                            $Fault = '';
                            $Message = '';
                        } else {
                            $Status = 'failed';
                            $Transaction_ID = $Trans_ID;
                            $Fault = $result;
                            $Message = 'تراکنش ناموفق بود';
                        }


                        if ($Status == 'completed') {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);



                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_Nextpay_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->success_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_Nextpay_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Nextpay_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }
                        else {




                            $tr_id = ( $Transaction_ID && $Transaction_ID != 0 ) ? ('<br/>توکن : ' . $Transaction_ID) : '';

                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                            $Note = apply_filters('WC_Nextpay_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_Nextpay_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_Nextpay_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                            wp_redirect($woocommerce->cart->get_checkout_url());
                            exit;
                        }
                    }
                    else {


                        $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                        $Notice = wpautop(wptexturize($this->success_massage));

                        $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                        $Notice = apply_filters('WC_Nextpay_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                        if ($Notice)
                            wc_add_notice($Notice, 'success');


                        do_action('WC_Nextpay_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    }
                }
                else {



                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_Nextpay_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Nextpay_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            }

        }

    }
}

add_action('plugins_loaded', 'Load_Nextpay_Gateway', 0);
