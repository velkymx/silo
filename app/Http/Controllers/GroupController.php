<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Groups/Index', [
            'groups' => Group::withCount('users')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name, 'users_count' => $g->users_count]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:groups,name']);
        Group::create(['name' => $request->string('name')]);

        return back()->with('success', 'Group created.');
    }

    public function update(Request $request, Group $group)
    {
        $request->validate(['name' => 'required|string|max:255|unique:groups,name,'.$group->id]);
        $group->update(['name' => $request->string('name')]);

        return back()->with('success', 'Group updated.');
    }

    public function destroy(Group $group)
    {
        $group->delete(); // users.group_id is set null via FK

        return back()->with('success', 'Group deleted.');
    }
}
