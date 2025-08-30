<?php

declare(strict_types=1);

namespace Modules\Welcome\Tests\Actions;

use Tests\TestCase;

class WelcomeModuleActionTest extends TestCase
{
    /**
     * Test successful activation of a module.
     */
    public function testActivateModuleSuccess()
    {
        $app = $this->getAppInstance();

        // Send GET request to the module endpoint
        $request = $this->createRequest('GET', $this->generateRouteUrl('welcome.index'));
        $response = $app->handle($request);

        // Decode the response
        $payload = (string) $response->getBody();

        // Assertions
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsStringIgnoringCase('welcome', $payload);
    }
}