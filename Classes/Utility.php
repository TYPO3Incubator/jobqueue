<?php
namespace TYPO3Incubator\Jobqueue;

class Utility
{

    /**
     * @param string $handler
     * @return bool
     */
    public static function validHandler($handler)
    {
        return preg_match('/^(\\w\\\\?)*(->|::)(\\w)+$/', $handler) === 1;
    }

    /**
     * @param string $handler
     * @return bool
     */
    public static function isStaticMethodHandler($handler)
    {
        return preg_match('/^(\\w\\\\?)*(::)(\\w)+$/', $handler) === 1;
    }

    /**
     * @param string $handler
     * @return array
     */
    public static function extractHandlerClassAndMethod($handler)
    {
        if (!self::validHandler($handler)) {
            throw new \InvalidArgumentException("Invalid handler reference '{$handler}'");
        }
        $parts = preg_split('/->|::/', $handler);
        return [
            'class' => $parts[0],
            'method' => $parts[1]
        ];
    }

    /**
     *
     * @param string $json
     * @return Message
     */
    public static function parseMessage($json = null)
    {
        if ($json === null) {
            // prevent a blocking read if nothing was actually send via STDIN
            socket_set_blocking(STDIN, false);
            $json = fgets(STDIN);
            // restore blocking mode
            socket_set_blocking(STDIN, true);
        }
        $payload = $json;
        while (!is_array($payload) && !is_null($payload)) {
            $payload = json_decode($payload, true);
        }
        if ($payload === null) {
            $error = json_last_error_msg();
            throw new \InvalidArgumentException("JSON could not be parsed '{$error}'");
        }
        if (!isset($payload['handler']) || !isset($payload['data']) || !isset($payload['attempts']) || !isset($payload['nextexecution'])) {
            #throw new \InvalidArgumentException("JSON does not contain the required information '".var_export($payload, true).'\'');
        }
        return (new Message($payload['handler'], $payload['data']))
            ->setNextExecution($payload['nextexecution'])
            ->setAttempts($payload['attempts']);
    }

    /**
     * @param array $signals
     * @param callable $callback
     */
    public static function applySignalHandling($signals, $callback)
    {
        if (!extension_loaded('pcntl')) {
            throw new \RuntimeException('Extension pcntl not loaded! Can\'t enable signal handling.');
        }
        declare(ticks = 1);
        foreach ($signals as $signal) {
            pcntl_signal($signal, $callback, false);
        }
    }

}
