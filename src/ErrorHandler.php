<?php

namespace AliReaza\ErrorHandler;

use Closure;
use ErrorException;
use Throwable;

/**
 * Class ErrorHandler
 *
 * @package AliReaza\ErrorHandler
 */
class ErrorHandler
{
    protected bool $debug;
    protected bool $add_trace;
    protected ?Closure $render = null;

    /**
     * @param bool $debug
     * @param bool $add_trace
     */
    public function register(bool $debug = false, bool $add_trace = false): void
    {
        $this->setDebug($debug);
        $this->addTrace($add_trace);

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatal']);
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug = true): void
    {
        $this->debug = $debug;
    }

    /**
     * @param bool $add_trace
     */
    public function addTrace(bool $add_trace = true): void
    {
        $this->add_trace = $add_trace;
    }

    /**
     * @param callable|Closure|string $render
     */
    public function setRender(callable|Closure|string $render): void
    {
        if (is_string($render)) {
            $render = new $render;
        }

        if (is_callable($render)) {
            $render = Closure::fromCallable($render);
        }

        $this->render = $render;
    }

    /**
     * @param int    $severity
     * @param string $message
     * @param string $filename
     * @param int    $line
     *
     * @throws ErrorException
     */
    public function handleError(int $severity, string $message, string $filename, int $line): void
    {
        throw new ErrorException($message, 0, $severity, $filename, $line);
    }

    /**
     * @param Throwable $exception
     */
    public function handleException(Throwable $exception): void
    {
        $errors = ['message' => 'Whoops, looks like something went wrong.'];

        if ($this->debug) {
            $errors = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($this->add_trace) {
                $errors['trace'] = $this->getTrace($exception);
            }
        }

        if (is_null($this->render)) {
            var_dump($errors);
            die();
        }

        $render = $this->render;
        $render($errors, $exception);
    }

    /**
     * @throws ErrorException
     */
    public function handleFatal(): void
    {
        $error = error_get_last();

        if ($error && error_reporting() && $error['type'] &= E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR) {
            throw new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        }
    }

    /**
     * @param Throwable $exception
     *
     * @return array
     */
    public function getTrace(Throwable $exception): array
    {
        $frames = $exception->getTrace();
        $frameData = [];

        foreach ($frames as $frame) {
            $frameData[] = json_decode(json_encode($frame), true);
        }

        return $frameData;
    }

    /**
     * @param Closure    $closure
     * @param mixed|null $result
     *
     * @return Throwable|null
     */
    public function call(Closure $closure, mixed &$result = null): ?Throwable
    {
        try {
            $result = $closure();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    /**
     * @param Throwable $throwable
     *
     * @return array
     */
    public function getMessages(Throwable $throwable): array
    {
        $messages = [];

        do {
            $messages[] = $throwable->getMessage();
        } while ($throwable = $throwable->getPrevious());

        return $messages;
    }

    /**
     * @param Closure $closure
     * @param null    $result
     *
     * @return array|null
     */
    public function getMessagesByCall(Closure $closure, &$result = null): ?array
    {
        $throw = $this->call($closure, $result);

        return is_null($throw) ? null : $this->getMessages($throw);
    }
}