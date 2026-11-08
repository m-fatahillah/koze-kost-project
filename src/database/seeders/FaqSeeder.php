<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'pertanyaan' => 'Bagaimana cara memesan?',
                'jawaban' => 'Cukup pilih kos yang kamu sukai, klik tombol "Booking Sekarang!" di halaman detail properti, lengkapi data diri, dan lakukan pembayaran uang muka (DP) sesuai instruksi untuk mengamankan unitmu.',
            ],
            [
                'pertanyaan' => 'Apakah ada biaya tambahan?',
                'jawaban' => 'Tergantung pada kos. Beberapa kos menggratiskan listrik, ada juga yang tidak. Detailnya bisa dilihat di fasilitas.',
            ],
            [
                'pertanyaan' => 'Bagaimana sistem pembayarannya?',
                'jawaban' => 'Anda bisa membayar via transfer bank, e-wallet, atau kartu kredit.',
            ],
            [
                'pertanyaan' => 'Bagaimana jika saya ingin membatalkan pesanan?',
                'jawaban' => 'Pembatalan dapat dilakukan sesuai dengan kebijakan masing-masing pemilik kos.',
            ],
            [
                'pertanyaan' => 'Apakah ada layanan survey lokasi?',
                'jawaban' => 'Ya, Anda dapat mengajukan jadwal survey ke pemilik kos melalui fitur chat kami.',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
