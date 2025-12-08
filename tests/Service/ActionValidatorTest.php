<?php

namespace App\Tests\Service;

use App\Domain\Action\ActionName;
use App\Service\ActionValidator;
use PHPUnit\Framework\TestCase;

class ActionValidatorTest extends TestCase
{
    private ActionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ActionValidator();
    }

    /**
     * Test validation of valid action names.
     */
    public function testValidateWithValidActions(): void
    {
        $actions = [
            ['name' => 'print', 'params' => []],
            ['name' => 'cut', 'params' => []],
        ];

        $result = $this->validator->validate($actions);

        $this->assertTrue($result->isValid);
        $this->assertCount(2, $result->actions);
        $this->assertEquals(ActionName::Print, $result->actions[0]->name);
        $this->assertEquals(ActionName::Cut, $result->actions[1]->name);
    }

    /**
     * Test validation of all valid action types.
     */
    public function testValidateWithAllActionTypes(): void
    {
        $actions = [
            ['name' => 'print'],
            ['name' => 'cut'],
            ['name' => 'cutout'],
            ['name' => 'split'],
            ['name' => 'assembly'],
        ];

        $result = $this->validator->validate($actions);

        $this->assertTrue($result->isValid);
        $this->assertCount(5, $result->actions);
    }

    /**
     * Test validation fails for unknown action name.
     */
    public function testValidateWithUnknownActionName(): void
    {
        $actions = [
            ['name' => 'print', 'params' => []],
            ['name' => 'laminate', 'params' => []],
        ];

        $result = $this->validator->validate($actions);

        $this->assertFalse($result->isValid);
        $this->assertEquals('INVALID_ACTION_NAME', $result->errorCode);
        $this->assertStringContainsString('laminate', $result->errorMessage);
    }

    /**
     * Test validation fails for missing name field.
     */
    public function testValidateWithMissingName(): void
    {
        $actions = [
            ['params' => []],
        ];

        $result = $this->validator->validate($actions);

        $this->assertFalse($result->isValid);
        $this->assertEquals('MISSING_ACTION_NAME', $result->errorCode);
    }

    /**
     * Test validation fails for non-string name.
     */
    public function testValidateWithNonStringName(): void
    {
        $actions = [
            ['name' => 123, 'params' => []],
        ];

        $result = $this->validator->validate($actions);

        $this->assertFalse($result->isValid);
        $this->assertEquals('INVALID_ACTION_NAME_TYPE', $result->errorCode);
    }

    /**
     * Test validation fails for non-array action.
     */
    public function testValidateWithNonArrayAction(): void
    {
        $actions = [
            'not an array',
        ];

        $result = $this->validator->validate($actions);

        $this->assertFalse($result->isValid);
        $this->assertEquals('INVALID_ACTION_FORMAT', $result->errorCode);
    }

    /**
     * Test validation succeeds with empty actions array.
     */
    public function testValidateWithEmptyActions(): void
    {
        $actions = [];

        $result = $this->validator->validate($actions);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->actions);
    }

    /**
     * Test params are preserved in validated actions.
     */
    public function testValidatePreservesParams(): void
    {
        $params = [
            'paper' => ['type' => 'dimension', 'width' => 200],
            'bleed' => ['size' => 6],
        ];

        $actions = [
            ['name' => 'print', 'params' => $params],
        ];

        $result = $this->validator->validate($actions);

        $this->assertTrue($result->isValid);
        $this->assertEquals($params, $result->actions[0]->params);
    }

    /**
     * Test default empty params when not provided.
     */
    public function testValidateWithMissingParams(): void
    {
        $actions = [
            ['name' => 'print'],
        ];

        $result = $this->validator->validate($actions);

        $this->assertTrue($result->isValid);
        $this->assertEquals([], $result->actions[0]->params);
    }

    /**
     * Test error response format.
     */
    public function testGetErrorResponse(): void
    {
        $actions = [
            ['name' => 'invalid_action'],
        ];

        $result = $this->validator->validate($actions);
        $errorResponse = $result->getErrorResponse();

        $this->assertArrayHasKey('error', $errorResponse);
        $this->assertArrayHasKey('code', $errorResponse);
        $this->assertEquals($result->errorMessage, $errorResponse['error']);
        $this->assertEquals($result->errorCode, $errorResponse['code']);
    }
}
