<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Enums\PropertyType;
use App\Enums\RoofType;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * ~15 demo leads across all three statuses. Fields are crafted to genuinely
     * match the assigned status so the dashboard is realistic. Ingestion normally
     * resolves status via LeadQualificationService; here it is set explicitly.
     *
     * Columns: name, email, phone, bill(RM), property, roof, state, status
     */
    public function run(): void
    {
        $leads = [
            // --- qualified: bill >= RM200, landed/commercial, non-flat roof, Peninsular ---
            ['Ahmad bin Ismail', 'ahmad.ismail@example.com', '012-3456789', 350, PropertyType::Landed,     RoofType::Tile,     'Selangor',         LeadStatus::Qualified],
            ['Tan Wei Ming',      'weiming.tan@example.com',  '016-2233445', 500, PropertyType::Commercial, RoofType::Metal,    'Kuala Lumpur',     LeadStatus::Qualified],
            ['Priya Nair',        'priya.nair@example.com',   '017-8899001', 280, PropertyType::Landed,     RoofType::Concrete, 'Pulau Pinang',     LeadStatus::Qualified],
            ['Lim Chong Wei',     'chongwei.lim@example.com', '013-5566778', 620, PropertyType::Commercial, RoofType::Tile,     'Johor',            LeadStatus::Qualified],
            ['Nurul Huda',        'nurul.huda@example.com',   '011-22334455', 210, PropertyType::Landed,    RoofType::Metal,    'Perak',            LeadStatus::Qualified],
            ['Suresh Kumar',      'suresh.kumar@example.com', '019-7788990', 450, PropertyType::Landed,     RoofType::Tile,     'Melaka',           LeadStatus::Qualified],

            // --- under_review: bill RM150–199, everything else qualifies ---
            ['Farah Aziz',        'farah.aziz@example.com',   '018-1234567', 180, PropertyType::Landed,     RoofType::Tile,     'Negeri Sembilan',  LeadStatus::UnderReview],
            ['Wong Mei Ling',     'meiling.wong@example.com', '014-9988776', 165, PropertyType::Commercial, RoofType::Concrete, 'Kedah',            LeadStatus::UnderReview],
            ['Raj Patel',         'raj.patel@example.com',    '012-6655443', 199, PropertyType::Landed,     RoofType::Metal,    'Pahang',           LeadStatus::UnderReview],
            ['Siti Aminah',       'siti.aminah@example.com',  '017-3344556', 150, PropertyType::Landed,     RoofType::Tile,     'Terengganu',       LeadStatus::UnderReview],

            // --- disqualified: one hard rule each (property / roof / state / bill<150) ---
            ['Chan Kok Leong',    'kokleong.chan@example.com', '016-7766554', 320, PropertyType::Condo,     RoofType::Tile,     'Kuala Lumpur',     LeadStatus::Disqualified], // condo
            ['Hafiz Rahman',      'hafiz.rahman@example.com',  '013-2211009', 400, PropertyType::Landed,    RoofType::Flat,     'Selangor',         LeadStatus::Disqualified], // flat roof
            ['Grace Lim',         'grace.lim@example.com',     '019-4433221', 500, PropertyType::Commercial, RoofType::Metal,   'Sabah',            LeadStatus::Disqualified], // non-Peninsular
            ['Anand Krishnan',    'anand.k@example.com',       '011-99887766', 120, PropertyType::Landed,   RoofType::Tile,     'Pulau Pinang',     LeadStatus::Disqualified], // bill < 150
            ['Mohd Faizal',       'faizal.mohd@example.com',   '018-5544332', 260, PropertyType::Apartment, RoofType::Concrete, 'Sarawak',          LeadStatus::Disqualified], // apartment + non-Peninsular
        ];

        foreach ($leads as [$name, $email, $phone, $bill, $property, $roof, $state, $status]) {
            Lead::create([
                'customer_name'   => $name,
                'email'           => $email,
                'phone'           => $phone,
                'monthly_bill_rm' => $bill,
                'property_type'   => $property,
                'roof_type'       => $roof,
                'state'           => $state,
                'status'          => $status,
            ]);
        }
    }
}
