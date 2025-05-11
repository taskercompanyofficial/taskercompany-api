<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\AssignedJobs;
use App\Models\Complaint;
use App\Models\ComplaintHistory;
use App\Models\Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Illuminate\Validation\ValidationException;
use App\Events\NewNotification;
use App\Models\ChatRoom;
use App\Models\AuthorizedBrands;
use App\Models\StoreUserSpecific;
use App\Models\Scedualer;
// Add this import at the top of your file
use Illuminate\Validation\Rule;
class ComplaintController extends Controller
{
    private $whatsappClient;

    public function __construct()
    {
        $this->whatsappClient = new Client();
    }

   public function index(Request $request)
{
    try {
        $perPage = $request->input('per_page', 100);
        $page = max(1, (int) $request->input('page', 1));
        $q = trim($request->input('q', ''));
        $status = $request->input('status');
        $brand_id = $request->input('brand_id');
        $branch_id = $request->input('branch_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $filters = json_decode($request->input('filters', '[]'), true);
        $logic = strtolower($request->input('logic', 'AND'));
        $sort = json_decode($request->input('sort', '[]'), true);

        $hasStatusFilter = collect($filters)->contains(fn($filter) => $filter['id'] === 'status');

        $complaintsQuery = Complaint::query()
            ->with(['brand', 'branch'])
            ->select('complaints.*')
            ->addSelect([
                'technician' => DB::table('staff')
                    ->select('full_name')
                    ->whereColumn('staff.id', 'complaints.technician')
                    ->limit(1)
            ])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('complain_num', 'like', "%$q%")
                        ->orWhere('applicant_name', 'like', "%$q%")
                        ->orWhere('brand_complaint_no', 'like', "%$q%")
                        ->orWhere('applicant_email', 'like', "%$q%")
                        ->orWhere('applicant_phone', 'like', "%$q%")
                        ->orWhere('extra_numbers', 'like', "%$q%")
                        ->orWhere('applicant_whatsapp', 'like', "%$q%")
                        ->orWhere('serial_number_ind', 'like', "%$q%")
                        ->orWhere('serial_number_oud', 'like', "%$q%")
                        ->orWhere('model', 'like', "%$q%")
                        ->orWhere('mq_nmb', 'like', "%$q%")
                        ->orWhere('description', 'like', "%$q%")
                        ->orWhere('applicant_adress', 'like', "%$q%");
                });
            })
            ->when($status, function ($query) use ($status) {
                $statuses = array_map('trim', explode('.', $status));
                $query->whereIn('status', $statuses);
            })
            ->when(!$q && !$status && !$hasStatusFilter, function ($query) {
                $query->whereNotIn('status', ['closed', 'cancelled']);
            })
            ->when($brand_id, fn($query) => $query->where('brand_id', $brand_id))
            ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
            ->when($from && $to, fn($query) => $query->whereBetween('created_at', [$from, $to]));

        if (!empty($filters)) {
            $complaintsQuery->where(function ($query) use ($filters, $logic) {
                foreach ($filters as $index => $filter) {
                    $method = ($index === 0) ? 'where' : ($logic === 'or' ? 'orWhere' : 'where');

                    if ($filter['condition'] === 'null') {
                        $query->{$method}($filter['id'], null);
                    } elseif ($filter['condition'] === 'between') {
                        $values = explode(',', $filter['value']);
                        if (count($values) === 2) {
                            $query->{$method . 'Between'}($filter['id'], [$values[0], $values[1]]);
                        }
                    } elseif (in_array($filter['condition'], ['in', 'not in'])) {
                        $values = array_map('trim', explode('.', $filter['value']));
                        $query->{$method . ($filter['condition'] === 'in' ? 'In' : 'NotIn')}($filter['id'], $values);
                    } else {
                        $value = in_array($filter['condition'], ['like', 'not like']) ? "%{$filter['value']}%" : $filter['value'];
                        $query->{$method}($filter['id'], $filter['condition'], $value);
                    }
                }
            });
        }

        if (!empty($sort)) {
            foreach ($sort as $sortItem) {
                $direction = $sortItem['desc'] ? 'desc' : 'asc';
                $complaintsQuery->orderBy($sortItem['id'], $direction);
            }
        } else {
            $complaintsQuery
                ->orderByRaw("FIELD(status,  'feedback-pending', 'quotation-applied', 'hold-by-us', 'hold-by-customer',  'kit-in-service-center', 'unit-in-service-center','part-demand','pending-by-brand', 'completed','in-progress','assigned-to-technician', 'open') DESC")
                ->orderByDesc('created_at'); 
        }

        $complaints = $complaintsQuery->paginate($perPage, ['*'], 'page', $page);

        $complaintsData = $complaints->map(function ($complaint) {
            $data = $complaint->toArray();
            $data['brand_id'] = $complaint->brand->name ?? null;
            $data['branch_id'] = $complaint->branch->name ?? null;
            return $data;
        });

        return response()->json([
            'data' => $complaintsData,
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'first_page' => 1,
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'next_page' => $complaints->hasMorePages() ? $complaints->currentPage() + 1 : null,
                'prev_page' => $complaints->currentPage() > 1 ? $complaints->currentPage() - 1 : null,
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error fetching complaints: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch complaints'
        ], 500);
    }
}



    public function store(Request $request)
    {
        try {
            $payload = $request->validate([
                'brand_complaint_no' => 'nullable|string|max:255',
                'applicant_name' => 'required|string|max:255',
                'applicant_phone' => 'required|string|max:20',
                'applicant_whatsapp' => 'required|string|max:20',
                'applicant_adress' => 'required|string|max:500',
                'brand_id' => 'required|integer',
                'branch_id' => 'required|integer|exists:branches,id',
                'extra_numbers' => 'required|string|max:255',
                'reference_by' => 'required|string|max:255',
                'dealer' => 'required|string|max:255',
                'product' => 'nullable|string|max:255',
                'complaint_type' => 'required|string|max:255',
                'working_details' => 'nullable|string|max:255',
                'description' => 'required|string',
                'status' => 'required|string|max:50',
                'call_status' => 'nullable|string|max:50',
            ]);

            DB::beginTransaction();

            $payload['user_id'] = $request->user() ? $request->user()->id : 12;
            $lastComplaint = Complaint::latest('id')->first();
            $newId = $lastComplaint ? $lastComplaint->id + 1 : 1;
            $payload['complain_num'] = 'TC' . now()->format('dmY') . $newId;
 $complaint = Complaint::create($payload);
            $complaint->refresh(); // Refresh to get the latest data including ID
            DB::commit();

            $title = "New Complaint";
            $message = "A New Complaint has been received!";
            $link = "https://crm.taskercompany.com/crm/complaints/" . $complaint->id;
            $status = "info";


            event(new NewNotification($title, $message, $status, $link));

            // Send WhatsApp notification
            try {
                $this->sendWhatsAppNotification($payload, 'complaint_create_template', $payload['applicant_whatsapp']);
            } catch (\Exception $e) {
                Log::error("WhatsApp notification failed: " . $e->getMessage());
                // Continue execution even if WhatsApp notification fails
            }

            return response()->json([
                "status" => "success",
                "message" => "Complaint has been created successfully",
                "data" => $complaint,
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                "status" => "error",
                "message" => "Validation failed",
                "errors" => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating Complaint: " . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Failed to create complaint" . $e->getMessage()
            ], 500);
        }
    }

  public function show($idOrComplainNum)
{
    try {
        $complaint = Complaint::with(['brand', 'branch', 'technician', 'user'])
            ->where('id', $idOrComplainNum)
            ->orWhere('complain_num', $idOrComplainNum)
            ->firstOrFail();

        return response()->json($complaint);
    } catch (\Exception $e) {
        Log::error("Error fetching complaint: " . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Complaint not found'
        ], 404);
    }
}


 public function update(Request $request, $id)
{
    try {
        DB::beginTransaction();

        $complaint = Complaint::findOrFail($id);
        $oldStatus = $complaint->status;

        // Basic validation rules
        $validationRules = [
            'brand_complaint_no' => 'nullable|string|max:255',
            'applicant_name' => 'required|string|max:255',
            'applicant_phone' => 'required|string|max:20',
            'applicant_whatsapp' => 'required|string|max:20',
            'applicant_adress' => 'required|string|max:500',
            'extra_numbers' => 'required|string|max:255',
            'reference_by' => 'required|string|max:255',
            'dealer' => 'required|string|max:255',
            'extra' => 'nullable|string',
            'description' => 'required|string',
            'branch_id' => 'required|integer|exists:branches,id',
            'brand_id' => 'required|integer',
            'product' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'working_details' => 'nullable|string',
            'serial_number_ind' => 'nullable|string|max:255',
            'serial_number_oud' => 'nullable|string|max:255',
            'mq_nmb' => 'nullable|string|max:255',
            'p_date' => 'nullable|date',
            'complete_date' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'product_type' => 'nullable|string|max:255',
            'technician' => 'nullable',
            'status' => 'required|string|max:50',
            'complaint_type' => 'required|string|max:255',
            'provided_services' => 'nullable|string',
            'warranty_type' => 'nullable|string|max:255',
            'comments_for_technician' => 'nullable|string',
            'files' => 'nullable',
            'send_message_to_technician' => 'nullable|boolean',
            'call_status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
        ];

        // Add unique validation for serial numbers if complaint type is new-ac-free-installation
        if ($request->input('complaint_type') === 'new-ac-free-installation') {
            $status = $request->input('status');
            $requiredStatuses = ['feedback-pending', 'pending-by-brand', 'closed'];
            
            // Serial numbers must be unique and required only for specific statuses
            if (in_array($status, $requiredStatuses)) {
                // For indoor serial number, make it required and unique
                $validationRules['serial_number_ind'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('complaints', 'serial_number_ind')->ignore($id)
                ];

                // For outdoor serial number, make it required and unique
                $validationRules['serial_number_oud'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('complaints', 'serial_number_oud')->ignore($id)
                ];
            } else {
                // For other statuses, serial numbers are not required but still must be unique if provided
                $validationRules['serial_number_ind'] = [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('complaints', 'serial_number_ind')->ignore($id)
                ];
                
                $validationRules['serial_number_oud'] = [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('complaints', 'serial_number_oud')->ignore($id)
                ];
            }
        }

        $payload = $request->validate($validationRules);
        
        if ($payload['status'] === 'feedback-pending') {
            $pendingCount = Complaint::where('status', 'feedback-pending')->count();
        
            if ($pendingCount >= 50) {
                return response()->json([
                    "status" => "error",
                    "message" => "Cannot create more complaints with status 'feedback-pending'. Limit exceeded (50). Current in Feedback Pending " . $pendingCount 
                ], 400);
            }
        }

        $technicianChanged = $complaint->technician !== $payload['technician'];
        $oldData = $complaint->toArray();

        $complaint->update($payload);
        if ($request->user()->id == $payload['technician']) {
            $title = $request->user()->full_name . " has updated a complaint";
            $message = $request->user()->full_name . " has updated a complaint ID" . $complaint->complain_num;
            $status = "info";
            $link = "https://crm.taskercompany.com/crm/complaints/" . $complaint->id;
            event(new NewNotification($title, $message, $status, $link));
        }
        $newData = $complaint->toArray();

        // Generate description by comparing changes
        $changes = [];
        foreach ($newData as $key => $value) {
            if ($key !== 'updated_at' && $key !== 'files' && isset($oldData[$key]) && $oldData[$key] !== $value) {
                $changes[] = ucfirst(str_replace('_', ' ', $key)) . " changed from '{$oldData[$key]}' to '{$value}'";
            }
        }

        $description = empty($changes)
            ? 'Complaint updated with no field changes'
            : 'Complaint updated: ' . implode(', ', $changes);

        ComplaintHistory::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'data' => json_encode($complaint),
            'description' => $description
        ]);

        // Handle technician assignment and notification
        if (!empty($payload['send_message_to_technician'])) {
            $this->handleJobAssignment($complaint, $payload, $technicianChanged, $payload['technician']);
        }

        // Send WhatsApp message if status changed to closed
        if (in_array($payload['status'], ['closed', 'cancelled']) && $oldStatus !== $payload['status']) {
            $message = "Dear *{$complaint->applicant_name}*,\n\n";

            if ($payload['status'] === 'closed') {
                $message .= "Apki shikayat (ID: {$complaint->complain_num}) ka masla hal kar diya gaya hai.\n\n";
                $message .= "*Shukriya Tasker Company ka intekhab karne ka.*\n\n";
                $message .= "Barah-e-karam mazeed maloomat ya madad ke liye neeche diye gaye raabta zaraye istemal karein:\n\n";
                $message .= "- *Helpline:* 03025117000\n";
                $message .= "- *Website:* www.taskercompany.com\n\n";
                $message .= "*Important Note:* Tasker Company ke Technician ya kisi bhi doosre worker se direct rabta na karein. Agar aap aisa karte hain to kisi bhi nuqsan ya maslay ki zimmedari Tasker Company par nahi hogi.";
            } elseif ($payload['status'] === 'cancelled') {
                $message .= "Apki shikayat (ID: {$complaint->complain_num}) cancel kar di gayi hai.\n\n";
                $message .= "*Agar aap ko kisi qisam ka masla ho ya madad darkar ho to barah-e-karam neeche diye gaye raabta zaraye istemal karein:*\n\n";
                $message .= "- *Helpline:* 03025117000\n";
                $message .= "- *Website:* www.taskercompany.com\n\n";
                $message .= "*Important Note:* Tasker Company ke Technician ya kisi bhi doosre worker se direct rabta na karein. Agar aap aisa karte hain to kisi bhi nuqsan ya maslay ki zimmedari Tasker Company par nahi hogi.";
            }

            $message .= "\n\nBest regards,\nTasker Company";

            try {
                $this->sendWhatsAppTextMessage($complaint->applicant_whatsapp, $message);
            } catch (\Exception $e) {
                Log::error("WhatsApp status update message failed: " . $e->getMessage());
            }
        }

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Complaint has been updated successfully',
            'data' => $complaint
        ]);
    } catch (ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Error updating complaint: " . $e->getMessage(), [
            'stack' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update complaint: ' . $e->getMessage()
        ], 500);
    }
}

    public function destroy($id)
    {
        try {
            $complaint = Complaint::findOrFail($id);

            // Delete all associated complaint histories first
            $complaintHistories = ComplaintHistory::where('complaint_id', $complaint->id);
            if ($complaintHistories->exists()) {
                $complaintHistories->delete();
            }

            // Delete all associated assigned jobs first
            $assignedJobs = AssignedJobs::where('job_id', $complaint->id);
            if ($assignedJobs->exists()) {
                $assignedJobs->delete();
            }

            // Now delete the complaint
            $complaint->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Complaint and its history have been deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting complaint: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete complaint: ' . $e->getMessage()
            ], 500);
        }
    }
       public function cancleComplaint($id, Request $request)
    {
        try {
            $payload = $request->validate([
                'reason' => 'required|string',
                'details' => 'required|string',
                'file' => 'nullable|file'
            ]);

            $complaint = Complaint::findOrFail($id);
            $complaint->status = 'cancelled';
            $complaint->cancellation_reason = $payload['reason'];
            $complaint->cancellation_details = $payload['details'];

            if ($request->hasFile('file')) {
                // Handle file upload if needed
                $file = $request->file('file');
                $path = $file->store('cancelled_complaints', 'public');
                $complaint->cancellation_file = $path;
            }

            $complaint->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Complaint has been cancelled successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error cancelling complaint: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel complaint'
            ], 500);
        }
    }
       public function scedualeComplaint(Request $request)
    {
        try {
            $payload = $request->validate([
                'complain_num' => 'required|integer|exists:complaints,id',
                'date' => 'required|date|after:now',
                'complaint_details' => 'nullable|string|max:500'
            ]);

            $complaint = Complaint::findOrFail($payload['complain_num']);

            // Update complaint status to scheduled
            $complaint->status = 'scheduled';
            $complaint->save();
            $sceduale = Scedualer::create($payload);
            return response()->json([
                'status' => 'success',
                'message' => 'Complaint has been scheduled successfully',
                'data' => $complaint
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed' . $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error scheduling complaint: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to schedule complaint'
            ], 500);
        }
    }
    public function technicianReachedOnSite($id)
    {
        $complaint = Complaint::findOrFail($id);
        $complaint->status = 'technician_reached';
        $complaint->save();
        return response()->json([
            'status' => 'success',
            'message' => 'Complaint status updated to technician_reached'
        ]);
    }
    public function getComplaintHistory($id)
    {
        $complaintHistory = ComplaintHistory::where('complaint_id', $id)->with('user')->get();
        return response()->json($complaintHistory);
    }

    public function sendMessage(Request $request, $to)
    {
        try {
            $payload = $request->validate([
                'message_type' => 'required|string',
                'complain_num' => 'required|string',
                'applicant_name' => 'required|string',
                'applicant_phone' => 'required|string',
                'applicant_adress' => 'required|string',
                'description' => 'nullable|string',
                'status' => 'nullable|string',
                'remarks' => 'nullable|string',
            ]);

            $templateName = $payload['message_type'];

            $this->sendWhatsAppNotification($request, $templateName, $to);

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Error sending message: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sendWhatsAppNotification($complaint, $templateName, $to)
    {
        try {
            // Clean and format the WhatsApp number
            $whatsappNumber = preg_replace('/[^0-9]/', '', $to);

            $payload = [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('WHATSAPP_TOKEN'),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $whatsappNumber,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => 'en_US'],
                        'components' => $this->getTemplateComponents($complaint, $templateName)
                    ]
                ]
            ];

            $response = $this->whatsappClient->post('https://graph.facebook.com/v21.0/501488956390575/messages', $payload);

            Log::info("WhatsApp notification sent successfully", [
                'template' => $templateName,
                'whatsapp_number' => $whatsappNumber
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp message: " . $e->getMessage(), [
                'template' => $templateName,
                'whatsapp_number' => $complaint->applicant_whatsapp ?? 'not_provided'
            ]);
            throw $e;
        }
    }

    private function sendWhatsAppTextMessage($to, $message)
    {
        try {
            $whatsappNumber = preg_replace('/[^0-9]/', '', $to);

            $payload = [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('WHATSAPP_TOKEN'),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $whatsappNumber,
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]
            ];

            $response = $this->whatsappClient->post('https://graph.facebook.com/v21.0/501488956390575/messages', $payload);

            Log::info("WhatsApp text message sent successfully", [
                'whatsapp_number' => $whatsappNumber
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp text message: " . $e->getMessage(), [
                'whatsapp_number' => $to
            ]);
            throw $e;
        }
    }

    private function getTemplateComponents($complaint, string $templateName)
    {
        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'text', 'text' => $complaint['applicant_name']]
                ]
            ]
        ];

        if ($templateName === 'complaint_create_template') {
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $complaint['complain_num']],
                    ['type' => 'text', 'text' => $complaint['applicant_phone']],
                    ['type' => 'text', 'text' => $complaint['applicant_adress']],
                    ['type' => 'text', 'text' => $complaint['description']]
                ]
            ];

            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => 0,
                'parameters' => [
                    ['type' => 'text', 'text' => "https://www.taskercompany.com"]
                ]
            ];

            $components[] = [
                'type' => 'button',
                'sub_type' => 'VOICE_CALL',
                'index' => 1,
                'parameters' => [
                    ['type' => 'text', 'text' => $complaint['applicant_phone']]
                ]
            ];
        } else if ($templateName === 'auto_pay_reminder_2') {
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $complaint['complain_num']],
                    ['type' => 'text', 'text' => $complaint['status']],
                    ['type' => 'text', 'text' => $complaint['remarks'] ?? 'No remarks']
                ]
            ];

            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => 0,
                'parameters' => [
                    ['type' => 'text', 'text' => "https://www.taskercompany.com"]
                ]
            ];
        }

        return $components;
    }

    private function handleJobAssignment($complaint, array $payload, bool $technicianChanged, $user_id)
    {
        // Check if job already exists
        $existingJob = AssignedJobs::where('job_id', $complaint->id)
            ->whereIn('status', ['open', 'pending'])
            ->first();

        if (!$existingJob) {
            // Create new job assignment only if none exists
            $job = AssignedJobs::create([
                'job_id' => $complaint->id,
                'assigned_by' => $user_id,
                'assigned_to' => $payload['technician'],
                'branch_id' => $payload['branch_id'],
                'description' => $payload['comments_for_technician'],
                'status' => 'pending',
            ]);
        } else {
            $job = $existingJob;
        }

        // Send notification if requested
        if ($job && ($payload['send_message_to_technician'] ?? false)) {
            $this->createAndSendNotification($complaint, $job, $technicianChanged, $payload['technician'], $user_id);
        }
    }

    private function createAndSendNotification($complaint, AssignedJobs $job, bool $technicianChanged, string $technicianId, $user_id)
    {
        $notificationTitle = $technicianChanged ? 'New Job Assigned' : 'Job Updated';
        $notificationBody = sprintf(
            "Complaint #%s\nCustomer: %s\nProduct: %s\nDescription: %s\n%s",
            $complaint->complain_num,
            $complaint->applicant_name,
            $complaint->product,
            $job->description,
            $technicianChanged ? "New assignment" : "Updated job"
        );

        $notification = Notifications::create([
            'user_id' => $user_id,
            'title' => $notificationTitle,
            'body' => $notificationBody,
            'type' => 'complaint_update',
            'params' => json_encode([
                'page' => 'complaint',
                'id' => $job->id,
            ], JSON_PRETTY_PRINT)
        ]);

        if ($notification) {
            $this->sendPushNotification($technicianId, $notificationTitle, $notificationBody);
        }
    }

    private function sendPushNotification(string $technicianId, string $title, string $body)
    {
        try {
            $pushToken = StoreUserSpecific::where('user_id', $technicianId)->first()->push_token;

            $response = $this->whatsappClient->post('https://exp.host/--/api/v2/push/send', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $pushToken,
                    'title' => $title,
                    'body' => $body
                ]
            ]);

            Log::info("Push notification sent successfully", [
                'technician_id' => $technicianId,
                'title' => $title
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error("Error sending push notification: " . $e->getMessage(), [
                'technician_id' => $technicianId,
                'title' => $title
            ]);
        }
    }
}
