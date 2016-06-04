<?php
/*
 * HostBill zarinpal gateway module
 * @see https://www.zarinpal.com/lab/
 *
 * 2013 HostBill -  Complete Client Management, Support and Billing Software
 * https://www.zarinpal.com/lab/
 */

class zarinpal extends PaymentModule
{
    protected $modname = 'درگاه پرداخت زرين پال';
    protected $description = 'این ماژول توسط تيم توسعه زرين پال نوشته شده است';
    protected $supportedCurrencies = [];

    protected $configuration = [
        'merchant_id' => [
            'value'       => '',
            'type'        => 'input',
            'description' => 'لطفا کد درگاه پرداخت خود را وارد نماييد',
        ],
        'success_message' => [
            'value'       => 'پرداخت با موفقیت انجام شد',
            'type'        => 'input',
            'description' => 'پیام موفقیت',
        ],
    ];

    public function drawForm()
    {
        $amount = intval($this->amount);
        $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
        $result = $client->PaymentRequest([
            'MerchantID'     => $this->configuration['merchant_id']['value'],
            'Amount'         => $amount,
            'Description'    => 'پرداخت فاکتور شماره: '.$this->invoice_id,
            'Email'          => '',
            'Mobile'         => '',
            'CallbackURL'    => $this->callback_url,
        ]);

        if ($result->Status == 100) {
            $_SESSION['invoiceid'] = $this->invoice_id;
            $_SESSION['amount'] = $amount;
            header('Location: https://www.zarinpal.com/pg/StartPay/'.$result->Authority);
        } else {
            echo 'خطا در اتصال به زرين پال. کد خطا: '.$result->Status;
        }
    }

    public function callback()
    {
        if ($_GET['Status'] == 'OK') {
            $amount = $_SESSION['amount'];
            $client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
            $result = $client->PaymentVerification([
                'MerchantID'     => $this->configuration['merchant_id']['value'],
                'Authority'      => $_GET['Authority'],
                'Amount'         => $amount,
            ]);
            if ($result->Status == 100) {
                //2. log incoming payment
                $this->logActivity([
                    'result' => 'Successfull',
                    'output' => $result,
                ]);

                //3. add transaction to invoice
                $invoice_id = $_SESSION['invoiceid'];
                $fee = 0;

                $this->addTransaction([
                    'in'             => $amount,
                    'invoice_id'     => $invoice_id,
                    'fee'            => $fee,
                    'transaction_id' => $result->RefID,
                ]);

                $this->addInfo($this->configuration['success_message']['value']);
                Utilities::redirect('?cmd=clientarea');
            } else {
                $this->logActivity([
                    'result' => 'Failed',
                    'output' => $_GET,
                ]);
            }
        } else {
            $this->logActivity([
                'result' => 'Failed',
                'output' => $_GET,
            ]);
            Utilities::redirect('?cmd=clientarea');
        }
    }
}
