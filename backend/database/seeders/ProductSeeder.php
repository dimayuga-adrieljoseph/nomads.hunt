<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // ── Nike ──────────────────────────────────────────────────────────
            [
                'name'        => 'Vintage Nike Windbreaker',
                'description' => 'Classic 90s Nike windbreaker in excellent condition. Vibrant color blocking with embroidered Swoosh logo. A true streetwear grail.',
                'category'    => 'Jacket',
                'brand'       => 'Nike',
                'size'        => 'Large',
                'condition'   => 'excellent',
                'mine_price'  => 1500,
                'steal_price' => 1800,
                'grab_price'  => 2200,
                'status'      => 'available',
            ],
            [
                'name'        => 'Nike Air Max 95 OG',
                'description' => 'Original Nike Air Max 95 in Neon Yellow. Lightly worn, sole intact, no yellowing. Comes with original box.',
                'category'    => 'Footwear',
                'brand'       => 'Nike',
                'size'        => 'US 10',
                'condition'   => 'good',
                'mine_price'  => 3500,
                'steal_price' => 4200,
                'grab_price'  => 5000,
                'status'      => 'available',
            ],
            [
                'name'        => 'Nike ACG Cargo Pants',
                'description' => 'Nike ACG all-conditions gear cargo pants. Multiple pockets, drawstring waist, forest green colorway. Y2K aesthetic at its finest.',
                'category'    => 'Bottoms',
                'brand'       => 'Nike',
                'size'        => 'Medium',
                'condition'   => 'good',
                'mine_price'  => 1200,
                'steal_price' => 1450,
                'grab_price'  => 1800,
                'status'      => 'available',
            ],

            // ── Levi's ────────────────────────────────────────────────────────
            [
                'name'        => "Levi's 501 Original Jeans",
                'description' => "Classic straight-leg 501s, medium wash, lightly faded naturally. The blueprint of denim. Tagged 32x30.",
                'category'    => 'Bottoms',
                'brand'       => "Levi's",
                'size'        => '32x30',
                'condition'   => 'good',
                'mine_price'  => 900,
                'steal_price' => 1100,
                'grab_price'  => 1400,
                'status'      => 'available',
            ],
            [
                'name'        => "Levi's Sherpa Trucker Jacket",
                'description' => 'Levi\'s Type III trucker with sherpa lining. Perfect for cool evenings. Light use, all buttons intact.',
                'category'    => 'Jacket',
                'brand'       => "Levi's",
                'size'        => 'Large',
                'condition'   => 'excellent',
                'mine_price'  => 1600,
                'steal_price' => 1900,
                'grab_price'  => 2400,
                'status'      => 'available',
            ],

            // ── Adidas ────────────────────────────────────────────────────────
            [
                'name'        => 'Adidas Track Jacket Vintage',
                'description' => '90s Adidas three-stripe track jacket, navy and white. Retro cut with snap buttons. Light wear only.',
                'category'    => 'Jacket',
                'brand'       => 'Adidas',
                'size'        => 'Medium',
                'condition'   => 'good',
                'mine_price'  => 1100,
                'steal_price' => 1350,
                'grab_price'  => 1650,
                'status'      => 'available',
            ],
            [
                'name'        => 'Adidas Superstar OG',
                'description' => 'OG Adidas Superstar shell toe. Clean white with black stripes. Minimal creasing on toe box.',
                'category'    => 'Footwear',
                'brand'       => 'Adidas',
                'size'        => 'US 9',
                'condition'   => 'excellent',
                'mine_price'  => 2200,
                'steal_price' => 2600,
                'grab_price'  => 3200,
                'status'      => 'available',
            ],

            // ── Ralph Lauren ──────────────────────────────────────────────────
            [
                'name'        => 'Ralph Lauren Polo Shirt Vintage',
                'description' => 'Classic Ralph Lauren polo, forest green with embroidered pony. 100% cotton, excellent condition, no fading.',
                'category'    => 'Tops',
                'brand'       => 'Ralph Lauren',
                'size'        => 'Medium',
                'condition'   => 'excellent',
                'mine_price'  => 800,
                'steal_price' => 950,
                'grab_price'  => 1200,
                'status'      => 'available',
            ],

            // ── Champion ──────────────────────────────────────────────────────
            [
                'name'        => 'Champion Reverse Weave Hoodie',
                'description' => 'Classic Champion Reverse Weave hoodie in grey. Heavy cotton fleece, iconic C logo on sleeve. Minimal pilling.',
                'category'    => 'Tops',
                'brand'       => 'Champion',
                'size'        => 'XL',
                'condition'   => 'good',
                'mine_price'  => 700,
                'steal_price' => 850,
                'grab_price'  => 1050,
                'status'      => 'available',
            ],

            // ── Carhartt ──────────────────────────────────────────────────────
            [
                'name'        => 'Carhartt Detroit Jacket',
                'description' => 'Classic Carhartt Detroit jacket in brown duck canvas. Blanket-lined, four pockets, aged patina from honest use.',
                'category'    => 'Jacket',
                'brand'       => 'Carhartt',
                'size'        => 'Large',
                'condition'   => 'fair',
                'mine_price'  => 1800,
                'steal_price' => 2100,
                'grab_price'  => 2700,
                'status'      => 'available',
            ],

            // ── Tommy Hilfiger ────────────────────────────────────────────────
            [
                'name'        => 'Tommy Hilfiger Flag Tee',
                'description' => '90s Tommy Hilfiger large flag logo tee in white. True vintage, slight yellowing on collar consistent with age.',
                'category'    => 'Tops',
                'brand'       => 'Tommy Hilfiger',
                'size'        => 'Large',
                'condition'   => 'fair',
                'mine_price'  => 600,
                'steal_price' => 750,
                'grab_price'  => 950,
                'status'      => 'available',
            ],

            // ── New Balance ───────────────────────────────────────────────────
            [
                'name'        => 'New Balance 574 Made in USA',
                'description' => 'New Balance 574 Made in USA edition. Navy suede/mesh, excellent cushion remaining. Hard to find in this condition.',
                'category'    => 'Footwear',
                'brand'       => 'New Balance',
                'size'        => 'US 10.5',
                'condition'   => 'excellent',
                'mine_price'  => 2800,
                'steal_price' => 3400,
                'grab_price'  => 4200,
                'status'      => 'available',
            ],

            // ── Stüssy ────────────────────────────────────────────────────────
            [
                'name'        => 'Stüssy Basic Logo Crewneck',
                'description' => 'Stüssy world tour crewneck sweatshirt in black. Embroidered logo, heavyweight fleece, excellent condition.',
                'category'    => 'Tops',
                'brand'       => 'Stüssy',
                'size'        => 'Medium',
                'condition'   => 'excellent',
                'mine_price'  => 1400,
                'steal_price' => 1700,
                'grab_price'  => 2100,
                'status'      => 'available',
            ],

            // ── Dickies ───────────────────────────────────────────────────────
            [
                'name'        => 'Dickies 874 Original Work Pants',
                'description' => 'Classic Dickies 874 in khaki. Straight cut, pressed crease, never washed out. A workwear staple.',
                'category'    => 'Bottoms',
                'brand'       => 'Dickies',
                'size'        => '34x30',
                'condition'   => 'good',
                'mine_price'  => 500,
                'steal_price' => 620,
                'grab_price'  => 800,
                'status'      => 'available',
            ],
        ];

        foreach ($products as $data) {
            // Only create if no product with same name exists
            Product::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
