<?php

namespace Database\Seeders;

use App\Models\Trip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the Japan trip exactly as it appears in docs/reference/japan-trip.html.
 * The data was extracted from the prototype once and is hard-coded here.
 */
class TripSeeder extends Seeder
{
    public const TRIP_NAME = 'Japan, 21 Nov – 4 Dec 2026';

    /**
     * Trip days from the prototype. Activity tuple: [time, name, transport, meal, ticket, notes, places, halal].
     *
     * @var list<array{day: int, date: string, city: string, title: string, activities: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: list<string>, 7?: int}>}>
     */
    private const DAYS = [
        ['day' => 1, 'date' => '2026-11-21', 'city' => 'Osaka', 'title' => 'Arrive + Minami', 'activities' => [
            ['07:00', 'Arrive at KIX', '', '', '', 'Buy an IC card (ICOCA or Suica) for trains.', ['Kansai International Airport']],
            ['08:00', 'Train to Namba', 'Follow Nankai Line signs, take the Nankai Airport Express', '', '', '', ['Namba Station']],
            ['09:30', 'Drop luggage at Namba Station lockers', '', '', '', 'Airbnb can’t take early luggage. Hotel TBC, near Dotonbori/Namba.', ['Namba Station coin lockers']],
            ['10:30', 'Kuromon Market', '', 'Breakfast', '', 'Walk the whole market street. Try a fruit cup, hot tea from a vending machine, watch the seafood prep.', ['Kuromon Ichiba Market']],
            ['11:00', 'Osaka Castle (outside only)', 'Namba → Midosuji Line → Hommachi, change to Chuo Line → Tanimachi 4-chome', '', '', 'Better to go early in the morning.', ['Osaka Castle']],
            ['13:30', 'Best Halal Ramen Marhaba', '', 'Lunch', '', '', ['Halal Ramen Marhaba Osaka'], 1],
            ['15:30', 'Umeda Sky Building', 'Midosuji Line → Umeda', '', 'Klook', '', ['Umeda Sky Building']],
            ['17:30', 'Café SKY 40 + Floating Garden ⭐', '', '', '', 'Sunset 16:45. Come earlier if you want the sunset from Kuchu Teien Observatory.', ['Kuchu Teien Observatory']],
            ['18:45', 'Dotonbori walk + street food', 'Umeda → Midosuji Line → Namba', '', '', 'Walk both canal sides, watch street performers, Don Quijote for 30 min, buy souvenir snacks. Check halal for street food.', ['Dotonbori']],
            ['20:00', 'Halal Honolu Grande Shinsaibashi', '', 'Dinner', '', '', ['Honolu Grande Shinsaibashi'], 1],
            ['21:30', 'Back to hotel', '', '', '', '', []],
        ]],
        ['day' => 2, 'date' => '2026-11-22', 'city' => 'Osaka', 'title' => 'Shrines + old Osaka', 'activities' => [
            ['08:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['09:00', 'Sumiyoshi Taisha', 'Nankai Line → Sumiyoshitaisha Station', '', '', 'Walk the iconic Taiko Bridge.', ['Sumiyoshi Taisha']],
            ['11:30', 'Shinsekai + Tsutenkaku Tower', 'Shin-Imamiya / Ebisucho Station', '', '', 'Macam Myeongdong. Retro streets, capsule machine shop, street snacks (check halal).', ['Shinsekai', 'Tsutenkaku Tower']],
            ['13:00', 'Gyumon Tsutenkaku', '', 'Lunch', '', '', ['Gyumon Tsutenkaku Osaka'], 1],
            ['15:00', 'Amerikamura', '', '', '', 'Macam Petaling Street. Vintage stores, sit at a café if tired.', ['Amerikamura Osaka']],
            ['17:00', 'Umeda + HEP FIVE shopping', '', '', '', '', ['HEP FIVE']],
            ['19:00', 'Gyumon Dotonbori 2nd', '', 'Dinner', '', '', ['Gyumon Dotonbori 2nd'], 1],
            ['20:30', 'Shinsaibashi shopping street', '', '', '', '', ['Shinsaibashi-suji Shopping Street']],
        ]],
        ['day' => 3, 'date' => '2026-11-23', 'city' => 'Nara', 'title' => 'Deer + temples', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['08:00', 'Osaka → Nara', 'Namba → Kintetsu Nara Line → Kintetsu-Nara Station', '', '', '', ['Kintetsu-Nara Station']],
            ['09:00', 'Nara Park, Kasuga Taisha, Nigatsu-do, Todai-ji', '', '', '', 'Buy deer senbei. Feed the deer slowly.', ['Nara Park', 'Kasuga Taisha', 'Nigatsu-do', 'Todai-ji']],
            ['13:00', 'Nara Halal Restaurant & Ramen ⭐', '', 'Lunch', '', '', ['Nara Halal Restaurant'], 1],
            ['16:00', 'Nakatanidou mochi, Naramachi, Sarusawa Pond', '', '', '', '', ['Nakatanidou Nara', 'Naramachi', 'Sarusawa Pond']],
            ['18:00', 'Gyumon Nara ⭐⭐⭐', '', 'Dinner', '', '', ['Gyumon Nara'], 1],
            ['19:15', 'Return to Osaka', 'Kintetsu-Nara → Kintetsu Nara Line → Namba', '', '', '', []],
            ['20:30', 'Dotonbori night walk', '', '', '', '', ['Dotonbori']],
        ]],
        ['day' => 4, 'date' => '2026-11-24', 'city' => 'Kyoto', 'title' => 'Arashiyama', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['07:30', 'Osaka → Kyoto Station', 'Namba → Midosuji Line → Umeda, then JR Kyoto Line from Osaka Station → Kyoto Station', '', '', '', ['Kyoto Station']],
            ['08:30', 'Kimono rental, Bamboo Forest, Tenryu-ji', 'Kyoto Station → JR Sagano Line → Saga-Arashiyama (~15 min)', '', '', 'Qi suggests wearing the kimono at the bamboo forest. Return kimono before the train ride.', ['Arashiyama Bamboo Grove', 'Tenryu-ji']],
            ['11:30', 'Lunch TBC', '', 'Lunch', '', '', []],
            ['12:30', 'Sagano Romantic Train → Kameoka, Hozugawa River Cruise back, mochi at Togetsukyo', '', '', 'Book ahead', 'River cruise takes about 2 hours.', ['Torokko Saga Station', 'Hozugawa River Boat Ride', 'Togetsukyo Bridge']],
            ['17:30', 'Return to Osaka', 'Saga-Arashiyama → Kyoto Station → JR Kyoto Line → Osaka (~1 h)', '', '', '', []],
            ['19:00', 'Dinner TBC', '', 'Dinner', '', '', []],
            ['20:30', 'Dotonbori relax walk', '', '', '', '', ['Dotonbori']],
        ]],
        ['day' => 5, 'date' => '2026-11-25', 'city' => 'Osaka', 'title' => 'Universal Studios', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['08:00', 'Universal Studios Japan', 'Osaka-Namba → Hanshin Namba Line → Nishikujo → JR Yumesaki Line → Universal City', '', 'USJ ticket', 'Go straight to Super Nintendo World, then Harry Potter.', ['Universal Studios Japan']],
            ['20:00', 'Gyumon Halal Wagyu Yakiniku', '', 'Dinner', '', '', ['Gyumon Halal Wagyu Yakiniku Osaka'], 1],
        ]],
        ['day' => 6, 'date' => '2026-11-26', 'city' => 'Osaka', 'title' => 'Free day + shopping', 'activities' => [
            ['08:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['10:00', 'Namba Yasaka Shrine', '', '', '', '', ['Namba Yasaka Shrine']],
            ['14:00', 'Lunch TBC', '', 'Lunch', '', 'Kobe beef? Find a halal one.', []],
            ['16:00', 'Tower Knives, KAMO Umeda, Osaka Aquarium Kaiyukan', '', '', '', 'Aquarium is far from Namba. Consider moving it to the morning.', ['Tower Knives Osaka', 'KAMO Soccer Shop Umeda', 'Osaka Aquarium Kaiyukan']],
            ['19:00', 'Dinner TBC', '', 'Dinner', '', '', []],
            ['20:30', 'Dotonbori relax walk', '', '', '', '', ['Dotonbori']],
        ]],
        ['day' => 7, 'date' => '2026-11-27', 'city' => 'Tokyo', 'title' => 'Shinkansen to Tokyo', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['08:30', 'Check out of hotel', '', '', '', '', []],
            ['09:30', 'To Shin-Osaka', 'Namba → Midosuji Line → Shin-Osaka', '', '', '', ['Shin-Osaka Station']],
            ['10:00', 'Tokaido Shinkansen', '', '', 'Shinkansen ticket', '', []],
            ['12:30', 'Arrive Tokyo Station', '', '', '', '', ['Tokyo Station']],
            ['13:00', 'Hotel check-in (Shinjuku)', '', '', '', '', []],
            ['14:30', 'Ginza (Onitsuka Tiger engraving), then Tokyo Metropolitan Govt Building, Shinjuku Gyoen, Hanazono Shrine, 3D Cat', '', 'Lunch', '', 'Go to Ginza first to buy OT shoes with name engraving. Shinjuku Gyoen closes early in winter (around 16:00), so check before going.', ['Onitsuka Tiger Ginza', 'Tokyo Metropolitan Government Building', 'Shinjuku Gyoen', 'Hanazono Shrine', 'Cross Shinjuku Vision 3D cat']],
            ['18:00', 'Kabukicho', '', '', '', 'Main street, Don Quijote, Godzilla head, Alpen Tokyo.', ['Kabukicho', 'Godzilla Head Shinjuku', 'Alpen Tokyo']],
            ['19:30', 'Panga Halal Yakiniku', '', 'Dinner', '', '', ['Panga Halal Yakiniku'], 1],
            ['21:00', 'Omoide Yokocho', '', '', '', '', ['Omoide Yokocho']],
        ]],
        ['day' => 8, 'date' => '2026-11-28', 'city' => 'Tokyo', 'title' => 'Akihabara + Ueno', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['10:00', 'Kanda Myojin, Akihabara Electric Town, Yodobashi Akiba', '', '', '', 'Anime shops, retro games, arcades, gachapon.', ['Kanda Myojin', 'Akihabara Electric Town', 'Yodobashi Akiba']],
            ['12:30', 'Lunch TBC', '', 'Lunch', '', '', []],
            ['14:30', '2k540 Aki-Oka Artisan', '', '', '', '', ['2k540 Aki-Oka Artisan']],
            ['17:00', 'Ameyoko, Ueno Park, Ueno Toshogu', '', '', '', 'Museums if interested.', ['Ameyoko', 'Ueno Park', 'Ueno Toshogu']],
            ['19:00', 'Dinner TBD', '', 'Dinner', '', '', []],
        ]],
        ['day' => 9, 'date' => '2026-11-29', 'city' => 'Tokyo', 'title' => 'Asakusa + Skytree', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['09:00', 'Kaminarimon, Senso-ji, Nakamise, Denboin-dori, Kannon-dori, Shin-Nakamise', '', '', '', 'Snack hunting in Asakusa.', ['Kaminarimon', 'Senso-ji', 'Nakamise Shopping Street']],
            ['12:30', 'Yoshi’s Passion halal Japanese curry', '', 'Lunch', '', '', ['Yoshi\'s Passion Asakusa'], 1],
            ['14:00', 'Kappabashi Kitchenware Street + Sumida River walk', '', '', '', '', ['Kappabashi Dougu Street', 'Sumida River Walk']],
            ['16:30', 'Tokyo Skytree', '', '', 'Ticket', '', ['Tokyo Skytree']],
        ]],
        ['day' => 10, 'date' => '2026-11-30', 'city' => 'Tokyo', 'title' => 'Shibuya', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['10:00', 'Shibuya Crossing + Hachiko', 'Shinjuku → JR Yamanote Line → Shibuya', '', '', '', ['Shibuya Crossing', 'Hachiko Statue']],
            ['11:00', 'Center Gai, PARCO, Sanrio Gift Gate, Shibuya 109, Donki', '', '', '', 'Macam Myeongdong. Arcades, souvenir shops, vending machine drinks.', ['Shibuya Center Gai', 'Shibuya PARCO', 'Shibuya 109', 'MEGA Don Quijote Shibuya']],
            ['12:30', 'Gyumon Halal Yakiniku', '', 'Lunch', '', '', ['Gyumon Shibuya'], 1],
            ['14:00', 'Miyashita Park + Dogenzaka', '', '', '', '', ['Miyashita Park', 'Dogenzaka']],
            ['16:30', 'Yoyogi Park', '', '', '', '', ['Yoyogi Park']],
            ['18:00', 'Shibuya Sky', '', '', 'Book early', '', ['Shibuya Sky']],
            ['19:30', 'Annam Indian Restaurant Ginza', 'Shibuya → Ginza Line → Ginza (~17 min)', 'Dinner', '', '', ['Annam Indian Restaurant Ginza'], 1],
            ['21:00', 'Shibuya night street walk', '', '', '', '', ['Shibuya']],
        ]],
        ['day' => 11, 'date' => '2026-12-01', 'city' => 'Fuji', 'title' => 'Kawaguchiko', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['07:30', 'Shinjuku → Kawaguchiko', 'JR Chuo Line → Otsuki, change to Fujikyu Railway → Kawaguchiko Station', '', '', '', ['Kawaguchiko Station']],
            ['10:30', 'Lake Kawaguchi', '', '', '', 'Autumn maple with Fuji in the background. Walk the lakeside slowly.', ['Lake Kawaguchi']],
            ['12:00', 'Hoto noodles', '', 'Lunch', '', 'Check ingredients.', []],
            ['13:30', 'Kachi Kachi Ropeway', '', '', 'Ticket', 'View Mt Fuji from the top.', ['Mt. Fuji Panoramic Ropeway']],
            ['15:30', 'Rest + vending machine drink', '', '', '', '', []],
            ['16:30', 'Return to Tokyo', '', '', '', '', []],
            ['19:30', 'Panga Halal Yakiniku', '', 'Dinner', '', '', ['Panga Halal Yakiniku'], 1],
        ]],
        ['day' => 12, 'date' => '2026-12-02', 'city' => 'Tokyo', 'title' => 'Mt Takao + Showa Kinen', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['09:30', 'Mt Takao: cable car up, explore, chairlift down, shopping street', 'Shinjuku → Keio Line → Takaosanguchi (~50 min)', '', '', 'Take photos at Takaosanguchi Station.', ['Takaosanguchi Station', 'Mt. Takao']],
            ['12:30', 'Lunch TBD', '', 'Lunch', '', '', []],
            ['14:00', 'Showa Kinen Park (cycling)', 'Takao → JR Chuo Line → Tachikawa', '', '', '', ['Showa Kinen Park']],
            ['18:30', 'Dinner TBD', '', 'Dinner', '', '', []],
        ]],
        ['day' => 13, 'date' => '2026-12-03', 'city' => 'Tokyo', 'title' => 'Ikebukuro + Harajuku', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['10:00', 'Ikebukuro Sunshine City', 'Shinjuku → JR Yamanote Line → Ikebukuro', '', '', 'Indoor shopping mall.', ['Sunshine City']],
            ['12:00', 'Sakura Tei Harajuku', 'Ikebukuro → JR Yamanote Line → Harajuku', 'Lunch', '', '', ['Sakura Tei Harajuku'], 1],
            ['14:00', 'Anime shopping TBC', '', '', '', '', []],
            ['16:30', 'Takeshita Street, Cat Street, Tokyu Plaza Omokado', '', '', '', 'Blue Bottle, Arc’teryx, Case:Pace.', ['Takeshita Street', 'Cat Street Harajuku', 'Tokyu Plaza Omotesando Omokado']],
            ['17:30', 'Omotesando Christmas illuminations', '', '', '', '', ['Omotesando']],
            ['19:00', 'Annam Indian Restaurant Ginza', '', 'Dinner', '', '', ['Annam Indian Restaurant Ginza'], 1],
        ]],
        ['day' => 14, 'date' => '2026-12-04', 'city' => 'Tokyo', 'title' => 'Shinjuku shopping', 'activities' => [
            ['07:00', '7-Eleven', '', 'Breakfast', '', '', []],
            ['10:00', 'Onitsuka Tiger Shinjuku', '', '', '', '', ['Onitsuka Tiger Shinjuku']],
            ['11:30', 'ABC-MART Grand Stage Shinjuku', '', '', '', 'Other sport shoe brands.', ['ABC-MART Grand Stage Shinjuku']],
            ['13:00', 'Halal Wagyu Ramen Shinjuku-Tei', '', 'Lunch', '', '', ['Shinjuku-Tei Halal Wagyu Ramen'], 1],
            ['14:30', 'Don Quijote Shinjuku', '', '', '', 'Souvenirs + snacks heaven.', ['Don Quijote Shinjuku']],
            ['17:00', 'Shinjuku Takashimaya', '', '', '', 'Higher-quality souvenirs.', ['Shinjuku Takashimaya']],
            ['19:00', 'Gyumon Halal Yakiniku', '', 'Dinner', '', '', ['Gyumon Halal Yakiniku Tokyo'], 1],
        ]],
    ];

    /**
     * Shopping list from the prototype: [name, qty, estimated yen].
     *
     * @var list<array{0: string, 1: int, 2: int}>
     */
    private const SHOPPING = [
        ['MUJI Packable Boston Bag 23L', 1, 2700],
        ['MUJI Lip Essence Pink (02)', 1, 900],
        ['MUJI UV Pocketable Hoodie, smoky pink XL', 1, 3600],
        ['MUJI Boa Fleece Jacket, smoky pink XL', 1, 4000],
        ['MUJI Eye Shadow 4 Colors Gold Brown', 1, 1350],
        ['Uniqlo Heattech Ultra Warm XL black (2 tops + 2 leggings)', 4, 10900],
        ['Derma Laser Super Retinol Mask', 1, 1400],
        ['Hada Labo Gokujyun Aging Care Lotion 170ml', 1, 1300],
        ['Kosé Gomen ne Suhada Face Mask', 1, 700],
        ['&honey Shampoo + Treatment (1 set)', 1, 2800],
        ['Hada Labo Gokujyun Alpha Premium Serum 20ml (may not exist in Japan)', 2, 3000],
        ['Hada Labo Perfect 3D Gel 40g (may not exist in Japan)', 2, 3000],
        ['Kosé CoenRich Q10 Hand Cream 80g', 1, 600],
        ['White Conc CC Cream 200g', 1, 1300],
        ['Travel-size sunscreens (Canmake, Anessa, 2× Shiseido)', 4, 6000],
        ['Melano CC Vitamin C Toner', 1, 1000],
        ['7-Eleven biscuits (4 blue, 4 orange)', 8, 2240],
        ['Royce Nama Chocolate, liqueur free (buy last)', 3, 2700],
    ];

    /**
     * Unscheduled ideas grouped by region.
     *
     * @var array<string, list<string>>
     */
    private const IDEAS = [
        'Kansai' => [
            'Dojimahama Tower',
            'Akashi-Kaikyo Bridge',
            'Sumaura-Yuen Amusement Park',
            'Kitano Ijinkan-Gai / Moegi House / Weathercock House',
            'Nakazakicho',
            'Osaka Aquarium Kaiyukan',
            'Tempozan Harbor Village',
            'Osaka Amazing Pass',
            'Daibutsu Ichigo (strawberries)',
            'Yoshidaen',
            'Jojakko-ji Temple',
            'Narutaki',
            'Senbon Torii',
            'Murou Art Forest',
            'Hozan-ji (Ikoma Shoten)',
        ],
        'Tokyo' => [
            'The North Face',
            'Human Made',
            'Blue Bottle',
            'Takeshita Street',
            'Case:Pace',
            'Hachiko Statue',
            'PARCO (Nintendo + Pokémon)',
            'Mega Don Quijote',
            'Palace Skateboards Tokyo',
            'Oi Racecourse Flea Market (9:30–14:30)',
            'Tokyo Skytree',
            'Ginza',
            'Rugby Online Tokyo',
            'Tokyo Tower',
        ],
    ];

    /**
     * Run the database seeds. Skips if the trip already exists so it is safe to re-run.
     */
    public function run(): void
    {
        if (Trip::where('name', self::TRIP_NAME)->exists()) {
            $this->command?->warn('Trip "'.self::TRIP_NAME.'" already exists, skipping.');

            return;
        }

        DB::transaction(function (): void {
            $trip = Trip::create([
                'name' => self::TRIP_NAME,
                'start_date' => '2026-11-21',
                'end_date' => '2026-12-04',
                'timezone' => 'Asia/Tokyo',
                'exchange_rate_rm_per_100_yen' => 3.00,
                'deposit_rm' => 0,
            ]);

            $now = now();
            $activityRows = [];

            foreach (self::DAYS as $dayData) {
                $day = $trip->days()->create([
                    'day_number' => $dayData['day'],
                    'date' => $dayData['date'],
                    'city' => $dayData['city'],
                    'title' => $dayData['title'],
                ]);

                foreach ($dayData['activities'] as $index => $activity) {
                    $activityRows[] = [
                        'id' => (string) Str::uuid7(),
                        'trip_id' => $trip->id,
                        'day_id' => $day->id,
                        'start_time' => $activity[0],
                        'name' => $activity[1],
                        'transport' => $activity[2],
                        'meal' => $activity[3],
                        'ticket' => $activity[4],
                        'notes' => $activity[5] === '' ? null : $activity[5],
                        'places' => json_encode($activity[6], JSON_UNESCAPED_UNICODE),
                        'is_halal' => (bool) ($activity[7] ?? false),
                        'status' => 'pending',
                        'sort_order' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('activities')->insert($activityRows);
            $trip->ensureTaxRefundStop();

            $shoppingRows = [];
            foreach (self::SHOPPING as $index => [$name, $qty, $estimatedYen]) {
                $shoppingRows[] = [
                    'id' => (string) Str::uuid7(),
                    'trip_id' => $trip->id,
                    'name' => $name,
                    'qty' => $qty,
                    'estimated_yen' => $estimatedYen,
                    'is_bought' => false,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('shopping_items')->insert($shoppingRows);

            $ideaRows = [];
            $sortOrder = 0;
            foreach (self::IDEAS as $region => $names) {
                foreach ($names as $name) {
                    $ideaRows[] = [
                        'trip_id' => $trip->id,
                        'region' => $region,
                        'name' => $name,
                        'is_visited' => false,
                        'sort_order' => $sortOrder++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('ideas')->insert($ideaRows);

            $this->command?->info("Seeded trip #{$trip->id} with join code {$trip->join_code}.");
        });
    }
}
