<?php

namespace App\Http\Controllers;

use App\Address\BrazilianStates;
use App\Audit\AuditTrail;
use App\Models\Tenant\InstitutionProfile;
use App\Models\Tenant\School;
use App\Support\Cnpj;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Dados públicos da Secretaria usados no timbre e no rodapé dos documentos emitidos. */
class InstitutionProfileController extends Controller
{
    public function edit(Request $request, string $municipio): View
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        return view('institution.edit', [
            'municipality' => $municipality,
            'perfil' => InstitutionProfile::current(),
            'states' => BrazilianStates::NAMES,
        ]);
    }

    public function update(Request $request, string $municipio): RedirectResponse
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);

        $website = trim((string) $request->input('website'));
        $request->merge([
            'cnpj' => Cnpj::digits($request->input('cnpj')) ?: null,
            'phone' => preg_replace('/\D/', '', (string) $request->input('phone')) ?: null,
            'email' => mb_strtolower(trim((string) $request->input('email'))) ?: null,
            'website' => $website === '' ? null : (preg_match('#^https?://#i', $website) === 1 ? $website : 'https://'.$website),
            'address_state' => mb_strtoupper(trim((string) $request->input('address_state'))) ?: null,
        ]);
        $data = $request->validate([
            'department_name' => ['required', 'string', 'min:5', 'max:120'],
            'cnpj' => ['nullable', 'digits:14', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Cnpj::isValid($value)) {
                    $fail('Confira o CNPJ: os dígitos verificadores não conferem.');
                }
            }],
            'phone' => ['nullable', 'regex:/^[1-9]{2}\d{8,9}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'website' => ['nullable', 'url:https,http', 'max:160'],
            'postal_code' => ['nullable', 'regex:/^\d{5}-?\d{3}$/'],
            'address_street' => ['nullable', 'string', 'max:180'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:120'],
            'address_district' => ['nullable', 'string', 'max:120'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_state' => ['nullable', BrazilianStates::VALIDATION_RULE],
            'secretary_name' => ['nullable', 'string', 'max:160'],
            'secretary_title' => ['nullable', 'string', 'max:120'],
        ], [
            'department_name.required' => 'Informe o nome do órgão, por exemplo Secretaria Municipal de Educação.',
            'cnpj.digits' => 'O CNPJ tem 14 números.',
            'phone.regex' => 'Informe o telefone com DDD, por exemplo (11) 4000-1234.',
            'email.email' => 'Confira o e-mail.',
            'website.url' => 'Confira o endereço do site, por exemplo www.prefeitura.sp.gov.br.',
            'postal_code.regex' => 'Informe o CEP com oito números.',
        ]);
        if (isset($data['postal_code'])) {
            $data['postal_code'] = substr(str_replace('-', '', $data['postal_code']), 0, 5).'-'.substr(str_replace('-', '', $data['postal_code']), 5);
        }
        $data = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $data);

        $changed = DB::connection('tenant')->transaction(function () use ($data, $request): array {
            $profile = InstitutionProfile::query()->lockForUpdate()->find(1) ?? new InstitutionProfile(['id' => 1]);
            $changed = array_keys(array_filter($data, fn ($value, string $field): bool => (string) $profile->{$field} !== (string) $value, ARRAY_FILTER_USE_BOTH));
            if ($changed !== [] || ! $profile->exists) {
                $profile->fill([...$data, 'updated_by' => $request->user()->id])->save();
            }
            if ($changed !== []) {
                // Dados públicos do órgão: a auditoria guarda quais campos mudaram.
                app(AuditTrail::class)->record('tenant', 'institution.profile.updated', InstitutionProfile::class, '1', null, ['changed_fields' => $changed]);
            }

            return $changed;
        });

        return redirect()->route('institution.edit', ['municipio' => $municipality->slug])
            ->with('status', $changed === [] ? 'Nada mudou nos dados da Prefeitura.' : 'Dados da Prefeitura salvos. Todos os documentos já usam o novo timbre.');
    }
}
