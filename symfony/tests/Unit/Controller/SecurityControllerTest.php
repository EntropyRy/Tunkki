<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SecurityController;
use PHPUnit\Framework\TestCase;

final class SecurityControllerTest extends TestCase
{
    public function testLogoutThrowsLogicException(): void
    {
        $controller = new SecurityController();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This method can be blank');

        $controller->logout();
    }
}
