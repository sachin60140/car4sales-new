<?php

namespace Database\Seeders;

use App\Domain\Inspections\Models\InspectionChecklistItem;
use Illuminate\Database\Seeder;

class InspectionChecklistSeeder extends Seeder
{
    /**
     * section_key => [ [label, is_critical], ... ]
     */
    public const SECTIONS = [
        'exterior' => [['Body panels & dents', false], ['Paint condition / repaint', false], ['Windshield & glass', false], ['Lights & indicators', false]],
        'interior' => [['Seats & upholstery', false], ['Dashboard & console', false], ['Infotainment', false], ['Odours / water ingress', true]],
        'engine' => [['Engine noise', true], ['Oil leaks', true], ['Coolant / radiator', false], ['Belts & hoses', false]],
        'transmission' => [['Gear shifting', true], ['Clutch condition', false]],
        'suspension' => [['Front suspension', false], ['Rear suspension', false], ['Steering play', false]],
        'brakes' => [['Brake pads', false], ['Brake discs', false], ['Handbrake', false]],
        'tyres' => [['Tyre tread (all four)', false], ['Spare tyre', false]],
        'electrical' => [['Battery health', false], ['AC / heater', false], ['Power windows', false], ['Wiring', false]],
        'obd' => [['OBD scan / error codes', true]],
        'test_drive' => [['Acceleration', false], ['Braking', false], ['NVH on road', false]],
        'structural' => [['Chassis', true], ['Pillars', true], ['Aprons', true], ['Accident evidence', true], ['Flood / fire indicators', true]],
        'documents' => [['RC verified', false], ['Service history', false], ['Insurance validity', false]],
    ];

    public function run(): void
    {
        foreach (self::SECTIONS as $section => $items) {
            $order = 0;

            foreach ($items as [$label, $critical]) {
                InspectionChecklistItem::query()->updateOrCreate(
                    ['section_key' => $section, 'label' => $label],
                    ['is_critical' => $critical, 'sort_order' => $order++, 'is_active' => true],
                );
            }
        }
    }
}
