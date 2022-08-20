# Paytrail Payment Class

## Overview
Helper class for using the new Paytrail API.

This class is designed to be used on websites, that don't have the ability to use composer and want a custom solution for payments. This class does not wrap around the entire **create payment** endpoint, but instead gives you a more clear view on how payments are created. At least I hope so.

## Example
```
require_once('paytrail.class.php');

$merchant_id = '375917';
$secret = 'SAIPPUAKAUPPIAS';

$Paytrail = new Paytrail($merchant_id, $secret);

$Payment = $Paytrail->addCustomer(array(
    'email'         => 'test.customer@example.com'
))->addProduct(array(
    'unitPrice'     => 1525,
    'units'         => 1,
    'vatPercentage' => 24,
    'productCode'   => '#1234',
    'deliveryDate'  => '2018-09-01'
))->addCallbackUrls(array(
    'success'       => 'https://ecom.example.com/cart/success',
    'cancel'        => 'https://ecom.example.com/cart/cancel'
))->addBody(array(
    'stamp'         => 'unique-identifier-for-merchants',
    'reference'     => '3759170',
    'currency'      => 'EUR',
    'language'      => 'FI',
))->createPayment();
```