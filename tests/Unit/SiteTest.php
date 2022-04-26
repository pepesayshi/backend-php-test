<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use \Classes\Site;

final class SiteTest extends TestCase {

    public function testCanLogIn() {

        $username = 'tester';
        $password = 'tester';

        $site = new Site();
        $user = $site->login($username, $password);

        $this->assertArrayHasKey('id', $user);
    }

}

?>