<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateKeys extends Command
{
    protected $signature = 'generate:keys';
    protected $description = 'Generate RSA Public and Private Keys';

    public function handle()
    {
        $privateKeyPath = storage_path('keys/private_key.pem');
        $publicKeyPath = storage_path('keys/public_key.pem');

        if (!file_exists(storage_path('keys'))) {
            mkdir(storage_path('keys'), 0700, true);
        }

        $res = openssl_pkey_new([
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($res, $privateKey);

        $publicKey = openssl_pkey_get_details($res)['key'];

        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);

        $this->info("Keys generated:");
        $this->info("Private Key: {$privateKeyPath}");
        $this->info("Public Key: {$publicKeyPath}");
    }
}
