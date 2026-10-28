<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lead-form enquiries were all saved as "lead_form" and shown as "Campaign
 * form": name them by what they are (air ticket quote, visa enquiry,
 * campaign form, other enquiry form).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inquiries')->where('source', 'lead_form')->orderBy('id')->chunkById(200, function ($rows) {
            $campaignPages = DB::table('pages')->where('type', 'campaign')->pluck('id')->all();

            foreach ($rows as $row) {
                $data = json_decode((string) $row->data, true) ?: [];
                $source = match (true) {
                    array_key_exists('trip_type', $data) || array_key_exists('from_city', $data) => 'fare_quote',
                    array_key_exists('visa_country', $data) || array_key_exists('visa_type', $data) => 'visa_enquiry',
                    in_array($row->page_id, $campaignPages, false) => 'campaign_form',
                    default => 'lead_form',
                };

                if ($source !== 'lead_form') {
                    DB::table('inquiries')->where('id', $row->id)->update(['source' => $source]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('inquiries')->whereIn('source', ['fare_quote', 'visa_enquiry', 'campaign_form'])->update(['source' => 'lead_form']);
    }
};
