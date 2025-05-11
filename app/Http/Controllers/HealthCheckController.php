<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\AES;

class HealthCheckController extends Controller
{
    public function healthCheck(Request $request)
    {
        try {
            // Validate required inputs
            $requiredFields = ['encrypted_flow_data', 'encrypted_aes_key', 'initial_vector'];
            foreach ($requiredFields as $field) {
                if (!$request->has($field)) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "$field is required",
                        'details' => "The $field parameter is missing from the request"
                    ], 400);
                }
            }

            $encryptedFlowData = base64_decode($request->input('encrypted_flow_data'));
            $encryptedAesKey = base64_decode($request->input('encrypted_aes_key'));
            $initialVector = base64_decode($request->input('initial_vector'));

            // Path to private key
            $privateKeyPath = storage_path('keys/private.pem');

            // Load the private key
            if (!file_exists($privateKeyPath)) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Private key file not found',
                    'details' => 'The required private key file is missing from the server'
                ], 500);
            }

            $privatePem = file_get_contents($privateKeyPath);

            // Decrypt the AES key using RSA
            $rsa = RSA::load($privatePem)
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            $decryptedAesKey = $rsa->decrypt($encryptedAesKey);
            if (!$decryptedAesKey) {
                throw new \Exception('Decryption of AES key failed.');
            }

            // Decrypt the Flow data using AES-GCM
            $aes = new AES('gcm');
            $aes->setKey($decryptedAesKey);
            $aes->setNonce($initialVector);

            $tagLength = 16;
            $encryptedFlowDataBody = substr($encryptedFlowData, 0, -$tagLength);
            $encryptedFlowDataTag = substr($encryptedFlowData, -$tagLength);
            $aes->setTag($encryptedFlowDataTag);

            $decryptedData = $aes->decrypt($encryptedFlowDataBody);
            if (!$decryptedData) {
                throw new \Exception('Decryption of flow data failed.');
            }

            // Prepare response data
            $screen = [
                "screen" => "HEALTH_CHECK",
                "data" => json_decode($decryptedData, true)
            ];

            // Encrypt response
            $flippedIv = ~$initialVector;
            $cipher = openssl_encrypt(
                json_encode($screen),
                'aes-128-gcm',
                $decryptedAesKey,
                OPENSSL_RAW_DATA,
                $flippedIv,
                $tag
            );

            return response($cipher . $tag);

        } catch (\Exception $e) {
            Log::error('Health check error: ' . $e->getMessage());
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => 'An unexpected error occurred during the health check process'
            ], 500);
        }
    }
}
