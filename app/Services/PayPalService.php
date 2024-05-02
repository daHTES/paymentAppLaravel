<?php


namespace App\Services;

use App\Traits\ConsumesExternalServices;
use Illuminate\Http\Request;

class PayPalService{

    use ConsumesExternalServices;

    protected $baseUri;
    protected $client_id;
    protected $client_secret;

    public function __construct(){

        $this->baseUri = config('services.paypal.base_uri');
        $this->client_id = config('services.paypal.client_id');
        $this->client_secret = config('services.paypal.client_secret');

    }

    public function resolveAuthorization(&$queryParams, &$formParams, &$headers){

        $headers['Authorization'] = $this->resolveAccessToken();

    }

    public function decodeResponse($response){

        return json_decode($response);
    }

    public function resolveAccessToken(){

        $credentials = base64_encode("{$this->client_id}:{$this->client_secret}");

        return "Basic {$credentials}";
    }

    public function handlePayment(Request $request){

            $order = $this->createOrder($request->value, $request->currency);

            $orderLinks = collect($order->links);

            $approve = $orderLinks->where('rel', 'approve')->first();

            session()->put('approvalId', $order->id);

            return redirect($approve->href);
    }

    public function handleApproval(){

        if(session()->has('approvalId')){
            $approvalId = session()->get('approvalId');

            $payment = $this->capturePayment($approvalId);

            $name = $payment->payer->name->given_name;
            $payment = $payment->purchase_units[0]->payments->captures[0]->amount;

            $amount = $payment->value;
            $currency = $payment->currency_code;

            return redirect()->route('home')->with(['payment' => "Thanks< {$name}. We received your {$amount}{$currency} payment."]);
        }

        return redirect()->route('home')->withErrors('We cannot capture the payment, Try again please');

    }

    public function createOrder($value, $currency){

        return $this->makeRequest(
            'POST',
            '/v2/checkout/orders',
            [],
            [
                "intent" => 'CAPTURE',
                "purchase_units" => [
                    0 => [
                        'amount' => [
                            'currency_code' =>  strtoupper($currency),
                            'value' => round($value * $factor = $this->resolveFactor($currency)) / $factor,
                        ]
                    ]
                ],
                'experience_context' => [
                    'brand_name' => config('app.name'),
                    'payment_method_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('approval'),
                    'cancel_url' => route('cancelled'),
                ]
            ],
            [],
            $isJsonRequest = true
        );
    }

    public function capturePayment($approvalid){

        return $this->makeRequest(
            'POST',
            "/v2/checkout/orders/{$approvalid}/capture",
            [],
            [],
            [
                'Content-Type' => 'application/json'
            ]
        );
    }

    public function resolveFactor($currency){

        $zeroDecimalCurrencies = ['JPY'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return 1;
        }

        return 100;

    }

}
