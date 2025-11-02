<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use App\Models\Joke;
use App\Responses\ApiResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class JokeController extends Controller
{
    // AuthorizesRequests trait provides authorize() method for policy checks
    // Could also use it in Controller.php (might be even better way)
    // Source: https://stackoverflow.com/questions/73981403/how-does-a-laravel-controller-call-authorize-function
    use AuthorizesRequests;

    /**
     * Get all jokes.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('viewAny', Joke::class);

        $user = auth()->user();
        
        // Filter by categories using whereHas for efficient querying
        // Source: https://laravel.com/docs/11.x/eloquent-relationships#querying-relationship-existence
        if ($user->hasRole('client')) {
            $jokes = Joke::whereHas('categories', function ($q) {
                $q->where('title', '!=', 'Unknown')
                ->whereNull('deleted_at');
            })->paginate(15);
        }
        else {
            // Laravel pagination
            // Source: https://laravel.com/docs/11.x/pagination#paginating-eloquent-results
            $jokes = Joke::paginate(15);
        }

        return ApiResponse::success($jokes, "Jokes retrieved");
    }

    /**
     * Create a new joke with categories.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize("create", Joke::class);

        // Laravel validation with array syntax for rules
        // 'categories.*' validates each array item individually
        // 'exists:categories,id' checks foreign key constraint
        // Source: https://laravel.com/docs/11.x/validation#available-validation-rules
        $rules = [
            'title' => ['string', 'required', 'min:3', 'max:128'],
            'content' => ['string', 'required', 'min:3', 'max:512'],
            'categories' => ['required', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
        $validated = $request->validate($rules);

        // Store
        $joke = Joke::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'user_id' => auth()->id(),
        ]);

        // Sync categories using many-to-many relationship
        // Source: https://laravel.com/docs/11.x/eloquent-relationships#syncing-associations
        $joke->categories()->sync($validated['categories']);

        return ApiResponse::success($joke, 'Joke created', 201);
    }
    /**
     * Display the specified resource.
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $this->authorize('view', Joke::class);

        $joke = Joke::find($id);

        $user = auth()->user();

        if ($joke === null) {
            return ApiResponse::error($joke, "Joke not found", 404);
        }

        if ($user->hasRole('client')) {
            $hasValidCategories = $joke->categories()
                ->where('categories.title', '!=', 'Unknown')
                ->whereNull('categories.deleted_at')
                ->exists();
            
            if (!$hasValidCategories) {
                return ApiResponse::error(null, 'Joke not found', 404);
            }
        }

        return ApiResponse::success($joke, "Joke retrieved");
    }

    /**
     * Update the specified Joke in storage.
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $joke = Joke::find($id);

        if ($joke === null) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        $this->authorize('update', $joke);

        // Validate
        $rules = [
            'title' => ['string', 'required', 'min:3', 'max:128'],
            'content' => ['string', 'required', 'min:3', 'max:512'],
            'categories' => ['required', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
        $validated = $request->validate($rules);

        // Store
        $joke->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        $joke->categories()->sync($validated['categories']);

        return ApiResponse::success($joke, 'Joke updated', 200);
    }

    /**
     * Remove the specified Joke from storage.
     * 
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $joke = Joke::find($id);

        if ($joke === null) {
            return ApiResponse::error($joke, "Joke not found", 404);
        }
        $this->authorize('delete', $joke);

        $joke->delete();
        return ApiResponse::success(null, 'Joke deleted', 200);
    }

    /**
     * Show all soft deleted Jokes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trash(Request $request)
    {
        $this->authorize('viewTrashed', Joke::class);

        // Retrieve only soft-deleted jokes for trash management
        // Source: https://laravel.com/docs/11.x/eloquent#soft-deleting
        $jokes = Joke::onlyTrashed()->latest()->get();
        if (count($jokes) === 0) {
            return ApiResponse::error(null, "No soft deleted jokes found", 404);
        }

        return ApiResponse::success($jokes, "Soft deleted jokes retrieved");
    }

    /**
     * Recover all soft deleted jokes from trash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoverAll()
    {
        $this->authorize('restoreAll', Joke::class);

        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('staff')) {
            $jokes = Joke::onlyTrashed()->get();
            if (count($jokes) === 0) {
                return ApiResponse::error(null, "No soft deleted jokes found", 404);
            }
            foreach ($jokes as $joke) {
                $joke->restore();
            }
        } else if ($user->hasRole('client')) {
            $jokes = Joke::onlyTrashed()->get();
            if (count($jokes) === 0) {
                return ApiResponse::error(null, "No soft deleted jokes found", 404);
            }
            foreach ($jokes as $joke) {
                if ($joke->user_id === auth()->id()) {
                    $joke->restore();
                }
            }
        }

        return ApiResponse::success(null, "Soft deleted jokes recovered");
    }

    /**
     * Remove all soft deleted jokes from trash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function emptyAll()
    {
        $this->authorize('emptyTrash', Joke::class);

        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('staff')) {
            $jokes = Joke::onlyTrashed()->get();
            if (count($jokes) === 0) {
                return ApiResponse::error(null, "No soft deleted jokes found", 404);
            }
            foreach ($jokes as $joke) {
                $joke->forceDelete();
            }
        } else if ($user->hasRole('client')) {
            $jokes = Joke::onlyTrashed()->get();
            if (count($jokes) === 0) {
                return ApiResponse::error(null, "No soft deleted jokes found", 404);
            }
            foreach ($jokes as $joke) {
                if ($joke->user_id === auth()->id()) {
                    $joke->forceDelete();
                }
            }
        }

        return ApiResponse::success(null, "Soft deleted jokes permanently removed");
    }

    /**
     * Recover specified soft deleted joke from trash
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoverOne(string $id)
    {
        $this->authorize('restoreOne', Joke::class);

        $joke = Joke::onlyTrashed()->find($id);

        if ($joke === null) {
            return ApiResponse::error(null, "Joke not found", 404);
        }
        $joke->restore();
        return ApiResponse::success($joke, "Joke recovered", 200);
    }

    /**
     * Remove specified soft deleted joke from trash
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function emptyOne(string $id)
    {
        $this->authorize('forceDelete', Joke::class);

        $joke = Joke::onlyTrashed()->find($id);

        if ($joke === null) {
            return ApiResponse::error(null, "Joke not found", 404);
        }
        $joke->forceDelete();
        return ApiResponse::success($joke, "Joke permanently removed", 200);
    }


    /**
     * Search jokes by keyword
     *
     * @param string $keyword
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(string $keyword)
    {
        $this->authorize('viewAny', Joke::class);

        $user = Auth::user();

        $query = Joke::where(function ($q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('content', 'like', "%{$keyword}%");
        });

        // Filter by categories using whereHas for relationship queries
        // Source: https://laravel.com/docs/11.x/eloquent-relationships#querying-relationship-existence
        if ($user->hasRole('client')) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.title', '!=', 'Unknown')
                  ->whereNull('categories.deleted_at');
            });
        }

        $jokes = $query->get();

        if (count($jokes) === 0) {
            return ApiResponse::error(null, "Joke not found", 404);
        }
        return ApiResponse::success($jokes, "Jokes found", 200);
    }

    /**
     * Return a random joke
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function random()
    {
        $joke = Joke::whereHas('categories', function ($q) {
            $q->where('categories.title', '!=', 'Unknown')
              ->whereNull('categories.deleted_at');
        })
        ->inRandomOrder()
        ->first();

        if ($joke === null) {
            return ApiResponse::error(null, "No jokes available", 404);
        }

        return ApiResponse::success($joke, "Random joke retrieved");
    }
}
