<?php
// app/Services/FilterService.php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FilterService
{
    public function applyFilters(Request $request, Builder $query)
    {
        // Common filters applicable to multiple models
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Date range filter (default to created_at)
        $dateField = $request->input('date_field', 'created_at');
        if ($startDate = $request->input('start_date')) {
            $query->where($dateField, '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->where($dateField, '<=', $endDate);
        }

        // User ID filter (for reports, ads, etc.)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Ad ID filter (specific to reports)
        if ($request->has('ad_id')) {
            $query->where('ad_id', $request->input('ad_id'));
        }

        // Reason filter (specific to reports)
        if ($reason = $request->input('reason')) {
            $query->where('reason', 'like', "%$reason%");
        }

        // Blog post-specific filters
        if ($authorId = $request->input('author_id')) {
            $query->where('author_id', $authorId);
        }
        if ($title = $request->input('title')) {
            $query->where('title', 'like', "%$title%");
        }

        // Category-specific filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($parentId = $request->input('parent_category_id')) {
            $query->where('parent_category_id', $parentId);
        }
        if ($name = $request->input('name')) {
            $query->where('name', 'like', "%$name%");
        }

        // Role filter for users
        if ($role = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', 'like', "%$role%");
            });
        }

        // General search filter (title, content, description, etc.)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                if (Schema::hasColumn($q->getModel()->getTable(), 'username')) {
                    $q->orWhere('username', 'like', "%$search%");
                } elseif (Schema::hasColumn($q->getModel()->getTable(), 'name')) {
                    $q->orWhere('name', 'like', "%$search%");
                }
                if (Schema::hasColumn($q->getModel()->getTable(), 'email')) {
                    $q->orWhere('email', 'like', "%$search%");
                }
                if (Schema::hasColumn($q->getModel()->getTable(), 'title')) {
                    $q->orWhere('title', 'like', "%$search%");
                }
                if (Schema::hasColumn($q->getModel()->getTable(), 'description')) {
                    $q->orWhere('description', 'like', "%$search%");
                }
                if (Schema::hasColumn($q->getModel()->getTable(), 'content')) {
                    $q->orWhere('content', 'like', "%$search%");
                }
                if (Schema::hasColumn($q->getModel()->getTable(), 'reason')) { // Added for reports
                    $q->orWhere('reason', 'like', "%$search%");
                }
            });
        }

        // Sorting (optional, only if not hardcoded in controller)
        if ($sortBy = $request->input('sort_by')) {
            $direction = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $direction);
        }

        return $query;
    }
}
