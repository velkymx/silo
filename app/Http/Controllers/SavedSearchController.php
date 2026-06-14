<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    private const KEYS = ['search', 'date_from', 'date_to', 'size_min', 'size_max', 'ftype', 'tag'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'params' => 'required|array',
        ]);

        // Keep only known filter keys with non-empty values.
        $params = collect($data['params'])
            ->only(self::KEYS)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        SavedSearch::create([
            'owner_id' => auth()->id(),
            'name' => $data['name'],
            'params' => $params,
        ]);

        return back()->with('success', 'Smart folder saved.');
    }

    public function destroy(SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->owner_id === auth()->id(), 403);
        $savedSearch->delete();

        return back()->with('success', 'Smart folder removed.');
    }
}
