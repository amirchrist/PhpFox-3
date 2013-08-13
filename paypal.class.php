<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');
class Phpfox_Gateway_Api_Paypal implements Phpfox_Gateway_Interface
{
	private $_aParam = array();
	private $_aCurrency = array('USD', 'GBP', 'EUR', 'AUD', 'CAD', 'JPY', 'NZD', 'CHF', 'HKD', 'SGD', 'SEK', 'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN', 'BRL', 'MYR', 'PHP', 'TWD', 'THB');
	public function __construct()
	{
		
	}	
	
	public function set($aSetting)
	{
		$this->_aParam = $aSetting;
		
		if (Phpfox::getLib('parse.format')->isSerialized($aSetting['setting']))
		{
			$this->_aParam['setting'] = unserialize($aSetting['setting']);
		}
	}
	
	public function getEditForm()
	{
		return array(
			'Zarinpalwg_api' => array(
				'phrase' => 'Zarinpalwg API Code',
				'phrase_info' => 'The API code that represents your Zarinpalwg gateway.',
				'value' => (isset($this->_aParam['setting']['Zarinpalwg_api']) ? $this->_aParam['setting']['Zarinpalwg_api'] : '')
			)
		);
	}
	
	public function getForm()
	{
		if (!in_array($this->_aParam['currency_code'], $this->_aCurrency))
		{
			if (isset($this->_aParam['alternative_cost']))
			{
				$aCosts = unserialize($this->_aParam['alternative_cost']);
				$bPassed = false;
				foreach ($aCosts as $sCode => $iPrice)
				{
					if (in_array($sCode, $this->_aCurrency))
					{
						$this->_aParam['amount'] = $iPrice;
						$this->_aParam['currency_code'] = $sCode;
						$bPassed = true;
						break;
					}
				}

				if ($bPassed === false)
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}

        $api = $this->_aParam['setting']['Zarinpalwg_api'];
        $amount = $this->_aParam['amount'];
        $ReturnPath =  urlencode(Phpfox::getLib('gateway')->url('paypal'));//$this->_aParam['return'];
		
		@session_start();
        $_SESSION['ResNumber'] = $this->_aParam['item_number'].'|'.$amount;
		$_SESSION['amount'] = $amount*10;
		$desc = $this->_aParam['item_number'];
		$result = $this->_send($desc,$api,$amount*10,$ReturnPath){
		
		$go = "https://www.zarinpal.com/pg/StartPay/". $result->Authority;
		
        
        if($result->Status = 100 ){
            $aForm = array(
			'url' => $go ,
            'param' => array(
				'Price' => '1'
			)
    		);
            return $aForm;
        }
        else
        {
            return false;
        }
	}
	
	public function callback()
	{
		Phpfox::log('Starting Zarinpalwg callback');

        $messagePage = '<html xmlns="http://www.w3.org/1999/xhtml">
        <head runat="server">
            <title>نتيجه پرداخت </title>
            <meta http-equiv="Content-Type" content="Type=text/html; charset=utf-8" />
        </head>
        <body style="text-align:center">
            <br/><br/><br/><br/>
            <div style="border: 1px solid;margin:auto;padding:15px 10px 15px 50px; width:600px;font-size:8pt; line-height:25px;$Style$">
             $Message$
            </div> <br /></br> <a href="/index.php" style="font:size:8pt ; color:#333333; font-family:tahoma; font-size:7pt" >بازگشت به صفحه اصلي</a>
        </body>
        </html>';

        $style = 'font-family:tahoma; text-align:right; direction:rtl';
        $style_succ = 'color: #4F8A10;background-color: #DFF2BF;'.$style;
        $style_alrt = 'color: #9F6000;background-color: #FEEFB3;'.$style;
        $style_errr = 'color: #D8000C;background-color: #FFBABA;'.$style;

        $api = $this->_aParam['setting']['Zarinpalwg_api'];
		@session_start();
        $aParts = explode('|',$$_SESSION['ResNumber']);
        $Price = $aParts[2];

        Phpfox::log('Attempting callback');
		$status = $_GET['Status'];
		$au = $_GET['Authority'];
		$amount = $_SESSION['amount'];
        if($status == 'OK')
        {
        		$result = $this->_get($api,$au,$amount){
				//_get($url,$api,$trans_id,$id_get);
        		if($result->Status == 100)// Your Peyment Code Only This Event
        		{
                    Phpfox::log('Callback OK');

                    Phpfox::log('Attempting to load module: ' . $aParts[0]);

        			if (Phpfox::isModule($aParts[0]))
        			{
        				Phpfox::log('Module is valid.');
        				Phpfox::log('Checking module callback for method: paymentApiCallback');
        				if (Phpfox::hasCallback($aParts[0], 'paymentApiCallback'))
        				{
        					Phpfox::log('Module callback is valid.');

        			  		$sStatus = '100';

        					Phpfox::log('Status built: ' . $sStatus);

      						Phpfox::log('Executing module callback');
      						Phpfox::callback($aParts[0] . '.paymentApiCallback', array(
      								'gateway' => 'paypal',
      								'ref' => $$result->Authority,
      								'status' => $result->Status,
      								'item_number' => $aParts[1],
      								'total_paid' => $Price
      							)
      						);
      						header('HTTP/1.1 200 OK');
                            $mss = 'کاربر گرامي ، عمليات پرداخت با موفقيت به پايان رسيد .<br><br>جهت پيگيري هاي آتي شماره رسيد پرداخت خود را ياداشت فرماييد : '.$result->RefID.'<br> با تشکر <br>';
                            $messagePage = str_replace('$Message$',$mss,$messagePage);
                            $messagePage = str_replace('$Style$',$style_succ,$messagePage);
                            echo $messagePage;

                            return;
        				}
        				else
        				{
        					Phpfox::log('Module callback is not valid.');
        				}
        			}
        			else
        			{
        				Phpfox::log('Module is not valid.');
        			}
        		}
        		else
        		{
        			Phpfox::log('Callback '.$Status);
                   	$sStatus = $Status;
                    $mss = 'کاربر گرامي ، عمليات  اعتبار سنجي پرداخت شما با خطا مواجه گرديد .<br> درصورتي که پرداخت شما موفقيت آميز انجام شده باشد پس از بررسي اطلاعات پرداخت براي شما ارسال خواهد شد . <br> با تشکر <br>  ';
                    $messagePage = str_replace('$Message$',$mss,$messagePage);
                    $messagePage = str_replace('$Style$',$style_alrt,$messagePage);
                    echo $messagePage;
                    return;
        		}
	        }
          	else
      		{
      			Phpfox::log('Callback FAILED');
                $sStatus = 'cancel';
                header('HTTP/1.1 200 OK');
      		}
            $mss = 'پرداخت ناموفق / خطا در عمليات پرداخت ! کاربر گرامي ، فرايند پرداخت با خطا مواجه گرديد !<br> با تشکر ';
            $messagePage = str_replace('$Message$',$mss,$messagePage);
            $messagePage = str_replace('$Style$',$style_errr,$messagePage);
            echo $messagePage;
    }
	
	public function _send($desc,$api,$amount,$redirect){
	
	
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentRequest(
	array(
					'MerchantID' 	=> $api ,
					'Amount' 		=> $amount ,
					'Description' 	=> $desc ,
					'Email' 		=> '' ,
					'Mobile' 		=> '' ,
					'CallbackURL' 	=> $redirect

				)
	 );
        return $res;

	}
	public function _get($api,$au,$amount){
	
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentVerification(
			array(
					'MerchantID'	 => $api ,
					'Authority' 	 => $au ,
					'Amount'	 	=> $amount
				)
		);
        return $res;
	}
}

// by masoud amini
?>