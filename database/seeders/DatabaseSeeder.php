<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguarded(function (): void {
            $now = now();

            foreach ($this->users() as $user) {
                User::create(array_merge($user, [
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            // foreach ($this->events() as $event) {
            //     Event::create(array_merge($event, [
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]));
            // }

            // foreach ($this->photos() as $photo) {
            //     Photo::create(array_merge($photo, [
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]));

            //     $this->putDummyPhoto($photo['original_path']);
            //     $this->putDummyPhoto($photo['watermarked_path']);
            // }

            // foreach ($this->orders() as $order) {
            //     Order::create(array_merge($order, [
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]));
            // }

            // foreach ($this->orderItems() as $item) {
            //     OrderItem::create(array_merge($item, [
            //         'created_at' => $now,
            //     ]));
            // }

            // foreach ($this->transactions() as $transaction) {
            //     Transaction::create(array_merge($transaction, [
            //         'payload' => [
            //             'transaction_status' => $transaction['status'],
            //             'order_id' => $transaction['midtrans_order_id'],
            //             'gross_amount' => (string) $transaction['gross_amount'],
            //             'payment_type' => $transaction['payment_type'],
            //             'fraud_status' => $transaction['fraud_status'],
            //         ],
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]));
            // }

            // foreach ($this->settings() as $setting) {
            //     Setting::create(array_merge($setting, [
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]));
            // }
        });
    }

    private function users(): array
    {
        return [
            ['id' => 1, 'name' => 'Super Admin', 'email' => 'superadmin@snaporia.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true],
            ['id' => 2, 'name' => 'Arka Visual', 'email' => 'arka@snaporia.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true],
            ['id' => 3, 'name' => 'Lensa Cerita Studio', 'email' => 'lensa@snaporia.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true],
            ['id' => 4, 'name' => 'Momentika Photo', 'email' => 'momentika@snaporia.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true],
            ['id' => 5, 'name' => 'Rani Amelia', 'email' => 'rani@example.test', 'password' => 'password', 'role' => 'visitor', 'is_active' => true],
            ['id' => 6, 'name' => 'Dimas Pratama', 'email' => 'dimas@example.test', 'password' => 'password', 'role' => 'visitor', 'is_active' => true],
            ['id' => 7, 'name' => 'Sinta Maharani', 'email' => 'sinta@example.test', 'password' => 'password', 'role' => 'visitor', 'is_active' => true],
            ['id' => 8, 'name' => 'Bima Wardana', 'email' => 'bima@example.test', 'password' => 'password', 'role' => 'visitor', 'is_active' => true],
            ['id' => 9, 'name' => 'Visitor Nonaktif', 'email' => 'inactive@example.test', 'password' => 'password', 'role' => 'visitor', 'is_active' => false],
        ];
    }

    private function events(): array
    {
        return [
            ['id' => 1, 'admin_id' => 2, 'name' => 'Wisuda Universitas Nusantara 2026', 'description' => 'Dokumentasi prosesi wisuda dan sesi keluarga.', 'date' => '2026-03-14', 'location' => 'Balai Kartini, Jakarta', 'price_per_photo' => 25000.00, 'price_package' => 175000.00, 'is_published' => true],
            ['id' => 2, 'admin_id' => 2, 'name' => 'Seminar Digital Creative 2026', 'description' => 'Foto pembicara, peserta, booth, dan suasana seminar.', 'date' => '2026-02-20', 'location' => 'Bandung Convention Center', 'price_per_photo' => 20000.00, 'price_package' => 120000.00, 'is_published' => true],
            ['id' => 3, 'admin_id' => 3, 'name' => 'Konser Senja Akustik', 'description' => 'Dokumentasi konser musik indoor.', 'date' => '2026-01-18', 'location' => 'Makassar Creative Hub', 'price_per_photo' => 30000.00, 'price_package' => 225000.00, 'is_published' => true],
            ['id' => 4, 'admin_id' => 3, 'name' => 'Lomba Tari Pelajar', 'description' => 'Dokumentasi panggung dan foto grup peserta.', 'date' => '2026-04-05', 'location' => 'Gedung Kesenian Surabaya', 'price_per_photo' => 18000.00, 'price_package' => 99000.00, 'is_published' => false],
            ['id' => 5, 'admin_id' => 4, 'name' => 'Wedding Nara & Galih', 'description' => 'Galeri private untuk contoh data event belum dipublikasikan.', 'date' => '2026-02-02', 'location' => 'Ubud, Bali', 'price_per_photo' => 35000.00, 'price_package' => 300000.00, 'is_published' => false],
        ];
    }

    private function photos(): array
    {
        return [
            ['id' => 1, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-001.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-001.jpg', 'filename' => 'wisuda-001.jpg', 'file_size' => 4821000, 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['id' => 2, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-002.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-002.jpg', 'filename' => 'wisuda-002.jpg', 'file_size' => 5012400, 'mime_type' => 'image/jpeg', 'sort_order' => 2],
            ['id' => 3, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-003.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-003.jpg', 'filename' => 'wisuda-003.jpg', 'file_size' => 4698800, 'mime_type' => 'image/jpeg', 'sort_order' => 3],
            ['id' => 4, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-004.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-004.jpg', 'filename' => 'wisuda-004.jpg', 'file_size' => 5300100, 'mime_type' => 'image/jpeg', 'sort_order' => 4],
            ['id' => 5, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-005.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-005.jpg', 'filename' => 'wisuda-005.jpg', 'file_size' => 4983000, 'mime_type' => 'image/jpeg', 'sort_order' => 5],
            ['id' => 6, 'event_id' => 1, 'original_path' => 'photos/original/2/1/wisuda-006.jpg', 'watermarked_path' => 'photos/watermarked/2/1/wisuda-006.jpg', 'filename' => 'wisuda-006.jpg', 'file_size' => 5124500, 'mime_type' => 'image/jpeg', 'sort_order' => 6],
            ['id' => 7, 'event_id' => 2, 'original_path' => 'photos/original/2/2/seminar-001.jpg', 'watermarked_path' => 'photos/watermarked/2/2/seminar-001.jpg', 'filename' => 'seminar-001.jpg', 'file_size' => 3214000, 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['id' => 8, 'event_id' => 2, 'original_path' => 'photos/original/2/2/seminar-002.jpg', 'watermarked_path' => 'photos/watermarked/2/2/seminar-002.jpg', 'filename' => 'seminar-002.jpg', 'file_size' => 3442000, 'mime_type' => 'image/jpeg', 'sort_order' => 2],
            ['id' => 9, 'event_id' => 2, 'original_path' => 'photos/original/2/2/seminar-003.jpg', 'watermarked_path' => 'photos/watermarked/2/2/seminar-003.jpg', 'filename' => 'seminar-003.jpg', 'file_size' => 3660000, 'mime_type' => 'image/jpeg', 'sort_order' => 3],
            ['id' => 10, 'event_id' => 2, 'original_path' => 'photos/original/2/2/seminar-004.jpg', 'watermarked_path' => 'photos/watermarked/2/2/seminar-004.jpg', 'filename' => 'seminar-004.jpg', 'file_size' => 3581000, 'mime_type' => 'image/jpeg', 'sort_order' => 4],
            ['id' => 11, 'event_id' => 3, 'original_path' => 'photos/original/3/3/konser-001.jpg', 'watermarked_path' => 'photos/watermarked/3/3/konser-001.jpg', 'filename' => 'konser-001.jpg', 'file_size' => 6125000, 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['id' => 12, 'event_id' => 3, 'original_path' => 'photos/original/3/3/konser-002.jpg', 'watermarked_path' => 'photos/watermarked/3/3/konser-002.jpg', 'filename' => 'konser-002.jpg', 'file_size' => 5887000, 'mime_type' => 'image/jpeg', 'sort_order' => 2],
            ['id' => 13, 'event_id' => 3, 'original_path' => 'photos/original/3/3/konser-003.jpg', 'watermarked_path' => 'photos/watermarked/3/3/konser-003.jpg', 'filename' => 'konser-003.jpg', 'file_size' => 6011000, 'mime_type' => 'image/jpeg', 'sort_order' => 3],
            ['id' => 14, 'event_id' => 3, 'original_path' => 'photos/original/3/3/konser-004.jpg', 'watermarked_path' => 'photos/watermarked/3/3/konser-004.jpg', 'filename' => 'konser-004.jpg', 'file_size' => 5773000, 'mime_type' => 'image/jpeg', 'sort_order' => 4],
            ['id' => 15, 'event_id' => 3, 'original_path' => 'photos/original/3/3/konser-005.jpg', 'watermarked_path' => 'photos/watermarked/3/3/konser-005.jpg', 'filename' => 'konser-005.jpg', 'file_size' => 6299000, 'mime_type' => 'image/jpeg', 'sort_order' => 5],
            ['id' => 16, 'event_id' => 4, 'original_path' => 'photos/original/3/4/tari-001.jpg', 'watermarked_path' => 'photos/watermarked/3/4/tari-001.jpg', 'filename' => 'tari-001.jpg', 'file_size' => 4219000, 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['id' => 17, 'event_id' => 4, 'original_path' => 'photos/original/3/4/tari-002.jpg', 'watermarked_path' => 'photos/watermarked/3/4/tari-002.jpg', 'filename' => 'tari-002.jpg', 'file_size' => 4388000, 'mime_type' => 'image/jpeg', 'sort_order' => 2],
            ['id' => 18, 'event_id' => 5, 'original_path' => 'photos/original/4/5/wedding-001.jpg', 'watermarked_path' => 'photos/watermarked/4/5/wedding-001.jpg', 'filename' => 'wedding-001.jpg', 'file_size' => 7350000, 'mime_type' => 'image/jpeg', 'sort_order' => 1],
            ['id' => 19, 'event_id' => 5, 'original_path' => 'photos/original/4/5/wedding-002.jpg', 'watermarked_path' => 'photos/watermarked/4/5/wedding-002.jpg', 'filename' => 'wedding-002.jpg', 'file_size' => 7484000, 'mime_type' => 'image/jpeg', 'sort_order' => 2],
        ];
    }

    private function orders(): array
    {
        return [
            ['id' => 1, 'user_id' => 5, 'order_code' => 'SNP-20260401-0001', 'type' => 'single', 'event_id' => 1, 'total_amount' => 50000.00, 'status' => 'paid', 'expires_at' => '2026-04-02 10:00:00', 'paid_at' => '2026-04-01 10:15:00'],
            ['id' => 2, 'user_id' => 6, 'order_code' => 'SNP-20260402-0002', 'type' => 'package', 'event_id' => 2, 'total_amount' => 120000.00, 'status' => 'paid', 'expires_at' => '2026-04-03 14:00:00', 'paid_at' => '2026-04-02 14:20:00'],
            ['id' => 3, 'user_id' => 7, 'order_code' => 'SNP-20260403-0003', 'type' => 'single', 'event_id' => 3, 'total_amount' => 30000.00, 'status' => 'pending', 'expires_at' => '2026-04-04 09:30:00', 'paid_at' => null],
            ['id' => 4, 'user_id' => 8, 'order_code' => 'SNP-20260404-0004', 'type' => 'package', 'event_id' => 1, 'total_amount' => 175000.00, 'status' => 'expired', 'expires_at' => '2026-04-05 11:00:00', 'paid_at' => null],
            ['id' => 5, 'user_id' => 5, 'order_code' => 'SNP-20260405-0005', 'type' => 'single', 'event_id' => 2, 'total_amount' => 20000.00, 'status' => 'failed', 'expires_at' => '2026-04-06 16:00:00', 'paid_at' => null],
        ];
    }

    private function orderItems(): array
    {
        return [
            ['id' => 1, 'order_id' => 1, 'photo_id' => 1, 'price' => 25000.00],
            ['id' => 2, 'order_id' => 1, 'photo_id' => 2, 'price' => 25000.00],
            ['id' => 3, 'order_id' => 2, 'photo_id' => 7, 'price' => 0.00],
            ['id' => 4, 'order_id' => 2, 'photo_id' => 8, 'price' => 0.00],
            ['id' => 5, 'order_id' => 2, 'photo_id' => 9, 'price' => 0.00],
            ['id' => 6, 'order_id' => 2, 'photo_id' => 10, 'price' => 0.00],
            ['id' => 7, 'order_id' => 3, 'photo_id' => 11, 'price' => 30000.00],
            ['id' => 8, 'order_id' => 4, 'photo_id' => 1, 'price' => 0.00],
            ['id' => 9, 'order_id' => 4, 'photo_id' => 2, 'price' => 0.00],
            ['id' => 10, 'order_id' => 4, 'photo_id' => 3, 'price' => 0.00],
            ['id' => 11, 'order_id' => 4, 'photo_id' => 4, 'price' => 0.00],
            ['id' => 12, 'order_id' => 4, 'photo_id' => 5, 'price' => 0.00],
            ['id' => 13, 'order_id' => 4, 'photo_id' => 6, 'price' => 0.00],
            ['id' => 14, 'order_id' => 5, 'photo_id' => 7, 'price' => 20000.00],
        ];
    }

    private function transactions(): array
    {
        return [
            ['id' => 1, 'order_id' => 1, 'midtrans_order_id' => 'MT-SNP-20260401-0001', 'midtrans_transaction_id' => 'trx-paid-0001', 'snap_token' => 'snap-token-paid-0001', 'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-paid-0001', 'payment_type' => 'bank_transfer', 'gross_amount' => 50000.00, 'status' => 'settlement', 'fraud_status' => 'accept', 'expires_at' => '2026-04-02 10:00:00'],
            ['id' => 2, 'order_id' => 2, 'midtrans_order_id' => 'MT-SNP-20260402-0002', 'midtrans_transaction_id' => 'trx-paid-0002', 'snap_token' => 'snap-token-paid-0002', 'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-paid-0002', 'payment_type' => 'qris', 'gross_amount' => 120000.00, 'status' => 'settlement', 'fraud_status' => 'accept', 'expires_at' => '2026-04-03 14:00:00'],
            ['id' => 3, 'order_id' => 3, 'midtrans_order_id' => 'MT-SNP-20260403-0003', 'midtrans_transaction_id' => null, 'snap_token' => 'snap-token-pending-0003', 'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-pending-0003', 'payment_type' => 'bank_transfer', 'gross_amount' => 30000.00, 'status' => 'pending', 'fraud_status' => null, 'expires_at' => '2026-04-04 09:30:00'],
            ['id' => 4, 'order_id' => 4, 'midtrans_order_id' => 'MT-SNP-20260404-0004', 'midtrans_transaction_id' => null, 'snap_token' => 'snap-token-expired-0004', 'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-expired-0004', 'payment_type' => 'gopay', 'gross_amount' => 175000.00, 'status' => 'expire', 'fraud_status' => null, 'expires_at' => '2026-04-05 11:00:00'],
            ['id' => 5, 'order_id' => 5, 'midtrans_order_id' => 'MT-SNP-20260405-0005', 'midtrans_transaction_id' => 'trx-failed-0005', 'snap_token' => 'snap-token-failed-0005', 'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-failed-0005', 'payment_type' => 'bank_transfer', 'gross_amount' => 20000.00, 'status' => 'failure', 'fraud_status' => null, 'expires_at' => '2026-04-06 16:00:00'],
        ];
    }

    private function settings(): array
    {
        return [
            ['id' => 1, 'key' => 'site_name', 'value' => 'Snaporia', 'description' => 'Nama aplikasi'],
            ['id' => 2, 'key' => 'site_tagline', 'value' => 'Find Your Moments.', 'description' => 'Tagline aplikasi'],
            ['id' => 3, 'key' => 'public_gallery_per_page', 'value' => '24', 'description' => 'Jumlah foto per halaman galeri publik'],
            ['id' => 4, 'key' => 'dashboard_table_per_page', 'value' => '20', 'description' => 'Jumlah data per halaman tabel dashboard'],
            ['id' => 5, 'key' => 'upload_max_file_size_mb', 'value' => '15', 'description' => 'Batas ukuran upload per file'],
            ['id' => 6, 'key' => 'upload_max_files_per_batch', 'value' => '50', 'description' => 'Batas jumlah file per batch upload'],
            ['id' => 7, 'key' => 'watermark_text', 'value' => 'Snaporia', 'description' => 'Teks watermark default'],
            ['id' => 8, 'key' => 'watermark_opacity', 'value' => '25', 'description' => 'Opacity watermark dalam persen'],
            ['id' => 9, 'key' => 'payment_pending_hours', 'value' => '24', 'description' => 'Masa berlaku order pending'],
        ];
    }

    private function putDummyPhoto(string $path): void
    {
        $jpeg = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAVEAEBAAAAAAAAAAAAAAAAAAAAAf/aAAwDAQACEAMQAAABrA//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z';

        Storage::disk('local')->put($path, base64_decode($jpeg));
    }
}
