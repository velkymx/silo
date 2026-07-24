<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Surfaces reusable automation rule templates. The list page shows
 * every seeded template with its trigger event and a short description;
 * the apply endpoint materializes a template as a personal rule for
 * the current user and saves it.
 */
class TemplateController extends Controller
{
    public function index()
    {
        $templates = WorkflowTemplate::orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Automation/Templates/Index', [
            'templates' => $templates->map(fn (WorkflowTemplate $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'description' => $t->description,
                'icon' => $t->icon,
                'event_namespace' => $t->event_namespace,
                'actions_count' => is_array($t->actions_json) ? count($t->actions_json) : 0,
            ])->values(),
        ]);
    }

    public function apply(Request $request, WorkflowTemplate $template)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
        ]);

        $name = $data['name'] ?? $template->name;
        $rule = $template->toRule(auth()->id(), $name);
        $rule->save();

        return redirect()->route('rss.rules.index')->with('success', "Rule “{$name}” created from template.");
    }
}
