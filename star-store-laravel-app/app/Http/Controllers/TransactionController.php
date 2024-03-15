<?php

namespace App\Http\Controllers;

use App\Http\Requests\transaction\StoreTransactionRequest;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::all();
        $response = $transactions->map(function ($transaction) {
            return [
                'client_id' => $transaction->user_id,
                'purchase_id' => $transaction->id,
                'value' => $transaction->total_to_pay,
                'date' => $transaction->created_at->format('d/m/Y'),
                'card_number' => '**** **** **** ' . $transaction->creditCard->card_number
            ];
        });

        return response()->json($response, 200);
    }

    public function show(string $id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        return response()->json(['data' => $transaction], 200);
    }

    public function showByUser(string $id)
    {
        $transactions = Transaction::where('user_id', $id)->get();

        if ($transactions->isEmpty()) {
            return response()->json(['message' => 'Transactions not found'], 404);
        }

        $response = $transactions->map(function ($transaction) {
            return [
                'client_id' => $transaction->user_id,
                'purchase_id' => $transaction->id,
                'value' => $transaction->total_to_pay,
                'date' => $transaction->created_at->format('d/m/Y'),
                'card_number' => '**** **** **** ' . $transaction->creditCard->card_number
            ];
        });

        return response()->json($response, 200);
    }
    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();

        $client = User::find($validated['client_id']);

        if (!$client)
            return response()->json(['message' => 'Client not found'], 404);

        $creditCard = DB::table('credit_cards')
            ->where('card_number', substr($validated['credit_card']['card_number'], -4))
            ->where('card_holder_name', $validated['credit_card']['card_holder_name'])
            ->where('exp_date', $validated['credit_card']['exp_date'])
            ->where('cvv', $validated['credit_card']['cvv'])
            ->first();

        if (!$creditCard) {
            $creditCard = CreditCard::create(
                [
                    'card_number' => substr($validated['credit_card']['card_number'], -4), //last four digits (1234123412341234 -> 1234
                    'value' => $validated['credit_card']['value'],
                    'cvv' => $validated['credit_card']['cvv'],
                    'card_holder_name' => $validated['credit_card']['card_holder_name'],
                    'exp_date' => $validated['credit_card']['exp_date']
                ]
            );
        }

        $transaction = Transaction::create(
            [
                'user_id' => $client->id,
                'credit_card_id' => $creditCard->id,
                'total_to_pay' => $validated['total_to_pay']
            ]
        );

        $response = [
            'client_id' => $transaction->user_id,
            'purchase_id' => $transaction->id,
            'value' => $transaction->total_to_pay,
            'date' => $transaction->created_at->format('d/m/Y'),
            'card_number' => '**** **** **** ' . $creditCard->card_number
        ];

        return response()->json($response, 201);
    }
}
