<?php
namespace Twohill\Eway\Control;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NestedController;
use SilverStripe\SiteConfig\SiteConfig;
use Twohill\Eway\Model\Payment;

class EWayPaymentController extends Controller implements NestedController {
	protected $parentController;
	protected $payment;
	protected $urlSegment;


	public function __construct(Controller $parentController, HTTPRequest $request, Payment $payment) {
		parent::__construct();
		$this->parentController = $parentController;
		$this->payment = $payment;
		$this->urlSegment = $request->latestParam('Action');
	}

	private static $allowed_actions = array(
		'submit',
		'handle_response',
	);

    /**
     * Link fragments
     * @param string $action
     * @return string
     */
	public function Link($action = '') {
		return Controller::join_links($this->parentController->Link(), "/{$this->urlSegment}/$action");
	}

    /**
     * @param string $action
     * @return string
     */
    public function absoluteURL($action = '') {
		return Director::absoluteURL($this->Link($action));
	}

	/**
	 * Implement controller nesting
	 */
	public function getNestedController() {
		return $this->parentController;
	}

    /**
     * Fires off the request to EWay. If all goes well it should return a URL to redirect the
     * user to.
     * @param HTTPRequest $request
     * @throws Exception
     */
	public function submit($request) {

		$url = $this->constructPurchaseURL();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		//Allow for lax SSL (the library on the web server seems a bit iffy)
		curl_setopt( $ch , CURLOPT_SSL_VERIFYPEER , false );
		curl_setopt( $ch , CURLOPT_SSL_VERIFYHOST , false );

		$rawResponse = curl_exec($ch);
		$response = simplexml_load_string($rawResponse);

		if ($response) {
			if ($response->Result == 'True') {
				$this->redirect($response->URI);
			} else {
				// The transaction could not be set up (maybe due to incorrect account details?)
				throw new Exception("Error messaage from EWAY: {$response->Error}");
			}
		} else {
			// Something else went wrong
			$error = ($rawResponse) ? $rawResponse : curl_error($ch);
			throw new Exception("Error communicating with EWay: $error");
		}
	}

    /**
     * Eway redirects back to this function when the payment process is completed, including
     * a POST var with the code to access the payment details.
     *
     * This function then reads the result from EWay directly, adds the payment details,
     * and redirects based on the SiteConfig settings.
     * @param HTTPRequest $request
     * @throws \SilverStripe\ORM\ValidationException
     * @throws Exception
     */
	public function handle_response($request) {

		$paymentCode = $request->postVar('AccessPaymentCode');

		$url = $this->constructResultURL($paymentCode);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		//Allow for lax SSL (the library on the web server seems a bit iffy)
		curl_setopt( $ch , CURLOPT_SSL_VERIFYPEER , false );
		curl_setopt( $ch , CURLOPT_SSL_VERIFYHOST , false );


		$rawResponse = curl_exec($ch);
		$response = simplexml_load_string($rawResponse);

		if ($response) {

			$config = SiteConfig::current_site_config();
			if ($response->TrxnStatus == 'true') {
				$this->payment->AuthCode = (string) $response->AuthCode;
				$this->payment->TransactionNumber = (string) $response->TrxnNumber;
				$this->payment->Status = "Completed";
				$this->payment->write();

				$this->redirect($config->EWay_ReturnPage()->Link($config->EWay_ReturnAction));
			} else {
				$this->payment->Status = "Cancelled";
				$this->payment->write();
				$this->redirect($config->EWay_CancelPage()->Link($config->EWay_CancelAction));
			}
		} else {
			throw new Exception("Received an invalid response from EWay: $rawResponse");
		}

	}

	/**
	 * Builds the request URL to send to EWay via CURL. See
	 * http://www.eway.co.nz/developer/eway-api/hosted-payment-solution.aspx for full details
	 *
	 * @return String // the escaped URL
	 */
	protected function constructPurchaseURL() {
		$config = SiteConfig::current_site_config();
		$payment = $this->payment;

		$params = array(
			'CustomerID' => $config->EWay_CustomerID,
			'UserName' => $config->EWay_UserName,
			'Amount' => number_format($payment->Amount, 2, '.', ''), //2 decimal points required, no 1000's separator
			'Currency' => $config->EWay_Currency,
			'PageTitle' => $config->EWay_PageTitle,
			'PageDescription' => $config->EWay_PageDescription,
			'PageFooter' => $config->EWay_PageFooter,
			'Language=' => $config->EWay_Language,
			'CompanyName' => $config->EWay_CompanyName,
			'CustomerFirstName' => $payment->FirstName,
		    'CustomerLastName' => $payment->LastName,
			'CustomerAddress' => $payment->Address,
			'CustomerCity' => $payment->City,
			'CustomerState' => $payment->State,
			'CustomerPostCode' => $payment->PostCode,
			'CustomerCountry' => $payment->Country,
			'CustomerEmail' => $payment->Email,
			'CustomerPhone' => $payment->Phone,
			'InvoiceDescription' => $payment->InvoiceDescription,
			'CancelURL' => $this->absoluteURL('handle-response'),
			'ReturnUrl' => $this->absoluteURL('handle-response'),
			'MerchantReference' => $payment->Reference,
			'MerchantInvoice' => $payment->InvoiceReference,
			'ModifiableCustomerDetails' => $config->EWay_CustomersCanModifyDetails
		);

		if ($config->EWay_CompanyLogoID && Director::protocol() == 'https://') {
			$params['CompanyLogo'] = $config->EWay_CompanyLogo()->getAbsoluteURL();
		}
		if ($config->EWay_PageBannerID && Director::protocol() == 'https://') {
			$params['PageBanner'] = $config->EWay_PageBanner()->SetSize(960, 65)->getAbsoluteURL();
		}

		return $config->EWay_ServerRequestURL . '?' . http_build_query($params);
	}

	/**
	 * Constructs the URL necessary to retrieve the results of a particular transaction
	 *
	 * @param String $paymentCode // the payment code for the transaction required
	 * @return String // the escaped URL
	 */
	protected function constructResultURL($paymentCode) {
		$config = SiteConfig::current_site_config();

		$params = array(
			'CustomerID' => $config->EWay_CustomerID,
			'UserName' => $config->EWay_UserName,
			'AccessPaymentCode' => $paymentCode
		);
		return $config->EWay_ServerResultURL . '?' . http_build_query($params);
	}
}
