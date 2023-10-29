<?php

namespace App\Http\Controllers;

use App\Models\Mark;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiController extends Controller
{
    /**
     * Get all students with pagination.
     */
    function getStudents(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'sometimes|required',
                'length' => 'sometimes|required|numeric',
                'sort_name' => 'sometimes|required', Rule::in(['student_id', 'student_name', 'standard']),
                'sort_order' => 'sometimes|required', Rule::in(['asc', 'desc']),
                'page' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                return response(['errors' => $validator->errors(), 'message' => 'Validation Error', 'code' => 201]);
            }
            $query = $request->get('query');
            $sort_name = $request->get('sort_name', 'student_id');
            $sort_order = $request->get('sort_order', 'asc');
            $length = $request->get('query', 10);
            $page = $request->get('page', 1);
            $data = Student::query();
            if ($query != "") {
                $data->where("student_id", $query)
                    ->orWhere("student_name", "like", "%$query%")
                    ->orWhere("standard", "like", "%$query%");
            }
            $count = $data->count();
            $data->orderBy($sort_name, $sort_order);
            $data->offset(($page - 1) * $length)->limit($length);
            return response(['data' => $data->get(), 'total' => $count, 'page' => $page, 'last_page' => round($count / $length), 'code' => 200]);
        } catch (\Exception $e) {
            return response(['message' => 'Something went wrong, Please try again!', 'code' => 400]);
        }
    }

    /**
     * Get result of students.
     */
    function fetchResult()
    {
        try {
            $data = Student::all();
            if ($data) {
                $student_results = [];
                foreach ($data as $student) {
                    $total = 0;
                    foreach ($student->mark as $mark) {
                        $total += $mark->marks;
                    }
                    $total_percent = round(($total / 400) * 100, 2);
                    $result = $total_percent <= 35 ? 'Fail' : ($total_percent <= 60 ? 'Second class' : ($total_percent <= 85 ? 'First class' : 'Distinction'));
                    $array = [
                        'student_name' => $student->student_name,
                        'total_marks' => $total,
                        'total_percent' => $total_percent,
                        'result' => $result,
                    ];
                    array_push($student_results, $array);
                }
                return response(['data' => $student_results, 'code' => 200]);
            }
            return response(['message' => 'No data found!', 'code' => 202]);
        } catch (\Exception $e) {
            return response(['message' => 'Something went wrong, Please try again!', 'code' => 400]);
        }
    }

    /**
     * Get transaction summary of user of last 90 days.
     * Daily closing balance of 90 days
     * 90 days average balance
     * First 30 days average closing balance
     * Last 30 days average closing balance
     */
    function transactionSummary(Request $request){
        if ($request->has('user_id')){
            $user_id = $request->get('user_id');
            $transactions = Transaction::query()
                ->selectRaw('trans_plaid_date as date, SUM(trans_plaid_amount) as total_amount')
                ->where('trans_user_id',$user_id)
                ->groupBy('trans_plaid_date')
                ->orderBy('trans_plaid_date','desc')
                ->limit(90)
                ->get();
            if (count($transactions) > 0){
                $dailyClosing = [];
                $currentBalance = 0;
                foreach($transactions as $transaction){
                    $currentBalance += $transaction->total_amount;
                    $dailyClosing[$transaction->date] = round($currentBalance,2);
                }

                $averageBalance = array_sum($dailyClosing) / 90;
                $first30DaysAvg = array_sum(array_slice($dailyClosing, 0, 30)) / 30;
                $last30DaysAvg = array_sum(array_slice($dailyClosing, -30)) / 30;

                return response(['data' => [
                    'daily_closing_balances' => $dailyClosing,
                    'average_90_days' => round($averageBalance,2),
                    'average_first_30_days' => round($first30DaysAvg,2),
                    'average_last_30_days' => round($last30DaysAvg,2)
                ], 'code' => 200]);
            }else{
                return response(['message' => 'No transactions found for this user!', 'code' => 202]);
            }
        }else{
            return response(['message' => 'User id is required!', 'code' => 202]);
        }
    }

    /**
     * Get transaction details of user.
     * Last 30 days income except 18020004 this category id
     * Debit transaction count in 30 days
     * Sum of debit trans amount done on Friday/Saturday/Sunday
     * Sum of income with transaction amount > 15
     */
    function transactions(Request $request){
        if ($request->has('user_id')){
            $user_id = $request->get('user_id');
            $thirtyDaysAgo = Carbon::now()->subDays(30);

            $creditSum = Transaction::query()
                ->where('trans_user_id',$user_id)
                ->where('trans_plaid_category_id', '!=', 18020004)
                ->where('trans_plaid_date','>=',$thirtyDaysAgo)
                ->where('trans_plaid_amount','>',0)
                ->sum('trans_plaid_amount');

            $debitCount = Transaction::query()
                ->where('trans_user_id',$user_id)
                ->where('trans_plaid_date','>=',$thirtyDaysAgo)
                ->where('trans_plaid_amount','<',0)->count();

            $weekendDebitSum = Transaction::query()
                ->where('trans_user_id',$user_id)
                ->where('trans_plaid_date','>=',$thirtyDaysAgo)
                ->where('trans_plaid_amount','<',0)
                ->whereIn(DB::raw('DAYOFWEEK(trans_plaid_date)'), [6, 7, 1])
                ->sum('trans_plaid_amount');

            $incomeAboveFifteen = Transaction::where('trans_user_id',$user_id)
                ->where('trans_plaid_date', '>=', $thirtyDaysAgo)
                ->where('trans_plaid_amount', '>', 15)
                ->sum('trans_plaid_amount');

            return response(['data' => [
                'last_30_days_income' => round($creditSum,2),
                'debit_transaction_count' => $debitCount,
                'weekend_debit_amount' => round($weekendDebitSum,2),
                'income_above_15' => round($incomeAboveFifteen,2),
            ], 'code' => 200]);
        }else{
            return response(['message' => 'User id is required!', 'code' => 202]);
        }
    }
    
}
