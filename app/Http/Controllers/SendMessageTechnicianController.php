<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Complaint;
use App\Models\Staff;
use Illuminate\Support\Facades\Http;

class SendMessageTechnicianController extends Controller
{
    public function post(Request $request)
{
    // Retrieve inputs
    $technicianId = $request->input('technician');
    $complaintId = $request->input('complaint_id');
    $additionalInfo = $request->input('additional_info');

    // Fetch technician details
    $staff = Staff::findOrFail($technicianId);
    
    // Fetch the complaint details based on the complaint number
    $complaint = Complaint::where('complain_num', $complaintId)->with('brand', 'branch')->first();
    if (!$complaint) {
        return response()->json(['message' => 'Complaint not found.'], 404);
    }

    // Set the technician for the complaint and save
    $complaint->technician = $technicianId;
    $complaint->status = 'assigned-to-technician'; // You can adjust the status as needed
    $complaint->save();

    // Prepare the formatted message content
    $message = $this->prepareMessage($complaint, $additionalInfo);

    // Send the message to the technician via WhatsApp
    $this->sendMessageToTechnician('03265117000', $message); // Assuming `phone` field exists in `Staff`

    return response()->json([
        'status'=> 'success',
        'message' => 'Complaint assigned to technician and information sent.' . $staff->phone_number,
    ]);
}


   private function prepareMessage($complaint, $additionalInfo)
{
    return "
📋 *Complaint #{$complaint->complain_num}*
*Brand Ref:* " . (isset($complaint->brand_complaint_no) ? $complaint->brand_complaint_no : 'N/A') . "

👤 *Applicant Details*
*Name:* {$complaint->applicant_name}
*Phone:* {$complaint->applicant_phone}
*WhatsApp:* " . (isset($complaint->applicant_whatsapp) ? $complaint->applicant_whatsapp : 'N/A') . "
*Extra Numbers:* " . (isset($complaint->extra_numbers) ? $complaint->extra_numbers : 'N/A') . "
*Address:* {$complaint->applicant_adress}

📦 *Product Details*
*Product:* " . (isset($complaint->product) ? $complaint->product : 'N/A') . "
*Brand:* " . (isset($complaint->brand->name) ? $complaint->brand->name : 'N/A') . "
*Model:* " . (isset($complaint->model) ? $complaint->model : 'N/A') . "
*Serial (IND):* " . (isset($complaint->serial_number_ind) ? $complaint->serial_number_ind : 'N/A') . "
*Serial (OUD):* " . (isset($complaint->serial_number_oud) ? $complaint->serial_number_oud : 'N/A') . "

🔧 *Service Information*
*Branch:* " . (isset($complaint->branch->name) ? $complaint->branch->name : 'N/A') . "
*Type:* {$complaint->complaint_type}
*Complaint:* {$complaint->description}

*Created:* {$complaint->created_at}
-------------------
*Additional Info:* {$additionalInfo}
";
}


    // Send message to technician via WhatsApp
    private function sendMessageToTechnician($whatsappNumber, $message)
    {
        $accessToken = env('WHATSAPP_TOKEN');
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";
        
        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $whatsappNumber,
            "text" => [
                "body" => $message
            ]
        ];

        // Make the request to WhatsApp API
        try {
            $response = Http::withToken($accessToken)
                ->post($url, $payload);
            
            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp API Error', [
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }
}