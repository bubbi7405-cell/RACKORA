<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Certificate;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $certs = [
            [
                'key' => 'iso_27001',
                'name' => 'ISO/IEC 27001',
                'description' => 'The international standard for information security management. Proves your systems are resilient against cyber threats.',
                'category' => 'security',
                'bonus_reputation' => 15.0,
                'requirements' => [
                    'min_security' => 80.0,
                    'research' => 'firewall_v2',
                ],
            ],
            [
                'key' => 'gdpr_compliance',
                'name' => 'GDPR Data Shield',
                'description' => 'Guarantees that customer data is handled according to strict European privacy laws. Required for many EU enterprise clients.',
                'category' => 'privacy',
                'bonus_reputation' => 10.0,
                'requirements' => [
                    'min_privacy' => 75.0,
                    'research' => 'encryption_v2',
                ],
            ],
            [
                'key' => 'tier_3_standard',
                'name' => 'Tier III Certification',
                'description' => 'Uptime institute standard for concurrently maintainable infrastructure. Proves your redundancy is real.',
                'category' => 'quality',
                'bonus_reputation' => 20.0,
                'requirements' => [
                    'min_uptime' => 99.9,
                    'research' => 'dmz_architecture',
                ],
            ],
            [
                'key' => 'soc2_type1',
                'name' => 'SOC 2 Type I',
                'description' => 'Service Organization Control report for cloud and managed service providers. Focuses on security, availability, and processing integrity.',
                'category' => 'security',
                'bonus_reputation' => 12.0,
                'requirements' => [
                    'min_security' => 70.0,
                    'min_privacy' => 50.0,
                ],
            ],
            [
                'key' => 'gov_grade_destruction',
                'name' => 'Gov-Grade Secure Destruction',
                'description' => 'Certified on-site physical hardware destruction. Required for Intelligence Agency and high-clearance Government contracts.',
                'category' => 'security',
                'bonus_reputation' => 25.0,
                'requirements' => [
                    'min_security' => 90.0,
                    'shred_count' => 50,
                ],
            ],
            [
                'key' => 'green_ops_standard',
                'name' => 'GreenOps Global Standard',
                'description' => 'Proof of carbon-neutral data center operations. Required for high-level Fortune 500 environmental mandates.',
                'category' => 'eco',
                'bonus_reputation' => 18.0,
                'requirements' => [
                    'min_green_rep' => 80.0,
                ],
            ],
        ];

        foreach ($certs as $cert) {
            Certificate::updateOrCreate(['key' => $cert['key']], $cert);
        }
    }
}
