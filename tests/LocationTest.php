<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LocationTest extends TestCase
{
    use DatabaseMigrations;
    /**
     *
     * A basic test example.
     *
     * @return void
     */
    public function testCreateLocation()
    {
        $data = ['user_id' => 1, 'lat' => 1.0, 'long' => 2.0];
        $this->post('/location', $data)
            ->seeJson($data);
        $this->seeInDatabase('locations', $data);
    }

}
