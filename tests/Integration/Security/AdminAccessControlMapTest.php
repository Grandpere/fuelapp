<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Integration\Security;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;

final class AdminAccessControlMapTest extends KernelTestCase
{
    private AccessMapInterface $accessMap;

    protected function setUp(): void
    {
        self::bootKernel();

        $accessMap = self::getContainer()->get('security.access_map');
        if (!$accessMap instanceof AccessMapInterface) {
            throw new RuntimeException('security.access_map service not found.');
        }

        $this->accessMap = $accessMap;
    }

    public function testApiAdminPrefixRequiresRoleAdmin(): void
    {
        $request = Request::create('/api/admin/jobs');

        $patterns = $this->accessMap->getPatterns($request);
        $attributes = $patterns[0] ?? null;
        self::assertIsArray($attributes);
        self::assertContains('ROLE_ADMIN', $attributes);
    }

    public function testUiAdminPrefixRequiresRoleAdmin(): void
    {
        $request = Request::create('/ui/admin/dashboard');

        $patterns = $this->accessMap->getPatterns($request);
        $attributes = $patterns[0] ?? null;
        self::assertIsArray($attributes);
        self::assertContains('ROLE_ADMIN', $attributes);
    }
}
