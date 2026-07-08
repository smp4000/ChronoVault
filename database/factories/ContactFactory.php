<?php

/**
 * =========================================================================
 * ContactFactory — Test-/Seed-Daten für Kontakte (Tenant-Datenbank)
 * =========================================================================
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'type' => ContactType::PrivatePerson,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'country' => 'Deutschland',
        ];
    }

    public function dealer(): static
    {
        return $this->state([
            'type' => ContactType::Dealer,
            'company_name' => fake()->company(),
        ]);
    }
}
