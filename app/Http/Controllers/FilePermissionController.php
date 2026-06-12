<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FilePermissionController extends Controller
{
    private const ABILITIES = [
        Permission::ABILITY_READ,
        Permission::ABILITY_WRITE,
        Permission::ABILITY_DELETE,
        Permission::ABILITY_SHARE,
    ];

    // List the access grants on a file (for the Share dialog).
    public function index(File $file)
    {
        $this->authorize('share', $file);

        return response()->json([
            'permissions' => $this->grants($file),
            'inherited' => $this->inheritedGrants($file),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // Grant one or more abilities to a user (by email) or a group.
    public function store(Request $request, File $file)
    {
        $this->authorize('share', $file);

        $data = $request->validate([
            'subject_type' => 'required|in:user,group',
            'email' => 'required_if:subject_type,user|nullable|email',
            'group_id' => 'required_if:subject_type,group|nullable|exists:groups,id',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'in:'.implode(',', self::ABILITIES),
        ]);

        if ($data['subject_type'] === Permission::SUBJECT_USER) {
            $subjectId = User::where('email', $data['email'])->value('id');
            if (! $subjectId) {
                throw ValidationException::withMessages(['email' => 'No user with that email.']);
            }
            if ($subjectId === $file->owner_id) {
                throw ValidationException::withMessages(['email' => 'The owner already has full access.']);
            }
        } else {
            $subjectId = (int) $data['group_id'];
        }

        foreach ($data['abilities'] as $ability) {
            Permission::firstOrCreate([
                'file_id' => $file->id,
                'subject_type' => $data['subject_type'],
                'subject_id' => $subjectId,
                'ability' => $ability,
            ]);
        }

        Audit::log('permission.grant', $file, [
            'subject_type' => $data['subject_type'],
            'subject_id' => $subjectId,
            'abilities' => $data['abilities'],
        ]);

        return response()->json(['permissions' => $this->grants($file)]);
    }

    // Revoke a single grant.
    public function destroy(File $file, Permission $permission)
    {
        $this->authorize('share', $file);
        abort_unless($permission->file_id === $file->id, 404);

        Audit::log('permission.revoke', $file, [
            'subject_type' => $permission->subject_type,
            'subject_id' => $permission->subject_id,
            'ability' => $permission->ability,
        ]);
        $permission->delete();

        return response()->json(['permissions' => $this->grants($file)]);
    }

    // Read-only grants inherited from ancestor folders (effective via the policy).
    private function inheritedGrants(File $file): array
    {
        $out = [];
        for ($node = $file->parent; $node; $node = $node->parent) {
            foreach ($this->grants($node) as $grant) {
                $out[] = $grant + ['source' => $node->name];
            }
        }

        return $out;
    }

    // Shape the file's grants with a human-readable subject label.
    private function grants(File $file): array
    {
        $permissions = $file->permissions()->get();

        $userNames = User::whereIn('id', $permissions->where('subject_type', Permission::SUBJECT_USER)->pluck('subject_id'))
            ->pluck('email', 'id');
        $groupNames = Group::whereIn('id', $permissions->where('subject_type', Permission::SUBJECT_GROUP)->pluck('subject_id'))
            ->pluck('name', 'id');

        return $permissions->map(fn (Permission $p) => [
            'id' => $p->id,
            'subject_type' => $p->subject_type,
            'subject_label' => $p->subject_type === Permission::SUBJECT_USER
                ? ($userNames[$p->subject_id] ?? "user #{$p->subject_id}")
                : ($groupNames[$p->subject_id] ?? "group #{$p->subject_id}"),
            'ability' => $p->ability,
        ])->values()->all();
    }
}
