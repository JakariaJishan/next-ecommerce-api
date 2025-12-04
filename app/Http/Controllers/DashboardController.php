<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * @operationId Dashboard summary
     */
    public function dashboardSummary()
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view dashboard data.', [], null);
            }

            // Check if the user has permission (e.g., admin role)
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view dashboard data.', [], null);
            }

            // Count users with the 'user' role
            $totalUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })->count();

            // Count total products
            $totalProducts = DB::table('products')->count();

            // Calculate total income from paid orders
            $totalIncome = DB::table('orders')
                ->where('payment_status', 'paid')
                ->sum('grand_total');

            // Get users created per month (based on created_at)
            $usersByMonth = User::whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })
                ->select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as count') // Changed to 'count'
                )
                ->groupBy('month_number')
                ->get();

            // Get products created per month (based on created_at) for line chart
            $productsByMonth = DB::table('products')
                ->select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as count') // Changed to 'count'
                )
                ->groupBy('month_number')
                ->get();

            // Get income per month from paid orders (based on updated_at)
            $incomeByMonth = DB::table('orders')
                ->select(
                    DB::raw('MONTH(updated_at) as month_number'),
                    DB::raw('ROUND(SUM(grand_total), 2) as count') // Changed to 'count' (still rounded for income)
                )
                ->where('payment_status', 'paid')
                ->groupBy('month_number')
                ->get();

            // Define the first five months for line chart
            $monthsForLineChart = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May'
            ];

            // Initialize chart_data for total_users
            $usersChartData = array_map(function ($monthName) {
                return [
                    'month' => $monthName,
                    'count' => 0 // Changed to 'count'
                ];
            }, $monthsForLineChart);

            // Fill in the actual user counts
            foreach ($usersByMonth as $record) {
                $monthIndex = $record->month_number - 1;
                if (isset($usersChartData[$monthIndex])) {
                    $usersChartData[$monthIndex]['count'] = $record->count;
                }
            }

            // Initialize chart_data for total_products
            $productsChartData = array_map(function ($monthName) {
                return [
                    'month' => $monthName,
                    'count' => 0 // Changed to 'count'
                ];
            }, $monthsForLineChart);

            // Fill in the actual products counts
            foreach ($productsByMonth as $record) {
                $monthIndex = $record->month_number - 1;
                if (isset($productsChartData[$monthIndex])) {
                    $productsChartData[$monthIndex]['count'] = $record->count;
                }
            }

            // Initialize chart_data for total_income
            $incomeChartData = array_map(function ($monthName) {
                return [
                    'month' => $monthName,
                    'count' => 0.00 // Changed to 'count', kept as float for income
                ];
            }, $monthsForLineChart);

            // Fill in the actual income
            foreach ($incomeByMonth as $record) {
                $monthIndex = $record->month_number - 1;
                if (isset($incomeChartData[$monthIndex])) {
                    $incomeChartData[$monthIndex]['count'] = (float)$record->count;
                }
            }

            // Get total products created per month (based on created_at) for barChart
            $productsPostedByMonth = DB::table('products')
                ->select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as products_count')
                )
                ->groupBy('month_number')
                ->get()
                ->pluck('products_count', 'month_number')
                ->toArray();

            // Get paid orders per month (based on updated_at) for barChart
            $paidOrdersByMonthForBarChart = DB::table('orders')
                ->select(
                    DB::raw('MONTH(updated_at) as month_number'),
                    DB::raw('COUNT(*) as paid_count')
                )
                ->where('payment_status', 'paid')
                ->groupBy('month_number')
                ->get()
                ->pluck('paid_count', 'month_number')
                ->toArray();

            // Define all months for barChart
            $monthsForBarChart = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December'
            ];

            // Initialize result array for barChart
            $barChart = array_map(function ($monthName, $monthNumber) use ($productsPostedByMonth, $paidOrdersByMonthForBarChart) {
                return [
                    'month' => $monthName,
                    'products' => isset($productsPostedByMonth[$monthNumber]) ? (int)$productsPostedByMonth[$monthNumber] : 0,
                    'orders_paid' => isset($paidOrdersByMonthForBarChart[$monthNumber]) ? (int)$paidOrdersByMonthForBarChart[$monthNumber] : 0
                ];
            }, $monthsForBarChart, array_keys($monthsForBarChart));

            // Prepare the data
            $data = [
                'line_chart' => [
                    [
                        'label' => 'total users',
                        'total' => $totalUsers, // Changed to 'total'
                        'chart_data' => array_values($usersChartData)
                    ],
                    [
                        'label' => 'total products',
                        'total' => $totalProducts, // Changed to 'total'
                        'chart_data' => array_values($productsChartData)
                    ],
                    [
                        'label' => 'total income',
                        'total' => round($totalIncome, 2), // Changed to 'total'
                        'chart_data' => array_values($incomeChartData)
                    ]
                ],
                'bar_chart' => array_values($barChart)
            ];

            // Return the data
            return apiResponse(true, 'Dashboard summary retrieved successfully.', $data, null, 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving dashboard summary.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Top sold products
     */
    public function topSoldProducts()
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view dashboard data.', [], null);
            }

            // Check if the user has permission (e.g., admin role)
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view dashboard data.', [], null);
            }

            // Top sold products based on order_items quantity
            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.*', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('products.id')
                ->orderByDesc('total_sold')
                ->limit(6)
                ->get();

            $data = [
                'top_sold_products' => $topProducts
            ];

            // Return the data
            return apiResponse(true, 'Top sold products retrieved successfully.', $data, null, 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving top sold products.',
                $e->getMessage(), 'error');
        }
    }

}
