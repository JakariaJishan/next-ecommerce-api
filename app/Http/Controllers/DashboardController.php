<?php

namespace App\Http\Controllers;

use App\Models\Ads;
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

            // Count total ads
            $totalAds = DB::table('ads')->count();

            // Calculate total income from sold ads
            $totalIncome = DB::table('ads')
                ->where('status', 'sold')
                ->sum('price');

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

            // Get ads posted per month (based on created_at) for line chart
            $adsByMonth = DB::table('ads')
                ->select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as count') // Changed to 'count'
                )
                ->groupBy('month_number')
                ->get();

            // Get income per month (sum of price for sold ads based on updated_at)
            $incomeByMonth = DB::table('ads')
                ->select(
                    DB::raw('MONTH(updated_at) as month_number'),
                    DB::raw('ROUND(SUM(price), 2) as count') // Changed to 'count' (still rounded for income)
                )
                ->where('status', 'sold')
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

            // Initialize chart_data for total_ads
            $adsChartData = array_map(function ($monthName) {
                return [
                    'month' => $monthName,
                    'count' => 0 // Changed to 'count'
                ];
            }, $monthsForLineChart);

            // Fill in the actual ads counts
            foreach ($adsByMonth as $record) {
                $monthIndex = $record->month_number - 1;
                if (isset($adsChartData[$monthIndex])) {
                    $adsChartData[$monthIndex]['count'] = $record->count;
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

            // Get total ads posted per month (based on created_at) for barChart
            $adsPostedByMonth = DB::table('ads')
                ->select(
                    DB::raw('MONTH(created_at) as month_number'),
                    DB::raw('COUNT(*) as ads_count')
                )
                ->groupBy('month_number')
                ->get()
                ->pluck('ads_count', 'month_number')
                ->toArray();

            // Get sold ads per month (based on updated_at) for barChart
            $soldAdsByMonthForBarChart = DB::table('ads')
                ->select(
                    DB::raw('MONTH(updated_at) as month_number'),
                    DB::raw('COUNT(*) as sold_count')
                )
                ->where('status', 'sold')
                ->groupBy('month_number')
                ->get()
                ->pluck('sold_count', 'month_number')
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
            $barChart = array_map(function ($monthName, $monthNumber) use ($adsPostedByMonth, $soldAdsByMonthForBarChart) {
                return [
                    'month' => $monthName,
                    'ads' => isset($adsPostedByMonth[$monthNumber]) ? (int)$adsPostedByMonth[$monthNumber] : 0,
                    'ads_sold' => isset($soldAdsByMonthForBarChart[$monthNumber]) ? (int)$soldAdsByMonthForBarChart[$monthNumber] : 0
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
                        'label' => 'total ads',
                        'total' => $totalAds, // Changed to 'total'
                        'chart_data' => array_values($adsChartData)
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
     * @operationId Top sold ads
     */
    public function topSoldAds()
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

            // Get sold ads with media and user details using Eloquent
            $soldAds = Ads::with([
                'media', // Ad media
                'user.media' // User and their media
            ])
                ->where('status', 'sold')
                ->orderBy('updated_at', 'desc')
                ->limit(6)
                ->get();

            // Prepare the data
            $data = [
                'top_sold_ads' => $soldAds
            ];

            // Return the data
            return apiResponse(true, 'Top sold ads retrieved successfully.', $data, null, 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving top sold ads.',
                $e->getMessage(), 'error');
        }
    }

}
