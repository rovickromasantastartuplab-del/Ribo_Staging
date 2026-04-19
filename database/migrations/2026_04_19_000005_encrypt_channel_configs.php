<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $accounts = DB::table('channel_accounts')->get();

        foreach ($accounts as $account) {
            $config = $account->configuration;

            // Check if it's already encrypted
            try {
                Crypt::decryptString($config);
                // If it doesn't throw, it's already encrypted
                continue;
            } catch (\Exception $e) {
                // Not encrypted or encrypted with a different key
                // Let's check if it's raw JSON
                $decoded = json_decode($config, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // It is raw JSON, let's encrypt it for the new APP_KEY
                    DB::table('channel_accounts')
                        ->where('id', $account->id)
                        ->update([
                            'configuration' => Crypt::encryptString($config)
                        ]);
                    
                    Log::info("Encrypted configuration for ChannelAccount ID: {$account->id}");
                }
            }
        }
    }

    /**
     * Reverse the migrations (Not practical here as we can't reliably "un-encrypt" without model context).
     */
    public function down(): void
    {
        // No action needed
    }
};
