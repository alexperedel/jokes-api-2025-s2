<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Joke;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CategoryController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the Categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::all();
        return ApiResponse::success($categories, "Categories retrieved");
    }

    /**
     * Store a newly created Category in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize("create", Category::class);

        $validated = $request->validate([
            'title' => ['string', 'required', 'min:4'],
            'description' => ['string', 'nullable', 'min:6'],
        ]);

        $category = Category::create($validated);

        return ApiResponse::success($category, 'Category created', 201);
    }

    /**
     * Display the specified Category.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $this->authorize('view', Category::class);

        $category = Category::find($id);

        if ($category === null) {
            return ApiResponse::error($category, "Category not found", 404);
        }

        $randomJokes = $category->jokes()->inRandomOrder()->limit(5)->get();

        return ApiResponse::success([
            'category' => $category,
            'jokes' => $randomJokes
        ], "Category retrieved");
    }

    /**
     * Update the specified Category in storage.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update', Category::class);

        $validated = $request->validate([
            'title' => ['string', 'required', 'min:4'],
            'description' => ['string', 'nullable', 'min:6'],
        ]);

        $category = Category::find($id);

        if ($category === null) {
            return ApiResponse::error($category, "Category not found", 404);
        }

        $category->update($validated);
        return ApiResponse::success($category, 'Category updated', 200);

    }

    /**
     * Remove the specified Category from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $this->authorize('delete', Category::class);

        $category = Category::find($id);

        if ($category === null) {
            return ApiResponse::error($category, "Category not found", 404);
        }
        $category->delete();
        return ApiResponse::success(null, 'Category deleted', 200);
    }

    /**
     * Show all soft deleted Categories
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trash(Request $request)
    {
        $this->authorize('viewTrashed', Category::class);

        $categories = Category::onlyTrashed()->latest()->get();
        if (count($categories) === 0) {
            return ApiResponse::error(null, "No soft deleted categories found", 404);
        }
        return ApiResponse::success($categories, "Soft deleted categories retrieved");
    }

    /**
     * Recover all soft deleted categories from trash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoverAll()
    {
        $this->authorize('restoreAll', Category::class);

        $categories = Category::onlyTrashed()->latest()->get();
        if (count($categories) === 0) {
            return ApiResponse::error(null, "No soft deleted categories found", 404);
        }

        foreach ($categories as $category) {
            $category->restore();
        }   
        return ApiResponse::success(null, "Soft deleted categories recovered");
    }

    /**
     * Remove all soft deleted categories from trash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAll()
    {
        $this->authorize('emptyTrash', Category::class);

        $categories = Category::onlyTrashed()->latest()->get();
        if (count($categories) === 0) {
            return ApiResponse::error(null, "No soft deleted categories found", 404);
        }

        foreach ($categories as $category) {
            $category->forceDelete();
        } 

        return ApiResponse::success(null, "Soft deleted categories permanently removed");
    }

    /**
     * Recover specified soft deleted category from trash
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoverOne(string $id)
    {
        $this->authorize('restoreOne', Category::class);

        $category = Category::onlyTrashed()->find($id);

        if ($category === null) {
            return ApiResponse::error(null, "category not found", 404);
        }
        $category->restore();
        return ApiResponse::success($category, "Category recovered", 200);
    }

    /**
     * Remove specified soft deleted category from trash
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeOne(string $id)
    {
        $this->authorize('forceDelete', Category::class);

        $category = Category::onlyTrashed()->find($id);

        if ($category === null) {
            return ApiResponse::error(null, "Category not found", 404);
        }
        $category->forceDelete();
        return ApiResponse::success($category, "Category permanently removed", 200);
    }


    /**
     * Search categories by keyword
     *
     * @param string $keyword
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(string $keyword)
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::where('title', 'like', "%{$keyword}%")->get();

        if (count($categories) === 0) {
            return ApiResponse::error(null, "Category not found", 404);
        }
        return ApiResponse::success($categories, "Categories found", 200);
    }

}
