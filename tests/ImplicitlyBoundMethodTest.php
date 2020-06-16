<?php

namespace Tests;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Livewire\ImplicitlyBoundMethod;
use ReflectionException;
use stdClass;

class ImplicitlyBoundMethodTest extends TestCase
{
    public function testSequentialBinding()
    {
        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'unresolvable'], ['foo', 'bar']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'bar'=>'bar'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'unresolvable'], ['foo' => 'foo', 'bar']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'bar'=>'bar'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'unresolvable'], ['bar', 'foo' => 'foo']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'bar'=>'bar'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'unresolvable'], ['bar' => 'bar', 'foo']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'bar'=>'bar'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'unresolvable'], ['foo', 'bar' => 'bar']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'bar'=>'bar'], $result);


        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'inject'], ['foo']);
        $this->assertEqualsCanonicalizing(['default'=>'foo'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'inject'], ['foo', 'bar']);
        $this->assertEqualsCanonicalizing([1=>'bar', 'default'=>'foo'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'implicit'], ['foo', 'model', 'bar']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'model'=>'model', 'bar'=>'bar'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'implicit'], ['foo', 'model', 'bar', 'baz', 'more']);
        $this->assertEqualsCanonicalizing(['foo'=>'foo', 'model'=>'model', 'bar'=>'bar', 0=>'baz', 1=>'more'], $result);

        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethodTester::testSequentialSubstitution([$stub, 'injectAndImplicit'], ['model', 'foo', 'bar', 'more']);
        $this->assertEqualsCanonicalizing([2=>'bar', 3=>'more', 'model'=>'model', 'bar'=>'foo'], $result);
    }

    public function testCallWithSequentialParameters()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@inject', ['foo']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('foo', $result[1]);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@inject', []);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@unresolvable', ['foo', 'bar']);
        $this->assertSame(['foo', 'bar'], $result);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@unresolvable', ['bar' => 'bar', 'foo']);
        $this->assertSame(['foo', 'bar'], $result);
    }

    public function testCallWithImplicitModelBinding()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@implicit', ['foo', 'injected', 'bar']);
        $this->assertSame('foo', $result[0]);
        $this->assertInstanceOf(ContainerTestModel::class, $result[1]);
        $this->assertSame(['injected'], $result[1]->value);
        $this->assertSame('bar', $result[2]);
        $this->assertSame(3, count($result));

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@implicit', ['foo', new ContainerTestModel('injected'), 'bar']);
        $this->assertSame('foo', $result[0]);
        $this->assertInstanceOf(ContainerTestModel::class, $result[1]);
        $this->assertSame(['injected'], $result[1]->value);
        $this->assertSame('bar', $result[2]);
        $this->assertSame(3, count($result));

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@implicit', ['foo', 'injected', 'bar', 'more', 'params']);
        $this->assertSame('foo', $result[0]);
        $this->assertInstanceOf(ContainerTestModel::class, $result[1]);
        $this->assertSame(['injected'], $result[1]->value);
        $this->assertSame('bar', $result[2]);
        $this->assertSame('more', $result[3]);
        $this->assertSame('params', $result[4]);
        $this->assertSame(5, count($result));

        $result = ImplicitlyBoundMethod::call($container, function (ContainerTestModel $foo, $bar = []) {
            return func_get_args();
        }, ['foo', 'bar' => 'taylor']);
        $this->assertInstanceOf(ContainerTestModel::class, $result[0]);
        $this->assertSame(['foo'], $result[0]->value);
        $this->assertSame('taylor', $result[1]);

        $result = ImplicitlyBoundMethod::call($container, function (ContainerTestModel $foo, $bar = []) {
            return func_get_args();
        }, ['foo' => new ContainerTestModel('foo'), 'bar' => 'taylor']);
        $this->assertInstanceOf(ContainerTestModel::class, $result[0]);
        $this->assertSame(['foo'], $result[0]->value);
        $this->assertSame('taylor', $result[1]);

        $result = ImplicitlyBoundMethod::call($container, function (stdClass $foo, ContainerTestModel $bar) {
            return func_get_args();
        }, [ContainerTestModel::class => 'taylor']);
        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertInstanceOf(ContainerTestModel::class, $result[1]);
        $this->assertSame(['taylor'], $result[1]->value);
    }

    public function testCallWithInjectedDependencyAndImplicitModelBinding()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@injectAndImplicit', ['injected', 'bar']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertInstanceOf(ContainerTestModel::class, $result[1]);
        $this->assertSame(['injected'], $result[1]->value);
        $this->assertSame('bar', $result[2]);
        $this->assertSame(3, count($result));
    }

    public function testCallWithInjecteDependencyNotFirst()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, 'Tests\containerTestInjectSecond', ['foo', 'bar']);
        $this->assertSame('foo', $result[0]);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[1]);
        $this->assertSame('bar', $result[2]);
    }

    public function testCallImplicitWithGlobalMethodName()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, 'Tests\containerTestImplicit');
        $this->assertInstanceOf(ContainerTestModel::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallImplicitWithStaticMethodNameString()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, 'Tests\ContainerStaticMethodStub::implicit');
        $this->assertInstanceOf(ContainerTestModel::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallImplicitWithCallableObject()
    {
        $container = new Container;
        $callable = new ContainerCallImplicitCallableStub;
        $result = ImplicitlyBoundMethod::call($container, $callable);
        $this->assertInstanceOf(ContainerTestModel::class, $result[0]);
        $this->assertSame('jeffrey', $result[1]);
    }

    /**************************************************************************
     * Everything below here is borrowed from Laravel Container testing
     * (tests/Container/ContainerCallTeset.php - with a few mods) to verify
     * ImplicitlyBoundMethod had no adverse impacts when extending BoundMethod.
     *************************************************************************/
    public function testCallWithAtSignBasedClassReferencesWithoutMethodThrowsException()
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Function ContainerTestCallStub() does not exist');

        $container = new Container;
        ImplicitlyBoundMethod::call($container, 'ContainerTestCallStub');
    }

    public function testCallWithAtSignBasedClassReferences()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@work', ['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $result);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@inject');
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@inject', ['default' => 'foo']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('foo', $result[1]);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class, ['foo', 'bar'], 'work');
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallWithCallableArray()
    {
        $container = new Container;
        $stub = new ContainerTestCallStub;
        $result = ImplicitlyBoundMethod::call($container, [$stub, 'work'], ['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallWithStaticMethodNameString()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, 'Tests\ContainerStaticMethodStub::inject');
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallWithGlobalMethodName()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, 'Tests\containerTestInject');
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallWithBoundMethod()
    {
        $container = new Container;
        $container->bindMethod(ContainerTestCallStub::class.'@unresolvable', function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@unresolvable');
        $this->assertEquals(['foo', 'bar'], $result);

        $container = new Container;
        $container->bindMethod(ContainerTestCallStub::class.'@unresolvable', function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = ImplicitlyBoundMethod::call($container, [new ContainerTestCallStub, 'unresolvable']);
        $this->assertEquals(['foo', 'bar'], $result);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, [new ContainerTestCallStub, 'inject'], ['_stub' => 'foo', 'default' => 'bar']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('bar', $result[1]);

        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, [new ContainerTestCallStub, 'inject'], ['_stub' => 'foo']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testBindMethodAcceptsAnArray()
    {
        $container = new Container;
        $container->bindMethod([ContainerTestCallStub::class, 'unresolvable'], function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@unresolvable');
        $this->assertEquals(['foo', 'bar'], $result);

        $container = new Container;
        $container->bindMethod([ContainerTestCallStub::class, 'unresolvable'], function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = ImplicitlyBoundMethod::call($container, [new ContainerTestCallStub, 'unresolvable']);
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testClosureCallWithInjectedDependency()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, function (ContainerCallConcreteStub $stub) {
            return func_get_args();
        }, ['foo' => 'bar']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertEquals([], $result[0]->value);

        $result = ImplicitlyBoundMethod::call($container, function (ContainerCallConcreteStub $stub) {
            return func_get_args();
        }, ['foo' => 'bar', 'stub' => new ContainerCallConcreteStub('baz')]);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertEquals(['baz'], $result[0]->value);
    }

    public function testCallWithDependencies()
    {
        $container = new Container;
        $result = ImplicitlyBoundMethod::call($container, function (stdClass $foo, $bar = []) {
            return func_get_args();
        });

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertEquals([], $result[1]);

        $result = ImplicitlyBoundMethod::call($container, function (stdClass $foo, $bar = []) {
            return func_get_args();
        }, ['bar' => 'taylor']);

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertSame('taylor', $result[1]);

        $stub = new ContainerCallConcreteStub;
        $result = ImplicitlyBoundMethod::call($container, function (stdClass $foo, ContainerCallConcreteStub $bar) {
            return func_get_args();
        }, [ContainerCallConcreteStub::class => $stub]);

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertSame($stub, $result[1]);

        /*
         * Wrap a function...
         */
        $result = $container->wrap(function (stdClass $foo, $bar = []) {
            return func_get_args();
        }, ['bar' => 'taylor']);

        $this->assertInstanceOf(Closure::class, $result);
        $result = $result();

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallWithCallableObject()
    {
        $container = new Container;
        $callable = new ContainerCallCallableStub;
        $result = ImplicitlyBoundMethod::call($container, $callable);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('jeffrey', $result[1]);
    }

    public function testCallWithoutRequiredParamsThrowsException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class Tests\ContainerTestCallStub');

        $container = new Container;
        ImplicitlyBoundMethod::call($container, ContainerTestCallStub::class.'@unresolvable');
    }

    public function testCallWithoutRequiredParamsOnClosureThrowsException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class Tests\ImplicitlyBoundMethodTest');

        $container = new Container;
        $foo = ImplicitlyBoundMethod::call($container, function ($foo, $bar = 'default') {
            return $foo;
        });
    }
}

class ContainerTestCallStub
{
    public function work()
    {
        return func_get_args();
    }

    public function inject(ContainerCallConcreteStub $stub, $default = 'taylor')
    {
        return func_get_args();
    }

    public function unresolvable($foo, $bar)
    {
        return func_get_args();
    }

    // added for ImplicitlyBoundMethod
    public function implicit($foo, ContainerTestModel $model, $bar, ...$params)
    {
        return func_get_args();
    }

    public function injectAndImplicit(ContainerCallConcreteStub $stub, ContainerTestModel $model, $bar = 'taylor')
    {
        return func_get_args();
    }

}

class ImplicitlyBoundMethodTester extends ImplicitlyBoundMethod
{
    public static function testSequentialSubstitution($callback, array $parameters = [])
    {
        $paramIndex = 0;
        foreach (static::getCallReflector($callback)->getParameters() as $parameter) {
            static::substituteNameBindingForCallParameter($parameter, $parameters, $paramIndex);
        }
        return $parameters;
    }
}

class ContainerTestModel extends \Illuminate\Database\Eloquent\Model
{
    public function __construct()
    {
        $this->value = func_get_args();
    }
    public function resolveRouteBinding($value, $field = null)
    {
        $this->value = func_get_args();
        return $this;
    }
}

class ContainerCallConcreteStub
{
    public $value;
    public function __construct()
    {
        $this->value = func_get_args();
    }
}

function containerTestInject(ContainerCallConcreteStub $stub, $default = 'taylor')
{
    return func_get_args();
}

function containerTestInjectSecond($foo, ContainerCallConcreteStub $stub, $default = 'taylor')
{
    return func_get_args();
}

function containerTestImplicit(ContainerTestModel $stub, $default = 'taylor')
{
    return func_get_args();
}

class ContainerStaticMethodStub
{
    public static function inject(ContainerCallConcreteStub $stub, $default = 'taylor')
    {
        return func_get_args();
    }

    public static function implicit(ContainerTestModel $stub, $default = 'taylor')
    {
        return func_get_args();
    }
}

class ContainerCallCallableStub
{
    public function __invoke(ContainerCallConcreteStub $stub, $default = 'jeffrey')
    {
        return func_get_args();
    }
}

class ContainerCallImplicitCallableStub
{
    public function __invoke(ContainerTestModel $stub, $default = 'jeffrey')
    {
        return func_get_args();
    }
}
