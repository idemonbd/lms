<?php

namespace App\Http\Controllers;

use App\Payment;
use App\Agreement;
use App\Accountant;
use App\PaymentReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{

    public function index()
    {
        $payments = Payment::all();
        $refunds = PaymentReturn::all();
        $agreements = Agreement::where('status',1)->get();
        return view('rent.payment.index', compact('payments', 'agreements','refunds'));
    }

    public function store(Request $request)
    {

        $agreement = Agreement::findOrFail($request->agreement_id);

        $payment = new Payment();
        $payment->id = $request->serial;
        $payment->agreement_id = $request->agreement_id;
        if (!($payment->accountant_id = Accountant::active()->id)) {
            return redirect(route('accountant.index'))->with('info','Set an accountant first');
        }
        $payment->entry_id = Auth::id();
        $payment->type = $request->type;
        $payment->state = 'payment';
        $payment->amount = $request->amount;
        $payment->method = $request->method;
        $payment->year = $request->year;
        $payment->month = $request->month;
        $payment->description = $request->description;
        $payment->tnxid = uniqid();
        if ($request->gst) {
            $payment->gst = $request->gst;
        }

        if ($request->type == 'rent' || $request->type == 'bill') {
            $this->rentPayment($request, $payment);
        }

        // PAYMENT METHODS
        if ($request->method == 'cash') {
            $this->cashPayment($request);
        }
        elseif ($request->method == 'bank') {
            $this->bankPayment( $request, $payment );
        }
        elseif ($request->method == 'wallet') {
            if ($agreement->wallet >= $request->amount) {
                $agreement->wallet -= $request->amount;
                $agreement->save();
            } else {
                return redirect()->back()->with('warning', 'Dont have enough balace in wallet');
            }
        }

        if ($request->type == 'wallet') {
            $agreement->wallet += $request->amount;
            $agreement->save();
        }


        // return $payment;
        $payment->save();
        return redirect()->back()->with('success', 'Added Successfully');
    }



    public function show(Payment $payment)
    {
        // return $payment;
        return view('rent.payment.show',compact('payment'));
    }

    public function edit(Payment $payment)
    {

        return view('rent.payment.edit', compact('payment'));
    }


    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            "method" => "required",
            "amount" => "required",
        ]);

        $payment->method = $request->method;
        $payment->amount = $request->amount;
        $payment->account = $request->account;
        $payment->remarks = $request->remarks;
        $payment->created_at = $request->created_at;
        $payment->save();

        return redirect(route('payment.index'))->with('success', 'Updated Successfully');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }



    public function rentPayment($request, $payment)
    {
      return $request->validate([
            "agreement_id" => "required|integer",
            "type" => "required|string",
            "year" => "required|integer",
            "month" => "required|array",
            "method" => "required|string",
            "amount" => "required|integer|gt:0",
        ]);
    }

    public function walletPayment($request)
    {

    }

    public function bankPayment( $request, $payment )
    {
        $payment->bank = $request->bank;
        $payment->account = $request->account;
        $payment->branch = $request->branch;
        $payment->cheque = $request->cheque;
        if ($request->file('attachment')) {
            $payment->attachment = $request->attachment->store('/cheque');
        }
    }

    public function cashPayment()
    {
        //
    }
}
