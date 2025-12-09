<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DisplayControllerTest extends WebTestCase
{
    /**
     * Test display action path by UUID - full E2E flow.
     */
    public function testDisplayActionPath(): void
    {
        $client = static::createClient();

        // First, create a process request to get an action path
        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
                    'properties' => ['copies' => 1000],
                    'actions' => [
                        ['name' => 'print', 'params' => [
                            'dimensions' => [
                                'open' => ['width' => 200, 'height' => 200],
                                'closed' => ['width' => 200, 'height' => 200],
                            ],
                            'inking' => [
                                'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                'verso' => [],
                            ],
                        ]],
                    ],
                    'required_parts' => [],
                ],
            ],
        ];

        $client->request(
            'POST',
            '/process',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $jobId = $data[0]['metaData']['jobId'];
        $actionPaths = $data[0]['parts']['PART0001']['actionPaths'];

        // Skip if no action paths were generated
        if (empty($actionPaths)) {
            $this->markTestSkipped('No action paths generated - cannot test display');
        }

        $actionPathId = $actionPaths[0]['id'];

        // Now test the display endpoint
        $client->request('GET', "/display/{$jobId}/PART0001/{$actionPathId}");

        $displayResponse = $client->getResponse();

        // Debug output if not 200
        if ($displayResponse->getStatusCode() !== Response::HTTP_OK) {
            fwrite(STDERR, "\n\nDisplay Response: " . $displayResponse->getContent() . "\n\n");
        }

        $this->assertEquals(Response::HTTP_OK, $displayResponse->getStatusCode());

        $displayData = json_decode($displayResponse->getContent(), true);
        $this->assertIsArray($displayData);
        $this->assertArrayHasKey('id', $displayData);
        $this->assertArrayHasKey('nodes', $displayData);
        $this->assertArrayHasKey('cost', $displayData);
        $this->assertArrayHasKey('duration', $displayData);
        $this->assertEquals($actionPathId, $displayData['id']);
    }

    /**
     * Test display returns 404 for non-existent UUID.
     */
    public function testDisplayReturns404ForInvalidUuid(): void
    {
        $client = static::createClient();

        $client->request('GET', '/display/JOB001/PART0001/non-existent-uuid-12345');

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
