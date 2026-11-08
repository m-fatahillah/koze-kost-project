<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateDummyProperties extends Command
{
    protected $signature = 'property:generate-dummy
        {count=750 : Jumlah properti yang dibuat (disarankan 500-1000)}
        {--owners=10 : Jumlah minimal akun Owner yang dipakai sebagai pemilik}
        {--fresh : Hapus seluruh properti dummy sebelumnya sebelum generate}';

    protected $description = 'Generate ratusan properti dummy di area Jawa Timur (prioritas Malang & Surabaya) lengkap dengan gambar, fasilitas, dan koordinat.';

    /**
     * Pusat kota di Jawa Timur. Bobot menentukan proporsi properti yang
     * tersebar di kota tersebut. Malang & Surabaya diberi bobot terbesar.
     *
     * @var array<int, array{name:string, lat:float, lng:float, weight:int, districts:array<int,string>}>
     */
    private array $cities = [
        [
            'name' => 'Malang', 'lat' => -7.9666, 'lng' => 112.6326, 'weight' => 30,
            'districts' => ['Lowokwaru', 'Klojen', 'Blimbing', 'Sukun', 'Kedungkandang', 'Dinoyo', 'Tlogomas', 'Sumbersari', 'Merjosari', 'Ketawanggede', 'Sukarno Hatta', 'Jatimulyo'],
        ],
        [
            'name' => 'Surabaya', 'lat' => -7.2575, 'lng' => 112.7521, 'weight' => 28,
            'districts' => ['Gubeng', 'Wonokromo', 'Rungkut', 'Sukolilo', 'Tegalsari', 'Mulyorejo', 'Wiyung', 'Lakarsantri', 'Keputih', 'Darmo', 'Ketintang', 'Manyar'],
        ],
        [
            'name' => 'Kota Batu', 'lat' => -7.8671, 'lng' => 112.5239, 'weight' => 6,
            'districts' => ['Sisir', 'Songgokerto', 'Junrejo', 'Bumiaji', 'Temas'],
        ],
        [
            'name' => 'Sidoarjo', 'lat' => -7.4478, 'lng' => 112.7183, 'weight' => 6,
            'districts' => ['Waru', 'Taman', 'Candi', 'Buduran', 'Gedangan', 'Sukodono'],
        ],
        [
            'name' => 'Gresik', 'lat' => -7.1556, 'lng' => 112.6516, 'weight' => 4,
            'districts' => ['Kebomas', 'Manyar', 'Driyorejo', 'Menganti'],
        ],
        [
            'name' => 'Mojokerto', 'lat' => -7.4722, 'lng' => 112.4338, 'weight' => 3,
            'districts' => ['Magersari', 'Prajurit Kulon', 'Sooko'],
        ],
        [
            'name' => 'Kediri', 'lat' => -7.8480, 'lng' => 112.0178, 'weight' => 3,
            'districts' => ['Mojoroto', 'Kota', 'Pesantren'],
        ],
        [
            'name' => 'Jember', 'lat' => -8.1724, 'lng' => 113.7003, 'weight' => 3,
            'districts' => ['Sumbersari', 'Patrang', 'Kaliwates', 'Tegal Boto'],
        ],
        [
            'name' => 'Banyuwangi', 'lat' => -8.2192, 'lng' => 114.3691, 'weight' => 2,
            'districts' => ['Giri', 'Kalipuro', 'Glagah'],
        ],
        [
            'name' => 'Madiun', 'lat' => -7.6298, 'lng' => 111.5239, 'weight' => 2,
            'districts' => ['Taman', 'Manguharjo', 'Kartoharjo'],
        ],
        [
            'name' => 'Pasuruan', 'lat' => -7.6453, 'lng' => 112.9075, 'weight' => 2,
            'districts' => ['Gadingrejo', 'Bugul Kidul', 'Purworejo'],
        ],
        [
            'name' => 'Probolinggo', 'lat' => -7.7543, 'lng' => 113.2159, 'weight' => 2,
            'districts' => ['Mayangan', 'Kanigaran', 'Kademangan'],
        ],
        [
            'name' => 'Blitar', 'lat' => -8.0954, 'lng' => 112.1609, 'weight' => 2,
            'districts' => ['Sananwetan', 'Kepanjenkidul', 'Sukorejo'],
        ],
        [
            'name' => 'Tulungagung', 'lat' => -8.0657, 'lng' => 111.9025, 'weight' => 2,
            'districts' => ['Kedungwaru', 'Boyolangu', 'Kauman'],
        ],
        [
            'name' => 'Jombang', 'lat' => -7.5460, 'lng' => 112.2330, 'weight' => 1,
            'districts' => ['Jombang', 'Diwek', 'Peterongan'],
        ],
        [
            'name' => 'Lamongan', 'lat' => -7.1167, 'lng' => 112.4167, 'weight' => 1,
            'districts' => ['Lamongan', 'Babat', 'Deket'],
        ],
        [
            'name' => 'Bojonegoro', 'lat' => -7.1502, 'lng' => 111.8817, 'weight' => 1,
            'districts' => ['Bojonegoro', 'Dander', 'Kapas'],
        ],
        [
            'name' => 'Nganjuk', 'lat' => -7.6056, 'lng' => 111.9024, 'weight' => 1,
            'districts' => ['Nganjuk', 'Berbek', 'Loceret'],
        ],
    ];

    /** @var array<int, string> */
    private array $namePrefixes = ['Kos', 'Wisma', 'Griya', 'Pondok', 'Rumah Kos', 'Hunian', 'Graha', 'Omah', 'Kos Eksklusif', 'Asrama'];

    /** @var array<int, string> */
    private array $nameThemes = ['Mentari', 'Asri', 'Sejahtera', 'Indah', 'Bahagia', 'Permai', 'Lestari', 'Harmoni', 'Bahari', 'Cendana', 'Anggrek', 'Melati', 'Mawar', 'Kenanga', 'Putra Mandiri', 'Bunda', 'Damai', 'Mulia', 'Surya', 'Cempaka', 'Nusantara', 'Pelangi', 'Bougenville', 'Kartini', 'Diponegoro', 'Brawijaya', 'Merdeka', 'Tirtasari'];

    /** @var array<int, string> */
    private array $images = ['img01.jpg', 'img02.jpg', 'img03.jpg', 'img04.jpg', 'img05.jpg'];

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        if ($count < 1) {
            $this->error('Jumlah properti harus minimal 1.');
            return self::FAILURE;
        }

        // Pastikan data acuan tersedia.
        $facilityIds = Facility::query()->pluck('id')->all();
        $propertyTypes = PropertyType::query()->pluck('id', 'name'); // ['Kos' => 1, ...]

        if (empty($facilityIds) || $propertyTypes->isEmpty()) {
            $this->error('Fasilitas atau tipe properti belum ada. Jalankan FacilitySeeder & PropertyTypeSeeder terlebih dahulu.');
            return self::FAILURE;
        }

        $imageDiskPaths = $this->prepareImages();
        if (empty($imageDiskPaths)) {
            $this->error('Gambar img01-img05 tidak ditemukan di public/images.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            // Properti dummy dikenali dari path gambarnya (properties/dummy/...),
            // bukan dari nama, agar nama tampil natural di UI.
            $deleted = Property::query()
                ->whereHas('images', fn ($q) => $q->where('image_url', 'like', 'properties/dummy/%'))
                ->delete();
            $this->warn("Menghapus {$deleted} properti dummy sebelumnya.");
        }

        $owners = $this->ensureOwners((int) $this->option('owners'));
        $ownerIds = $owners->pluck('id')->all();

        $this->info("Membuat {$count} properti dummy di Jawa Timur (prioritas Malang & Surabaya)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $weightedCities = $this->buildWeightedCityPool();
        $now = now();
        $created = 0;

        // Proses per-batch agar hemat memori & cepat.
        foreach (array_chunk(range(1, $count), 200) as $chunk) {
            DB::transaction(function () use ($chunk, $weightedCities, $propertyTypes, $facilityIds, $ownerIds, $imageDiskPaths, $now, &$created, $bar) {
                $imageRows = [];
                $facilityRows = [];

                foreach ($chunk as $i) {
                    $city = $weightedCities[array_rand($weightedCities)];
                    $district = $city['districts'][array_rand($city['districts'])];
                    $typeName = $this->randomType();
                    $gender = $this->randomGender();
                    $priceType = $typeName === 'Vila' ? 'daily' : (mt_rand(1, 10) === 1 ? 'yearly' : 'monthly');
                    $price = $this->randomPrice($typeName, $priceType);
                    $totalRooms = mt_rand(6, 40);
                    $availableRooms = mt_rand(0, $totalRooms);

                    $property = Property::query()->create([
                        'owner_id' => $ownerIds[array_rand($ownerIds)],
                        'name' => $this->randomName($district),
                        'description' => $this->randomDescription($typeName, $gender, $district, $city['name']),
                        'property_type_id' => $propertyTypes[$typeName],
                        'gender_restriction' => $gender,
                        'price' => $price,
                        'price_type' => $priceType,
                        'address' => "Jl. {$this->randomStreet()} No. " . mt_rand(1, 200) . ", {$district}, {$city['name']}, Jawa Timur",
                        'latitude' => $city['lat'] + (mt_rand(-450, 450) / 10000),
                        'longitude' => $city['lng'] + (mt_rand(-450, 450) / 10000),
                        'total_rooms' => $totalRooms,
                        'available_rooms' => $availableRooms,
                        'is_featured' => mt_rand(1, 12) === 1,
                        'status' => 'active',
                    ]);

                    // 3-5 foto acak, foto pertama = primary.
                    $shots = $imageDiskPaths;
                    shuffle($shots);
                    $shots = array_slice($shots, 0, mt_rand(3, count($shots)));
                    foreach ($shots as $idx => $path) {
                        $imageRows[] = [
                            'property_id' => $property->id,
                            'image_url' => $path,
                            'is_primary' => $idx === 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // 4-7 fasilitas acak.
                    $picked = (array) array_rand(array_flip($facilityIds), min(mt_rand(4, 7), count($facilityIds)));
                    foreach ($picked as $facilityId) {
                        $facilityRows[] = [
                            'property_id' => $property->id,
                            'facility_id' => $facilityId,
                        ];
                    }

                    $created++;
                    $bar->advance();
                }

                if ($imageRows) {
                    DB::table('property_images')->insert($imageRows);
                }
                if ($facilityRows) {
                    DB::table('property_facility')->insert($facilityRows);
                }
            });
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai. {$created} properti dummy berhasil dibuat.");
        $this->line('Properti dummy dikenali dari gambarnya (properties/dummy/...). Generate ulang bersih dengan menambahkan opsi --fresh.');

        return self::SUCCESS;
    }

    /**
     * Salin img01-img05 ke storage publik agar tampil lewat URL /storage/...
     *
     * @return array<int, string> daftar path relatif disk public.
     */
    private function prepareImages(): array
    {
        $destDir = storage_path('app/public/properties/dummy');
        File::ensureDirectoryExists($destDir);

        $paths = [];
        foreach ($this->images as $image) {
            $source = public_path('images/' . $image);
            if (!File::exists($source)) {
                continue;
            }
            $dest = $destDir . DIRECTORY_SEPARATOR . $image;
            if (!File::exists($dest)) {
                File::copy($source, $dest);
            }
            $paths[] = 'properties/dummy/' . $image;
        }

        return $paths;
    }

    /**
     * Pastikan ada sejumlah akun Owner; buat akun dummy bila kurang.
     */
    private function ensureOwners(int $minimum): \Illuminate\Support\Collection
    {
        $owners = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Owner'))
            ->get(['id']);

        $needed = max(0, $minimum - $owners->count());
        for ($i = 0; $i < $needed; $i++) {
            $owner = User::firstOrCreate(
                ['email' => 'owner.dummy' . ($i + 1) . '@koze.test'],
                [
                    'name' => 'Owner Dummy ' . ($i + 1),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $owner->assignRole('Owner');
            $owners->push($owner);
        }

        return $owners;
    }

    /**
     * Bangun pool kota berdasarkan bobot (kota berbobot tinggi muncul lebih sering).
     *
     * @return array<int, array{name:string, lat:float, lng:float, districts:array<int,string>}>
     */
    private function buildWeightedCityPool(): array
    {
        $pool = [];
        foreach ($this->cities as $city) {
            for ($i = 0; $i < $city['weight']; $i++) {
                $pool[] = $city;
            }
        }
        return $pool;
    }

    private function randomType(): string
    {
        // Mayoritas Kos, sisanya bervariasi.
        return [
            'Kos', 'Kos', 'Kos', 'Kos', 'Kos', 'Kos',
            'Kontrakan', 'Kontrakan',
            'Apartemen', 'Vila',
        ][array_rand(range(0, 9))];
    }

    private function randomGender(): string
    {
        return ['putra', 'putri', 'campur'][array_rand([0, 1, 2])];
    }

    private function randomPrice(string $type, string $priceType): int
    {
        if ($priceType === 'daily') {
            return mt_rand(15, 120) * 10000; // 150rb - 1.2jt / hari
        }
        if ($priceType === 'yearly') {
            return mt_rand(900, 4500) * 10000; // 9jt - 45jt / tahun
        }

        return match ($type) {
            'Apartemen' => mt_rand(200, 600) * 10000,   // 2jt - 6jt
            'Kontrakan' => mt_rand(120, 400) * 10000,   // 1.2jt - 4jt
            default => mt_rand(70, 350) * 10000,        // 700rb - 3.5jt
        };
    }

    private function randomName(string $district): string
    {
        $prefix = $this->namePrefixes[array_rand($this->namePrefixes)];
        $theme = $this->nameThemes[array_rand($this->nameThemes)];

        return mt_rand(0, 1) === 0
            ? "{$prefix} {$theme}"
            : "{$prefix} {$theme} {$district}";
    }

    private function randomDescription(string $type, string $gender, string $district, string $city): string
    {
        $genderLabel = match ($gender) {
            'putra' => 'khusus putra',
            'putri' => 'khusus putri',
            default => 'campur (putra & putri)',
        };

        $highlights = [
            'lokasi strategis dekat kampus dan pusat keramaian',
            'akses mudah ke jalan utama dan transportasi umum',
            'lingkungan aman, bersih, dan nyaman',
            'dekat minimarket, warung makan, dan fasilitas umum',
            'cocok untuk mahasiswa maupun karyawan',
            'suasana tenang dengan area parkir luas',
        ];
        shuffle($highlights);

        return "{$type} {$genderLabel} di {$district}, {$city}. "
            . ucfirst($highlights[0]) . ', ' . $highlights[1] . '. '
            . 'Kamar terawat dengan fasilitas memadai dan pengelola yang responsif.';
    }

    private function randomStreet(): string
    {
        $streets = ['Mawar', 'Melati', 'Anggrek', 'Sudirman', 'Diponegoro', 'Ahmad Yani', 'Gajah Mada', 'Veteran', 'Soekarno Hatta', 'Raya Tlogomas', 'Bendungan Sutami', 'Watugong', 'Kalpataru', 'Sigura-gura', 'Bunga Coklat', 'Joyo Suko', 'Cengger Ayam', 'Borobudur'];
        return $streets[array_rand($streets)];
    }
}
