<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Events\NewNotification;

class WhatsappWebhookController extends Controller
{
    public function callback(Request $request)
    {
        $entry = $request->input('entry.0.changes.0.value.messages.0');

        if (!$entry) {
            return response()->json(['message' => 'No message found'], 400);
        }

        $type = $entry['type'] ?? null;
        $from = $entry['from'] ?? null;

        if (!$type || !$from) {
            return response()->json(['message' => 'Invalid message structure'], 400);
        }

        $accessToken = env('WHATSAPP_TOKEN');
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');

        event(new NewNotification(
            "New WhatsApp Message",
            "A new message has been received on WhatsApp.",
            "info",
            "https://crm.taskercompany.com/crm/complaints/"
        ));

        if ($type === 'text') {
            $text = strtolower(trim($entry['text']['body'] ?? ''));

            if (in_array($text, ['start', 'hello', 'hi', 'assalam o alaikum'])) {
                $this->sendMainMenu($from, $phoneNumberId, $accessToken);
            } elseif ($text === 'check_status') {
                $this->askComplaintNumber($from, $phoneNumberId, $accessToken);
            } elseif ($text === 'create_complaint') {
                $this->askForName($from, $phoneNumberId, $accessToken);
            } elseif ($text === 'contact_team') {
                $this->sendContactOptions($from, $phoneNumberId, $accessToken);
            } else {
                $this->sendTextMessage($from, $phoneNumberId, $accessToken, "مہربانی فرما کر 'Start' ٹائپ کریں تاکہ ہم آپ کی مدد شروع کر سکیں۔");
            }

            return response()->json(['message' => 'Handled text message']);
        }

        if (in_array($type, ['audio', 'image', 'video'])) {
            $this->handleMedia($entry, $accessToken, $type);

            return response()->json(['message' => ucfirst($type) . ' saved successfully']);
        }

        return response()->json(['message' => 'Unsupported message type'], 400);
    }

    private function sendMainMenu($to, $phoneNumberId, $accessToken)
    {
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => [
                    "text" => "خوش آمدید! آپ کس بارے میں معلومات حاصل کرنا چاہتے ہیں؟"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "check_status",
                                "title" => "شکایت کی حیثیت معلوم کریں"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "create_complaint",
                                "title" => "نئی شکایت درج کریں"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "contact_team",
                                "title" => "ہماری ٹیم سے رابطہ کریں"
                            ]
                        ],
                    ]
                ]
            ]
        ];

        Http::withToken($accessToken)->post($url, $payload);
    }

    private function askComplaintNumber($to, $phoneNumberId, $accessToken)
    {
        $this->sendTextMessage($to, $phoneNumberId, $accessToken, "براہ کرم اپنی شکایت نمبر فراہم کریں تاکہ ہم آپ کو اس کا اسٹیٹس بتا سکیں۔");
    }

    private function askForName($to, $phoneNumberId, $accessToken)
    {
        $this->sendTextMessage($to, $phoneNumberId, $accessToken, "براہ کرم اپنا مکمل نام بتائیں تاکہ ہم آپ کی شکایت درج کر سکیں۔");
    }

    private function sendContactOptions($to, $phoneNumberId, $accessToken)
    {
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => [
                    "text" => "ہماری ٹیم سے رابطہ کرنے کے لیے ایک آپشن منتخب کریں۔"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "call_now",
                                "title" => "کال کریں"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "email_support",
                                "title" => "ای میل سپورٹ"
                            ]
                        ],
                    ]
                ]
            ]
        ];

        Http::withToken($accessToken)->post($url, $payload);
    }

    private function sendTextMessage($to, $phoneNumberId, $accessToken, $message)
    {
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "text" => [
                "body" => $message
            ]
        ];

        Http::withToken($accessToken)->post($url, $payload);
    }

    private function handleMedia($entry, $accessToken, $type)
    {
        $mediaId = $entry[$type]['id'] ?? null;

        if (!$mediaId) {
            throw new \Exception('No media ID found.');
        }

        $mediaResponse = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}");

        if (!$mediaResponse->successful()) {
            throw new \Exception('Failed to fetch media URL.');
        }

        $mediaUrl = $mediaResponse->json('url');

        $fileResponse = Http::withToken($accessToken)
            ->get($mediaUrl);

        if (!$fileResponse->successful()) {
            throw new \Exception('Failed to download media.');
        }

        $extension = $this->getExtension($type);
        $filePath = "whatsapp_media/{$type}s/{$mediaId}.{$extension}";

        Storage::disk('public')->put($filePath, $fileResponse->body());
    }

    private function getExtension($type)
    {
        return match ($type) {
            'audio' => 'ogg',
            'image' => 'jpg',
            'video' => 'mp4',
            default => 'bin',
        };
    }
}
