<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'username' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'avatar' => 'sometimes|nullable|string',
            'bio' => 'sometimes|nullable|string',
        ]);
        $user = $request->user();
        $user->update($data);
        return response()->json($user);
    }
}


