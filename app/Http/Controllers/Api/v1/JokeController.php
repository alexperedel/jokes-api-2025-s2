<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Joke;
use App\Responses\ApiResponse;

class JokeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the jokes.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('viewAny', Joke::class);

        $jokes = Joke::all();
        return ApiResponse::success($jokes, "Jokes retrieved");
    }

    /**
     * Show the form for creating a new resource.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
            {
        $this->authorize("create", Joke::class);

        $validated = $request->validate([
            'title' => ['string', 'required', 'min:3'],
            'content' => ['string', 'nullable', 'min:6'],
        ]);

        $joke = Joke::create($validated);

        return ApiResponse::success($joke, 'Joke created', 201);
    }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
