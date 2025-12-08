<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProcessControllerTest extends WebTestCase
{
    /**
     * Test successful processing with valid payload.
     */
    public function testProcessWithValidPayload(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
                    'properties' => [],
                    'actions' => [
                        ['name' => 'print', 'params' => [
                            "dimensions" => [
                                "open" => ["width" => 200, "height" => 200],
                                "closed" => ["width" => 200, "height" => 200]
                            ]
                        ]],
                        ['name' => 'cut', 'params' => []],
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

        // Debug: show error content if not 200
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            fwrite(STDERR, "\n\nResponse content: " . $response->getContent() . "\n\n");
        }

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        // Response is an array of jobs (TestController::getTest() compatible format)
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('metaData', $data[0]);
        $this->assertArrayHasKey('parts', $data[0]);
        $this->assertArrayHasKey('jobId', $data[0]['metaData']);
        $this->assertArrayHasKey('PART0001', $data[0]['parts']);
        $this->assertArrayHasKey('actionPaths', $data[0]['parts']['PART0001']);
    }

    /**
     * Test error response for unknown action name.
     */
    public function testProcessWithInvalidActionName(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
                    'actions' => [
                        ['name' => 'laminate', 'params' => []],
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
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertEquals('INVALID_ACTION_NAME', $data['code']);
        $this->assertStringContainsString('laminate', $data['error']);
    }

    /**
     * Test error response for missing parts field.
     */
    public function testProcessWithMissingParts(): void
    {
        $client = static::createClient();

        $payload = [];

        $client->request(
            'POST',
            '/process',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('MISSING_PARTS', $data['code']);
    }

    /**
     * Test error response for missing partId.
     */
    public function testProcessWithMissingPartId(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'actions' => [
                        ['name' => 'print', 'params' => []],
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
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('MISSING_PART_ID', $data['code']);
    }

    /**
     * Test error response for missing actions field.
     */
    public function testProcessWithMissingActions(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
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
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('MISSING_ACTIONS', $data['code']);
    }

    /**
     * Test successful processing with empty parts array.
     */
    public function testProcessWithEmptyPartsArray(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [],
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
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('metaData', $data[0]);
        $this->assertArrayHasKey('parts', $data[0]);
    }

    /**
     * Test successful processing with all valid action types.
     */
    public function testProcessWithAllActionTypes(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
                    'properties' => [],
                    'actions' => [
                        ['name' => 'print', 'params' => []],
                        ['name' => 'cut', 'params' => []],
                        ['name' => 'cutout', 'params' => []],
                        ['name' => 'split', 'params' => []],
                        ['name' => 'assembly', 'params' => []],
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
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('metaData', $data[0]);
        $this->assertArrayHasKey('parts', $data[0]);
        $this->assertArrayHasKey('PART0001', $data[0]['parts']);
    }

    /**
     * Test error response for invalid JSON.
     */
    public function testProcessWithInvalidJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/process',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not valid json{'
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_JSON', $data['code']);
    }

    /**
     * Test successful processing with multiple parts.
     */
    public function testProcessWithMultipleParts(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'PART0001',
                    'properties' => [],
                    'actions' => [
                        ['name' => 'print', 'params' => []],
                    ],
                    'required_parts' => [],
                ],
                [
                    'partId' => 'PART0002',
                    'properties' => [],
                    'actions' => [
                        ['name' => 'cut', 'params' => []],
                    ],
                    'required_parts' => ['PART0001'],
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
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('metaData', $data[0]);
        $this->assertArrayHasKey('parts', $data[0]);
        $this->assertArrayHasKey('PART0001', $data[0]['parts']);
        $this->assertArrayHasKey('PART0002', $data[0]['parts']);
    }
}
