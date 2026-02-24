<?php

namespace Database\Seeders;

use App\Models\RegulatoryFramework;
use Illuminate\Database\Seeder;

class RegulatoryFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $frameworks = [
            [
                'jurisdiction_code' => 'EU',
                'framework_name' => 'GDPR — Data Processing in Contracts',
                'description' => 'General Data Protection Regulation requirements for contracts that involve processing of personal data within the European Union.',
                'is_active' => true,
                'requirements' => [
                    ['id' => 'gdpr-1', 'text' => 'Contract must include a data processing agreement (DPA) or data processing clauses if personal data is processed on behalf of a controller.', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'gdpr-2', 'text' => 'Contract must specify the subject matter, duration, nature, and purpose of data processing.', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'gdpr-3', 'text' => 'Contract must define the types of personal data processed and categories of data subjects.', 'category' => 'data_protection', 'severity' => 'high'],
                    ['id' => 'gdpr-4', 'text' => 'Contract must require the processor to implement appropriate technical and organisational security measures (Article 32).', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'gdpr-5', 'text' => 'Contract must include provisions for data breach notification without undue delay (Article 33).', 'category' => 'data_protection', 'severity' => 'high'],
                    ['id' => 'gdpr-6', 'text' => 'Contract must address sub-processor engagement — either general or specific written authorisation required.', 'category' => 'data_protection', 'severity' => 'high'],
                    ['id' => 'gdpr-7', 'text' => 'Contract must include provisions for data subject rights assistance (access, rectification, erasure, portability).', 'category' => 'data_protection', 'severity' => 'medium'],
                    ['id' => 'gdpr-8', 'text' => 'Contract must address international data transfers and applicable safeguards (SCCs, adequacy decisions, or BCRs).', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'gdpr-9', 'text' => 'Contract must require deletion or return of personal data upon termination of the processing relationship.', 'category' => 'data_protection', 'severity' => 'medium'],
                    ['id' => 'gdpr-10', 'text' => 'Contract must provide for audit rights — the controller must be able to audit processor compliance.', 'category' => 'data_protection', 'severity' => 'medium'],
                ],
            ],
            [
                'jurisdiction_code' => 'GLOBAL',
                'framework_name' => 'PCI DSS v4.0 — Merchant Agreement Requirements',
                'description' => 'Payment Card Industry Data Security Standard requirements for merchant agreements involving payment card data handling.',
                'is_active' => true,
                'requirements' => [
                    ['id' => 'pci-1', 'text' => 'Agreement must require the service provider to maintain PCI DSS compliance for the duration of the engagement.', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'pci-2', 'text' => 'Agreement must define which PCI DSS requirements are the responsibility of the service provider vs. the merchant.', 'category' => 'financial', 'severity' => 'critical'],
                    ['id' => 'pci-3', 'text' => 'Agreement must require the service provider to acknowledge responsibility for the security of cardholder data it possesses, stores, processes, or transmits.', 'category' => 'data_protection', 'severity' => 'critical'],
                    ['id' => 'pci-4', 'text' => 'Agreement must include provisions for incident response and breach notification specific to cardholder data compromise.', 'category' => 'data_protection', 'severity' => 'high'],
                    ['id' => 'pci-5', 'text' => 'Agreement must require periodic evidence of PCI DSS compliance (AOC, SAQ, or ROC) from the service provider.', 'category' => 'financial', 'severity' => 'high'],
                    ['id' => 'pci-6', 'text' => 'Agreement must address data retention and destruction requirements for cardholder data.', 'category' => 'data_protection', 'severity' => 'medium'],
                    ['id' => 'pci-7', 'text' => 'Agreement must include right-to-audit clauses for PCI DSS compliance verification.', 'category' => 'financial', 'severity' => 'medium'],
                ],
            ],
            [
                'jurisdiction_code' => 'AE',
                'framework_name' => 'UAE Federal Law No. 5/2012 — Electronic Transactions',
                'description' => 'UAE Federal Law on Combating Cybercrimes and Electronic Transactions requirements relevant to merchant and commercial agreements operating in the UAE/MENA region.',
                'is_active' => true,
                'requirements' => [
                    ['id' => 'uae-1', 'text' => 'Electronic contracts must include clear identification of the contracting parties, including legal names and registered addresses.', 'category' => 'other', 'severity' => 'critical'],
                    ['id' => 'uae-2', 'text' => 'Electronic signatures used must comply with UAE recognition standards for electronic authentication.', 'category' => 'other', 'severity' => 'high'],
                    ['id' => 'uae-3', 'text' => 'Contract must specify the governing law and jurisdiction — UAE law requires explicit choice of law in commercial agreements.', 'category' => 'dispute_resolution', 'severity' => 'critical'],
                    ['id' => 'uae-4', 'text' => 'Contract must include a dispute resolution clause specifying arbitration (DIAC/ADCCAC) or court jurisdiction.', 'category' => 'dispute_resolution', 'severity' => 'high'],
                    ['id' => 'uae-5', 'text' => 'If the contract involves personal data, it must comply with UAE Personal Data Protection Law (Federal Decree-Law No. 45/2021) data handling requirements.', 'category' => 'data_protection', 'severity' => 'high'],
                    ['id' => 'uae-6', 'text' => 'Contract records must be retained in a form that allows verification of their integrity and is accessible for inspection by regulatory authorities.', 'category' => 'other', 'severity' => 'medium'],
                    ['id' => 'uae-7', 'text' => 'Contract must include provisions for force majeure that align with UAE Civil Code interpretations.', 'category' => 'liability', 'severity' => 'medium'],
                ],
            ],
        ];

        foreach ($frameworks as $data) {
            RegulatoryFramework::firstOrCreate(
                [
                    'jurisdiction_code' => $data['jurisdiction_code'],
                    'framework_name' => $data['framework_name'],
                ],
                $data
            );
        }
    }
}
