<?php

namespace App\Models\Tenant;

use App\Support\Cnpj;
use Illuminate\Support\Facades\DB;

/** Dados institucionais da Secretaria (linha única, id = 1): timbre e rodapé dos documentos. */
class InstitutionProfile extends TenantModel
{
    protected $table = 'institution_profile';

    public $incrementing = false;

    protected $fillable = [
        'id', 'department_name', 'cnpj', 'phone', 'email', 'website', 'postal_code', 'address_street',
        'address_number', 'address_complement', 'address_district', 'address_city', 'address_state',
        'secretary_name', 'secretary_title', 'updated_by',
    ];

    /** Campos que o rodapé dos documentos usa; os vazios aparecem como pendência para a gestão. */
    public const FOOTER_FIELDS = [
        'cnpj' => 'CNPJ', 'phone' => 'telefone', 'email' => 'e-mail', 'website' => 'site', 'address_street' => 'endereço',
    ];

    private const CACHE = 'astuti.institution_profile';

    protected static function booted(): void
    {
        static::saved(fn () => self::forget());
    }

    /** Uma consulta por requisição e por município (o timbre aparece no layout e no documento). */
    public static function current(): self
    {
        $key = (string) DB::connection('tenant')->getDatabaseName();
        $loaded = app()->bound(self::CACHE) ? app(self::CACHE) : [];
        if (! isset($loaded[$key])) {
            $loaded[$key] = static::query()->find(1)
                ?? new self(['id' => 1, 'department_name' => 'Secretaria Municipal de Educação']);
            app()->instance(self::CACHE, $loaded);
        }

        return $loaded[$key];
    }

    public static function forget(): void
    {
        app()->forgetInstance(self::CACHE);
    }

    public function addressLine(): string
    {
        $street = trim(implode(', ', array_filter([$this->address_street, $this->address_number])));
        $city = trim(implode('/', array_filter([$this->address_city, $this->address_state])));

        return implode(' - ', array_filter([
            trim(implode(', ', array_filter([$street ?: null, $this->address_complement, $this->address_district]))) ?: null,
            $city ?: null,
            $this->postal_code ? 'CEP '.$this->postal_code : null,
        ]));
    }

    public function formattedCnpj(): ?string
    {
        return $this->cnpj ? Cnpj::format($this->cnpj) : null;
    }

    public function formattedPhone(): ?string
    {
        $digits = (string) $this->phone;

        return match (strlen($digits)) {
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            default => null,
        };
    }

    /** Site sem "https://" e sem barra final, como se escreve em papel timbrado. */
    public function displayWebsite(): ?string
    {
        return $this->website ? rtrim((string) preg_replace('#^https?://#i', '', $this->website), '/') : null;
    }

    /** @return list<string> rótulos dos campos do rodapé que faltam */
    public function missing(): array
    {
        return array_values(array_filter(self::FOOTER_FIELDS, fn (string $label, string $field): bool => blank($this->{$field}), ARRAY_FILTER_USE_BOTH));
    }
}
