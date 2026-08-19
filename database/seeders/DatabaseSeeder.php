<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Pet;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Owners
        $supri = User::create([
            'name' => 'Pak Supri',
            'email' => 'supri@easykos.com',
            'phone' => '081234567801',
            'role' => 'owner',
            'password' => Hash::make('password'),
        ]);

        $ratna = User::create([
            'name' => 'Ibu Ratna',
            'email' => 'ratna@easykos.com',
            'phone' => '081234567802',
            'role' => 'owner',
            'password' => Hash::make('password'),
        ]);

        // 2. Seed Tenants
        $budi = User::create([
            'name' => 'Budi Tabuti',
            'email' => 'budi@easykos.com',
            'phone' => '081234567803',
            'role' => 'tenant',
            'password' => Hash::make('password'),
        ]);

        $siti = User::create([
            'name' => 'Siti Aisyah',
            'email' => 'siti@easykos.com',
            'phone' => '081234567804',
            'role' => 'tenant',
            'password' => Hash::make('password'),
        ]);

        $rizky = User::create([
            'name' => 'Rizky Pratama',
            'email' => 'rizky@easykos.com',
            'phone' => '081234567805',
            'role' => 'tenant',
            'password' => Hash::make('password'),
        ]);

        // 3. Seed Properties
        $prop1 = Property::create([
            'user_id' => $supri->id,
            'name' => 'Kos Mawar Indah',
            'type' => 'Standard Single',
            'category' => 'single',
            'address' => 'Jl. Gejayan No. 12, Sleman, Yogyakarta',
            'price' => 1500000,
            'rating' => 4.8,
            'reviews_count' => 24,
            'image' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80'
            ],
            'facilities' => ['WiFi 50Mbps', 'Kamar Mandi Dalam', 'Kasur Springbed', 'Lemari Pakaian', 'Dapur Bersama', 'Parkir Motor'],
            'description' => 'Kos strategis dekat kampus UNY & UGM. Lingkungan tenang, aman, dan nyaman dengan akses 24 jam.',
            'status' => 'Available',
            'total_rooms' => 5,
            'occupied_rooms' => 4,
        ]);

        $prop2 = Property::create([
            'user_id' => $supri->id,
            'name' => 'Kos Mawar Indah',
            'type' => 'Deluxe AC',
            'category' => 'deluxe',
            'address' => 'Jl. Gejayan No. 12, Sleman, Yogyakarta',
            'price' => 2100000,
            'rating' => 4.9,
            'reviews_count' => 19,
            'image' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80'
            ],
            'facilities' => ['AC', 'WiFi Cepat', 'Kamar Mandi Dalam', 'Water Heater', 'Kasur Queen Bed', 'Parkir Mobil'],
            'description' => 'Kamar Deluxe luas dengan fasilitas lengkap dan AC sejuk.',
            'status' => 'Full',
            'total_rooms' => 3,
            'occupied_rooms' => 3,
        ]);

        $prop3 = Property::create([
            'user_id' => $ratna->id,
            'name' => 'Wisma Asri Exclusive',
            'type' => 'Deluxe AC',
            'category' => 'deluxe',
            'address' => 'Seturan, Depok, Sleman, Yogyakarta',
            'price' => 2200000,
            'rating' => 4.9,
            'reviews_count' => 42,
            'image' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80'
            ],
            'facilities' => ['AC Dingin', 'WiFi Cepat', 'Kamar Mandi Dalam', 'Water Heater', 'Kasur Queen Bed', 'Parkir Mobil & Motor'],
            'description' => 'Kos eksklusif fully furnished dengan AC dan water heater. Cocok untuk mahasiswa dan pekerja profesional.',
            'status' => 'Available',
            'total_rooms' => 8,
            'occupied_rooms' => 6,
        ]);

        $prop4 = Property::create([
            'user_id' => $supri->id,
            'name' => 'Kos Residence 88',
            'type' => 'Premium Suite',
            'category' => 'premium',
            'address' => 'Pogung Dalangan, Mlati, Sleman, Yogyakarta',
            'price' => 2800000,
            'rating' => 5.0,
            'reviews_count' => 18,
            'image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80'
            ],
            'facilities' => ['AC', 'Smart TV 43 Inch', 'WiFi 100Mbps', 'Kamar Mandi Dalam', 'Balkon Pribadi', 'Security 24 Jam'],
            'description' => 'Suite kos modern dengan balkon pribadi, Smart TV, dan pembersihan kamar berkala. Security 24 jam.',
            'status' => 'Available',
            'total_rooms' => 6,
            'occupied_rooms' => 5,
        ]);

        $prop5 = Property::create([
            'user_id' => $ratna->id,
            'name' => 'Griya Putri Cantika',
            'type' => 'Double Bed',
            'category' => 'double',
            'address' => 'Gondokusuman, Kota Yogyakarta',
            'price' => 1800000,
            'rating' => 4.7,
            'reviews_count' => 31,
            'image' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80'
            ],
            'facilities' => ['WiFi', 'Kamar Mandi Dalam', 'Kasur Double', 'Lemari Dua Pintu', 'CCTV 24 Jam', 'Dapur & Kulkas'],
            'description' => 'Khusus putri. Dekat dengan stasiun Lempuyangan dan Malioboro. Bersih, tertib, dan dilengkapi CCTV.',
            'status' => 'Available',
            'total_rooms' => 10,
            'occupied_rooms' => 8,
        ]);

        // 4. Seed Virtual Pet with timestamps (8h feeding cycle & 5 days happiness cycle)
        Pet::create([
            'user_id' => $budi->id,
            'name' => 'Moni',
            'level' => 5,
            'exp' => 75,
            'max_exp' => 100,
            'health' => 100,
            'hunger' => 85,
            'happiness' => 95,
            'mood' => 'happy',
            'last_fed_at' => Carbon::now()->subHours(1),
            'last_played_at' => Carbon::now()->subHours(2),
        ]);

        Pet::create([
            'user_id' => $supri->id,
            'name' => 'Koko',
            'level' => 3,
            'exp' => 40,
            'max_exp' => 100,
            'health' => 100,
            'hunger' => 90,
            'happiness' => 90,
            'mood' => 'happy',
            'last_fed_at' => Carbon::now()->subMinutes(45),
            'last_played_at' => Carbon::now()->subHours(3),
        ]);

        Pet::create([
            'user_id' => $ratna->id,
            'name' => 'Chiko',
            'level' => 2,
            'exp' => 20,
            'max_exp' => 100,
            'health' => 100,
            'hunger' => 95,
            'happiness' => 92,
            'mood' => 'happy',
            'last_fed_at' => Carbon::now()->subMinutes(20),
            'last_played_at' => Carbon::now()->subHours(4),
        ]);

        Pet::create([
            'user_id' => $siti->id,
            'name' => 'Moni',
            'level' => 4,
            'exp' => 50,
            'max_exp' => 100,
            'health' => 100,
            'hunger' => 70,
            'happiness' => 88,
            'mood' => 'happy',
            'last_fed_at' => Carbon::now()->subHours(2),
            'last_played_at' => Carbon::now()->subHours(5),
        ]);

        Pet::create([
            'user_id' => $rizky->id,
            'name' => 'Moni',
            'level' => 1,
            'exp' => 10,
            'max_exp' => 100,
            'health' => 100,
            'hunger' => 100,
            'happiness' => 100,
            'mood' => 'happy',
            'last_fed_at' => Carbon::now(),
            'last_played_at' => Carbon::now(),
        ]);

        // 5. Seed Real Initial Chat Messages
        Message::create([
            'sender_id' => $budi->id,
            'receiver_id' => $supri->id,
            'property_id' => $prop1->id,
            'message' => 'Halo Pak Supri, apakah kamar tipe Standard Single masih tersedia untuk bulan depan?',
            'created_at' => Carbon::now()->subMinutes(25),
        ]);

        Message::create([
            'sender_id' => $supri->id,
            'receiver_id' => $budi->id,
            'property_id' => $prop1->id,
            'message' => 'Halo Mas Budi! Masih ada 1 kamar lagi ya. Bisa langsung survey atau booking lewat aplikasi EasyKos.',
            'created_at' => Carbon::now()->subMinutes(20),
        ]);

        Message::create([
            'sender_id' => $siti->id,
            'receiver_id' => $ratna->id,
            'property_id' => $prop3->id,
            'message' => 'Selamat pagi Ibu Ratna, apakah kos Wisma Asri boleh pasang kulkas pribadi?',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        Message::create([
            'sender_id' => $ratna->id,
            'receiver_id' => $siti->id,
            'property_id' => $prop3->id,
            'message' => 'Boleh sekali Mba Siti, ada biaya tambahan listrik Rp 50.000/bulan ya.',
            'created_at' => Carbon::now()->subHours(1),
        ]);

        // 6. Seed Bookings
        Booking::create([
            'user_id' => $budi->id,
            'property_id' => $prop1->id,
            'duration_months' => 1,
            'total_price' => 1500000,
            'discount_amount' => 0,
            'payment_method' => 'ovo',
            'voucher_code' => null,
            'status' => 'Active',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        Booking::create([
            'user_id' => $siti->id,
            'property_id' => $prop3->id,
            'duration_months' => 1,
            'total_price' => 2100000,
            'discount_amount' => 100000,
            'payment_method' => 'qris',
            'voucher_code' => 'VETPET100',
            'status' => 'Active',
            'start_date' => Carbon::now()->subMonth(),
        ]);
    }
}
