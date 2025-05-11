<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StaffAttendence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CreateDailyAttendance extends Command
{
    protected $signature = 'attendance:create';
    protected $description = 'Create a new attendance record for all staff if one does not exist for today';

    public function handle()
    {
        // Get today's date
        $today = Carbon::today();
        
        // Retrieve all staff members
        $staffMembers = \App\Models\Staff::all();

        foreach ($staffMembers as $staff) {
            // Check if an attendance record for the current day already exists for this staff member
            $attendanceExists = StaffAttendence::where('staff_id', $staff->id)
                ->whereDate('created_at', $today)
                ->exists();

            // If no record exists for today, create one
            if (! $attendanceExists) {
                StaffAttendence::create([
                    'staff_id' => $staff->id,
                    // Add other fields if needed (e.g., date, status, etc.)
                ]);
            }
        }

        // Prepare the group push notification data
        $subIds = $staffMembers->pluck('sub_id')->toArray(); // Assuming `sub_id` is the column in the staff table that identifies each user's subscription ID

       foreach ($subIds as $subId) {
    try {
        $response = Http::timeout(10) // Set the timeout to 60 seconds
                        ->post('https://app.nativenotify.com/api/indie/notification', [
                            'subID' => $subId,
                            'appId' => env('NATIVE_NOTIFY_APP_ID', 27090),
                            'appToken' => env('NATIVE_NOTIFY_APP_TOKEN', 'zebq5WbeBz9GG4UyCl2bDm'),
                            'title' => 'Daily Attendance Reminder',
                            'message' => 'Attendance records have been created for today. Please check your status.',
                        ]);
        
        if (!$response->successful()) {
            Log::warning("Failed to send push notification for subId: {$subId}. Error: " . $response->body());
        }
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::warning("Connection error for subId: {$subId}. Error: " . $e->getMessage());
    }
}


        $this->info('Attendance records created and notifications processed.');

        // Fire a notification event
        event(new \App\Events\NewNotification(
            "Daily Reminder",
            "Attendance records have been created! Notifications Sent.",
            "info"
        ));
    }
}
