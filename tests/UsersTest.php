<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UsersTest extends TestCase
{
    use DatabaseMigrations;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetUser()
    {
        $user = \App\User::create(['name' => 'Craig', 'email' => 'craig@asd.com']);

        $this->get('/users')
            ->seeJson(['name' => 'Craig']);
    }

    public function testCreateUser()
    {
        $this->post('/users', ['name' => 'Kelvin', 'email' => 'kelvintamzil@yahoo.com'])
            ->seeJson(['name' => 'Kelvin'
            ]);
        $this->seeInDatabase('users', ['name' => 'Kelvin']);
    }

}
