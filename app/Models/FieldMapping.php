<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'external_field',
        'crm_field',
        'default_value',
    ];

    /**
     * Get the user (company owner) that owns this mapping.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all mappings for a specific company and provider.
     */
    public static function forCompany(int $companyId, string $provider): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $companyId)
            ->where('provider', $provider)
            ->get();
    }

    /**
     * Apply stored mappings to a raw fields array from an external source.
     *
     * Given raw_fields like: ['full_name' => 'John Doe', 'email' => 'john@test.com', 'budget' => '500k']
     * And mappings like: [full_name => name, email => email, budget => value]
     * Returns: ['name' => 'John Doe', 'email' => 'john@test.com', 'value' => '500k']
     */
    public static function applyMappings(array $rawFields, int $companyId, string $provider): array
    {
        $mappings = static::forCompany($companyId, $provider);
        $mapped = [];

        foreach ($mappings as $mapping) {
            $externalField = $mapping->external_field;
            $crmField = $mapping->crm_field;

            if (isset($rawFields[$externalField]) && $rawFields[$externalField] !== '') {
                $mapped[$crmField] = $rawFields[$externalField];
            } elseif ($mapping->default_value) {
                $mapped[$crmField] = $mapping->default_value;
            }
        }

        return $mapped;
    }
}
