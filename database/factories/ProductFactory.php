<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'        => strtoupper($this->faker->unique()->lexify('??-###')),
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category'    => $this->faker->randomElement(Product::CATEGORIES),
            'unit_price'  => $this->faker->randomFloat(2, 5, 500),
            'unit_label'  => $this->faker->randomElement(['piece', 'ream', 'box', 'pack']),
            'minimum_order' => 1,
            'maximum_order' => null,
            'customizations' => null,
            'is_active'   => true,
        ];
    }

    public function withCustomizations(): static
    {
        return $this->state([
            'customizations' => [
                [
                    'key'     => 'color',
                    'label'   => 'Color',
                    'type'    => 'select',
                    'options' => ['blue', 'red', 'black'],
                ],
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
