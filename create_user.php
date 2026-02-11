<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'danny@codepony.de';
$password = 'Mandysandy.2007';

$user = User::where('email', $email)->first();

if (!$user) {
    try {
        $user = User::create([
            'name' => 'Danny',
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        echo "User created successfully.\n";
    } catch (\Exception $e) {
        echo "Error creating user: " . $e->getMessage() . "\n";
    }
} else {
    $user->password = Hash::make($password);
    $user->save();
    echo "User password updated successfully.\n";
}
