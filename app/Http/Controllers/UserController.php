<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Inertia\Inertia;

class UserController extends Controller
{
    // Display the profile edit form
    public function edit()
    {
        return Inertia::render('Profile/Edit', [
            'user' => Auth::user()->only('id', 'name', 'email', 'group_id'),
            'groups' => \App\Models\Group::all(['id', 'name']),
        ]);
    }

    // Handle profile update
    public function update(Request $request)
    {
        $user = Auth::user();
    
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed', // Password confirmation required if provided
            'group_id' => 'required|exists:groups,id', // Validate the group ID
        ]);
    
        // Update user details
        $user->name = $request->name;
        $user->email = $request->email;
        $user->group_id = $request->group_id; // Assign the selected group
    
        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
    
        $user->save();
    
        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}
