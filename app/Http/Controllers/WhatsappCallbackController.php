<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Complaint;
use App\Models\WhatsappSession;
use App\Events\NewNotification;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\Branch;
use App\Models\Branches;

class WhatsappCallbackController extends Controller
{
    private $whatsappClient;

    public function __construct()
    {
        $this->whatsappClient = new Client();
    }

    public function handleCallback(Request $request)
    {
        $hubVerifyToken = env('WHATSAPP_VERIFY_TOKEN', 'your_default_token');

        if ($request->isMethod('get') && $request->has('hub_challenge')) {
            if ($request->query('hub_verify_token') === $hubVerifyToken) {
                return response($request->query('hub_challenge'), 200);
            }
            return response('Invalid verification token', 403);
        }

        if ($request->isMethod('post')) {
            Log::info('Received WhatsApp Webhook Event:', [
                'payload' => $request->all()
            ]);

            $payload = $request->json()->all();
            if (!isset($payload['object']) || $payload['object'] !== 'whatsapp_business_account') {
                return response()->json(['error' => 'Invalid webhook payload'], 400);
            }

            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    if (isset($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $message) {
                            $this->processIncomingMessage($message);
                        }
                    }
                }
            }

            return response()->json(['message' => 'Event received successfully'], 200);
        }

        return response('Forbidden', 403);
    }

    private function processIncomingMessage($message)
    {
        $whatsappNumber = $message['from'];
        $messageType = $message['type'] ?? '';

        // Handle interactive messages (button responses)
        if ($messageType === 'interactive' && isset($message['interactive']['button_reply'])) {
            $buttonResponse = $message['interactive']['button_reply'];
            $messageText = strtolower($buttonResponse['id']);
        }
        // Handle text messages
        else if ($messageType === 'text' && isset($message['text']['body'])) {
            $messageText = strtolower(trim($message['text']['body']));
        }
        else {
            Log::error('Unsupported message format', ['message' => $message]);
            return;
        }

        // Log the incoming message for debugging
        Log::info('Processing message:', [
            'from' => $whatsappNumber,
            'text' => $messageText,
            'type' => $messageType
        ]);

        // Retrieve session data from cache
        $session = Cache::get("whatsapp_session_{$whatsappNumber}", []);

        // Log current session state
        Log::info('Current session:', ['session' => $session]);

        if ($messageText === "hi" || $messageText === "hello") {
            $this->sendMessage($whatsappNumber, "Welcome to Tasker Company! We are a leading HVACR service provider in Lahore and Rawalpindi, offering expert solutions for air conditioning, heating, ventilation and refrigeration needs. Would you like support, register a complaint, or check complaint status?", ['Support', 'Register Complaint', 'Check Status']);
        } elseif (in_array($messageText, ["support", "register_complaint", "check_status"])) {
            if ($messageText === "support") {
                $this->sendMessage($whatsappNumber, "Our team will contact you soon. For urgent matters, you can call us on +92 302 5117000");
            } elseif ($messageText === "check_status") {
                Cache::put("whatsapp_session_{$whatsappNumber}", ['step' => 'check_complaint'], now()->addMinutes(30));
                $this->sendMessage($whatsappNumber, "Please enter your complaint number:");
            } else {
                Cache::put("whatsapp_session_{$whatsappNumber}", ['step' => 1], now()->addMinutes(30));
                $this->sendMessage($whatsappNumber, "Please enter your name:");
            }
        } elseif (!empty($session)) {
            if ($session['step'] === 'check_complaint') {
                $this->handleComplaintStatus($whatsappNumber, $messageText);
            } else {
                $this->handleStepper($whatsappNumber, $messageText, $session);
            }
        } else {
            Log::info('No matching condition found', [
                'message' => $messageText,
                'session' => $session
            ]);
            $this->sendMessage($whatsappNumber, "Sorry, I didn't understand that. Type 'hi' or 'hello' to start.");
        }
    }

    private function handleComplaintStatus($whatsappNumber, $complaintNumber)
    {
        $complaint = Complaint::where('complain_num', $complaintNumber)->first();

        if (!$complaint) {
            $this->sendMessage($whatsappNumber, "Sorry, we couldn't find a complaint with that number. Please check the number and try again.");
            Cache::forget("whatsapp_session_{$whatsappNumber}");
            return;
        }

        $statusMessage = "Complaint Details:\n" .
            "Number: {$complaint->complain_num}\n" .
            "Status: {$complaint->status}\n" .
            "Type: {$complaint->complaint_type}\n" .
            "Description: {$complaint->description}";

        $this->sendMessage($whatsappNumber, $statusMessage);
        Cache::forget("whatsapp_session_{$whatsappNumber}");
    }

    private function handleStepper($whatsappNumber, $messageText, $session)
    {
        Log::info('Handling stepper:', [
            'step' => $session['step'],
            'message' => $messageText
        ]);

        switch ($session['step']) {
            case 1:
                $session['applicant_name'] = $messageText;
                $session['step'] = 2;
                $this->sendMessage($whatsappNumber, "Please enter your phone number:");
                break;
            case 2:
                $session['applicant_phone'] = $messageText;
                $session['step'] = 3;
                $this->sendMessage($whatsappNumber, "Please enter your WhatsApp number:");
                break;
            case 3:
                $session['applicant_whatsapp'] = $messageText;
                $session['step'] = 4;
                $this->sendMessage($whatsappNumber, "Please enter your address:");
                break;
            case 4:
                $session['applicant_address'] = $messageText;
                $session['step'] = 5;

                // Fetch branches and format them for display
                $branches = Branches::all();
                $branchList = "Please select a branch number:\n";
                foreach($branches as $index => $branch) {
                    $branchList .= ($index + 1) . ". " . $branch->name . "\n";
                }
                $session['branches'] = $branches->pluck('id', 'name')->toArray();

                $this->sendMessage($whatsappNumber, $branchList);
                break;
            case 5:
                // Convert branch selection to branch_id
                $branchIndex = intval($messageText) - 1;
                $branches = collect($session['branches']);
                if ($branchIndex >= 0 && $branchIndex < $branches->count()) {
                    $session['branch_id'] = $branches->values()->get($branchIndex);
                    $session['step'] = 6;
                    $this->sendMessage($whatsappNumber, "Please enter the complaint type:");
                } else {
                    $this->sendMessage($whatsappNumber, "Invalid branch selection. Please try again.");
                }
                break;
            case 6:
                $session['complaint_type'] = $messageText;
                $session['step'] = 7;
                $this->sendMessage($whatsappNumber, "Please enter the product brand:");
                break;
            case 7:
                $session['brand_id'] = $messageText;
                $session['step'] = 8;
                $this->sendMessage($whatsappNumber, "Please enter the complaint description:");
                break;
            case 8:
                $this->registerComplaint($whatsappNumber, $messageText, $session);
                return;
            default:
                Log::error('Invalid step in session', ['session' => $session]);
                Cache::forget("whatsapp_session_{$whatsappNumber}");
                $this->sendMessage($whatsappNumber, "Something went wrong. Please type 'hi' to start over.");
                return;
        }

        Cache::put("whatsapp_session_{$whatsappNumber}", $session, now()->addMinutes(30));
    }

    private function registerComplaint($whatsappNumber, $description, $session)
    {
        DB::beginTransaction();
        try {
            $lastComplaint = Complaint::latest('id')->first();
            $newId = $lastComplaint ? $lastComplaint->id + 1 : 1;
            $complaintNumber = 'TC' . now()->format('dmY') . $newId;

            $complaint = Complaint::create([
                'applicant_name' => $session['applicant_name'],
                'applicant_phone' => $session['applicant_phone'],
                'applicant_whatsapp' => $session['applicant_whatsapp'],
                'applicant_address' => $session['applicant_address'],
                'branch_id' => $session['branch_id'],
                'complaint_type' => $session['complaint_type'],
                'brand_id' => $session['brand_id'],
                'description' => $description,
                'status' => 'Pending',
                'call_status' => 'New',
                'complain_num' => $complaintNumber,
            ]);

            DB::commit();
            Cache::forget("whatsapp_session_{$whatsappNumber}"); // Clear session
            $this->sendMessage($whatsappNumber, "Complaint registered successfully! Your complaint number is $complaintNumber.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating complaint: " . $e->getMessage());
            $this->sendMessage($whatsappNumber, "There was an issue registering your complaint. Please try again later.");
        }
    }

    private function sendMessage($to, $text, $buttons = [])
    {
        $whatsappApiUrl = "https://graph.facebook.com/v21.0/524018304136329/messages";
        $accessToken = env('WHATSAPP_TOKEN');

        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
        ];

        if (!empty($buttons)) {
            $payload["type"] = "interactive";
            $payload["interactive"] = [
                "type" => "button",
                "body" => ["text" => $text],
                "action" => [
                    "buttons" => array_map(function ($button) {
                        return [
                            "type" => "reply",
                            "reply" => [
                                "id" => strtolower(str_replace(' ', '_', $button)),
                                "title" => $button
                            ]
                        ];
                    }, $buttons)
                ]
            ];
        } else {
            $payload["type"] = "text";
            $payload["text"] = ["body" => $text];
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($whatsappApiUrl, [
                "headers" => [
                    "Authorization" => "Bearer $accessToken",
                    "Content-Type" => "application/json"
                ],
                "json" => $payload
            ]);

            $responseBody = json_decode($response->getBody(), true);
            Log::info("WhatsApp message sent successfully", ["response" => $responseBody]);

            return $responseBody;
        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp message: " . $e->getMessage());
            return false;
        }
    }
}
