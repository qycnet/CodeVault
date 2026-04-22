<?php
/**
 * CodeVault - GraphQLService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class GraphQLServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\GraphQLService();
    }
    
    public function testQueryValidation(): void
    {
        $validQuery = '{ user(id: 1) { name email } }';
        $this->assertTrue($this->service->isValidQuery($validQuery));
        
        $invalidQuery = '';
        $this->assertFalse($this->service->isValidQuery($invalidQuery));
    }
    
    public function testMutationValidation(): void
    {
        $mutation = 'mutation { createUser(input: {name: "test"}) { id } }';
        $this->assertTrue($this->service->isMutation($mutation));
        
        $query = '{ user(id: 1) { name } }';
        $this->assertFalse($this->service->isMutation($query));
    }
    
    public function testQueryDepthLimit(): void
    {
        $deepQuery = '{ a { b { c { d { e { f } } } } } }';
        $this->assertFalse($this->service->isWithinDepthLimit($deepQuery, 5));
        $this->assertTrue($this->service->isWithinDepthLimit($deepQuery, 10));
    }
    
    public function testQueryComplexity(): void
    {
        $complexQuery = '{ users(first: 100) { edges { node { posts(first: 50) { edges { node { comments } } } } } } }';
        $complexity = $this->service->calculateComplexity($complexQuery);
        
        $this->assertGreaterThan(100, $complexity);
    }
    
    public function testIntrospectionControl(): void
    {
        // 生产环境应禁用 introspection
        $this->assertFalse($this->service->isIntrospectionEnabled());
    }
    
    public function testFieldValidation(): void
    {
        $query = '{ user { name email invalidField } }';
        $errors = $this->service->validateFields($query);
        
        $this->assertNotEmpty($errors);
    }
    
    public function testVariableValidation(): void
    {
        $query = 'query($id: ID!) { user(id: $id) { name } }';
        $variables = ['id' => '1'];
        
        $this->assertTrue($this->service->validateVariables($query, $variables));
        
        $invalidVariables = [];
        $this->assertFalse($this->service->validateVariables($query, $invalidVariables));
    }
    
    public function testErrorFormatting(): void
    {
        $errors = [
            ['message' => 'Field "invalid" doesn\'t exist', 'locations' => [['line' => 1, 'column' => 10]]],
        ];
        
        $formatted = $this->service->formatErrors($errors);
        
        $this->assertArrayHasKey('message', $formatted[0]);
        $this->assertArrayHasKey('locations', $formatted[0]);
    }
}
