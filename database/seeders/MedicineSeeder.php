<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicine;

class MedicineSeeder extends Seeder
{
    public function run()
    {
        $medicines = [
            [
                'medicine_name' => 'Paracetamol',
                'brand' => 'Biogesic', // ✅ ADD
                'category' => 'Painkiller',
                'dosage' => '500mg', // ✅ ADD
                'quantity' => 100,
                'unit' => 'tablets',
                'price' => 5.00, // ✅ ADD
                'expiration_date' => '2026-12-31',
            ],
            [
                'medicine_name' => 'Ibuprofen',
                'brand' => 'Advil',
                'category' => 'Painkiller',
                'dosage' => '200mg',
                'quantity' => 50,
                'unit' => 'tablets',
                'price' => 8.50,
                'expiration_date' => '2025-10-15',
            ],
            [
                'medicine_name' => 'Amoxicillin',
                'brand' => 'Amoxil',
                'category' => 'Antibiotic',
                'dosage' => '500mg',
                'quantity' => 80,
                'unit' => 'capsules',
                'price' => 12.00,
                'expiration_date' => '2026-08-20',
            ],
            [
                'medicine_name' => 'Vitamin C',
                'brand' => 'Ceelin',
                'category' => 'Vitamin',
                'dosage' => '500mg',
                'quantity' => 120,
                'unit' => 'tablets',
                'price' => 3.00,
                'expiration_date' => '2027-01-01',
            ],
            [
                'medicine_name' => 'Cetirizine',
                'brand' => 'Zyrtec',
                'category' => 'Antihistamine',
                'dosage' => '10mg',
                'quantity' => 40,
                'unit' => 'tablets',
                'price' => 6.00,
                'expiration_date' => '2025-11-11',
            ],
            [
                'medicine_name' => 'Acyclovir',
                'brand' => 'Zovirax',
                'category' => 'Antiviral',
                'dosage' => '400mg',
                'quantity' => 20,
                'unit' => 'tablets',
                'price' => 15.00,
                'expiration_date' => '2025-12-12',
            ],
            [
                'medicine_name' => 'Salbutamol Syrup',
                'brand' => 'Ventolin',
                'category' => 'Others',
                'dosage' => '2mg/5ml',
                'quantity' => 10,
                'unit' => 'ml',
                'price' => 25.00,
                'expiration_date' => '2025-08-01',
            ],
            [
                'medicine_name' => 'ORS Sachet',
                'brand' => 'Hydrite',
                'category' => 'Others',
                'dosage' => '1 sachet',
                'quantity' => 200,
                'unit' => 'sachets',
                'price' => 10.00,
                'expiration_date' => '2027-03-15',
            ],
        ];

        foreach ($medicines as $medicine) {
            Medicine::create(array_merge($medicine, [
                'status' => 'Available' // optional override, or auto compute sa model/controller
            ]));
        }
    }
}