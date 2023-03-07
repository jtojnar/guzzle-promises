<?php

namespace GuzzleHttp\Promise;

/**
 * Promises/A+ implementation that avoids recursion when possible.
 *
 * @link https://promisesaplus.com/
 *
 * @template ValueType
 * @template ReasonType
 * @internal
 */
class PromiseHandler
{
    /** @var PromiseInterface<ValueType, ReasonType> */
    private $promise;
    /** @var ?callable(ValueType): (ValueType|PromiseInterface<ValueType, ReasonType>) */
    private $onFulfilled;
    /** @var ?callable(ReasonType): (ValueType|PromiseInterface<ValueType, ReasonType>) */
    private $onRejected;
    /**
     * @param PromiseInterface<ValueType, ReasonType> $promise
     * @param ?callable(ValueType): (ValueType|PromiseInterface<ValueType, ReasonType>) $onFulfilled Fn that when invoked resolves the promise.
     * @param ?callable(ReasonType): (ValueType|PromiseInterface<ValueType, ReasonType>) $onRejected Fn that when invoked cancels the promise.
     */
    public function __construct(
        PromiseInterface $promise,
        callable $onFulfilled = null,
        callable $onRejected = null
    ) {
        $this->promise = $promise;
        $this->onFulfilled = $onFulfilled;
        $this->onRejected = $onRejected;
    }

    /**
     * Call a stack of handlers using a specific callback index and value.
     *
     * @param 1|2   $index   1 (resolve) or 2 (reject).
     * @param ($index is 1 ? ValueType : ReasonType) $value   Value to pass to the callback.
     */
    public static function call($index, $value)
    {
        // The promise may have been cancelled or resolved before placing
        // this thunk in the queue.
        if (Is::settled($this->promise)) {
            return;
        }
        $handler = $index === 1 ? $this->onFulfilled : $this->onRejected;

        try {
            if ($handler !== null) {
                // TODO: Verify this comment
                /*
                 * If $f throws an exception, then $handler will be in the exception
                 * stack trace. Since $handler contains a reference to the callable
                 * itself we get a circular reference. We clear the $handler
                 * here to avoid that memory leak.
                 */
                unset($handler);
                $this->promise->resolve($handler($value));
            } elseif ($index === 1) {
                // Forward resolution values as-is.
                $this->promise->resolve($value);
            } else {
                // Forward rejections down the chain.
                $this->promise->reject($value);
            }
        } catch (\Throwable $reason) {
            $this->promise->reject($reason);
        } catch (\Exception $reason) {
            $this->promise->reject($reason);
        }
    }
}
