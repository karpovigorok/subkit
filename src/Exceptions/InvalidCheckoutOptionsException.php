<?php

namespace SubKit\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the checkout hook returns options SubKit refuses to pass to the provider.
 */
class InvalidCheckoutOptionsException extends InvalidArgumentException {}
