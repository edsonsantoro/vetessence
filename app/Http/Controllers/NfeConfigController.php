<?php

namespace App\Http\Controllers;

use App\Models\NfeConfig;
use Illuminate\Http\Request;

class NfeConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:nfe-config.edit');
    }

    public function edit()
    {
        $config = NfeConfig::firstOrNew();

        return view('nfe.config', compact('config'));
    }

    public function update(Request $request)
    {
        $rules = [
            'provider' => 'required|in:webmania,nfeio',
            'ambiente' => 'required|in:homologacao,producao',
            'emit_auto' => 'nullable|boolean',
        ];

        $provider = $request->input('provider', 'webmania');

        $providerRules = match ($provider) {
            'webmania' => [
                'webmania_consumer_key' => 'required|string',
                'webmania_consumer_secret' => 'required|string',
                'webmania_access_token' => 'required|string',
                'webmania_access_token_secret' => 'required|string',
            ],
            'nfeio' => [
                'nfeio_api_key' => 'required|string',
                'nfeio_company_id' => 'required|string',
            ],
            default => [],
        };

        $validated = $request->validate(array_merge($rules, $providerRules));

        NfeConfig::updateOrCreate(
            ['id' => NfeConfig::first()?->id],
            $validated + [
                'emit_auto' => $request->boolean('emit_auto'),
                'is_active' => true,
            ],
        );

        return redirect()
            ->route('nf.config')
            ->with('success', 'Configuração NF-e salva!');
    }
}
