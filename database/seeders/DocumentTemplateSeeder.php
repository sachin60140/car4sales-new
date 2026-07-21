<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * key => [name, module, view]
     */
    public const TEMPLATES = [
        'purchase_agreement' => ['Purchase Agreement', 'vehicle-purchases', 'documents.purchase_agreement'],
        'seller_declaration' => ['Seller Declaration', 'vehicle-purchases', 'documents.purchase_agreement'],
        'possession_receipt' => ['Possession Receipt', 'possessions', 'documents.purchase_agreement'],
        'payment_receipt' => ['Payment Receipt', 'seller-payments', 'documents.purchase_agreement'],
        'inspection_report' => ['Inspection Report', 'inspections', 'documents.purchase_agreement'],
        'valuation_report' => ['Valuation Report', 'valuations', 'documents.purchase_agreement'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::TEMPLATES as $key => [$name, $module, $view]) {
            $templateId = DB::table('document_templates')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'module' => $module, 'engine' => 'blade', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );

            $id = DB::table('document_templates')->where('key', $key)->value('id');

            $exists = DB::table('document_template_versions')
                ->where('document_template_id', $id)->where('version', 1)->exists();

            if (! $exists) {
                DB::table('document_template_versions')->insert([
                    'document_template_id' => $id,
                    'version' => 1,
                    'view' => $view,
                    'is_current' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
