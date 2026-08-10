<?php

namespace App\Http\Controllers;

use App\Models\NfseConfig;
use Illuminate\Http\Request;

class NfseConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:nfse-config.edit');
    }

    public function edit()
    {
        $config = NfseConfig::firstOrNew();

        return view('nfse.config', compact('config'));
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
                'webmania_access_token' => 'required|string',
            ],
            'nfeio' => [
                'nfeio_api_key' => 'required|string',
                'nfeio_company_id' => 'required|string',
            ],
            default => [],
        };

        $validated = $request->validate(array_merge($rules, $providerRules));

        NfseConfig::updateOrCreate(
            ['id' => NfseConfig::first()?->id],
            $validated + [
                'emit_auto' => $request->boolean('emit_auto'),
                'is_active' => true,
            ],
        );

        return redirect()
            ->route('nf.config')
            ->with('success', 'Configuração NFS-e salva!');
    }
}
