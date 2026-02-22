<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PaymentSetting extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * Keys whose values must be encrypted at rest in the database.
     * These are secret/private keys for payment gateways.
     */
    protected array $sensitiveKeys = [
        'stripe_secret',
        'paypal_secret_key',
        'razorpay_secret',
        'paystack_secret_key',
        'flutterwave_secret_key',
        'paytabs_server_key',
        'skrill_secret_word',
        'coingate_api_token',
        'payfast_passphrase',
        'payfast_merchant_key',
        'tap_secret_key',
        'xendit_api_key',
        'paytr_merchant_key',
        'paytr_merchant_salt',
        'mollie_api_key',
        'toyyibpay_secret_key',
        'paymentwall_private_key',
        'sspay_secret_key',
        'benefit_secret_key',
        'iyzipay_secret_key',
        'aamarpay_signature',
        'midtrans_secret_key',
        'yookassa_secret_key',
        'nepalste_secret_key',
        'cinetpay_secret_key',
        'payhere_merchant_secret',
        'payhere_app_secret',
        'fedapay_secret_key',
        'authorizenet_transaction_key',
        'khalti_secret_key',
        'easebuzz_salt_key',
        'ozow_private_key',
        'ozow_api_key',
        'cashfree_secret_key',
        'mercadopago_access_token',
        'hitpay_api_key',
        'hitpay_salt',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setValueAttribute($value)
    {
        // Encrypt sensitive keys before saving to the database
        if (
            isset($this->attributes['key'])
            && in_array($this->attributes['key'], $this->sensitiveKeys)
            && !empty($value)
            && !is_bool($value)
        ) {
            $this->attributes['value'] = Crypt::encryptString((string) $value);
            return;
        }

        $this->attributes['value'] = is_bool($value) ? ($value ? '1' : '0') : $value;
    }

    public function getValueAttribute($value)
    {
        $booleanKeys = [
            'is_manually_enabled',
            'is_bank_enabled',
            'is_stripe_enabled',
            'is_paypal_enabled',
            'is_razorpay_enabled',
            'is_mercadopago_enabled',
            'is_paystack_enabled',
            'is_flutterwave_enabled',
            'is_paytabs_enabled',
            'is_skrill_enabled',
            'is_coingate_enabled',
            'is_payfast_enabled',
            'is_tap_enabled',
            'is_xendit_enabled',
            'is_paytr_enabled',
            'is_mollie_enabled',
            'is_toyyibpay_enabled',
            'is_paymentwall_enabled',
            'is_sspay_enabled',
            'is_benefit_enabled',
            'is_iyzipay_enabled',
            'is_aamarpay_enabled',
            'is_midtrans_enabled',
            'is_yookassa_enabled',
            'is_nepalste_enabled',
            'is_paiement_enabled',
            'is_cinetpay_enabled',
            'is_payhere_enabled',
            'is_fedapay_enabled',
            'is_authorizenet_enabled',
            'is_khalti_enabled',
            'is_easebuzz_enabled',
            'is_ozow_enabled',
            'is_cashfree_enabled',
            'is_hitpay_enabled'
        ];

        if (isset($this->key) && in_array($this->key, $booleanKeys)) {
            return $value === '1' || $value === 1 || $value === true;
        }

        // Decrypt sensitive keys when reading from the database
        if (
            isset($this->key)
            && in_array($this->key, $this->sensitiveKeys)
            && !empty($value)
        ) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException $e) {
                // Backwards compatibility: value is still plain text from before encryption was added.
                // It will auto-encrypt when the admin saves settings next time.
                return $value;
            }
        }

        return $value;
    }

    public static function updateOrCreateSetting($userId, $key, $value)
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getUserSettings($userId)
    {
        if (!$userId) {
            return [];
        }

        // We must map it through Eloquent models so the `getValueAttribute` decryption runs
        return self::where('user_id', $userId)->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        })->toArray();
    }
}