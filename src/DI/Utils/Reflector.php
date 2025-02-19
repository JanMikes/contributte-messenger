<?php declare(strict_types = 1);

namespace Contributte\Messenger\DI\Utils;

use Contributte\Messenger\Exception\LogicalException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class Reflector
{

	/**
	 * @param class-string $class
	 */
	public static function getMessageHandler(string $class): ?AsMessageHandler
	{
		$rc = new ReflectionClass($class);

		$attributes = $rc->getAttributes(AsMessageHandler::class);

		// No #[AsMessageHandler] attribute
		if (count($attributes) <= 0) {
			return null;
		}

		// Validate multi-usage of #[AsMessageHandler]
		if (count($attributes) > 1) {
			throw new LogicalException(sprintf('Only attribute #[AsMessageHandler] can be used on class "%s"', $class));
		}

		return $attributes[0]->newInstance();
	}

	/**
	 * @param class-string $class
	 * @param array{method: string} $options
	 */
	public static function getMessageHandlerMessage(string $class, array $options): string
	{
		try {
			$rc = new ReflectionClass($class);
		} catch (ReflectionException $e) {
			throw new LogicalException(sprintf('Handler "%s" class not found', $class), 0, $e);
		}

		try {
			$rcMethod = $rc->getMethod($options['method']);
		} catch (ReflectionException) {
			throw new LogicalException(sprintf('Handler must have "%s::%s()" method.', $class, $options['method']));
		}

		if ($rcMethod->getNumberOfParameters() !== 1) {
			throw new LogicalException(sprintf('Only one parameter is allowed in "%s::%s()."', $class, $options['method']));
		}

		/** @var ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type */
		$type = $rcMethod->getParameters()[0]->getType();

		if ($type === null) {
			throw new LogicalException(sprintf('Cannot detect parameter type for "%s::%s()."', $class, $options['method']));
		}

		if ($type instanceof ReflectionUnionType) {
			throw new LogicalException(sprintf('Union parameter type for "%s::%s() is not supported."', $class, $options['method']));
		}

		if ($type instanceof ReflectionIntersectionType) {
			throw new LogicalException(sprintf('Intersection parameter type for "%s::%s() is not supported."', $class, $options['method']));
		}

		return $type->getName();
	}

}
