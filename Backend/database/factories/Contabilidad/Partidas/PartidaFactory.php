<?php

namespace Database\Factories\Contabilidad\Partidas;

use App\Models\Contabilidad\Partidas\Partida;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartidaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    protected  $model = Partida::class;

    public function definition()
    {
        return [

            'tipo' =>$this->faker->name,
            'concepto' =>$this->faker->text,
            'estado' => $this->faker->randomElement(['aprobado', 'denegado']),
            'id_usario' => $this->faker->numberBetween([1,4]),
            'id_empresa' => 1,
        ];
    }
}
