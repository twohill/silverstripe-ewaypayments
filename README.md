# silverstripe-ewaypayments
Silverstripe module for handling payments via eway

## Example usage in a controller.

TODO: write some actual documentation.
```php
<?php
//..
public function doConfirm($data, $form) {
	$this->redirect($this->Link('pay-order/submit'));
}

public function pay_order($request) {
	$member = $this->currentMember();
	$invoice = $member->UnpaidInvoice;
	
	$payment = $invoice->findOrMakePayment($member);
	
	return new EWayPaymentController($this, $request, $payment);
}

public function order_paid($request) {
	$invoice = $this->currentMember()->UnpaidInvoice;
	if ($invoice) {
		if ($invoice->PaymentID && $invoice->Payment()->Status == "Completed") {
			//@TODO some sort of payment processing
			$invoice->markPaid();

			return $this->redirect($this->Link('thanks'));
		}	
	}
	$this->redirect($this->Link());
	
}

public function order_cancelled($request) {
	if ($invoice = $this->currentMember()->UnpaidInvoice) {
		return $this->customise(array(
			"Title" => "Payment Unsuccessfull",
			"Content" => $this->UnsuccessfulPaymentContent,
			"Invoice" => $invoice,
			"Form" => $this->ConfirmationAndPaymentForm()
		));
	} else {
		$this->redirect($this->Link());
	}
}
```