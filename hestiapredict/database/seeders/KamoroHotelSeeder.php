<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class KamoroHotelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Création des comptes de test (Inclusion du point-virgule de sécurité)
        $receptionist = User::updateOrCreate(
            ['email' => 'reco1@kamorohotel.com'],
            ['name' => 'Mahery Réception', 'password' => Hash::make('reco123'), 'role' => 'receptionist']
        );

        User::updateOrCreate(
            ['email' => 'admin@kamorohotel.com'],
            ['name' => 'Directeur Kamoro', 'password' => Hash::make('admin123'), 'role' => 'admin']
        );

        User::updateOrCreate(
            ['email' => 'superadmin@kamorohotel.com'],
            ['name' => 'Super Admin Kamoro', 'password' => Hash::make('super123'), 'role' => 'superadmin']
        );

        // 2. Injection des chambres avec les 3 types de Triples bien séparés issus du PDF des tarifs
        $roomsData = [
            // Double Standard (110 000 Ariary)
            ['type' => 'Chambre Double', 'model' => 'Standard', 'price' => 110000, 'numbers' => ['01', '03', '05', '11', '14', '16', '21', '23', '24', '26', '27']],
            // Double Standard à prix fixe (95 000 Ariary)
            ['type' => 'Chambre Double', 'model' => 'Standard (état dégradé)', 'price' => 95000, 'fixed' => true, 'numbers' => ['02', '25']],
            // Double Supérieure (125 000 Ariary)
            ['type' => 'Chambre Double', 'model' => 'Supérieure', 'price' => 125000, 'numbers' => ['101', '105', '106', '201', '202', '301', '305', '306', '307']],
            // Twin Standard (100 000 Ariary)
            ['type' => 'Chambre Twin', 'model' => 'Standard', 'price' => 100000, 'numbers' => ['12', '15']],
            // Twin Supérieure (125 000 Ariary)
            ['type' => 'Chambre Twin', 'model' => 'Supérieure', 'price' => 125000, 'numbers' => ['206']],
            
            // Les 3 catégories de Triples distinctes :
            // Triple Standard : 1 grand lit et 1 petit lit (135 000 Ariary)
            ['type' => 'Chambre Triple', 'model' => 'Standard (1 Grand + 1 Petit)', 'price' => 135000, 'numbers' => ['22']],
            // Triple Supérieure : 3 petits lits (155 000 Ariary)
            ['type' => 'Chambre Triple', 'model' => 'Supérieure (3 Petits lits)', 'price' => 155000, 'numbers' => ['102', '205']],
            // Triple Supérieure : 1 grand lit king size et 1 petit lit (155 000 Ariary)
            ['type' => 'Chambre Triple', 'model' => 'Supérieure (1 King + 1 Petit)', 'price' => 155000, 'numbers' => ['302']],
            
            // Familiale Standard (175 000 Ariary)
            ['type' => 'Chambre Familiale', 'model' => 'Standard', 'price' => 175000, 'numbers' => ['04', '17']],
            // Familiale Supérieure (205 000 Ariary)
            ['type' => 'Chambre Familiale', 'model' => 'Supérieure', 'price' => 205000, 'numbers' => ['103', '104', '203', '204', '303', '304']],
        ];

        $insertedRooms = [];
        foreach ($roomsData as $group) {
            foreach ($group['numbers'] as $number) {
                $id = DB::table('rooms')->insertGetId([
                    'room_number' => $number,
                    'type' => $group['type'],
                    'model' => $group['model'],
                    'base_price_ariary' => $group['price'],
                    'is_fixed_price' => $group['fixed'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $insertedRooms[] = ['id' => $id, 'price' => $group['price'], 'type' => $group['type']];
            }
        }

        // 3. Génération d'un historique massif (Saisonnalité forte : juillet, août, septembre avec courbe progressive)
        $names = ['Rova', 'Rakoto', 'Naivo', 'Nisha', 'Faly', 'Koloina', 'Sitraka', 'Mamy'];
        
        // On injecte 2000 réservations pour saturer les mois d'été
        for ($i = 0; $i < 2000; $i++) {
            // Attribution d'une date sur les 365 derniers jours
            $checkIn = Carbon::now()->subDays(rand(1, 365));
            $month = $checkIn->month;
            
            $roomsCount = rand(1, 2);
            $seasonMultiplier = 1.0;
            
            if ($month == 6) {
                $seasonMultiplier = 1.15;
                $roomsCount = rand(2, 4);
            } elseif ($month == 7 || $month == 8) {
                $seasonMultiplier = 1.60; 
                $roomsCount = rand(5, 12); // Saturation massive en Juillet/Août
            } elseif ($month == 9) {
                $seasonMultiplier = 1.30;
                $roomsCount = rand(3, 8);
            } elseif ($month == 10) {
                $seasonMultiplier = 1.10;
                $roomsCount = rand(1, 4);
            }

            // Pour s'assurer qu'il y a plus de trafic en été, on peut parfois relancer le dé si ce n'est pas l'été
            // mais on a déjà forcé 1200 résas, donc la densité des chambres par résa suffira à saturer l'été.

            // Durée de séjour réaliste : 95% une nuit, 5% deux nuits
            $dice = rand(1, 100);
            $length = ($dice <= 95) ? 1 : 2;
            $checkOut = (clone $checkIn)->addDays($length);

            // Sources réalistes
            $sourceDice = rand(1, 10);
            if ($sourceDice <= 5) { $source = 'Appel'; }
            elseif ($sourceDice <= 8) { $source = 'Mail'; }
            else { $source = 'Booking'; }

            $reference = 'RES-' . strtoupper(bin2hex(random_bytes(3)));
            $resId = DB::table('reservations')->insertGetId([
                'client_name' => $names[array_rand($names)] . ' ' . rand(10, 99),
                'client_phone' => sprintf('034%07d', rand(0, 9999999)),
                'booking_reference' => $reference,
                'user_id' => $receptionist->id,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'status' => 'arrive',
                'source' => $source,
                'created_at' => $checkIn,
                'updated_at' => $checkIn
            ]);

            // Assignation des chambres via notre table pivot
            shuffle($insertedRooms);
            for ($j = 0; $j < $roomsCount; $j++) {
                if (!isset($insertedRooms[$j])) break;
                
                $price = $insertedRooms[$j]['price'];
                if ($source === 'Booking') {
                    $price = intval(32.5 * 5000); 
                } else {
                    $price = intval($price * $seasonMultiplier);
                }

                DB::table('booking_room')->insert([
                    'reservation_id' => $resId,
                    'room_id' => $insertedRooms[$j]['id'],
                    'price_snapshot_ariary' => $price,
                    'created_at' => $checkIn,
                    'updated_at' => $checkIn
                ]);
            }
        }

        // 4. Simulation de réservations pour DEMAIN (Saturation pour test)
        $tomorrow = Carbon::tomorrow();
        for ($k = 0; $k < 15; $k++) { // 15 réservations pour demain
            $reference = 'RES-' . strtoupper(bin2hex(random_bytes(3)));
            $resId = DB::table('reservations')->insertGetId([
                'client_name' => 'Client Demain ' . $k,
                'client_phone' => sprintf('03300000%02d', $k),
                'booking_reference' => $reference,
                'user_id' => $receptionist->id,
                'check_in_date' => $tomorrow->toDateString(),
                'check_out_date' => $tomorrow->copy()->addDay()->toDateString(),
                'status' => 'en_attente',
                'source' => 'Appel',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::table('booking_room')->insert([
                'reservation_id' => $resId,
                'room_id' => $insertedRooms[$k % 35]['id'],
                'price_snapshot_ariary' => $insertedRooms[$k % 35]['price'],
            ]);
        }

        // 5. Simulation de 4 réservations pour AUJOURD'HUI
        $today = Carbon::today();
        $todayNames = ['Rova Today', 'Rakoto Today', 'Naivo Today', 'Nisha Today'];
        for ($k = 0; $k < 4; $k++) {
            $reference = 'RES-' . strtoupper(bin2hex(random_bytes(3)));
            $resId = DB::table('reservations')->insertGetId([
                'client_name' => $todayNames[$k],
                'client_phone' => sprintf('03400000%02d', $k),
                'booking_reference' => $reference,
                'user_id' => $receptionist->id,
                'check_in_date' => $today->toDateString(),
                'check_out_date' => $today->copy()->addDay()->toDateString(),
                'status' => 'arrive',
                'source' => 'Appel',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::table('booking_room')->insert([
                'reservation_id' => $resId,
                'room_id' => $insertedRooms[($k + 20) % 35]['id'],
                'price_snapshot_ariary' => $insertedRooms[($k + 20) % 35]['price'],
            ]);
        }
    }
}
