<?php

namespace Coff\DecisionTree\Test;

use Coff\DecisionTree\DecisionNode;
use PHPUnit\Framework\TestCase;

class A {
    public $aValue;
};

class B {
    public A $a;
}

class ContextClass {
    public int $contextValue;
}

class DecisionNodeTest extends TestCase
{
    public function testAssertThrowsException(): void
    {
        $node = new DecisionNode(fn (object $obj) => new \Exception('Message'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Message');

        $node->assert(new class() {});
    }

    public function testAssertReturnsValue(): void
    {
        $node = new DecisionNode(fn (object $obj) => 'someValue');

        $result = $node->assert(new class() {});

        $this->assertEquals('someValue', $result);
    }

    public function testAssertExecutesCallback(): void
    {
        $node = new DecisionNode(fn (object $obj) => fn () => 'called');

        $result = $node->assert(new class() {});

        $this->assertEquals('called', $result);
    }

    public function testAssertReadsProperty(): void
    {
        $node = new DecisionNode(fn (object $obj) => $obj->a);

        $result = $node->assert(new class() {
            public int $a = 5;
        });

        $this->assertEquals(5, $result);
    }

    public function testAssertChainsAssertions(): void
    {
        $node1 = new DecisionNode(fn (object $obj) => true);
        $node2 = new DecisionNode(fn (object $obj) => $node1);
        $node3 = new DecisionNode(fn (object $obj) => $node2);

        $result = $node3->assert(new class() {});

        $this->assertEquals(true, $result);
    }

    public function testAssertDivergesBetweenBranches(): void
    {
        $node1a = new DecisionNode(fn (object $obj) => true);
        $node1b = new DecisionNode(fn (object $obj) => false);
        $node2 = new DecisionNode(fn (object $obj) => 'yes' === $obj->a ? $node1a : $node1b);
        $node3 = new DecisionNode(fn (object $obj) => $node2);

        $result = $node3->assert(new class() {
            public $a = 'yes';
        });

        $this->assertEquals(true, $result);

        $result = $node3->assert(new class() {
            public $a = 'no';
        });

        $this->assertEquals(false, $result);
    }

    public function testAssertWithExtractionCallback(): void
    {

        $node = new DecisionNode(
            callback: fn (A $a) => $a->aValue,
            extract: fn (B $b) => $b->a,
        );

        $b = new B;
        $b->a = new A;
        $b->a->aValue = 5;

        $result = $node->assert($b);

        $this->assertEquals(5, $result);
    }

    public function testAssertWithExtractionCallbackOnSubnode(): void
    {

        $node1 = new DecisionNode(
            callback: fn (A $a) => $a->aValue,
            extract: fn (B $b) => $b->a,
        );

        $node2 = new DecisionNode( fn(B $b) => $node1 );

        $b = new B;
        $b->a = new A;
        $b->a->aValue = 5;

        $result = $node2->assert($b);

        $this->assertEquals(5, $result);
    }

    public function testAssertWithContextPassed(): void
    {
        $node2 = new DecisionNode(
            callback: fn (object $obj, ContextClass $context) => $context->contextValue * 2,
        );

        $node1 = new DecisionNode(
            callback: function (object $obj, ContextClass $context) use ($node2) {
                $context->contextValue = 5;
                return $node2;
            },
        );

        $result = $node1->assert(new class {}, new ContextClass());

        $this->assertEquals(10, $result);

    }
}
