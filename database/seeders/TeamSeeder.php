<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un membre par profil de depart, pour que l'ecran d'equipe et la matrice de permissions
 * soient lisibles sans avoir a inviter qui que ce soit.
 */
class TeamSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string}>
     */
    private const Members = [
        'Tresorier' => ['name' => 'Koffi Brou', 'email' => 'tresorier@convive.com'],
        'Hotesse' => ['name' => 'Adjoua Yao', 'email' => 'hotesse@convive.com'],
        'Lecture' => ['name' => 'Mariam Traore', 'email' => 'lecture@convive.com'],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        foreach (self::Members as $profileName => $member) {
            $profile = $tenant->run(fn () => Profile::where('name', $profileName)->first());

            if (! $profile) {
                continue;
            }

            $tenant->addMember($this->member($member['name'], $member['email']), $profile);
        }
    }

    private function member(string $name, string $email): User
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }
}
