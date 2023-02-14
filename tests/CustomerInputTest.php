<?php

use Gitplus\PayFluid\CustomerInput;
use PHPUnit\Framework\TestCase;

class CustomerInputTest extends TestCase
{
    public function testLabelGetsSet()
    {
        $input = new CustomerInput();
        $this->assertEmpty($input->getLabel());

        $input->label("Custom Label");
        $this->assertEquals("Custom Label", $input->getLabel());

        $input->label("Label with trailing whitespace   ");
        $this->assertEquals("Label with trailing whitespace", $input->getLabel());

        $input->label("      Label with leading whitespace");
        $this->assertEquals("Label with leading whitespace", $input->getLabel());

        $input->label("      Label with leading and trailing whitespace  ");
        $this->assertEquals("Label with leading and trailing whitespace", $input->getLabel());
    }

    public function testGetLabel()
    {
        $input = new CustomerInput();
        $this->assertEmpty($input->getLabel());
    }

    public function testSetOption()
    {
        $input = new CustomerInput();
        $this->assertEmpty($input->getOptions());

        $input->setOption("key", "val");
        $this->assertIsArray($input->getOptions());
        $this->assertCount(1, $input->getOptions());

        $input->setOption("key2", "val2");
        $this->assertCount(2, $input->getOptions());
    }

    public function testSetOptionThrowsExceptionOnEmptyKey()
    {
        $input = new CustomerInput();
        $this->expectException(Exception::class);
        $input->setOption("", "value");
    }

    public function testThrowExceptionUnknownInputType()
    {
        $input = new CustomerInput();
        $this->assertEmpty($input->getType());

        $this->expectException(Exception::class);
        $input->type("unknown type");
    }

    public function testGetPlaceholder()
    {
        $input = new CustomerInput();
        $this->assertEmpty($input->getPlaceholder());

        $input->placeholder("Placeholder");
        $this->assertEquals("Placeholder", $input->getPlaceholder());
    }

    public function testRequired()
    {
        $input = new CustomerInput();
        $this->assertFalse($input->getRequired());

        $input->required(true);
        $this->assertTrue($input->getRequired());

    }
}