<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\GameConfig;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Marketing Campaigns
        $campaigns = [
            'email_blast' => [
                'name' => 'Email Blast',
                'cost' => 500,
                'duration' => 60,
                'effectiveness' => 1.5,
                'reputation_gain' => 0.5,
                'min_reputation' => 0
            ],
            'social_media' => [
                'name' => 'Social Media Ads',
                'cost' => 2500,
                'duration' => 360,
                'effectiveness' => 3.0,
                'reputation_gain' => 2.0,
                'min_reputation' => 10
            ],
            'influencer' => [
                'name' => 'Tech Influencer Review',
                'cost' => 8000,
                'duration' => 1440,
                'effectiveness' => 5.0,
                'reputation_gain' => 8.0,
                'min_reputation' => 30
            ],
            'billboard' => [
                'name' => 'City Billboard',
                'cost' => 15000,
                'duration' => 10080,
                'effectiveness' => 2.0,
                'reputation_gain' => 12.0,
                'min_reputation' => 50
            ],
            'tv_spot' => [
                'name' => 'Super Bowl TV Spot',
                'cost' => 100000,
                'duration' => 60,
                'effectiveness' => 20.0,
                'reputation_gain' => 25.0,
                'min_reputation' => 80
            ]
        ];
        GameConfig::set('marketing_campaigns', $campaigns, 'marketing', 'Marketing campaign definitions');

        // 2. Product Definitions (Scripts for Order Generation)
        $products = [
            'web_hosting' => [
                'name' => 'Web Hosting',
                'requirements' => ['cpu' => [1, 2], 'ram' => [1, 4], 'storage' => [10, 50], 'bandwidth' => [10, 100]],
                'min_level' => 1,
                'base_price' => 10
            ],
            'game_server' => [
                'name' => 'Game Server',
                'requirements' => ['cpu' => [2, 8], 'ram' => [4, 16], 'storage' => [50, 200], 'bandwidth' => [100, 1000]],
                'min_level' => 5,
                'base_price' => 50
            ],
            'database_hosting' => [
                'name' => 'Database Hosting',
                'requirements' => ['cpu' => [2, 16], 'ram' => [8, 32], 'storage' => [100, 1000], 'bandwidth' => [50, 500]],
                'min_level' => 10,
                'base_price' => 120
            ],
            'ml_training' => [
                'name' => 'ML Model Training',
                'requirements' => ['cpu' => [8, 64], 'ram' => [32, 128], 'storage' => [500, 5000], 'bandwidth' => [1000, 10000], 'gpu' => [1, 4]],
                'min_level' => 15,
                'base_price' => 500
            ]
        ];
        GameConfig::set('product_definitions', $products, 'gameplay', 'Product definitions and requirements');
    }

    public function down(): void
    {
        GameConfig::where('key', 'marketing_campaigns')->delete();
        GameConfig::where('key', 'product_definitions')->delete();
    }
};
