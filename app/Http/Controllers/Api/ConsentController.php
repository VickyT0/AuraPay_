<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Services\ConsentService;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    public function index(Request $request)
    {
        return response()->json($request->user()->consents()->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:account_info,payment_initiation'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $consent = $this->consents->grant(
    $request->user(),
    $validated['scope'],
    $validated['ttl_minutes'] ?? 43200,
);

        return response()->json($consent, 201);
    }

    public function destroy(Consent $consent)
    {
        abort_unless($consent->user_id === auth()->id(), 403);

        $this->consents->revoke($consent);

        return response()->json(['message' => 'Consent revoked.']);
    }
}
