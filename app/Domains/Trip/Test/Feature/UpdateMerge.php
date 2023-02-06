<?php declare(strict_types=1);

namespace App\Domains\Trip\Test\Feature;

class UpdateMerge extends FeatureAbstract
{
    /**
     * @var string
     */
    protected string $route = 'trip.update.merge';

    /**
     * @return void
     */
    public function testGetUnauthorizedFail(): void
    {
        $this->get($this->routeFactoryCreateModel())
            ->assertStatus(302)
            ->assertRedirect(route('user.auth.credentials'));
    }

    /**
     * @return void
     */
    public function testPostUnauthorizedFail(): void
    {
        $this->post($this->routeFactoryCreateModel())
            ->assertStatus(302)
            ->assertRedirect(route('user.auth.credentials'));
    }

    /**
     * @return void
     */
    public function testGetSuccess(): void
    {
        $this->authUser();

        $this->get($this->routeFactoryCreateModel())
            ->assertStatus(200);
    }

    /**
     * @return void
     */
    public function testPostSuccess(): void
    {
        $this->authUser();

        $this->post($this->routeFactoryCreateModel())
            ->assertStatus(200);
    }
}