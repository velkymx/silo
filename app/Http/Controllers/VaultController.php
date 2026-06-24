<?php

namespace App\Http\Controllers;

use App\Models\VaultItem;
use App\Services\Audit;
use App\Services\VaultImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Team secrets vault. Secrets are encrypted at rest (App\Casts\VaultEncrypted)
 * and NEVER serialized in list/Inertia payloads — they are only returned by the
 * audited, rate-limited, password-gated reveal endpoint.
 */
class VaultController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $items = VaultItem::visibleTo($user)
            ->orderBy('category')->orderBy('name')
            ->get()
            ->map(fn (VaultItem $i) => $this->shape($i, $user->id));

        return Inertia::render('Vault/Index', [
            'items' => $items->values(),
            'groups' => \App\Models\Group::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VaultItem::class);
        $data = $this->validateData($request, true);

        VaultItem::create($data + [
            'owner_id' => auth()->id(),
            'last_rotated_at' => now(),
        ]);

        return back()->with('success', 'Secret saved.');
    }

    public function update(Request $request, VaultItem $vaultItem)
    {
        $this->authorize('update', $vaultItem);
        $data = $this->validateData($request, false);

        // Only stamp a rotation when the secret actually changes.
        if (! empty($data['secret'])) {
            $data['last_rotated_at'] = now();
        } else {
            unset($data['secret']);
        }

        $vaultItem->update($data);

        return back()->with('success', 'Secret updated.');
    }

    public function destroy(VaultItem $vaultItem)
    {
        $this->authorize('delete', $vaultItem);
        $vaultItem->delete();

        return back()->with('success', 'Secret removed.');
    }

    /**
     * Decrypt and return a secret. Gated on a fresh password re-entry, audited,
     * rate-limited (route), and sent with no-store so it is never cached.
     */
    public function reveal(Request $request, VaultItem $vaultItem)
    {
        $this->authorize('view', $vaultItem);
        $request->validate(['password' => ['required', 'current_password']]);

        Audit::log('vault.reveal', null, ['id' => $vaultItem->id, 'name' => $vaultItem->name]);

        return response()
            ->json(['secret' => $vaultItem->secret, 'notes' => $vaultItem->notes])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Import a Chrome password CSV. Parsed in memory and encrypted on write —
     * the uploaded plaintext file is never stored beyond the request.
     */
    public function import(Request $request, VaultImporter $importer)
    {
        $this->authorize('create', VaultItem::class);
        $request->validate(['file' => 'required|file|max:10240']);

        $userId = auth()->id();
        $count = 0;

        foreach ($importer->parse($request->file('file')->get()) as $row) {
            VaultItem::create([
                'owner_id' => $userId,
                'name' => Str::limit($row['name'], 120, ''),
                'username' => $row['username'] ? Str::limit($row['username'], 255, '') : null,
                'url' => $row['url'],
                'secret' => $row['secret'],
                'notes' => $row['notes'],
                'last_rotated_at' => now(),
            ]);
            $count++;
        }

        // Never include secret material in the audit metadata.
        Audit::log('vault.import', null, ['count' => $count]);

        return back()->with('success', "Imported {$count} secret(s).");
    }

    /** Server-side strong-password generator. */
    public function generate(Request $request)
    {
        $length = min(max((int) $request->integer('length', 20), 8), 128);
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return response()->json(['password' => $password])->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, bool $secretRequired): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:2048',
            'category' => 'nullable|string|max:60',
            'secret' => ($secretRequired ? 'required' : 'nullable').'|string|max:8192',
            'notes' => 'nullable|string|max:8192',
            'group_id' => 'nullable|exists:groups,id',
        ]);
    }

    /**
     * Shape an item for the list — deliberately WITHOUT the secret/notes.
     *
     * @return array<string, mixed>
     */
    private function shape(VaultItem $item, int $userId): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'username' => $item->username,
            'url' => $item->url,
            'category' => $item->category,
            'shared' => $item->group_id !== null,
            'group_id' => $item->group_id,
            'last_rotated_at' => $item->last_rotated_at?->format('Y-m-d'),
            'can_edit' => $item->owner_id === $userId || auth()->user()->is_admin,
        ];
    }
}
