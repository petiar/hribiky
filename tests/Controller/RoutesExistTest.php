<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Routy, ktoré sa nesmú potichu stratiť. Symfony 8 odstránil Routing\Annotation\Route
 * a controllery s týmto importom prišli o routy bez akejkoľvek chyby.
 */
class RoutesExistTest extends KernelTestCase
{
    #[DataProvider('routes')]
    public function testRouteExists(string $name, string $path): void
    {
        self::bootKernel();
        $route = static::getContainer()->get('router')->getRouteCollection()->get($name);

        $this->assertNotNull($route, "Route „$name“ neexistuje.");
        $this->assertSame($path, $route->getPath());
    }

    public static function routes(): array
    {
        return [
            'nový hríbik (web)' => ['rozcestnik_create', '/rozcestnik/create'],
            'nový komentár (web)' => ['rozcestnik_update_create', '/rozcestnik-update'],
            'API kľúč' => ['generate_api_key', '/generate-key'],
            'fotky k článku (admin)' => ['admin_blog_photo_list', '/admin/blog-post/{id}/photos'],
            'dočasné fotky (admin)' => ['admin_blog_photo_temp_upload', '/admin/blog-post/temp-photos'],
            'vyhľadávanie' => ['search', '/hladat'],
            'API hríbiky' => ['api_mushrooms__index', '/api/mushrooms'],
            'API komentáre' => ['api_mushrooms_comments_index', '/api/mushrooms_comments'],
            'API vyhľadávanie' => ['api_search', '/api/search'],
            'API rebríček' => ['api_leaderboard_index', '/api/leaderboard'],
        ];
    }
}
