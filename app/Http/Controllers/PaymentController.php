<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\PaymentPlatform;
use App\Resolves\PaymentPlatformResolve;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    protected $paymentPlatformResolve;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(PaymentPlatformResolve $paymentPlatformResolve)
    {
        $this->middleware('auth');
        $this->paymentPlatformResolve = $paymentPlatformResolve;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function pay(Request $request)
    {
        $rules = [
            'value' => ['required', 'numeric', 'min:5'],
            'currency' => ['required', 'exists:currencies,iso'],
            'payment_platform' => ['required', 'exists:payment_platforms,id'],
        ];


        $request->validate($rules);

        $paymentPlatform = $this->paymentPlatformResolve->resolveService($request->payment_platform);

        session()->put('paymentPlatformId', $request->payment_platform);

        return $paymentPlatform->handlePayment($request);

        return $request->all();

    }

    public function approval(){

        if(session()->has('paymentPlatformId')){

            $paymentPlatform = $this->paymentPlatformResolve->resolveService(session()->get('paymentPlatformId'));

            return $paymentPlatform->handleApproval();
        }

        return redirect()->route('home')->withErrors('We cannot retv. your payment platform. Try again');

    }

    public function cancelled(){

        return redirect()->route('home')->withErrors('You cancelled the payment');

    }
}
